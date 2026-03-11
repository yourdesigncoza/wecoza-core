# S02: Event/Task Integration

**Goal:** Exam tasks generated from `learner_exam_results` data appear on the event/task dashboard alongside existing event_dates tasks. Office staff can complete and reopen exam tasks. Exam task completion routes through ExamService, not event_dates JSONB.
**Demo:** On the task dashboard (`[wecoza_event_tasks]`), an exam class shows per-learner exam step tasks (e.g., "Mock Exam 1: John Doe"). Clicking "Complete" on an exam task marks it done via ExamService. Clicking "Reopen" clears the exam result. Non-exam classes are completely unaffected.

## Must-Haves

- ExamTaskProvider generates Task objects from learner_exam_results + learner_lp_tracking data for a given set of class IDs
- Batch loading: one query for all displayed class IDs, distributed per class (no N+1)
- TaskManager merges exam tasks into TaskCollection alongside event_dates tasks for exam classes
- TaskManager routes `exam-{trackingId}-{step}` task IDs to ExamService for complete/reopen
- ClassTaskPresenter handles exam tasks: learner name in label, no note field, compatible JSON shape
- Zero changes to existing non-exam task flows (agent-order, event-N tasks unchanged)
- Verification script proves exam tasks appear, complete, reopen, and coexist with event tasks

## Proof Level

- This slice proves: integration
- Real runtime required: yes (PostgreSQL queries against real tables)
- Human/UAT required: no (automated verification against real DB)

## Verification

- `php tests/exam/verify-exam-task-integration.php` — integration test covering:
  - ExamTaskProvider generates correct Task objects from DB data
  - Task IDs follow `exam-{trackingId}-{step}` format
  - TaskManager.buildTasksFromEvents includes exam tasks for exam classes
  - TaskManager.buildTasksFromEvents excludes exam tasks for non-exam classes
  - TaskManager.markTaskCompleted routes exam task IDs to ExamService
  - TaskManager.reopenTask clears exam results via ExamRepository
  - ClassTaskPresenter formats exam tasks with learner name, no note fields
  - Presenter output JSON shape is compatible with existing JS (has id, label, complete_label/reopen_label)
  - Exam tasks coexist with event_dates tasks in the same TaskCollection without ID collisions

## Observability / Diagnostics

- Runtime signals: `error_log("WeCoza Exam: ExamTaskProvider::... - ...")` with class_id and tracking_id context on all caught exceptions. TaskManager logs when routing exam task IDs.
- Inspection surfaces: `ExamTaskProvider::getExamTasksForClasses()` returns structured array keyed by class_id — can be called standalone to inspect exam task generation for any class set.
- Failure visibility: ExamTaskProvider returns empty arrays on failure (never throws to callers), logs specific error with class_id. TaskManager throws RuntimeException with descriptive message for invalid exam task IDs.
- Redaction constraints: none (no secrets or PII in task data — learner names are already public within the WP admin context)

## Integration Closure

- Upstream surfaces consumed: `ExamService` (S01), `ExamRepository` (S01), `ExamStep` enum (S01), `Task` model, `TaskCollection` model, `TaskManager`, `ClassTaskPresenter`, `TaskController`, `ClassTaskService`, `ClassTaskRepository`
- New wiring introduced in this slice: ExamTaskProvider injected into TaskManager; TaskManager's complete/reopen methods route exam task IDs; ClassTaskPresenter recognizes exam task type; ClassTaskService passes class IDs to ExamTaskProvider for batch loading
- What remains before the milestone is truly usable end-to-end: S03 (exam progress UI for recording percentages/uploads), S04 (full integration testing, edge cases, LP completion trigger)

## Tasks

- [x] **T01: Create ExamTaskProvider service with batch-loaded exam task generation** `est:45m`
  - Why: Core new component — generates virtual Task objects from DB state. All downstream integration depends on this.
  - Files: `src/Events/Services/ExamTaskProvider.php`, `tests/exam/verify-exam-task-integration.php`
  - Do: Create ExamTaskProvider with `getExamTasksForClasses(array $classIds)` that batch-queries `learner_lp_tracking` + `learner_exam_results` + `learners` in one query, distributes results per class_id, and generates Task objects with ID `exam-{trackingId}-{step}`. Each task label: `"{ExamStep.label()}: {first_name} {surname}"`. Status derived from whether a result row exists. Create verification script with initially-failing checks for the full S02 flow.
  - Verify: `php tests/exam/verify-exam-task-integration.php` — ExamTaskProvider section passes
  - Done when: ExamTaskProvider generates correct Task objects from real DB data; batch loading confirmed with single query; verification script exists with all planned checks (some will fail until T02/T03)

- [x] **T02: Extend TaskManager and ClassTaskService to integrate exam tasks** `est:45m`
  - Why: Wires ExamTaskProvider into the existing task pipeline so exam tasks appear in TaskCollection and complete/reopen routes through ExamService.
  - Files: `src/Events/Services/TaskManager.php`, `src/Events/Services/ClassTaskService.php`
  - Do: Add optional ExamTaskProvider to TaskManager constructor (D005 pattern). In `buildTasksFromEvents()`, accept optional `exam_class` flag from class row; if true, call ExamTaskProvider and merge Tasks into collection. Add `parseExamTaskId()` method to extract tracking_id + step from `exam-{id}-{step}` format. In `markTaskCompleted()`, detect `exam-` prefix and delegate to ExamService.recordExamResult (percentage=100 for dashboard completion). In `reopenTask()`, detect `exam-` prefix and delete exam result via ExamRepository. Update ClassTaskService to pass class IDs to ExamTaskProvider for batch pre-loading, then pass class row to TaskManager.
  - Verify: `php tests/exam/verify-exam-task-integration.php` — TaskManager integration section passes
  - Done when: TaskManager.buildTasksFromEvents returns exam tasks for exam classes; markTaskCompleted with exam task ID persists via ExamService; reopenTask with exam task ID clears the result; non-exam classes unchanged

- [x] **T03: Extend ClassTaskPresenter for exam tasks and complete integration verification** `est:30m`
  - Why: Exam tasks need correct presentation (learner name in label, no note field) and the JSON shape must be compatible with existing dashboard JS.
  - Files: `src/Events/Views/Presenters/ClassTaskPresenter.php`, `tests/exam/verify-exam-task-integration.php`
  - Do: In `presentTasks()`, detect exam task IDs (prefix `exam-`). For open exam tasks: skip note_label/note_placeholder/note_required fields. For completed exam tasks: include same reopen_label. Ensure exam task JSON shape has all fields the JS `buildOpenTaskHtml()` and `buildCompletedTaskHtml()` functions expect. Update verification script to cover presenter output shape. Run full verification.
  - Verify: `php tests/exam/verify-exam-task-integration.php` — all checks pass
  - Done when: Full verification script passes all checks; exam tasks render with learner name labels and no note UI; existing non-exam task presentation unchanged

## Files Likely Touched

- `src/Events/Services/ExamTaskProvider.php` (new)
- `src/Events/Services/TaskManager.php` (modified)
- `src/Events/Services/ClassTaskService.php` (modified)
- `src/Events/Views/Presenters/ClassTaskPresenter.php` (modified)
- `tests/exam/verify-exam-task-integration.php` (new)
