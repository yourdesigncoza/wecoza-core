---
phase: 18-notification-system
plan: 01
subsystem: database
tags: [postgresql, jsonb, events, notifications, repository-pattern]

# Dependency graph
requires:
  - phase: 14-task-system-refactor
    provides: Event-based class management patterns
provides:
  - class_events table schema for event storage
  - ClassEventRepository for CRUD operations
  - ClassEventDTO for immutable event data transfer
  - EventType enum for type-safe event classification
affects: [18-02, 18-03, 18-04, 18-05] # All subsequent notification plans

# Tech tracking
tech-stack:
  added: []  # Uses existing PDO/PostgreSQL
  patterns:
    - Immutable DTO pattern with readonly properties
    - Backed enum for event type classification
    - Repository pattern for event storage

key-files:
  created:
    - schema/class_events.sql
    - src/Events/Enums/EventType.php
    - src/Events/DTOs/ClassEventDTO.php
    - src/Events/Repositories/ClassEventRepository.php
  modified: []

key-decisions:
  - "JSONB for event_data and ai_summary - flexible schema for varied event payloads"
  - "Notification workflow states: pending -> enriching -> sending -> sent (or failed)"
  - "Partial indexes for status queue and unread events - optimized for common queries"
  - "PHP 8.1 readonly properties instead of PHP 8.2 readonly class - version compatibility"

patterns-established:
  - "EventType::forEntity() pattern - maps entity+operation to event type"
  - "ClassEventDTO::create() factory - for new events without event_id"
  - "Immutable with* methods - for status transitions without mutation"
  - "Cursor-based pagination - getTimeline(afterId) for infinite scroll"

# Metrics
duration: 12min
completed: 2026-02-05
---

# Phase 18 Plan 01: Event Storage Infrastructure Summary

**PostgreSQL class_events table with repository, DTO, and enum for notification event storage pipeline**

## Performance

- **Duration:** 12 min
- **Started:** 2026-02-05T09:24:00Z
- **Completed:** 2026-02-05T09:36:00Z
- **Tasks:** 3
- **Files created:** 4

## Accomplishments

- Created class_events table schema with JSONB columns for flexible event data
- Implemented 7-case EventType enum with forEntity() mapping and priority levels
- Built ClassEventDTO with readonly properties and immutable update methods
- Developed ClassEventRepository with full CRUD and workflow status transitions

## Task Commits

Each task was committed atomically:

1. **Task 1: Create class_events table schema** - `cbf0314` (feat)
2. **Task 2: Create EventType enum and ClassEventDTO** - `0c4e23f` (feat)
3. **Task 3: Create ClassEventRepository** - `b0d4bdc` (feat)

## Files Created

- `schema/class_events.sql` - PostgreSQL DDL with columns, constraints, indexes, comments
- `src/Events/Enums/EventType.php` - Backed enum: CLASS_INSERT/UPDATE/DELETE, LEARNER_ADD/REMOVE/UPDATE, STATUS_CHANGE
- `src/Events/DTOs/ClassEventDTO.php` - Readonly DTO with fromRow(), create(), toArray(), with* methods
- `src/Events/Repositories/ClassEventRepository.php` - Repository with insertEvent(), findPendingForProcessing(), updateStatus(), markSent(), etc.

## Decisions Made

1. **JSONB for event_data column** - Allows storing varied payloads (new_row, old_row, diff, metadata) without schema changes
2. **Separate notification_status workflow** - 5 states (pending, enriching, sending, sent, failed) for clear processing pipeline
3. **Partial indexes** - idx_class_events_status WHERE status IN (...) and idx_class_events_unread WHERE viewed_at IS NULL for optimized queries
4. **PHP 8.1 readonly properties** - Used `public readonly` instead of `final readonly class` for PHP 8.1 compatibility

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] PHP 8.1 readonly class syntax**
- **Found during:** Task 2 (ClassEventDTO)
- **Issue:** `final readonly class` syntax requires PHP 8.2, server runs PHP 8.1.2
- **Fix:** Changed to `final class` with `public readonly` on each property
- **Files modified:** src/Events/DTOs/ClassEventDTO.php
- **Verification:** `php -l` syntax check passes
- **Committed in:** 0c4e23f (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Minor syntax adjustment for PHP version compatibility. No scope creep.

## Issues Encountered

None - plan executed smoothly after PHP version adjustment.

## User Setup Required

**Database schema must be applied manually:**

```sql
-- Execute schema/class_events.sql against PostgreSQL database
psql -U John -d wecoza -f schema/class_events.sql
```

Alternatively, the schema can be applied via WordPress admin or migration system when available.

## Next Phase Readiness

- Event storage infrastructure complete
- Ready for 18-02: Event Emitter service to populate class_events table
- ClassEventRepository provides all query methods needed by notification processors

---
*Phase: 18-notification-system*
*Completed: 2026-02-05*
