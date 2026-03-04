---
created: 2026-03-04T14:16:00.000Z
title: "WEC-182 [NEW] Attendance: monthly summary totals row"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
status: ready
priority: 5
files:
  - assets/js/classes/attendance-capture.js
---

## From Mario meeting (2026-03-04)

At the bottom of each month's attendance data, add a summary/totals row:
- Total hours scheduled
- Total hours present
- Total hours absent
- Attendance percentage

## Scope

Small-medium change — add a `<tfoot>` row computed from session data already available in JS. Can bundle with other attendance JS work.
