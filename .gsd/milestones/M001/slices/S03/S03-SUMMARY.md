---
id: S03
parent: M001
milestone: M001
provides:
  - 3 AJAX endpoints (record_exam_result, get_exam_progress, delete_exam_result) for exam result CRUD
  - exam_class column wired into progression data pipeline (baseQuery, model, service)
  - is_exam_class + exam_progress keys in getCurrentLPDetails return
  - 5-step exam progress PHP component with conditional rendering (exam vs POE)
  - jQuery IIFE module for exam recording with FormData file upload, progress bar, validation, and in-place refresh
  - CSS styles for exam step cards
requires:
  - slice: S01
    provides: ExamService, ExamRepository, ExamStep enum, ExamUploadService
affects:
  - S04
key_files:
  - src/Learners/Ajax/ExamAjaxHandlers.php
  - src/Learners/Repositories/LearnerProgressionRepository.php
  - src/Learners/Services/ProgressionService.php
  - src/Learners/Services/ExamService.php
  - src/Learners/Models/LearnerProgressionModel.php
  - views/learners/components/learner-exam-progress.php
  - views/learners/components/learner-progressions.php
  - assets/js/learners/learner-exam-progress.js
  - src/Learners/Shortcodes/learner-single-display-shortcode.php
  - wecoza-core.php
  - /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
  - tests/exam/verify-exam-ajax.php
