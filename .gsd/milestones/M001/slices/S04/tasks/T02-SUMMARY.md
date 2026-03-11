---
id: T02
parent: S04
milestone: M001
provides:
  - Edge case coverage for exam LP completion (partial progress, certificate requirement, delete/re-record, defensive error handling)
  - lp_error key in AJAX response when LP completion fails after successful exam record
key_files:
  - tests/exam/verify-exam-completion.php
  - src/Learners/Ajax/ExamAjaxHandlers.php
key_decisions:
  - lp_error only included in response when non-null to keep clean success responses
patterns_established:
  - Defensive AJAX response pattern: success response includes error details for optional subsystem failures (lp_error for LP completion failure)
observability_surfaces:
  - lp_error key in record_exam_result AJAX response when LP marking fails
  - PHP error log "WeCoza ExamAjax: markComplete failed" with exception message
duration: 15min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T02: Edge case hardening and regression verification

**Extended completion test to 52 checks covering partial progress, certificate requirement, delete/re-record cycle, and defensive error handling; added lp_error to AJAX response; all 5 test suites pass (223 total checks).**

## What Happened

1. Added `lp_error` field to `handle_record_exam_result()` response — when `markComplete()` throws, the exception message is captured in `$lpError` and included in the success response alongside `lp_completed: false`. The field is only added when non-null to keep clean responses on normal success.

2. Extended `tests/exam/verify-exam-completion.php` from 30 checks to 52 checks with 4 new sections:
   - **Section 6 (Partial progress):** Verifies `isExamComplete()` iterates all 5 ExamStep cases, returns false on any null step, and indexes by step value
   - **Section 7 (Certificate requirement):** Verifies final step must have non-empty `file_path`, check is after the all-steps loop, and uses `file_path` not `file_name`
   - **Section 8 (Delete/re-record):** Verifies `deleteExamResult()` exists with correct signature, delegates to repository, returns success/error array; handler registered; `recordExamResult` uses upsert for re-record
   - **Section 9 (Defensive checks):** Verifies `lp_error` capture, conditional inclusion in response, tracking_id/exam_step validation, outer try/catch

3. Ran all 5 test suites — 223 total checks, 0 failures.

## Verification

All 5 test suites pass:
- `php tests/exam/verify-exam-schema.php` — 20/20 passed
- `php tests/exam/verify-exam-service.php` — 46/46 passed
- `php tests/exam/verify-exam-task-integration.php` — 83/83 passed
- `php tests/exam/verify-exam-ajax.php` — 22/22 passed
- `php tests/exam/verify-exam-completion.php` — 52/52 passed

## Diagnostics

- Run `php tests/exam/verify-exam-completion.php` for automated edge case verification
- Check AJAX response for `lp_error` key when LP completion fails after successful exam record
- Grep PHP error log for "markComplete failed for tracking_id=" to see LP completion failures

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/Learners/Ajax/ExamAjaxHandlers.php` — Added `$lpError` capture and conditional `lp_error` key in success response
- `tests/exam/verify-exam-completion.php` — Extended from 30 to 52 checks with 4 new edge case sections
