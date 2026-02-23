<?php
declare(strict_types=1);

/**
 * WeCoza Core - Learner Progression Repository
 *
 * Data access layer for learner LP progression tracking.
 * Handles all database operations for the learner_lp_tracking table.
 *
 * @package WeCoza\Learners\Repositories
 * @since 1.0.0
 */

namespace WeCoza\Learners\Repositories;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Abstract\BaseRepository;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class LearnerProgressionRepository extends BaseRepository
{
    // quoteIdentifier: all column names in this repository are hardcoded literals (safe)

    /**
     * Table name
     */
    protected static string $table = 'learner_lp_tracking';

    /**
     * Primary key column
     */
    protected static string $primaryKey = 'tracking_id';

    /**
     * Base query with common joins
     */
    private function baseQuery(): string
    {
        // Complex query: 5-table JOIN for full progression context (includes client via class)
        return "
            SELECT
                lpt.*,
                cts.subject_name,
                cts.subject_duration,
                CONCAT(l.first_name, ' ', l.surname) AS learner_name,
                c.class_code,
                cl.client_id,
                cl.client_name
            FROM learner_lp_tracking lpt
            LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            LEFT JOIN learners l ON lpt.learner_id = l.id
            LEFT JOIN classes c ON lpt.class_id = c.class_id
            LEFT JOIN clients cl ON c.client_id = cl.client_id
        ";
    }