key_decisions:
  - D011: Client-side exam card rendering from JSON using jQuery DOM methods for XSS safety
  - 3 wp_ajax_ hooks only (no nopriv) since the app requires login
  - get_exam_progress response enriched with recorded_by_name and file_url to support client-side rendering
  - ExamService::deleteExamResult() added (wasn't in S01 — needed for AJAX delete endpoint)
patterns_established:
  - ExamAjaxHandlers follows ProgressionAjaxHandlers namespace and pattern (WeCoza\Learners\Ajax)
  - verify_learner_access('learners_nonce') reused from same namespace for all exam AJAX
  - EXAM_STEPS JS constant mirrors ExamStep PHP enum (values, labels, requiresFile flags)
  - Conditional UI branch in learner-progressions.php using is_exam_class flag
  - Each exam-step-card has data-tracking-id and data-exam-step attributes for JS targeting
  - renderExamSection → renderExamStepCard → renderCompletedDetails/renderPendingForm JS decomposition
observability_surfaces:
  - error_log("WeCoza ExamAjax: handle_*") on all caught exceptions with tracking_id and step
  - wp_send_json_error with specific error messages for all failure cases
  - console.error on AJAX failures with status, error, and response text
  - console.warn on client-side validation failures (file type, file size)
  - User-visible inline Bootstrap alerts on all error states
  - Fallback "Unable to load exam progress" alert when exam_progress is null/empty
  - Each step card's completed/pending state visible in DOM via CSS classes
drill_down_paths:
  - .gsd/milestones/M001/slices/S03/tasks/T01-SUMMARY.md
  - .gsd/milestones/M001/slices/S03/tasks/T02-SUMMARY.md
  - .gsd/milestones/M001/slices/S03/tasks/T03-SUMMARY.md
duration: 45m
verification_result: passed
completed_at: 2026-03-11
---

# S03: Exam Progress UI & AJAX

**Office staff can record mock exam percentages, SBA marks + scans, and final exam marks + certificates through the learner progression UI, with conditional exam/POE flow rendering.**

## What Happened

Wired `exam_class` into the progression data pipeline (T01): added `COALESCE(c.exam_class, 'No') AS exam_class` to `LearnerProgressionRepository::baseQuery()`, extended `LearnerProgressionModel` with `examClass` property and `isExamClass()` helper, and extended `ProgressionService::getCurrentLPDetails()` to return `is_exam_class` (bool) and `exam_progress` (from ExamService) for exam-track learners.

Created 3 AJAX endpoints in `ExamAjaxHandlers.php` (T01): `record_exam_result` (POST with percentage + optional file), `get_exam_progress` (GET returning enriched step data with `recorded_by_name` and `file_url`), and `delete_exam_result` (POST). All handlers reuse `verify_learner_access('learners_nonce')` and validate steps via `ExamStep::tryFromString()`. Added `ExamService::deleteExamResult()` to support the delete endpoint.

Built the server-rendered exam progress component (T02): `learner-exam-progress.php` loops through `ExamStep::cases()` to render 5 step cards — each showing either completed details (percentage, date, recorded-by, file link) or a pending input form (percentage input, file input for SBA/final, submit button). Modified `learner-progressions.php` with conditional branch: exam learners see the exam component, non-exam learners see the original POE flow (Mark Complete + Portfolio Upload). Hours/progress card remains for both.

Created the jQuery IIFE module (T03): `learner-exam-progress.js` handles percentage submission, FormData file upload with progress bar, delete/re-record with confirm() dialog, and full in-place section refresh via client-side DOM construction from JSON. Uses jQuery DOM methods exclusively (no innerHTML) for XSS safety. Enqueued in the learner single display shortcode.

## Verification

- `php tests/exam/verify-exam-ajax.php` — 22/22 checks passed (AJAX handler registration, nonce validation, ExamService integration, response format, model properties)
- `grep -c 'wp_ajax_record_exam_result\|wp_ajax_get_exam_progress\|wp_ajax_delete_exam_result'` — returns 3 (correct: logged-in only)
- `grep 'learner-exam-progress' ...shortcode.php` — enqueue confirmed
- `php -l` syntax checks passed on all PHP files
- `node -c` syntax check passed on JS file
- CSS grep confirms 6 exam-specific rules in ydcoza-styles.css
- Conditional rendering branch confirmed in learner-progressions.php via grep

## Deviations

- Added `ExamService::deleteExamResult()` which wasn't in the original S01 ExamService. Needed for the delete AJAX endpoint.
- Enriched `handle_get_exam_progress` response in ExamAjaxHandlers to include `recorded_by_name` and `file_url`. Not in the original task plan but necessary for client-side rendering to match PHP component output.
- Slice plan stated wp_ajax hook grep should return 6 (3×2). Actually 3 — only `wp_ajax_` hooks needed since the app requires login (no `wp_ajax_nopriv_`). This was correct behavior.

## Known Limitations

- Browser visual verification deferred to S04 — component depends on real exam-class learner data in the database.
- File download links assume exam files are stored under wp-content directory; if ExamUploadService stores elsewhere, `content_url()` path stripping won't work. (Matches existing portfolio upload pattern.)
- No exam LP completion trigger yet — recording all 5 steps doesn't auto-complete the LP. This is S04 scope.

## Follow-ups

- S04: Full end-to-end browser walkthrough with real exam-class learner data
- S04: Exam LP completion trigger (markLPComplete integration when all 5 steps done)
- S04: Edge case handling — partial progress, re-recording marks, exam class with mixed learners

## Files Created/Modified

- `src/Learners/Ajax/ExamAjaxHandlers.php` — new: 3 AJAX endpoints for exam result CRUD; enriched get_exam_progress response
- `src/Learners/Repositories/LearnerProgressionRepository.php` — modified: added exam_class to baseQuery SELECT
- `src/Learners/Services/ProgressionService.php` — modified: getCurrentLPDetails returns is_exam_class + exam_progress
- `src/Learners/Services/ExamService.php` — modified: added deleteExamResult() method
- `src/Learners/Models/LearnerProgressionModel.php` — modified: added examClass property, getExamClass(), isExamClass()
- `views/learners/components/learner-exam-progress.php` — new: 5-step exam progress card component
- `views/learners/components/learner-progressions.php` — modified: conditional branch for exam vs POE flow
- `assets/js/learners/learner-exam-progress.js` — new: jQuery IIFE module for exam step recording
- `src/Learners/Shortcodes/learner-single-display-shortcode.php` — modified: enqueue exam JS
- `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css` — modified: appended exam step card CSS
- `wecoza-core.php` — modified: require_once for ExamAjaxHandlers.php
- `tests/exam/verify-exam-ajax.php` — new: 22-check verification script

## Forward Intelligence

### What the next slice should know
- The exam progress UI is fully built but untested in a real browser. S04 needs to exercise the full flow: navigate to an exam-class learner, record mocks, upload SBA, upload final certificate, and verify LP completion.
- `getCurrentLPDetails()` now returns `exam_progress` only when `is_exam_class` is true. If the learner's class doesn't have `exam_class = 'Yes'`, exam data won't appear even if exam results exist in the DB.
- The JS module uses `learnerSingleAjax.ajaxurl` and `learnerSingleAjax.nonce` — no separate localize_script needed.

### What's fragile
- The JS client-side renderer (`renderExamStepCard`) must exactly mirror the PHP component's HTML structure for CSS to work. If the PHP component changes, the JS renderer needs updating too.
- File URL resolution in both PHP (`content_url()`) and AJAX response (`content_url()`) assumes files under wp-content. If the upload path changes, both break.

### Authoritative diagnostics
- `php tests/exam/verify-exam-ajax.php` — 22 automated checks covering AJAX registration, service integration, model properties
- Browser dev tools Network tab for all 3 AJAX endpoints
- PHP error log: grep for `"WeCoza ExamAjax:"` for server-side errors
- Browser console: `console.error('Exam record AJAX error:', ...)` and `console.error('Exam delete AJAX error:', ...)` for client-side failures

### What assumptions changed
- Slice plan assumed 6 wp_ajax hooks (3×2 with nopriv). Only 3 needed since app requires login.
- ExamService::deleteExamResult() was assumed to exist from S01 but wasn't there — added in this slice.
