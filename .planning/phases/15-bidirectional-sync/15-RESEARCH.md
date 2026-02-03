# Phase 15: Bidirectional Sync - Research

**Researched:** 2026-02-03
**Domain:** PostgreSQL JSONB updates, WordPress AJAX handlers, event-form synchronization
**Confidence:** HIGH

## Summary

Researched implementing bidirectional synchronization between the task dashboard and class form event data. The current TaskController uses `log_id` which no longer exists (class_change_logs dropped in Phase 13). Phase 15 rewrites TaskController to use `class_id` and perform JSONB updates directly to `classes.event_dates`.

Key findings:
- TaskController must switch from `log_id` to `class_id` as primary identifier
- PostgreSQL `jsonb_set()` enables atomic updates to specific array elements by index
- Current `Task::reopen()` clears notes, but SYNC-04 requires preserving them
- FormDataProcessor handles event_dates but doesn't process `completed_by`/`completed_at` fields
- Agent Order Number completion already writes to `classes.order_nr` (working in Phase 14)
- Form-to-Dashboard sync is "free" since dashboard rebuilds tasks from fresh data on each load

**Primary recommendation:** Refactor TaskManager to accept `class_id` instead of `log_id`, implement `updateEventStatus()` method that uses `jsonb_set()` to update specific event array elements, and modify `Task::reopen()` to preserve notes.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PostgreSQL | 9.5+ | JSONB `jsonb_set()` for atomic updates | Native array element updates without full document replacement |
| PDO | PHP 8.0+ | Prepared statements with named parameters | Existing codebase pattern, type-safe parameter binding |
| WordPress | 6.0+ | `get_current_user_id()`, `current_time()` functions | Native user/timestamp retrieval |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| N/A | N/A | No external libraries needed | All functionality native to PHP/PostgreSQL/WordPress |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| jsonb_set() | PHP array decode/modify/encode | jsonb_set() is atomic, avoids race conditions |
| class_id parameter | Lookup class_id from event somewhere | Direct class_id simpler, no extra query |
| Preserving notes in Task::reopen() | Separate "notes" storage | Notes belong with task/event, not separate |

**Installation:**
```bash
# No new dependencies required
# Existing: PostgreSQL with JSONB support, PHP 8.0+ with PDO, WordPress 6.0+
```

## Architecture Patterns

### Recommended Project Structure
```
src/Events/
├── Controllers/
│   └── TaskController.php     # MODIFY: Switch log_id -> class_id, call new methods
├── Services/
│   └── TaskManager.php        # MODIFY: Add updateEventStatus(), change method signatures
├── Models/
│   └── Task.php               # MODIFY: reopen() preserves notes
└── Repositories/
    └── (No changes needed)    # Queries already return class_id
```

### Pattern 1: JSONB Array Element Update
**What:** Use PostgreSQL `jsonb_set()` to update a specific element in the event_dates array
**When to use:** Completing or reopening an event-based task
**Example:**
```php
// Source: PostgreSQL 9.5+ documentation + Phase 14 research
private function updateEventAtIndex(int $classId, int $eventIndex, array $updates): void
{
    // Build the new event object by merging existing with updates
    // jsonb_set replaces the entire element at that index
    $sql = <<<SQL
UPDATE classes
SET event_dates = jsonb_set(
    event_dates,
    :path,
    (event_dates->:index_int) || :updates::jsonb,
    true
),
    updated_at = NOW()
WHERE class_id = :class_id
SQL;

    $stmt = $this->db->getPdo()->prepare($sql);
    $stmt->bindValue(':path', '{' . $eventIndex . '}', PDO::PARAM_STR);
    $stmt->bindValue(':index_int', $eventIndex, PDO::PARAM_INT);
    $stmt->bindValue(':updates', json_encode($updates), PDO::PARAM_STR);
    $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
    $stmt->execute();
}
```

### Pattern 2: Task ID Parsing for Event Index Extraction
**What:** Parse task_id to extract event array index
**When to use:** TaskController receives task_id like "event-3", needs integer 3
**Example:**
```php
// Source: Current TaskManager task ID convention (Phase 14)
private function parseEventIndex(string $taskId): ?int
{
    if (preg_match('/^event-(\d+)$/', $taskId, $matches)) {
        return (int) $matches[1];
    }
    return null;  // Not an event task (e.g., "agent-order")
}
```

