---
phase: 52-class-activation-logic
plan: 03
subsystem: ui
tags: [php, ajax, attendance, class-status, access-control, wordpress]

# Dependency graph
requires:
  - phase: 52-01
    provides: wecoza_resolve_class_status() helper in functions.php, class_status column in DB

provides:
  - Attendance view activation gate — lock alert for draft/stopped classes, full UI for active
  - JS config classStatus, isAttendanceLocked, orderNr in WeCozaSingleClass localized data
  - Server-side AJAX guard require_active_class() rejecting capture/exception for non-active classes

affects:
  - 52-05 (manager JS — consumes isAttendanceLocked and classStatus from WeCozaSingleClass)
  - attendance-capture.js (reads isAttendanceLocked from WeCozaSingleClass config)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - require_active_class() procedural guard in AJAX namespace — DRY active-class enforcement
    - Compute status once then reuse ($classStatus) — avoids double-call to wecoza_resolve_class_status()
    - Early-return lock gate in PHP view — prevents attendance UI rendering on non-active classes

key-files:
  created: []
  modified:
    - views/classes/components/single-class/attendance.php
    - src/Classes/Controllers/ClassController.php
    - src/Classes/Ajax/AttendanceAjaxHandlers.php

key-decisions:
  - "require_active_class() calls wp_send_json_error + exit directly — consistent with verify_attendance_nonce() pattern in same file"
  - "Guard added to capture and exception handlers only — view/delete endpoints remain accessible on any status for audit integrity"
  - "absint() used instead of intval() for classId extraction in guarded handlers — consistent WP sanitization"

patterns-established:
  - "require_active_class(int $classId): void — procedural guard that queries DB and sends 403/404 before handler logic"
  - "Compute $classStatus once before wp_localize_script array — prevents double helper call"

requirements-completed: [WEC-179]

# Metrics
duration: 2min
completed: 2026-02-24
---

# Phase 52 Plan 03: Attendance Lock Gate Summary

**PHP lock gate + JS config + server-side AJAX guard prevent attendance capture on draft/stopped classes, with clear user messaging and 403 rejection for direct API bypass attempts**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-24T09:20:45Z
- **Completed:** 2026-02-24T09:22:45Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Attendance view shows lock alert (alert-subtle-warning) with status-specific message for non-active classes; early return hides entire attendance UI
- WeCozaSingleClass JS config now includes classStatus, isAttendanceLocked, and orderNr for Plan 05 manager JS consumption
- require_active_class() helper guards capture and exception AJAX endpoints — direct API calls on non-active classes return 403

## Task Commits

Each task was committed atomically:

1. **Task 1: Attendance view lock gate + JS localization** - `2de62d4` (feat)
2. **Task 2: Server-side AJAX guard for attendance on non-active classes** - `de7df2d` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `views/classes/components/single-class/attendance.php` - Added activation gate after empty($class) check; wecoza_resolve_class_status() determines lock; returns early with lock alert for draft/stopped
- `src/Classes/Controllers/ClassController.php` - Added $classStatus computation and classStatus/isAttendanceLocked/orderNr to WeCozaSingleClass wp_localize_script array
- `src/Classes/Ajax/AttendanceAjaxHandlers.php` - Added require_active_class() helper function; called at top of handle_attendance_capture() and handle_attendance_mark_exception()

## Decisions Made
- `require_active_class()` uses the same exit pattern as `verify_attendance_nonce()` — calls wp_send_json_error then `exit` directly rather than throwing. Consistent with existing file conventions.
- Guard applied only to capture and exception handlers. View-only (`get_sessions`, `get_detail`) and admin delete remain unguarded so captured history remains accessible regardless of class status.
- Used `absint()` instead of `intval()` for classId extraction in guarded handlers — minor consistency improvement with WP conventions (noted as minor deviation).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Minor Consistency] Switched intval() to absint() for classId in guarded handlers**
- **Found during:** Task 2 (reviewing handle_attendance_mark_exception)
- **Issue:** Plan specified absint() in the guard call context; existing code used intval()
- **Fix:** Changed `intval($_POST['class_id'])` to `absint($_POST['class_id'])` in both capture and exception handlers
- **Files modified:** src/Classes/Ajax/AttendanceAjaxHandlers.php
- **Verification:** Both handlers now use absint() consistently
- **Committed in:** de7df2d (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - minor consistency improvement)
**Impact on plan:** Trivial — absint() is semantically correct for positive integer IDs. No scope creep.

## Issues Encountered
None — plan executed with clear specifications. wecoza_resolve_class_status() was already in place from Plan 01.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plan 04 (event tasks badge) and Plan 05 (manager JS) can proceed
- classStatus and isAttendanceLocked are now in WeCozaSingleClass — Plan 05 manager JS can read them immediately
- orderNr is also in localized data — Plan 05 can use it without additional PHP changes

## Self-Check: PASSED

- attendance.php: FOUND
- ClassController.php: FOUND
- AttendanceAjaxHandlers.php: FOUND
- 52-03-SUMMARY.md: FOUND
- Commit 2de62d4 (Task 1): FOUND
- Commit de7df2d (Task 2): FOUND

---
*Phase: 52-class-activation-logic*
*Completed: 2026-02-24*
