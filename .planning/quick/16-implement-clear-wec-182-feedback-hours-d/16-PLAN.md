---
phase: 16-implement-clear-wec-182-feedback
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/js/classes/attendance-capture.js
  - assets/js/learners/progression-admin.js
  - views/learners/progression-admin.php
  - src/Learners/Repositories/LearnerProgressionRepository.php
  - src/Learners/Ajax/ProgressionAjaxHandlers.php
  - assets/js/learners/regulatory-export.js
  - views/learners/regulatory-export.php
autonomous: true
requirements: [WEC-182-1a, WEC-182-3a, WEC-182-4a]
must_haves:
  truths:
    - "Attendance capture modal defaults hours_present to 0.0, not scheduled hours"
    - "Start New LP action appears in each row's actions dropdown, pre-selecting the learner"
    - "Regulatory export includes Race and Gender columns in both on-screen table and CSV"
  artifacts:
    - path: "assets/js/classes/attendance-capture.js"
      provides: "Hours default 0.0 in capture modal"
    - path: "assets/js/learners/progression-admin.js"
      provides: "Per-row Start LP action in dropdown"
    - path: "src/Learners/Repositories/LearnerProgressionRepository.php"
      provides: "race and gender in findForRegulatoryExport SQL"
  key_links:
    - from: "LearnerProgressionRepository::findForRegulatoryExport()"
      to: "ProgressionAjaxHandlers CSV + regulatory-export.js table"
      via: "race/gender columns in SQL result"
      pattern: "l\\.race.*l\\.gender"
---

