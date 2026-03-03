---
phase: 14-task-system-refactor
plan: 01
subsystem: api
tags: [task-management, jsonb, event-dates, php]

# Dependency graph
requires:
  - phase: 13-database-cleanup
    provides: Clean schema with event_dates JSONB in classes table
provides:
  - buildTasksFromEvents() factory method for event-based task generation
  - Agent Order Number task always present in collections
  - Event tasks with IDs event-{index} and formatted labels
affects: [14-02, 15-bidirectional-sync, 16-presentation-layer]

# Tech tracking
tech-stack:
  added: []
  patterns: [event-based task derivation, JSONB decoding with fallbacks]

key-files:
  created: []
  modified:
    - src/Events/Services/TaskManager.php

key-decisions:
  - "Task IDs use 'agent-order' and 'event-{index}' format for predictable access"
  - "Empty string order_nr treated as incomplete (not just null)"
  - "Invalid JSON in event_dates gracefully handled with warning log"
  - "Missing event type defaults to 'Unknown Event' fallback"

patterns-established:
  - "buildTasksFromEvents() is public factory, helper methods are private"
  - "TaskCollection built incrementally via add() calls"
  - "Status derived via match expression from event status strings"

# Metrics
duration: 2min
completed: 2026-02-03
---

# Phase 14 Plan 01: Task Building from Events Summary

**TaskManager.buildTasksFromEvents() factory method derives TaskCollection from classes.event_dates JSONB with Agent Order Number always present**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-03T12:21:50Z
- **Completed:** 2026-02-03T12:23:44Z
- **Tasks:** 2 (Task 2 was verification of edge cases already in Task 1)
- **Files modified:** 1

## Accomplishments
- Added buildTasksFromEvents() public factory method to TaskManager
- Agent Order Number task always included in returned TaskCollection
- Event tasks created with IDs event-0, event-1, etc.
- Labels formatted as "{type}: {description}" or just "{type}"
- Status derived from event['status'] field and order_nr presence
- Edge cases handled: null/empty event_dates, invalid JSON, missing fields

## Task Commits

Each task was committed atomically:

1. **Task 1: Add buildTasksFromEvents factory method** - `9aee2a0` (feat)
2. **Task 2: Handle edge cases** - No separate commit (verification only, edge cases already implemented in Task 1)

## Files Created/Modified
- `src/Events/Services/TaskManager.php` - Added buildTasksFromEvents(), buildAgentOrderTask(), buildEventTask() methods

## Decisions Made
- **Task ID format:** Used 'agent-order' for order task and 'event-{index}' for events to enable predictable task access
- **Empty string handling:** Treated empty string order_nr as incomplete (same as null) for explicit completion semantics
- **JSON error handling:** Invalid event_dates JSON logs warning via wecoza_log() and treats as empty array (graceful degradation)
- **Type fallback:** Missing or empty event type defaults to 'Unknown Event' to ensure valid label

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation was straightforward with existing Task and TaskCollection models.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- buildTasksFromEvents() ready for use in ClassRepository (Plan 14-02)
- Existing trigger-based methods preserved for backward compatibility
- completedBy/completedAt fields return null (Phase 15 adds user tracking)

---
*Phase: 14-task-system-refactor*
*Completed: 2026-02-03*
