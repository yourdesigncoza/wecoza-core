---
estimated_steps: 4
estimated_files: 2
---

# T02: Create ExamRepository with CRUD operations

**Slice:** S01 — Exam Data Layer & Service
**Milestone:** M001

## Description

Create `ExamRepository` extending `BaseRepository` to handle all database operations on the `learner_exam_results` table. Follows `LearnerProgressionRepository` patterns: column whitelisting for security, parameterized queries, RETURNING clause for inserts, and structured error handling. Supports upsert (insert or update) since office staff may need to correct previously recorded results.

## Steps

1. Read `src/Learners/Repositories/LearnerProgressionRepository.php` to confirm exact pattern for column whitelists, insert/update methods, and error handling
2. Read `core/Abstract/BaseRepository.php` to confirm available base methods and abstract interface
3. Write `src/Learners/Repositories/ExamRepository.php` with: static `$table = 'learner_exam_results'`, `$primaryKey = 'result_id'`, column whitelist methods (`getAllowedInsertColumns`, `getAllowedUpdateColumns`, `getAllowedFilterColumns`, `getAllowedOrderColumns`), query methods: `findByTrackingId($trackingId)`, `findByTrackingAndStep($trackingId, ExamStep $step)`, `upsert($trackingId, ExamStep $step, array $data)`, `getProgressForTracking($trackingId)` returning all 5 steps with status
4. Verify repository loads and instantiates via WordPress bootstrap

## Must-Haves

- [ ] Extends `BaseRepository` with correct `$table` and `$primaryKey`
- [ ] Column whitelisting on all insert/update/filter/order columns
- [ ] `upsert()` uses INSERT ... ON CONFLICT (tracking_id, exam_step) DO UPDATE pattern
- [ ] All queries use parameterized PDO statements (no string interpolation)
- [ ] `getProgressForTracking()` returns array keyed by exam_step with result data or null for missing steps
- [ ] Error handling with `error_log("WeCoza Exam: ...")` pattern and exception catching
- [ ] Uses `RETURNING` clause for insert to get result_id

## Verification

- `php -l src/Learners/Repositories/ExamRepository.php` — no syntax errors
- WordPress bootstrap test: repository instantiates and `$db` connection is set

## Observability Impact

- Signals added/changed: Error logging via `error_log("WeCoza Exam: ExamRepository::methodName - ...")` on all caught exceptions
- How a future agent inspects this: Check PHP error log for "WeCoza Exam: ExamRepository" entries; inspect return values from repository methods
- Failure state exposed: Methods return `null` or empty array on failure, with error details logged including tracking_id and exam_step context

## Inputs

- `src/Learners/Enums/ExamStep.php` — ExamStep enum from T01
- `src/Learners/Repositories/LearnerProgressionRepository.php` — pattern reference
- `core/Abstract/BaseRepository.php` — base class

## Expected Output

- `src/Learners/Repositories/ExamRepository.php` — fully functional repository with CRUD + upsert + progress query
