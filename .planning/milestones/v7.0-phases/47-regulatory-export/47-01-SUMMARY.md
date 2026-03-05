---
phase: 47-regulatory-export
plan: 01
subsystem: api
tags: [postgresql, php, ajax, csv, learner-progression, compliance]

# Dependency graph
requires:
  - phase: 46-learner-progression
    provides: LearnerProgressionRepository with findForReport(), learner_lp_tracking table schema

provides:
  - findForRegulatoryExport() - 6-table JOIN returning all 17 Umalusi/DHET compliance columns
  - getRegulatoryExportCount() - companion COUNT query with same filter logic
  - get_regulatory_report AJAX action - JSON endpoint (rows + total) for frontend table
  - export_regulatory_csv AJAX action - UTF-8 BOM CSV file download with all compliance columns
affects:
  - phase 48 (frontend table consuming get_regulatory_report JSON)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shared filter-building pattern: PDO::PARAM_INT/PARAM_STR explicit binding extracted from findForReport"
    - "CSV streaming: php://output + fputcsv + UTF-8 BOM + exit (no wp_send_json)"
    - "Date validation: preg_match /^\d{4}-\d{2}-\d{2}$/ before passing to query"

key-files:
  created: []
  modified:
    - src/Learners/Repositories/LearnerProgressionRepository.php
    - src/Learners/Ajax/ProgressionAjaxHandlers.php

key-decisions:
  - "Admin-only access (manage_options) enforced on both regulatory endpoints — PII data requires highest privilege"
  - "Separate first_name/surname columns returned (not CONCAT) to support regulatory form field mapping"
  - "UTF-8 BOM prepended to CSV output for Excel UTF-8 compatibility"

patterns-established:
  - "Regulatory export follows findForReport pattern: separate data method + count method sharing identical filter/JOIN logic"

requirements-completed: [REG-01, REG-02, REG-03, REG-04]

# Metrics
duration: 1min
completed: 2026-02-23
---

# Phase 47 Plan 01: Regulatory Export Backend Summary

**PostgreSQL 6-table JOIN query returning 17 Umalusi/DHET compliance columns, plus JSON and CSV streaming AJAX endpoints with date-range filtering and admin capability enforcement**

## Performance

- **Duration:** 1 min
- **Started:** 2026-02-23T11:44:41Z
- **Completed:** 2026-02-23T11:46:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- `findForRegulatoryExport()` 6-table JOIN (lpt + class_type_subjects + learners + classes + clients + employers) returning all compliance columns: first_name, surname, sa_id_no, passport_number, lp_code, lp_name, lp_duration_hours, class_code, client_name, employer_name, start_date, completion_date, hours_trained, hours_present, hours_absent, status, portfolio_submitted
- `getRegulatoryExportCount()` companion method using identical JOIN/filter for pre-export record count display
- `handle_get_regulatory_report()` AJAX handler returning `{rows, total}` JSON for frontend table rendering
- `handle_export_regulatory_csv()` AJAX handler streaming UTF-8 BOM CSV file download with header row and all 17 columns

## Task Commits

Each task was committed atomically:

1. **Task 1: Add findForRegulatoryExport and getRegulatoryExportCount repository methods** - `c9685d6` (feat)
2. **Task 2: Add regulatory report and CSV export AJAX handlers** - `a1071fc` (feat)

## Files Created/Modified
- `src/Learners/Repositories/LearnerProgressionRepository.php` - Added `findForRegulatoryExport()` and `getRegulatoryExportCount()` with 6-table JOIN and date-range filters
- `src/Learners/Ajax/ProgressionAjaxHandlers.php` - Added `handle_get_regulatory_report()`, `handle_export_regulatory_csv()`, and two `add_action()` registrations

## Decisions Made
- Admin-only (`manage_options`) access on both endpoints — regulatory data contains learner PII (SA ID, passport numbers)
- `first_name` and `surname` returned as separate columns (not CONCAT) to support direct field-mapping on Umalusi/DHET forms
- UTF-8 BOM (`\xEF\xBB\xBF`) prepended to CSV so Excel opens it correctly without encoding issues
- Date inputs validated via `preg_match('/^\d{4}-\d{2}-\d{2}$/')` before being passed to the query
- Status whitelist `['in_progress', 'completed', 'on_hold']` applied before query binding
- `exit` called after CSV stream (no `wp_send_json`) — raw file output pattern

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Backend layer complete; frontend regulatory export UI (plan 47-02) can now consume `get_regulatory_report` for the data table and `export_regulatory_csv` for the download button
- Both endpoints are idempotent read-only operations (no state changes)

---
*Phase: 47-regulatory-export*
*Completed: 2026-02-23*

## Self-Check: PASSED

- FOUND: src/Learners/Repositories/LearnerProgressionRepository.php
- FOUND: src/Learners/Ajax/ProgressionAjaxHandlers.php
- FOUND: .planning/phases/47-regulatory-export/47-01-SUMMARY.md
- FOUND commit c9685d6: feat(47-01): add findForRegulatoryExport and getRegulatoryExportCount repository methods
- FOUND commit a1071fc: feat(47-01): add regulatory report JSON and CSV export AJAX handlers
