---
id: T02
parent: S02
milestone: M001
provides:
  - TaskManager exam task integration (build/complete/reopen routing)
  - ClassTaskService batch preloading for exam classes
  - ExamRepository.deleteByTrackingAndStep for exam task reopening
  - ExamTaskProvider.deleteExamResult delegation method
key_files:
  - src/Events/Services/TaskManager.php
  - src/Events/Services/ClassTaskService.php
  - src/Events/Services/ExamTaskProvider.php
  - src/Learners/Repositories/ExamRepository.php
  - tests/exam/verify-exam-task-integration.php
key_decisions:
  - fetchClassById now includes exam_class column for exam task routing
  - completeExamTask records 100% via ExamService (actual percentage via S03 UI)
  - reopenExamTask deletes result row entirely rather than soft-clearing
patterns_established:
  - isExamTask()+parseExamTaskIdOrFail() private helpers for exam task routing in TaskManager
  - Exam complete/reopen refreshes cache via preloadForClasses before rebuilding TaskCollection
  - ClassTaskService pre-filters exam class IDs from rows for batch preloading
observability_surfaces:
  - error_log("WeCoza Exam: TaskManager::markTaskCompleted - routing exam task {taskId}") on exam completion
  - error_log("WeCoza Exam: TaskManager::reopenTask - routing exam task {taskId}") on exam reopen
  - RuntimeException with "Invalid exam task ID format: {taskId}" for malformed exam- prefixed IDs
  - error_log("WeCoza Exam: ExamRepository::deleteByTrackingAndStep - ...") on delete failures
duration: 20m
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T02: Extend TaskManager and ClassTaskService to integrate exam tasks

**Wired ExamTaskProvider into TaskManager build/complete/reopen pipeline and ClassTaskService batch preloading**

## What Happened

Extended TaskManager with optional ExamTaskProvider and ExamService constructor parameters (D005 null-coalescing pattern). `buildTasksFromEvents()` now checks `$class['exam_class']` and merges cached exam tasks from ExamTaskProvider when truthy. `markTaskCompleted()` routes `exam-` prefixed task IDs to `ExamService::recordExamResult()` with 100% score. `reopenTask()` routes exam tasks to `ExamTaskProvider::deleteExamResult()` which delegates to a new `ExamRepository::deleteByTrackingAndStep()` method.

ClassTaskService now collects exam class IDs from fetched rows and calls `ExamTaskProvider::preloadForClasses()` before the per-class loop, ensuring batch loading (single query) rather than N+1.

Added `deleteByTrackingAndStep()` to ExamRepository and `deleteExamResult()` to ExamTaskProvider since these didn't exist yet but were needed for the reopen flow.

## Verification

- `php tests/exam/verify-exam-task-integration.php` — **54 passed, 0 failed, 3 skipped** (skips are T03 presenter placeholders)
- Section 3 (TaskManager Integration) all checks pass:
  - TaskManager instantiates with and without exam dependencies
  - buildTasksFromEvents excludes exam tasks for non-exam classes
  - buildTasksFromEvents includes exam tasks for exam classes (tested with class_id=22, 46 total tasks)
  - markTaskCompleted throws RuntimeException for invalid exam task IDs
  - reopenTask throws RuntimeException for invalid exam task IDs
  - Non-exam classes retain agent-order and event tasks unchanged

## Diagnostics

- Check PHP error log for `"WeCoza Exam: TaskManager::"` entries to trace exam task routing
- `TaskManager::markTaskCompleted()` with exam- prefix logs before delegating to ExamService
- Invalid exam task IDs produce RuntimeException with the malformed ID in the message
- `ExamTaskProvider::deleteExamResult()` logs failures with tracking_id and step context

## Deviations

- Added `deleteByTrackingAndStep()` to ExamRepository and `deleteExamResult()` to ExamTaskProvider — these methods didn't exist yet but were required by the plan's reopen flow
- Added `exam_class` to `fetchClassById()` SELECT — needed for buildTasksFromEvents to know if a class is exam-type when called from markTaskCompleted/reopenTask paths

## Known Issues

None

## Files Created/Modified

- `src/Events/Services/TaskManager.php` — Added exam task routing in constructor, build, complete, reopen; added fetchClassById exam_class column
- `src/Events/Services/ClassTaskService.php` — Added batch preloading of exam class IDs before per-class loop
- `src/Events/Services/ExamTaskProvider.php` — Added deleteExamResult() method
- `src/Learners/Repositories/ExamRepository.php` — Added deleteByTrackingAndStep() method
- `tests/exam/verify-exam-task-integration.php` — Replaced Section 3 placeholders with real TaskManager integration checks
