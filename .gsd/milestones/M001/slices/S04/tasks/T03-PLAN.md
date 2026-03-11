---
estimated_steps: 5
estimated_files: 0
---

# T03: Browser end-to-end verification

**Slice:** S04 — Integration Testing & Polish
**Milestone:** M001

## Description

Final proof: exercise the full exam workflow in a real browser against the live WordPress installation. Verify exam progress UI renders for exam-class learners, POE flow renders for non-exam learners, JS module loads without errors, and AJAX endpoints are registered. This is the milestone's definition of done verification.

## Steps

1. **Find test data** — Query the database (read-only) for an exam-class learner with an active LP:
   - Find a class with `exam_class = 'Yes'`
   - Find a learner in that class with `learner_lp_tracking.status = 'in_progress'`
   - Also find a non-exam class learner for POE flow comparison

2. **Navigate to exam-class learner** — Open the learner's single display page in the browser:
   - Verify the exam progress section renders (5 step cards visible)
   - Verify each step card has the correct data attributes (`data-tracking-id`, `data-exam-step`)
   - Verify no JS console errors on page load
   - Take screenshot for documentation

3. **Verify non-exam learner** — Navigate to a non-exam learner's page:
   - Verify POE flow renders (Mark Complete button, Portfolio Upload visible)
   - Verify NO exam progress section appears
   - Verify no JS console errors
   - Take screenshot for documentation

4. **Verify AJAX endpoint registration** — Check that all 3 exam AJAX endpoints are accessible:
   - `wp_ajax_record_exam_result`
   - `wp_ajax_get_exam_progress`
   - `wp_ajax_delete_exam_result`
   - Verify via browser network tab or JS evaluation

5. **Document results** — Record any issues found. If issues require code fixes, fix them inline and re-verify. Write slice summary with complete verification results.

## Must-Haves

- [ ] Exam progress UI renders for exam-class learner in browser
- [ ] POE flow renders for non-exam learner (no exam UI leakage)
- [ ] No JS console errors on either page
- [ ] AJAX endpoints registered and accessible
- [ ] Screenshots captured as evidence

## Verification

- `browser_assert` checks: exam step cards visible for exam learner, POE elements visible for non-exam learner
- `browser_get_console_logs` shows no JS errors
- `browser_find` confirms exam-specific elements present/absent as expected
- All prior test suites still pass after any inline fixes

## Observability Impact

- Signals added: None (verification-only task)
- How a future agent inspects this: Re-run browser verification steps; check screenshots in task summary
- Failure state exposed: None

## Inputs

- Live WordPress installation at `http://localhost/wecoza/`
- All exam code from S01–S03 and T01–T02 of S04
- Database with exam-class learners (needs read-only query to find specific IDs)

## Expected Output

- Browser verification complete with pass/fail results for all checks
- Screenshots documenting exam UI and POE UI rendering
- Any inline fixes applied and verified
- Ready for S04-SUMMARY.md write-up
