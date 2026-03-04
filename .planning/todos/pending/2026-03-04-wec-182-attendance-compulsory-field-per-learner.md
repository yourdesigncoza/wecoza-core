---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [1b] Attendance: compulsory field per learner"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
blocked_by: mario-clarification
files:
  - assets/js/classes/attendance-capture.js
  - src/Classes/Ajax/AttendanceAjaxHandlers.php
---

## Problem

Mario requested: "There must also be a compulsory field in which each learner must be captured."

Unclear what this means. Two interpretations:
1. **Mandatory hours** — agent cannot submit until every learner has hours > 0 (no zeros allowed)
2. **Attendance status dropdown** — per-learner status field (Present / Absent / Late) that must be selected

Asked Mario to clarify on Linear WEC-182.

## Solution

TBD — depends on Mario's answer.

- If mandatory hours: add JS validation in `submitCapture()` to reject if any learner has 0 hours
- If attendance status: add a `<select>` per learner row in capture modal, require selection before submit, store in DB
