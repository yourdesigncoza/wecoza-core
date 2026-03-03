---
phase: 46-learner-progression-report
plan: 01
subsystem: api
tags: [postgresql, ajax, php, repository, learner-progression, reporting]

# Dependency graph
requires:
  - phase: 45-admin-management
    provides: ProgressionAjaxHandlers pattern, LearnerProgressionRepository base
provides:
  - LearnerProgressionRepository::findForReport() - 5-table JOIN report query with employer
  - LearnerProgressionRepository::getReportSummaryStats() - aggregate stats with conditional aggregation
  - handle_get_progression_report AJAX handler - employer-grouped response with summary stats
affects: [46-02, 46-03]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - PDO::PARAM_INT/PARAM_STR explicit binding in parameterized queries (new pattern for dynamic filters)
    - PostgreSQL FILTER(...) conditional aggregation for multi-status counts in single query
    - Two-pass grouping (flat rows -> employer -> learner hierarchy) in AJAX handler

key-files:
  created: []
  modified:
    - src/Learners/Repositories/LearnerProgressionRepository.php
    - src/Learners/Ajax/ProgressionAjaxHandlers.php

key-decisions:
  - "46-01: findForReport uses explicit PDO::PARAM_INT/STR binding (not helper->query()) to support dynamic filter set"
  - "46-01: getReportSummaryStats uses PostgreSQL FILTER(WHERE ...) conditional aggregation — single-pass, avoids subqueries"
  - "46-01: employer JOIN is via learners.employer_id (not classes) — report is learner-centric, not class-centric"
  - "46-01: No LIMIT/OFFSET on findForReport — search/filter narrows dataset, report loads all matching rows"
  - "46-01: avg_progress excludes completed LPs (only non-completed), capped at 100 via LEAST()"

patterns-established:
  - "Report queries extend base JOIN pattern with additional LEFT JOIN employers emp ON l.employer_id = emp.employer_id"
  - "Dynamic filter building with parallel $params + $paramTypes arrays for explicit PDO type binding"

requirements-completed: [RPT-01, RPT-02, RPT-03, RPT-04, RPT-05]

# Metrics
duration: 2min
completed: 2026-02-19
---

# Phase 46 Plan 01: Learner Progression Report Summary

**5-table PostgreSQL JOIN report endpoint with employer-grouped response, aggregate stats, and search/filter support via new get_progression_report AJAX action**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-19T06:30:09Z
- **Completed:** 2026-02-19T06:31:44Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Added `findForReport()` to `LearnerProgressionRepository` — 5-table JOIN (lpt + products + learners + classes + employers) with search/employer_id/status filters and explicit PDO type binding
- Added `getReportSummaryStats()` to `LearnerProgressionRepository` — same JOIN/filter logic, returns total_learners, completed/in_progress/on_hold counts, avg_progress (capped at 100, non-completed only), and completion_rate via PostgreSQL conditional aggregation
- Added `handle_get_progression_report()` to `ProgressionAjaxHandlers.php` — validates nonce and manage_options, builds grouped employer->learner->progressions hierarchy, returns `{groups, summary}` response registered on `wp_ajax_get_progression_report`

## Task Commits

Each task was committed atomically:

1. **Task 1: Add report query methods to LearnerProgressionRepository** - `b361350` (feat)
2. **Task 2: Add progression report AJAX handler and register it** - `65314e1` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified
- `src/Learners/Repositories/LearnerProgressionRepository.php` - Added findForReport() and getReportSummaryStats() (196 lines inserted)
- `src/Learners/Ajax/ProgressionAjaxHandlers.php` - Added handle_get_progression_report() function and wp_ajax registration (84 lines inserted)

## Decisions Made
- `findForReport` uses explicit `PDO::PARAM_INT`/`PDO::PARAM_STR` binding rather than the `$this->db->query()` helper, since the filter set is fully dynamic and binding types must be set per-parameter
- `getReportSummaryStats` uses PostgreSQL `FILTER (WHERE ...)` conditional aggregation — single pass, efficient, avoids subqueries or multiple COUNT queries
- The employer JOIN is `LEFT JOIN employers emp ON l.employer_id = emp.employer_id` (through learners, not classes) — report is learner-centric
- No LIMIT/OFFSET: search/filter narrows the dataset, report loads all matching rows
- `avg_progress` excludes completed LPs (only meaningful for active progressions), capped at 100 via `LEAST()`

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Backend data layer for report page is complete
- Plan 46-02 can proceed to build the report view/shortcode
- `get_progression_report` AJAX action is registered and accepts search/employer_id/status filters

## Self-Check: PASSED

All expected files exist. Both task commits verified in git history.

---
*Phase: 46-learner-progression-report*
*Completed: 2026-02-19*
