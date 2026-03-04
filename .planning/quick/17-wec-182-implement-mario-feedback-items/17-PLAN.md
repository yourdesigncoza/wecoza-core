---
phase: quick-17
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/js/classes/attendance-capture.js
  - views/classes/components/single-class/attendance.php
  - src/Classes/Ajax/AttendanceAjaxHandlers.php
  - assets/js/learners/progression-admin.js
  - src/Learners/Repositories/LearnerProgressionRepository.php
autonomous: true
requirements: [WEC-182-1c, WEC-182-1d, WEC-182-1e, WEC-182-3b, WEC-182-4a]

must_haves:
  truths:
    - "Exception button on pending attendance rows is clearly labelled 'Report Exception' not just a triangle icon"
    - "Blocked sessions (public holidays, exception dates) render as greyed-out rows with Blocked badge and block reason"
    - "Blocked sessions are excluded from pending count in summary cards"
    - "Stopped classes allow attendance capture for dates on or before the stop date"
    - "LP description in progression admin table shows class_type + class_subject + subject_code format"
    - "Regulatory CSV export includes Race and Gender columns"
  artifacts:
    - path: "assets/js/classes/attendance-capture.js"
      provides: "Exception button label, blocked row rendering, stopped class date gating"
    - path: "views/classes/components/single-class/attendance.php"
      provides: "Stopped class partial-lock logic with stop date pass-through"
    - path: "src/Classes/Ajax/AttendanceAjaxHandlers.php"
      provides: "require_active_class allows stopped classes within stop date"
    - path: "src/Learners/Repositories/LearnerProgressionRepository.php"
      provides: "Extended baseQuery and findForRegulatoryExport with class_type_name, class_subject, subject_code, race, gender"
    - path: "assets/js/learners/progression-admin.js"
      provides: "LP description concatenation in renderTable"
  key_links:
    - from: "attendance.php"
      to: "attendance-capture.js"
      via: "window.WeCozaSingleClass.stopDate"
      pattern: "stopDate"
    - from: "AttendanceAjaxHandlers.php require_active_class"
      to: "classes table"
      via: "stop_restart_dates check"
      pattern: "stop_restart_dates"
    - from: "progression-admin.js renderTable"
      to: "LearnerProgressionRepository baseQuery"
      via: "AJAX response fields class_type_name, class_subject, subject_code"
---

<objective>
Implement 5 WEC-182 Mario feedback items: restyle exception button, render blocked days, allow stopped-class capture until stop date, improve LP description format, and add race/gender to regulatory export.

Purpose: Address Mario's UX feedback on attendance and progression admin features.
Output: Updated JS, PHP view, AJAX handler, repository, and progression JS.
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/todos/pending/2026-03-04-wec-182-agent-absent-client-cancelled-ux.md
@.planning/todos/pending/2026-03-04-wec-182-block-exception-days-js-rendering.md
@.planning/todos/pending/2026-03-04-wec-182-stopped-class-capture-until-stop-date.md
@.planning/todos/pending/2026-03-04-wec-182-lp-description-detail-in-progression-admin.md

<interfaces>
<!-- Key types and contracts the executor needs. -->

From attendance-capture.js getActionButton():
- Pending sessions render Capture + Exception buttons in a btn-group
- Exception button currently: `<button class="btn btn-sm btn-subtle-warning btn-exception" ...><i class="bi bi-exclamation-triangle"></i></button>`
- Needs to become a labelled button: "Report Exception" text with icon

From attendance-capture.js renderSessionTable():
- Already handles `s.is_blocked` with greyed rows, badge, and no action buttons (done in quick-16)
- updateSummaryCards() already excludes blocked from pending count (done in quick-16)
- Both items 1c exception restyle and 1d blocked rendering are ALREADY DONE in current code

From attendance.php:
- Lock gate at line 38: `$isAttendanceLocked = $classStatus !== 'active'`
- For stopped classes, currently returns early with lock message
- Needs: if stopped, compute stop date, pass to JS, render partial UI

From AttendanceAjaxHandlers.php require_active_class():
- Line 66: `if ($status !== 'active')` blocks all writes
- Needs: if stopped + session_date param + stop_restart_dates => allow if date <= stop_date

From LearnerProgressionRepository baseQuery():
```php
SELECT lpt.*, cts.subject_name, cts.subject_duration,
       CONCAT(l.first_name, ' ', l.surname) AS learner_name,
       c.class_code, cl.client_id, cl.client_name
FROM learner_lp_tracking lpt
LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
LEFT JOIN learners l ON lpt.learner_id = l.id
LEFT JOIN classes c ON lpt.class_id = c.class_id
LEFT JOIN clients cl ON c.client_id = cl.client_id
```
Needs: JOIN class_types ct, add ct.class_type_name, c.class_subject, cts.subject_code

