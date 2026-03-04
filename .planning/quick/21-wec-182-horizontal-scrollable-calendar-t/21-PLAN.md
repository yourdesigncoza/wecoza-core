---
phase: quick-21
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - views/classes/components/single-class/attendance.php
  - assets/js/classes/attendance-capture.js
  - /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
autonomous: true
requirements: [WEC-182-CALENDAR]
---

<objective>
Add a monthly calendar grid to the attendance section on the single class display page. Standard 7-column week grid (Mon-Sun), navigate between months with arrows. Session days are color-coded cells by status. Click a session day to capture/view. Non-class days are empty. Blocked days greyed with reason tooltip.

This replaces the horizontal Gantt timeline approach which doesn't scale for long-running classes.
</objective>

<context>
@views/classes/components/single-class/attendance.php
@assets/js/classes/attendance-capture.js

<interfaces>
Each session object in allSessions has:
- date: "YYYY-MM-DD"
- day: "Mon", "Tue", etc.
- status: "pending" | "captured" | "client_cancelled" | "agent_absent"
- is_blocked: boolean
- block_reason: string
- scheduled_hours: string
- session_id: number|null

Existing functions to reuse:
- openCaptureModal(date) — opens capture modal
- openDetailModal(sessionId) — opens view detail modal
- escHtml(val), escAttr(val) — escaping helpers

Existing state:
- allSessions[] — loaded by loadSessions() AJAX
- currentMonth — "YYYY-MM" string, used by month filter select

Calendar should sync with month filter: changing calendar month updates the select, changing select updates calendar.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Monthly calendar grid — PHP container + JS render + CSS</name>
  <files>
    views/classes/components/single-class/attendance.php
    assets/js/classes/attendance-capture.js
    /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
  </files>
  <action>
**PHP (attendance.php):** Insert calendar container between summary stats (line 107) and month filter (line 109). Add BEFORE the `<!-- Month Filter -->` comment:

```html
<!-- Monthly Calendar View -->
<div class="mb-3" id="att-calendar-wrapper">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <button type="button" class="btn btn-sm btn-subtle-secondary" id="att-cal-prev">
            <i class="bi bi-chevron-left"></i>
        </button>
        <h6 class="text-body-tertiary mb-0" id="att-cal-title">...</h6>
        <button type="button" class="btn btn-sm btn-subtle-secondary" id="att-cal-next">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
    <div id="att-calendar-grid"></div>
    <div class="d-flex gap-3 mt-2 flex-wrap">
        <small class="text-body-tertiary"><span class="att-cal-legend att-cal-captured"></span> Captured</small>
        <small class="text-body-tertiary"><span class="att-cal-legend att-cal-pending"></span> Pending</small>
        <small class="text-body-tertiary"><span class="att-cal-legend att-cal-exception"></span> Exception</small>
        <small class="text-body-tertiary"><span class="att-cal-legend att-cal-blocked"></span> Blocked</small>
        <small class="text-body-tertiary"><span class="att-cal-legend att-cal-future"></span> Upcoming</small>
    </div>
</div>
```

**JS (attendance-capture.js):**

Add a new section "SECTION 2B: MONTHLY CALENDAR" after SECTION 2 (summary cards) and before SECTION 3 (month tabs).

Add module-level variable:
```javascript
let calendarMonth = new Date().toISOString().substring(0, 7); // "YYYY-MM"
```

Add `renderCalendar()` function:

