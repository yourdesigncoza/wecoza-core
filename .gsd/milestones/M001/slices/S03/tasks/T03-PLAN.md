---
estimated_steps: 5
estimated_files: 3
---

# T03: Create exam progress JavaScript and wire into shortcode

**Slice:** S03 — Exam Progress UI & AJAX
**Milestone:** M001

## Description

Creates the jQuery IIFE module that powers all interactive exam recording behavior: percentage input + submit per step, FormData file upload with progress bar for SBA/final, delete/re-record flow, and in-place UI updates after each AJAX call. Enqueues the script in the learner single display shortcode.

## Steps

1. **Create `assets/js/learners/learner-exam-progress.js`** — jQuery IIFE module following `learner-progressions.js` pattern:
   - **Record exam result handler** — Event delegation on `.exam-step-submit` buttons. Collects `tracking_id` and `exam_step` from card data attributes, percentage from input. For SBA/final, creates `FormData` with file from `input[type=file]`. Uses `$.ajax()` with `processData: false, contentType: false` for file uploads. Non-file steps use standard `$.ajax()` POST. Action: `record_exam_result`. On success, refresh the exam progress section via `get_exam_progress`.
   - **Get exam progress handler** — Function `refreshExamProgress(trackingId)` calls `get_exam_progress` AJAX and replaces the exam progress card HTML with updated server-rendered content. Uses the same skeleton/loading pattern as `refreshProgressionData()` in learner-progressions.js.
   - **Delete exam result handler** — Event delegation on `.exam-step-delete` buttons. Confirms via Bootstrap modal or `confirm()`. Calls `delete_exam_result` AJAX. On success, refreshes exam progress.
   - **File upload progress bar** — Shows a Bootstrap progress bar during file uploads. Uses `xhr.upload.onprogress` event for real-time percentage updates. Pattern from existing portfolio upload code.
   - **Input validation** — Client-side: percentage must be 0–100 integer, file max 10MB, file types PDF/DOC/DOCX/JPG/PNG. Disable submit button until valid. Show inline validation messages.
   - All AJAX calls use `learnerSingleAjax.ajaxurl` for URL, include `nonce: learnerSingleAjax.nonce` and `action` in data.

2. **Enqueue script in `learner-single-display-shortcode.php`** — Add `wp_enqueue_script('learner-exam-progress', wecoza_js_url('learners/learner-exam-progress.js'), ['jquery'], ...)` in the script enqueue section. No separate `wp_localize_script` needed — reuses existing `learnerSingleAjax` object.

3. **Add AJAX response handler for section refresh** — The `get_exam_progress` AJAX returns JSON data. The JS needs to either: (a) rebuild the step cards from JSON data client-side, or (b) call a separate AJAX endpoint that returns rendered HTML. Decision: use approach (a) — rebuild cards from JSON using a `renderExamStepCard(step)` helper function in JS. This avoids a separate render endpoint and keeps the pattern simple. The initial server-render (T02 component) and JS re-render must produce visually identical output.

4. **Handle edge cases** — Double-submit prevention (disable button during AJAX, re-enable on complete/error). Network error handling with user-visible alert. File input reset after successful upload. Percentage input clear after successful recording.

5. **Verify** — Syntax check JS file, confirm enqueue in shortcode, test AJAX patterns against existing code.

## Must-Haves

- [ ] jQuery IIFE module with no global namespace pollution
- [ ] Record exam result: percentage POST for mocks, FormData POST for SBA/final
- [ ] File upload progress bar using `xhr.upload.onprogress`
- [ ] Client-side validation: percentage 0–100, file size ≤10MB, accepted MIME types
- [ ] Delete/re-record flow with confirmation
- [ ] In-place UI refresh after each action (no full page reload)
- [ ] All AJAX uses `learnerSingleAjax.ajaxurl` and `learnerSingleAjax.nonce`
- [ ] Script enqueued in learner-single-display-shortcode.php
- [ ] Double-submit prevention on all buttons

## Verification

- `node -c assets/js/learners/learner-exam-progress.js` or `grep -c 'jQuery' assets/js/learners/learner-exam-progress.js` — file exists with jQuery pattern
- `grep 'learner-exam-progress' src/Learners/Shortcodes/learner-single-display-shortcode.php` — enqueue present
- `grep 'record_exam_result\|get_exam_progress\|delete_exam_result' assets/js/learners/learner-exam-progress.js` — all 3 AJAX actions present
- `grep 'processData.*false\|contentType.*false' assets/js/learners/learner-exam-progress.js` — FormData pattern used

## Observability Impact

- Signals added/changed: Console warnings on validation failures (percentage out of range, file too large); console.error on AJAX failures with response data
- How a future agent inspects this: Browser dev tools Console tab for client-side errors; Network tab for AJAX request/response inspection
- Failure state exposed: User-visible alert/toast on AJAX error with message from server; form stays in current state on failure (no data loss)

## Inputs

- `assets/js/learners/learner-progressions.js` — pattern template for jQuery IIFE, AJAX calls, progress bar, event delegation
- T02 output: `views/learners/components/learner-exam-progress.php` — HTML structure with data attributes for JS targeting
- T01 output: AJAX endpoints registered and working
- `src/Learners/Shortcodes/learner-single-display-shortcode.php` — enqueue location

## Expected Output

- `assets/js/learners/learner-exam-progress.js` — new: complete jQuery AJAX module for exam step recording
- `src/Learners/Shortcodes/learner-single-display-shortcode.php` — modified: enqueues exam progress JS
