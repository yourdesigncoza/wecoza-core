---
phase: 48-foundation
plan: 01
subsystem: learners
tags: [progression, hours_trained, progress_percentage, postgresql, javascript]

# Dependency graph
requires:
  - phase: 44-46-learner-progression
    provides: LP tracking tables, ProgressionService, LearnerProgressionModel, progression views and JS

provides:
  - Consistent hours_trained-based progress calculation across PHP model, service, SQL, views, JS
  - getProgressPercentage() using hoursTrained / subjectDuration
  - isHoursComplete() using hoursTrained >= subjectDuration
  - getLearnerOverallProgress() summing getHoursTrained() for in-progress LPs
  - SQL CTEs in ClassRepository and LearnerRepository using lpt.hours_trained
  - View templates displaying hours_trained as progress numerator
  - JS progression-admin.js progress bar and detail label using hours_trained

affects: [progression-service, learner-views, class-modal-learners, progression-admin-js]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Progress percentage = hours_trained / subject_duration (not hours_present)"
    - "hours_present is retained for display breakdown but never used in progress calculations"

key-files:
  created: []
  modified:
    - src/Learners/Models/LearnerProgressionModel.php
    - src/Learners/Services/ProgressionService.php
    - src/Learners/Repositories/LearnerProgressionRepository.php
    - src/Learners/Repositories/LearnerRepository.php
    - src/Classes/Repositories/ClassRepository.php
    - views/learners/components/learner-progressions.php
    - views/classes/components/single-class/modal-learners.php
    - assets/js/learners/progression-admin.js

key-decisions:
  - "Progress tracks training delivery (hours_trained) not attendance (hours_present) — per Mario's clarification"
  - "hours_present field is retained in SELECTs and getCurrentLPDetails for breakdown display, but never used as progress numerator"
  - "SQL alias renamed from active_hours_present to active_hours_trained in ClassRepository and LearnerRepository CTEs"

patterns-established:
  - "Progress numerator = hours_trained everywhere: PHP model, service, SQL CTEs, views, JS"

requirements-completed: [PROG-01, PROG-02, PROG-03]

# Metrics
duration: 3min
completed: 2026-02-23
---

# Phase 48 Plan 01: Foundation Summary

**Fixed all progress percentage calculations to use hours_trained (training delivery) instead of hours_present (attendance) across PHP model methods, SQL CTEs, view templates, and JavaScript**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-23T12:07:34Z
- **Completed:** 2026-02-23T12:10:22Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- `getProgressPercentage()` and `isHoursComplete()` in LearnerProgressionModel now use `hoursTrained` (was `hoursPresent`)
- `getLearnerOverallProgress()` in ProgressionService now sums `getHoursTrained()` for in-progress LPs
- All SQL CASE expressions calculating progress_pct in ClassRepository, LearnerRepository, and LearnerProgressionRepository now use `lpt.hours_trained / cts.subject_duration`
- View templates (learner-progressions.php, modal-learners.php) display `hours_trained` as the progress numerator in the "X / Y hrs" label and tooltip
- JS admin panel (`progression-admin.js`) progress bar column and detail label both use `hours_trained`

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix PHP model and service progress calculations** - `777bac4` (fix)
2. **Task 2: Fix SQL-level progress calculations and view templates** - `658165c` (fix)

**Plan metadata:** (docs commit below)

## Files Created/Modified
- `src/Learners/Models/LearnerProgressionModel.php` - getProgressPercentage() and isHoursComplete() use hoursTrained
- `src/Learners/Services/ProgressionService.php` - getLearnerOverallProgress() sums getHoursTrained()
- `src/Learners/Repositories/LearnerProgressionRepository.php` - avg_progress CASE uses hours_trained
- `src/Learners/Repositories/LearnerRepository.php` - active_lp CTE and getActiveLPForLearner use hours_trained; alias renamed
- `src/Classes/Repositories/ClassRepository.php` - active_lp CTE uses hours_trained; alias renamed
- `views/learners/components/learner-progressions.php` - progress label and LP history show hours_trained
- `views/classes/components/single-class/modal-learners.php` - LP hours display and progress tooltip use hours_trained
- `assets/js/learners/progression-admin.js` - progress bar column and detail label use hours_trained

## Decisions Made
- hours_present data is retained in getCurrentLPDetails() and related methods so the breakdown display (Trained / Present / Absent columns in views) continues to show all three values — only the progress calculation numerator changed.
- SQL alias `active_hours_present` renamed to `active_hours_trained` in both ClassRepository and LearnerRepository CTEs for naming consistency with the semantic change.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All progress percentage calculations now consistently use hours_trained across the full stack
- The attendance capture feature (Phase 48 remaining plans) can build on this corrected foundation
- No blockers

---
*Phase: 48-foundation*
*Completed: 2026-02-23*

## Self-Check: PASSED

- FOUND: src/Learners/Models/LearnerProgressionModel.php
- FOUND: src/Learners/Services/ProgressionService.php
- FOUND: src/Classes/Repositories/ClassRepository.php
- FOUND: .planning/phases/48-foundation/48-01-SUMMARY.md
- FOUND commit 777bac4: fix(48-01): use hours_trained for progress percentage and completion checks
- FOUND commit 658165c: fix(48-01): use hours_trained in all SQL, view, and JS progress calculations
