---
id: T04
parent: S01
milestone: M001
provides:
  - ExamService with recordExamResult, getExamProgress, isExamComplete, getExamResultsForTracking
  - Schema verification script (verify-exam-schema.php)
  - Full S01 data layer verification script (verify-exam-service.php)
key_files:
  - src/Learners/Services/ExamService.php
  - tests/exam/verify-exam-schema.php
  - tests/exam/verify-exam-service.php
key_decisions:
  - ExamService constructor accepts optional ExamRepository and ExamUploadService for testability (unlike ProgressionService which hardcodes new)
  - File upload for non-file steps (mock exams) is accepted with a log warning rather than rejected — allows flexibility for evidence uploads
  - Validation failures return before any DB/upload operations — fail fast pattern
patterns_established:
  - ExamService methods return consistent ['success' => bool, 'data' => array, 'error' => string] format
  - Constructor injection with null-coalescing defaults for optional DI
observability_surfaces:
  - error_log("WeCoza Exam: ExamService::methodName - ...") with tracking_id, step, and error context
  - getExamProgress() returns structured {steps, completion_percentage, completed_count, total_steps} for inspection
  - All failure paths return ['success' => false, 'error' => 'specific message'] — never throw to callers
duration: 15min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T04: Create ExamService with business logic and verification script

**Built ExamService orchestrating ExamRepository and ExamUploadService, plus comprehensive verification scripts proving the entire S01 data layer works end-to-end against real DB.**

## What Happened

Created ExamService with 4 public methods: `recordExamResult()` (validates percentage 0-100, handles optional file upload, delegates to repo upsert), `getExamProgress()` (returns all 5 steps with completion stats), `isExamComplete()` (checks all 5 steps recorded + final has certificate file), and `getExamResultsForTracking()` (raw results passthrough).

Created two verification scripts: `verify-exam-schema.php` (20 checks on SQL DDL structure) and `verify-exam-service.php` (46 checks covering enum, repo, upload service, service instantiation, validation, return formats, and DB-dependent CRUD/upsert/progress/completion).

## Verification

- `php tests/exam/verify-exam-schema.php` — **20/20 passed**: file exists, CREATE TABLE, all 9 columns, CHECK constraints for exam_step values and percentage range, UNIQUE constraint, FK reference, ON DELETE CASCADE
- `php tests/exam/verify-exam-service.php` — **46/46 passed**: ExamStep enum (8), ExamRepository (5), ExamUploadService (3), ExamService (5), validation (7), return format (2), DB-dependent tests (16 including upsert dedup, progress structure, completion check)
- Slice-level verification: both scripts pass — S01 complete

## Diagnostics

- Run `php tests/exam/verify-exam-service.php` to check entire S01 data layer health
- `ExamService::getExamProgress($trackingId)` returns structured array for any tracking_id — inspect completion_percentage and per-step data
- Check PHP error log for `"WeCoza Exam: ExamService::"` entries for any runtime failures
- All methods return `['success' => false, 'error' => '...']` on failure — callers can inspect and display

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/Learners/Services/ExamService.php` — Business logic service with validation, recording, progress, and completion checking
- `tests/exam/verify-exam-schema.php` — Schema SQL structure verification (20 checks)
- `tests/exam/verify-exam-service.php` — Full S01 data layer verification (46 checks including DB-dependent tests)
