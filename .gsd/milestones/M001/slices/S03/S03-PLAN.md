# S03: Exam Progress UI & AJAX

**Goal:** Office staff can record mock exam percentages, SBA marks + scans, and final exam marks + certificates through the learner progression UI. The UI conditionally shows exam flow vs POE flow based on `exam_class` status.
**Demo:** Navigate to an exam-class learner's progression tab → see 5-step exam progress card instead of POE controls → record a mock percentage → upload SBA scan → verify step cards update in-place. Navigate to a non-exam learner → see original POE flow unchanged.

## Must-Haves

- AJAX endpoint `record_exam_result` accepts percentage + optional file, delegates to ExamService
- AJAX endpoint `get_exam_progress` returns structured progress for a tracking_id
- AJAX endpoint `delete_exam_result` removes a step result (for re-recording)
- `baseQuery()` in LearnerProgressionRepository includes `exam_class` column
- `getCurrentLPDetails()` returns `is_exam_class` flag and `exam_progress` data when applicable
- Exam progress component renders 5-step cards with step labels, percentages, badges, file upload for SBA/final
- Conditional rendering: exam-class learners see exam UI, non-exam learners see original POE UI
- JavaScript handles FormData file uploads with progress bar for SBA/certificate steps
- All AJAX calls use existing `learnerSingleAjax` nonce (`learners_nonce`)
- New AJAX handler file loaded via `require_once` in `wecoza-core.php`
- New JS file enqueued in learner single display shortcode
- CSS appended to `ydcoza-styles.css`

## Proof Level

- This slice proves: integration
- Real runtime required: yes (WordPress AJAX + PostgreSQL)
- Human/UAT required: yes (browser verification of conditional UI + file uploads)

## Verification

- `php tests/exam/verify-exam-ajax.php` — verification script testing AJAX handler registration, nonce validation, ExamService integration, and response format
- Manual browser verification: exam-class learner shows exam progress card; non-exam learner shows POE flow
- `grep -c 'wp_ajax_record_exam_result\|wp_ajax_get_exam_progress\|wp_ajax_delete_exam_result' src/Learners/Ajax/ExamAjaxHandlers.php` returns 6 (3 actions × 2 for logged-in users)
- JS file exists and is enqueued: `grep 'learner-exam-progress' src/Learners/Shortcodes/learner-single-display-shortcode.php`

## Observability / Diagnostics

- Runtime signals: `error_log("WeCoza ExamAjax: ...")` with action name, tracking_id, step, and error context on all caught exceptions; `wp_send_json_error` responses include specific error messages
- Inspection surfaces: Browser dev tools Network tab shows AJAX request/response for all exam endpoints; `ExamService::getExamProgress()` returns structured data inspectable via `get_exam_progress` AJAX call
- Failure visibility: AJAX error responses contain `['success' => false, 'data' => ['message' => 'specific error']]`; PHP error log entries match `"WeCoza ExamAjax:"` pattern
- Redaction constraints: No learner PII in error logs — only tracking_id and step identifiers

## Integration Closure

- Upstream surfaces consumed: `ExamService` (S01), `ExamRepository` (S01), `ExamStep` enum (S01), `ExamUploadService` (S01), `verify_learner_access()` from ProgressionAjaxHandlers, `learnerSingleAjax` localized JS object
- New wiring introduced in this slice: ExamAjaxHandlers registered in wecoza-core.php, exam progress JS enqueued in shortcode, `is_exam_class` added to progression data flow, conditional UI branch in progression view
- What remains before the milestone is truly usable end-to-end: S04 — full end-to-end browser walkthrough, exam LP completion trigger (markLPComplete integration), edge case handling (partial progress, re-recording)

## Tasks

