---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [1e] Attendance: stopped class capture until stop date"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
blocked_by: mario-clarification
files:
  - views/classes/components/single-class/attendance.php
  - src/Classes/Ajax/AttendanceAjaxHandlers.php
  - src/Classes/Controllers/ClassController.php
---

## Problem

Mario: "Stopped classes can still be captured until the actual stop date. Sometimes agents only capture afterwards, so if class stopped, he must still be able to capture, but not for days after the stop date."

Currently `class_status='stopped'` locks the entire attendance section (view returns early with lock alert, AJAX returns 403). Need to change to allow capture for dates <= stop date.

Asked Mario: Is there a specific "stop date" field, or should we use the last `stop_restart_dates` entry?

## Solution

TBD — need to determine stop date source.

- Change `attendance.php` lock logic: if stopped, don't return early — instead pass stop date to JS
- Change `AttendanceAjaxHandlers::require_active_class()`: allow writes for stopped classes if session date <= stop date
- JS: disable capture buttons only for dates after stop date
- `stop_restart_dates` JSON may have the answer, or there may be a separate `stopped_date` field
