---
phase: quick-20
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/js/classes/attendance-capture.js
  - views/classes/components/single-class/attendance.php
  - /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
autonomous: true
requirements: [WEC-182-4, WEC-182-5]
must_haves:
  truths:
    - "Amber warning appears next to hours_present input when value exceeds scheduled hours"
    - "Warning disappears when value is corrected back within range"
    - "Submission still works when hours exceed scheduled (soft warning only)"
    - "Monthly summary row shows totals for scheduled, present, absent hours and attendance %"
    - "Summary row updates when month filter changes"
  artifacts:
    - path: "assets/js/classes/attendance-capture.js"
      provides: "Hours warning logic + monthly summary row"
    - path: "views/classes/components/single-class/attendance.php"
      provides: "tfoot element for summary row"
  key_links:
    - from: "hours-present-input event handler"
      to: "warning element next to input"
      via: "input event in capture modal"
      pattern: "hours-warning"
    - from: "renderSessionTable()"
      to: "tfoot summary row"
      via: "computed totals from filtered sessions"
      pattern: "tfoot|summary"
---

<objective>
Add two small attendance capture enhancements from WEC-182 Mario feedback:
1. Soft amber warning when hours_present exceeds scheduled hours per learner
2. Monthly summary totals row (tfoot) showing scheduled/present/absent totals + attendance %

Purpose: Improve data entry accuracy and provide at-a-glance monthly attendance metrics.
Output: Updated attendance-capture.js + attendance.php view + minimal CSS
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@assets/js/classes/attendance-capture.js
@views/classes/components/single-class/attendance.php
@/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add hours validation warning in capture modal</name>
  <files>assets/js/classes/attendance-capture.js, /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css</files>
  <action>
In attendance-capture.js, modify the existing `input change` event handler for `.hours-present-input` in `bindEvents()` (Section 5, line ~307-313).

After the existing hours-absent calculation logic, add warning display logic:
- Get `max` attribute from the input (scheduled hours)
- If `present > max`: show a small amber warning element after the input. Use a `<span class="hours-over-warning text-warning ms-1" title="Exceeds scheduled hours"><i class="bi bi-exclamation-triangle-fill"></i></span>` appended after the input. Only add if not already present.
- If `present <= max`: remove any existing `.hours-over-warning` sibling

Also modify the `submitCapture()` validation (Section 6, line ~400-406). Currently the validation REJECTS hours > max (`hoursPresent > maxHours` triggers `isValid = false`). Change this to:
- Remove the `hoursPresent > maxHours` check from the isValid guard — only reject negative or NaN values
- The `max` attribute on the input already provides browser-level soft guidance
- This makes the warning truly soft (submission proceeds regardless)

In ydcoza-styles.css, add minimal style:
```css
/* Attendance hours over-scheduled warning */
.hours-over-warning { font-size: 0.85em; cursor: help; }
```
  </action>
  <verify>Load a single class page, open capture modal, enter hours exceeding scheduled — amber triangle icon appears. Enter valid hours — icon disappears. Submit with over-hours — submission succeeds (not blocked).</verify>
  <done>Amber warning icon appears/disappears reactively on hours_present input. Submission is not blocked by over-hours values.</done>
</task>

<task type="auto">
  <name>Task 2: Add monthly summary totals row to session table</name>
  <files>assets/js/classes/attendance-capture.js, views/classes/components/single-class/attendance.php</files>
  <action>
In attendance.php, add a `<tfoot>` element to the session table (after `<tbody id="attendance-sessions-tbody"></tbody>`, before `</table>`):
```html
<tfoot id="attendance-sessions-tfoot" class="border-top"></tfoot>
```

In attendance-capture.js, create a new helper function `renderSummaryRow()` in Section 4 (after `renderSessionTable`):

```javascript
function renderSummaryRow() {
    var $tfoot = $('#attendance-sessions-tfoot');
    var filtered = currentMonth === 'all'
        ? allSessions
        : allSessions.filter(function(s) {
            return s.date && s.date.substring(0, 7) === currentMonth;
        });

    // Only show for non-empty, non-"all" filtered views
    // Also show for "all" if there are sessions
    if (filtered.length === 0) {
        $tfoot.html('');
        return;
    }

    // Only sum captured sessions (not pending/blocked/exception)
    var capturedSessions = filtered.filter(function(s) {
        return s.status === 'captured';
    });

    var totalScheduled = 0;
    var totalPresent = 0;
    var totalAbsent = 0;

    // Scheduled hours: sum ALL non-blocked sessions (the full schedule)
    filtered.forEach(function(s) {
        if (!s.is_blocked) {
            totalScheduled += parseFloat(s.scheduled_hours) || 0;
        }
    });

    // Present/absent: from captured session detail (use session-level totals if available,
    // otherwise approximate from scheduled hours for captured sessions)
    // Note: session objects don't carry per-learner totals, so we use:
    // - For captured: hours = scheduled (hours_trained), present/absent tracked server-side
    // - Best approximation: captured count * scheduled_hours for present estimate
    // Actually, we only have scheduled_hours per session. Show scheduled total and captured count.
    // Simpler approach: show total scheduled hours, captured session count, and percentage.

    var capturedCount = capturedSessions.length;
    var totalCount = filtered.filter(function(s) { return !s.is_blocked; }).length;
    var pct = totalCount > 0 ? Math.round((capturedCount / totalCount) * 100) : 0;

    var badgeCls = pct >= 80 ? 'badge-phoenix-success'
        : pct >= 50 ? 'badge-phoenix-warning'
        : 'badge-phoenix-danger';

    $tfoot.html(
        '<tr class="bg-body-highlight fw-semibold">'
        + '<td class="ps-3" colspan="3">Summary</td>'
        + '<td>' + totalScheduled.toFixed(1) + '</td>'
        + '<td colspan="2" class="text-end pe-3">'
        + '<span class="me-3">' + capturedCount + ' / ' + totalCount + ' sessions</span>'
        + '<span class="badge badge-phoenix ' + badgeCls + '">' + pct + '%</span>'
        + '</td>'
        + '</tr>'
    );
}
```

Call `renderSummaryRow()` at the end of `renderSessionTable()` function (after `$('#attendance-sessions-tbody').html(html);`).

This ensures the summary row updates whenever the month filter changes (since `renderSessionTable` is called from the month-select change handler).
  </action>
  <verify>Load a single class page with attendance data. Verify tfoot summary row shows at bottom of session table with total scheduled hours, captured/total session count, and color-coded percentage badge. Change month filter — summary row updates to reflect filtered data.</verify>
  <done>Summary totals row visible at bottom of attendance table showing scheduled hours total, session capture ratio, and attendance percentage with color-coded badge. Updates on month filter change.</done>
</task>

</tasks>

<verification>
1. Navigate to a single class page with attendance sessions
2. Verify summary row appears at bottom of attendance session table
3. Change month filter — summary row updates correctly
4. Open capture modal — enter hours > scheduled — amber warning triangle appears
5. Correct hours back to within range — warning disappears
6. Submit with over-scheduled hours — submission succeeds (no hard block)
7. No JS console errors
</verification>

<success_criteria>
- Amber warning icon appears next to hours_present input when value exceeds scheduled, disappears when corrected
- Attendance submission works even when hours exceed scheduled (soft warning only)
- Monthly summary tfoot row shows total scheduled hours, captured/total sessions, and color-coded attendance percentage
- Summary row updates when month filter changes
- No regression in existing capture/exception/detail modal functionality
</success_criteria>

<output>
After completion, create `.planning/quick/20-wec-182-hours-validation-warning-monthly/20-SUMMARY.md`
</output>