### Pattern 3: Completion Metadata in Event Schema
**What:** Add completed_by, completed_at fields to event objects
**When to use:** Marking event as completed
**Example event structure:**
```json
{
  "type": "Training",
  "description": "Week 1 complete",
  "date": "2026-02-10",
  "status": "Completed",
  "notes": "Went well",
  "completed_by": 42,
  "completed_at": "2026-02-10T14:30:00Z"
}
```

### Pattern 4: Preserve Notes on Reopen
**What:** Task::reopen() keeps existing note, only clears completion metadata
**When to use:** Reopening a task that has notes
**Example:**
```php
// Source: SYNC-04 requirement
public function reopen(): self
{
    $clone = clone $this;
    $clone->status = self::STATUS_OPEN;
    $clone->completedBy = null;
    $clone->completedAt = null;
    // NOTE: Do NOT clear $clone->note - preserve it
    return $clone;
}
```

### Anti-Patterns to Avoid
- **Using log_id anywhere:** class_change_logs table is gone. Use class_id exclusively.
- **Full event_dates replacement in PHP:** Race condition risk. Use jsonb_set() for atomic element updates.
- **Clearing notes on reopen:** User data loss. Notes persist independent of completion status.
- **Storing completion metadata in separate table:** Denormalizes. Keep with event object.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Array element JSONB update | JSON decode, array modify, JSON encode, UPDATE | PostgreSQL `jsonb_set()` | Atomic operation, handles concurrency |
| User ID retrieval | Custom auth checks | `get_current_user_id()` | WordPress handles session, roles, etc. |
| Timestamp generation | `date()` or `time()` | `current_time('mysql', true)` | Respects WordPress timezone, returns ISO format |
| Task ID parsing | String split, manual validation | Regex with preg_match | Clean capture groups, validation in one step |

**Key insight:** The sync is truly "bidirectional" but asymmetric. Dashboard writes to event_dates; form reads event_dates. The form-to-dashboard direction requires no code because dashboard rebuilds from fresh data every load. Only dashboard-to-form (task completion) needs implementation.

## Common Pitfalls

### Pitfall 1: TaskController Still Uses log_id Parameter
**What goes wrong:** AJAX requests send log_id, but class_change_logs table doesn't exist
**Why it happens:** Phase 14 updated ClassTaskService but TaskController wasn't updated
**How to avoid:** Change TaskController to accept class_id parameter. Update JavaScript to send class_id instead of log_id.
**Warning signs:** "Invalid task request" errors or 500 errors in console

### Pitfall 2: Event Index Mismatch Between Read and Write
**What goes wrong:** User loads dashboard, event at index 2. User deletes event in form (index shifts). User completes event-2 in dashboard. Wrong event updated.
**Why it happens:** Event indices are volatile; array can be modified between page load and AJAX call
**How to avoid:** Accept this as v1.2 limitation. Document that concurrent form edits may cause issues. Future: add optimistic locking or stable event IDs.
**Warning signs:** Task completion updates wrong event

### Pitfall 3: Agent Order Number Reopen Clears order_nr Incorrectly
**What goes wrong:** Reopening Agent Order Number should not clear order_nr (different from event status)
**Why it happens:** Current logic treats Agent Order Number same as events
**How to avoid:** Special-case agent-order task in reopen flow. Reopening agent-order sets order_nr to empty string (incomplete), but this is different from clearing notes.
**Warning signs:** Reopening Agent Order Number task leaves order_nr value but shows task as open

### Pitfall 4: Notes Cleared on Reopen
**What goes wrong:** User adds note to event, completes it, then reopens. Note is gone.
**Why it happens:** Current Task::reopen() sets note to null
**How to avoid:** Modify Task::reopen() to preserve note. Only clear completedBy and completedAt.
**Warning signs:** User complaints about lost notes after reopening tasks

### Pitfall 5: FormDataProcessor Ignores completed_by/completed_at
**What goes wrong:** User saves class form. completion metadata stripped from event_dates.
**Why it happens:** FormDataProcessor::processFormData() only extracts type, description, date, status, notes
**How to avoid:** Update FormDataProcessor to pass through completed_by and completed_at if present.
**Warning signs:** Task shows open after form save even though it was completed

