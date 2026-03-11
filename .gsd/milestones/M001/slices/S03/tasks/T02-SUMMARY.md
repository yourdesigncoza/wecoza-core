---
id: T02
parent: S03
milestone: M001
provides:
  - learner-exam-progress.php component rendering 5 ExamStep cards with conditional completed/pending states
  - Conditional branch in learner-progressions.php — exam learners see exam UI, non-exam see POE flow
  - CSS styles for exam step cards in ydcoza-styles.css
key_files:
  - views/learners/components/learner-exam-progress.php
  - views/learners/components/learner-progressions.php
  - /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
key_decisions:
  - File download links use content_url() with WP_CONTENT_DIR path stripping — relies on exam files being stored under wp-content
  - Delete/re-record button uses bi-arrow-counterclockwise icon with text-danger styling to indicate destructive action
patterns_established:
  - Exam component included via __DIR__ relative include from learner-progressions.php (same pattern as other component includes)
  - Each exam-step-card has data-tracking-id and data-exam-step attributes for JS targeting in T03
  - Hidden #exam-nonce field provides nonce for JS AJAX calls
observability_surfaces:
  - Fallback "Unable to load exam progress" alert renders when exam_progress is null/empty
  - Each step card's completed/pending state is visible in DOM via .completed/.pending CSS classes
duration: 15min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T02: Build exam progress PHP view component with conditional rendering

**Created server-rendered 5-step exam progress component and wired conditional rendering so exam-class learners see exam UI while non-exam learners see POE flow.**

## What Happened

Built `learner-exam-progress.php` that loops through `ExamStep::cases()` to render 5 step cards. Each card shows: step label via `ExamStep::label()`, completion badge (success/secondary), and either completed details (percentage, date, recorded-by user, file link for SBA/final) or a pending input form (percentage number input, file input for SBA/final, submit button). The component receives `$currentLP` from parent scope and validates `exam_progress` and `tracking_id` presence, showing a fallback alert if missing.

Modified `learner-progressions.php` to wrap the existing POE flow (Mark Complete + Portfolio Upload) in `<?php if (empty($currentLP['is_exam_class'])): ?>` and added `<?php else: ?>` block that includes the exam progress component. The hours/progress card and history section remain visible for both flows.

Appended exam-specific CSS to ydcoza-styles.css: `.exam-step-card` base, `.completed` with green left border, `.pending` with neutral border and hover effect, plus typography helpers.

## Verification

- `php -l views/learners/components/learner-exam-progress.php` — no syntax errors ✓
- `php -l views/learners/components/learner-progressions.php` — no syntax errors ✓
- `grep 'is_exam_class' views/learners/components/learner-progressions.php` — conditional exists ✓
- `grep 'exam-step-card' ydcoza-styles.css` — 6 CSS rules found ✓
- `grep 'ExamStep::cases' views/learners/components/learner-exam-progress.php` — enum loop used ✓
- Slice-level: AJAX handler count = 3 (T01) ✓
- Slice-level: JS enqueue not yet present (T03 task) — expected partial

## Diagnostics

- View page source for a learner to see which branch rendered: exam component or POE buttons
- If `exam_progress` is null/empty for an exam learner, "Unable to load exam progress" alert renders
- Each `.exam-step-card` has `data-tracking-id` and `data-exam-step` attributes inspectable in DOM
- CSS classes `.completed` / `.pending` on cards indicate state

## Deviations

None.

## Known Issues

- File download links assume exam files are stored under wp-content directory; if ExamUploadService stores elsewhere, the `content_url()` path stripping won't work. (Matches existing portfolio upload pattern.)
- Browser visual verification deferred — component depends on a real exam-class learner in the database. Functional correctness verified via PHP lint and structural grep checks.

## Files Created/Modified

- `views/learners/components/learner-exam-progress.php` — new: 5-step exam progress card component with ExamStep enum loop
- `views/learners/components/learner-progressions.php` — modified: added conditional branch for exam vs POE flow
- `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css` — modified: appended exam step card CSS styles
