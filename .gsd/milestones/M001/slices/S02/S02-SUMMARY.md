---
id: S02
parent: M001
milestone: M001
provides:
  - ExamTaskProvider service generating virtual Task objects from learner_exam_results + learner_lp_tracking
  - TaskManager exam task routing for build/complete/reopen flows
  - ClassTaskService batch preloading for exam class IDs
  - ClassTaskPresenter exam task handling with hide_note flag
  - ExamRepository.deleteByTrackingAndStep for exam task reopening
  - Template conditional for hiding note input on exam tasks
requires:
  - slice: S01
    provides: ExamService, ExamRepository, ExamStep enum, learner_exam_results schema
affects:
  - S03
  - S04
key_files:
  - src/Events/Services/ExamTaskProvider.php
  - src/Events/Services/TaskManager.php
  - src/Events/Services/ClassTaskService.php
  - src/Events/Views/Presenters/ClassTaskPresenter.php
  - src/Learners/Repositories/ExamRepository.php
  - views/events/event-tasks/main.php
  - tests/exam/verify-exam-task-integration.php
key_decisions:
  - D007: Virtual task generation from DB state instead of event_dates JSONB (supersedes D004)
  - D008: Dashboard exam completion records 100%, actual percentages via S03 UI
  - D009: Exam task reopen hard-deletes result row (no soft-clear)
  - D010: hide_note flag on presenter output for template conditionals
patterns_established:
  - ExamTaskProvider preload/cache pattern for batch dashboard loading (single query for all class IDs)
  - Task ID format exam-{trackingId}-{step} with static parseExamTaskId() decomposition
  - isExamTaskId() prefix check used in both TaskManager routing and ClassTaskPresenter detection
  - Constructor injection with null-coalescing defaults for optional exam dependencies (D005 pattern)
observability_surfaces:
  - error_log("WeCoza Exam: ExamTaskProvider::...") with class_id context on caught exceptions
  - error_log("WeCoza Exam: TaskManager::markTaskCompleted - routing exam task {taskId}") on exam completion
  - error_log("WeCoza Exam: TaskManager::reopenTask - routing exam task {taskId}") on exam reopen
  - RuntimeException with descriptive message for invalid exam task IDs (malformed exam- prefix)
  - ExamTaskProvider::getExamTasksForClasses() callable standalone for inspection
  - php tests/exam/verify-exam-task-integration.php validates all integration (83 checks)
drill_down_paths:
  - .gsd/milestones/M001/slices/S02/tasks/T01-SUMMARY.md
  - .gsd/milestones/M001/slices/S02/tasks/T02-SUMMARY.md
  - .gsd/milestones/M001/slices/S02/tasks/T03-SUMMARY.md
duration: 52m
verification_result: passed
completed_at: 2026-03-11
---

# S02: Event/Task Integration

**ExamTaskProvider generates virtual exam tasks from DB state; TaskManager routes exam task complete/reopen through ExamService; ClassTaskPresenter formats exam tasks with no-note UI — all integrated with zero changes to existing non-exam flows.**

## What Happened

Built `ExamTaskProvider` service that batch-queries `learner_lp_tracking` → `learners` → LEFT JOIN `learner_exam_results` in a single query for all displayed class IDs. Generates up to 5 virtual Task objects per learner (one per ExamStep). Task IDs follow `exam-{trackingId}-{step}` format. Labels show `"{ExamStep.label()}: {first_name} {surname}"`. Uses preload/cache pattern for N+1 avoidance.

Extended TaskManager with optional ExamTaskProvider and ExamService (D005 null-coalescing injection). `buildTasksFromEvents()` checks `$class['exam_class']` flag and merges cached exam tasks. `markTaskCompleted()` routes `exam-` prefixed IDs to `ExamService::recordExamResult()` with 100% score. `reopenTask()` routes to hard-delete via `ExamRepository::deleteByTrackingAndStep()`. Both paths refresh the ExamTaskProvider cache before rebuilding TaskCollection.

Extended ClassTaskService to pre-filter exam class IDs from fetched rows and call `ExamTaskProvider::preloadForClasses()` before the per-class loop, ensuring single batch query.

