---
estimated_steps: 5
estimated_files: 3
---

# T01: Wire exam LP completion trigger and create integration test

**Slice:** S04 — Integration Testing & Polish
**Milestone:** M001

## Description

The critical missing piece: `handle_record_exam_result()` in ExamAjaxHandlers.php records exam results but never checks if the LP should be auto-completed. This task wires `ExamService::isExamComplete()` into the handler, adds a double-completion guard, returns `lp_completed` in the AJAX response, updates the JS to show completion feedback, and creates the integration test that validates everything.

## Steps

1. **Modify `handle_record_exam_result()` in ExamAjaxHandlers.php** — After the successful `$service->recordExamResult()` call and before `wp_send_json_success()`:
   - Call `$service->isExamComplete($trackingId)`
   - If true, load `LearnerProgressionModel::getById($trackingId)`
   - Guard: if `$model->isCompleted()`, log skip and set `$lpCompleted = false` (already done)
   - Otherwise call `$model->markComplete(get_current_user_id())`, log success, set `$lpCompleted = true`
   - If isExamComplete is false, set `$lpCompleted = false`
   - Add `'lp_completed' => $lpCompleted` to the `wp_send_json_success` data array
   - Add `use WeCoza\Learners\Models\LearnerProgressionModel;` import at top of file

2. **Update JS `handleRecordSubmit` success handler** in `learner-exam-progress.js` — After the existing `self.refreshExamProgress(trackingId)` call:
   - Check `response.data.lp_completed === true`
   - If true, show a Bootstrap success alert at the top of the exam section: "🎓 Learning Programme completed! All exam steps have been recorded."
   - Attempt to call `refreshProgressionData()` if it exists in global scope (from learner-progression.js) to update the overall progression card status

3. **Create `tests/exam/verify-exam-completion.php`** — Integration test covering:
   - Section 1: AJAX handler structure — verify `isExamComplete` call exists in handler, verify `lp_completed` key in response format, verify `LearnerProgressionModel` import
   - Section 2: ExamService::isExamComplete behavior — all 5 steps present returns true, missing any step returns false, final step without file_path returns false
   - Section 3: LearnerProgressionModel::markComplete — accepts null portfolio path, sets status to 'completed', double-call guard (isCompleted check)
   - Section 4: JS module — verify lp_completed handling exists in JS file

4. **Run the new test** — `php tests/exam/verify-exam-completion.php` and fix any failures

5. **Run regression tests** — Verify `php tests/exam/verify-exam-ajax.php` still passes (22 checks)

## Must-Haves

- [ ] `handle_record_exam_result()` calls `isExamComplete()` after successful record
- [ ] Double-completion guard prevents marking already-completed LP
- [ ] `lp_completed` key present in AJAX success response
- [ ] JS shows completion alert when `lp_completed` is true
- [ ] Integration test passes with all checks green
- [ ] Existing exam AJAX test still passes (regression)

## Verification

- `php tests/exam/verify-exam-completion.php` — all checks pass
- `php tests/exam/verify-exam-ajax.php` — 22/22 checks still pass
- `grep 'isExamComplete' src/Learners/Ajax/ExamAjaxHandlers.php` returns match
- `grep 'lp_completed' src/Learners/Ajax/ExamAjaxHandlers.php` returns match
- `grep 'lp_completed' assets/js/learners/learner-exam-progress.js` returns match

## Observability Impact

- Signals added: `error_log("WeCoza ExamAjax: LP auto-completed for tracking_id={$trackingId}")` on successful LP completion; `error_log("WeCoza ExamAjax: LP already completed for tracking_id={$trackingId}, skipping")` on double-completion skip
- How a future agent inspects this: grep PHP error log for "LP auto-completed" or "LP already completed"; check AJAX response for `lp_completed` key; run `php tests/exam/verify-exam-completion.php`
- Failure state exposed: Exception in markComplete logged with tracking_id; `lp_completed: false` returned even if isExamComplete was true (on markComplete failure)

## Inputs

- `src/Learners/Ajax/ExamAjaxHandlers.php` — current handler with no completion logic (S03)
- `src/Learners/Services/ExamService.php` — `isExamComplete()` ready to use (S01)
- `src/Learners/Models/LearnerProgressionModel.php` — `getById()`, `isCompleted()`, `markComplete()` ready (existing)
- `assets/js/learners/learner-exam-progress.js` — `handleRecordSubmit` success handler refreshes exam section but doesn't check for LP completion (S03)
- `src/Learners/Ajax/ProgressionAjaxHandlers.php` line 441 — precedent for calling `markComplete()` directly without portfolio

## Expected Output

- `src/Learners/Ajax/ExamAjaxHandlers.php` — modified: LP completion trigger after recordExamResult, lp_completed in response
- `assets/js/learners/learner-exam-progress.js` — modified: completion alert on lp_completed, optional progression card refresh
- `tests/exam/verify-exam-completion.php` — new: integration test validating completion trigger, guard, response format, JS handling
