---
estimated_steps: 4
estimated_files: 3
---

# T02: Edge case hardening and regression verification

**Slice:** S04 — Integration Testing & Polish
**Milestone:** M001

## Description

Extend the integration test with edge case coverage and run all 5 test suites to confirm zero regressions. The edge cases identified in research: partial progress (4 of 5 steps) must NOT trigger completion, delete/re-record cycle must work correctly, `isExamComplete()` must require certificate file_path on final step, and non-exam learners must be completely unaffected.

## Steps

1. **Extend `tests/exam/verify-exam-completion.php`** with additional edge case sections:
   - Section 5: Partial progress — verify `isExamComplete()` returns false with only 4 of 5 steps recorded (mock_1 through sba present, final missing)
   - Section 6: Certificate requirement — verify `isExamComplete()` returns false when final step has percentage but no file_path
   - Section 7: Delete/re-record cycle — verify `ExamService::deleteExamResult()` clears a step, `isExamComplete()` then returns false, re-recording restores it
   - Section 8: Handler defensive checks — verify handler gracefully handles edge cases (invalid tracking_id format, isExamComplete throwing exception doesn't break the record response)

2. **Review `handle_record_exam_result()` for defensive gaps** — Ensure the LP completion block is wrapped in try/catch so a failure in `markComplete()` doesn't break the already-successful exam record response. If the record succeeded but LP completion fails, return success with `lp_completed: false` and `lp_error` message.

3. **Run all 5 test suites** in sequence and confirm all pass:
   - `php tests/exam/verify-exam-schema.php`
   - `php tests/exam/verify-exam-service.php`
   - `php tests/exam/verify-exam-task-integration.php`
   - `php tests/exam/verify-exam-ajax.php`
   - `php tests/exam/verify-exam-completion.php`

4. **Fix any issues found** during test writing or regression runs

## Must-Haves

- [ ] Partial progress (4/5 steps) does NOT trigger LP completion — verified in test
- [ ] Missing certificate on final step does NOT trigger LP completion — verified in test
- [ ] Delete/re-record cycle verified in test
- [ ] LP completion failure doesn't break successful exam record response
- [ ] All 5 test suites pass with zero failures

## Verification

- `php tests/exam/verify-exam-completion.php` — all checks pass including new edge case sections
- `php tests/exam/verify-exam-schema.php` — passes (regression)
- `php tests/exam/verify-exam-service.php` — passes (regression)
- `php tests/exam/verify-exam-task-integration.php` — passes (regression)
- `php tests/exam/verify-exam-ajax.php` — passes (regression)

## Observability Impact

- Signals added: `lp_error` key in AJAX response when LP completion fails after successful record (defensive)
- How a future agent inspects this: Run all 5 test suites; grep error log for "WeCoza ExamAjax:" messages
- Failure state exposed: LP completion failure isolated from exam record success — user sees "result recorded" even if LP marking fails, with error logged server-side

## Inputs

- `tests/exam/verify-exam-completion.php` — base test from T01
- `src/Learners/Ajax/ExamAjaxHandlers.php` — modified in T01 with completion trigger
- `src/Learners/Services/ExamService.php` — `isExamComplete()`, `deleteExamResult()` from S01/S03
- All 4 prior test files — for regression verification

## Expected Output

- `tests/exam/verify-exam-completion.php` — extended with edge case sections (8+ sections total)
- `src/Learners/Ajax/ExamAjaxHandlers.php` — potentially modified with defensive try/catch around LP completion block
- All 5 test suites passing with zero failures
