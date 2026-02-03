---
phase: quick-002
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Events/Models/Task.php
  - src/Events/Services/TaskManager.php
  - src/Events/Views/Presenters/ClassTaskPresenter.php
  - views/events/event-tasks/main.php
  - src/Events/Shortcodes/EventTasksShortcode.php
autonomous: true

must_haves:
  truths:
    - "Event date displays below task label in Open Tasks list"
    - "Date appears in grey, smaller text (text-body-tertiary small)"
    - "Date format is human-readable (e.g., '20 Feb 2026')"
    - "Agent Order Number task shows no date (null handling)"
  artifacts:
    - path: "src/Events/Models/Task.php"
      provides: "eventDate property and getter"
      contains: "getEventDate"
    - path: "src/Events/Services/TaskManager.php"
      provides: "Event date extraction in buildEventTask"
      contains: "eventDate"
    - path: "src/Events/Views/Presenters/ClassTaskPresenter.php"
      provides: "event_date in open task payload"
      contains: "event_date"
  key_links:
    - from: "TaskManager::buildEventTask"
      to: "Task constructor"
      via: "eventDate parameter"
      pattern: "new Task.*eventDate"
    - from: "ClassTaskPresenter::presentTasks"
      to: "Task::getEventDate"
      via: "payload assignment"
      pattern: "getEventDate"
---

<objective>
Add event date display to Open Tasks view in the Event Tasks shortcode.

Purpose: Users need to see when each event is scheduled directly in the task list, providing context without needing to expand details or cross-reference elsewhere.

Output: Event dates displayed below task labels in grey smaller text for all event tasks (not Agent Order Number task).
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/Events/Models/Task.php
@src/Events/Services/TaskManager.php
@src/Events/Views/Presenters/ClassTaskPresenter.php
@views/events/event-tasks/main.php
@src/Events/Shortcodes/EventTasksShortcode.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add eventDate property to Task model</name>
  <files>src/Events/Models/Task.php</files>
  <action>
Add eventDate property and getter to Task model:

1. Add property after $note (line 20):
   `private ?string $eventDate;`

2. Add constructor parameter after $note (line 28):
   `?string $eventDate = null`

3. Assign in constructor (after line 35):
   `$this->eventDate = $eventDate;`

4. Update fromArray() (after line 46):
   Read `event_date` key: `isset($payload['event_date']) ? (string) $payload['event_date'] : null`

5. Update toArray() (after line 68):
   Write if non-null: `if ($this->eventDate !== null) { $payload['event_date'] = $this->eventDate; }`

6. Add getter after getNote() (after line 106):
   ```php
   public function getEventDate(): ?string
   {
       return $this->eventDate;
   }
   ```

7. Update markCompleted() to preserve eventDate in clone (line 115).

8. Update reopen() to preserve eventDate in clone (line 125).
  </action>
  <verify>Run `php -l src/Events/Models/Task.php` - no syntax errors</verify>
  <done>Task model has eventDate property, constructor param, fromArray/toArray support, and getter</done>
</task>

<task type="auto">
  <name>Task 2: Extract and pass event date in TaskManager</name>
  <files>src/Events/Services/TaskManager.php</files>
  <action>
Update buildEventTask() to extract and format event date:

1. In buildEventTask() (line 570), extract date from event array:
   ```php
   // Extract and format event date
   $rawDate = $event['date'] ?? null;
   $eventDate = null;
   if ($rawDate !== null && $rawDate !== '') {
       try {
           $dt = new \DateTimeImmutable((string) $rawDate);
           $eventDate = $dt->format('j M Y'); // e.g., "20 Feb 2026"
       } catch (\Exception $e) {
           // Leave as null if unparseable
       }
   }
   ```

2. Pass $eventDate as 7th argument to Task constructor (line 596):
   Change from:
   ```php
   return new Task(
       "event-{$index}",
       $label,
       $status,
       $completedBy,
       $completedAt,
       $notes
   );
   ```
   To:
   ```php
   return new Task(
       "event-{$index}",
       $label,
       $status,
       $completedBy,
       $completedAt,
       $notes,
       $eventDate
   );
   ```

Note: buildAgentOrderTask() does NOT pass eventDate (defaults to null) - Agent Order task has no date.
  </action>
  <verify>Run `php -l src/Events/Services/TaskManager.php` - no syntax errors</verify>
  <done>TaskManager extracts event date and passes to Task constructor</done>
</task>

<task type="auto">
  <name>Task 3: Add event_date to presenter payload and update views</name>
  <files>
    src/Events/Views/Presenters/ClassTaskPresenter.php
    views/events/event-tasks/main.php
    src/Events/Shortcodes/EventTasksShortcode.php
  </files>
  <action>
**ClassTaskPresenter.php** - In presentTasks() (line 398):

Add event_date to open task payload (after line 431, before $open[] = $payload):
```php
$payload['event_date'] = $task->getEventDate();
```

**main.php** - In Open Tasks list (around line 221):

After the label div (line 221), add date display:
```php
<div class="fw-semibold text-body w-30">
    <?php echo esc_html($task['label']); ?>
    <?php if (!empty($task['event_date'])): ?>
        <div class="text-body-tertiary small"><?php echo esc_html($task['event_date']); ?></div>
    <?php endif; ?>
</div>
```

**EventTasksShortcode.php** - In buildOpenTaskHtml() (line 390):

Update the label div to include date:
```javascript
'<div class="fw-semibold text-body w-30">' + escapeHtml(task.label) +
    (task.event_date ? '<div class="text-body-tertiary small">' + escapeHtml(task.event_date) + '</div>' : '') +
'</div>' +
```

This replaces the existing:
```javascript
'<div class="fw-semibold text-body w-30">' + escapeHtml(task.label) + '</div>' +
```
  </action>
  <verify>
1. `php -l src/Events/Views/Presenters/ClassTaskPresenter.php` - no errors
2. `php -l views/events/event-tasks/main.php` - no errors
3. `php -l src/Events/Shortcodes/EventTasksShortcode.php` - no errors
4. Visit page with [wecoza_event_tasks] shortcode, expand a class - event tasks show dates below labels
  </verify>
  <done>Event dates display in grey smaller text below task labels in Open Tasks view (both PHP and JS rendering)</done>
</task>

</tasks>

<verification>
1. All PHP files pass lint check (`php -l`)
2. Visit Event Tasks page, expand any class panel
3. Event tasks (not Agent Order) show date below label in grey text
4. Complete/reopen tasks - dates persist through AJAX re-render
5. Agent Order Number task shows NO date (correct)
</verification>

<success_criteria>
- Event dates visible below task labels in Open Tasks list
- Style: grey (text-body-tertiary), smaller (small class)
- Format: human readable (e.g., "20 Feb 2026")
- Agent Order task shows no date
- Works on initial load and after AJAX updates
</success_criteria>

<output>
After completion, create `.planning/quick/002-add-date-to-open-tasks-view/002-01-SUMMARY.md`
</output>