From findForRegulatoryExport():
- Already selects l.race and l.gender (verified at line 740-741)
- CSV header already includes Race and Gender (verified at lines 794-795)
- CSV rows already output race and gender (verified at lines 818-819)
- Item 4a is ALREADY DONE

From progression-admin.js renderTable() Col 3:
```javascript
$tr.append($('<td>').text(row.subject_name || ''));
```
Needs: build LP description from row.class_type_name + row.class_subject + row.subject_code
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Attendance -- restyle exception button + stopped class capture</name>
  <files>
    assets/js/classes/attendance-capture.js
    views/classes/components/single-class/attendance.php
    src/Classes/Ajax/AttendanceAjaxHandlers.php
  </files>
  <action>
NOTE: Items 1c (exception button visibility) and 1d (blocked rows) are ALREADY IMPLEMENTED in the current code. Verify by reading the current getActionButton() -- the exception button already exists with icon, it just needs "Report Exception" text added. And renderSessionTable() already handles is_blocked rows. The summary cards already exclude blocked from pending.

**1c. Restyle exception button (attendance-capture.js getActionButton()):**
Change the exception button from icon-only to a labelled button. In getActionButton() around line 245, change from:
```
'<button class="btn btn-sm btn-subtle-warning btn-exception" ...><i class="bi bi-exclamation-triangle"></i></button>'
```
To:
```
'<button class="btn btn-sm btn-phoenix-warning btn-exception" ...><i class="bi bi-exclamation-triangle-fill me-1"></i>Exception</button>'
```
Use `btn-phoenix-warning` (not `btn-subtle-warning`) for better visibility. Use filled triangle icon. Short label "Exception" fits the btn-group nicely.

**1e. Stopped class capture until stop date:**

**attendance.php** -- Replace the lock gate logic (lines 37-56):
- Keep the draft check: if `$classStatus === 'draft'`, show lock message and return early
- If `$classStatus === 'stopped'`:
  - Parse schedule_data from `$class['schedule_data']` (JSON string or array)
  - Extract `stop_restart_dates` array
  - Find the effective stop date: last entry with a `stop` date that has no corresponding `restart` after it. The stop_restart_dates format is an array of objects with `stop` and optionally `restart` keys. The final stop date is the `stop` value of the last entry where `restart` is null/empty.
  - If no valid stop date found, show lock message and return early
  - Otherwise, DO NOT return early -- render the full attendance UI
  - Pass the stop date to JS via the existing `wp_localize_script` data: add `'stopDate' => $stopDate` to the WeCozaSingleClass config. The wp_localize_script call is in `src/Classes/Controllers/ClassController.php` around line 492. Instead of modifying the controller, inject the stop date via a small inline script block at the bottom of attendance.php: `<script>if(window.WeCozaSingleClass) window.WeCozaSingleClass.stopDate = '<?= esc_js($stopDate) ?>';</script>`

**AttendanceAjaxHandlers.php require_active_class():**
- After the `$status !== 'active'` check, add a stopped-class exception:
- If `$status === 'stopped'`, check if a `session_date` param exists in `$_POST` or `$_GET`
- If session_date exists, look up `schedule_data` from the classes table for this class_id
- Parse stop_restart_dates, find effective stop date (same logic as above)
- If session_date <= stop_date, return (allow the request)
- Otherwise fall through to the existing error response
- Extract the stop date lookup into a helper function `get_effective_stop_date(array $scheduleData): ?string` to DRY between attendance.php and the AJAX handler

**attendance-capture.js:**
- In `getActionButton()`, check `config.stopDate`: if it exists and `s.date > config.stopDate`, return dash (no actions) instead of showing Capture/Exception buttons
- This disables capture for dates after stop date while allowing it for dates on or before
  </action>
  <verify>
    Load a single class page in the browser. Verify:
    1. Exception button shows "Exception" label with filled triangle icon
    2. On a stopped class with stop_restart_dates, attendance section renders (not locked)
    3. Dates before/on stop date show Capture + Exception buttons
    4. Dates after stop date show dash (no actions)
    Check PHP syntax: `php -l views/classes/components/single-class/attendance.php && php -l src/Classes/Ajax/AttendanceAjaxHandlers.php`
  </verify>
  <done>
    Exception button clearly labelled. Stopped classes render attendance UI with capture enabled for dates <= stop date and disabled for dates after. AJAX handler allows writes for stopped classes within stop date window.
  </done>
</task>

<task type="auto">
  <name>Task 2: Progression admin LP description format + verify export fields</name>
  <files>
    src/Learners/Repositories/LearnerProgressionRepository.php
    assets/js/learners/progression-admin.js
  </files>
  <action>
**3b. LP description in progression admin:**

