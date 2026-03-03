# Phase 14: Task System Refactor - Research

**Researched:** 2026-02-03
**Domain:** PostgreSQL JSONB manipulation, PHP service layer refactoring, task building patterns
**Confidence:** HIGH

## Summary

Researched migration from PostgreSQL trigger-based task generation (reading `class_change_logs.tasks`) to direct JSONB array parsing (reading `classes.event_dates`). Current architecture uses TaskManager to fetch/merge templates from TaskTemplateRegistry and store in change logs. New architecture eliminates change logs and templates entirely, building tasks on-the-fly from event_dates JSONB array plus special Agent Order Number task.

**Key findings:**
- PostgreSQL jsonb_set() function enables atomic updates to specific array indices
- Event_dates structure already exists: `[{type, description, date, status, notes}]`
- Agent Order Number task is special: always present, writes to `classes.order_nr` field
- Task IDs must follow pattern: `agent-order` (special) and `event-{index}` (events)
- Builder pattern ideal for constructing TaskCollection from heterogeneous sources

**Primary recommendation:** Use TaskManager::buildTasksFromEvents($class) factory method that constructs Agent Order Number task + event tasks in single operation, returning TaskCollection. Store NO state—build fresh on every read.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PostgreSQL | 9.5+ | JSONB array storage/manipulation | Native binary JSON with atomic updates, indexing |
| PDO | PHP 8.0+ | Database access layer | Type-safe prepared statements, existing codebase pattern |
| PHP | 8.0+ | Application language | Match expressions, typed properties, existing requirement |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| N/A | N/A | No external libraries needed | All functionality native to PHP/PostgreSQL |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| jsonb_set() | PHP array manipulation | PostgreSQL atomicity better for concurrent updates |
| Builder pattern | Direct Task construction | Builder handles Agent Order Number + events elegantly |
| On-the-fly building | Caching TaskCollections | Cache invalidation complexity outweighs read cost |

**Installation:**
```bash
# No new dependencies required
# Existing: PostgreSQL with JSONB support, PHP 8.0+ with PDO
```

## Architecture Patterns

### Recommended Project Structure
```
src/Events/Services/
├── TaskManager.php          # Factory methods: buildTasksFromEvents(), buildAgentOrderTask()
├── ClassTaskService.php     # Orchestrator: fetches classes, calls TaskManager
└── (DELETE) TaskTemplateRegistry.php  # Removed in Phase 17

src/Events/Repositories/
└── ClassTaskRepository.php  # Query classes directly (no JOIN to logs)

src/Events/Models/
├── Task.php                 # Keep as-is (immutable value object)
└── TaskCollection.php       # Keep as-is (collection with open/completed filters)
```

### Pattern 1: Factory Method for Task Building
**What:** TaskManager acts as factory, constructing TaskCollection from class data
**When to use:** Every read operation—no caching, always build fresh
**Example:**
```php
// Source: Current codebase pattern adapted
final class TaskManager
{
    public function buildTasksFromEvents(array $class): TaskCollection
    {
        $tasks = new TaskCollection();

        // 1. Agent Order Number always present
        $tasks->add($this->buildAgentOrderTask($class));

        // 2. One task per event in event_dates JSONB
        $eventDates = json_decode($class['event_dates'] ?? '[]', true);
        foreach ($eventDates as $index => $event) {
            $tasks->add($this->buildEventTask($index, $event));
        }

        return $tasks;
    }

    private function buildAgentOrderTask(array $class): Task
    {
        $orderNr = $class['order_nr'] ?? null;
        $isCompleted = !empty($orderNr);

        return new Task(
            id: 'agent-order',
            label: 'Agent order Number',
            status: $isCompleted ? Task::STATUS_COMPLETED : Task::STATUS_OPEN,
            completedBy: null,  // Not tracked for now
            completedAt: null,  // Not tracked for now
            note: $orderNr
        );
    }

    private function buildEventTask(int $index, array $event): Task
    {
        $label = !empty($event['description'])
            ? "{$event['type']}: {$event['description']}"
            : $event['type'];

        return new Task(
            id: "event-{$index}",
            label: $label,
            status: Task::STATUS_OPEN,  // Derive from event status later
            completedBy: null,
            completedAt: null,
            note: $event['notes'] ?? null
        );
    }
}
```