### Pitfall 6: ISO Timestamp Format Mismatch
**What goes wrong:** completed_at stored as "2026-02-03 14:30:00", JavaScript expects ISO8601
**Why it happens:** current_time('mysql', true) returns MySQL format, not ISO
**How to avoid:** Use `date('c')` for ISO8601 or `gmdate('Y-m-d\TH:i:s\Z')` for UTC. Or use current_time and accept MySQL format.
**Warning signs:** Date parsing errors in JavaScript, NaN dates

## Code Examples

Verified patterns from existing codebase and official sources:

### TaskController with class_id Parameter
```php
// Source: Refactored from current TaskController.php
public function handleUpdate(): void
{
    check_ajax_referer('wecoza_events_tasks', 'nonce');

    if (!is_user_logged_in()) {
        $this->responder->error(__('Please sign in to manage tasks.', 'wecoza-events'), 403);
    }

    // CHANGED: class_id instead of log_id
    $classId = $this->request->getPostInt('class_id') ?? 0;
    $taskId = $this->request->getPostString('task_id', '') ?? '';
    $taskAction = $this->request->getPostString('task_action', '') ?? '';

    if ($classId <= 0 || $taskId === '' || !in_array($taskAction, ['complete', 'reopen'], true)) {
        $this->responder->error(__('Invalid task request.', 'wecoza-events'), 400);
    }

    try {
        if ($taskAction === 'complete') {
            $note = $this->request->getPostString('note');
            $note = $note !== null ? trim($note) : null;
            // CHANGED: Pass classId, not logId
            $tasks = $this->manager->markTaskCompleted(
                $classId,
                $taskId,
                get_current_user_id(),
                current_time('mysql', true),
                $note
            );
        } else {
            $tasks = $this->manager->reopenTask($classId, $taskId);
        }
    } catch (Throwable $exception) {
        $this->responder->error($exception->getMessage(), 500);
    }

    $this->responder->success([
        'tasks' => $this->presenter->presentTasks($tasks),
    ]);
}
```

### TaskManager::markTaskCompleted() with JSONB Update
```php
// Source: New implementation for Phase 15
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
```

### JSONB Update for Event Status
```sql
-- Source: PostgreSQL jsonb_set documentation
-- Update event at index 2 with completion data
UPDATE classes
SET event_dates = jsonb_set(
    event_dates,
    '{2}',
    (event_dates->2) || '{"status": "Completed", "completed_by": 42, "completed_at": "2026-02-03T14:30:00Z"}'::jsonb,
    true
),
    updated_at = NOW()
WHERE class_id = 123;
```

### PHP Implementation of updateEventStatus
```php
// Source: Phase 15 implementation
private function updateEventStatus(
    int $classId,
    int $eventIndex,
    string $status,
    ?int $completedBy,
    ?string $completedAt,
    ?string $notes
): void {
    $updates = ['status' => $status];

    if ($status === 'Completed') {
        $updates['completed_by'] = $completedBy;
        $updates['completed_at'] = $completedAt;
    } else {
        // Reopen: clear completion metadata, preserve notes
        $updates['completed_by'] = null;
        $updates['completed_at'] = null;
    }

    // Notes preserved or updated based on what was passed
    if ($notes !== null) {
        $updates['notes'] = $notes;
    }

    $sql = <<<SQL
UPDATE classes
SET event_dates = jsonb_set(
    event_dates,
    :path,
    (event_dates->:index) || :updates::jsonb,
    true
),
    updated_at = NOW()
WHERE class_id = :class_id
SQL;

    $stmt = $this->db->getPdo()->prepare($sql);
    $stmt->bindValue(':path', '{' . $eventIndex . '}', PDO::PARAM_STR);
    $stmt->bindValue(':index', $eventIndex, PDO::PARAM_INT);
    $stmt->bindValue(':updates', json_encode($updates, JSON_THROW_ON_ERROR), PDO::PARAM_STR);
    $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
    $stmt->execute();
}
```

