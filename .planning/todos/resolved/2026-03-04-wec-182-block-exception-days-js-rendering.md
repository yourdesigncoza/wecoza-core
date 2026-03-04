---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [1d] Attendance: grey out blocked exception days"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
status: resolved
resolved: 2026-03-04
resolved_by: quick-16 (commit 962608b)
files:
  - assets/js/classes/attendance-capture.js
  - src/Classes/Services/AttendanceService.php
---

## Clarification from Mario (2026-03-04)

"Greyed out - not capturable will be good."

**Meeting follow-up (2026-03-04):** Mario confirmed action buttons must be completely REMOVED (not just disabled) for blocked sessions. Reason text should be visible inline or on hover.

## Solution

Backend prep is DONE — `AttendanceService::generateSessionList()` already returns `is_blocked` and `block_reason` fields (commit 826a538).

JS changes in `renderSessionTable()`:
- Blocked sessions: muted/greyed row styling
- Show "Blocked" badge with reason text (inline or hover tooltip)
- Completely remove Capture AND Exception action buttons (not just disable)
- Update summary stats to exclude blocked sessions from pending count