Extended ClassTaskPresenter to detect exam task IDs. Exam open tasks get `hide_note: true` and `note_required: false`, skipping note_label/note_placeholder fields. Updated the main.php template with conditional wrapper around note input column.

## Verification

```
php tests/exam/verify-exam-task-integration.php
=== Results: 83 passed, 0 failed, 0 skipped ===
```

- Section 1: ExamTaskProvider unit (24 checks) — instantiation, parseExamTaskId, isExamTaskId, empty input, cache
- Section 2: ExamTaskProvider DB (16 checks) — batch query for class_id=22 (45 tasks = 9 learners × 5 steps), ID format, labels, no collisions, preload+cache
- Section 3: TaskManager integration (11 checks) — exam tasks included for exam classes, excluded for non-exam, complete/reopen routing, RuntimeException for invalid IDs
- Section 4: ClassTaskPresenter (25+7 checks) — exam open/completed shape, non-exam regression, agent-order regression, mixed collections, ID collision detection

## Deviations

- Added `deleteByTrackingAndStep()` to ExamRepository and `deleteExamResult()` to ExamTaskProvider — not in original plan but required for the reopen flow
- Added `exam_class` to `fetchClassById()` SELECT — needed for TaskManager to detect exam classes during complete/reopen paths
- Modified `views/events/event-tasks/main.php` template — not in original file list but required to prevent undefined index warnings from note field access
- `learners` table PK is `id` not `learner_id` — fixed JOIN accordingly (plan referenced generically)

## Known Limitations

- Dashboard "Complete" records 100% — actual exam percentages require S03 UI (by design, D008)
- No UI yet for recording exam results — S03 provides the exam progress forms
- ExamTaskProvider returns empty TaskCollection on failure (silent degradation) — appropriate for dashboard but S04 should verify error logging works in production

## Follow-ups

- S03: Build AJAX endpoints and UI for recording actual exam percentages, SBA uploads, certificate uploads
- S04: Test exam task dashboard interaction end-to-end in browser, verify LP completion trigger when all 5 steps complete

## Files Created/Modified

- `src/Events/Services/ExamTaskProvider.php` — New: batch exam task generation, preload/cache, parseExamTaskId, deleteExamResult
- `src/Events/Services/TaskManager.php` — Modified: exam task routing in constructor/build/complete/reopen, fetchClassById includes exam_class
- `src/Events/Services/ClassTaskService.php` — Modified: batch preloading of exam class IDs before per-class loop
- `src/Learners/Repositories/ExamRepository.php` — Modified: added deleteByTrackingAndStep()
- `src/Events/Views/Presenters/ClassTaskPresenter.php` — Modified: exam task detection, hide_note flag, no-note shape
- `views/events/event-tasks/main.php` — Modified: conditional wrapper around note input column
- `tests/exam/verify-exam-task-integration.php` — New: full S02 integration verification (83 checks, 4 sections)

## Forward Intelligence

### What the next slice should know
- ExamTaskProvider is already wired and working — S03 only needs to build AJAX endpoints that call ExamService::recordExamResult() with actual percentages and file paths
- The `exam-{trackingId}-{step}` task ID format is stable and used by TaskManager routing — S03 AJAX handlers can use ExamTaskProvider::parseExamTaskId() to decompose task IDs
- ClassTaskPresenter output shape is compatible with existing dashboard JS — no JS changes were needed for exam tasks to render

### What's fragile
- `fetchClassById()` in TaskManager now has an `exam_class` column in its SELECT — if the classes table schema changes or the column is renamed, exam task routing silently falls back to non-exam behavior (no error, just missing exam tasks)
- The preload/cache in ExamTaskProvider is per-request only (no persistent cache) — if dashboard shows many exam classes, the single batch query could get large

### Authoritative diagnostics
- `php tests/exam/verify-exam-task-integration.php` is the single source of truth for S02 integration — 83 checks covering all 4 layers (provider, manager, service, presenter)
- PHP error log entries matching `"WeCoza Exam:"` trace exam task routing through the full pipeline

### What assumptions changed
- D004 assumed exam tasks would extend event_dates JSONB — D007 replaced this with virtual task generation (exam tasks are per-learner, not per-class, making JSONB impractical)
- Assumed ExamRepository already had delete capability — had to add deleteByTrackingAndStep() during T02
