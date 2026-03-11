---
id: T02
parent: S01
milestone: M001
provides:
  - ExamRepository with CRUD + upsert + progress query on learner_exam_results table
key_files:
  - src/Learners/Repositories/ExamRepository.php
key_decisions:
  - upsert uses INSERT ON CONFLICT (tracking_id, exam_step) DO UPDATE with RETURNING result_id — keeps recorded_at from original insert, updates updated_at on conflict
patterns_established:
  - ExamRepository extends BaseRepository with column whitelists; custom query methods follow LearnerProgressionRepository error handling pattern
observability_surfaces:
  - error_log("WeCoza Exam: ExamRepository::<method> - Error for tracking_id=X, step=Y: ...") on all caught exceptions
duration: 8min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T02: Create ExamRepository with CRUD operations

**Built ExamRepository extending BaseRepository with upsert, per-step lookup, and progress-map query against learner_exam_results table.**

## What Happened

Created `ExamRepository` with:
- `$table = 'learner_exam_results'`, `$primaryKey = 'result_id'`
- Column whitelists for all four security methods (insert: 8, update: 5, filter: 6, order: 6)
- `findByTrackingId($trackingId)` — returns all results for an LP ordered by step sequence
- `findByTrackingAndStep($trackingId, ExamStep $step)` — single result lookup
- `upsert($trackingId, ExamStep $step, array $data)` — INSERT ON CONFLICT DO UPDATE with RETURNING result_id
- `getProgressForTracking($trackingId)` — returns array keyed by all 5 exam_step values with result data or null for missing steps

The upsert method filters incoming data through `getAllowedUpdateColumns()`, then builds the full insert with tracking_id, exam_step, recorded_at, updated_at. On conflict, it updates all columns except tracking_id, exam_step, and recorded_at (preserving original record timestamp).

Inherits `findById()`, `findAll()`, `findBy()`, `insert()`, `update()`, `delete()`, `paginate()` from BaseRepository — all of which use the column whitelists.

## Verification

- `php -l src/Learners/Repositories/ExamRepository.php` — no syntax errors
- WordPress bootstrap: instantiated successfully, DB connection confirmed as PostgresConnection
- Column whitelist counts verified via reflection (8/5/6/6)
- `getProgressForTracking(999999)` returns all 5 keys (mock_1, mock_2, mock_3, sba, final) with null values
- Slice schema test `php tests/exam/verify-exam-schema.php` — 20/20 passed
- Slice service test `tests/exam/verify-exam-service.php` — not yet created (future task)

## Diagnostics

- Check PHP error log for `"WeCoza Exam: ExamRepository"` entries for any runtime failures
- `getProgressForTracking()` provides a structured inspection surface showing which steps are completed vs pending
- All methods return null or empty array on failure (never throw to callers), with full context in error_log

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/Learners/Repositories/ExamRepository.php` — New repository with CRUD + upsert + progress query
