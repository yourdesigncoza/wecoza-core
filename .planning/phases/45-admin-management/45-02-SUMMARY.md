---
phase: 45-admin-management
plan: 02
subsystem: ui
tags: [shortcode, php, bootstrap, modal, pagination, learner-progression, admin]

# Dependency graph
requires:
  - phase: 45-admin-management-01
    provides: AJAX handlers for admin progression operations (fetch, hold/resume, bulk complete, start LP, hours log)
provides:
  - "[wecoza_progression_admin] shortcode registered and rendering full admin UI shell"
  - "Filter form with client/class/product/status dropdowns (JS-populated)"
  - "Data table with select-all checkbox, 8 columns, JS-populated tbody"
  - "Three modals: Start New LP, Hours Log audit trail, Bulk Complete confirmation"
  - "Bulk action bar with selected-count and Bulk Complete button"
affects:
  - 45-admin-management-03

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shortcode uses ob_start()/wecoza_view()/ob_get_clean() output buffering pattern"
    - "wp_localize_script with progressionAdminAjax variable (ajaxurl + nonce)"
    - "View shell with display:none content div — JS reveals after data load"
    - "Modals included inline in view template, JS opens them via Bootstrap API"

key-files:
  created:
    - src/Learners/Shortcodes/progression-admin-shortcode.php
    - views/learners/progression-admin.php
  modified:
    - wecoza-core.php

key-decisions:
  - "wecoza_view() used instead of direct include — matches plugin's view rendering pattern"
  - "All filter dropdowns rendered empty — JS populates via AJAX for separation of concerns"
  - "Three modals included in view shell, not dynamically injected — simpler JS wiring in Plan 03"

patterns-established:
  - "Progression admin view: loading spinner -> content reveal pattern matches learners-display-shortcode.php"

requirements-completed: [ADMIN-01, ADMIN-04]

# Metrics
duration: 2min
completed: 2026-02-18
---

# Phase 45 Plan 02: Progression Admin Shortcode and View Summary

**[wecoza_progression_admin] shortcode with filterable table shell, three Bootstrap modals, and bulk action bar — ready for Plan 03 JS wiring**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-18T20:14:38Z
- **Completed:** 2026-02-18T20:16:00Z
- **Tasks:** 2/2
- **Files modified:** 3

## Accomplishments
- Created progression-admin-shortcode.php registering [wecoza_progression_admin] with proper script enqueue and localization
- Built progression-admin.php view template with filter form (4 dropdowns), data table (8 columns + checkboxes), pagination, and three modals
- Registered shortcode file in wecoza-core.php after learners-update-shortcode.php

## Task Commits

Each task was committed atomically:

1. **Task 1: Create shortcode file and view template** - `b257cfa` (feat)
2. **Task 2: Register shortcode in wecoza-core.php** - `129eebe` (chore)

**Plan metadata:** (docs commit to follow)

## Files Created/Modified
- `src/Learners/Shortcodes/progression-admin-shortcode.php` - Shortcode registration, script enqueue, progressionAdminAjax localization
- `views/learners/progression-admin.php` - Full admin UI shell: filter form, bulk bar, data table, Start New LP modal, Hours Log modal, Bulk Complete modal
- `wecoza-core.php` - Added require_once for progression-admin-shortcode.php

## Decisions Made
- Used `wecoza_view()` helper instead of direct PHP include — consistent with plugin's view rendering pattern
- Filter dropdowns rendered with only placeholder options — JS will populate from AJAX for clean separation of concerns
- All three modals included in the static view shell rather than dynamically injected by JS — simpler and more predictable for Plan 03

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- HTML shell complete with all required element IDs (progression-admin-container, filter-*, progression-admin-tbody, select-all-progressions, bulk-action-bar, etc.)
- Plan 03 (JS) can immediately wire up: filter form submit, table population, checkbox selection, modal opens, AJAX calls to Plan 01 handlers
- progression-admin.js file expected at assets/js/learners/progression-admin.js (Plan 03 creates this)

---
*Phase: 45-admin-management*
*Completed: 2026-02-18*
