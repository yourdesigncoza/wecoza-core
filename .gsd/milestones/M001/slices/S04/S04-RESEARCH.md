# S04: Integration Testing & Polish — Research

**Date:** 2026-03-11

## Summary

S04 is the final slice — integration testing, edge case handling, and wiring the exam LP completion trigger. S01–S03 built the full stack: schema, service, repository, task integration, AJAX endpoints, and UI. All 4 existing test suites pass (46 + 83 + 22 checks). The critical gap is that **recording all 5 exam steps does not auto-complete the LP** — `handle_record_exam_result()` in `ExamAjaxHandlers.php` never calls `isExamComplete()` or triggers `markLPComplete()`. This is explicitly noted as S04 scope in the S03 summary.

The second concern is edge cases: partial progress display, re-recording marks (delete + re-record), and the `exam_learners` JSONB field on classes. Currently `ExamTaskProvider` generates tasks for **all** learners in an exam class, but the `exam_learners` field on `ClassModel` stores a subset of learner IDs who are specifically exam-track. This might mean only some learners in an exam class should get exam tasks — needs clarification, but the safe default is to treat `exam_class = 'Yes'` as the filter (matching current behavior).

The third area is browser verification. The full flow (navigate to exam-class learner → record mocks → upload SBA → upload certificate → verify LP completion) must be exercised against the live app. This requires identifying a real exam-class learner in the database.

## Recommendation

Structure S04 into 3 tasks:

1. **T01: Exam LP Completion Trigger** — Wire `isExamComplete()` check into `handle_record_exam_result()` AJAX handler. After a successful `recordExamResult()`, check if all 5 steps are now complete. If so, auto-call `ProgressionService::markLPComplete()` (without portfolio file, since exam path uses certificates instead of POE portfolios). Return `lp_completed: true` in the AJAX response so JS can show a completion message. Also update the JS to handle this response.

2. **T02: Edge Cases & Defensive Fixes** — Verify and fix: (a) partial progress renders correctly (some steps done, others pending), (b) delete/re-record works end-to-end via AJAX, (c) `isExamComplete()` correctly requires final certificate file_path, (d) non-exam learners in an exam class are unaffected. Write a comprehensive integration test covering these edge cases.

3. **T03: Browser Verification** — Full end-to-end walkthrough in the live app. Find an exam-class learner, exercise all 5 steps, verify LP completion, verify non-exam learner POE flow is unchanged.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| LP completion | `ProgressionService::markLPComplete()` | Already handles status update, date recording, progression logging |
| Exam completion check | `ExamService::isExamComplete()` | Already validates all 5 steps + final certificate |
| AJAX security | `verify_learner_access('learners_nonce')` | Already in all exam AJAX handlers |
| Test framework | `tests/exam/verify-exam-*.php` pattern | 3 existing test files with consistent assert/cleanup patterns |

## Existing Code and Patterns

- `src/Learners/Ajax/ExamAjaxHandlers.php` — The `handle_record_exam_result()` function is the insertion point for the LP completion trigger. After `$service->recordExamResult()` succeeds, add `isExamComplete()` check.
- `src/Learners/Services/ProgressionService.php::markLPComplete()` — Requires `$trackingId`, `$completedBy`, optional `$portfolioFile`, optional `$effectiveDate`. For exam completion, pass `null` for portfolio (exam path uses certificates, not POE). Portfolio is not actually required — the method handles null gracefully.
- `src/Learners/Services/ExamService.php::isExamComplete()` — Checks all 5 steps exist AND final step has `file_path`. This is the authoritative check.
- `src/Learners/Models/LearnerProgressionModel.php::markComplete()` — Sets status='completed', completion_date, marked_complete_by. Works without portfolio path.
- `assets/js/learners/learner-exam-progress.js` — The `handleRecordSubmit` success handler calls `refreshExamProgress()` but doesn't check for LP completion. Need to add check for `response.data.lp_completed` and show completion UI.
- `src/Events/Services/ExamTaskProvider.php` — Generates tasks for ALL learners in exam classes. The `exam_learners` JSONB field is not currently used to filter — may be intentional (all learners in exam class get exam tasks) or may need filtering. Current behavior is safe for S04.
- `views/learners/components/learner-progressions.php` — The conditional branch `if (!empty($currentLP['is_exam_class']))` correctly gates exam vs POE flow.

