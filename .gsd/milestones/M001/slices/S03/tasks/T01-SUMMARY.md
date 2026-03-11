---
id: T01
parent: S03
milestone: M001
provides:
  - exam_class column in progression baseQuery SELECT
  - is_exam_class + exam_progress keys in getCurrentLPDetails return
  - 3 AJAX endpoints (record_exam_result, get_exam_progress, delete_exam_result)
  - ExamService::deleteExamResult() method
  - LearnerProgressionModel exam_class property and isExamClass() method
key_files:
  - src/Learners/Ajax/ExamAjaxHandlers.php
  - src/Learners/Repositories/LearnerProgressionRepository.php
  - src/Learners/Services/ProgressionService.php
  - src/Learners/Services/ExamService.php
  - src/Learners/Models/LearnerProgressionModel.php
  - wecoza-core.php
  - tests/exam/verify-exam-ajax.php
key_decisions:
  - 3 wp_ajax_ hooks (not 6) since only logged-in users access these endpoints
patterns_established:
  - ExamAjaxHandlers follows same namespace pattern as ProgressionAjaxHandlers (WeCoza\Learners\Ajax)
  - verify_learner_access('learners_nonce') reused from same namespace
  - ExamStep::tryFromString() used for all step validation in AJAX handlers
  - error_log("WeCoza ExamAjax: handler_name - ...") pattern for exam AJAX errors
observability_surfaces:
  - error_log("WeCoza ExamAjax: handle_*") on all caught exceptions
  - wp_send_json_error with specific error messages for all failure cases
  - get_exam_progress AJAX endpoint returns structured progress data inspectable via browser dev tools
duration: 15m
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T01: Wire exam_class into progression data layer and create AJAX handlers

**Added exam_class to progression data pipeline, created 3 AJAX endpoints for exam result CRUD, and added ExamService::deleteExamResult().**

## What Happened

1. Added `COALESCE(c.exam_class, 'No') AS exam_class` to `baseQuery()` SELECT in `LearnerProgressionRepository.php`. The `classes c` table was already JOINed.

2. Added `examClass` property, `getExamClass()` getter, and `isExamClass()` boolean helper to `LearnerProgressionModel`.

3. Extended `getCurrentLPDetails()` in `ProgressionService.php` to return `is_exam_class` (bool) for all learners, and `exam_progress` (from `ExamService::getExamProgress()`) when the learner is on an exam-track LP.

4. Added `deleteExamResult()` method to `ExamService` — delegates to `ExamRepository::deleteByTrackingAndStep()` with error handling.

5. Created `ExamAjaxHandlers.php` with 3 handlers: `handle_record_exam_result` (POST: tracking_id, exam_step, percentage, optional file), `handle_get_exam_progress` (GET: tracking_id), `handle_delete_exam_result` (POST: tracking_id, exam_step). All use `verify_learner_access('learners_nonce')` and `ExamStep::tryFromString()`.

6. Registered `ExamAjaxHandlers.php` in `wecoza-core.php` after the ProgressionAjaxHandlers include.

## Verification

- `php tests/exam/verify-exam-ajax.php` — 22/22 checks passed
- `grep 'exam_class'` confirms COALESCE line in baseQuery
- `grep 'is_exam_class'` confirms key in ProgressionService
- `grep 'ExamAjaxHandlers'` confirms require_once in wecoza-core.php
- `php -l` syntax check passed on all 5 modified/created PHP files

### Slice-level checks status (T01 is task 1 of 3):
- ✅ `php tests/exam/verify-exam-ajax.php` passes
- ⏳ Manual browser verification (T02/T03 — UI not built yet)
- ✅ `grep -c` for wp_ajax hooks returns 3 (correct — only wp_ajax_ needed, not wp_ajax_nopriv_)
- ⏳ JS file enqueued check (T03)

## Diagnostics

- Grep PHP error log for `"WeCoza ExamAjax:"` to find AJAX handler errors
- Call `get_exam_progress` AJAX endpoint with a tracking_id to verify data flow
- All AJAX error responses follow `['success' => false, 'data' => ['message' => 'specific error']]` format
- No learner PII in error logs — only tracking_id and step identifiers

## Deviations

- Added `ExamService::deleteExamResult()` method which wasn't in the original ExamService from S01. The task plan called for the AJAX handler to use it, but it didn't exist yet.
- Slice plan says wp_ajax hook count should be 6 (3×2). Actually 3 — only `wp_ajax_` hooks needed since the app requires login (no `wp_ajax_nopriv_` needed). Task plan correctly identified this.

## Known Issues

None.

## Files Created/Modified

- `src/Learners/Ajax/ExamAjaxHandlers.php` — new: 3 AJAX endpoints for exam result CRUD
- `src/Learners/Repositories/LearnerProgressionRepository.php` — modified: added exam_class to baseQuery SELECT
- `src/Learners/Services/ProgressionService.php` — modified: getCurrentLPDetails returns is_exam_class + exam_progress
- `src/Learners/Services/ExamService.php` — modified: added deleteExamResult() method
- `src/Learners/Models/LearnerProgressionModel.php` — modified: added examClass property, getExamClass(), isExamClass()
- `wecoza-core.php` — modified: require_once for ExamAjaxHandlers.php
- `tests/exam/verify-exam-ajax.php` — new: 22-check verification script
