<?php
declare(strict_types=1);

namespace WeCoza\Events\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Events\DTOs\ClassEventDTO;
use WeCoza\Events\Enums\EventType;
use PDO;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for class_events table operations
 *
 * Provides CRUD operations for event storage and notification workflow.
 *
 * @since 1.2.0
 */
final class ClassEventRepository extends BaseRepository
{
    protected static string $table = 'class_events';
    protected static string $primaryKey = 'event_id';

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array<int, string>
     */
    protected function getAllowedOrderColumns(): array
    {
        return ['event_id', 'created_at', 'enriched_at', 'sent_at', 'viewed_at'];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     *
     * @return array<int, string>
     */
    protected function getAllowedFilterColumns(): array
    {
        return [
            'event_id',
            'event_type',
            'entity_type',
            'entity_id',
            'user_id',
            'notification_status',
        ];
    }

    /**
     * Get columns allowed for INSERT operations
     *
     * @return array<int, string>
     */
    protected function getAllowedInsertColumns(): array
    {
        return [
            'event_type',
            'entity_type',
            'entity_id',
            'user_id',
            'event_data',
            'notification_status',
        ];
    }

    /**
     * Get columns allowed for UPDATE operations
     *
     * @return array<int, string>
     */
    protected function getAllowedUpdateColumns(): array
    {
        return [
            'ai_summary',
            'notification_status',
            'enriched_at',
            'sent_at',
            'viewed_at',
            'acknowledged_at',
        ];
    }

    /**
     * Insert a new event
     *
     * @param ClassEventDTO $event Event DTO to insert
     * @return int Inserted event_id
     * @throws RuntimeException If insert fails
     */
    public function insertEvent(ClassEventDTO $event): int
    {
        $data = $event->toInsertArray();
        $eventId = parent::insert($data);

        if ($eventId === null) {
            throw new RuntimeException('Failed to insert event');
        }

        return $eventId;
    }

    /**
     * Find an event by ID
     *
     * @param int $eventId Event ID
     * @return ClassEventDTO|null Event DTO or null if not found
     */
    public function findByEventId(int $eventId): ?ClassEventDTO
    {
        $row = parent::findById($eventId);
        return $row ? ClassEventDTO::fromRow($row) : null;
    }

    /**
     * Find pending events for processing
     *
     * @param int $limit Maximum events to return
     * @return array<int, ClassEventDTO>
     */
    public function findPendingForProcessing(int $limit = 50): array
    {
        $sql = <<<SQL
SELECT *
FROM class_events
WHERE notification_status = 'pending'
ORDER BY created_at ASC
LIMIT :limit
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare pending events query');
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute pending events query');
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row !== false) {
                $results[] = ClassEventDTO::fromRow($row);
            }
        }

        return $results;
    }

    /**
     * Find events by entity
     *
     * @param string $entityType Entity type (class or learner)
     * @param int $entityId Entity ID
     * @param int $limit Maximum events to return
     * @return array<int, ClassEventDTO>
     */
    public function findByEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $sql = <<<SQL
SELECT *
FROM class_events
WHERE entity_type = :entity_type AND entity_id = :entity_id
ORDER BY created_at DESC
LIMIT :limit
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare entity events query');
        }

        $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute entity events query');
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row !== false) {
                $results[] = ClassEventDTO::fromRow($row);
            }
        }

        return $results;
    }

    /**
     * Update notification status
     *
     * @param int $eventId Event ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateStatus(int $eventId, string $status): bool
    {
        return parent::update($eventId, ['notification_status' => $status]);
    }

    /**
     * Update AI summary
     *
     * @param int $eventId Event ID
     * @param array<string, mixed> $summary AI summary data
     * @return bool Success
     */
    public function updateAiSummary(int $eventId, array $summary): bool
    {
        $sql = <<<SQL
UPDATE class_events
SET ai_summary = :ai_summary,
    enriched_at = CURRENT_TIMESTAMP
WHERE event_id = :event_id
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bindValue(':ai_summary', json_encode($summary, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Mark event as sent
     *
     * @param int $eventId Event ID
     * @return bool Success
     */
    public function markSent(int $eventId): bool
    {
        $sql = <<<SQL
UPDATE class_events
SET notification_status = 'sent',
    sent_at = CURRENT_TIMESTAMP
WHERE event_id = :event_id
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Mark event as viewed
     *
     * @param int $eventId Event ID
     * @return bool Success
     */
    public function markViewed(int $eventId): bool
    {
        $sql = <<<SQL
UPDATE class_events
SET viewed_at = CURRENT_TIMESTAMP
WHERE event_id = :event_id AND viewed_at IS NULL
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Mark event as acknowledged
     *
     * @param int $eventId Event ID
     * @return bool Success
     */
    public function markAcknowledged(int $eventId): bool
    {
        $sql = <<<SQL
UPDATE class_events
SET acknowledged_at = CURRENT_TIMESTAMP
WHERE event_id = :event_id AND acknowledged_at IS NULL
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Get event timeline for dashboard display
     *
     * @param int $limit Maximum events
     * @param int|null $afterId Pagination cursor (get events after this ID)
     * @return array<int, ClassEventDTO>
     */
    public function getTimeline(int $limit = 50, ?int $afterId = null): array
    {
        $whereClause = $afterId !== null ? 'WHERE event_id < :after_id' : '';

        $sql = <<<SQL
SELECT *
FROM class_events
{$whereClause}
ORDER BY created_at DESC, event_id DESC
LIMIT :limit
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare timeline query');
        }

        if ($afterId !== null) {
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute timeline query');
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row !== false) {
                $results[] = ClassEventDTO::fromRow($row);
            }
        }

        return $results;
    }

    /**
     * Get count of unread events (viewed_at IS NULL)
     *
     * @return int Unread count
     */
    public function getUnreadCount(): int
    {
        $sql = <<<SQL
SELECT COUNT(*)
FROM class_events
WHERE viewed_at IS NULL
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt || !$stmt->execute()) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }
}
