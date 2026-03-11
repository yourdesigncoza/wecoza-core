---
id: T03
parent: S03
milestone: M001
provides:
  - jQuery IIFE module for exam result CRUD with file upload, progress bar, validation, and in-place UI refresh
  - Script enqueued in learner-single-display shortcode via learnerSingleAjax
key_files:
  - assets/js/learners/learner-exam-progress.js
  - src/Learners/Shortcodes/learner-single-display-shortcode.php
  - src/Learners/Ajax/ExamAjaxHandlers.php
key_decisions:
  - Client-side card rendering from JSON (approach a) using jQuery DOM methods for XSS safety; mirrors PHP component output exactly
  - Enriched get_exam_progress AJAX response with recorded_by_name and file_url to support client-side rendering without extra endpoints
  - confirm() used for delete instead of Bootstrap modal — keeps it simple, no extra modal HTML needed
patterns_established:
  - EXAM_STEPS JS constant mirrors ExamStep PHP enum (step values, labels, requiresFile flags)
  - STEP_ORDER array ensures consistent rendering order
  - All AJAX calls use learnerSingleAjax.ajaxurl and learnerSingleAjax.nonce — no separate wp_localize_script needed
  - renderExamSection → renderExamStepCard → renderCompletedDetails/renderPendingForm decomposition for maintainable client-side rendering
observability_surfaces:
  - console.error on AJAX failures with status, error, and response text
  - console.warn on client-side validation failures (file type, file size)
  - User-visible inline alerts on all error states (step-level and section-level)
  - Form state preserved on failure — no data loss
duration: 15 min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T03: Create exam progress JavaScript and wire into shortcode

**Built jQuery IIFE module for all exam recording interactions (record/delete/refresh) with FormData file upload, progress bar, client-side validation, and in-place UI refresh; enqueued in learner single display shortcode.**

## What Happened

Created `assets/js/learners/learner-exam-progress.js` following the same IIFE pattern as `learner-progressions.js`. The module handles:

1. **Record exam result** — Form submit handler on `.exam-record-form`. Collects tracking_id, exam_step, percentage from form data attributes and inputs. For SBA/final steps, creates FormData with file and uses `processData: false, contentType: false`. For mock steps, still uses FormData for consistency. On success, calls `refreshExamProgress()`.

2. **Refresh exam progress** — `refreshExamProgress(trackingId)` fetches updated data via `get_exam_progress` AJAX and fully rebuilds the step cards from JSON using `renderExamSection()` → `renderExamStepCard()` decomposition. Uses jQuery DOM methods exclusively (no innerHTML) for XSS safety.

3. **Delete/re-record** — Event delegation on `.exam-delete-btn` with `confirm()` dialog. On success, refreshes section.

4. **File upload progress bar** — Uses `xhr.upload.onprogress` to update Bootstrap progress bar during file uploads.

5. **Validation** — Live validation on percentage input (0–100) and file input (accepted extensions + 10MB max). Submit button disabled until valid.

6. **Double-submit prevention** — Submit/delete buttons disabled during AJAX, re-enabled on error.

Enhanced `handle_get_exam_progress` in ExamAjaxHandlers to enrich step data with `recorded_by_name` (from `get_userdata()`) and `file_url` (from `content_url()`) so the JS client-side renderer has all data needed without extra AJAX calls.

Enqueued the script in `learner-single-display-shortcode.php` after `learner-progressions.js`. No separate `wp_localize_script` — reuses existing `learnerSingleAjax` object.

## Verification

- `node -c assets/js/learners/learner-exam-progress.js` — syntax OK
- `grep -c 'jQuery' ...` — 3 matches (IIFE pattern confirmed)
- `grep 'learner-exam-progress' ...shortcode.php` — enqueue present
- `grep -c 'record_exam_result|get_exam_progress|delete_exam_result' ...js` — 4 (all 3 actions present)
- `grep -c 'processData.*false|contentType.*false' ...js` — 2 (FormData pattern)
- `grep -c "prop('disabled', true)" ...js` — 2 (double-submit prevention)
- `php tests/exam/verify-exam-ajax.php` — 22/22 passed

Slice-level checks:
- ✅ AJAX handler registration: 3 wp_ajax_ hooks (logged-in only, per T01 decision)
- ✅ JS file enqueued in shortcode
- ✅ Verification script: 22/22 passed

## Diagnostics

- Browser dev tools Console: `console.error('Exam record AJAX error:', ...)` and `console.error('Exam delete AJAX error:', ...)` on failures
- Browser dev tools Console: `console.warn('Exam file validation failed:', ...)` on client-side validation rejection
- Browser dev tools Network: all 3 AJAX endpoints visible with request/response data
- User sees inline alerts (Bootstrap dismissible) on any error — form state preserved on failure

## Deviations

- Enhanced `handle_get_exam_progress` in ExamAjaxHandlers.php to include `recorded_by_name` and `file_url` in the response. This wasn't in the task plan but was necessary for client-side rendering to match the PHP component's output (which resolves these server-side).

## Known Issues

None.

## Files Created/Modified

- `assets/js/learners/learner-exam-progress.js` — new: complete jQuery IIFE module for exam step recording, deletion, file upload, and in-place refresh
- `src/Learners/Shortcodes/learner-single-display-shortcode.php` — modified: added wp_enqueue_script for learner-exam-progress.js
- `src/Learners/Ajax/ExamAjaxHandlers.php` — modified: enriched get_exam_progress response with recorded_by_name and file_url for client-side rendering
