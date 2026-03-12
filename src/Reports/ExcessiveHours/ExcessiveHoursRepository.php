<?php
declare(strict_types=1);

/**
 * WeCoza Core - Excessive Hours Repository
 *
 * Data access layer for excessive training hours detection and resolution tracking.
 * Detection is live (no cron) — queries learner_lp_tracking in real-time.
 * Only resolution records are persisted.
 *
 * @package WeCoza\Reports\ExcessiveHours
 * @since 1.0.0
 */

namespace WeCoza\Reports\ExcessiveHours;

use WeCoza\Core\Database\PostgresConnection;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ExcessiveHoursRepository
{
    private PostgresConnection $db;

    /**
     * Resolutions table name
     */
    private const TABLE = 'excessive_hours_resolutions';

    /**
     * Allowed action_taken values (whitelist for security)
     */
    public const ALLOWED_ACTIONS = [
        'contacted_facilitator',
        'qa_visit_arranged',
        'other',
    ];

    public function __construct()
    {
        $this->db = PostgresConnection::getInstance();
    }

    /**
     * Find all in_progress LPs with excessive hours for applicable class types.
     *
     * Live query — runs on every request, always returns current data.
     * Uses LATERAL JOIN to fetch the latest resolution per tracking_id.
     * Resolution is considered "active" if created within the last 30 days.
     *
     * @param array $filters Optional filters: status (open|resolved|all), client_id, class_type_code, search
     * @param string $orderBy Column to sort by
     * @param string $orderDir Sort direction (ASC|DESC)
     * @param int $limit Page size
     * @param int $offset Offset for pagination
     * @return array{data: array, total: int, open_count: int, resolved_count: int}
     */
    public function findFlagged(
        array $filters = [],
        string $orderBy = 'overage_hours',
        string $orderDir = 'DESC',
        int $limit = 50,
        int $offset = 0
    ): array {
        $pdo = $this->db->getPdo();

        // Whitelist order columns
        $allowedOrderColumns = [
            'overage_hours', 'hours_trained', 'subject_duration',
            'learner_name', 'class_code', 'class_type_name', 'client_name',
            'resolved_at', 'created_at',
        ];
        if (!in_array($orderBy, $allowedOrderColumns, true)) {
            $orderBy = 'overage_hours';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $baseFrom = $this->buildBaseQuery();
        $where = $this->buildWhereClause($filters);

        // Count totals (open vs resolved) before applying status filter
        $countSql = "
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE ehr.resolution_id IS NULL OR ehr.created_at < NOW() - INTERVAL '30 days') AS open_count,
                COUNT(*) FILTER (WHERE ehr.resolution_id IS NOT NULL AND ehr.created_at >= NOW() - INTERVAL '30 days') AS resolved_count
            {$baseFrom}
            {$where['sql']}
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($where['params']);
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

        // Apply status filter for the data query
        $statusWhere = '';
        if (isset($filters['status'])) {
            if ($filters['status'] === 'open') {
                $statusWhere = " AND (ehr.resolution_id IS NULL OR ehr.created_at < NOW() - INTERVAL '30 days')";
            } elseif ($filters['status'] === 'resolved') {
                $statusWhere = " AND (ehr.resolution_id IS NOT NULL AND ehr.created_at >= NOW() - INTERVAL '30 days')";
            }
            // 'all' = no additional filter
        }

        $dataSql = "
            SELECT
                lpt.tracking_id,
                lpt.learner_id,
                lpt.hours_trained,
                lpt.hours_present,
                lpt.hours_absent,
                lpt.start_date,
                cts.subject_duration,
                cts.subject_name,
                cts.subject_code,
                (lpt.hours_trained - cts.subject_duration) AS overage_hours,
                ROUND(((lpt.hours_trained - cts.subject_duration) / cts.subject_duration) * 100, 1) AS overage_pct,
                CONCAT(l.first_name, ' ', l.surname) AS learner_name,
                c.class_code,
                c.class_id,
                ct.class_type_name,
                ct.class_type_code,
                cl.client_name,
                cl.client_id,
                ehr.resolution_id,
                ehr.action_taken,
                ehr.resolution_notes,
                ehr.resolved_by,
                ehr.created_at AS resolved_at,
                CASE
                    WHEN ehr.resolution_id IS NOT NULL AND ehr.created_at >= NOW() - INTERVAL '30 days'
                    THEN 'resolved'
                    ELSE 'open'
                END AS flag_status
            {$baseFrom}
            {$where['sql']}
            {$statusWhere}
            ORDER BY {$orderBy} {$orderDir}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($dataSql);
        foreach ($where['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int) ($counts['total'] ?? 0),
            'open_count' => (int) ($counts['open_count'] ?? 0),
            'resolved_count' => (int) ($counts['resolved_count'] ?? 0),
        ];
    }

    /**
     * Count open (unresolved) excessive hours flags.
     *
     * Lightweight query for SystemPulse attention items — no joins to names/clients.
     *
     * @return int Number of open flags
     */
    public function countOpen(): int
    {
        $pdo = $this->db->getPdo();

        $sql = "
            SELECT COUNT(*)
            FROM learner_lp_tracking lpt
            INNER JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            INNER JOIN classes c ON lpt.class_id = c.class_id
            INNER JOIN class_types ct ON c.class_type = ct.class_type_code
            LEFT JOIN LATERAL (
                SELECT r.resolution_id, r.created_at
                FROM excessive_hours_resolutions r
                WHERE r.tracking_id = lpt.tracking_id
                ORDER BY r.created_at DESC LIMIT 1
            ) ehr ON TRUE
            WHERE lpt.status = 'in_progress'
              AND cts.subject_duration > 0
              AND lpt.hours_trained > cts.subject_duration
              AND ct.class_type_code IN ('AET','REALLL','GETC','BA2','BA3','BA4','ASC')
              AND (ehr.resolution_id IS NULL OR ehr.created_at < NOW() - INTERVAL '30 days')
        ";

        return (int) $pdo->query($sql)->fetchColumn();
    }

    /**
     * Create a resolution record for a flagged learner.
     *
     * @param int $trackingId LP tracking record ID
     * @param string $actionTaken One of ALLOWED_ACTIONS
     * @param string|null $resolutionNotes Free-text notes
     * @param int $resolvedBy WordPress user ID
     * @return int The new resolution_id
     * @throws Exception if action_taken is not in whitelist or tracking_id doesn't exist
     */
    public function createResolution(
        int $trackingId,
        string $actionTaken,
        ?string $resolutionNotes,
        int $resolvedBy
    ): int {
        if (!in_array($actionTaken, self::ALLOWED_ACTIONS, true)) {
            throw new Exception("Invalid action_taken value: {$actionTaken}");
        }

        $pdo = $this->db->getPdo();

        // Verify tracking_id exists
        $checkStmt = $pdo->prepare("SELECT tracking_id FROM learner_lp_tracking WHERE tracking_id = :id");
        $checkStmt->execute([':id' => $trackingId]);
        if (!$checkStmt->fetch()) {
            throw new Exception("Tracking record not found: {$trackingId}");
        }

        // Check for duplicate resolution within last 24 hours
        $dupeStmt = $pdo->prepare("
            SELECT resolution_id, resolved_by
            FROM excessive_hours_resolutions
            WHERE tracking_id = :tracking_id
              AND created_at > NOW() - INTERVAL '24 hours'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $dupeStmt->execute([':tracking_id' => $trackingId]);
        $recent = $dupeStmt->fetch(PDO::FETCH_ASSOC);

        if ($recent) {
            throw new Exception("Already resolved within the last 24 hours (resolution #{$recent['resolution_id']}).");
        }

        $stmt = $pdo->prepare("
            INSERT INTO excessive_hours_resolutions (tracking_id, action_taken, resolution_notes, resolved_by)
            VALUES (:tracking_id, :action_taken, :resolution_notes, :resolved_by)
            RETURNING resolution_id
        ");

        $stmt->execute([
            ':tracking_id' => $trackingId,
            ':action_taken' => $actionTaken,
            ':resolution_notes' => $resolutionNotes,
            ':resolved_by' => $resolvedBy,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get resolution history for a specific tracking record.
     *
     * @param int $trackingId
     * @return array
     */
    public function getResolutionHistory(int $trackingId): array
    {
        $pdo = $this->db->getPdo();

        $stmt = $pdo->prepare("
            SELECT
                r.resolution_id,
                r.action_taken,
                r.resolution_notes,
                r.resolved_by,
                r.created_at
            FROM excessive_hours_resolutions r
            WHERE r.tracking_id = :tracking_id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([':tracking_id' => $trackingId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Build the FROM/JOIN portion of the excessive hours query.
     *
     * Uses INNER JOIN on classes/class_types (we require a class type match).
     * Uses LATERAL for latest resolution per tracking_id.
     *
     * @return string SQL FROM clause
     */
    private function buildBaseQuery(): string
    {
        return "
            FROM learner_lp_tracking lpt
            INNER JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            INNER JOIN learners l ON lpt.learner_id = l.id
            INNER JOIN classes c ON lpt.class_id = c.class_id
            INNER JOIN class_types ct ON c.class_type = ct.class_type_code
            LEFT JOIN clients cl ON c.client_id = cl.client_id
            LEFT JOIN LATERAL (
                SELECT r.resolution_id, r.action_taken, r.resolution_notes, r.resolved_by, r.created_at
                FROM excessive_hours_resolutions r
                WHERE r.tracking_id = lpt.tracking_id
                ORDER BY r.created_at DESC LIMIT 1
            ) ehr ON TRUE
        ";
    }

    /**
     * Build WHERE clause with optional filters.
     *
     * @param array $filters
     * @return array{sql: string, params: array}
     */
    private function buildWhereClause(array $filters): array
    {
        $conditions = [
            "lpt.status = 'in_progress'",
            "cts.subject_duration > 0",
            "lpt.hours_trained > cts.subject_duration",
            "ct.class_type_code IN ('AET','REALLL','GETC','BA2','BA3','BA4','ASC')",
        ];
        $params = [];

        if (!empty($filters['client_id'])) {
            $conditions[] = "cl.client_id = :client_id";
            $params[':client_id'] = (int) $filters['client_id'];
        }

        if (!empty($filters['class_type_code'])) {
            // Validate against known applicable types
            $allowed = ['AET', 'REALLL', 'GETC', 'BA2', 'BA3', 'BA4', 'ASC'];
            if (in_array($filters['class_type_code'], $allowed, true)) {
                $conditions[] = "ct.class_type_code = :class_type_code";
                $params[':class_type_code'] = $filters['class_type_code'];
            }
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(
                CONCAT(l.first_name, ' ', l.surname) ILIKE :search
                OR c.class_code ILIKE :search
                OR cl.client_name ILIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql = " WHERE " . implode(" AND ", $conditions);

        return ['sql' => $sql, 'params' => $params];
    }
}
