# S02: Event/Task Integration — Research

**Date:** 2026-03-11

## Summary

The existing task system is tightly coupled to the `classes.event_dates` JSONB column. `TaskManager::buildTasksFromEvents()` reads this column and creates `Task` objects with IDs like `event-0`, `event-1`, plus a hardcoded `agent-order` task. `ClassTaskService` fetches class rows and delegates to TaskManager. The entire pipeline — from storage through rendering — assumes tasks live in `event_dates` JSONB.

Exam tasks are fundamentally different: they are per-learner-per-LP, not per-class. A class with 5 exam learners would need 25 exam tasks (5 steps × 5 learners). The `event_dates` JSONB pattern stores tasks at the class level with no learner dimension. Injecting exam tasks into `event_dates` would corrupt the existing data model and make the JSONB unbounded.

The recommended approach is to **extend TaskManager with an ExamTaskProvider** that generates virtual exam tasks from `learner_exam_results` data, merging them into the TaskCollection alongside existing event_dates tasks. Exam task completion routes through ExamService (already built in S01), not through `event_dates` JSONB updates.

## Recommendation

**Create an `ExamTaskProvider` service** that:
1. Given a class_id, queries `learner_lp_tracking` + `learner_exam_results` to find exam learners and their progress
2. Generates `Task` objects for each incomplete exam step per learner, with IDs like `exam-{tracking_id}-{step}` (e.g., `exam-42-mock_1`)
3. Returns a `TaskCollection` that `TaskManager::buildTasksFromEvents()` can merge

**Extend `TaskManager`** to:
1. Accept an optional `ExamTaskProvider` (constructor injection, null-coalescing default — matches D005 pattern)
2. In `buildTasksFromEvents()`, check if the class is an exam class (`exam_class` is already in the fetched row from `ClassTaskRepository`)
3. If exam class: call `ExamTaskProvider::getExamTasks($classId)` and merge results into the `TaskCollection`

**Extend `TaskManager::markTaskCompleted()`** to:
1. Detect exam task IDs (prefix `exam-`)
2. Parse `tracking_id` and `step` from the ID
3. Delegate to `ExamService::recordExamResult()` instead of updating `event_dates` JSONB
4. Return refreshed `TaskCollection` including updated exam tasks

