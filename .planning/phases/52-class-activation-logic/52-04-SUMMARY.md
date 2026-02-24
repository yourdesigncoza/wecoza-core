---
phase: 52-class-activation-logic
plan: 04
subsystem: ui
tags: [php, badge, phoenix, class-status, views]

# Dependency graph
requires:
  - phase: 52-01
    provides: wecoza_resolve_class_status() helper in functions.php and class_status column in DB

provides:
  - Three-way Draft/Active/Stopped badge on classes listing using wecoza_resolve_class_status()
  - 6th status summary card on single-class detail page
  - Active count in summary strip uses class_status === 'active' (not legacy schedule-stop check)

affects: [52-05, 52-06, any view referencing class status badges]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "wecoza_resolve_class_status() used in views for null-safe status resolution"
    - "match() expressions for badge class/label/icon — DRY, exhaustive, PHP 8+ idiomatic"
    - "Phoenix badge classes badge-phoenix-{success|warning|danger} for three-way status"

key-files:
  created: []
  modified:
    - views/classes/components/classes-display.view.php
    - views/classes/components/single-class/summary-cards.php
    - src/Classes/Controllers/ClassController.php

key-decisions:
  - "Active count uses wecoza_resolve_class_status() === 'active' rather than legacy isClassCurrentlyStopped() schedule-pause check — the two stop concepts are distinct"
  - "Status card in summary-cards.php uses same icon+label pattern as existing 5 cards — no new CSS"

patterns-established:
  - "Three-way class status badge: match() on wecoza_resolve_class_status() result → badge class/label/icon"
  - "Summary card with bg-{status}-subtle icon container and h5 label"

requirements-completed: [WEC-179]

# Metrics
duration: 5min
completed: 2026-02-24
---

# Phase 52 Plan 04: Three-Way Badge and Status Summary Card Summary

**Three-way Draft/Active/Stopped Phoenix badge on classes listing and 6th status summary card on single-class detail page, both driven by wecoza_resolve_class_status()**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-24T09:20:42Z
- **Completed:** 2026-02-24T09:25:04Z
- **Tasks:** 1
- **Files modified:** 3

## Accomplishments
- Replaced binary `order_nr`-based Draft/Active badge with three-way badge: Draft (warning/yellow), Active (success/green), Stopped (danger/red) using `wecoza_resolve_class_status()`
- Added 6th summary card to single-class detail page showing class status with colour-coded icon
- Updated `ClassController` active count to use `wecoza_resolve_class_status() === 'active'` instead of legacy schedule-based `isClassCurrentlyStopped()`

## Task Commits

1. **Task 1: Three-way badge in listing + status summary card** - `de7df2d` (feat — committed as part of 52-03 batch)

Note: All 52-04 changes were already committed in `de7df2d` as the prior 52-03 executor included them in a single commit. No additional commit was needed.

## Files Created/Modified
- `views/classes/components/classes-display.view.php` - Three-way badge replacing binary order_nr check (lines 270-293)
- `views/classes/components/single-class/summary-cards.php` - 6th status summary card added after Total Hours card
- `src/Classes/Controllers/ClassController.php` - Active count calculation uses wecoza_resolve_class_status()

## Decisions Made
- Active count uses `wecoza_resolve_class_status() === 'active'` not the legacy `isClassCurrentlyStopped()` — the legacy method checks schedule pause windows (stop_restart_dates), a different concept from class deactivation via class_status
- Status card in summary-cards.php matches the existing 5-card layout pattern (d-flex align-items-center, icon container with bg-{color}-subtle, label+value) — no new CSS

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] ClassController active_count updated to use new status system**
- **Found during:** Task 1 (reviewing active_count logic)
- **Issue:** Plan noted "look for any `$activeCount` counter" — found in ClassController (not view). Was using `isClassCurrentlyStopped()` which checks schedule pauses, not class_status
- **Fix:** Changed `if (!$this->isClassCurrentlyStopped($class))` to `if (wecoza_resolve_class_status($class) === 'active')`
- **Files modified:** `src/Classes/Controllers/ClassController.php`
- **Verification:** grep confirms wecoza_resolve_class_status in controller
- **Committed in:** de7df2d (part of task commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug/incorrect active count computation)
**Impact on plan:** Necessary for correctness — active count must reflect class_status, not legacy schedule pauses.

## Issues Encountered
- All plan changes were already present in HEAD (committed during 52-03 execution as a batch). Plan 04 SUMMARY created to record completion; no new commits required.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Classes listing and single-class page both display correct three-way status badges
- Ready for Phase 52-05 (status management UI and AJAX handler)

---
*Phase: 52-class-activation-logic*
*Completed: 2026-02-24*