    /**
     * Find progression by ID
     */
    public function findById(int $trackingId): ?array
    {
        $sql = $this->baseQuery() . " WHERE lpt.tracking_id = :tracking_id";

        try {
            $stmt = $this->db->query($sql, ['tracking_id' => $trackingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find current (in_progress) LP for a learner
     */
    public function findCurrentForLearner(int $learnerId): ?array
    {
        // Complex query: base query + status filter (uses baseQuery JOINs)
        $sql = $this->baseQuery() . "
            WHERE lpt.learner_id = :learner_id
            AND lpt.status = 'in_progress'
            LIMIT 1
        ";

        try {
            $stmt = $this->db->query($sql, ['learner_id' => $learnerId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findCurrentForLearner error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find all progressions for a learner
     */
    public function findAllForLearner(int $learnerId): array
    {
        // Complex query: base query + learner filter (uses baseQuery JOINs)
        $sql = $this->baseQuery() . "
            WHERE lpt.learner_id = :learner_id
            ORDER BY lpt.start_date DESC
        ";

        try {
            $stmt = $this->db->query($sql, ['learner_id' => $learnerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findAllForLearner error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find completed progressions for a learner (history)
     */
    public function findHistoryForLearner(int $learnerId): array
    {
        // Complex query: base query + completed status filter (uses baseQuery JOINs)
        $sql = $this->baseQuery() . "
            WHERE lpt.learner_id = :learner_id
            AND lpt.status = 'completed'
            ORDER BY lpt.completion_date DESC
        ";

        try {
            $stmt = $this->db->query($sql, ['learner_id' => $learnerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findHistoryForLearner error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find progressions by class
     */
    public function findByClass(int $classId, ?string $status = null): array
    {
        // Complex query: base query + class filter with optional status (uses baseQuery JOINs)
        $sql = $this->baseQuery() . " WHERE lpt.class_id = :class_id";
        $params = ['class_id' => $classId];

        if ($status) {
            $sql .= " AND lpt.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY l.surname, l.first_name";

        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findByClass error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find progressions by class type subject
     */
    public function findByClassTypeSubject(int $classTypeSubjectId, ?string $status = null): array
    {
        // Complex query: base query + subject filter with optional status (uses baseQuery JOINs)
        $sql = $this->baseQuery() . " WHERE lpt.class_type_subject_id = :class_type_subject_id";
        $params = ['class_type_subject_id' => $classTypeSubjectId];

        if ($status) {
            $sql .= " AND lpt.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY lpt.start_date DESC";

        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findByClassTypeSubject error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Insert new progression
     */
    public function insert(array $data): ?int
    {
        // Complex query: custom column whitelist with transaction and cache clear
        $columns = [
            'learner_id', 'class_type_subject_id', 'class_id',
            'hours_trained', 'hours_present', 'hours_absent',
            'status', 'start_date', 'notes',
            'created_at', 'updated_at'
        ];

        $filteredData = array_intersect_key($data, array_flip($columns));

        if (!isset($filteredData['start_date'])) {
            $filteredData['start_date'] = wp_date('Y-m-d');
        }
        if (!isset($filteredData['status'])) {
            $filteredData['status'] = 'in_progress';
        }

        $columnList = implode(', ', array_keys($filteredData));
        $placeholders = ':' . implode(', :', array_keys($filteredData));

        $sql = "INSERT INTO learner_lp_tracking ($columnList) VALUES ($placeholders) RETURNING tracking_id";

        try {
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($sql);
            $stmt->execute($filteredData);
            $trackingId = $stmt->fetchColumn();

            $pdo->commit();
            delete_transient('learner_progressions_cache');

            return (int) $trackingId;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("WeCoza Core: LearnerProgressionRepository insert error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update existing progression
     */
    public function update(int $trackingId, array $data): bool
    {
        // Complex query: custom column whitelist with cache clear
        $columns = [
            'hours_trained', 'hours_present', 'hours_absent',
            'status', 'completion_date',
            'portfolio_file_path', 'portfolio_uploaded_at',
            'marked_complete_by', 'marked_complete_date',
            'notes', 'updated_at'
        ];

        $filteredData = array_intersect_key($data, array_flip($columns));
        $filteredData['updated_at'] = current_time('mysql');

        $setParts = [];
        foreach (array_keys($filteredData) as $column) {
            $setParts[] = "$column = :$column";
        }
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE learner_lp_tracking SET $setClause WHERE tracking_id = :tracking_id";
        $filteredData['tracking_id'] = $trackingId;

        try {
            $stmt = $this->db->query($sql, $filteredData);
            delete_transient('learner_progressions_cache');
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete progression
     */
    public function delete(int $trackingId): bool
    {
        // Complex query: manual DELETE with cache clear (could use parent but needs cache clear)
        $sql = "DELETE FROM learner_lp_tracking WHERE tracking_id = :tracking_id";

        try {
            $stmt = $this->db->query($sql, ['tracking_id' => $trackingId]);
            delete_transient('learner_progressions_cache');
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log hours to the hours log table
     */
    public function logHours(array $data): bool
    {
        // Complex query: operates on learner_hours_log table (not $table)
        $columns = [
            'learner_id', 'class_type_subject_id', 'class_id', 'tracking_id',
            'log_date', 'hours_trained', 'hours_present',
            'source', 'session_id', 'created_by', 'notes'
        ];

        $filteredData = array_intersect_key($data, array_flip($columns));
        $filteredData['created_at'] = current_time('mysql');

        $columnList = implode(', ', array_keys($filteredData));
        $placeholders = ':' . implode(', :', array_keys($filteredData));

        $sql = "INSERT INTO learner_hours_log ($columnList) VALUES ($placeholders)";

        try {
            $this->db->query($sql, $filteredData);
            return true;
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository logHours error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get hours log for a tracking ID
     */
    public function getHoursLog(int $trackingId): array
    {
        // Complex query: reads from learner_hours_log table (not $table)
        $sql = "
            SELECT * FROM learner_hours_log
            WHERE tracking_id = :tracking_id
            ORDER BY log_date DESC, created_at DESC
        ";

        try {
            $stmt = $this->db->query($sql, ['tracking_id' => $trackingId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository getHoursLog error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get hours log for a learner
     */
    public function getHoursLogForLearner(int $learnerId, ?string $startDate = null, ?string $endDate = null): array
    {
        // Complex query: JOIN learner_hours_log + class_type_subjects with date range filter
        $sql = "
            SELECT lhl.*, cts.subject_name
            FROM learner_hours_log lhl
            LEFT JOIN class_type_subjects cts ON lhl.class_type_subject_id = cts.class_type_subject_id
            WHERE lhl.learner_id = :learner_id
        ";
        $params = ['learner_id' => $learnerId];

        if ($startDate) {
            $sql .= " AND lhl.log_date >= :start_date";
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND lhl.log_date <= :end_date";
            $params['end_date'] = $endDate;
        }

        $sql .= " ORDER BY lhl.log_date DESC, lhl.created_at DESC";

        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository getHoursLogForLearner error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monthly progressions report
     */
    public function getMonthlyProgressions(int $year, int $month): array
    {
        // Complex query: 5-table JOIN with date range for monthly report
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = wp_date('Y-m-t', strtotime($startDate));

        $sql = "
            SELECT
                lpt.*,
                cts.subject_name,
                CONCAT(l.first_name, ' ', l.surname) AS learner_name,
                c.class_code,
                cl.client_name
            FROM learner_lp_tracking lpt
            LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            LEFT JOIN learners l ON lpt.learner_id = l.id
            LEFT JOIN classes c ON lpt.class_id = c.class_id
            LEFT JOIN clients cl ON c.client_id = cl.client_id
            WHERE lpt.completion_date BETWEEN :start_date AND :end_date
            AND lpt.status = 'completed'
            ORDER BY lpt.completion_date DESC
        ";

        try {
            $stmt = $this->db->query($sql, [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository getMonthlyProgressions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find progressions with filters (for admin panel)
     */
    public function findWithFilters(array $filters = [], int $limit = AppConstants::DEFAULT_PAGE_SIZE, int $offset = 0): array
    {
        // Complex query: dynamic JOIN + multi-criteria filter with pagination
        $sql = $this->baseQuery();
        $conditions = [];
        $params = [];

        if (!empty($filters['client_id'])) {
            $conditions[] = "cl.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        if (!empty($filters['class_id'])) {
            $conditions[] = "lpt.class_id = :class_id";
            $params['class_id'] = $filters['class_id'];
        }

        if (!empty($filters['class_type_subject_id'])) {
            $conditions[] = "lpt.class_type_subject_id = :class_type_subject_id";
            $params['class_type_subject_id'] = $filters['class_type_subject_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = "lpt.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['learner_id'])) {
            $conditions[] = "lpt.learner_id = :learner_id";
            $params['learner_id'] = $filters['learner_id'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY lpt.updated_at DESC LIMIT :limit OFFSET :offset";

        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(":$key", $value, $type);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findWithFilters error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count progressions with filters
     */
    public function countWithFilters(array $filters = []): int
    {
        // Complex query: dynamic JOIN + multi-criteria COUNT
        $sql = "SELECT COUNT(*) FROM learner_lp_tracking lpt
                LEFT JOIN classes c ON lpt.class_id = c.class_id
                LEFT JOIN clients cl ON c.client_id = cl.client_id";
        $conditions = [];
        $params = [];

        if (!empty($filters['client_id'])) {
            $conditions[] = "cl.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        if (!empty($filters['class_id'])) {
            $conditions[] = "lpt.class_id = :class_id";
            $params['class_id'] = $filters['class_id'];
        }

        if (!empty($filters['class_type_subject_id'])) {
            $conditions[] = "lpt.class_type_subject_id = :class_type_subject_id";
            $params['class_type_subject_id'] = $filters['class_type_subject_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = "lpt.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        try {
            $stmt = $this->db->query($sql, $params);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository countWithFilters error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch all progression rows for the report page (5-table JOIN with employer).
     *
     * Supported filters:
     *   - search (string): matches learner full name (ILIKE) or learner ID (if numeric)
     *   - employer_id (int): filter by employer
     *   - status (string): filter by lpt.status
     *
     * Results are ordered by employer_name, learner surname, start_date DESC.
     */
    public function findForReport(array $filters = []): array
    {
        // Complex query: 5-table JOIN (lpt + class_type_subjects + learners + classes + employers) with dynamic filters
        $sql = "
            SELECT
                lpt.*,
                cts.subject_name,
                cts.subject_duration,
                CONCAT(l.first_name, ' ', l.surname) AS learner_name,
                l.id AS learner_id,
                c.class_code,
                emp.employer_name,
                emp.employer_id
            FROM learner_lp_tracking lpt
            LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            LEFT JOIN learners l ON lpt.learner_id = l.id
            LEFT JOIN classes c ON lpt.class_id = c.class_id
            LEFT JOIN employers emp ON l.employer_id = emp.employer_id
        ";

        $conditions = [];
        $params     = [];
        $paramTypes = [];

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            if (is_numeric($search)) {
                $conditions[]             = "l.id = :search_id";
                $params['search_id']      = (int) $search;
                $paramTypes['search_id']  = PDO::PARAM_INT;
            } else {
                $conditions[]             = "CONCAT(l.first_name, ' ', l.surname) ILIKE :search_name";
                $params['search_name']    = '%' . $search . '%';
                $paramTypes['search_name'] = PDO::PARAM_STR;
            }
        }

        if (!empty($filters['employer_id'])) {
            $conditions[]                   = "l.employer_id = :employer_id";
            $params['employer_id']          = (int) $filters['employer_id'];
            $paramTypes['employer_id']      = PDO::PARAM_INT;
        }

        if (!empty($filters['status'])) {
            $conditions[]              = "lpt.status = :status";
            $params['status']          = $filters['status'];
            $paramTypes['status']      = PDO::PARAM_STR;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY emp.employer_name, l.surname, lpt.start_date DESC";

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $paramTypes[$key]);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findForReport error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Return aggregate statistics for the report page.
     *
     * Applies the same filter logic as findForReport but returns a single stats row:
     *   - total_learners, total_progressions, completed_count, in_progress_count, on_hold_count
     *   - avg_progress (avg hours_trained/subject_duration*100, capped at 100, non-completed only)
     *   - completion_rate (completed_count / total_progressions * 100)
     */
    public function getReportSummaryStats(array $filters = []): array
    {
        // Complex query: same 5-table JOIN as findForReport with conditional aggregation (PostgreSQL FILTER)
        $sql = "
            SELECT
                COUNT(DISTINCT lpt.learner_id)                                                  AS total_learners,
                COUNT(*)                                                                        AS total_progressions,
                COUNT(*) FILTER (WHERE lpt.status = 'completed')                               AS completed_count,
                COUNT(*) FILTER (WHERE lpt.status = 'in_progress')                             AS in_progress_count,
                COUNT(*) FILTER (WHERE lpt.status = 'on_hold')                                 AS on_hold_count,
                COALESCE(
                    AVG(
                        LEAST(
                            CASE
                                WHEN lpt.status != 'completed' AND NULLIF(cts.subject_duration, 0) IS NOT NULL
                                    THEN (lpt.hours_trained / cts.subject_duration::float) * 100
                                ELSE NULL
                            END,
                            100
                        )
                    ),
                    0
                )                                                                               AS avg_progress
            FROM learner_lp_tracking lpt
            LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            LEFT JOIN learners l ON lpt.learner_id = l.id
            LEFT JOIN classes c ON lpt.class_id = c.class_id
            LEFT JOIN employers emp ON l.employer_id = emp.employer_id
        ";

        $conditions = [];
        $params     = [];
        $paramTypes = [];

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            if (is_numeric($search)) {
                $conditions[]             = "l.id = :search_id";
                $params['search_id']      = (int) $search;
                $paramTypes['search_id']  = PDO::PARAM_INT;
            } else {
                $conditions[]             = "CONCAT(l.first_name, ' ', l.surname) ILIKE :search_name";
                $params['search_name']    = '%' . $search . '%';
                $paramTypes['search_name'] = PDO::PARAM_STR;
            }
        }

        if (!empty($filters['employer_id'])) {
            $conditions[]              = "l.employer_id = :employer_id";
            $params['employer_id']     = (int) $filters['employer_id'];
            $paramTypes['employer_id'] = PDO::PARAM_INT;
        }

        if (!empty($filters['status'])) {
            $conditions[]          = "lpt.status = :status";
            $params['status']      = $filters['status'];
            $paramTypes['status']  = PDO::PARAM_STR;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $zeroed = [
            'total_learners'     => 0,
            'total_progressions' => 0,
            'completed_count'    => 0,
            'in_progress_count'  => 0,
            'on_hold_count'      => 0,
            'avg_progress'       => 0.0,
            'completion_rate'    => 0.0,
        ];

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $paramTypes[$key]);
            }

            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $zeroed;
            }

            $total       = (int) $row['total_progressions'];
            $completed   = (int) $row['completed_count'];
            $rate        = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

            return [
                'total_learners'     => (int)   $row['total_learners'],
                'total_progressions' => $total,
                'completed_count'    => $completed,
                'in_progress_count'  => (int)   $row['in_progress_count'],
                'on_hold_count'      => (int)   $row['on_hold_count'],
                'avg_progress'       => round((float) $row['avg_progress'], 1),
                'completion_rate'    => $rate,
            ];
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository getReportSummaryStats error: " . $e->getMessage());
            return $zeroed;
        }
    }

    /**
     * Fetch all progression rows for the regulatory export (Umalusi / DHET compliance).
     *
     * 6-table JOIN: lpt + class_type_subjects + learners + classes + clients + employers.
     * Returns one flat row per progression containing every compliance-required column.
     *
     * Supported filters:
     *   - date_from  (string YYYY-MM-DD): lpt.start_date >= :date_from
     *   - date_to    (string YYYY-MM-DD): lpt.start_date <= :date_to
     *   - status     (string): lpt.status = :status
     *   - client_id  (int): cl.client_id = :client_id
     *
     * Results ordered by: client_name, employer_name, surname, first_name, start_date.
     */
    public function findForRegulatoryExport(array $filters = []): array
    {
        // Complex query: 6-table JOIN for full regulatory compliance columns with date-range filter
        $sql = "
            SELECT
                l.first_name,
                l.surname,
                l.sa_id_no,
                l.passport_number,
                cts.subject_code   AS lp_code,
                cts.subject_name   AS lp_name,
                cts.subject_duration AS lp_duration_hours,
                c.class_code,
                cl.client_name,
                emp.employer_name,
                lpt.start_date,
                lpt.completion_date,
                lpt.hours_trained,
                lpt.hours_present,
                lpt.hours_absent,
                lpt.status,
                CASE WHEN lpt.portfolio_file_path IS NOT NULL THEN 'Yes' ELSE 'No' END AS portfolio_submitted,
                lpt.created_at,
                lpt.updated_at
            FROM learner_lp_tracking lpt
            LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            LEFT JOIN learners l               ON lpt.learner_id = l.id
            LEFT JOIN classes c                ON lpt.class_id = c.class_id
            LEFT JOIN clients cl               ON c.client_id = cl.client_id
            LEFT JOIN employers emp            ON l.employer_id = emp.employer_id
        ";

        $conditions = [];
        $params     = [];
        $paramTypes = [];

        if (!empty($filters['date_from'])) {
            $conditions[]              = "lpt.start_date >= :date_from";
            $params['date_from']       = $filters['date_from'];
            $paramTypes['date_from']   = PDO::PARAM_STR;
        }

        if (!empty($filters['date_to'])) {
            $conditions[]            = "lpt.start_date <= :date_to";
            $params['date_to']       = $filters['date_to'];
            $paramTypes['date_to']   = PDO::PARAM_STR;
        }

        if (!empty($filters['status'])) {
            $conditions[]          = "lpt.status = :status";
            $params['status']      = $filters['status'];
            $paramTypes['status']  = PDO::PARAM_STR;
        }

        if (!empty($filters['client_id'])) {
            $conditions[]              = "cl.client_id = :client_id";
            $params['client_id']       = (int) $filters['client_id'];
            $paramTypes['client_id']   = PDO::PARAM_INT;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY cl.client_name, emp.employer_name, l.surname, l.first_name, lpt.start_date";

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $paramTypes[$key]);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository findForRegulatoryExport error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Return COUNT(*) for the regulatory export query using the same filter logic.
     *
     * Used by the frontend to display total matching records before triggering
     * the CSV download.
     *
     * Supported filters: same as findForRegulatoryExport().
     */
    public function getRegulatoryExportCount(array $filters = []): int
    {
        // Complex query: same 6-table JOIN as findForRegulatoryExport, COUNT(*) only
        $sql = "
            SELECT COUNT(*)
            FROM learner_lp_tracking lpt
            LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            LEFT JOIN learners l               ON lpt.learner_id = l.id
            LEFT JOIN classes c                ON lpt.class_id = c.class_id
            LEFT JOIN clients cl               ON c.client_id = cl.client_id
            LEFT JOIN employers emp            ON l.employer_id = emp.employer_id
        ";

        $conditions = [];
        $params     = [];
        $paramTypes = [];

        if (!empty($filters['date_from'])) {
            $conditions[]              = "lpt.start_date >= :date_from";
            $params['date_from']       = $filters['date_from'];
            $paramTypes['date_from']   = PDO::PARAM_STR;
        }

        if (!empty($filters['date_to'])) {
            $conditions[]            = "lpt.start_date <= :date_to";
            $params['date_to']       = $filters['date_to'];
            $paramTypes['date_to']   = PDO::PARAM_STR;
        }

        if (!empty($filters['status'])) {
            $conditions[]          = "lpt.status = :status";
            $params['status']      = $filters['status'];
            $paramTypes['status']  = PDO::PARAM_STR;
        }

        if (!empty($filters['client_id'])) {
            $conditions[]              = "cl.client_id = :client_id";
            $params['client_id']       = (int) $filters['client_id'];
            $paramTypes['client_id']   = PDO::PARAM_INT;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $paramTypes[$key]);
            }

            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository getRegulatoryExportCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Save portfolio file record
     */
    public function savePortfolioFile(int $trackingId, array $fileData): ?int
    {
        // Complex query: operates on learner_progression_portfolios table (not $table)
        $sql = "
            INSERT INTO learner_progression_portfolios
            (tracking_id, file_name, file_path, file_type, file_size, uploaded_by, uploaded_at)
            VALUES (:tracking_id, :file_name, :file_path, :file_type, :file_size, :uploaded_by, :uploaded_at)
            RETURNING file_id
        ";

        try {
            $stmt = $this->db->query($sql, [
                'tracking_id' => $trackingId,
                'file_name' => $fileData['file_name'],
                'file_path' => $fileData['file_path'],
                'file_type' => $fileData['file_type'] ?? null,
                'file_size' => $fileData['file_size'] ?? null,
                'uploaded_by' => $fileData['uploaded_by'] ?? null,
                'uploaded_at' => current_time('mysql'),
            ]);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository savePortfolioFile error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete hours log entries by session_id and return affected tracking IDs.
     * Used by AttendanceService::deleteAndReverseHours() for hours reversal.
     *
     * @param int $sessionId Session ID to delete log entries for
     * @return array Array of distinct tracking_ids that had rows deleted
     */
    public function deleteHoursLogBySessionId(int $sessionId): array
    {
        // Step 1: Get affected tracking_ids before deletion
        $selectSql = "SELECT DISTINCT tracking_id FROM learner_hours_log WHERE session_id = :session_id";

        try {
            $stmt        = $this->db->query($selectSql, ['session_id' => $sessionId]);
            $trackingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository deleteHoursLogBySessionId select error: " . $e->getMessage());
            return [];
        }

        // Step 2: Delete the rows
        $deleteSql = "DELETE FROM learner_hours_log WHERE session_id = :session_id";

        try {
            $this->db->query($deleteSql, ['session_id' => $sessionId]);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository deleteHoursLogBySessionId delete error: " . $e->getMessage());
            return [];
        }

        return $trackingIds ?: [];
    }

    /**
     * Get portfolio files for a tracking ID
     */
    public function getPortfolioFiles(int $trackingId): array
    {
        // Complex query: reads from learner_progression_portfolios table (not $table)
        $sql = "SELECT * FROM learner_progression_portfolios WHERE tracking_id = :tracking_id ORDER BY uploaded_at DESC";

        try {
            $stmt = $this->db->query($sql, ['tracking_id' => $trackingId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: LearnerProgressionRepository getPortfolioFiles error: " . $e->getMessage());
            return [];
        }
    }
}
