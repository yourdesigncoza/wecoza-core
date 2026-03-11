# S04: Integration Testing & Polish

**Goal:** Full exam workflow is connected end-to-end — recording all 5 exam steps auto-completes the LP, edge cases are handled defensively, and the entire flow is verified in-browser.
**Demo:** Record mock 1→2→3 → SBA (with upload) → final (with certificate) for an exam-class learner. LP status changes to "completed" automatically. Re-recording a step works. Non-exam learners are unaffected.

## Must-Haves

- `handle_record_exam_result()` checks `isExamComplete()` after each successful record and auto-triggers LP completion via `LearnerProgressionModel::markComplete()` (not `markLPComplete()` — avoids portfolio requirement)
- Double-completion guard: skip `markComplete()` if progression is already completed
- AJAX response includes `lp_completed: true` flag when LP completion fires
- JS handles `lp_completed` response — shows completion message and refreshes progression card
- Comprehensive integration test covering: LP completion trigger, double-completion guard, partial progress, delete/re-record, `isExamComplete()` certificate requirement
- Browser verification of the full 5-step exam flow on a real exam-class learner

## Proof Level

- This slice proves: final-assembly
- Real runtime required: yes (browser against live WordPress)
- Human/UAT required: no (agent browser verification sufficient for technical sign-off; stakeholder walkthrough may follow)

## Verification

- `php tests/exam/verify-exam-completion.php` — integration test covering LP completion trigger, double-completion guard, partial progress correctness, delete/re-record, certificate requirement on `isExamComplete()`
- All 4 prior test suites still pass: `php tests/exam/verify-exam-schema.php`, `php tests/exam/verify-exam-service.php`, `php tests/exam/verify-exam-task-integration.php`, `php tests/exam/verify-exam-ajax.php`
- Browser: navigate to exam-class learner → verify exam progress UI renders → record mock results → upload SBA → upload certificate → verify LP completion message → verify progression status shows "completed"
- Browser: navigate to non-exam learner → verify POE flow renders (no exam UI)

## Observability / Diagnostics

- Runtime signals: `error_log("WeCoza ExamAjax: LP auto-completed for tracking_id={$trackingId}")` on successful exam LP completion; `error_log("WeCoza ExamAjax: LP already completed for tracking_id={$trackingId}, skipping")` on double-completion
- Inspection surfaces: AJAX response `lp_completed: true/false` key in `record_exam_result` response; `php tests/exam/verify-exam-completion.php` for automated verification
- Failure visibility: Exception messages in PHP error log with tracking_id context; JS console.error on AJAX failures
- Redaction constraints: none (no secrets in exam data)

## Integration Closure

- Upstream surfaces consumed: `ExamService::isExamComplete()` (S01), `ExamService::recordExamResult()` (S01), `LearnerProgressionModel::getById()` / `::markComplete()` (existing), `ExamAjaxHandlers::handle_record_exam_result()` (S03), `learner-exam-progress.js` (S03), `learner-progressions.php` (S03)
- New wiring introduced in this slice: LP auto-completion trigger in AJAX handler (PHP→ExamService→Model), `lp_completed` flag in AJAX response (PHP→JS), JS completion UI feedback (JS→DOM), progression card refresh on LP completion (JS)
- What remains before the milestone is truly usable end-to-end: nothing — this slice closes the milestone

## Tasks

- [x] **T01: Wire exam LP completion trigger and create integration test** `est:30m`
  - Why: The critical missing piece — recording all 5 exam steps currently doesn't auto-complete the LP. This wires `isExamComplete()` into the AJAX handler and adds `lp_completed` to the response. Also creates the integration test that verifies the logic.
  - Files: `src/Learners/Ajax/ExamAjaxHandlers.php`, `assets/js/learners/learner-exam-progress.js`, `tests/exam/verify-exam-completion.php`
  - Do: (1) After successful `recordExamResult()` in `handle_record_exam_result()`, call `$service->isExamComplete($trackingId)`. If true, load `LearnerProgressionModel::getById($trackingId)`, check `!$model->isCompleted()`, then call `$model->markComplete(get_current_user_id())`. Add `lp_completed` flag to AJAX response. Log completion/skip. (2) In JS `handleRecordSubmit` success handler, check `response.data.lp_completed` — if true, show Bootstrap success alert and call `refreshProgressionData()` if available. (3) Create `tests/exam/verify-exam-completion.php` testing: ExamAjaxHandlers has the completion logic wired, ExamService::isExamComplete() behavior with partial/full results, LearnerProgressionModel::markComplete() accepts null portfolio, double-completion guard logic.
  - Verify: `php tests/exam/verify-exam-completion.php` passes all checks; `php tests/exam/verify-exam-ajax.php` still passes (regression)
  - Done when: AJAX handler calls isExamComplete after recording, returns lp_completed flag, JS shows completion UI, integration test passes

- [x] **T02: Edge case hardening and regression verification** `est:25m`
  - Why: Ensures robustness — partial progress, delete/re-record cycle, certificate requirement, and non-exam learner isolation all need explicit verification beyond the happy path.
  - Files: `tests/exam/verify-exam-completion.php`, `src/Learners/Ajax/ExamAjaxHandlers.php`
  - Do: (1) Extend `verify-exam-completion.php` with edge case sections: partial progress (4 of 5 steps) does NOT trigger completion, delete a step reverts `isExamComplete()` to false, final step without file_path does NOT satisfy `isExamComplete()`, re-recording after delete works. (2) Verify all 4 existing test suites still pass (regression). (3) Review and fix any defensive gaps found during test writing (e.g., null checks, error handling).
  - Verify: `php tests/exam/verify-exam-completion.php` passes including new edge case sections; all 4 prior test suites pass
  - Done when: Edge cases verified in tests, all existing tests still pass, no defensive gaps found

- [x] **T03: Browser end-to-end verification** `est:30m`
  - Why: Final proof — the full exam workflow must be exercised in a real browser against the live WordPress installation. This is the milestone's definition of done.
  - Files: (no code changes expected — verification only)
  - Do: (1) Query DB for an exam-class learner with an active LP. (2) Navigate to that learner's single display page. (3) Verify exam progress UI renders with 5 step cards. (4) Verify non-exam learner shows POE flow (no exam cards). (5) If write access allows: exercise record/delete flow in browser. If not: verify UI structure, AJAX endpoint registration, and JS module loading. (6) Document any issues found as follow-ups or fix inline.
  - Verify: Browser assertions confirm exam UI renders for exam-class learner, POE UI renders for non-exam learner, JS module loads without errors, AJAX endpoints are registered
  - Done when: Browser verification complete with screenshots/assertions proving the UI works correctly for both exam and non-exam learners

## Files Likely Touched

- `src/Learners/Ajax/ExamAjaxHandlers.php`
- `assets/js/learners/learner-exam-progress.js`
- `tests/exam/verify-exam-completion.php`
