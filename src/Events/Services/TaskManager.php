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
    private TaskTemplateRegistry $registry;

    public function __construct(?TaskTemplateRegistry $registry = null)
    {
        $this->db = PostgresConnection::getInstance();
        $this->registry = $registry ?? new TaskTemplateRegistry();
    }

    public function getTasksWithTemplate(int $logId, ?string $operation = null): TaskCollection
    {
        $operation = $operation ?? $this->fetchOperation($logId);

        $existing = $this->getTasksForLog($logId);
        $needsPersist = false;

        if ($existing->isEmpty()) {
            $classId = $this->fetchClassIdForLog($logId);
            $previous = $this->getPreviousTasksSnapshot($classId, $logId);
            if ($previous !== null && !$previous->isEmpty()) {
                $existing = $previous;
                $needsPersist = true;
            } else {
                $existing = $this->registry->getTemplateForOperation('insert');
                $needsPersist = true;
            }
        }

        $template = $this->registry->getTemplateForOperation($operation);

        foreach ($template->all() as $task) {
            if (!$existing->has($task->getId())) {
                $existing->add($task);
                $needsPersist = true;
            }
        }

        if ($needsPersist) {
            $this->saveTasksForLog($logId, $existing);
        }

        return $existing;
    }

    public function markTaskCompleted(
        int $logId,
        string $taskId,
        int $userId,
        string $timestamp,
        ?string $note = null
    ): TaskCollection {
        $cleanNote = $note !== null ? trim($note) : null;
        $tasks = $this->getTasksWithTemplate($logId);

        if ($this->requiresNote($taskId)) {
            $orderNumber = $this->normaliseOrderNumber($cleanNote ?? '');
            if ($orderNumber === '') {
                throw new RuntimeException(__('An order number is required before completing this task.', 'wecoza-events'));
            }

            $classId = $this->fetchClassIdForLog($logId);
            $this->updateClassOrderNumber($classId, $orderNumber);
            $cleanNote = $orderNumber;
        }

        $task = $tasks->get($taskId)->markCompleted(
            $userId,
            $timestamp,
            $cleanNote === null || $cleanNote === '' ? null : $cleanNote
        );
        $tasks->replace($task);
        $this->saveTasksForLog($logId, $tasks);

        return $tasks;
    }

    public function reopenTask(int $logId, string $taskId): TaskCollection
    {
        $tasks = $this->getTasksWithTemplate($logId);

        $task = $tasks->get($taskId)->reopen();
        $tasks->replace($task);
        $this->saveTasksForLog($logId, $tasks);

        return $tasks;
    }

    private function fetchOperation(int $logId): string
    {
        $sql = "SELECT operation FROM class_change_logs WHERE log_id = :id LIMIT 1";

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare operation lookup.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute operation lookup.');
        }

        $operation = $stmt->fetchColumn();
        if (!is_string($operation) || $operation === '') {
            throw new RuntimeException('Unable to determine log operation.');
        }

        return $operation;
    }

    public function getTasksForLog(int $logId): TaskCollection
    {
        $sql = "SELECT tasks FROM class_change_logs WHERE log_id = :id LIMIT 1";

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare task lookup query.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute task lookup query.');
        }

        $payload = $stmt->fetchColumn();
        if ($payload === false || $payload === null) {
            return new TaskCollection();
        }

        $decoded = $this->decodeJson($payload);
        if (!is_array($decoded)) {
            return new TaskCollection();
        }

        return TaskCollection::fromArray($decoded);
    }

    public function saveTasksForLog(int $logId, TaskCollection $tasks): void
    {
        $sql = "UPDATE class_change_logs SET tasks = :tasks WHERE log_id = :id";

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare task update query.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        $stmt->bindValue(':tasks', $this->encodeJson($tasks->toArray()), PDO::PARAM_STR);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to persist tasks payload.');
        }
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

    private function getPreviousTasksSnapshot(int $classId, int $currentLogId): ?TaskCollection
    {
        $sql = <<<SQL
SELECT tasks
FROM class_change_logs
WHERE class_id = :class_id
  AND log_id <> :log_id
  AND tasks IS NOT NULL
  AND jsonb_typeof(tasks) = 'array'
  AND jsonb_array_length(tasks) > 0
ORDER BY changed_at DESC, log_id DESC
LIMIT 1
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare previous tasks lookup.');
        }

        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':log_id', $currentLogId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute previous tasks lookup.');
        }

        $payload = $stmt->fetchColumn();
        if ($payload === false || $payload === null) {
            return null;
        }

        $decoded = $this->decodeJson((string) $payload);
        if (!is_array($decoded)) {
            return null;
        }

        return TaskCollection::fromArray($decoded);
    }

    private function requiresNote(string $taskId): bool
    {
        return $taskId === 'agent-order';
    }

    private function fetchClassIdForLog(int $logId): int
    {
        $sql = "SELECT class_id FROM class_change_logs WHERE log_id = :id LIMIT 1";

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare class lookup.');
        }

        $stmt->bindValue(':id', $logId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute class lookup.');
        }

        $classId = $stmt->fetchColumn();
        if ($classId === false || $classId === null) {
            throw new RuntimeException('Unable to determine class for the supplied task.');
        }

        return (int) $classId;
    }

    private function updateClassOrderNumber(int $classId, string $orderNumber): void
    {
        $sql = "UPDATE classes SET order_nr = :order_nr, updated_at = now() WHERE class_id = :class_id";

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare order number update.');
        }

        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':order_nr', $orderNumber, PDO::PARAM_STR);

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

        $sql = <<<SQL
UPDATE classes
SET event_dates = jsonb_set(
    event_dates,
    :path::text[],
    (event_dates->:index) || :updates::jsonb,
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
        $stmt->bindValue(':index', $eventIndex, PDO::PARAM_INT);
        $stmt->bindValue(':updates', $updatesJson, PDO::PARAM_STR);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update event status.');
        }
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

        // Decode event_dates JSONB
        $eventDatesRaw = $class['event_dates'] ?? null;
        $events = [];

        if ($eventDatesRaw !== null && $eventDatesRaw !== '') {
            if (is_string($eventDatesRaw)) {
                $decoded = json_decode($eventDatesRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $events = $decoded;
                } else {
                    wecoza_log('Invalid JSON in event_dates: ' . json_last_error_msg(), 'warning');
                }
            } elseif (is_array($eventDatesRaw)) {
                $events = $eventDatesRaw;
            }
        }

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

        return new Task(
            'agent-order',
            'Agent Order Number',
            $status,
            null, // completedBy - Phase 15 adds this
            null, // completedAt - Phase 15 adds this
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

        // Extract notes
        $notes = isset($event['notes']) && $event['notes'] !== '' ? (string) $event['notes'] : null;

        return new Task(
            "event-{$index}",
            $label,
            $status,
            null, // completedBy - Phase 15 adds this
            null, // completedAt - Phase 15 adds this
            $notes
        );
    }
}