### Pattern 2: Direct Class Querying (No JOINs)
**What:** ClassTaskRepository fetches classes table directly, no JOIN to change logs
**When to use:** Fetching data for task dashboard
**Example:**
```php
// Source: Simplified from existing ClassTaskRepository::fetchClasses()
public function fetchClasses(int $limit, string $sortDirection, ?int $classIdFilter): array
{
    $orderDirection = strtolower($sortDirection) === 'asc' ? 'ASC' : 'DESC';

    $whereClause = $classIdFilter !== null ? 'WHERE c.class_id = :class_id' : '';

    $sql = <<<SQL
SELECT
    c.class_id,
    c.client_id,
    c.class_type,
    c.class_code,
    c.original_start_date,
    c.order_nr,
    c.event_dates,  -- NEW: Fetch JSONB array
    c.updated_at,
    cl.client_name
FROM classes c
LEFT JOIN clients cl ON cl.client_id = c.client_id
{$whereClause}
ORDER BY c.original_start_date {$orderDirection} NULLS LAST, c.class_id {$orderDirection}
LIMIT :limit;
SQL;

    // Execute and return (no log_id, no operation field)
}
```

### Pattern 3: Service Layer Orchestration
**What:** ClassTaskService coordinates repository + TaskManager
**When to use:** Controller needs task data
**Example:**
```php
// Source: Refactored ClassTaskService pattern
public function getClassTasks(int $limit, string $sortDirection, bool $prioritiseOpen, ?int $classIdFilter): array
{
    $classes = $this->repository->fetchClasses($limit, $sortDirection, $classIdFilter);

    $items = [];
    foreach ($classes as $class) {
        $tasks = $this->taskManager->buildTasksFromEvents($class);

        $items[] = [
            'row' => $class,
            'tasks' => $tasks,
            'class_id' => $class['class_id'],
            'manageable' => true,  // All classes manageable (even with no events)
            'open_count' => count($tasks->open()),
        ];
    }

    if ($prioritiseOpen) {
        [$open, $completed] = $this->partitionByOpenCount($items);
        $items = [...$open, ...$completed];
    }

    return $items;
}
```

### Anti-Patterns to Avoid
- **Storing TaskCollection state:** Tasks are derived data, not stored. Storing creates sync issues.
- **Caching task collections:** Event_dates can change (user edits form), cached tasks stale immediately.
- **Building tasks in repository:** Repository returns raw data, TaskManager handles domain logic.
- **Using log_id anywhere:** Change logs being removed—no references to log_id should remain.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSONB array updates by index | String concatenation, JSON encode/decode in PHP | PostgreSQL jsonb_set() function | Atomic updates, handles concurrent writes, avoids TOAST overhead |
| Task status derivation | Complex if/else chains | Match expressions (PHP 8.0+) | Exhaustive checking, cleaner code |
| Task collection filtering | Manual array_filter loops | TaskCollection::open() / completed() methods | Already implemented, tested, consistent API |
| Immutable Task updates | Setter methods | Task::markCompleted() / reopen() clone pattern | Existing immutable value object pattern |

**Key insight:** PostgreSQL JSONB functions handle edge cases (document bloat, concurrent updates, TOAST compression) that seem trivial but cause production issues. Always prefer database-level operations for JSONB manipulation.

## Common Pitfalls

### Pitfall 1: Array Index vs Event Index Mismatch
**What goes wrong:** Event_dates array has gaps (deleted events), indices don't match expected task IDs
**Why it happens:** Frontend deletes event at index 1, indices shift, task IDs `event-1`, `event-2` now point to wrong events
**How to avoid:** Accept that event indices are volatile. Task IDs are `event-{current-index}`, not persistent. This is acceptable because tasks rebuilt on every read. For updates, identify event by array index at time of read, not by stable ID.
**Warning signs:** Task completion updates wrong event because index shifted between page load and AJAX call

### Pitfall 2: Agent Order Number Not Always Present
**What goes wrong:** Assumption that `classes.order_nr IS NOT NULL` means completed fails for empty strings
**Why it happens:** Database allows empty string, PHP `!empty()` check treats empty string as false
**How to avoid:** Use explicit `!== null && $orderNr !== ''` checks. Empty string is incomplete, null is incomplete, only non-empty string is complete.
**Warning signs:** Agent Order Number task shows "open" despite order_nr column having empty string value

