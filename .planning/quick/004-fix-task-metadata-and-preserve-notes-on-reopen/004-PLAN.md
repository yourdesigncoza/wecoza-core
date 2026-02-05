---
phase: quick
plan: 004
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Events/Services/TaskManager.php
  - schema/agent_order_metadata.sql
autonomous: true

must_haves:
  truths:
    - "Agent Order Number task shows user name and completion time when completed"
    - "Event task notes are preserved when reopening a completed task"
  artifacts:
    - path: "src/Events/Services/TaskManager.php"
      provides: "Agent order metadata storage and note preservation"
    - path: "schema/agent_order_metadata.sql"
      provides: "DDL for order_nr_metadata column"
  key_links:
    - from: "TaskManager::completeAgentOrderTask()"
      to: "classes.order_nr_metadata JSONB"
      via: "stores completed_by/completed_at"
    - from: "TaskManager::buildAgentOrderTask()"
      to: "classes.order_nr_metadata"
      via: "reads metadata for display"
    - from: "TaskManager::updateEventStatus()"
      to: "event notes"
      via: "reads existing notes before update"
---

<objective>
Fix two bugs in task completion workflow:
1. Agent Order Number task shows "Unknown user / Unknown time" instead of actual completion metadata
2. Task notes disappear when reopening a completed event task

Purpose: Restore data visibility and prevent user data loss in task management
Output: Working metadata display for agent-order task, preserved notes on reopen
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@src/Events/Services/TaskManager.php
@src/Events/Views/Presenters/ClassTaskPresenter.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add order_nr_metadata column and update TaskManager for agent-order metadata</name>
  <files>
    schema/agent_order_metadata.sql
    src/Events/Services/TaskManager.php
  </files>
  <action>
1. Create schema/agent_order_metadata.sql with DDL:
   ```sql
   ALTER TABLE classes ADD COLUMN IF NOT EXISTS order_nr_metadata JSONB;
   COMMENT ON COLUMN classes.order_nr_metadata IS 'Completion metadata for Agent Order Number task: {completed_by, completed_at}';
   ```

2. Update TaskManager::fetchClassById() to also select order_nr_metadata:
   ```php
   $sql = "SELECT class_id, order_nr, order_nr_metadata, event_dates FROM classes WHERE class_id = :class_id LIMIT 1";
   ```

3. Update TaskManager::updateClassOrderNumber() to also save metadata:
   - Add optional $userId and $timestamp parameters
   - When $orderNumber is non-empty (completion), store metadata as JSONB
   - When $orderNumber is empty (reopen), set metadata to NULL
   ```php
   private function updateClassOrderNumber(int $classId, string $orderNumber, ?int $userId = null, ?string $timestamp = null): void
   {
       $metadata = null;
       if ($orderNumber !== '' && $userId !== null && $timestamp !== null) {
           $metadata = json_encode(['completed_by' => $userId, 'completed_at' => $timestamp], JSON_THROW_ON_ERROR);
       }

       $sql = "UPDATE classes SET order_nr = :order_nr, order_nr_metadata = :metadata, updated_at = now() WHERE class_id = :class_id";
       // ... bind and execute
   }
   ```

4. Update TaskManager::completeAgentOrderTask() to pass userId and timestamp to updateClassOrderNumber().

5. Update TaskManager::buildAgentOrderTask() to read metadata and pass to Task constructor:
   ```php
   $metadata = isset($class['order_nr_metadata']) ? json_decode($class['order_nr_metadata'], true) : null;
   $completedBy = $metadata['completed_by'] ?? null;
   $completedAt = $metadata['completed_at'] ?? null;

   return new Task(
       'agent-order',
       'Agent Order Number',
       $status,
       $completedBy,
       $completedAt,
       $isComplete ? (string) $orderNr : null
   );
   ```
  </action>
  <verify>
After user runs the DDL, complete an agent-order task in the dashboard. Check the Completed Tasks section shows the user name and timestamp (not "Unknown user / Unknown time").
  </verify>
  <done>Agent Order Number task displays actual user name and completion timestamp when completed.</done>
</task>

<task type="auto">
  <name>Task 2: Preserve notes when reopening event tasks</name>
  <files>src/Events/Services/TaskManager.php</files>
  <action>
The bug: TaskManager::reopenTask() calls updateEventStatus() with null for notes, which means the existing note is not carried forward. The updateEventStatus() method only includes notes in the update if $notes !== null.

When reopening, we need to:
1. First fetch the existing event to get its current notes
2. Pass those notes through to updateEventStatus()

Update TaskManager::reopenTask() for event tasks:

```php
public function reopenTask(int $classId, string $taskId): TaskCollection
{
    if ($taskId === 'agent-order') {
        return $this->reopenAgentOrderTask($classId);
    }

    $eventIndex = $this->parseEventIndex($taskId);
    if ($eventIndex === null) {
        throw new RuntimeException(__('Invalid task ID format.', 'wecoza-events'));
    }

    // ADDED: Fetch existing event to preserve notes
    $class = $this->fetchClassById($classId);
    $events = $this->parseEventDates($class['event_dates'] ?? null);
    $existingNotes = $events[$eventIndex]['notes'] ?? null;

    // Pass existing notes to preserve them
    $this->updateEventStatus($classId, $eventIndex, 'Pending', null, null, $existingNotes);

    // Return fresh tasks
    $class = $this->fetchClassById($classId);
    return $this->buildTasksFromEvents($class);
}
```

Also add a helper method to parse event_dates (DRY extraction from buildTasksFromEvents):

```php
private function parseEventDates($eventDatesRaw): array
{
    if ($eventDatesRaw === null || $eventDatesRaw === '') {
        return [];
    }
    if (is_string($eventDatesRaw)) {
        $decoded = json_decode($eventDatesRaw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
    }
    return is_array($eventDatesRaw) ? $eventDatesRaw : [];
}
```

Update buildTasksFromEvents() to use this helper for DRY.
  </action>
  <verify>
1. Complete an event task with a note (e.g., "Notify Front Desk")
2. Confirm note appears in Completed Tasks
3. Reopen the task
4. Confirm note is still visible in Open Tasks
  </verify>
  <done>Notes persist when reopening event tasks.</done>
</task>

</tasks>

<verification>
- [ ] Run schema DDL in database (user action)
- [ ] Complete Agent Order Number task with value "12345"
- [ ] Verify completed task shows actual user/time (not "Unknown")
- [ ] Complete an event task with a note
- [ ] Reopen the event task
- [ ] Verify note is preserved in Open Tasks view
</verification>

<success_criteria>
1. Agent Order Number task displays real user name and completion timestamp
2. Event task notes persist through reopen cycle
3. No regressions in task completion/reopen functionality
</success_criteria>

<output>
After completion, create `.planning/quick/004-fix-task-metadata-and-preserve-notes-on-reopen/004-SUMMARY.md`
</output>
