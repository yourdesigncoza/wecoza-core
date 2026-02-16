<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

use WeCoza\Events\DTOs\ClassEventDTO;
use WeCoza\Events\Enums\EventType;
use WeCoza\Events\Repositories\ClassEventRepository;

if (!defined('ABSPATH')) {
    exit;
}

use function as_enqueue_async_action;
use function get_current_user_id;
use function wecoza_log;

/**
 * Event Dispatcher Service
 *
 * Bridge between controller actions (class create/update, learner changes) and
 * the notification pipeline. Captures events and schedules async processing.
 *
 * @since 1.2.0
 */
final class EventDispatcher
{
    private ClassEventRepository $repository;

    /**
     * Fields considered significant for class change notifications
     * Changes to non-significant fields are ignored to prevent notification spam
     */
    private const SIGNIFICANT_CLASS_FIELDS = [
        'class_status',
        'start_date',
        'end_date',
        'learner_ids',
        'event_dates',
        'class_facilitator',
        'class_coach',
        'class_assessor',
        'original_start_date',
        'client_id',
        'class_type',
        'class_subject',
    ];

    public function __construct(ClassEventRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Static factory for quick instantiation
     */
    public static function boot(): self
    {
        return new self(new ClassEventRepository());
    }

    /**
     * Dispatch a class event (INSERT, UPDATE, DELETE)
     *
     * @param EventType $type Event type
     * @param int $classId Class ID
     * @param array<string, mixed> $newRow New class data
     * @param array<string, mixed>|null $oldRow Previous class data (for UPDATE)
     * @return int Event ID (0 if skipped)
     */
    public function dispatchClassEvent(
        EventType $type,
        int $classId,
        array $newRow,
        ?array $oldRow = null,
    ): int {
        // Check if we should dispatch this event type
        if (!$this->shouldDispatch($type)) {
            return 0;
        }

        // Compute diff for updates
        $diff = [];
        if ($oldRow !== null) {
            $diff = $this->computeDiff($oldRow, $newRow);

            // For UPDATE events, check if changes are significant
            if ($type === EventType::CLASS_UPDATE && !$this->isSignificantChange($diff)) {
                wecoza_log("EventDispatcher: Skipping non-significant UPDATE for class {$classId}", 'debug');
                return 0;
            }
        }

        // Build event data
        $eventData = $this->buildEventData($newRow, $oldRow, $diff, [
            'changed_fields' => array_keys($diff),
            'timestamp' => wp_date('c'),
        ]);

        // Create DTO
        $dto = ClassEventDTO::create(
            eventType: $type,
            entityType: 'class',
            entityId: $classId,
            eventData: $eventData,
            userId: get_current_user_id() ?: null,
        );

        // Insert event
        $eventId = $this->repository->insertEvent($dto);

        // Schedule async processing
        $this->scheduleProcessing($eventId);

        return $eventId;
    }

    /**
     * Dispatch a learner event (ADD, REMOVE, UPDATE on a class)
     *
     * @param EventType $type Event type (LEARNER_ADD, LEARNER_REMOVE, LEARNER_UPDATE)
     * @param int $learnerId Learner ID
     * @param int $classId Class ID
     * @param array<string, mixed> $eventData Learner details
     * @return int Event ID (0 if skipped)
     */
    public function dispatchLearnerEvent(
        EventType $type,
        int $learnerId,
        int $classId,
        array $eventData,
    ): int {
        // Check if we should dispatch this event type
        if (!$this->shouldDispatch($type)) {
            return 0;
        }

        // Enrich event data with class reference
        $eventData['class_id'] = $classId;
        $eventData['metadata'] = [
            'timestamp' => wp_date('c'),
            'learner_id' => $learnerId,
        ];

        // Create DTO
        $dto = ClassEventDTO::create(
            eventType: $type,
            entityType: 'learner',
            entityId: $learnerId,
            eventData: $eventData,
            userId: get_current_user_id() ?: null,
        );

        // Insert event
        $eventId = $this->repository->insertEvent($dto);

        // Schedule async processing
        $this->scheduleProcessing($eventId);

        wecoza_log("EventDispatcher: Created learner event {$eventId} for learner {$learnerId} on class {$classId} ({$type->value})", 'debug');

        return $eventId;
    }

    /**
     * Dispatch a status change event (specialized for significant class status transitions)
     *
     * @param int $classId Class ID
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param array<string, mixed> $classData Full class data for context
     * @return int Event ID (0 if skipped)
     */
    public function dispatchStatusChange(
        int $classId,
        string $oldStatus,
        string $newStatus,
        array $classData,
    ): int {
        // Status change is always significant
        if (!$this->shouldDispatch(EventType::STATUS_CHANGE)) {
            return 0;
        }

        // Build focused diff for status change
        $diff = [
            'class_status' => [
                'old' => $oldStatus,
                'new' => $newStatus,
            ],
        ];

        $eventData = $this->buildEventData($classData, null, $diff, [
            'changed_fields' => ['class_status'],
            'timestamp' => wp_date('c'),
            'status_transition' => "{$oldStatus} -> {$newStatus}",
        ]);

        // Create DTO
        $dto = ClassEventDTO::create(
            eventType: EventType::STATUS_CHANGE,
            entityType: 'class',
            entityId: $classId,
            eventData: $eventData,
            userId: get_current_user_id() ?: null,
        );

        // Insert event
        $eventId = $this->repository->insertEvent($dto);

        // Schedule async processing
        $this->scheduleProcessing($eventId);

        wecoza_log("EventDispatcher: Created status change event {$eventId} for class {$classId} ({$oldStatus} -> {$newStatus})", 'debug');

        return $eventId;
    }

    /**
     * Compute diff between old and new data
     *
     * @param array<string, mixed> $old Old data
     * @param array<string, mixed> $new New data
     * @return array<string, array{old: mixed, new: mixed}> Changed fields with old/new values
     */
    private function computeDiff(array $old, array $new): array
    {
        $diff = [];

        // Check all keys in new data
        foreach ($new as $key => $newValue) {
            $oldValue = $old[$key] ?? null;

            // Handle JSONB/array comparison
            if (is_array($newValue) && is_array($oldValue)) {
                if (json_encode($newValue) !== json_encode($oldValue)) {
                    $diff[$key] = ['old' => $oldValue, 'new' => $newValue];
                }
            } elseif ($this->valuesAreDifferent($oldValue, $newValue)) {
                $diff[$key] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        // Check for keys that were removed (in old but not in new)
        foreach ($old as $key => $oldValue) {
            if (!array_key_exists($key, $new)) {
                $diff[$key] = ['old' => $oldValue, 'new' => null];
            }
        }

        return $diff;
    }

    /**
     * Check if two values are different (handles type coercion)
     */
    private function valuesAreDifferent(mixed $old, mixed $new): bool
    {
        // Both null
        if ($old === null && $new === null) {
            return false;
        }

        // One is null
        if ($old === null || $new === null) {
            return true;
        }

        // String comparison for mixed types
        return (string) $old !== (string) $new;
    }

    /**
     * Build standardized event_data structure
     *
     * @param array<string, mixed> $newRow New data
     * @param array<string, mixed>|null $oldRow Old data
     * @param array<string, array{old: mixed, new: mixed}> $diff Computed diff
     * @param array<string, mixed> $metadata Additional metadata
     * @return array<string, mixed>
     */
    private function buildEventData(array $newRow, ?array $oldRow, array $diff, array $metadata = []): array
    {
        return [
            'new_row' => $newRow,
            'old_row' => $oldRow,
            'diff' => $diff,
            'metadata' => $metadata,
        ];
    }

    /**
     * Schedule async event processing
     *
     * @param int $eventId Event ID to process
     */
    private function scheduleProcessing(int $eventId): void
    {
        if (!function_exists('as_enqueue_async_action')) {
            wecoza_log("EventDispatcher: Action Scheduler not available, event {$eventId} not scheduled", 'warning');
            return;
        }

        as_enqueue_async_action(
            'wecoza_process_event',
            ['event_id' => $eventId],
            'wecoza-notifications'
        );
    }

    /**
     * Check if event type is significant based on diff
     *
     * Public method for external use (e.g., testing, conditional logic)
     *
     * @param array<string, array{old: mixed, new: mixed}> $diff Computed diff
     * @return bool True if any significant field changed
     */
    public function isSignificantChange(array $diff): bool
    {
        if (empty($diff)) {
            return false;
        }

        foreach (array_keys($diff) as $field) {
            if (in_array($field, self::SIGNIFICANT_CLASS_FIELDS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of significant class fields
     *
     * @return array<int, string>
     */
    public static function getSignificantFields(): array
    {
        return self::SIGNIFICANT_CLASS_FIELDS;
    }

    /**
     * Check if we should dispatch events of this type
     *
     * Events are always recorded for audit trail purposes. This method checks
     * if the system is configured to process notifications for this event type.
     *
     * @param EventType $type Event type
     * @return bool True if event should be dispatched
     */
    private function shouldDispatch(EventType $type): bool
    {
        // Events are always recorded for audit trail purposes
        // The notification processing step will check if recipients are configured
        // This ensures we have a complete event history regardless of notification settings

        // Check for explicit disable via filter (allows site-specific customization)
        $enabled = apply_filters('wecoza_event_dispatch_enabled', true, $type);
        if (!$enabled) {
            wecoza_log("EventDispatcher: Event type {$type->value} disabled via filter", 'debug');
            return false;
        }

        return true;
    }

    /**
     * Check if notifications are enabled for a specific operation
     *
     * Uses NotificationSettings to determine if there's a recipient configured.
     * This is separate from shouldDispatch() because we want to record events
     * even when notifications are disabled.
     *
     * @param EventType $type Event type
     * @return bool True if notification should be sent
     */
    public function isNotificationEnabled(EventType $type): bool
    {
        $settings = new NotificationSettings();

        // Map event type to operation string for settings lookup
        $operation = match ($type) {
            EventType::CLASS_INSERT => 'INSERT',
            EventType::CLASS_UPDATE => 'UPDATE',
            EventType::CLASS_DELETE => 'DELETE',
            EventType::STATUS_CHANGE => 'UPDATE', // Status changes count as updates
            EventType::LEARNER_ADD => 'INSERT',
            EventType::LEARNER_REMOVE => 'DELETE',
            EventType::LEARNER_UPDATE => 'UPDATE',
        };

        // Check if a recipient is configured for this operation
        return $settings->getRecipientForOperation($operation) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Convenience Methods
    |--------------------------------------------------------------------------
    | These provide a simple API for common event dispatching scenarios.
    */

    /**
     * Dispatch class created event
     *
     * @param int $classId Class ID
     * @param array<string, mixed> $classData Class data
     * @return int Event ID
     */
    public static function classCreated(int $classId, array $classData): int
    {
        return self::boot()->dispatchClassEvent(
            EventType::CLASS_INSERT,
            $classId,
            $classData
        );
    }

    /**
     * Dispatch class updated event
     *
     * @param int $classId Class ID
     * @param array<string, mixed> $newData New class data
     * @param array<string, mixed> $oldData Previous class data
     * @return int Event ID (0 if non-significant change)
     */
    public static function classUpdated(int $classId, array $newData, array $oldData): int
    {
        return self::boot()->dispatchClassEvent(
            EventType::CLASS_UPDATE,
            $classId,
            $newData,
            $oldData
        );
    }

    /**
     * Dispatch class deleted event
     *
     * @param int $classId Class ID
     * @param array<string, mixed> $classData Class data at time of deletion
     * @return int Event ID
     */
    public static function classDeleted(int $classId, array $classData): int
    {
        return self::boot()->dispatchClassEvent(
            EventType::CLASS_DELETE,
            $classId,
            $classData
        );
    }

    /**
     * Dispatch learner added to class event
     *
     * @param int $classId Class ID
     * @param int $learnerId Learner ID
     * @param array<string, mixed> $learnerData Learner details
     * @return int Event ID
     */
    public static function learnerAdded(int $classId, int $learnerId, array $learnerData): int
    {
        return self::boot()->dispatchLearnerEvent(
            EventType::LEARNER_ADD,
            $learnerId,
            $classId,
            $learnerData
        );
    }

    /**
     * Dispatch learner removed from class event
     *
     * @param int $classId Class ID
     * @param int $learnerId Learner ID
     * @param array<string, mixed> $learnerData Learner details
     * @return int Event ID
     */
    public static function learnerRemoved(int $classId, int $learnerId, array $learnerData): int
    {
        return self::boot()->dispatchLearnerEvent(
            EventType::LEARNER_REMOVE,
            $learnerId,
            $classId,
            $learnerData
        );
    }
}