### Pitfall 3: JSONB Query Performance (No Statistics)
**What goes wrong:** Queries filtering on event_dates fields are 2000x slower than expected
**Why it happens:** PostgreSQL doesn't keep statistics on JSONB column contents, uses hardcoded 0.1% estimate for all conditions
**How to avoid:** Don't filter event_dates in WHERE clauses. Fetch all classes, filter in PHP if needed. For critical queries, promote frequently-filtered fields to real columns.
**Warning signs:** Dashboard load time increases dramatically as class count grows

### Pitfall 4: TOAST Overhead for Large event_dates Arrays
**What goes wrong:** Classes with 50+ events suffer slow reads (I/O overhead, decompression)
**Why it happens:** PostgreSQL TOASTs JSONB documents >2KB, storing out-of-line with compression
**How to avoid:** This phase doesn't solve it, but document as known limitation. Future optimization: paginate event display, or split events into separate table.
**Warning signs:** Classes with many events load slower than classes with few events

### Pitfall 5: Forgetting to Remove TaskTemplateRegistry Dependencies
**What goes wrong:** TaskManager still references TaskTemplateRegistry after it's deleted
**Why it happens:** Multiple services instantiate TaskTemplateRegistry, easy to miss one
**How to avoid:** Grep entire codebase for `TaskTemplateRegistry` before deleting. Update TaskManager constructor to not accept registry parameter.
**Warning signs:** PHP fatal error "Class not found: TaskTemplateRegistry" after Phase 17 cleanup

### Pitfall 6: Breaking Existing Tests
**What goes wrong:** TaskManagementTest.php expects change logs, fails after refactor
**Why it happens:** Tests verify trigger existence, query class_change_logs table (dropped in Phase 13)
**How to avoid:** Update tests in same commit as code changes. New tests should verify: (1) buildTasksFromEvents() returns TaskCollection, (2) Agent Order Number task always present, (3) Event tasks match event_dates array length.
**Warning signs:** CI/CD pipeline fails with "Table class_change_logs does not exist"

## Code Examples

Verified patterns from official sources:

### PostgreSQL jsonb_set() for Array Element Update
```sql
-- Source: https://neon.com/postgresql/postgresql-json-functions/postgresql-jsonb_set
-- Update event at index 2 to mark completed
UPDATE classes
SET event_dates = jsonb_set(
    event_dates,
    '{2}',  -- Array index (zero-based)
    jsonb_build_object(
        'type', event_dates->2->>'type',
        'description', event_dates->2->>'description',
        'date', event_dates->2->>'date',
        'status', 'Completed',
        'notes', event_dates->2->>'notes',
        'completed_by', 123,           -- User ID
        'completed_at', NOW()::text    -- ISO timestamp
    )
)
WHERE class_id = :class_id;
```

### PHP Match Expression for Status Derivation
```php
// Source: PHP 8.0+ documentation (existing codebase pattern)
private function deriveTaskStatus(array $event): string
{
    return match ($event['status'] ?? 'Pending') {
        'Completed' => Task::STATUS_COMPLETED,
        'Pending', 'Cancelled' => Task::STATUS_OPEN,
        default => Task::STATUS_OPEN,
    };
}
```

### Immutable Task Update Pattern
```php
// Source: Existing Task.php model (keep as-is)
public function markCompleted(int $userId, string $timestamp, ?string $note = null): self
{
    $clone = clone $this;
    $clone->status = self::STATUS_COMPLETED;
    $clone->completedBy = $userId;
    $clone->completedAt = $timestamp;
    $clone->note = $note;
    return $clone;
}

// Usage:
$task = $tasks->get('event-3');
$updated = $task->markCompleted(get_current_user_id(), date('c'), 'Done');
$tasks->replace($updated);  // TaskCollection stores updated version
```

