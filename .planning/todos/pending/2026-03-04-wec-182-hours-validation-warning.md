---
created: 2026-03-04T14:16:00.000Z
title: "WEC-182 [NEW] Attendance: warning when hours exceed scheduled"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
status: ready
priority: 4
files:
  - assets/js/classes/attendance-capture.js
---

## From Mario meeting (2026-03-04)

Show a visual warning if `hours_present` entered exceeds scheduled hours for that session. Not a hard block — just a warning so agents double-check.

## Current state

Input field already has `max` attribute set to scheduled hours. Need to add:
- `input` event listener on hours field
- If value > max → show warning text/icon (amber) next to input
- Allow submission regardless (soft warning only)

## Scope

Small change — can bundle with other attendance JS work.
