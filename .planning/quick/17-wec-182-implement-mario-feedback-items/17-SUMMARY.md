---
phase: quick-17
plan: 01
subsystem: Classes/Attendance + Learners/Progression
tags: [attendance, progression, ux, stopped-class, lp-description]
requirements: [WEC-182-1c, WEC-182-1d, WEC-182-1e, WEC-182-3b, WEC-182-4a]
dependency_graph:
  requires: [quick-16]
  provides: [attendance-stopped-class-gate, lp-description-format]
  affects: [attendance-capture.js, attendance.php, AttendanceAjaxHandlers.php, LearnerProgressionRepository.php, progression-admin.js]
tech_stack:
  added: []
  patterns: [helper-function-extraction, stopped-class-date-gating, lp-description-builder]
key_files:
  created: []
  modified:
    - assets/js/classes/attendance-capture.js
    - views/classes/components/single-class/attendance.php
    - src/Classes/Ajax/AttendanceAjaxHandlers.php
    - core/Helpers/functions.php
    - src/Learners/Repositories/LearnerProgressionRepository.php
    - assets/js/learners/progression-admin.js
decisions:
  - "Added wecoza_get_effective_stop_date() to core/Helpers/functions.php (not inline) for DRY reuse between attendance.php view and AttendanceAjaxHandlers.php AJAX guard"
  - "Exception button uses 'Exception' label (not 'Report Exception') to fit btn-group width"
  - "buildLpDescription() helper added to progression-admin.js utility section for DRY use across table, modal, and filter dropdowns"
  - "WEC-182-1d (blocked rows) and WEC-182-4a (race/gender export) were already complete from quick-16; verified only, no changes needed"
metrics:
  duration_minutes: 25
  completed_date: "2026-03-04"
  tasks_completed: 2
  files_modified: 6
---

# Quick Task 17 Summary: WEC-182 Mario Feedback Items

**One-liner:** Exception button labelled with text, stopped-class attendance gated by stop date, LP description uses class_type + subject + code format.

## Tasks Completed

| # | Task | Commit | Status |
|---|------|--------|--------|
| 1 | Attendance: exception button label + stopped class capture | add7e57 | Done |
| 2 | Progression admin LP description format + verify export fields | e20f8bf | Done |

## What Was Implemented

### Task 1: Attendance

**WEC-182-1c: Exception button restyle**
- Changed from icon-only `btn-subtle-warning` to labelled `btn-phoenix-warning` with filled icon + "Exception" text
- File: `assets/js/classes/attendance-capture.js` — `getActionButton()`

**WEC-182-1e: Stopped class capture until stop date**
- Added `wecoza_get_effective_stop_date(array $scheduleData): ?string` helper to `core/Helpers/functions.php`
  - Traverses `stop_restart_dates` array in reverse, finds last `stop` entry without a `restart`
- `attendance.php`: draft classes always locked; stopped classes render full UI if effective stop date found; injects `WeCozaSingleClass.stopDate` via inline script
- `attendance-capture.js` `getActionButton()`: checks `config.stopDate` — dates after stop date return dash (no actions)
- `AttendanceAjaxHandlers.php` `require_active_class()`: stopped classes allowed if `session_date` POST param <= effective stop date

**WEC-182-1d: Blocked rows (already done in quick-16)**
- Verified: `renderSessionTable()` already renders greyed rows with Blocked badge; `updateSummaryCards()` already excludes blocked from pending count. No changes needed.

### Task 2: Progression Admin

**WEC-182-3b: LP description detail**
- `LearnerProgressionRepository.php` `baseQuery()`: added `LEFT JOIN class_types ct ON c.class_type = ct.class_type_id`, added `ct.class_type_name`, `c.class_subject`, `cts.subject_code` to SELECT — propagates to all downstream methods (findById, findAllForLearner, findByClass, findWithFilters, etc.)
- `progression-admin.js`: added `buildLpDescription(row)` helper in Utility section
  - Format: "TYPE SUBJECT CODE" (e.g., "AET Communication CL1") or "TYPE - CODE" if no subject
  - Fallback to `subject_name` if `class_type_name` not available
- Used in: renderTable() Col 3, renderHoursLogSummary() badge, buildFilterOptionsFromData() subjects

**WEC-182-4a: Race/Gender in regulatory export (already done in quick-16)**
- Verified: `findForRegulatoryExport()` already selects `l.race` and `l.gender`. No changes needed.

## Deviations from Plan

None — plan executed exactly as written. Items 1d and 4a were pre-verified as already complete per plan notes.

## Self-Check: PASSED

All 6 modified files exist on disk. Both task commits (add7e57, e20f8bf) confirmed in git log. 17-SUMMARY.md created successfully.
