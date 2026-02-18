<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Events\DTOs\ClassEventDTO;
use WeCoza\Events\Repositories\ClassEventRepository;

use function wp_date;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for notification dashboard data retrieval and management
 *
 * Provides methods for:
 * - Timeline retrieval with pagination and filtering
 * - Entity-specific event queries
 * - Unread event counting
 * - Event state management (viewed/acknowledged)
 * - Statistics for dashboard widgets
 * - DTO-to-display transformation
 *
 * @since 1.2.0
 */
final class NotificationDashboardService
{
    private ClassEventRepository $repository;

    /** @var array<int, string> In-request cache for resolved agent names */
    private array $agentNameCache = [];

    /** @var array<int, string> In-request cache for resolved client names */
    private array $clientNameCache = [];

    /** @var array<int, array{name:string,address:string}> In-request cache for resolved site info */
    private array $siteInfoCache = [];

    public function __construct(?ClassEventRepository $repository = null)
    {
        $this->repository = $repository ?? new ClassEventRepository();
    }

    /**
     * Static factory for shortcode usage
     */
    public static function boot(): self
    {
        return new self();
    }

    /**
     * Get notification timeline with pagination and filtering
     *
     * @param int $limit Maximum events to return
     * @param int|null $afterEventId Pagination cursor (get events after this ID)
     * @param bool $unreadOnly Filter to unread events only
     * @return array<int, ClassEventDTO>
     */
    public function getTimeline(int $limit = AppConstants::DEFAULT_PAGE_SIZE, ?int $afterEventId = null, bool $unreadOnly = false): array
    {
        $events = $this->repository->getTimeline($limit, $afterEventId);

        if ($unreadOnly) {
            $events = array_filter($events, fn(ClassEventDTO $event) => !$event->isViewed());
            $events = array_values($events);
        }

        return $events;
    }

    /**
     * Get events for specific entity
     *
     * @param string $entityType Entity type: 'class' or 'learner'
     * @param int $entityId Entity ID
     * @param int $limit Maximum events to return
     * @return array<int, ClassEventDTO>
     */
    public function getByEntity(string $entityType, int $entityId, int $limit = AppConstants::DEFAULT_PAGE_SIZE): array
    {
        return $this->repository->findByEntity($entityType, $entityId, $limit);
    }

    /**
     * Get count of unread events
     *
     * @return int Unread count
     */
    public function getUnreadCount(): int
    {
        return $this->repository->getUnreadCount();
    }

    /**
     * Get count of acknowledged events
     *
     * @return int Acknowledged count
     */
    public function getAcknowledgedCount(): int
    {
        return $this->repository->getAcknowledgedCount();
    }

    /**
     * Soft-delete a notification recording the WordPress user ID
     *
     * @param int $eventId Event ID
     * @param int $deletedByUserId WordPress user ID performing deletion
     * @return bool Success
     */
    public function deleteNotification(int $eventId, int $deletedByUserId): bool
    {
        return $this->repository->softDelete($eventId, $deletedByUserId);
    }

    /**
     * Mark event as viewed
     *
     * @param int $eventId Event ID
     * @return bool Success
     */
    public function markAsViewed(int $eventId): bool
    {
        return $this->repository->markViewed($eventId);
    }

    /**
     * Mark event as acknowledged
     *
     * @param int $eventId Event ID
     * @return bool Success
     */
    public function markAsAcknowledged(int $eventId): bool
    {
        return $this->repository->markAcknowledged($eventId);
    }

