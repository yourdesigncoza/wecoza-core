---
phase: 15-fix-backslash-escaping-in-feedback-dashb
plan: "01"
subsystem: Feedback
tags: [bug-fix, input-sanitization, wordpress, wp_unslash]
dependency_graph:
  requires: []
  provides: [clean-feedback-text-storage]
  affects: [FeedbackController, FeedbackDashboardShortcode]
tech_stack:
  added: []
  patterns: [wp_unslash before sanitization]
key_files:
  created: []
  modified:
    - src/Feedback/Controllers/FeedbackController.php
    - src/Feedback/Shortcodes/FeedbackDashboardShortcode.php
decisions:
  - Replace stripslashes() with wp_unslash() for url_params for consistency with WP best practices
metrics:
  duration: "5m"
  completed: "2026-03-03"
  tasks_completed: 1
  tasks_total: 1
  files_changed: 2
---

# Quick Task 15: Fix Backslash Escaping in Feedback Dashboard — Summary

**One-liner:** Applied `wp_unslash()` to all string `$_POST` inputs in the Feedback module before sanitization, preventing WordPress magic quotes from inserting visible backslashes into stored feedback text and comments.

## What Was Done

WordPress core calls `wp_magic_quotes()` at boot, which runs `addslashes()` recursively over `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, and `$_REQUEST`. Any text containing quotes (e.g. `status from "On Hold" to "Resume"`) arrived at the controller already escaped as `status from \"On Hold\" to \"Resume\"`. Without calling `wp_unslash()` before sanitization, this escaped form was passed to `wecoza_sanitize_value()` and ultimately stored in the database verbatim, then displayed with visible backslashes.

The fix wraps every string-type `$_POST` read with `wp_unslash()` before sanitization, at the earliest point of input capture.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add wp_unslash to all $_POST reads in Feedback module | 60f6638 | FeedbackController.php, FeedbackDashboardShortcode.php |

## Changes by File

### `src/Feedback/Controllers/FeedbackController.php`

`handleSubmit()` — 7 string fields + 1 url_params JSON field:
- `$category`, `$feedbackText`, `$pageUrl`, `$pageTitle`, `$shortcode`, `$browserInfo`, `$viewport`: all now `wecoza_sanitize_value(wp_unslash($_POST[...] ?? ''), 'string')`
- `url_params` JSON decode: replaced `stripslashes($_POST['url_params'])` with `wp_unslash($_POST['url_params'])` for consistency with WP standards

`handleFollowup()` — 1 string field:
- `$answer`: now `wecoza_sanitize_value(wp_unslash($_POST['answer'] ?? ''), 'string')`

Integer-cast fields (`feedback_id`, `round`, `skip`) and the base64 `screenshot` field were intentionally left unchanged — integers do not contain quotes, and base64 data contains no quotable characters.

### `src/Feedback/Shortcodes/FeedbackDashboardShortcode.php`

`handleComment()` — 1 string field:
- `$commentText`: now `sanitize_textarea_field(wp_unslash($_POST['comment_text'] ?? ''))`

## Verification Results

- `grep -c "wp_unslash" FeedbackController.php` → 10 (plan required 9+)
- `grep -c "wp_unslash" FeedbackDashboardShortcode.php` → 1 (plan required 1+)
- No unguarded string `$_POST` reads remain without `wp_unslash`
- PHP lint: no syntax errors in either file

## Deviations from Plan

None — plan executed exactly as written.

## Scope Note

This fix prevents backslashes in **new** feedback submissions and comments. Existing records in the database that were stored with backslashes are unaffected; those would require a one-time data migration (out of scope for this task).

## Self-Check

- [x] `src/Feedback/Controllers/FeedbackController.php` — modified (verified)
- [x] `src/Feedback/Shortcodes/FeedbackDashboardShortcode.php` — modified (verified)
- [x] Commit 60f6638 exists

## Self-Check: PASSED
