---
phase: quick
plan: 004
type: quick-task
subsystem: task-management
completed: 2026-02-05
duration: 92 seconds

tags:
  - bug-fix
  - data-loss
  - task-completion
  - metadata

requires:
  - Phase 15 (Bidirectional Sync) - Task completion workflow

provides:
  - Agent Order Number task displays real user/time
  - Event task notes persist through reopen cycle

affects:
  - Dashboard task display
  - Task completion UI

tech-stack:
  patterns:
    - JSONB metadata storage
    - DRY extraction (parseEventDates helper)

key-files:
  created:
    - schema/agent_order_metadata.sql
  modified:
    - src/Events/Services/TaskManager.php

decisions:
  - id: QUICK-004-01
    choice: Store completion metadata in separate JSONB column
    rationale: Allows separate lifecycle for order_nr value vs completion tracking
    alternatives: Store in same field, use separate columns per field
---

# Quick Task 004: Fix Task Metadata and Preserve Notes on Reopen

**One-liner:** Agent Order Number now shows real completion metadata; event task notes preserved on reopen.

## Problem Statement

Two user-reported bugs in task completion workflow:

1. **Agent Order Number task** showed "Unknown user / Unknown time" in Completed Tasks section despite being completed
2. **Event task notes** disappeared when reopening a completed task (data loss)

Both bugs caused by incomplete metadata handling in TaskManager.

## Changes Made

### Task 1: Agent Order Completion Metadata

**Created:**
- `schema/agent_order_metadata.sql` - DDL for new `order_nr_metadata JSONB` column

**Modified `TaskManager.php`:**
- `fetchClassById()` - Now selects `order_nr_metadata` column
- `updateClassOrderNumber()` - Stores `{completed_by, completed_at}` as JSONB when completing, sets NULL when reopening
- `completeAgentOrderTask()` - Passes `$userId` and `$timestamp` to `updateClassOrderNumber()`
- `buildAgentOrderTask()` - Reads metadata from JSONB, passes to Task constructor

**Flow:**
1. User completes agent-order task with order number "12345"
2. `completeAgentOrderTask()` calls `updateClassOrderNumber($classId, "12345", $userId, $timestamp)`
3. Method stores `{"completed_by": 1, "completed_at": "2026-02-05 11:30:00"}` in `order_nr_metadata`
4. Dashboard re-renders, `buildAgentOrderTask()` reads metadata
5. ClassTaskPresenter displays "John Doe / Feb 5, 2026 11:30" instead of "Unknown user / Unknown time"

### Task 2: Preserve Notes on Reopen

**Modified `TaskManager.php`:**
- Added `parseEventDates()` - DRY helper to decode event_dates JSONB (handles string/array)
- `reopenTask()` - Now fetches existing event before update, extracts notes, passes to `updateEventStatus()`
- `buildTasksFromEvents()` - Refactored to use `parseEventDates()` helper

**Flow:**
1. User completes event task with note "Notify Front Desk"
2. Note stored in `event_dates[{index}]['notes']`
3. User clicks "Reopen" on task
4. `reopenTask()` fetches class, parses events, extracts existing note
5. Calls `updateEventStatus(..., existingNotes)` which preserves the note
6. Task reopens with note still visible in Open Tasks section

## Technical Details

### JSONB Storage Pattern

```php
// Completion
$metadata = json_encode([
    'completed_by' => $userId,
    'completed_at' => $timestamp
], JSON_THROW_ON_ERROR);

// Reopening
$metadata = null; // Clear metadata
```

### Note Preservation Logic

```php
// Extract existing notes before update
$events = $this->parseEventDates($class['event_dates']);
$existingNotes = $events[$eventIndex]['notes'] ?? null;

// Pass through to update
$this->updateEventStatus($classId, $eventIndex, 'Pending', null, null, $existingNotes);
```

## Testing Notes

**Task 1 verification requires DDL execution:**
```sql
-- User must run manually:
ALTER TABLE classes ADD COLUMN IF NOT EXISTS order_nr_metadata JSONB;
```

After DDL:
1. Complete agent-order task with value "12345"
2. Check Completed Tasks section shows real user name and timestamp
3. Reopen task, verify metadata cleared

**Task 2 verification:**
1. Complete event task with note "Test note"
2. Verify note appears in Completed Tasks
3. Reopen task
4. Verify note still visible in Open Tasks

## Deviations from Plan

None - plan executed exactly as written.

## Commits

| Commit | Message | Files |
|--------|---------|-------|
| b1236f9 | feat(quick-004): store and display agent order completion metadata | schema/agent_order_metadata.sql, TaskManager.php |
| cab8521 | fix(quick-004): preserve notes when reopening event tasks | TaskManager.php |

## Decisions Made

**QUICK-004-01: Store completion metadata in separate JSONB column**

Instead of encoding completion data within order_nr field or using separate columns for completed_by/completed_at, we chose a dedicated JSONB column:

- **Pros:** Clean separation between value (order_nr) and metadata, flexible schema for future additions
- **Cons:** Requires DDL migration, one extra column
- **Rationale:** Allows order_nr to remain simple string while tracking rich completion context

## Impact Assessment

**Immediate:**
- Agent Order Number task completion now properly attributes work to user
- Event task notes no longer lost on reopen (prevents user data loss)

**Future:**
- Pattern established for other task-level metadata needs
- DRY parseEventDates() helper simplifies future event_dates operations

**No breaking changes** - Backward compatible:
- Missing `order_nr_metadata` falls back to null (displays "Unknown user")
- Existing event_dates without notes work as before

## Dependencies

**Requires:**
- Phase 15 completion (Task completion workflow established)
- DDL execution by user (agent_order_metadata column)

**No impact on:**
- Other task types
- Class form save logic
- Event dispatch system

## Next Phase Readiness

**Unblocks:** Nothing pending

**Notes for future work:**
- Consider similar metadata for other task types if needed
- Could extend parseEventDates() to validate event structure