- [x] **T01: Wire exam_class into progression data layer and create AJAX handlers** `est:45m`
  - Why: The progression data layer needs `is_exam_class` to enable conditional UI, and AJAX endpoints are the backbone for all exam UI interactions
  - Files: `src/Learners/Repositories/LearnerProgressionRepository.php`, `src/Learners/Services/ProgressionService.php`, `src/Learners/Ajax/ExamAjaxHandlers.php`, `wecoza-core.php`, `tests/exam/verify-exam-ajax.php`
  - Do: Add `COALESCE(c.exam_class, 'No') AS exam_class` to `baseQuery()` SELECT. Extend `getCurrentLPDetails()` to include `is_exam_class` (bool) and `exam_progress` (from ExamService when exam class). Create `ExamAjaxHandlers.php` with 3 endpoints following ProgressionAjaxHandlers pattern. Register in wecoza-core.php. Create verification script.
  - Verify: `php tests/exam/verify-exam-ajax.php` passes; `grep 'exam_class' src/Learners/Repositories/LearnerProgressionRepository.php` shows the column in baseQuery
  - Done when: All 3 AJAX actions registered, getCurrentLPDetails returns is_exam_class, verification script passes

- [x] **T02: Build exam progress PHP view component with conditional rendering** `est:40m`
  - Why: The UI component renders the 5-step exam progress card and the conditional branch replaces POE controls for exam-class learners
  - Files: `views/learners/components/learner-exam-progress.php`, `views/learners/components/learner-progressions.php`, `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`
  - Do: Create exam progress component showing 5 ExamStep cards (label, badge, percentage input, file upload for sba/final). Add conditional in learner-progressions.php: if `$currentLP['is_exam_class']` render exam component instead of Mark Complete + Portfolio Upload sections. Hours/progress card stays for both flows. Style exam cards in ydcoza-styles.css.
  - Verify: `php -l views/learners/components/learner-exam-progress.php` passes; grep confirms conditional block in progressions view; CSS file contains exam-specific styles
  - Done when: Exam progress component renders 5-step cards server-side; POE sections hidden for exam learners; non-exam learners see original POE flow

- [x] **T03: Create exam progress JavaScript and wire into shortcode** `est:40m`
  - Why: JavaScript handles the interactive exam recording — percentage input, file upload with progress bar, AJAX calls, and in-place step card updates
  - Files: `assets/js/learners/learner-exam-progress.js`, `src/Learners/Shortcodes/learner-single-display-shortcode.php`
  - Do: Create jQuery IIFE module following learner-progressions.js pattern. Handle: percentage submit per step, FormData file upload for SBA/final with progress bar, delete/re-record flow, refresh exam progress section after each action. All AJAX uses `learnerSingleAjax.ajaxurl` and `learnerSingleAjax.nonce`. Enqueue JS in shortcode with `wp_enqueue_script`.
  - Verify: `grep 'learner-exam-progress' src/Learners/Shortcodes/learner-single-display-shortcode.php` confirms enqueue; JS file passes basic syntax check
  - Done when: JS file enqueued on learner single display, handles all 3 AJAX actions with proper FormData for file uploads, updates UI in-place after each step

## Files Likely Touched

- `src/Learners/Repositories/LearnerProgressionRepository.php` — add exam_class to baseQuery SELECT
- `src/Learners/Services/ProgressionService.php` — extend getCurrentLPDetails with is_exam_class + exam_progress
- `src/Learners/Ajax/ExamAjaxHandlers.php` — new: 3 AJAX endpoints
- `wecoza-core.php` — require_once for ExamAjaxHandlers
- `views/learners/components/learner-exam-progress.php` — new: 5-step exam progress card
- `views/learners/components/learner-progressions.php` — conditional rendering branch
- `assets/js/learners/learner-exam-progress.js` — new: jQuery AJAX module for exam steps
- `src/Learners/Shortcodes/learner-single-display-shortcode.php` — enqueue exam JS
- `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css` — exam card styles
- `tests/exam/verify-exam-ajax.php` — new: AJAX verification script
