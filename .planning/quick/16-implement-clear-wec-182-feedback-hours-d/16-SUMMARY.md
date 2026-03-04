---
phase: quick-16
plan: 01
subsystem: Learners / Classes
tags: [ux, attendance, progression, regulatory-export, wec-182]
dependency_graph:
  requires: []
  provides: [attendance-hours-default, per-row-start-lp, race-gender-export]
  affects: [attendance-capture.js, progression-admin.js, regulatory-export.js, LearnerProgressionRepository, ProgressionAjaxHandlers]
tech_stack:
  added: []
  patterns: [delegated-event-binding, optional-param-preselect]
key_files:
  created: []
  modified:
    - assets/js/classes/attendance-capture.js
    - assets/js/learners/progression-admin.js
    - assets/js/learners/regulatory-export.js
    - src/Learners/Repositories/LearnerProgressionRepository.php
    - src/Learners/Ajax/ProgressionAjaxHandlers.php
    - views/learners/progression-admin.php
    - views/learners/regulatory-export.php
decisions:
  - "Start New LP shown only for completed/on_hold rows so in_progress learners cannot start a second active LP"
  - "Race/Gender inserted after Passport Number in SQL SELECT to group demographic columns logically"
metrics:
  duration: ~10 minutes
  completed: 2026-03-04
  tasks_completed: 2
  files_modified: 7
---

# Quick Task 16: WEC-182 UX Feedback — Hours Default, Per-Row Start LP, Race/Gender Export

**One-liner:** Attendance hours default to 0.0, Start New LP moved to per-row dropdown with learner pre-selection, Race and Gender added to regulatory export table and CSV.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Attendance hours default to 0.0 | 962608b | assets/js/classes/attendance-capture.js |
| 2 | Per-row Start LP + Race/Gender export | 962608b | 6 files (see below) |

## Changes Made

### Task 1: Attendance Hours Default (WEC-182-1a)

File: `assets/js/classes/attendance-capture.js`

- `hours_present` input in `openCaptureModal()` already used `value="0.0"` — confirmed in place.
- `max` attribute retains `scheduledHours.toFixed(1)` so agent can see the target.
- "Hours Absent" auto-computes as trained minus present; defaulting present to 0 means absent defaults to full scheduled hours until agent enters actuals.

### Task 2a: Move Start New LP to Per-Row Dropdown (WEC-182-3a)

Files: `views/learners/progression-admin.php`, `assets/js/learners/progression-admin.js`

- Removed `#btn-start-new-lp` button from card header (PHP view).
- Removed `$('#btn-start-new-lp').on('click', ...)` binding from JS.
- Added "Start New LP" menu item (`.btn-start-lp-for-row`) to per-row actions dropdown; only shown when `row.status !== 'in_progress'` (completed or on_hold rows) to prevent dual-active LPs.
- `handleStartNewLPClick()` updated to accept optional `preselectedLearnerId` parameter; sets `#start-lp-learner` select value before showing modal.
- Added delegated click handler on `#progression-admin-tbody` for `.btn-start-lp-for-row` that passes `data-learner-id` to `handleStartNewLPClick()`.

### Task 2b: Race and Gender in Regulatory Export (WEC-182-4a)

Files: `src/Learners/Repositories/LearnerProgressionRepository.php`, `src/Learners/Ajax/ProgressionAjaxHandlers.php`, `assets/js/learners/regulatory-export.js`, `views/learners/regulatory-export.php`

- `findForRegulatoryExport()` SELECT clause: added `l.race,` and `l.gender,` after `l.passport_number`.
- CSV handler: added 'Race' and 'Gender' header columns and `$row['race']` / `$row['gender']` data values after Passport Number.
- `renderTable()` in regulatory-export.js: added two `<td>` cells for `row.race` and `row.gender` after SA ID column.
- Detail row colspan updated from 9 to 11 to match new column count.
- `<thead>` in regulatory-export.php: added `<th>Race</th>` and `<th>Gender</th>` after SA ID column.

## Deviations from Plan

None - plan executed exactly as written. Task 1 (`value="0.0"`) was already in place from a prior session; confirmed and included in commit.

## Self-Check

- [x] `value="0.0"` present at attendance-capture.js:356
- [x] `l.race` present at LearnerProgressionRepository.php:740
- [x] `'Race'` present at ProgressionAjaxHandlers.php:794
- [x] `btn-start-lp-for-row` present at progression-admin.js:234 (dropdown item) and :1039 (event binding)
- [x] `row.race` present at regulatory-export.js:202
- [x] `#btn-start-new-lp` removed from both view and JS
- [x] Commit 962608b verified

## Self-Check: PASSED
