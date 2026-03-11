---
estimated_steps: 5
estimated_files: 3
---

# T02: Extend TaskManager and ClassTaskService to integrate exam tasks

**Slice:** S02 — Event/Task Integration
**Milestone:** M001

## Description

Wire ExamTaskProvider into the existing task pipeline. TaskManager gains exam task awareness: `buildTasksFromEvents()` merges exam tasks for exam classes, `markTaskCompleted()` routes `exam-` prefixed IDs to ExamService, `reopenTask()` clears exam results. ClassTaskService pre-loads exam data for all displayed classes via batch loading.

## Steps

1. Modify `TaskManager` constructor to accept optional `ExamTaskProvider` and `ExamService` parameters (D005 null-coalescing pattern). Store as private properties.

2. Extend `buildTasksFromEvents(array $class)` to accept the class row (which already includes `exam_class` from ClassTaskRepository). After building event_dates tasks, check if `$class['exam_class']` is truthy. If so, call `$this->examTaskProvider->getExamTasksForClass((int)$class['class_id'])` and merge each returned Task into the collection via `$collection->add()`.

3. Add private `isExamTask(string $taskId): bool` method (checks `str_starts_with($taskId, 'exam-')`) and private `parseExamTaskId(string $taskId): ?array` that delegates to `ExamTaskProvider::parseExamTaskId()`. Update `markTaskCompleted()`:
   - Before existing agent-order check, add exam task routing
   - If `isExamTask($taskId)`, parse tracking_id and step
   - Call `$this->examService->recordExamResult($trackingId, $step, 100.0, null, $userId)` — percentage 100 for dashboard completion (actual percentage recorded via S03 UI)
   - Fetch fresh class, rebuild and return TaskCollection

4. Update `reopenTask()`:
   - If `isExamTask($taskId)`, parse tracking_id and step
   - Delete the exam result via `$this->examTaskProvider->deleteExamResult($trackingId, $step)` (delegates to ExamRepository)
   - Fetch fresh class, rebuild and return TaskCollection

5. Modify `ClassTaskService::getClassTasks()`:
   - Before the per-class loop, collect all class_ids from `$rows`
   - Filter to exam class IDs (`$row['exam_class']` is truthy)
   - If any exam class IDs exist, call `$this->taskManager->getExamTaskProvider()->preloadForClasses($examClassIds)`
   - The per-class loop then calls `buildTasksFromEvents($row)` as before — exam tasks are already cached

## Must-Haves

- [ ] TaskManager accepts optional ExamTaskProvider and ExamService in constructor
- [ ] buildTasksFromEvents merges exam tasks only for exam classes
- [ ] markTaskCompleted routes exam- prefix to ExamService.recordExamResult
- [ ] reopenTask routes exam- prefix to delete exam result
- [ ] ClassTaskService pre-loads exam data before per-class loop (batch, not N+1)
- [ ] Non-exam classes completely unaffected — zero behavior change for existing flows
- [ ] Invalid exam task IDs throw RuntimeException with descriptive message

## Verification

- `php tests/exam/verify-exam-task-integration.php` — Section 3 (TaskManager integration) checks all pass
- Manually verify: non-exam class buildTasksFromEvents returns same results as before (no regression)
- Exam class buildTasksFromEvents returns event tasks + exam tasks merged

## Observability Impact

- Signals added/changed: `error_log("WeCoza Exam: TaskManager::markTaskCompleted - routing exam task {$taskId}")` on exam task routing; warning log on parse failure
- How a future agent inspects this: Check PHP error log for "WeCoza Exam: TaskManager" entries to trace exam task routing
- Failure state exposed: RuntimeException with "Invalid exam task ID format: {taskId}" for malformed exam- prefixed IDs; ExamService errors bubble up with their existing structured format

## Inputs

- `src/Events/Services/ExamTaskProvider.php` (T01) — Provides exam Task generation and parseExamTaskId
- `src/Events/Services/TaskManager.php` — Current task builder to extend
- `src/Events/Services/ClassTaskService.php` — Current orchestrator to extend
- `src/Learners/Services/ExamService.php` (S01) — recordExamResult for exam task completion

## Expected Output

- `src/Events/Services/TaskManager.php` — Extended with exam task integration in build/complete/reopen
- `src/Events/Services/ClassTaskService.php` — Extended with batch preloading for exam classes
- `tests/exam/verify-exam-task-integration.php` — Section 3 checks now pass