### FormDataProcessor Event Dates with Completion Metadata
```php
// Source: Updated FormDataProcessor::processFormData()
// In event_dates processing section:
for ($i = 0; $i < count($types); $i++) {
    $currentType = $types[$i] ?? '';
    $currentDate = $dates[$i] ?? '';
    if (!empty($currentType) && !empty($currentDate)) {
        $status = self::sanitizeText($statuses[$i] ?? 'Pending');
        $event = [
            'type' => self::sanitizeText($currentType),
            'description' => self::sanitizeText($descriptions[$i] ?? ''),
            'date' => self::sanitizeText($currentDate),
            'status' => in_array($status, $allowedStatuses) ? $status : 'Pending',
            'notes' => self::sanitizeText($notes[$i] ?? '')
        ];

        // NEW: Preserve completion metadata if present
        if (isset($completedBy[$i]) && !empty($completedBy[$i])) {
            $event['completed_by'] = intval($completedBy[$i]);
        }
        if (isset($completedAt[$i]) && !empty($completedAt[$i])) {
            $event['completed_at'] = self::sanitizeText($completedAt[$i]);
        }

        $eventDates[] = $event;
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| TaskController uses log_id | TaskController uses class_id | v1.2 (Phase 15) | Matches Phase 14 architecture, no change logs |
| Full event_dates PHP replacement | PostgreSQL jsonb_set() atomic update | v1.2 (Phase 15) | Prevents race conditions on concurrent updates |
| Notes cleared on reopen | Notes preserved on reopen | v1.2 (Phase 15) | User data not lost when reopening tasks |
| Completion metadata not tracked | completed_by/completed_at in event schema | v1.2 (Phase 15) | Audit trail for who completed what, when |

**Deprecated/outdated:**
- log_id parameter: Replaced by class_id (Phase 14 removed class_change_logs)
- TaskManager methods accepting logId: Replaced with classId variants
- Task::reopen() clearing notes: Modified to preserve notes

## Open Questions

Things that couldn't be fully resolved:

1. **Concurrent form and dashboard edits**
   - What we know: Event indices can shift if form is edited between dashboard load and task completion
   - What's unclear: Should we add optimistic locking? Stable event IDs?
   - Recommendation: Accept as v1.2 limitation. Document in release notes. Future: add event_id field for stable identification.

2. **Agent Order Number reopen behavior**
   - What we know: SYNC-05 says write order_nr on completion. SYNC-04 says preserve notes on reopen.
   - What's unclear: Should reopening Agent Order Number clear order_nr or just mark task open?
   - Recommendation: Reopening Agent Order Number should set order_nr to empty string (marks incomplete). This is consistent with "empty string = incomplete" decision from Phase 14.

3. **FormDataProcessor hidden fields for completion metadata**
   - What we know: Form needs to submit completed_by/completed_at to preserve them on save
   - What's unclear: Should these be hidden inputs or stored separately?
   - Recommendation: Add hidden inputs for completion metadata. Minimal form changes, preserves existing architecture.

## Sources

### Primary (HIGH confidence)
- Current codebase: TaskController.php, TaskManager.php, FormDataProcessor.php, Task.php
- PostgreSQL 9.5+ jsonb_set(): https://www.postgresql.org/docs/9.5/functions-json.html
- Phase 14 research: .planning/phases/14-task-system-refactor/14-RESEARCH.md
- Requirements: .planning/REQUIREMENTS.md (SYNC-01 through SYNC-05, REPO-03)

### Secondary (MEDIUM confidence)
- WordPress get_current_user_id(): https://developer.wordpress.org/reference/functions/get_current_user_id/
- WordPress current_time(): https://developer.wordpress.org/reference/functions/current_time/
- JSONB merge operator (||): https://www.postgresql.org/docs/14/functions-json.html

### Tertiary (LOW confidence)
- None - all findings verified with official sources

## Metadata

**Confidence breakdown:**
- TaskController refactor: HIGH - Direct code analysis, clear path from current to target
- JSONB update pattern: HIGH - PostgreSQL docs verified, existing codebase uses similar patterns
- FormDataProcessor changes: HIGH - Code analyzed, changes minimal and well-scoped
- Task::reopen() preservation: HIGH - Single line change, requirement explicit (SYNC-04)
- Concurrent edit handling: MEDIUM - Known limitation documented, not solved in this phase

**Research date:** 2026-02-03
**Valid until:** 2026-03-03 (30 days - stable domain, no external dependencies)
