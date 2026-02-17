---
phase: quick-11
plan: 01
subsystem: clients
tags: [ux, ajax, form, scroll, feedback]
dependency_graph:
  requires: []
  provides: [client-capture-post-save-ux]
  affects: [wecoza_capture_clients shortcode]
tech_stack:
  added: []
  patterns: [scroll-to-feedback, immediate-form-clear, banner-auto-dismiss]
key_files:
  created: []
  modified:
    - assets/js/clients/client-capture.js
decisions:
  - Scroll added via shared scrollToFeedback() helper to keep DRY — used in done/fail/error paths
  - Banner auto-dismiss uses fadeOut+empty+show to reset feedback div state cleanly
  - id input removed (not just cleared) after new client save to guarantee next submit is a create
metrics:
  duration: "5 minutes"
  completed: "2026-02-17"
  tasks: 1
  files_modified: 1
---

# Quick Task 11: After Saving a New Client — Post-Save UX Summary

**One-liner:** Scroll-to-banner + immediate form clear + 5-second auto-dismiss for new client saves in client-capture.js.

## What Was Done

Restructured the `.done()` AJAX callback in `/assets/js/clients/client-capture.js` to fix the post-save UX flow on the client capture form.

### Changes Made

**`assets/js/clients/client-capture.js`** (lines 500–590)

1. **Added `scrollToFeedback()` helper** — animates `html, body` to `container.offset().top - 80` with 400ms duration. Consistent with `agents-ajax-pagination.js` and `class-capture.js` patterns.

2. **Scroll on success** — called immediately after `renderMessage('success', ...)`.

3. **Immediate form clear** — replaced `setTimeout(clearForm, 1500)` with synchronous `clearForm()`. Users already know the form was submitted (saw "Saving client..." button state); clearing immediately is cleaner.

4. **Remove dynamic id input** — after `clearForm()`, calls `form.find('input[name="id"]').remove()` so subsequent submits are treated as new creates, not updates.

5. **5-second auto-dismiss** — `setTimeout(feedback.fadeOut(...), 5000)` replaces the old 3-second `feedback.empty()`. `fadeOut(300, fn)` fades out gracefully, then empties and restores visibility of the div.

6. **Restructured if/else** — Three clear branches: `isNewClient && clear_form_on_success` (clear path), `isNewClient` without clear (edit-mode path, unchanged), and update path (`window.location.reload()`).

7. **Scroll on all error paths** — `scrollToFeedback()` added to the `else if (errors)`, `else`, and `.fail()` branches so error banners are always visible.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Files Exist
- `assets/js/clients/client-capture.js` — FOUND (modified)
- `scrollToFeedback` function — FOUND at line 500
- `scrollToFeedback()` in done/fail/error paths — FOUND
- Immediate `clearForm()` without setTimeout — FOUND at line 545
- `form.find('input[name="id"]').remove()` — FOUND at line 546
- `setTimeout(feedback.fadeOut, 5000)` — FOUND at lines 548–552

### Commits
- `d5cccbe` feat(quick-11): improve post-save UX in client capture form — FOUND

## Self-Check: PASSED