## Constraints

- **Read-only DB from agent** — Cannot test write operations (record, delete) via CLI scripts against the live DB. Test scripts use cleanup patterns but the browser verification is the real proof.
- **`markLPComplete()` has portfolio requirement in POE flow** — But the underlying `LearnerProgressionModel::markComplete()` accepts null portfolio. For exam completion, we should call `markComplete()` directly (like the admin bypass in `ProgressionAjaxHandlers.php` line 441) rather than `markLPComplete()` which may throw on missing portfolio.
- **Exam class detection** — `exam_class` column stores 'Yes'/'No' strings (not boolean). `COALESCE(c.exam_class, 'No')` handles nulls in the repository query.
- **File uploads** — ExamUploadService stores files in `wp-content/uploads/exam-documents/`. Both PHP and JS resolve file URLs via `content_url()` path stripping. If the upload directory doesn't exist, `ensureUploadDirectory()` creates it on first upload.

## Common Pitfalls

- **Calling `markLPComplete()` for exam completion** — This method requires portfolio upload for POE. For exam path, call `LearnerProgressionModel::markComplete()` directly (bypassing portfolio requirement). Precedent: `ProgressionAjaxHandlers.php` line 441 does exactly this for admin override.
- **Double LP completion** — If an exam is already complete and a step is re-recorded, `isExamComplete()` returns true again. Must check `$progression->isCompleted()` before marking complete to avoid "LP is already marked as complete" exception.
- **ExamService not checking tracking_id validity** — `recordExamResult()` delegates to `repository->upsert()` which will fail on FK constraint if tracking_id doesn't exist. The error surfaces as a generic "Failed to save exam result" — acceptable.
- **JS double-refresh on LP completion** — After LP completion, `refreshExamProgress()` will refresh the exam cards, but the overall progression card (hours, progress bar, status) also needs updating. The existing `refreshProgressionData()` function in `learner-progression.js` handles this — may need to trigger it.
- **`isExamComplete()` requires certificate file_path on final step** — If office staff record final exam via dashboard "Complete" (which records 100% with no file), `isExamComplete()` returns false. LP completion only triggers when actual certificate is uploaded through the exam UI. This is correct behavior per D008.

## Open Risks

- **`exam_learners` field semantics** — The `classes.exam_learners` JSONB stores specific learner IDs. Currently `ExamTaskProvider` ignores this and generates tasks for ALL learners in exam classes. If some learners in an exam class shouldn't get exam tasks, the provider needs filtering. This may be a non-issue (all learners in exam class take exams) but could surface during browser testing.
- **`ProgressionService::recordProgression()` side effect** — When marking LP complete, this private method writes to `learner_progressions` table. It's called by `markLPComplete()` but NOT by `markComplete()` directly. If we bypass `markLPComplete()`, the legacy progression record won't be created. Need to decide if this matters.
- **Browser test data availability** — Need an actual exam-class learner with an in-progress LP in the database. If none exists, browser testing may be limited to verifying UI renders correctly without full write flow.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | N/A | No specific skill needed — standard WP AJAX patterns |
| PostgreSQL | N/A | Already using established patterns |
| jQuery | N/A | Existing JS module pattern sufficient |

No external skills needed — this slice is pure integration of existing code.

## Sources

- S02 summary: ExamTaskProvider, TaskManager routing, exam task completion/reopen flows
- S03 summary: AJAX endpoints, exam progress UI, conditional rendering
- `ExamService::isExamComplete()` source: requires all 5 steps + final certificate file
- `ProgressionAjaxHandlers.php` line 441: precedent for calling `markComplete()` directly without portfolio
- `LearnerProgressionModel::markComplete()`: accepts null portfolio path, works for exam completion