<objective>
Implement three CLEAR feedback items from WEC-182 (Mario's review):
1. Attendance hours default to 0.0 (not scheduled hours)
2. "Start New LP" moved from header button to per-row action dropdown
3. Race & Gender added to regulatory export (table + CSV)

NOTE: Exception days blocking (1d), compulsory field (1b), agent absent UX (1c), stopped class capture (1e), LP description detail (3b) are DEFERRED pending Mario's clarification.

Purpose: Address user-reported UX issues and missing data in LP export
Output: Updated JS, PHP service, views, and repository files
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@CLAUDE.md

Key implementation reference:
- WEC-182 memory file: /home/laudes/.claude/projects/-opt-lampp-htdocs-wecoza-wp-content-plugins-wecoza-core/memory/wec-182-implementation.md

<interfaces>
<!-- AttendanceService::generateSessionList() returns array of: -->
```php
[
    'date'            => 'YYYY-MM-DD',
    'day'             => 'Monday',
    'start_time'      => 'HH:MM',
    'end_time'        => 'HH:MM',
    'scheduled_hours' => float,
    'session_id'      => int|null,
    'status'          => 'pending'|'captured'|'client_cancelled'|'agent_absent',
    'captured_by'     => int|null,
    'captured_at'     => string|null,
    'notes'           => string|null,
]
```

<!-- PublicHolidaysController::getHolidaysByYear(int $year) returns: -->
```php
[['date' => 'YYYY-MM-DD', 'name' => 'Holiday Name'], ...]
```

<!-- schedule_data JSON exception_dates structure: -->
```php
[['date' => 'YYYY-MM-DD', 'reason' => 'string'], ...]
```

<!-- findForRegulatoryExport() SQL currently selects from learners table aliased as `l` -->
<!-- learners table has `race` and `gender` columns (confirmed in LearnerModel) -->

<!-- progression-admin.js renderTable() builds per-row dropdown at lines 178-232 -->
<!-- handleStartNewLPClick() at line 731 opens modal, populateStartLPModal() populates selects -->
<!-- #start-lp-learner is a <select> populated by populateSelect() from filterOptionsCache.learners -->
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Attendance hours default to 0</name>
  <files>
    assets/js/classes/attendance-capture.js
  </files>
  <action>
**1a. Hours default to 0.0 in capture modal:**

In `assets/js/classes/attendance-capture.js`, `openCaptureModal()` around line 341:
- Change `value="' + scheduledHours.toFixed(1) + '"` to `value="0.0"`
- Keep `max="' + scheduledHours.toFixed(1) + '"` unchanged (agent sees target)
- The "Hours Trained" display column (line 338) should still show `scheduledHours.toFixed(1)` -- that's the scheduled target, not user input
- The "Hours Absent" calc in `submitCapture()` (around line 375+) already computes absent = trained - present, so setting present to 0 means absent defaults to full scheduled hours. This is correct.
- Server-side validation in `AttendanceAjaxHandlers.php` line 154 already validates `0 <= hours_present <= scheduledHours`, so 0.0 is already accepted. No PHP change needed for this item.
  </action>
  <verify>
    <automated>grep -n 'value="0.0"' assets/js/classes/attendance-capture.js</automated>
  </verify>
  <done>
    - Capture modal hours_present input defaults to 0.0 (not scheduled hours)
    - Max attribute still shows scheduled hours as target
  </done>
</task>

<task type="auto">
  <name>Task 2: Move Start LP to per-row dropdown and add Race/Gender to export</name>
  <files>
    assets/js/learners/progression-admin.js
    views/learners/progression-admin.php
    src/Learners/Repositories/LearnerProgressionRepository.php
    src/Learners/Ajax/ProgressionAjaxHandlers.php
    assets/js/learners/regulatory-export.js
    views/learners/regulatory-export.php
  </files>
  <action>
**3a. Move "Start New LP" from header to per-row action dropdown:**

In `views/learners/progression-admin.php` (around line 41-43):
- Remove the `#btn-start-new-lp` button from the header `<div class="col-auto">`. Keep the help button.

In `assets/js/learners/progression-admin.js`, method `renderTable()` (around line 220-229):
- After the existing "Mark Complete" menu item block (line 229), add a new "Start New LP" menu item for rows where status is NOT 'in_progress' (only show for completed/on_hold rows, so the learner doesn't get a second active LP):
  ```javascript
  // Start New LP (only for completed or on_hold — not in_progress which already has active LP)
  if (row.status !== 'in_progress') {
      const $startLpItem = $('<li>').append(
          $('<a>').addClass('dropdown-item btn-start-lp-for-row')
              .attr('href', '#')
              .attr('data-learner-id', row.learner_id || '')
              .attr('data-learner-name', row.learner_name || '')
              .html('<i class="bi bi-plus-circle me-2"></i>Start New LP')
      );
      $menu.append($startLpItem);
  }
  ```

- IMPORTANT: The server response for admin progressions must include `learner_id`. Check if `handle_get_admin_progressions` already returns it. The `baseQuery()` in `LearnerProgressionRepository` selects `lpt.*` which includes `learner_id`. So `row.learner_id` should be available.

- Modify `handleStartNewLPClick()` to accept an optional learner ID parameter:
  ```javascript
  function handleStartNewLPClick(preselectedLearnerId) {
      clearAlert('#start-lp-alert');
      $('#start-lp-form')[0].reset();
      populateStartLPModal();
      if (preselectedLearnerId) {
          // Set value after populate (may need short delay if AJAX-based)
          $('#start-lp-learner').val(preselectedLearnerId);
      }
      const modal = new bootstrap.Modal(document.getElementById('startNewLPModal'));
      modal.show();
  }
  ```

- Update event binding: remove the old `$('#btn-start-new-lp').on('click', ...)` binding (line 1022). Add delegated binding for the new per-row action:
  ```javascript
  $('#progression-admin-tbody').on('click', '.btn-start-lp-for-row', function(e) {
      e.preventDefault();
      const learnerId = $(this).data('learner-id');
      handleStartNewLPClick(learnerId);
  });
  ```

**4a. Add Race and Gender to regulatory export:**

In `src/Learners/Repositories/LearnerProgressionRepository.php`, method `findForRegulatoryExport()` (around line 737):
- Add `l.race,` and `l.gender,` to the SELECT clause, after `l.passport_number,` (line 739). Place them right after passport_number for logical grouping of learner demographics.

In `src/Learners/Ajax/ProgressionAjaxHandlers.php`, method `handle_export_regulatory_csv()`:
- Add 'Race' and 'Gender' to the CSV header array (line 789-807), after 'Passport Number':
  ```php
  'Race',
  'Gender',
  ```
- Add the corresponding data values in the data row loop (after line 815 `$row['passport_number']`):
  ```php
  $row['race']              ?? '',
  $row['gender']            ?? '',
  ```

In `assets/js/learners/regulatory-export.js`, method `renderTable()` (around line 186):
- Add Race and Gender columns to the main row, after the SA ID column (line 201). Insert before the programme column:
  ```javascript
  $mainRow.append($('<td>').addClass(cellClass).text(row.race || ''));
  $mainRow.append($('<td>').addClass(cellClass).text(row.gender || ''));
  ```
- Update the detail row colspan from 9 to 11 (line 219: `colspan="9"` -> `colspan="11"`).

In `views/learners/regulatory-export.php`, the `<thead>` (around line 87-96):
- Add two new `<th>` columns after the "SA ID" column (line 90):
  ```html
  <th class="sort pe-1 align-middle white-space-nowrap">Race</th>
  <th class="sort pe-1 align-middle white-space-nowrap">Gender</th>
  ```
  </action>
  <verify>
    <automated>grep -n 'l\.race' src/Learners/Repositories/LearnerProgressionRepository.php && grep -n "'Race'" src/Learners/Ajax/ProgressionAjaxHandlers.php && grep -n 'btn-start-lp-for-row' assets/js/learners/progression-admin.js && grep -n 'row.race' assets/js/learners/regulatory-export.js</automated>
  </verify>
  <done>
    - Header "Start New LP" button removed from progression admin view
    - Per-row dropdown shows "Start New LP" for completed/on_hold rows
    - Clicking per-row "Start New LP" opens modal with learner pre-selected
    - findForRegulatoryExport() SQL includes l.race and l.gender
    - CSV export header and data rows include Race and Gender after Passport Number
    - On-screen regulatory table shows Race and Gender columns after SA ID
  </done>
</task>

</tasks>

<verification>
1. Open attendance capture for any class -- click Capture on a pending session. Verify hours_present input defaults to 0.0, not the scheduled hours. Max attribute should still show scheduled hours.
2. Open Progression Admin panel -- verify "Start New LP" button is gone from header. On a completed row, open the actions dropdown and verify "Start New LP" appears. Click it and verify the modal opens with that learner pre-selected.
3. Open Regulatory Export, generate report -- verify Race and Gender columns appear in the table. Download CSV and verify Race and Gender columns present after Passport Number.
</verification>

<success_criteria>
- Attendance hours default to 0.0 in capture modal
- Start New LP available per-row (not in header), pre-selects learner
- Regulatory export includes Race and Gender in table and CSV
</success_criteria>

<output>
After completion, create `.planning/quick/16-implement-clear-wec-182-feedback-hours-d/16-SUMMARY.md`
</output>