    /**
     * Get event statistics for dashboard overview widgets
     *
     * @return array{
     *     total: int,
     *     unread: int,
     *     by_status: array<string, int>,
     *     by_event_type: array<string, int>,
     *     by_entity_type: array<string, int>
     * }
     */
    public function getStatistics(): array
    {
        $events = $this->repository->getTimeline(1000);

        $stats = [
            'total' => count($events),
            'unread' => 0,
            'by_status' => [],
            'by_event_type' => [],
            'by_entity_type' => [],
        ];

        foreach ($events as $event) {
            if (!$event->isViewed()) {
                $stats['unread']++;
            }

            $status = $event->notificationStatus;
            $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;

            $eventType = $event->eventType->value;
            $stats['by_event_type'][$eventType] = ($stats['by_event_type'][$eventType] ?? 0) + 1;

            $entityType = $event->entityType;
            $stats['by_entity_type'][$entityType] = ($stats['by_entity_type'][$entityType] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Transform DTO to array suitable for presenter
     *
     * Extracts and formats all relevant fields for display including:
     * - Event identification (event_id, event_type, entity info)
     * - Class details from event_data['new_row']
     * - AI summary if present
     * - Timestamps formatted for human readability
     * - Read/acknowledged state
     *
     * @param ClassEventDTO $event Event DTO to transform
     * @return array<string, mixed>
     */
    public function transformForDisplay(ClassEventDTO $event): array
    {
        $eventData = $event->eventData;
        $newRow = $eventData['new_row'] ?? [];
        $aiSummary = $event->aiSummary;

        $agentId = (int) ($newRow['class_agent'] ?? 0);
        $agentName = $agentId > 0 ? $this->resolveAgentName($agentId) : '';

        $clientId = (int) ($newRow['client_id'] ?? 0);
        $clientName = $clientId > 0 ? $this->resolveClientName($clientId) : '';

        $siteId = (int) ($newRow['site_id'] ?? 0);
        $siteInfo = $siteId > 0 ? $this->resolveSiteInfo($siteId) : ['name' => '', 'address' => ''];

        return [
            'event_id' => $event->eventId,
            'event_type' => $event->eventType->value,
            'event_type_label' => $event->getLabel(),
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'user_id' => $event->userId,
            'notification_status' => $event->notificationStatus,
            'priority' => $event->getPriority(),

            'class_code' => $newRow['class_code'] ?? '',
            'class_subject' => $newRow['class_subject'] ?? '',
            'class_name' => $this->formatClassName($newRow),
            'agent_id' => $agentId,
            'agent_name' => $agentName,
            'client_id' => $clientId,
            'client_name' => $clientName,
            'site_id' => $siteId,
            'site_name' => $siteInfo['name'],
            'site_address' => $siteInfo['address'],

            'has_ai_summary' => $aiSummary !== null,
            'ai_summary_text' => $aiSummary['summary'] ?? '',
            'ai_summary_status' => $aiSummary['status'] ?? null,
            'ai_summary_model' => $aiSummary['model'] ?? null,
            'ai_tokens_used' => $aiSummary['tokens_used'] ?? null,

            'created_at' => $event->createdAt,
            'created_at_formatted' => $this->formatTimestamp($event->createdAt),
            'enriched_at' => $event->enrichedAt,
            'enriched_at_formatted' => $this->formatTimestamp($event->enrichedAt),
            'sent_at' => $event->sentAt,
            'sent_at_formatted' => $this->formatTimestamp($event->sentAt),
            'viewed_at' => $event->viewedAt,
            'viewed_at_formatted' => $this->formatTimestamp($event->viewedAt),
            'acknowledged_at' => $event->acknowledgedAt,
            'acknowledged_at_formatted' => $this->formatTimestamp($event->acknowledgedAt),

            'is_read' => $event->isViewed(),
            'is_acknowledged' => $event->isAcknowledged(),
            'is_sent' => $event->isSent(),
            'is_enriched' => $event->isEnriched(),

            'event_data' => $eventData,
        ];
    }

    /**
     * Transform multiple DTOs for display
     *
     * @param array<int, ClassEventDTO> $events
     * @return array<int, array<string, mixed>>
     */
    public function transformManyForDisplay(array $events): array
    {
        return array_map([$this, 'transformForDisplay'], $events);
    }

    /**
     * Resolve agent name from agents table by agent ID
     *
     * Uses an in-request cache to avoid repeated DB queries for the same agent
     * across multiple notifications.
     *
     * @param int $agentId Agent ID from event_data->new_row->class_agent
     * @return string "First Last" or "Unknown Agent" on failure
     */
    private function resolveAgentName(int $agentId): string
    {
        if (isset($this->agentNameCache[$agentId])) {
            return $this->agentNameCache[$agentId];
        }

        try {
            $pdo = wecoza_db()->getPdo();
            $stmt = $pdo->prepare('SELECT first_name, surname FROM agents WHERE agent_id = :id LIMIT 1');
            if ($stmt && $stmt->execute([':id' => $agentId])) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row !== false && $row !== null) {
                    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                    $resolved = $name !== '' ? $name : 'Unknown Agent';
                } else {
                    $resolved = 'Unknown Agent';
                }
            } else {
                $resolved = 'Unknown Agent';
            }
        } catch (\Throwable $e) {
            $resolved = 'Unknown Agent';
        }

        $this->agentNameCache[$agentId] = $resolved;

        return $resolved;
    }

    /**
     * Resolve client name from clients table by client ID
     *
     * @param int $clientId Client ID from event_data->new_row->client_id
     * @return string Client name or empty string on failure
     */
    private function resolveClientName(int $clientId): string
    {
        if (isset($this->clientNameCache[$clientId])) {
            return $this->clientNameCache[$clientId];
        }

        $resolved = '';
        try {
            $pdo = wecoza_db()->getPdo();
            $stmt = $pdo->prepare('SELECT client_name FROM public.clients WHERE client_id = :id LIMIT 1');
            if ($stmt && $stmt->execute([':id' => $clientId])) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row !== false && $row !== null) {
                    $resolved = trim((string) ($row['client_name'] ?? ''));
                }
            }
        } catch (\Throwable $e) {
            // Silently fail — name is non-critical
        }