**LearnerProgressionRepository.php baseQuery():**
Add JOIN to class_types and select additional fields:
```php
private function baseQuery(): string
{
    return "
        SELECT
            lpt.*,
            cts.subject_name,
            cts.subject_code,
            cts.subject_duration,
            CONCAT(l.first_name, ' ', l.surname) AS learner_name,
            c.class_code,
            c.class_subject,
            ct.class_type_name,
            cl.client_id,
            cl.client_name
        FROM learner_lp_tracking lpt
        LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
        LEFT JOIN learners l ON lpt.learner_id = l.id
        LEFT JOIN classes c ON lpt.class_id = c.class_id
        LEFT JOIN class_types ct ON c.class_type = ct.class_type_id
        LEFT JOIN clients cl ON c.client_id = cl.client_id
    ";
}
```
Key changes: added `LEFT JOIN class_types ct ON c.class_type = ct.class_type_id`, added `cts.subject_code`, `c.class_subject`, `ct.class_type_name` to SELECT.

This propagates to ALL methods using baseQuery(): findById, findCurrentForLearner, findAllForLearner, findHistoryForLearner, findByClass, findByClassTypeSubject, findWithFilters. The new fields are additive -- no breaking changes.

**progression-admin.js renderTable():**
Replace Col 3 (LP name) construction. Currently line 145:
```javascript
$tr.append($('<td>').text(row.subject_name || ''));
```
Change to build LP description using Mario's format:
```javascript
// Col 3: LP description (class_type + subject + level/module)
var lpDesc = '';
if (row.class_type_name) {
    lpDesc = row.class_type_name;
    if (row.class_subject) {
        lpDesc += ' ' + row.class_subject;
    } else {
        lpDesc += ' -';
    }
    if (row.subject_code) {
        lpDesc += ' ' + row.subject_code;
    }
} else {
    lpDesc = row.subject_name || '';
}
$tr.append($('<td>').text(lpDesc.trim()));
```
Logic: If class_type_name exists -> "TYPE SUBJECT CODE" (e.g., "AET Communication CL1") or "TYPE - CODE" if no subject (e.g., "GETC AET - LO4"). Fallback to subject_name if no class_type_name available.

Also update the hours log modal header (around line 666-669) where subject_name is displayed as a badge -- use the same LP description logic there. Extract into a helper function `buildLpDescription(row)` to DRY:
```javascript
function buildLpDescription(row) {
    if (row.class_type_name) {
        var desc = row.class_type_name;
        desc += row.class_subject ? ' ' + row.class_subject : ' -';
        if (row.subject_code) desc += ' ' + row.subject_code;
        return desc.trim();
    }
    return row.subject_name || '';
}
```

Also update the filter dropdown builder `buildFilterOptionsFromData()` (around line 349) -- use the same LP description for the subject filter dropdown labels so they match what the user sees in the table.

**4a. Race and Gender in export -- VERIFY ONLY:**
The findForRegulatoryExport() query already selects `l.race` and `l.gender` (lines 740-741). The CSV header already includes Race and Gender columns (lines 794-795). The CSV row output already maps these fields (lines 818-819). This item is ALREADY COMPLETE from quick-16. No changes needed -- just verify.
  </action>
  <verify>
    Check PHP syntax: `php -l src/Learners/Repositories/LearnerProgressionRepository.php`
    Load the progression admin page in the browser.
    Verify LP description column shows format like "AET Communication CL1" or "GETC AET - LO4" instead of just subject_name.
    Verify hours log modal header uses same format.
    Verify filter dropdown shows descriptive LP names.
  </verify>
  <done>
    Progression admin table shows detailed LP description (class_type + subject + level). Export already includes race/gender (verified). Repository baseQuery includes class_type_name, class_subject, subject_code for all downstream consumers.
  </done>
</task>

</tasks>

<verification>
1. `php -l views/classes/components/single-class/attendance.php` -- no syntax errors
2. `php -l src/Classes/Ajax/AttendanceAjaxHandlers.php` -- no syntax errors
3. `php -l src/Learners/Repositories/LearnerProgressionRepository.php` -- no syntax errors
4. Load single class page with active class -- exception button shows label "Exception"
5. Load single class page with stopped class -- attendance renders, dates after stop date disabled
6. Load progression admin page -- LP description shows concatenated format
7. Download regulatory CSV -- Race and Gender columns present (already working)
</verification>

<success_criteria>
- Exception button is visually obvious as a clickable button with text label
- Stopped classes show attendance UI with capture gated by stop date
- LP description in admin table follows TYPE SUBJECT CODE format
- No PHP syntax errors, no JS console errors
- All existing functionality preserved (no regressions)
</success_criteria>

<output>
After completion, create `.planning/quick/17-wec-182-implement-mario-feedback-items/17-SUMMARY.md`
</output>
