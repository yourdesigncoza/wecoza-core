# S01: Exam Data Layer & Service — UAT

**Milestone:** M001
**Written:** 2026-03-11

## UAT Type

- UAT mode: artifact-driven
- Why this mode is sufficient: S01 is a data layer with no UI. All contracts are verified by automated scripts running against real PostgreSQL. No browser or human interaction needed.

## Preconditions

- PostgreSQL running and accessible via `wecoza_db()`
- Schema deployed: `psql -f schema/learner_exam_results.sql`
- WordPress environment loadable (for autoloading and DB connection)
- At least one row in `learner_lp_tracking` table (for FK-valid test data)

## Smoke Test

Run `php tests/exam/verify-exam-service.php` — all 46 checks must pass. This exercises the enum, repository, upload service, service layer, validation, return formats, and DB CRUD including upsert deduplication and progress tracking.

## Test Cases

### 1. Schema structure is correct

1. Run `php tests/exam/verify-exam-schema.php`
2. **Expected:** 20/20 checks pass — confirms table name, all 9 columns, CHECK constraints for exam_step values and percentage range, UNIQUE constraint, FK reference, ON DELETE CASCADE

### 2. ExamStep enum is complete and correct

1. Run `php tests/exam/verify-exam-service.php` (enum section)
2. **Expected:** 8/8 enum checks pass — 5 cases, correct values, requiresFile() true for sba/final only, tryFromString() resolves valid values and returns null for invalid

### 3. ExamService records exam results

1. Run `php tests/exam/verify-exam-service.php` (DB section)
2. **Expected:** recordExamResult() succeeds with valid percentage, returns result_id, upsert updates existing record without creating duplicate

### 4. Progress tracking works

1. Run `php tests/exam/verify-exam-service.php` (DB section)
2. **Expected:** getExamProgress() returns all 5 steps with completion stats, recorded step has data, isExamComplete() returns false when not all steps recorded

### 5. Validation rejects invalid input

1. Run `php tests/exam/verify-exam-service.php` (validation section)
2. **Expected:** Percentage > 100 rejected, percentage < 0 rejected, percentage 0 and 100 accepted, error messages present on rejection

## Edge Cases

### Upsert deduplication

1. Record a result for the same tracking_id + exam_step twice with different percentages
2. **Expected:** Second call updates the existing row (same result_id), does not create a duplicate

### Non-existent tracking ID

1. Call getExamProgress() with a tracking_id that has no results
2. **Expected:** Returns all 5 steps with null data, completion_percentage = 0, completed_count = 0

### Boundary percentages

1. Record percentage = 0 and percentage = 100
2. **Expected:** Both accepted without error

## Failure Signals

- Any check in `verify-exam-service.php` reports ❌ FAIL
- PHP error log contains `"WeCoza Exam:"` entries with error context
- ExamService methods return `['success' => false]` unexpectedly
- Schema deployment fails (missing FK target table, syntax errors)

## Requirements Proved By This UAT

- Exam results can be created, read, and updated via ExamService against real database tables
- Percentage validation enforces 0–100 range
- Upsert prevents duplicate results per tracking_id + exam_step
- File upload service validates MIME types and creates security files
- Completion check requires all 5 steps + final certificate upload

## Not Proven By This UAT

- UI rendering of exam progress (S03)
- AJAX endpoint behavior (S03)
- Event/task integration — exam tasks appearing on dashboard (S02)
- File upload with real HTTP multipart request (only service contract verified, not HTTP layer)
- LP completion trigger when final exam is recorded (S04)
- Concurrent access / race conditions on upsert

## Notes for Tester

- The verification script cleans up its own test data after execution
- Schema must be deployed before running verify-exam-service.php — the script will fail on DB-dependent tests if the table doesn't exist
- The 30 non-DB checks will still pass without schema deployment (enum, instantiation, validation)
