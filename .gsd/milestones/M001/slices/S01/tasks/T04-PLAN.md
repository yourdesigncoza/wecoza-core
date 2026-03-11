---
estimated_steps: 5
estimated_files: 3
---

# T04: Create ExamService with business logic and verification script

**Slice:** S01 — Exam Data Layer & Service
**Milestone:** M001

## Description

Create `ExamService` — the main service class that S02 and S03 will consume. Orchestrates ExamRepository and ExamUploadService to provide the business logic layer: recording exam results with validation, retrieving progress, checking completion. Also write the comprehensive verification script that proves the entire S01 data layer works end-to-end against the real database.

## Steps

1. Read `src/Learners/Services/ProgressionService.php` to confirm service pattern — constructor injection, return formats, error handling, logging
2. Write `src/Learners/Services/ExamService.php` with:
   - Constructor takes ExamRepository and ExamUploadService (or instantiates them)
   - `recordExamResult(int $trackingId, ExamStep $step, float $percentage, ?array $file = null, ?int $recordedBy = null)` — validates percentage 0-100, delegates to repo upsert, handles file upload for sba/final steps, returns `['success' => bool, 'data' => array, 'error' => string]`
   - `getExamProgress(int $trackingId)` — returns all 5 steps with result data or null, plus overall completion percentage
   - `isExamComplete(int $trackingId)` — checks all 5 steps recorded AND final step has file (certificate), returns bool
   - `getExamResultsForTracking(int $trackingId)` — raw results from repository
   - Validation: percentage 0-100, file required only for sba/final when recording those steps (not blocking — file can be added later)
3. Write `tests/exam/verify-exam-schema.php` — reads SQL file and checks: CREATE TABLE present, all expected columns listed, CHECK constraints for exam_step and percentage, UNIQUE constraint, FK reference
4. Write `tests/exam/verify-exam-service.php` — WordPress-bootstrapped script that:
   - Verifies ExamStep enum has 5 cases with correct values
   - Verifies ExamRepository instantiates and has expected methods
   - Verifies ExamUploadService instantiates and has expected methods
   - Verifies ExamService instantiates and has expected methods
   - Tests `recordExamResult()` with valid percentage — asserts success response
   - Tests `recordExamResult()` with invalid percentage (>100, <0) — asserts error response
   - Tests `getExamProgress()` returns all 5 steps
   - Tests `isExamComplete()` returns false when not all steps recorded
   - Tests upsert behavior — recording same step twice updates rather than duplicates
   - Reports pass/fail counts with clear output
5. Run verification scripts and confirm all assertions pass

## Must-Haves

- [ ] `recordExamResult()` validates percentage 0-100 before DB write
- [ ] `recordExamResult()` handles file upload when file array provided for sba/final steps
- [ ] `getExamProgress()` returns structured array with all 5 ExamStep values as keys
- [ ] `isExamComplete()` checks all 5 steps have results AND final has certificate file
- [ ] All methods return consistent `['success' => bool, ...]` format
- [ ] Error logging with full context (tracking_id, step, error message)
- [ ] Verification script exercises all public methods with both valid and invalid inputs
- [ ] Schema verification script validates SQL structure independently

## Verification

- `php tests/exam/verify-exam-schema.php` — all SQL structure checks pass
- `php tests/exam/verify-exam-service.php` — all service contract assertions pass (requires schema deployed)
- Both scripts output clear pass/fail for each check

## Observability Impact

- Signals added/changed: `error_log("WeCoza Exam: ExamService::methodName - ...")` on validation failures and caught exceptions; structured return arrays expose error details to callers
- How a future agent inspects this: Run `php tests/exam/verify-exam-service.php` to check entire data layer health; inspect `ExamService::getExamProgress()` output for any tracking_id
- Failure state exposed: Each method returns `['success' => false, 'error' => 'specific message']` — callers (S02/S03) can inspect and display errors

## Inputs

- `src/Learners/Enums/ExamStep.php` — from T01
- `src/Learners/Repositories/ExamRepository.php` — from T02
- `src/Learners/Services/ExamUploadService.php` — from T03
- `src/Learners/Services/ProgressionService.php` — pattern reference

## Expected Output

- `src/Learners/Services/ExamService.php` — complete business logic service with all 4 public methods
- `tests/exam/verify-exam-schema.php` — SQL structure validation script
- `tests/exam/verify-exam-service.php` — comprehensive contract verification script proving entire S01 data layer works
