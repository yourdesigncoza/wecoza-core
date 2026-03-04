---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [1c] Attendance: agent absent / client cancelled UX"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
blocked_by: mario-clarification
files:
  - assets/js/classes/attendance-capture.js
  - views/classes/components/single-class/attendance.php
---

## Problem

Mario: "There must be an option where an agent can also select that he was absent for that day for a class, or if the client cancelled directly with him."

This functionality already exists — the warning triangle (⚠) next to each pending session opens an exception modal with "Agent Absent" and "Client Cancelled" options.

Asked Mario: Are agents using the attendance page? Is the exception triangle not visible enough, or do they need a different access point?

## Solution

TBD — depends on Mario's answer.

- If visibility issue: make the exception button more prominent (larger, labelled instead of icon-only)
- If access issue: agents may need a simplified capture view or the exception option integrated into the capture modal itself
- If agents don't have page access: may need a separate agent-facing page/shortcode