### Task Label Formatting
```php
// Source: TASK-04 requirement
private function formatTaskLabel(array $event): string
{
    $type = $event['type'] ?? '';
    $description = trim($event['description'] ?? '');

    return $description !== ''
        ? "{$type}: {$description}"
        : $type;
}

// Examples:
// {type: "Training", description: "Week 1 complete"} -> "Training: Week 1 complete"
// {type: "Training", description: ""} -> "Training"
// {type: "Material Delivery", description: null} -> "Material Delivery"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Trigger-based task generation | Manual event capture + on-the-fly task building | v1.2 (Phase 13-14) | Simpler architecture, user controls events, no hidden automation |
| Template-based tasks | Event-driven tasks + special Agent Order Number | v1.2 (Phase 14) | Tasks reflect actual class lifecycle, not operation type |
| Store tasks in class_change_logs.tasks | Build TaskCollection from classes.event_dates | v1.2 (Phase 14) | No stale data, tasks always current, no sync issues |
| JOIN classes to change logs | Query classes directly | v1.2 (Phase 14) | Faster queries, simpler SQL, all classes visible (not just those with logs) |

**Deprecated/outdated:**
- TaskTemplateRegistry: Replaced by event-driven task generation (delete in Phase 17)
- class_change_logs table: Replaced by classes.event_dates JSONB (dropped in Phase 13)
- log_class_change() trigger: Replaced by manual event capture (dropped in Phase 13)
- TaskManager::getTasksWithTemplate(): Replaced by buildTasksFromEvents() (refactor in Phase 14)

## Open Questions

Things that couldn't be fully resolved:

1. **Event index stability during concurrent updates**
   - What we know: JSONB updates are atomic at document level
   - What's unclear: Two users load same class, both mark event-2 complete, one update overwrites other
   - Recommendation: Accept race condition for v1.2, document as known limitation. Future: add optimistic locking (compare updated_at before write)

2. **Task completion metadata (completed_by, completed_at)**
   - What we know: Not currently stored in event_dates schema, FormDataProcessor doesn't handle them
   - What's unclear: Should Agent Order Number track completion metadata or just order_nr value?
   - Recommendation: Phase 14 focuses on building tasks from events. Phase 15 (Data Sync) adds completion metadata to event schema. For now, Agent Order Number task shows note=order_nr, completed_by/at remain null.

3. **Deriving event status from completion metadata**
   - What we know: Events have 'status' field ('Pending', 'Completed', 'Cancelled')
   - What's unclear: Should task status derive from event.status or from presence of completed_at?
   - Recommendation: Phase 14 uses event.status directly. Phase 15 adds bidirectional sync (dashboard marks complete → event.status='Completed' + completed_at set)

4. **Performance at scale (1000+ classes with 50+ events each)**
   - What we know: Building tasks on-the-fly avoids stale data but costs CPU per request
   - What's unclear: Is overhead acceptable? Does caching become necessary?
   - Recommendation: Start with no caching (simple). Add performance monitoring. If dashboard loads exceed 500ms, consider memoization within single request (not across requests)

## Sources

### Primary (HIGH confidence)
- PostgreSQL 9.5 JSON Functions: https://www.postgresql.org/docs/9.5/functions-json.html
- Neon jsonb_set() reference: https://neon.com/postgresql/postgresql-json-functions/postgresql-jsonb_set
- Current codebase (TaskManager.php, Task.php, TaskCollection.php, ClassTaskRepository.php)

### Secondary (MEDIUM confidence)
- PostgreSQL JSONB best practices (2026): https://scalegrid.io/blog/using-jsonb-in-postgresql-how-to-effectively-store-index-json-data-in-postgresql/
- JSONB pitfalls and indexing: https://vsevolod.net/postgresql-jsonb-index/
- PHP Service Layer patterns: https://dev.to/otutukingsley/using-the-service-layer-pattern-in-php-for-clean-and-scalable-code-15fb
- PHP Builder pattern: https://refactoring.guru/design-patterns/builder/php/example

### Tertiary (LOW confidence)
- Legacy refactoring best practices: https://modlogix.com/blog/legacy-code-refactoring-tips-steps-and-best-practices/
- PostgreSQL JSONB statistics issues: https://www.heap.io/blog/when-to-avoid-jsonb-in-a-postgresql-schema

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - PostgreSQL JSONB well-documented, PHP 8.0+ match expressions verified, existing codebase patterns confirmed
- Architecture: HIGH - Factory pattern for TaskManager verified in current code, service orchestration pattern established
- Pitfalls: MEDIUM - JSONB performance issues verified in multiple sources, but production impact unknown until load tested
- Code examples: HIGH - jsonb_set() syntax verified in official docs, Task immutability pattern exists in current code

**Research date:** 2026-02-03
**Valid until:** 2026-03-03 (30 days - stable domain, PostgreSQL JSONB API stable since 9.5)