**Extend `ClassTaskPresenter`** to:
1. Recognize exam tasks and present them with learner name context (e.g., "Mock Exam 1: John Doe")
2. No note field needed for exam tasks (percentage recording happens in S03's dedicated UI)
3. Exam task completion from the task dashboard is a simple "mark done" — percentage entry is in the separate exam UI

This approach:
- Zero changes to existing `event_dates` JSONB structure
- Zero changes to existing non-exam task flows
- Exam tasks appear naturally on the task dashboard alongside existing tasks
- Completion routing is clean: exam tasks → ExamService, event tasks → event_dates JSONB

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Task model | `WeCoza\Events\Models\Task` | Already has ID, label, status, completedBy, completedAt, note, eventDate. Exam tasks fit this shape exactly. |
| Task collection | `WeCoza\Events\Models\TaskCollection` | Keyed by task ID, has open()/completed() filters. Merge exam tasks via `add()`. |
| Task presentation | `ClassTaskPresenter::presentTasks()` | Already splits open/completed, formats labels, resolves user names. Exam tasks get the same treatment. |
| Task AJAX | `TaskController::handleUpdate()` | Already dispatches to TaskManager. Just needs exam task ID routing. |
| Exam persistence | `ExamService::recordExamResult()` | Already validates, upserts, handles files. Don't duplicate. |
| Exam progress | `ExamService::getExamProgress()` | Returns all 5 steps with completion stats. Use to generate Task objects. |
| Class row fetch | `ClassTaskRepository::fetchClasses()` | Already includes `exam_class`, `exam_type` in SELECT. No query changes needed. |

## Existing Code and Patterns

- `src/Events/Services/TaskManager.php` — Core task builder. `buildTasksFromEvents()` is the extension point. Currently hardcodes `agent-order` + `event-N` tasks. Add exam task generation here.
- `src/Events/Services/ClassTaskService.php` — Thin orchestrator. Calls `taskManager->buildTasksFromEvents()`. No changes needed if TaskManager handles everything.
- `src/Events/Models/Task.php` — Immutable value object with `markCompleted()` and `reopen()`. Exam tasks use the same shape. Note: `eventDate` field can show exam due dates.
- `src/Events/Models/TaskCollection.php` — Keyed by task ID string. `add()` appends. Will need to ensure no ID collisions between event tasks and exam tasks (guaranteed by prefix `exam-` vs `event-`).
- `src/Events/Controllers/TaskController.php` — AJAX handler for task updates. Routes `class_id` + `task_id` + `task_action` to TaskManager. Exam task IDs will route through the same endpoint.
- `src/Events/Repositories/ClassTaskRepository.php` — Already selects `exam_class`, `exam_type` from classes table. No schema changes needed.
- `src/Events/Views/Presenters/ClassTaskPresenter.php` — `presentTasks()` formats open/completed arrays. Exam tasks need a slightly different presentation (learner name in label, no note_required).
- `src/Events/Shortcodes/EventTasksShortcode.php` — Renders the `[wecoza_event_tasks]` shortcode. JS already handles dynamic task list rebuild from AJAX response. No changes if presenter output shape is consistent.
- `src/Learners/Services/ExamService.php` — S01 output. `getExamProgress()` returns `{steps, completion_percentage, completed_count, total_steps}`. `recordExamResult()` handles persistence. Both are the backend for exam task operations.
- `src/Learners/Enums/ExamStep.php` — 5 cases with `label()`, `badgeClass()`, `requiresFile()`. Use `label()` for task labels.
- `src/Classes/Services/FormDataProcessor.php` — Shows event_dates JSONB structure: `{type, description, date, status, notes, completed_by, completed_at}`. Exam tasks should NOT touch this.

## Constraints

- **Exam tasks are per-learner, event tasks are per-class.** The task dashboard groups by class. Exam tasks must be grouped under the class but show individual learner progress.
- **`learner_lp_tracking` links learners to classes.** Must join through this table to find exam learners for a given class. The `exam_learners` JSONB on the classes table is the class-level list, but `learner_lp_tracking` is the authoritative per-learner progression record.
- **Task ID format must be parseable.** Current pattern: `agent-order` or `event-{index}`. Exam pattern: `exam-{tracking_id}-{step}`. TaskManager must reliably route based on prefix.
- **No DB writes from agent.** Schema changes (if any) go to `schema/` as SQL files.
- **ExamTaskProvider needs learner names.** Must join `learner_lp_tracking` → `learners` to get learner first_name/surname for task labels. This is a new query not covered by `ClassTaskRepository.fetchClasses()`.
- **`TaskManager::markTaskCompleted()` currently returns `TaskCollection`.** Exam task completion must also return a fresh `TaskCollection` including updated exam tasks for the AJAX response.
- **ClassTaskPresenter JS rebuilds task lists.** The `buildOpenTaskHtml()` and `buildCompletedTaskHtml()` JS functions build HTML from the presenter's JSON output. Exam tasks must produce compatible JSON shape.
- **`event_dates` JSONB can be null/empty.** An exam-only class might have zero traditional events. `buildTasksFromEvents()` must still work (it already handles empty arrays).

## Common Pitfalls

- **Querying exam data per-class is N+1.** `ClassTaskService::getClassTasks()` loops over class rows. If ExamTaskProvider queries per-class inside that loop, it's N+1. **Avoid by**: batch-loading exam data for all displayed class IDs in one query, then distributing results per class.
- **Task ID collisions.** If tracking_id is numeric and happens to match an event index, `event-42` and `exam-42-mock_1` could confuse routing. **Avoid by**: using the `exam-` prefix consistently and matching on prefix in TaskManager.
- **Stale exam completion state.** If exam is completed via S03's UI (recordExamResult), the task dashboard must reflect this. **Avoid by**: always generating exam tasks from current `learner_exam_results` data, never caching.
- **Reopening exam tasks.** The current Task model supports `reopen()`. For exam tasks, reopening means deleting the exam result row. **Avoid by**: supporting reopen for exam tasks by clearing the result via ExamRepository.
- **Presenter assumes note field for all open tasks.** Current `presentTasks()` adds `note_label`, `note_placeholder`, `note_required` to all open tasks. Exam tasks don't need notes (percentage is recorded separately). **Avoid by**: adding a `task_type` or checking the ID prefix in the presenter to skip note fields for exam tasks.

## Open Risks

- **Performance with many exam learners.** A class with 30 exam learners generates 150 exam tasks (30 × 5 steps). The task dashboard might become cluttered. May need to group or collapse exam tasks per learner.
- **Exam task completion from dashboard vs. dedicated UI.** D004 says "steps 3+ should be events/tasks with reminders." If exam task completion from the dashboard is a simple complete/reopen (no percentage), then percentages must be recorded separately in S03. This creates two paths to "complete" an exam step — need to clarify whether dashboard completion records a default percentage or just marks present/done.
- **Reminders for exam steps.** The existing notification system (`EventDispatcher`, `NotificationProcessor`) processes `class_events` table entries. Exam tasks don't naturally create class_events. Reminders may need a separate mechanism or a new EventType (e.g., `EXAM_STEP_DUE`).
- **Exam learner changes after task generation.** If a learner is added to or removed from an exam class after tasks are displayed, the task list must update dynamically. Since exam tasks are generated on-the-fly from DB state (not stored in JSONB), this is inherently handled — but removal of a learner mid-exam could leave orphaned exam results.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | N/A | No specific skill needed — custom PHP plugin, no WP-specific framework skill applies |
| PostgreSQL | N/A | Standard SQL — no ORM or special tooling |

No external skills are applicable. This is custom PHP/PostgreSQL integration within an existing codebase.

## Sources

- `src/Events/Services/TaskManager.php` — primary extension point, full read
- `src/Events/Services/ClassTaskService.php` — orchestrator pattern, full read
- `src/Events/Models/Task.php` — task value object, full read
- `src/Events/Models/TaskCollection.php` — collection model, full read
- `src/Events/Controllers/TaskController.php` — AJAX handler, full read
- `src/Events/Repositories/ClassTaskRepository.php` — class query with exam fields, full read
- `src/Events/Views/Presenters/ClassTaskPresenter.php` — presentation logic, full read
- `src/Events/Shortcodes/EventTasksShortcode.php` — shortcode + JS, full read
- `src/Events/Services/EventDispatcher.php` — notification system entry point, full read
- `src/Events/Services/NotificationProcessor.php` — async notification processing, full read
- `src/Learners/Services/ExamService.php` — S01 output, full read
- `src/Learners/Enums/ExamStep.php` — exam step enum, full read
- `src/Classes/Services/FormDataProcessor.php` — event_dates JSONB structure, partial read
- `views/events/event-tasks/main.php` — dashboard template, partial read
