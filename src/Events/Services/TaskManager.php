<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RuntimeException;
use WeCoza\Core\Database\PostgresConnection;
use WeCoza\Events\Models\Task;
use WeCoza\Events\Models\TaskCollection;

use JsonException;
use function __;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function trim;
use const JSON_THROW_ON_ERROR;

final class TaskManager
{
    private PostgresConnection $db;

    public function __construct()
    {
        $this->db = PostgresConnection::getInstance();
    }

    /**
     * Mark a task as completed.
     *
     * For event tasks, updates event_dates JSONB at the specified index.
     * For agent-order task, writes order number to classes.order_nr.
     *
     * @param int $classId Class ID
     * @param string $taskId Task ID (e.g., "agent-order" or "event-0")
     * @param int $userId User ID who completed
     * @param string $timestamp Completion timestamp
     * @param string|null $note Optional note (required for agent-order)
     * @return TaskCollection Fresh task collection after update
     * @throws RuntimeException If task ID invalid or order number missing
     */
    public function markTaskCompleted(
        int $classId,
        string $taskId,
        int $userId,
        string $timestamp,
        ?string $note = null
    ): TaskCollection {
        $cleanNote = $note !== null ? trim($note) : null;

        // Handle agent-order task specially
        if ($taskId === 'agent-order') {
            return $this->completeAgentOrderTask($classId, $userId, $timestamp, $cleanNote);
        }

        // Parse event index from task ID
        $eventIndex = $this->parseEventIndex($taskId);
        if ($eventIndex === null) {
            throw new RuntimeException(__('Invalid task ID format.', 'wecoza-events'));
        }

        // Update event_dates JSONB at specific index
        $this->updateEventStatus($classId, $eventIndex, 'Completed', $userId, $timestamp, $cleanNote);

        // Return fresh tasks
        $class = $this->fetchClassById($classId);
        return $this->buildTasksFromEvents($class);
    }

    /**
     * Complete the Agent Order Number task.
     *
     * Validates order number and writes to classes.order_nr with completion metadata.
     *
     * @param int $classId Class ID
     * @param int $userId User ID who completed
     * @param string $timestamp Completion timestamp
     * @param string|null $note Order number value
     * @return TaskCollection Fresh task collection after update
     * @throws RuntimeException If order number missing or empty
     */
    private function completeAgentOrderTask(
        int $classId,
        int $userId,
        string $timestamp,
        ?string $note
    ): TaskCollection {
        $orderNumber = $this->normaliseOrderNumber($note ?? '');
        if ($orderNumber === '') {
            throw new RuntimeException(__('An order number is required before completing this task.', 'wecoza-events'));
        }

        $this->updateClassOrderNumber($classId, $orderNumber, $userId, $timestamp);

        // Return fresh tasks
        $class = $this->fetchClassById($classId);
        return $this->buildTasksFromEvents($class);
    }

    /**
     * Reopen a previously completed task.
     *
     * For event tasks, sets status to Pending and clears completion metadata (preserves notes).
     * For agent-order task, sets order_nr to empty string.
     *
     * @param int $classId Class ID
     * @param string $taskId Task ID (e.g., "agent-order" or "event-0")
     * @return TaskCollection Fresh task collection after update
     * @throws RuntimeException If task ID invalid
     */
    public function reopenTask(int $classId, string $taskId): TaskCollection
    {
        // Handle agent-order task specially - set order_nr to empty string
        if ($taskId === 'agent-order') {
            return $this->reopenAgentOrderTask($classId);
        }

        // Parse event index from task ID
        $eventIndex = $this->parseEventIndex($taskId);
        if ($eventIndex === null) {
            throw new RuntimeException(__('Invalid task ID format.', 'wecoza-events'));
        }

        // Fetch existing event to preserve notes
        $class = $this->fetchClassById($classId);
        $events = $this->parseEventDates($class['event_dates'] ?? null);
        $existingNotes = isset($events[$eventIndex]['notes']) && $events[$eventIndex]['notes'] !== ''
            ? (string) $events[$eventIndex]['notes']
            : null;

        // Update event_dates JSONB: set status to Pending, clear completion metadata, preserve notes
        $this->updateEventStatus($classId, $eventIndex, 'Pending', null, null, $existingNotes);

        // Return fresh tasks
        $class = $this->fetchClassById($classId);
        return $this->buildTasksFromEvents($class);
    }

    /**
     * Reopen the Agent Order Number task.
     *
     * Sets order_nr to empty string which marks the task as incomplete.
     *
     * @param int $classId Class ID
     * @return TaskCollection Fresh task collection after update
     */
    private function reopenAgentOrderTask(int $classId): TaskCollection
    {
        // Set order_nr to empty string = incomplete
        $this->updateClassOrderNumber($classId, '');

        // Return fresh tasks
        $class = $this->fetchClassById($classId);
        return $this->buildTasksFromEvents($class);
    }

