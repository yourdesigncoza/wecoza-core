# S02: Event/Task Integration — UAT

**Milestone:** M001
**Written:** 2026-03-11

## UAT Type

- UAT mode: artifact-driven
- Why this mode is sufficient: This slice is integration-layer work — no user-facing UI was built. All verification is against real PostgreSQL data via automated checks. The task dashboard JS was not modified, only the PHP presenter output shape. Browser UAT is deferred to S04 which covers the full end-to-end flow.

## Preconditions

- PostgreSQL running with `learner_exam_results`, `learner_lp_tracking`, `learners`, and `classes` tables populated
- At least one class with `exam_class = true` and learners enrolled via `learner_lp_tracking`
- WeCoza Core plugin loaded (for autoloading and DB connection)

## Smoke Test

```bash
php tests/exam/verify-exam-task-integration.php
```
Expected: `=== Results: 83 passed, 0 failed, 0 skipped ===`

## Test Cases

### 1. ExamTaskProvider generates correct tasks from DB

1. Call `ExamTaskProvider::getExamTasksForClasses([22])` (class 22 is an exam class with 9 learners)
2. **Expected:** Returns 45 Task objects (9 learners × 5 exam steps). Each task ID matches `exam-{trackingId}-{step}` format. Labels contain learner names.

### 2. TaskManager includes exam tasks for exam classes only

1. Call `TaskManager::buildTasksFromEvents()` with an exam class row (`exam_class = true`)
2. Call `TaskManager::buildTasksFromEvents()` with a non-exam class row (`exam_class = false`)
3. **Expected:** Exam class returns exam tasks merged with event tasks. Non-exam class returns only event tasks — zero exam tasks.

### 3. TaskManager routes exam task completion through ExamService

1. Call `TaskManager::markTaskCompleted('exam-42-mock_1', userId)` for a valid tracking_id
2. **Expected:** ExamService::recordExamResult() called with tracking_id=42, step=mock_1, percentage=100. Result row exists in learner_exam_results.

### 4. TaskManager routes exam task reopen through ExamRepository

1. Call `TaskManager::reopenTask('exam-42-mock_1')` for a completed exam task
2. **Expected:** learner_exam_results row deleted for tracking_id=42, step=mock_1. Task reverts to open status.

### 5. ClassTaskPresenter formats exam tasks correctly

1. Create TaskCollection with mixed exam and non-exam tasks
2. Call `ClassTaskPresenter::presentTasks()`
3. **Expected:** Exam open tasks have `hide_note: true`, `note_required: false`, no `note_label`/`note_placeholder`. Non-exam tasks retain all note fields. Agent-order tasks retain `note_required: true`.

## Edge Cases

### Invalid exam task ID format

1. Call `TaskManager::markTaskCompleted('exam-invalid', userId)`
2. **Expected:** RuntimeException with descriptive message including the malformed ID

### Empty class set

1. Call `ExamTaskProvider::getExamTasksForClasses([])`
2. **Expected:** Returns empty array, no DB query executed

### Non-exam class with exam-prefixed event

1. Non-exam class processes through TaskManager::buildTasksFromEvents()
2. **Expected:** No exam task provider called, event_dates tasks returned unchanged

## Failure Signals

- Verification script reports any failures: `X passed, Y failed`
- PHP error log contains `"WeCoza Exam:"` entries indicating caught exceptions
- RuntimeException thrown for malformed exam task IDs (expected for invalid input, failure signal if thrown for valid input)
- Missing exam tasks for exam classes on dashboard (silent — check ExamTaskProvider::getExamTasksForClasses() standalone)

## Requirements Proved By This UAT

- Exam tasks generated from DB state appear alongside event_dates tasks for exam classes
- Exam task completion routes through ExamService (not event_dates JSONB)
- Exam task reopen clears result via ExamRepository delete
- Non-exam classes completely unaffected by exam task integration
- Batch loading prevents N+1 queries for dashboard display
- Presenter output compatible with existing dashboard JS shape

## Not Proven By This UAT

- Browser interaction with exam tasks on live dashboard (deferred to S04)
- Actual percentage entry for exam results (deferred to S03 UI)
- SBA/certificate file upload through exam tasks (deferred to S03)
- LP completion trigger when all 5 exam steps are recorded (deferred to S04)
- Reminder generation for pending exam steps (not in S02 scope — may need separate implementation)

## Notes for Tester

- Class ID 22 is used as the primary test fixture (exam class with 9 enrolled learners)
- The verification script is self-contained — it bootstraps WordPress and runs all checks
- Dashboard "Complete" records 100% by design (D008) — this is not a bug
- Exam tasks have no note input by design (D010) — note column is hidden via `hide_note` flag
