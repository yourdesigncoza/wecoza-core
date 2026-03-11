---
estimated_steps: 5
estimated_files: 2
---

# T01: Create ExamTaskProvider service with batch-loaded exam task generation

**Slice:** S02 — Event/Task Integration
**Milestone:** M001

## Description

Create the `ExamTaskProvider` service that generates virtual `Task` objects from `learner_exam_results` + `learner_lp_tracking` + `learners` data. This is the core new component that all downstream integration depends on. Uses a single batch query for all displayed class IDs to avoid N+1. Also create the verification script with all planned S02 checks (some will intentionally fail until T02/T03 complete the integration).

## Steps

1. Create `src/Events/Services/ExamTaskProvider.php` with constructor injection for ExamRepository (D005 pattern). Implement `getExamTasksForClasses(array $classIds): array` that:
   - Accepts an array of class_ids
   - Runs a single query joining `learner_lp_tracking t` → `learners l` → LEFT JOIN `learner_exam_results r` WHERE `t.class_id IN (...)` AND the class is exam-type (caller pre-filters)
   - Groups results by class_id, then by tracking_id
   - For each learner-tracking, generates up to 5 Task objects (one per ExamStep)
   - Task ID format: `exam-{tracking_id}-{step_value}` (e.g., `exam-42-mock_1`)
   - Task label: `"{ExamStep.label()}: {first_name} {surname}"` (e.g., "Mock Exam 1: John Doe")
   - Task status: completed if a result row exists for that step, open otherwise
   - Task completedBy/completedAt populated from the result row if completed
   - Returns `array<int, TaskCollection>` keyed by class_id

2. Add static helper `parseExamTaskId(string $taskId): ?array` that returns `['tracking_id' => int, 'step' => ExamStep]` or null if format doesn't match. This is used by TaskManager in T02.

3. Add `preloadForClasses(array $classIds): void` method that runs the batch query and caches results internally. `getExamTasksForClass(int $classId): TaskCollection` returns from cache (or empty collection if not preloaded).

4. Create `tests/exam/verify-exam-task-integration.php` with organized check sections:
   - Section 1: ExamTaskProvider unit checks (class instantiation, parseExamTaskId parsing, empty input handling)
   - Section 2: ExamTaskProvider DB checks (generates tasks for exam class with learners, returns empty for non-exam class, batch loading works)
   - Section 3: TaskManager integration checks (placeholder — expected to fail until T02)
   - Section 4: ClassTaskPresenter checks (placeholder — expected to fail until T03)
   - Follow the same verification pattern as `tests/exam/verify-exam-service.php`

5. Verify ExamTaskProvider sections pass; document expected failures in other sections.

## Must-Haves

- [ ] Single batch query for all class IDs — no N+1 per-class queries
- [ ] Task ID format `exam-{trackingId}-{step}` is parseable and collision-free with `event-{N}` and `agent-order`
- [ ] Task labels include learner name for identification
- [ ] Completed exam tasks have correct completedBy and completedAt from result rows
- [ ] Open exam tasks generated for steps without results
- [ ] parseExamTaskId correctly extracts tracking_id (int) and ExamStep from valid IDs
- [ ] Empty/invalid inputs return empty collections, never throw

## Verification

- `php tests/exam/verify-exam-task-integration.php` — Section 1 (unit) and Section 2 (DB) checks all pass
- ExamTaskProvider instantiates without errors
- parseExamTaskId returns correct values for valid IDs, null for invalid
- Batch query returns correct task distribution per class_id

## Observability Impact

- Signals added/changed: `error_log("WeCoza Exam: ExamTaskProvider::method - ...")` with class_id context on caught exceptions
- How a future agent inspects this: Call `ExamTaskProvider::getExamTasksForClasses([classId])` standalone to see generated tasks for any class
- Failure state exposed: Returns empty TaskCollection on failure; error logged with class_id and exception message

## Inputs

- `src/Events/Models/Task.php` — Task value object with id, label, status, completedBy, completedAt, note, eventDate
- `src/Events/Models/TaskCollection.php` — Keyed collection with add(), open(), completed()
- `src/Learners/Enums/ExamStep.php` — 5 cases with label(), requiresFile()
- `src/Learners/Repositories/ExamRepository.php` — For future use in T02 (reopen deletes results)
- `src/Learners/Services/ExamService.php` — getExamProgress() return shape for reference
- `tests/exam/verify-exam-service.php` — Verification script pattern to follow

## Expected Output

- `src/Events/Services/ExamTaskProvider.php` — New service generating exam Task objects from DB data
- `tests/exam/verify-exam-task-integration.php` — Verification script with all S02 checks (Sections 1-2 pass, Sections 3-4 are placeholders)
