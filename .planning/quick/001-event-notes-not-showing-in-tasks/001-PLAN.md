# Quick Task 001: Event Notes Not Showing in Open Tasks

## Problem
Notes captured in Event Dates section (e.g., "Notify Front Desc", "Peter Should Moderate") are not displaying in the Open Tasks view - only the placeholder "Note (optional)" appears.

## Root Cause
In `ClassTaskPresenter.php`, the `note` value was passed for **completed** tasks (line 412) but not for **open** tasks. The view template also lacked a `value` attribute on the input field.

## Solution
1. Add `$payload['note'] = $task->getNote();` for open tasks in presenter
2. Add `value="<?php echo esc_attr($task['note'] ?? ''); ?>"` to input in view

## Files
- `src/Events/Views/Presenters/ClassTaskPresenter.php`
- `views/events/event-tasks/main.php`
