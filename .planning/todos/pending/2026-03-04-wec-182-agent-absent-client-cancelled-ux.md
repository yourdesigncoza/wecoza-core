---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [1c] Attendance: make exception button more visible + agent-restricted page"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
status: ready
files:
  - assets/js/classes/attendance-capture.js
  - views/classes/components/single-class/attendance.php
---

## Clarification from Mario (2026-03-04)

"I did not think that was a button; I just thought it was there to show as outstanding or something. So that's great that it is already there, I think we will have to make it more visible so that an agent will understand that it is an exception."

## Solution

**Visibility fix:** Restyle the exception triangle — make it a clearly labelled button (e.g., "Report Exception" or "Mark Exception") instead of just an icon.

**Agent-restricted page:** Separate todo created — see `2026-03-04-wec-182-agent-restricted-attendance-capture-page.md`