```javascript
function renderCalendar() {
    var $grid = $('#att-calendar-grid');
    if (!$grid.length) return;

    // Parse calendarMonth
    var parts = calendarMonth.split('-');
    var year  = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10) - 1; // 0-indexed

    // Update title
    var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $('#att-cal-title').text(monthNames[month] + ' ' + year);

    // Build session lookup by date for this month
    var sessionMap = {};
    allSessions.forEach(function(s) {
        if (s.date && s.date.substring(0, 7) === calendarMonth) {
            // Support multiple sessions per day — store as array
            if (!sessionMap[s.date]) sessionMap[s.date] = [];
            sessionMap[s.date].push(s);
        }
    });

    // Calendar grid: 7 columns (Mon=0 ... Sun=6)
    var firstDay = new Date(year, month, 1);
    var lastDay  = new Date(year, month + 1, 0);
    var startDow = (firstDay.getDay() + 6) % 7; // Monday = 0
    var totalDays = lastDay.getDate();
    var today = new Date().toISOString().substring(0, 10);

    var html = '<table class="att-cal-table"><thead><tr>';
    var dayHeaders = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    dayHeaders.forEach(function(d) {
        html += '<th>' + d + '</th>';
    });
    html += '</tr></thead><tbody><tr>';

    // Empty cells before first day
    for (var i = 0; i < startDow; i++) {
        html += '<td class="att-cal-empty"></td>';
    }

    var cellCount = startDow;
    for (var day = 1; day <= totalDays; day++) {
        var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        var sessions = sessionMap[dateStr] || [];

        if (sessions.length === 0) {
            // Non-class day
            var todayClass = dateStr === today ? ' att-cal-today' : '';
            html += '<td class="att-cal-noclass' + todayClass + '"><span class="att-cal-daynum">' + day + '</span></td>';
        } else {
            // Class day — determine status from sessions
            // If multiple sessions, show combined status
            var cellClass = getCalendarCellClass(sessions, today, dateStr);
            var tooltip = getCalendarTooltip(sessions);
            var clickable = isCalendarClickable(sessions, today, dateStr);
            var todayMark = dateStr === today ? ' att-cal-today' : '';

            html += '<td class="att-cal-day ' + cellClass + todayMark + (clickable ? ' att-cal-clickable' : '') + '"'
                  + ' data-date="' + escAttr(dateStr) + '"'
                  + (tooltip ? ' title="' + escAttr(tooltip) + '"' : '')
                  + '>'
                  + '<span class="att-cal-daynum">' + day + '</span>';

            // Show session count if multiple
            if (sessions.length > 1) {
                html += '<span class="att-cal-count">' + sessions.length + '</span>';
            }

            html += '</td>';
        }

        cellCount++;
        if (cellCount % 7 === 0 && day < totalDays) {
            html += '</tr><tr>';
        }
    }

    // Fill remaining cells
    var remaining = 7 - (cellCount % 7);
    if (remaining < 7) {
        for (var r = 0; r < remaining; r++) {
            html += '<td class="att-cal-empty"></td>';
        }
    }

    html += '</tr></tbody></table>';
    $grid.html(html);
}

function getCalendarCellClass(sessions, today, dateStr) {
    // If any session is blocked, whole day is blocked
    var allBlocked = sessions.every(function(s) { return s.is_blocked; });
    if (allBlocked) return 'att-cal-blocked';

    var hasException = sessions.some(function(s) {
        return s.status === 'client_cancelled' || s.status === 'agent_absent';
    });
    var allCaptured = sessions.every(function(s) {
        return s.status === 'captured' || s.is_blocked;
    });
    var hasPending = sessions.some(function(s) {
        return s.status === 'pending' && !s.is_blocked;
    });

    if (allCaptured) return 'att-cal-captured';
    if (hasException) return 'att-cal-exception';
    if (hasPending && dateStr < today) return 'att-cal-pending'; // missed
    if (hasPending) return 'att-cal-future'; // upcoming
    return '';
}

function getCalendarTooltip(sessions) {
    var tips = [];
    sessions.forEach(function(s) {
        if (s.is_blocked) {
            tips.push(s.block_reason || 'Blocked');
        } else {
            var time = (s.start_time || '') + (s.end_time ? ' - ' + s.end_time : '');
            var hrs = s.scheduled_hours ? parseFloat(s.scheduled_hours).toFixed(1) + 'h' : '';
            tips.push([time, hrs, s.status].filter(Boolean).join(' | '));
        }
    });
    return tips.join('\n');
}

function isCalendarClickable(sessions, today, dateStr) {
    // Not clickable if all blocked
    if (sessions.every(function(s) { return s.is_blocked; })) return false;
    // Not clickable if future pending
    if (sessions.every(function(s) { return s.status === 'pending'; }) && dateStr > today) return false;
    return true;
}
```

Add click handler in `bindEvents()`:
```javascript
$('#att-calendar-grid').on('click', '.att-cal-clickable', function() {
    var date = $(this).data('date');
    if (!date) return;
    var sessions = allSessions.filter(function(s) { return s.date === date && !s.is_blocked; });
    if (sessions.length === 0) return;

    // If captured session exists, show detail; otherwise open capture
    var captured = sessions.find(function(s) { return s.status === 'captured'; });
    if (captured && captured.session_id) {
        openDetailModal(captured.session_id);
    } else {
        openCaptureModal(date);
    }
});
```

Add prev/next month handlers in `bindEvents()`:
```javascript
$('#att-cal-prev').on('click', function() {
    var parts = calendarMonth.split('-');
    var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 2, 1);
    calendarMonth = d.toISOString().substring(0, 7);
    renderCalendar();
    // Sync month filter select
    $('#attendance-month-select').val(calendarMonth).trigger('change');
});
$('#att-cal-next').on('click', function() {
    var parts = calendarMonth.split('-');
    var d = new Date(parseInt(parts[0]), parseInt(parts[1]), 1);
    calendarMonth = d.toISOString().substring(0, 7);
    renderCalendar();
    $('#attendance-month-select').val(calendarMonth).trigger('change');
});
```

Sync calendar when month filter select changes — add to the existing month select change handler:
```javascript
// Inside the existing change handler for #attendance-month-select,
// after the existing logic, add:
if (val !== 'all') {
    calendarMonth = val;
    renderCalendar();
}
```

Call `renderCalendar()` from `loadSessions()` success handler, right after `updateSummaryCards()`:
```javascript
updateSummaryCards();
renderCalendar();  // <-- ADD THIS LINE
buildMonthTabs();
```

