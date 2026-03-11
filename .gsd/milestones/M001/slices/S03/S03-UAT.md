# S03: Exam Progress UI & AJAX — UAT

**Milestone:** M001
**Written:** 2026-03-11

## UAT Type

- UAT mode: mixed (artifact-driven + live-runtime)
- Why this mode is sufficient: Automated checks verify structural correctness (AJAX registration, data pipeline, syntax). Live-runtime browser testing verifies the conditional UI rendering and interactive exam recording flow. Full end-to-end flow deferred to S04 which covers integration testing.

## Preconditions

- WordPress running at `http://wecoza.test` (or equivalent local URL)
- PostgreSQL database accessible with `learner_exam_results` table created (S01 schema)
- Logged in as a user with `manage_learners` capability
- At least one learner enrolled in an exam-class LP (class with `exam_class = 'Yes'`)
- At least one learner enrolled in a non-exam LP (for POE flow comparison)

## Smoke Test

Navigate to an exam-class learner's single display page → Progression tab should show "Exam Progress" section with 5 step cards instead of Mark Complete / Portfolio Upload buttons.

## Test Cases

### 1. Conditional UI: Exam-class learner shows exam progress

1. Navigate to a learner enrolled in an exam-class LP
2. Click the Progression tab
3. **Expected:** See "Exam Progress" heading with 5 step cards (Mock Exam 1, Mock Exam 2, Mock Exam 3, SBA Assessment, Final Exam). Hours/progress card is also visible. Mark Complete and Portfolio Upload sections are NOT shown.

### 2. Conditional UI: Non-exam learner shows POE flow

1. Navigate to a learner enrolled in a non-exam LP
2. Click the Progression tab
3. **Expected:** See the original POE flow — Mark Complete button, Portfolio Upload section. No "Exam Progress" section visible.

### 3. Record mock exam percentage

1. On an exam-class learner's progression, find the "Mock Exam 1" pending card
2. Enter a percentage (e.g., 72) in the number input
3. Click the submit button
4. **Expected:** Card updates in-place to show completed state — green left border, "72%" badge, completion date, recorded-by username. No page reload.

### 4. Record SBA with file upload

1. Find the "SBA Assessment" pending card
2. Enter a percentage (e.g., 65)
3. Select a PDF/image file for upload
4. Click submit
5. **Expected:** Progress bar shows during upload. Card updates to completed state showing percentage, date, username, and a file download link.

### 5. Record final exam with certificate upload

1. Find the "Final Exam" pending card
2. Enter a percentage (e.g., 80)
3. Select a certificate file (PDF/image)
4. Click submit
5. **Expected:** Card updates to completed state with percentage, date, username, and certificate download link.

### 6. Delete/re-record an exam result

1. On a completed step card, click the re-record button (counterclockwise arrow icon)
2. Confirm the browser dialog
3. **Expected:** Card reverts to pending state with input form. Previous result is removed.

### 7. Validation: percentage range

1. On a pending step card, enter 101 in the percentage field
2. Attempt to submit
3. **Expected:** Submit button remains disabled or validation prevents submission. No AJAX call made.

### 8. Validation: file type for SBA/final

1. On SBA or Final Exam pending card, select a .exe or .zip file
2. **Expected:** Validation warning appears. Submit prevented.

## Edge Cases

### Empty exam progress data

1. Navigate to an exam-class learner with no exam results recorded yet
2. **Expected:** All 5 cards show in pending state. No errors in console.

### AJAX error handling

1. Open browser dev tools Network tab
2. Simulate a server error (e.g., temporarily rename ExamAjaxHandlers.php)
3. Try to record an exam result
4. **Expected:** Inline error alert appears with specific error message. Form state preserved (entered data not lost). Console shows error details.

## Failure Signals

- "Exam Progress" section not appearing for exam-class learners
- POE sections (Mark Complete, Portfolio Upload) showing for exam-class learners
- AJAX 0 or 403 responses in Network tab (nonce/auth failure)
- PHP errors in `wp-content/debug.log` matching "WeCoza ExamAjax:"
- JavaScript errors in browser console
- Step cards not updating after recording (refresh function broken)
- File upload progress bar not appearing for SBA/final steps

## Not Proven By This UAT

- Exam LP completion trigger (recording all 5 steps does not auto-complete LP — S04 scope)
- Full end-to-end flow from first mock through LP completion (S04 scope)
- Mixed learner scenario: exam class with some learners on exam track and others not
- Re-recording accuracy: percentage overwrite correctness after delete + re-record
- Concurrent access: two users recording results for the same learner simultaneously

## Notes for Tester

- The exam progress section requires real `exam_class = 'Yes'` data on the learner's class. If no exam classes exist in the database, the conditional branch won't trigger.
- File uploads go to `wp-content/uploads/exam-documents/` — check filesystem permissions if uploads fail.
- The JS module reuses `learnerSingleAjax` nonce — if the nonce expires (e.g., long idle session), all AJAX calls will fail with auth errors.
- Mock exam steps (1, 2, 3) only require percentage input. SBA and Final additionally show file upload input.
