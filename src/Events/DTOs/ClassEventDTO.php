<?php
declare(strict_types=1);

namespace WeCoza\Events\DTOs;

use WeCoza\Events\Enums\EventType;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Data Transfer Object for class_events table rows
 *
 * Immutable DTO representing a notification event. Provides factory methods
 * for database hydration and immutable update methods for status transitions.
 *
 * @since 1.2.0
 */
final class ClassEventDTO
{
    /**
     * @param int|null $eventId Auto-generated primary key (null for new events)
     * @param EventType $eventType Type of event
     * @param string $entityType Entity type: 'class' or 'learner'
     * @param int $entityId ID of the affected entity
     * @param int|null $userId WordPress user who triggered the event
     * @param array $eventData JSONB payload (new_row, old_row, diff, metadata)
     * @param array|null $aiSummary AI enrichment result
     * @param string $notificationStatus Workflow status
     * @param string|null $createdAt When event was captured
     * @param string|null $enrichedAt When AI enrichment completed
     * @param string|null $sentAt When notification was delivered
     * @param string|null $viewedAt When user viewed the notification
     * @param string|null $acknowledgedAt When user acknowledged the notification
     */
    public function __construct(
        public readonly ?int $eventId,
        public readonly EventType $eventType,
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly ?int $userId,
        public readonly array $eventData,
        public readonly ?array $aiSummary,
        public readonly string $notificationStatus,
        public readonly ?string $createdAt,
        public readonly ?string $enrichedAt,
        public readonly ?string $sentAt,
        public readonly ?string $viewedAt,
        public readonly ?string $acknowledgedAt,
    ) {}

    /**
     * Create DTO from database row
     *
     * @param array<string, mixed> $row Database row from class_events table
     * @return self
     */
    public static function fromRow(array $row): self
    {
        // Parse JSONB columns
        $eventData = self::parseJsonb($row['event_data'] ?? null);
        $aiSummary = self::parseJsonb($row['ai_summary'] ?? null);

        // Parse event type
        $eventType = EventType::tryFromString($row['event_type'] ?? null);
        if ($eventType === null) {
            throw new \InvalidArgumentException('Invalid or missing event_type in row');
        }

        return new self(
            eventId: isset($row['event_id']) ? (int) $row['event_id'] : null,
            eventType: $eventType,
            entityType: (string) ($row['entity_type'] ?? 'class'),
            entityId: (int) ($row['entity_id'] ?? 0),
            userId: isset($row['user_id']) ? (int) $row['user_id'] : null,
            eventData: $eventData,
            aiSummary: $aiSummary,
            notificationStatus: (string) ($row['notification_status'] ?? 'pending'),
            createdAt: $row['created_at'] ?? null,
            enrichedAt: $row['enriched_at'] ?? null,
            sentAt: $row['sent_at'] ?? null,
            viewedAt: $row['viewed_at'] ?? null,
            acknowledgedAt: $row['acknowledged_at'] ?? null,
        );
    }

    /**
     * Create a new event DTO for insertion
     *
     * @param EventType $eventType Type of event
     * @param string $entityType Entity type: 'class' or 'learner'
     * @param int $entityId ID of the affected entity
     * @param array $eventData Event payload
     * @param int|null $userId WordPress user who triggered the event
     * @return self
     */
    public static function create(
        EventType $eventType,
        string $entityType,
        int $entityId,
        array $eventData,
        ?int $userId = null,
    ): self {
        return new self(
            eventId: null,
            eventType: $eventType,
            entityType: $entityType,
            entityId: $entityId,
            userId: $userId,
            eventData: $eventData,
            aiSummary: null,
            notificationStatus: 'pending',
            createdAt: null,
            enrichedAt: null,
            sentAt: null,
            viewedAt: null,
            acknowledgedAt: null,
        );
    }

    /**
     * Convert to array for database storage or API response
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType->value,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'user_id' => $this->userId,
            'event_data' => $this->eventData,
            'ai_summary' => $this->aiSummary,
            'notification_status' => $this->notificationStatus,
            'created_at' => $this->createdAt,
            'enriched_at' => $this->enrichedAt,
            'sent_at' => $this->sentAt,
            'viewed_at' => $this->viewedAt,
            'acknowledged_at' => $this->acknowledgedAt,
        ];
    }

    /**
     * Convert to array for database INSERT (excludes auto-generated columns)
     *
     * @return array<string, mixed>
     */
    public function toInsertArray(): array
    {
        $data = [
            'event_type' => $this->eventType->value,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'user_id' => $this->userId,
            'event_data' => json_encode($this->eventData, JSON_THROW_ON_ERROR),
        ];

        // Include notification_status when overriding the DB default ('pending')
        if ($this->notificationStatus !== 'pending') {
            $data['notification_status'] = $this->notificationStatus;
        }

        return $data;
    }

