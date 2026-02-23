---
phase: 50-ajax-endpoints
plan: 01
subsystem: api
tags: [ajax, attendance, wordpress, php, nonce]

# Dependency graph
requires:
  - phase: 49-backend-logic
    provides: AttendanceService with captureAttendance, markException, deleteAndReverseHours, generateSessionList methods
provides:
  - Five AJAX endpoints for attendance operations registered via wp_ajax_ actions
  - getSessionDetail() service wrapper on AttendanceService
  - attendanceNonce localized into WeCozaSingleClass for frontend use
  - src/Classes/Ajax/AttendanceAjaxHandlers.php with register_attendance_ajax_handlers()
affects: [51-js-frontend, attendance-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Shared verify_attendance_nonce() helper for DRY nonce validation across handlers
    - camelCase -> snake_case normalization in capture handler for JS/PHP key compatibility
    - Range validation of hours_present against scheduledHours before service call
    - manage_options capability check on admin-only delete endpoint

key-files:
  created:
    - src/Classes/Ajax/AttendanceAjaxHandlers.php
  modified:
    - src/Classes/Services/AttendanceService.php
    - wecoza-core.php
    - src/Classes/Controllers/ClassController.php

key-decisions:
  - "Shared verify_attendance_nonce() helper instead of inline check_ajax_referer in each handler — DRY pattern matching ProgressionAjaxHandlers approach"
  - "No wp_ajax_nopriv_ registrations — site requires login per CLAUDE.md policy"
  - "Range validation in AJAX handler (not service) — handler owns HTTP input validation, service owns business logic"

patterns-established:
  - "DRY nonce helper: verify_attendance_nonce() called by all 5 handlers — never duplicate check_ajax_referer"
  - "camelCase normalization in handler layer: isset(snake) ? snake : (isset(camel) ? camel : default)"
  - "Service wrapper pattern: getSessionDetail() in service exposes repository method through service layer"

requirements-completed: [UI-06, ATT-05]

# Metrics
duration: 2min
completed: 2026-02-23
---

# Phase 50 Plan 01: AJAX Endpoints Summary

**Five AJAX attendance endpoints wired to AttendanceService with nonce validation, camelCase normalization, and hours range validation**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-23T13:46:59Z
- **Completed:** 2026-02-23T13:48:42Z
- **Tasks:** 1
- **Files modified:** 4 (1 created, 3 modified)

## Accomplishments
- Created `AttendanceAjaxHandlers.php` with five AJAX handlers covering all attendance operations
- Added `getSessionDetail()` service wrapper to `AttendanceService` for repository encapsulation
- Wired all five `wp_ajax_wecoza_attendance_*` actions into wecoza-core.php
- Added `attendanceNonce` to `WeCozaSingleClass` localized JS config for frontend consumption

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AttendanceAjaxHandlers with five handlers, service wrapper, and wiring** - `9c96627` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `src/Classes/Ajax/AttendanceAjaxHandlers.php` - Five AJAX handlers + shared nonce helper + registration function
- `src/Classes/Services/AttendanceService.php` - Added getSessionDetail() service wrapper method
- `wecoza-core.php` - Added require_once for AttendanceAjaxHandlers.php
- `src/Classes/Controllers/ClassController.php` - Added attendanceNonce to WeCozaSingleClass localize array

## Decisions Made
- Used shared `verify_attendance_nonce()` helper instead of inline `check_ajax_referer` in each handler — follows DRY principle consistent with project conventions.
- Range validation sits in the AJAX handler layer, not the service — handler owns HTTP input normalization and validation; service owns business rules.
- No `wp_ajax_nopriv_` registrations per CLAUDE.md policy (site requires login).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All five AJAX endpoints are registered and callable: `wecoza_attendance_get_sessions`, `wecoza_attendance_capture`, `wecoza_attendance_mark_exception`, `wecoza_attendance_get_detail`, `wecoza_attendance_admin_delete`
- `WeCozaSingleClass.attendanceNonce` available in JS for nonce-authenticated requests
- Phase 51 (JS frontend) can immediately consume these endpoints

---
*Phase: 50-ajax-endpoints*
*Completed: 2026-02-23*
