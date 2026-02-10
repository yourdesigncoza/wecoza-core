<?php
declare(strict_types=1);

namespace WeCoza\Events\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Core\Database\PostgresConnection;
use PDO;
use RuntimeException;
use function sprintf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for managing material delivery tracking records
 */
final class MaterialTrackingRepository extends BaseRepository
{
    protected static string $table = 'class_material_tracking';
    protected static string $primaryKey = 'id';

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array List of allowed column names
     */
    protected function getAllowedOrderColumns(): array
    {
        return ['id', 'class_id', 'notification_type', 'notification_sent_at', 'materials_delivered_at', 'delivery_status', 'created_at', 'updated_at'];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     *
     * @return array List of allowed column names
     */
    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'class_id', 'notification_type', 'delivery_status'];
    }

    /**
     * Record that a notification was sent for a class
     *
     * @param int $classId The class ID
     * @param string $notificationType Either 'orange' or 'red'
     * @throws RuntimeException If database operation fails
     */
    public function markNotificationSent(int $classId, string $notificationType): void
    {
        $sql = 'INSERT INTO class_material_tracking
             (class_id, notification_type, notification_sent_at, delivery_status, created_at, updated_at)
             VALUES (:class_id, :type, NOW(), \'notified\', NOW(), NOW())
             ON CONFLICT (class_id, notification_type)
             DO UPDATE SET
                notification_sent_at = NOW(),
                delivery_status = \'notified\',
                updated_at = NOW()';

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare material tracking insert statement.');
        }

        $success = $stmt->execute([
            ':class_id' => $classId,
            ':type' => $notificationType
        ]);

        if (!$success) {
            throw new RuntimeException(
                sprintf('Failed to mark notification sent for class %d, type %s', $classId, $notificationType)
            );
        }
    }

    /**
     * Mark materials as delivered for a specific delivery event
     *
     * @param int $classId The class ID
     * @param int $eventIndex The index of the delivery event in event_dates JSONB array (0-based)
     * @throws RuntimeException If database operation fails
     */
    public function markDelivered(int $classId, int $eventIndex): void
    {
        // Validate and sanitize event index (must be non-negative integer)
        $eventIndex = (int) $eventIndex;
        if ($eventIndex < 0) {
            throw new RuntimeException('Event index must be non-negative.');
        }

        // Get current user ID for completed_by field
        $currentUserId = get_current_user_id();
        $completedBy = $currentUserId > 0 ? (string) $currentUserId : 'system';
        $completedAt = current_time('Y-m-d H:i:s');

        // Build JSONB path for the specific event (must be a string literal in PostgreSQL)
        $jsonPath = sprintf('{%d}', $eventIndex);

        // Update the specific event in the event_dates JSONB array
        // Note: Using sprintf to inject sanitized event_index since PostgreSQL doesn't support
        // placeholders for JSONB array indices
        $sql = sprintf('UPDATE classes
             SET event_dates = jsonb_set(
                 event_dates,
                 \'%s\',
                 jsonb_build_object(
                     \'type\', event_dates->%d->\'type\',
                     \'description\', event_dates->%d->\'description\',
                     \'date\', event_dates->%d->\'date\',
                     \'status\', \'completed\',
                     \'notes\', COALESCE(event_dates->%d->\'notes\', \'\'::jsonb),
                     \'completed_by\', :completed_by,
                     \'completed_at\', :completed_at
                 )
             ),
             updated_at = NOW()
             WHERE class_id = :class_id
               AND jsonb_array_length(COALESCE(event_dates, \'[]\'::jsonb)) > %d',
            $jsonPath,
            $eventIndex,
            $eventIndex,
            $eventIndex,
            $eventIndex,
            $eventIndex
        );

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare material delivery update statement.');
        }

        $success = $stmt->execute([
            ':class_id' => $classId,
            ':completed_by' => $completedBy,
            ':completed_at' => $completedAt
        ]);

        if (!$success) {
            throw new RuntimeException(
                sprintf('Failed to mark delivery as completed for class %d, event index %d', $classId, $eventIndex)
            );
        }
    }

    /**
     * Check if a notification was already sent for a class
     *
     * @param int $classId The class ID
     * @param string $notificationType Either 'orange' or 'red'
     * @return bool True if notification was sent, false otherwise
     */
    public function wasNotificationSent(int $classId, string $notificationType): bool
    {
        $sql = 'SELECT notification_sent_at
             FROM class_material_tracking
             WHERE class_id = :class_id
               AND notification_type = :type
               AND notification_sent_at IS NOT NULL';

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->execute([
            ':class_id' => $classId,
            ':type' => $notificationType
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get delivery status for a class
     *
     * @param int $classId The class ID
     * @return array<string, mixed> Array with orange_status, red_status, and overall_status
     */
    public function getDeliveryStatus(int $classId): array
    {
        $sql = 'SELECT
                notification_type,
                delivery_status,
                notification_sent_at,
                materials_delivered_at
             FROM class_material_tracking
             WHERE class_id = :class_id';

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return [
                'orange_status' => null,
                'red_status' => null,
                'overall_status' => 'pending'
            ];
        }

        $stmt->execute([':class_id' => $classId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $status = [
            'orange_status' => null,
            'red_status' => null,
            'overall_status' => 'pending'
        ];

        foreach ($rows as $row) {
            if ($row['notification_type'] === 'orange') {
                $status['orange_status'] = $row['delivery_status'];
            } elseif ($row['notification_type'] === 'red') {
                $status['red_status'] = $row['delivery_status'];
            }

            if ($row['delivery_status'] === 'delivered') {
                $status['overall_status'] = 'delivered';
            } elseif ($row['delivery_status'] === 'notified' && $status['overall_status'] !== 'delivered') {
                $status['overall_status'] = 'notified';
            }
        }

        return $status;
    }

    /**
     * Get all tracking records for a class
     *
     * @param int $classId The class ID
     * @return array<int, array<string, mixed>> Array of tracking records
     */
    public function getTrackingRecords(int $classId): array
    {
        $sql = 'SELECT
                id,
                class_id,
                notification_type,
                notification_sent_at,
                materials_delivered_at,
                delivery_status,
                created_at,
                updated_at
             FROM class_material_tracking
             WHERE class_id = :class_id
             ORDER BY notification_type';

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->execute([':class_id' => $classId]);

        /** @var array<int, array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    /**
     * Get tracking dashboard data with class and client information
     * Queries event_dates JSONB for Deliveries events as primary data source
     *
     * @param int $limit Maximum number of records to return
     * @param string|null $status Filter by event status (pending, completed, or null for all)
     * @param string|null $search Search filter for class code/subject/client name (or null)
     * @return array<int, array<string, mixed>> Array of tracking records with joined data
     */
    public function getTrackingDashboardData(
        int $limit = 50,
        ?string $status = null,
        ?string $search = null
    ): array {
        $sql = 'SELECT
                c.class_id,
                c.class_code,
                c.class_subject,
                c.original_start_date,
                cl.client_name,
                s.site_name,
                elem->>\'type\' as event_type,
                elem->>\'description\' as event_description,
                elem->>\'date\' as event_date,
                elem->>\'status\' as event_status,
                elem->>\'notes\' as event_notes,
                elem->>\'completed_by\' as event_completed_by,
                elem->>\'completed_at\' as event_completed_at,
                (elem_index - 1) as event_index,
                cmt.notification_type,
                cmt.notification_sent_at
            FROM classes c
            LEFT JOIN clients cl ON c.client_id = cl.client_id
            LEFT JOIN sites s ON c.site_id = s.site_id
            CROSS JOIN LATERAL jsonb_array_elements(COALESCE(c.event_dates, \'[]\'::jsonb))
                WITH ORDINALITY AS events(elem, elem_index)
            LEFT JOIN class_material_tracking cmt ON cmt.class_id = c.class_id
            WHERE elem->>\'type\' = \'Deliveries\'';

        $params = [':limit' => $limit];

        if ($status !== null) {
            $sql .= ' AND LOWER(elem->>\'status\') = :status';
            $params[':status'] = strtolower($status);
        }

        if ($search !== null) {
            $sql .= ' AND (c.class_code ILIKE :search OR c.class_subject ILIKE :search OR cl.client_name ILIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY
                CASE LOWER(elem->>\'status\')
                    WHEN \'pending\' THEN 1
                    WHEN \'completed\' THEN 2
                    ELSE 3
                END,
                c.original_start_date DESC NULLS LAST,
                c.class_code
            LIMIT :limit';

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return [];
        }

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();

        /** @var array<int, array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    /**
     * Get tracking statistics for dashboard
     * Counts Deliveries events from event_dates JSONB
     *
     * @return array<string, int> Array with keys: total, pending, completed
     */
    public function getTrackingStatistics(): array
    {
        $sql = 'SELECT
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN LOWER(elem->>\'status\') = \'pending\' THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN LOWER(elem->>\'status\') = \'completed\' THEN 1 ELSE 0 END), 0) as completed
            FROM classes c
            CROSS JOIN LATERAL jsonb_array_elements(COALESCE(c.event_dates, \'[]\'::jsonb)) AS events(elem)
            WHERE elem->>\'type\' = \'Deliveries\'';

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return ['total' => 0, 'pending' => 0, 'completed' => 0];
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return ['total' => 0, 'pending' => 0, 'completed' => 0];
        }

        return [
            'total' => (int) ($result['total'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'completed' => (int) ($result['completed'] ?? 0),
        ];
    }
}
