---
created: 2026-03-04T14:16:00.000Z
title: "WEC-182 [NEW] Attendance: horizontal scrollable calendar timeline"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
status: ready
priority: 3
files:
  - assets/js/classes/attendance-capture.js
  - views/classes/components/single-class/attendance.php
---

## From Mario meeting (2026-03-04)

Replace or supplement the current month-filter table with a horizontal scrollable timeline (Gantt-chart style):

- One cell per scheduled day
- Class days highlighted, non-class days hidden/minimised
- Click a day → opens capture modal for that session
- Exception days greyed out with reason on hover
- Past days show status: captured (green) / not captured (red/amber)
- Future days show as upcoming
- Agents will use this as their primary attendance capture interface

## Scope

This is a significant UI feature — likely needs its own GSD milestone or at minimum a `--full` quick task. Consider:
- New JS component or major refactor of `renderSessionTable()`
- CSS for horizontal scroll, day cells, status colours
- Integration with existing session data from `generateSessionList()`
- Mobile responsiveness for scrollable timeline
- Phoenix theme alignment — check for existing timeline/Gantt patterns