Also set initial calendarMonth to the current month or the first month with sessions:
```javascript
// After allSessions is populated, before renderCalendar():
if (allSessions.length > 0) {
    var today = new Date().toISOString().substring(0, 7);
    var months = allSessions.map(function(s) { return s.date ? s.date.substring(0, 7) : ''; }).filter(Boolean);
    if (months.indexOf(today) !== -1) {
        calendarMonth = today;
    } else {
        // Find nearest month to today
        months.sort();
        calendarMonth = months[months.length - 1]; // default to last month with sessions
        for (var i = 0; i < months.length; i++) {
            if (months[i] >= today) { calendarMonth = months[i]; break; }
        }
    }
}
```

**CSS (ydcoza-styles.css):** Append at end:

```css
/* === Attendance Monthly Calendar (WEC-182) === */
.att-cal-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 3px;
    table-layout: fixed;
}
.att-cal-table th {
    text-align: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--phoenix-tertiary-color, #6c757d);
    padding: 4px 2px;
    text-transform: uppercase;
}
.att-cal-table td {
    text-align: center;
    vertical-align: middle;
    border-radius: 6px;
    padding: 6px 2px;
    min-height: 40px;
    position: relative;
}
.att-cal-daynum { font-size: 0.78rem; font-weight: 500; display: block; }
.att-cal-count { font-size: 0.6rem; opacity: 0.7; display: block; }

/* Empty / no-class cells */
.att-cal-empty { background: transparent; }
.att-cal-noclass { background: transparent; color: #ccc; }
.att-cal-noclass .att-cal-daynum { font-weight: 400; }

/* Status colours — same palette as table badges */
.att-cal-day { border: 1px solid var(--phoenix-border-color, #dee2e6); }
.att-cal-captured { background: #d1e7dd; border-color: #a3cfbb; color: #0f5132; }
.att-cal-pending { background: #f8d7da; border-color: #f1aeb5; color: #842029; }
.att-cal-exception { background: #fff3cd; border-color: #ffe69c; color: #664d03; }
.att-cal-blocked { background: #e9ecef; border-color: #ced4da; color: #6c757d; opacity: 0.65; }
.att-cal-future { background: #f8f9fa; border-color: #dee2e6; color: #6c757d; }

/* Today marker */
.att-cal-today { box-shadow: inset 0 0 0 2px var(--phoenix-primary, #3874ff); }

/* Clickable */
.att-cal-clickable { cursor: pointer; transition: box-shadow 0.15s, transform 0.15s; }
.att-cal-clickable:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.15); transform: translateY(-1px); }

/* Legend dots */
.att-cal-legend {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin-right: 3px;
    vertical-align: middle;
}
.att-cal-captured.att-cal-legend, .att-cal-legend.att-cal-captured { background: #d1e7dd; border: 1px solid #a3cfbb; }
.att-cal-pending.att-cal-legend, .att-cal-legend.att-cal-pending { background: #f8d7da; border: 1px solid #f1aeb5; }
.att-cal-exception.att-cal-legend, .att-cal-legend.att-cal-exception { background: #fff3cd; border: 1px solid #ffe69c; }
.att-cal-blocked.att-cal-legend, .att-cal-legend.att-cal-blocked { background: #e9ecef; border: 1px solid #ced4da; }
.att-cal-future.att-cal-legend, .att-cal-legend.att-cal-future { background: #f8f9fa; border: 1px solid #dee2e6; }
```

IMPORTANT:
- Do NOT modify any existing functions. The calendar is purely additive.
- The month filter table and all modals remain unchanged.
- Sync calendar <-> month select so they stay in sync.
  </action>
  <verify>
    <automated>cd /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core && grep -c "att-calendar-grid" views/classes/components/single-class/attendance.php && grep -c "renderCalendar" assets/js/classes/attendance-capture.js && grep -c "att-cal-table" /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css</automated>
  </verify>
  <done>
    - Calendar HTML container exists in attendance.php between summary cards and month filter
    - renderCalendar() function renders 7-column month grid from allSessions
    - Multiple sessions per day supported (shows count badge)
    - Click handler routes to capture/detail modal based on status
    - Prev/next month navigation syncs with month filter select
    - CSS appended to ydcoza-styles.css with status colours matching existing badges
    - All existing functionality unchanged
  </done>
</task>

</tasks>

<success_criteria>
- Monthly calendar grid visible between summary cards and month filter
- 7-column week layout (Mon-Sun) with month/year title and prev/next arrows
- Class session days color-coded: green=captured, red=missed, yellow=exception, grey=blocked, light=future
- Non-class days show faded day numbers
- Multiple sessions per day show session count
- Blocked days have tooltip with reason
- Click pending/missed day → capture modal
- Click captured day → detail modal
- Prev/next arrows navigate months, synced with month filter dropdown
- Today highlighted with blue border
- Existing table view and modals unchanged
</success_criteria>

<output>
After completion, create `.planning/quick/21-wec-182-horizontal-scrollable-calendar-t/21-SUMMARY.md`
</output>
