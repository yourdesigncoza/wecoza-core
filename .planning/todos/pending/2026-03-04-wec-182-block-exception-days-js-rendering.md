---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [1d] Attendance: block exception days JS rendering"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
blocked_by: mario-clarification
files:
  - assets/js/classes/attendance-capture.js
  - src/Classes/Services/AttendanceService.php
---

## Problem

Mario: "The exception days we created must be blocked, and should not be open to capture."

Backend prep is DONE — `AttendanceService::generateSessionList()` now returns `is_blocked` and `block_reason` fields for exception dates and public holidays (commit 826a538).

JS rendering not done yet. Asked Mario: should blocked days be greyed-out visible rows (showing the reason), or hidden completely?

## Solution

TBD — depends on Mario's preference for UX.

- If greyed-out: add conditional rendering in `renderSessionTable()` — muted row, "Blocked" badge, reason text, no action buttons
- If hidden: filter out `is_blocked` sessions before rendering
- Either way, update summary stats to exclude blocked from pending count
