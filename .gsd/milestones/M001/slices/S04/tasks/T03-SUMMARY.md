---
id: T03
parent: S04
milestone: M001
provides:
  - Browser end-to-end verification of exam workflow UI
  - Fixed bool-to-string type mismatch in LearnerProgressionRepository exam_class query
key_files:
  - src/Learners/Repositories/LearnerProgressionRepository.php
  - tests/exam/verify-exam-ajax.php
key_decisions:
  - "Changed COALESCE(c.exam_class, 'No') to CASE WHEN c.exam_class = true THEN 'Yes' ELSE 'No' END — PostgreSQL boolean column was being assigned to PHP ?string typed property causing fatal error"
patterns_established:
  - "PostgreSQL boolean columns must be explicitly cast to string in SQL when the PHP model property is typed as ?string"
observability_surfaces:
  - none (verification-only task)
duration: ~20 minutes
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T03: Browser end-to-end verification

**Fixed exam_class bool-to-string type mismatch and verified full exam workflow UI renders correctly in browser with all AJAX endpoints registered.**

## What Happened

1. **Found test data**: Identified exam-class learner (ID 2, class 14, tracking_id 41) and non-exam learner (ID 8) via read-only DB queries.

2. **Discovered and fixed PHP Fatal Error**: Navigating to the learner view page produced `Cannot assign bool to property LearnerProgressionModel::$examClass of type ?string`. Root cause: PostgreSQL `exam_class` column is boolean type, but `COALESCE(c.exam_class, 'No')` still returns bool in PG. Fixed by changing to `CASE WHEN c.exam_class = true THEN 'Yes' ELSE 'No' END` which returns a proper text string.

3. **Verified exam-class learner UI**: Navigated to learner 2's Progressions tab — all 5 exam step cards render correctly (Mock Exam 1-3, SBA, Final Exam) with PENDING status, score inputs, Record buttons, and file upload for SBA/Final. Each card has correct `data-tracking-id=41` and `data-exam-step` attributes. No "Mark Complete" or "Portfolio Upload" shown (correct for exam flow).

4. **Verified non-exam UI isolation**: Learner 8 has both exam and non-exam LPs. The active in_progress LP is exam-class, so exam UI shows correctly. Completed non-exam progressions in the sidebar show NO exam elements. The conditional rendering works as designed — exam UI only appears for exam-class LPs.

5. **Verified AJAX endpoints**: All 3 exam AJAX endpoints (`record_exam_result`, `get_exam_progress`, `delete_exam_result`) return HTTP 200 and are properly registered (not returning WordPress '0' or '-1' error responses).

6. **All test suites pass**: 223 total checks across 5 test files, 0 failures.

## Verification

### Browser Assertions (all PASS)
- ✅ "Exam Progress" text visible
- ✅ "0/5 STEPS" badge visible
- ✅ Mock Exam 1, 2, 3, SBA, Final Exam all visible
- ✅ `.exam-step-card` selector visible (5 cards)
- ✅ No JS console errors
- ✅ Data attributes correct: `data-exam-step` (mock_1, mock_2, mock_3, sba, final), `data-tracking-id` (41)
- ✅ No "Mark Complete" / "Portfolio Upload" for exam-class LP
- ✅ No exam elements in completed progressions sidebar
- ✅ All 3 AJAX endpoints registered and returning 200

### Test Suites (all pass)
- `verify-exam-schema.php`: 20/20 passed
- `verify-exam-service.php`: 46/46 passed
- `verify-exam-task-integration.php`: 83/83 passed
- `verify-exam-ajax.php`: 22/22 passed
- `verify-exam-completion.php`: 52/52 passed
- **Total: 223/223 passed, 0 failed**

## Diagnostics

- Re-run browser verification by navigating to `http://localhost/wecoza/app/view-learner/?learner_id=2` → Progressions tab
- Run all test suites: `for f in tests/exam/verify-exam-*.php; do php $f; done`

## Deviations

- **Fixed PHP Fatal Error during verification**: The `COALESCE(c.exam_class, 'No')` SQL returned PG boolean instead of string, causing a type error when hydrating `LearnerProgressionModel::$examClass`. Changed to `CASE WHEN` expression. Also updated the test assertion in `verify-exam-ajax.php` that checked for the old SQL pattern.
- **No purely non-exam learner available**: All learners with LP tracking also have an exam-class LP. Verified conditional rendering by checking that exam UI only appears for the active exam-class LP and does not leak into completed non-exam progressions.

## Known Issues

- None

## Files Created/Modified

- `src/Learners/Repositories/LearnerProgressionRepository.php` — Fixed `COALESCE(c.exam_class, 'No')` to `CASE WHEN c.exam_class = true THEN 'Yes' ELSE 'No' END` to prevent bool-to-string type error
- `tests/exam/verify-exam-ajax.php` — Updated assertion to match new SQL pattern