    /**
     * Create a copy with updated notification status
     */
    public function withStatus(string $status): self
    {
        return new self(
            eventId: $this->eventId,
            eventType: $this->eventType,
            entityType: $this->entityType,
            entityId: $this->entityId,
            userId: $this->userId,
            eventData: $this->eventData,
            aiSummary: $this->aiSummary,
            notificationStatus: $status,
            createdAt: $this->createdAt,
            enrichedAt: $this->enrichedAt,
            sentAt: $this->sentAt,
            viewedAt: $this->viewedAt,
            acknowledgedAt: $this->acknowledgedAt,
        );
    }

    /**
     * Create a copy with AI summary and enriched_at timestamp
     */
    public function withAiSummary(array $summary, ?string $enrichedAt = null): self
    {
        return new self(
            eventId: $this->eventId,
            eventType: $this->eventType,
            entityType: $this->entityType,
            entityId: $this->entityId,
            userId: $this->userId,
            eventData: $this->eventData,
            aiSummary: $summary,
            notificationStatus: $this->notificationStatus,
            createdAt: $this->createdAt,
            enrichedAt: $enrichedAt ?? wp_date('c'),
            sentAt: $this->sentAt,
            viewedAt: $this->viewedAt,
            acknowledgedAt: $this->acknowledgedAt,
        );
    }

    /**
     * Create a copy with sent_at timestamp
     */
    public function withSentAt(?string $sentAt = null): self
    {
        return new self(
            eventId: $this->eventId,
            eventType: $this->eventType,
            entityType: $this->entityType,
            entityId: $this->entityId,
            userId: $this->userId,
            eventData: $this->eventData,
            aiSummary: $this->aiSummary,
            notificationStatus: 'sent',
            createdAt: $this->createdAt,
            enrichedAt: $this->enrichedAt,
            sentAt: $sentAt ?? wp_date('c'),
            viewedAt: $this->viewedAt,
            acknowledgedAt: $this->acknowledgedAt,
        );
    }

    /**
     * Create a copy with viewed_at timestamp
     */
    public function withViewedAt(?string $viewedAt = null): self
    {
        return new self(
            eventId: $this->eventId,
            eventType: $this->eventType,
            entityType: $this->entityType,
            entityId: $this->entityId,
            userId: $this->userId,
            eventData: $this->eventData,
            aiSummary: $this->aiSummary,
            notificationStatus: $this->notificationStatus,
            createdAt: $this->createdAt,
            enrichedAt: $this->enrichedAt,
            sentAt: $this->sentAt,
            viewedAt: $viewedAt ?? wp_date('c'),
            acknowledgedAt: $this->acknowledgedAt,
        );
    }

    /**
     * Create a copy with acknowledged_at timestamp
     */
    public function withAcknowledgedAt(?string $acknowledgedAt = null): self
    {
        return new self(
            eventId: $this->eventId,
            eventType: $this->eventType,
            entityType: $this->entityType,
            entityId: $this->entityId,
            userId: $this->userId,
            eventData: $this->eventData,
            aiSummary: $this->aiSummary,
            notificationStatus: $this->notificationStatus,
            createdAt: $this->createdAt,
            enrichedAt: $this->enrichedAt,
            sentAt: $this->sentAt,
            viewedAt: $this->viewedAt,
            acknowledgedAt: $acknowledgedAt ?? wp_date('c'),
        );
    }

    /**
     * Create a copy with assigned event_id (after INSERT)
     */
    public function withEventId(int $eventId): self
    {
        return new self(
            eventId: $eventId,
            eventType: $this->eventType,
            entityType: $this->entityType,
            entityId: $this->entityId,
            userId: $this->userId,
            eventData: $this->eventData,
            aiSummary: $this->aiSummary,
            notificationStatus: $this->notificationStatus,
            createdAt: $this->createdAt,
            enrichedAt: $this->enrichedAt,
            sentAt: $this->sentAt,
            viewedAt: $this->viewedAt,
            acknowledgedAt: $this->acknowledgedAt,
        );
    }

    /**
     * Get human-readable event label
     */
    public function getLabel(): string
    {
        return $this->eventType->label();
    }

    /**
     * Get event priority (1-5)
     */
    public function getPriority(): int
    {
        return $this->eventType->priority();
    }

    /**
     * Check if event has been viewed
     */
    public function isViewed(): bool
    {
        return $this->viewedAt !== null;
    }

    /**
     * Check if event has been acknowledged
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledgedAt !== null;
    }

    /**
     * Check if event has been sent as notification
     */
    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }

    /**
     * Check if event has AI enrichment
     */
    public function isEnriched(): bool
    {
        return $this->aiSummary !== null;
    }

    /**
     * Parse JSONB column value
     *
     * @param mixed $value Database value (string JSON or null)
     * @return array|null Parsed array or null
     */
    private static function parseJsonb(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
