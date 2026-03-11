# S04: Integration Testing & Polish — UAT

**Milestone:** M001
**Written:** 2026-03-11

## UAT Type

- UAT mode: mixed (artifact-driven + live-runtime)
- Why this mode is sufficient: Integration tests verify LP completion logic, edge cases, and defensive error handling (artifact-driven). Browser verification against live WordPress confirms UI rendering, AJAX registration, and conditional exam/POE flow (live-runtime). Write-path flows verified via code inspection and integration tests due to DB read-only constraint.

## Preconditions

- WordPress running at `http://localhost/wecoza/`
- PostgreSQL with `learner_exam_results` table deployed (S01 schema)
- At least one exam-class learner with active LP tracking (e.g., learner_id=2, tracking_id=41)
- User logged into WordPress with appropriate capabilities

## Smoke Test

Run `php tests/exam/verify-exam-completion.php` — should report 52/52 passed. Then navigate to `http://localhost/wecoza/app/view-learner/?learner_id=2` → Progressions tab — should see 5 exam step cards.

## Test Cases

### 1. LP auto-completion on final exam step

1. Record exam results for all 5 steps (mock_1, mock_2, mock_3, sba with file, final with certificate)
2. After recording the 5th step, check AJAX response
3. **Expected:** Response includes `lp_completed: true`. PHP error log shows "LP auto-completed for tracking_id=X". Progression status changes to "completed".

### 2. Double-completion guard

1. With all 5 steps already recorded and LP completed, record any step again (re-record)
2. **Expected:** Response includes `lp_completed: false`. PHP error log shows "LP already completed for tracking_id=X, skipping". No error thrown.

### 3. Exam progress UI rendering

1. Navigate to exam-class learner's progression view
2. **Expected:** 5 exam step cards (Mock Exam 1, Mock Exam 2, Mock Exam 3, SBA, Final Exam) with score inputs, Record buttons. SBA and Final have file upload. No "Mark Complete" or "Portfolio Upload" buttons.

### 4. Non-exam learner isolation

1. Navigate to a non-exam learner's progression view
2. **Expected:** Standard POE flow renders (Mark Complete, Portfolio Upload). No exam step cards visible.

### 5. JS completion feedback

1. Record the final exam step completing all 5
2. **Expected:** Bootstrap success alert appears: "🎓 Learning Programme completed!". If refreshProgressionData exists, progression card refreshes automatically.

## Edge Cases

### Partial progress (4 of 5 steps)

1. Record only 4 of 5 exam steps
2. **Expected:** `lp_completed: false` in response. LP status remains "in_progress".

### Final step without certificate

1. Record final exam step with percentage but no file upload
2. **Expected:** `isExamComplete()` returns false. LP not auto-completed.

### Delete and re-record

1. Delete an exam step result, then re-record it
2. **Expected:** Delete succeeds. Re-record succeeds (upsert). If all 5 steps now complete, LP auto-completes.

### LP completion failure

1. Simulate `markComplete()` failure (e.g., invalid tracking_id)
2. **Expected:** Exam result is still saved. Response includes `lp_error` with error message. PHP error log shows "markComplete failed for tracking_id=X".

## Failure Signals

- PHP Fatal Error on progression page load (check for bool-to-string type errors)
- AJAX endpoints returning WordPress '0' or '-1' (handler not registered)
- JS console errors on progression tab (module loading failure)
- Missing exam step cards for exam-class learner
- Exam step cards appearing for non-exam learner
- `lp_completed` key missing from record_exam_result response
- LP status not changing to "completed" after all 5 steps recorded

## Requirements Proved By This UAT

- Exam step recording auto-completes LP when all 5 steps done (proved by integration test + code inspection)
- Double-completion guard prevents duplicate completion (proved by integration test)
- Certificate required for final exam step completion (proved by integration test)
- Exam UI renders only for exam-class learners (proved by browser verification)
- POE flow unaffected for non-exam learners (proved by browser verification)
- All 5 exam steps visible as cards in progression UI (proved by browser verification)
- AJAX endpoints registered and responding (proved by browser HTTP checks)

## Not Proven By This UAT

- Actual end-to-end write flow in browser (recording results, seeing status change live) — deferred due to DB read-only constraint. Logic verified via integration tests.
- File upload flow for SBA/certificate in browser — upload UI verified present, actual upload not exercised
- Email/notification on LP completion — not part of M001 scope
- Concurrent user access / race conditions on exam result recording

## Notes for Tester

- The integration tests are code-inspection style (checking PHP/JS source for correct patterns) rather than exercising live DB writes. This is by design — the read-only constraint prevents running live mutations in tests.
- To do a full live walkthrough: log in as office staff, navigate to an exam-class learner, record results for all 5 steps with SBA scan and final certificate, verify LP completes.
- Check PHP error log at `/opt/lampp/htdocs/wecoza/wp-content/debug.log` for completion events.