    private function decodeJson(string $payload): mixed
    {
        if ($payload === '') {
            return [];
        }

        return json_decode($payload, true);
    }

    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode tasks payload: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function requiresNote(string $taskId): bool
    {
        return $taskId === 'agent-order';
    }

    /**
     * Fetch a class by ID with fields needed for task building.
     *
     * @param int $classId Class ID
     * @return array<string, mixed> Class data with class_id, order_nr, order_nr_metadata, event_dates
     * @throws RuntimeException If class not found
     */
    private function fetchClassById(int $classId): array
    {
        $sql = "SELECT class_id, order_nr, order_nr_metadata, event_dates FROM classes WHERE class_id = :class_id LIMIT 1";

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare class lookup.');
        }

        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute class lookup.');
        }

        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($class === false) {
            throw new RuntimeException(__('Class not found.', 'wecoza-events'));
        }

        return $class;
    }

    /**
     * Update the class order number and optionally store completion metadata.
     *
     * When completing (non-empty order number), stores metadata as JSONB.
     * When reopening (empty order number), clears metadata.
     *
     * @param int $classId Class ID
     * @param string $orderNumber Order number value (empty string = incomplete)
     * @param int|null $userId User ID who completed (null for reopen)
     * @param string|null $timestamp Completion timestamp (null for reopen)
     * @throws RuntimeException If update fails
     */
    private function updateClassOrderNumber(int $classId, string $orderNumber, ?int $userId = null, ?string $timestamp = null): void
    {
        $metadata = null;
        if ($orderNumber !== '' && $userId !== null && $timestamp !== null) {
            $metadata = json_encode(['completed_by' => $userId, 'completed_at' => $timestamp], JSON_THROW_ON_ERROR);
        }

        $sql = "UPDATE classes SET order_nr = :order_nr, order_nr_metadata = :metadata, updated_at = now() WHERE class_id = :class_id";

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare order number update.');
        }

        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':order_nr', $orderNumber, PDO::PARAM_STR);
        $stmt->bindValue(':metadata', $metadata, $metadata !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update class order number.');
        }
    }

    private function normaliseOrderNumber(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[[:cntrl:]]+/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > 100) {
            $value = mb_substr($value, 0, 100);
        }

        return $value;
    }

    /**
     * Parse the event index from a task ID.
     *
     * @param string $taskId Task ID (e.g., "event-3" or "agent-order")
     * @return int|null Integer index for event tasks, null for non-event tasks
     */
    public function parseEventIndex(string $taskId): ?int
    {
        if (preg_match('/^event-(\d+)$/', $taskId, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Update a specific event's status in the classes.event_dates JSONB array.
     *
     * Uses PostgreSQL jsonb_set() for atomic update of a single event element.
     *
     * @param int $classId Class ID
     * @param int $eventIndex Zero-based index of the event in the array
     * @param string $status New status ('Pending' or 'Completed')
     * @param int|null $completedBy User ID who completed (null for Pending)
     * @param string|null $completedAt ISO timestamp of completion (null for Pending)
     * @param string|null $notes Optional notes to include
     * @throws RuntimeException If update fails
     */
    public function updateEventStatus(
        int $classId,
        int $eventIndex,
        string $status,
        ?int $completedBy,
        ?string $completedAt,
        ?string $notes = null
    ): void {
        // Build the updates object
        $updates = ['status' => $status];

        if ($status === 'Completed') {
            $updates['completed_by'] = $completedBy;
            $updates['completed_at'] = $completedAt;
        } else {
            // Pending: clear completion metadata
            $updates['completed_by'] = null;
            $updates['completed_at'] = null;
        }

        if ($notes !== null) {
            $updates['notes'] = $notes;
        }

        $updatesJson = json_encode($updates, JSON_THROW_ON_ERROR);
        $path = '{' . $eventIndex . '}';

        // Note: We embed $eventIndex directly in the SQL because PostgreSQL's -> operator
        // doesn't work reliably with PDO bound parameters. The index is validated as int above.
        $sql = <<<SQL
UPDATE classes
SET event_dates = jsonb_set(
    event_dates,
    :path::text[],
    (event_dates->{$eventIndex}) || :updates::jsonb,
    true
),
    updated_at = NOW()
WHERE class_id = :class_id
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare event status update.');
        }

        $stmt->bindValue(':path', $path, PDO::PARAM_STR);
        $stmt->bindValue(':updates', $updatesJson, PDO::PARAM_STR);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update event status.');
        }
    }

    /**
     * Parse event_dates JSONB field into an array.
     *
     * Handles both string (JSONB from database) and array (already decoded) formats.
     *
     * @param mixed $eventDatesRaw Raw event_dates value from database
     * @return array<int, array<string, mixed>> Array of events
     */
    private function parseEventDates($eventDatesRaw): array
    {
        if ($eventDatesRaw === null || $eventDatesRaw === '') {
            return [];
        }

        if (is_string($eventDatesRaw)) {
            $decoded = json_decode($eventDatesRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            wecoza_log('Invalid JSON in event_dates: ' . json_last_error_msg(), 'warning');
            return [];
        }

        return is_array($eventDatesRaw) ? $eventDatesRaw : [];
    }

    /**
     * Build a TaskCollection from the class's event_dates JSONB array.
     *
     * Always includes the Agent Order Number task, plus one task per event.
     *
     * @param array<string, mixed> $class Class data array with 'order_nr' and 'event_dates' keys
     * @return TaskCollection Collection of tasks derived from events
     */
    public function buildTasksFromEvents(array $class): TaskCollection
    {
        $collection = new TaskCollection();

        // Agent Order Number task is always present
        $collection->add($this->buildAgentOrderTask($class));

        // Parse event_dates JSONB
        $events = $this->parseEventDates($class['event_dates'] ?? null);

        // Build task for each event
        foreach ($events as $index => $event) {
            if (is_array($event)) {
                $collection->add($this->buildEventTask((int) $index, $event));
            }
        }

        return $collection;
    }

    /**
     * Build the Agent Order Number task.
     *
     * Status is completed if order_nr is non-null AND non-empty string.
     * Reads completion metadata from order_nr_metadata JSONB column.
     *
     * @param array<string, mixed> $class Class data array
     * @return Task The Agent Order Number task
     */
    private function buildAgentOrderTask(array $class): Task
    {
        $orderNr = $class['order_nr'] ?? null;

        // Explicit check: completed only if non-null AND non-empty string
        $isComplete = $orderNr !== null && $orderNr !== '';
        $status = $isComplete ? Task::STATUS_COMPLETED : Task::STATUS_OPEN;

        // Extract completion metadata from JSONB column
        $metadata = null;
        $completedBy = null;
        $completedAt = null;

        if (isset($class['order_nr_metadata']) && $class['order_nr_metadata'] !== null) {
            if (is_string($class['order_nr_metadata'])) {
                $metadata = json_decode($class['order_nr_metadata'], true);
            } elseif (is_array($class['order_nr_metadata'])) {
                $metadata = $class['order_nr_metadata'];
            }
        }

        if (is_array($metadata)) {
            $completedBy = isset($metadata['completed_by']) ? (int) $metadata['completed_by'] : null;
            $completedAt = isset($metadata['completed_at']) && $metadata['completed_at'] !== '' ? (string) $metadata['completed_at'] : null;
        }

        return new Task(
            'agent-order',
            'Agent Order Number',
            $status,
            $completedBy,
            $completedAt,
            $isComplete ? (string) $orderNr : null
        );
    }

    /**
     * Build a task from an event array.
     *
     * Label format: "{type}: {description}" if description non-empty, else just "{type}".
     * Status derived from event['status'] field.
     *
     * @param int $index Event index for ID generation
     * @param array<string, mixed> $event Event data from event_dates JSONB
     * @return Task The event task
     */
    private function buildEventTask(int $index, array $event): Task
    {
        // Extract type with fallback
        $type = isset($event['type']) && $event['type'] !== '' ? (string) $event['type'] : 'Unknown Event';

        // Extract and trim description
        $description = isset($event['description']) ? trim((string) $event['description']) : '';

        // Format label
        $label = $description !== '' ? "{$type}: {$description}" : $type;

        // Derive status from event status field
        $eventStatus = $event['status'] ?? 'Pending';
        $status = match ($eventStatus) {
            'Completed' => Task::STATUS_COMPLETED,
            'Pending', 'Cancelled' => Task::STATUS_OPEN,
            default => Task::STATUS_OPEN,
        };

        // Extract completion metadata
        $completedBy = isset($event['completed_by']) ? (int) $event['completed_by'] : null;
        $completedAt = isset($event['completed_at']) && $event['completed_at'] !== '' ? (string) $event['completed_at'] : null;

        // Extract notes
        $notes = isset($event['notes']) && $event['notes'] !== '' ? (string) $event['notes'] : null;

        // Extract and format event date
        $rawDate = $event['date'] ?? null;
        $eventDate = null;
        if ($rawDate !== null && $rawDate !== '') {
            try {
                $dt = new \DateTimeImmutable((string) $rawDate);
                $eventDate = $dt->format('j M Y'); // e.g., "20 Feb 2026"
            } catch (\Exception $e) {
                wecoza_log("Unparseable event date '{$rawDate}': " . $e->getMessage(), 'warning');
            }
        }

        return new Task(
            "event-{$index}",
            $label,
            $status,
            $completedBy,
            $completedAt,
            $notes,
            $eventDate
        );
    }
}
