---
id: T01
parent: S02
milestone: M001
provides:
  - ExamTaskProvider service that generates virtual Task objects from exam DB data
  - parseExamTaskId static helper for task ID decomposition
  - Batch preload/cache pattern for N+1 avoidance
  - S02 verification script with all section placeholders
key_files:
  - src/Events/Services/ExamTaskProvider.php
  - tests/exam/verify-exam-task-integration.php
key_decisions:
  - learners table PK is `id` not `learner_id` — JOIN uses `learners.id = learner_lp_tracking.learner_id`
patterns_established:
  - ExamTaskProvider uses constructor injection for ExamRepository, single batch query pattern
  - Task ID format exam-{trackingId}-{step} with static parseExamTaskId() for decomposition
  - preloadForClasses() + getExamTasksForClass() cache pattern for dashboard batch loading
observability_surfaces:
  - error_log("WeCoza Exam: ExamTaskProvider::method - ...") with class_id context on caught exceptions
  - getExamTasksForClasses() callable standalone to inspect generated tasks for any class set
  - Returns empty TaskCollection on failure (never throws), logs specific error
duration: 20m
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T01: ExamTaskProvider service with batch-loaded exam task generation

**Created ExamTaskProvider that generates virtual Task objects from learner exam data with single batch query for all class IDs.**

## What Happened

Built `ExamTaskProvider` service in `src/Events/Services/`. The service:

1. `getExamTasksForClasses(array $classIds)` — runs a single query joining `learner_lp_tracking` → `learners` → LEFT JOIN `learner_exam_results`, groups by class_id and tracking_id, generates up to 5 Task objects per learner (one per ExamStep).
2. `preloadForClasses(array $classIds)` / `getExamTasksForClass(int $classId)` — cache pattern for dashboard batch loading.
3. `parseExamTaskId(string $taskId)` — static helper returning `['tracking_id' => int, 'step' => ExamStep]` or null.
4. `isExamTaskId(string $taskId)` — quick prefix + parse check.

Task ID format: `exam-{tracking_id}-{step_value}` (e.g., `exam-42-mock_1`). Labels: `"{ExamStep.label()}: {first_name} {surname}"`. Completed tasks populated from result rows; open tasks generated for missing steps.

Created verification script with 4 sections: Section 1 (unit) and Section 2 (DB) pass; Sections 3-4 are placeholders for T02/T03.

## Verification

```
php tests/exam/verify-exam-task-integration.php
=== Results: 43 passed, 0 failed, 7 skipped ===
```

- Section 1 (Unit): 24/24 passed — instantiation, parseExamTaskId valid/invalid, isExamTaskId, empty input handling, cache behavior
- Section 2 (DB): 19/19 passed — batch query returns correct tasks for class_id=22 (45 tasks = 9 learners × 5 steps), task IDs parseable, labels formatted, no ID collisions with event/agent formats, preload+cache works
- Section 3 (TaskManager): 4 skipped — awaiting T02
- Section 4 (Presenter): 3 skipped — awaiting T03

## Diagnostics

- Call `ExamTaskProvider::getExamTasksForClasses([classId])` standalone to inspect exam task generation
- `ExamTaskProvider::parseExamTaskId('exam-42-mock_1')` returns structured array or null
- Errors logged as `error_log("WeCoza Exam: ExamTaskProvider::... - ...")` with class_id context

## Deviations

- `learners` table PK is `id` not `learner_id` — fixed JOIN from `l.learner_id` to `l.id`. The task plan referenced the column generically.
- Added `isExamTaskId()` convenience method not in original plan — useful for T02 routing logic.

## Known Issues

None.

## Files Created/Modified

- `src/Events/Services/ExamTaskProvider.php` — New service: batch exam task generation from DB data
- `tests/exam/verify-exam-task-integration.php` — S02 verification script with all 4 sections (1-2 pass, 3-4 placeholder)
