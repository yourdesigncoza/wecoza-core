---
estimated_steps: 6
estimated_files: 5
---

# T01: Wire exam_class into progression data layer and create AJAX handlers

**Slice:** S03 — Exam Progress UI & AJAX
**Milestone:** M001

## Description

This task does two things: (1) adds `exam_class` to the progression data pipeline so the UI knows whether a learner is on an exam-track LP, and (2) creates the AJAX handler file with 3 endpoints that the JS will call. Together, these form the backend plumbing that T02 and T03 depend on.

## Steps

1. **Add `exam_class` to `baseQuery()` in `LearnerProgressionRepository.php`** — Add `COALESCE(c.exam_class, 'No') AS exam_class` to the SELECT clause. The `classes c` table is already JOINed. Use COALESCE to handle NULL values safely.

2. **Extend `getCurrentLPDetails()` in `ProgressionService.php`** — After fetching the progression model, check if `exam_class === 'Yes'` and add `'is_exam_class' => bool` to the return array. When true, also fetch `ExamService::getExamProgress($trackingId)` and include as `'exam_progress'` key.

3. **Create `src/Learners/Ajax/ExamAjaxHandlers.php`** — New file in `WeCoza\Learners\Ajax` namespace. Three handler functions:
   - `handle_record_exam_result()` — POST: validates tracking_id, exam_step (via `ExamStep::tryFromString()`), percentage (0–100). Optionally handles `$_FILES['exam_file']`. Calls `ExamService::recordExamResult()`. Returns `wp_send_json_success/error`.
   - `handle_get_exam_progress()` — GET: validates tracking_id. Calls `ExamService::getExamProgress()`. Returns structured progress data.
   - `handle_delete_exam_result()` — POST: validates tracking_id + exam_step. Calls `ExamService::deleteExamResult()` (which delegates to ExamRepository::deleteByTrackingAndStep). Returns success/error.
   - All use `verify_learner_access('learners_nonce')` for auth/nonce.
   - Register 6 `add_action` calls (wp_ajax_ for each × 2 isn't needed — only logged-in users, so 3 wp_ajax_ hooks).

4. **Register handler file in `wecoza-core.php`** — Add `require_once` for `ExamAjaxHandlers.php` after the existing ProgressionAjaxHandlers include (around line 694), with matching comment block.

5. **Create `tests/exam/verify-exam-ajax.php`** — Verification script that:
   - Checks ExamAjaxHandlers.php file exists and has correct namespace
   - Checks all 3 wp_ajax action hooks are registered (via string grep or function_exists)
   - Verifies `getCurrentLPDetails` returns `is_exam_class` key
   - Verifies ExamService integration works (getExamProgress returns expected structure)
   - Tests input validation (invalid step name returns error, missing tracking_id returns error)

6. **Run verification** — Execute the verification script and fix any failures.

## Must-Haves

- [ ] `baseQuery()` SELECT includes `COALESCE(c.exam_class, 'No') AS exam_class`
- [ ] `getCurrentLPDetails()` returns `is_exam_class` (bool) and `exam_progress` (array when exam class)
- [ ] `ExamAjaxHandlers.php` has 3 handlers using `verify_learner_access('learners_nonce')`
- [ ] `ExamStep::tryFromString()` used for safe step validation (not `::from()`)
- [ ] `wecoza-core.php` loads ExamAjaxHandlers.php
- [ ] Verification script passes

## Verification

- `php tests/exam/verify-exam-ajax.php` — all checks pass
- `grep 'exam_class' src/Learners/Repositories/LearnerProgressionRepository.php` shows COALESCE line
- `grep 'is_exam_class' src/Learners/Services/ProgressionService.php` shows the new key
- `grep 'ExamAjaxHandlers' wecoza-core.php` shows require_once

## Observability Impact

- Signals added/changed: `error_log("WeCoza ExamAjax: handler_name - ...")` on all caught exceptions in AJAX handlers; consistent `wp_send_json_error(['message' => '...'])` format
- How a future agent inspects this: grep PHP error log for `"WeCoza ExamAjax:"`, or call `get_exam_progress` AJAX endpoint directly to verify data flow
- Failure state exposed: AJAX error responses include specific error messages (invalid step, missing tracking_id, service failure)

## Inputs

- `src/Learners/Ajax/ProgressionAjaxHandlers.php` — pattern template for AJAX handler structure, `verify_learner_access()` function
- `src/Learners/Services/ExamService.php` — S01 service with `recordExamResult()`, `getExamProgress()`, `deleteExamResult()` (via repository)
- `src/Learners/Enums/ExamStep.php` — `tryFromString()` for safe step validation
- `src/Learners/Repositories/LearnerProgressionRepository.php` — `baseQuery()` to extend
- `src/Learners/Services/ProgressionService.php` — `getCurrentLPDetails()` to extend

## Expected Output

- `src/Learners/Repositories/LearnerProgressionRepository.php` — modified: exam_class in baseQuery SELECT
- `src/Learners/Services/ProgressionService.php` — modified: getCurrentLPDetails returns is_exam_class + exam_progress
- `src/Learners/Ajax/ExamAjaxHandlers.php` — new: 3 AJAX endpoints with proper security
- `wecoza-core.php` — modified: require_once for ExamAjaxHandlers
- `tests/exam/verify-exam-ajax.php` — new: verification script
