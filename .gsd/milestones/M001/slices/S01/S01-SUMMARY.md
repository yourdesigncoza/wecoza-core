---
id: S01
parent: M001
milestone: M001
provides:
  - learner_exam_results PostgreSQL schema DDL (deployable)
  - ExamStep PHP 8.1 string-backed enum (mock_1, mock_2, mock_3, sba, final)
  - ExamRepository with CRUD, upsert, and progress query
  - ExamUploadService for SBA/certificate file uploads with MIME validation
  - ExamService with recordExamResult, getExamProgress, isExamComplete, getExamResultsForTracking
  - Schema verification script (20 checks) and service verification script (46 checks)
requires: []
affects:
  - S02
  - S03
  - S04
key_files:
  - schema/learner_exam_results.sql
  - src/Learners/Enums/ExamStep.php
  - src/Learners/Repositories/ExamRepository.php
  - src/Learners/Services/ExamUploadService.php
  - src/Learners/Services/ExamService.php
  - tests/exam/verify-exam-schema.php
  - tests/exam/verify-exam-service.php
key_decisions:
  - D001 Single learner_exam_results table with step enum column
  - D002 FK to learner_lp_tracking (not learner directly) for per-attempt results
  - D003 Separate ExamUploadService following PortfolioUploadService pattern
  - D005 Constructor injection with null-coalescing defaults for testability
  - D006 Consistent service return format ['success', 'data', 'error'] across all methods
patterns_established:
  - ExamStep enum follows ProgressionStatus pattern with label(), badgeClass(), tryFromString() plus domain-specific requiresFile()
  - ExamRepository extends BaseRepository with column whitelists and upsert via INSERT ON CONFLICT DO UPDATE with RETURNING
  - ExamUploadService stores relative paths with tracking_id + exam_step in filename for traceability
  - All ExamService methods return ['success' => bool, 'data' => array, 'error' => string] — never throw to callers
observability_surfaces:
  - error_log("WeCoza Exam: ClassName::method - ...") with tracking_id, step, and error context on all caught exceptions
  - ExamService::getExamProgress() returns structured {steps, completion_percentage, completed_count, total_steps}
  - All failure paths return ['success' => false, 'error' => 'specific message']
  - php tests/exam/verify-exam-service.php — 46-check health check covering enum, repo, upload, service, validation, and DB CRUD
drill_down_paths:
  - .gsd/milestones/M001/slices/S01/tasks/T01-SUMMARY.md
  - .gsd/milestones/M001/slices/S01/tasks/T02-SUMMARY.md
  - .gsd/milestones/M001/slices/S01/tasks/T03-SUMMARY.md
  - .gsd/milestones/M001/slices/S01/tasks/T04-SUMMARY.md
duration: ~40min
verification_result: passed
completed_at: 2026-03-11
---

# S01: Exam Data Layer & Service

**Deployable exam results schema, type-safe ExamStep enum, and complete ExamService data layer — verified with 66 automated checks against real PostgreSQL.**

## What Happened

Built the full exam data layer in four tasks:

1. **Schema & Enum (T01):** Created `learner_exam_results` DDL with PK, FK to `learner_lp_tracking(tracking_id) ON DELETE CASCADE`, UNIQUE on `(tracking_id, exam_step)`, CHECK constraints on the 5 exam step values and percentage range 0–100. Created `ExamStep` PHP 8.1 string-backed enum with `label()`, `badgeClass()`, `requiresFile()` (true for sba/final only), and nullable `tryFromString()`.

2. **Repository (T02):** Created `ExamRepository` extending `BaseRepository` with column whitelists (insert:8, update:5, filter:6, order:6). Key methods: `findByTrackingId()`, `findByTrackingAndStep()`, `upsert()` (INSERT ON CONFLICT DO UPDATE with RETURNING), and `getProgressForTracking()` (returns all 5 steps keyed by enum value with data or null).

3. **Upload Service (T03):** Created `ExamUploadService` following `PortfolioUploadService` pattern. Upload dir: `uploads/exam-documents/`. Accepts PDF/DOC/DOCX/JPG/PNG up to 10MB. Uses `finfo` for MIME validation. Creates `.htaccess` + `index.php` security files. Filenames include `{tracking_id}_{exam_step}_{uniqid}` for traceability.

4. **Service & Verification (T04):** Created `ExamService` with constructor injection (optional ExamRepository + ExamUploadService). Four public methods: `recordExamResult()` validates percentage 0–100 then delegates to repo upsert + optional upload; `getExamProgress()` returns all 5 steps with completion stats; `isExamComplete()` checks all 5 steps recorded + final has certificate; `getExamResultsForTracking()` passes through raw results. Created two verification scripts totalling 66 checks.

## Verification

- `php tests/exam/verify-exam-schema.php` — **20/20 passed**: DDL structure, all 9 columns, CHECK constraints, UNIQUE, FK, CASCADE
- `php tests/exam/verify-exam-service.php` — **46/46 passed**: ExamStep enum (8), ExamRepository (5), ExamUploadService (3), ExamService (5), validation (7), return format (2), DB-dependent CRUD/upsert/progress/completion (16)
- All verification scripts clean up test data after execution

## Deviations

None. All four tasks executed as planned.

## Known Limitations

- Schema must be manually deployed by developer (`psql -f schema/learner_exam_results.sql`) — not auto-migrated
- ExamService, ExamRepository, and ExamUploadService are instantiable but not wired into any controller, AJAX handler, or shortcode yet
- No UI, no AJAX endpoints, no event/task integration — those are S02 and S03

## Follow-ups

None discovered during execution.

## Files Created/Modified

- `schema/learner_exam_results.sql` — DDL for learner_exam_results table with all constraints
- `src/Learners/Enums/ExamStep.php` — ExamStep string-backed enum (5 cases + helper methods)
- `src/Learners/Repositories/ExamRepository.php` — Repository with CRUD, upsert, and progress query
- `src/Learners/Services/ExamUploadService.php` — File upload service with MIME validation and security
- `src/Learners/Services/ExamService.php` — Business logic service orchestrating repo and upload
- `tests/exam/verify-exam-schema.php` — Schema SQL structure verification (20 checks)
- `tests/exam/verify-exam-service.php` — Full S01 data layer verification (46 checks)

## Forward Intelligence

### What the next slice should know
- `ExamService` is the primary entry point for S02 and S03. Constructor accepts optional deps — pass mocks for unit testing.
- `ExamStep::requiresFile()` returns true for `sba` and `final` only — use this to conditionally show file upload UI in S03.
- `getExamProgress()` returns a structured array with `steps` (keyed by step value), `completion_percentage`, `completed_count`, and `total_steps` — S03 UI can render directly from this.
- `isExamComplete()` requires all 5 steps recorded AND final step must have a non-empty `file_path` (certificate uploaded).

### What's fragile
- `ExamRepository::upsert()` depends on the UNIQUE constraint `(tracking_id, exam_step)` — if that constraint is missing from the deployed schema, upserts will create duplicates instead of updating.
- `ExamUploadService` creates the upload directory on first use — if the web server user lacks write permission to `wp-content/uploads/`, uploads will silently fail with a logged error.

### Authoritative diagnostics
- `php tests/exam/verify-exam-service.php` — runs 46 checks including DB-dependent tests; if this passes, the entire data layer is healthy.
- PHP error log entries matching `"WeCoza Exam:"` — all service/repository errors are logged with method name, tracking_id, step, and error message.

### What assumptions changed
- No assumptions changed — the existing `learner_lp_tracking` table and `BaseRepository` worked exactly as expected.
