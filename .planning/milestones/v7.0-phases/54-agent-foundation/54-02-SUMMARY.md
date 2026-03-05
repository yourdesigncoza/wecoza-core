---
phase: 54
plan: "02"
subsystem: Agents / Classes
tags: [security, authorization, agents, attendance, postgresql]
dependency_graph:
  requires: ["54-01"]
  provides: ["AGT-03", "AGT-04"]
  affects: ["src/Classes/Ajax/AttendanceAjaxHandlers.php", "src/Agents/Repositories/AgentRepository.php"]
tech_stack:
  added: []
  patterns: ["capability-check guard function", "nullable FK pattern", "repository lookup by FK"]
key_files:
  created:
    - .planning/phases/54-agent-foundation/54-02-DDL.sql
  modified:
    - src/Classes/Ajax/AttendanceAjaxHandlers.php
    - src/Agents/Repositories/AgentRepository.php
decisions:
  - verify_attendance_capability() uses procedural function pattern (not AjaxSecurity class) to stay consistent with verify_attendance_nonce() in same file
  - wp_user_id sanitization added to sanitizeAgentData() with 0-to-null guard (mirrors location_id pattern) to ensure the column value is correctly stored
  - Read-only AJAX handlers (get_sessions, get_detail) intentionally left unguarded so agents can view sessions without capture_attendance capability
metrics:
  duration_seconds: 78
  completed_date: "2026-03-04"
  tasks_completed: 3
  tasks_total: 3
  files_created: 1
  files_modified: 2
---

# Phase 54 Plan 02: AJAX Capability Guard + wp_user_id Data Layer Summary

**One-liner:** `capture_attendance` capability enforced on write AJAX handlers; `wp_user_id` column support added to AgentRepository with DDL for manual execution.

## Tasks Completed

| Task | Name | Commit | Status |
|------|------|--------|--------|
| 1 | Add AJAX capability guard to write handlers | 08bf35a | Complete |
| 2 | Add wp_user_id to AgentRepository + DDL file | 78eb73d | Complete |
| 3 | Confirm DDL has been executed | — | Complete (user confirmed) |

## What Was Built

### Task 1: AJAX Capability Guard (AGT-04)

Added `verify_attendance_capability()` function to `AttendanceAjaxHandlers.php` immediately after the existing `verify_attendance_nonce()` function. The new function checks `current_user_can('capture_attendance')` and sends a 403 JSON error on failure, following the identical pattern to the nonce check for consistency.

The function is called in:
- `handle_attendance_capture()` — line 151
- `handle_attendance_mark_exception()` — line 234

Read-only handlers (`handle_attendance_get_sessions`, `handle_attendance_get_detail`) are intentionally unguarded so agents can view their sessions without the capture capability.

### Task 2: wp_user_id Data Layer (AGT-03)

Three changes to `AgentRepository.php`:
1. `getAllowedInsertColumns()` — `wp_user_id` added after `location_id`, before `created_at`. `getAllowedUpdateColumns()` derives from insert columns automatically.
2. `findByWpUserId(int $wpUserId): ?array` — new public method querying `agents WHERE wp_user_id = :wp_user_id AND status <> 'deleted'`. Enables Phase 55 agent-class lookup by WordPress user ID.
3. `sanitizeAgentData()` — `wp_user_id` mapped to `absint` with 0-to-null guard (mirrors `location_id` pattern).

DDL file created at `.planning/phases/54-agent-foundation/54-02-DDL.sql` for manual execution by user.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical Functionality] Added wp_user_id to sanitizeAgentData()**
- **Found during:** Task 2
- **Issue:** `wp_user_id` was added to the column whitelist but not to `sanitizeAgentData()`, meaning any attempt to save the value would silently fail (the data sanitizer would drop the key before it reached the insert/update path)
- **Fix:** Added `'wp_user_id' => 'absint'` to the sanitization map plus a 0-to-null guard matching the `location_id` pattern
- **Files modified:** `src/Agents/Repositories/AgentRepository.php`
- **Commit:** 78eb73d

## DDL Execution

Task 3 (human-verify checkpoint) confirmed complete. User ran `.planning/phases/54-agent-foundation/54-02-DDL.sql` against the PostgreSQL database and verified `wp_user_id` exists as an integer column on the `agents` table. Phase 55 can now be executed.

## Self-Check

- [x] src/Classes/Ajax/AttendanceAjaxHandlers.php — modified, committed (08bf35a)
- [x] src/Agents/Repositories/AgentRepository.php — modified, committed (78eb73d)
- [x] .planning/phases/54-agent-foundation/54-02-DDL.sql — created, committed (78eb73d)
- [x] PHP syntax: no errors in both modified files

## Self-Check: PASSED
