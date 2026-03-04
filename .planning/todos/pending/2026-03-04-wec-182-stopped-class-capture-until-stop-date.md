---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [1e] Attendance: allow capture on stopped classes until stop date"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
status: ready
files:
  - views/classes/components/single-class/attendance.php
  - src/Classes/Ajax/AttendanceAjaxHandlers.php
  - src/Classes/Controllers/ClassController.php
---

## Clarification from Mario (2026-03-04)

"It will basically be the last session available until the stop date. The stop date will always be on a class day, so that will be the last day for capturing."

## Solution

Stop date is always on a class day — use it directly as the last capturable session.

- Change `attendance.php` lock logic: if stopped, don't return early — pass stop date to JS
- Change `AttendanceAjaxHandlers::require_active_class()`: allow writes for stopped classes if session_date <= stop_date
- JS: disable capture buttons for dates after stop date
- Source: `stop_restart_dates` JSON in `schedule_data` — use the last stop entry with no corresponding restart
