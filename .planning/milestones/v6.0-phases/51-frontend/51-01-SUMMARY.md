---
phase: 51-frontend
plan: 01
subsystem: ui
tags: [php, bootstrap5, attendance, modal, ajax, wordpress]

# Dependency graph
requires:
  - phase: 50-ajax-endpoints
    provides: AttendanceAjaxHandlers with five AJAX endpoints and wecoza_attendance_nonce

provides:
  - attendance.php HTML shell: summary cards (3), month filter tabs, session table, capture modal, view-detail modal, exception modal
  - attendance component wired into single-class-display.view.php between notes and schedule stats
  - attendance-capture.js registered with jquery + single-class-display-js dependencies
  - learnerIds decoded and passed in WeCozaSingleClass localize data for JS capture modal

affects: [52-frontend-js, attendance-capture-js, single-class-display]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Attendance component follows single-class component pattern: $class ?? [] guard, empty check, card wrapper"
    - "Modals placed outside card wrapper but inside component file (same file)"
    - "JS receives learnerIds via WeCozaSingleClass.learnerIds (decoded JSON array)"

key-files:
  created:
    - views/classes/components/single-class/attendance.php
  modified:
    - views/classes/components/single-class-display.view.php
    - src/Classes/Controllers/ClassController.php

key-decisions:
  - "attendance-capture.js depends on wecoza-single-class-display-js so WeCozaSingleClass config is available before attendance JS runs"
  - "learnerIds decoded from JSON string to PHP array before passing to wp_localize_script — JS receives plain array"
  - "Admin delete button rendered conditionally via current_user_can('manage_options') in PHP template"

patterns-established:
  - "Attendance section placed between Notes and Schedule Statistics — visibility priority ordering"
  - "Summary card icons use Phoenix fs-3 sizing with gap-3 layout"

requirements-completed: [UI-01, UI-02, UI-03, UI-04, UI-05]

# Metrics
duration: 2min
completed: 2026-02-23
---

# Phase 51 Plan 01: Attendance Frontend Shell Summary

**PHP attendance component with 3 summary cards, month tabs, session table, capture/detail/exception modals; wired into single-class-display with attendance-capture.js enqueue and learnerIds localization**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-23T14:12:15Z
- **Completed:** 2026-02-23T14:13:55Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Created attendance.php view component (235 lines) with all required HTML shells — summary cards, month tabs, session table, capture modal, view-detail modal with admin-only delete, exception modal with type dropdown
- Inserted attendance component between Notes and Schedule Stats sections in single-class-display.view.php
- Registered attendance-capture.js with correct dependency chain and enqueued it on single class pages; added learnerIds to WeCozaSingleClass JS config

## Task Commits

Each task was committed atomically:

1. **Task 1: Create attendance.php view component** - `ab5ceb8` (feat)
2. **Task 2: Wire component into view + enqueue JS** - `460f574` (feat)

## Files Created/Modified

- `views/classes/components/single-class/attendance.php` - Full attendance section HTML: 3 summary cards, month nav tabs, session table, capture modal (per-learner hours inputs + submit), view-detail modal (read-only + admin delete), exception modal (type dropdown + notes)
- `views/classes/components/single-class-display.view.php` - Added attendance component include after notes, before monthly stats
- `src/Classes/Controllers/ClassController.php` - Registered wecoza-attendance-capture-js, enqueued on single class pages, added learnerIds decoding + localization to WeCozaSingleClass

## Decisions Made

- `attendance-capture.js` depends on `wecoza-single-class-display-js` so `WeCozaSingleClass` config object is always available when attendance JS initializes
- `learnerIds` decoded from raw JSON string (`$class['learner_ids']`) to PHP array before `wp_localize_script` — ensures JS receives a plain array regardless of DB storage format
- Admin delete button (`btn-admin-delete-session`) rendered conditionally via `current_user_can('manage_options')` in the PHP template, consistent with existing admin-gate pattern

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- HTML shell is complete; Plan 02 can implement attendance-capture.js module that calls the AJAX endpoints from Phase 50 and populates all the DOM elements defined here
- IDs available for JS: `att-total-sessions`, `att-captured-count`, `att-pending-count`, `attendance-month-tabs`, `attendance-sessions-tbody`, `capture-learners-tbody`, `detail-learners-tbody`, `exception-type-select`, `exception-notes`, `btn-submit-capture`, `btn-submit-exception`, `btn-admin-delete-session`

---
*Phase: 51-frontend*
*Completed: 2026-02-23*
