---
estimated_steps: 5
estimated_files: 3
---

# T02: Build exam progress PHP view component with conditional rendering

**Slice:** S03 — Exam Progress UI & AJAX
**Milestone:** M001

## Description

Creates the server-rendered exam progress component showing 5 exam step cards, and wires conditional rendering into the existing progression view so exam-class learners see exam UI while non-exam learners continue seeing the POE flow (Mark Complete + Portfolio Upload). The hours/progress card remains visible for both flows.

## Steps

1. **Create `views/learners/components/learner-exam-progress.php`** — Component that receives `$currentLP` (with `exam_progress` and `tracking_id`) from parent scope. Renders:
   - Section header "Exam Progress" with completion badge (e.g., "2/5 steps")
   - 5 step cards in order (mock_1 → mock_2 → mock_3 → sba → final), each showing:
     - Step label from `ExamStep::label()` (e.g., "Mock Exam 1")
     - Badge from `ExamStep::badgeClass()` — completed (green) vs pending (secondary)
     - If completed: recorded percentage, recorded date, recorded-by user
     - If pending: percentage input field (number 0–100), submit button
     - For SBA/final steps (`ExamStep::requiresFile()`): file input (accept PDF/DOC/DOCX/JPG/PNG, max 10MB)
     - For completed SBA/final: show file name with download link
     - Each card has data attributes: `data-tracking-id`, `data-exam-step` for JS targeting
   - Uses Bootstrap 5 card layout with `data-bs-` attributes, Phoenix badge classes
   - Uses `ExamStep` enum for step metadata — loop through `ExamStep::cases()` to generate cards

2. **Add conditional rendering in `learner-progressions.php`** — Inside the `<?php if ($isAdmin): ?>` admin actions block (around line 130), wrap the existing Mark Complete + Portfolio Upload sections in `<?php if (empty($currentLP['is_exam_class'])): ?>`. Add `<?php else: ?>` block that includes the exam progress component: `<?php include __DIR__ . '/learner-exam-progress.php'; ?>`. The hours/progress display above the admin actions stays for both flows.

3. **Ensure `$currentLP` data propagation** — The exam progress component needs `$currentLP['exam_progress']`, `$currentLP['tracking_id']`, and nonce. Verify the `$currentLP` from T01's extended `getCurrentLPDetails()` flows through to the component. Add a hidden nonce field that the JS will use.

4. **Add CSS to `ydcoza-styles.css`** — Append exam-specific styles:
   - `.exam-step-card` — card styling for each step (subtle border, padding)
   - `.exam-step-card.completed` — green left border or success styling
   - `.exam-step-card.pending` — neutral border
   - `.exam-step-percentage` — styling for the recorded percentage display
   - `.exam-step-actions` — spacing for input + submit button layout
   - Keep styles minimal — Bootstrap 5 handles most of the layout

5. **Verify** — PHP lint check on new component, visual inspection of conditional logic, confirm CSS additions.

## Must-Haves

- [ ] Exam progress component renders all 5 ExamStep cards with correct labels and badges
- [ ] SBA and final cards show file upload input; mock cards do not
- [ ] Completed steps show percentage, date, and file info (when applicable)
- [ ] Pending steps show percentage input and submit button
- [ ] Conditional in learner-progressions.php: exam learners see exam UI, non-exam see POE
- [ ] Hours/progress card remains visible for both exam and non-exam learners
- [ ] CSS appended to ydcoza-styles.css (not a separate file)
- [ ] Component uses `ExamStep::cases()` loop, not hardcoded step names

## Verification

- `php -l views/learners/components/learner-exam-progress.php` — no syntax errors
- `grep 'is_exam_class' views/learners/components/learner-progressions.php` — conditional exists
- `grep 'exam-step-card' /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css` — CSS exists
- `grep 'ExamStep::cases' views/learners/components/learner-exam-progress.php` — enum loop used

## Observability Impact

- Signals added/changed: None (server-side render, no new runtime signals)
- How a future agent inspects this: View page source for a learner to see which branch rendered (exam vs POE); inspect `$currentLP['is_exam_class']` value via `get_exam_progress` AJAX
- Failure state exposed: If `exam_progress` is null/empty for an exam learner, the component shows "Unable to load exam progress" fallback message

## Inputs

- `views/learners/components/learner-progressions.php` — existing view to add conditional branch
- `src/Learners/Enums/ExamStep.php` — `ExamStep::cases()`, `label()`, `badgeClass()`, `requiresFile()`
- T01 output: `getCurrentLPDetails()` now returns `is_exam_class` and `exam_progress`
- S03-RESEARCH.md: Phoenix theme badge classes `badge-phoenix badge-phoenix-*`, BS5 `data-bs-` attributes

## Expected Output

- `views/learners/components/learner-exam-progress.php` — new: 5-step exam progress card component
- `views/learners/components/learner-progressions.php` — modified: conditional rendering branch
- `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css` — modified: exam card styles appended
