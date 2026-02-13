---
status: verifying
trigger: "learner-update-button-noop"
created: 2026-02-13T10:15:00Z
updated: 2026-02-13T10:15:00Z
---

## Current Focus

hypothesis: Button click does nothing due to JavaScript issue (missing event handler, validation blocking, or JS error)
test: Reading form HTML and associated JavaScript files to find submit handler
expecting: Will find either missing event listener, validation library preventing submission, or JS syntax error
next_action: Read learners-update-shortcode.php to examine form structure and button configuration

## Symptoms

expected: Clicking "Update Learner" should submit the form, save changes, and show a success message or redirect.
actual: Clicking "Update Learner" does absolutely nothing — no visible effect, no page reload, no error message.
errors: Debug log is empty (no PHP errors). No visible JS errors reported by user. No AJAX errors visible.
reproduction: Open any learner update form, make changes or not, click "Update Learner" button.
started: Unknown — user not sure if it worked before today's toDbArray(true) fix. Possibly pre-existing.

## Eliminated

## Evidence

- timestamp: 2026-02-13T10:16:00Z
  checked: learners-update-shortcode.php form HTML structure
  found: Form has `<form id="learners-update-form" class="needs-validation" novalidate method="POST">` with button `<button type="submit" class="btn btn-primary btn-sm">Update Learner</button>` (line 564). Form uses standard HTML POST submission, NOT AJAX. Form has inline validation JS but NO dedicated update JS file is enqueued.
  implication: No JavaScript file is enqueued for the update form. The form relies on native HTML POST submission and inline validation code (lines 571-656). If button does nothing, inline JS might be preventing submission.

- timestamp: 2026-02-13T10:16:30Z
  checked: assets/js/learners/ directory
  found: Only 4 JS files exist: learners-display-shortcode.js, learner-progressions.js, learner-single-display.js, learners-app.js. NONE are enqueued for the update form shortcode.
  implication: There is NO dedicated JS file for learners-update-shortcode.php. The form depends entirely on inline JS validation code.

- timestamp: 2026-02-13T10:17:00Z
  checked: Script enqueuing patterns
  found: learner-single-display-shortcode.php enqueues scripts, learners-display-shortcode.php enqueues scripts, but learners-update-shortcode.php does NOT enqueue any JS files (checked via grep). learners-app.js is NOT enqueued anywhere.
  implication: Update form has NO script enqueuing at all. All functionality comes from inline JS (lines 570-656).

- timestamp: 2026-02-13T10:18:00Z
  checked: learners-app.js form submit handler
  found: Lines 314-337 have a form submit handler that attaches to `$form` which is defined as `$('#learners-form')` on line 251. This handler prevents submission if SA ID or passport validation fails.
  implication: learners-app.js targets `#learners-form`, but the update form has id `#learners-update-form`. This validation JS won't attach to the update form.

- timestamp: 2026-02-13T10:19:00Z
  checked: learners-app.js again more carefully
  found: **CRITICAL BUG FOUND** - Lines 122 and 145 define `const form_update = $('#learners-update-form');` and attach a submit handler on line 145: `form_update.submit(function(e) { ... })`. However, learners-app.js is NEVER ENQUEUED. The update form inline JS (lines 571-585 of shortcode) only handles Bootstrap validation, NOT the SA ID/passport validation that learners-app.js provides.
  implication: The update form expects learners-app.js to be loaded, but it never is. The inline JS creates its own Bootstrap validation handler but doesn't include the SA ID/passport custom validation. This likely causes conflicts.

- timestamp: 2026-02-13T10:20:00Z
  checked: Debug log
  found: Debug log is completely empty (no output at all from tail command).
  implication: No PHP errors occurring. This is definitely a JavaScript problem, not a server-side issue.

- timestamp: 2026-02-13T10:30:00Z
  checked: Required attributes on form fields
  found: Multiple conditionally-hidden fields have `required` attribute: sa_id_no, passport_number (lines 277, 284), assessment_date, assessment_level (implied), employer_field (line 514 context)
  implication: These fields can be hidden but retain `required` attribute, causing checkValidity() to fail

- timestamp: 2026-02-13T10:31:00Z
  checked: learners-app.js toggling logic
  found: Lines 262-284 toggle `required` attribute when switching SA ID/Passport. Lines 158-171 toggle `required` for employer field based on employment status.
  implication: The JS properly manages `required` attributes, BUT only if the script is loaded

- timestamp: 2026-02-13T10:32:00Z
  checked: Script enqueueing in learners-update-shortcode.php
  found: NO wp_enqueue_script calls found. Searched entire codebase — learners-app.js is NOT enqueued anywhere despite comments saying "handled globally"
  implication: **ROOT CAUSE CONFIRMED** - learners-app.js contains critical field toggling logic but is never loaded. Hidden required fields fail HTML5 validation silently.

## Resolution

root_cause: learners-app.js contains critical logic to toggle `required` attributes on conditionally-hidden fields (SA ID/passport, employer, assessment) but is never enqueued for the update form. Hidden required fields cause checkValidity() to return false, triggering silent preventDefault() in validation handler.

fix:
  1. Added wp_enqueue_script('learners-app') after line 64 to load field toggling logic
  2. Enhanced validation handler with console.log to report validation failures and list invalid fields (with hidden status)
  3. Added wecoza_log() at POST handler entry to confirm requests reach PHP

verification: Test form submission with SA ID selected (passport hidden), unemployed status (employer hidden), and Not Assessed (assessment fields hidden).

files_changed: ['src/Learners/Shortcodes/learners-update-shortcode.php']