        $this->clientNameCache[$clientId] = $resolved;
        return $resolved;
    }

    /**
     * Resolve site name and address from sites/locations tables
     *
     * @param int $siteId Site ID from event_data->new_row->site_id
     * @return array{name:string,address:string}
     */
    private function resolveSiteInfo(int $siteId): array
    {
        if (isset($this->siteInfoCache[$siteId])) {
            return $this->siteInfoCache[$siteId];
        }

        $resolved = ['name' => '', 'address' => ''];
        try {
            $pdo = wecoza_db()->getPdo();
            $stmt = $pdo->prepare(
                'SELECT s.site_name, l.street_address
                 FROM public.sites s
                 LEFT JOIN public.locations l ON s.place_id = l.location_id
                 WHERE s.site_id = :id
                 LIMIT 1'
            );
            if ($stmt && $stmt->execute([':id' => $siteId])) {
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row !== false && $row !== null) {
                    $resolved['name'] = trim((string) ($row['site_name'] ?? ''));
                    $resolved['address'] = trim((string) ($row['street_address'] ?? ''));
                }
            }
        } catch (\Throwable $e) {
            // Silently fail — name is non-critical
        }

        $this->siteInfoCache[$siteId] = $resolved;
        return $resolved;
    }

    /**
     * Format timestamp for human-readable display
     *
     * @param string|null $timestamp ISO timestamp or null
     * @return string Formatted date or empty string
     */
    private function formatTimestamp(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '';
        }

        $time = strtotime($timestamp);
        if ($time === false) {
            return '';
        }

        return wp_date('F j, Y g:i a', $time) ?: '';
    }

    /**
     * Format class name from event data
     *
     * @param array<string, mixed> $newRow
     * @return string Formatted class name
     */
    private function formatClassName(array $newRow): string
    {
        $code = $newRow['class_code'] ?? '';
        $subject = $newRow['class_subject'] ?? '';

        if ($code !== '' && $subject !== '') {
            return "{$code} - {$subject}";
        }

        if ($code !== '') {
            return $code;
        }

        if ($subject !== '') {
            return $subject;
        }

        return 'Unknown Class';
    }
}
