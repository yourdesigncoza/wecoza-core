# S01: Exam Data Layer & Service

**Goal:** Exam results can be created, read, and updated via ExamService and ExamRepository against real database tables. Schema SQL is ready for deployment. No UI yet.
**Demo:** Run a PHP verification script that instantiates ExamService, calls all public methods with valid/invalid inputs, and asserts correct behavior (create, read, update, validation errors, completion check).

## Must-Haves

- `learner_exam_results` schema SQL with PK, FK to `learner_lp_tracking`, UNIQUE on `(tracking_id, exam_step)`, CHECK constraints on `exam_step` and `percentage`
- `ExamStep` PHP 8.1 enum with values: `mock_1`, `mock_2`, `mock_3`, `sba`, `final` — with `label()`, `badgeClass()`, `tryFromString()`
- `ExamRepository` extending `BaseRepository` with column whitelisting, parameterized queries, upsert support
- `ExamService` with `recordExamResult()`, `getExamProgress()`, `isExamComplete()`, `getExamResultsForTracking()`
- `ExamUploadService` following `PortfolioUploadService` pattern — separate upload dir (`uploads/exam-documents/`), MIME validation, security files
- Percentage validation (0–100) in service layer
- File upload support for SBA (PDF/DOC/DOCX/JPG/PNG) and certificates (PDF/JPG/PNG)

## Proof Level

- This slice proves: contract
- Real runtime required: yes (PHP execution against WordPress environment for autoloading, but no browser/UI)
- Human/UAT required: no

## Verification

- `php tests/exam/verify-exam-schema.php` — validates schema SQL file has correct table, columns, constraints
- `php tests/exam/verify-exam-service.php` — loads WordPress environment, exercises ExamService methods against real DB (requires schema deployed), validates return types, error handling, and completion logic
- Manual: developer runs `schema/learner_exam_results.sql` against PostgreSQL, then runs verification scripts

## Observability / Diagnostics

- Runtime signals: All errors logged via `error_log("WeCoza Exam: ...")` pattern with context (method name, tracking_id, exam_step, error message)
- Inspection surfaces: `ExamService::getExamProgress()` returns structured array with step statuses; `ExamRepository` queries are inspectable via PostgreSQL query logs
- Failure visibility: Service methods return `['success' => false, 'error' => '...']` arrays with specific error messages; repository exceptions are caught and logged with full context
- Redaction constraints: No PII in logs beyond tracking_id; file paths logged without upload content

## Integration Closure

- Upstream surfaces consumed: `learner_lp_tracking` table (FK target), `BaseRepository` (parent class), `PostgresConnection` (DB access), `PortfolioUploadService` (pattern reference only, not called)
- New wiring introduced in this slice: ExamService + ExamRepository + ExamUploadService + ExamStep enum — all instantiable but not wired into any controller, AJAX handler, or shortcode yet
- What remains before the milestone is truly usable end-to-end: S02 (event/task integration), S03 (AJAX endpoints + UI), S04 (end-to-end testing)

## Tasks

- [x] **T01: Create exam schema SQL and ExamStep enum** `est:30m`
  - Why: Foundation — the DB table and PHP enum are prerequisites for everything else in this slice
  - Files: `schema/learner_exam_results.sql`, `src/Learners/Enums/ExamStep.php`
  - Do: Write schema SQL with all constraints (PK, FK, UNIQUE, CHECKs). Create ExamStep enum following ProgressionStatus pattern exactly. Include verification script that parses the SQL file.
  - Verify: `php tests/exam/verify-exam-schema.php` passes — confirms SQL has correct table name, columns, constraints, and enum values match PHP enum
  - Done when: Schema SQL is deployable, ExamStep enum is loadable, verification script passes

- [x] **T02: Create ExamRepository with CRUD operations** `est:45m`
  - Why: Data access layer — ExamService needs a repository to interact with the database
  - Files: `src/Learners/Repositories/ExamRepository.php`
  - Do: Extend BaseRepository, set table/PK, implement column whitelists, add `findByTrackingId()`, `findByTrackingAndStep()`, `upsert()`, `getProgressForTracking()`. Use parameterized queries and RETURNING clause. Follow LearnerProgressionRepository patterns.
  - Verify: Repository class loads without errors — `php -r "require 'vendor/autoload.php'; new \WeCoza\Learners\Repositories\ExamRepository();"` (or WP bootstrap equivalent)
  - Done when: ExamRepository instantiates, all methods exist with correct signatures and column whitelisting

- [x] **T03: Create ExamUploadService for SBA/certificate files** `est:30m`
  - Why: SBA and final exam steps require file uploads — this service handles validation, storage, and security
  - Files: `src/Learners/Services/ExamUploadService.php`
  - Do: Clone PortfolioUploadService pattern. Upload dir: `uploads/exam-documents/`. Allowed types: PDF, DOC, DOCX, JPG, PNG. Max 10MB. Create security files (.htaccess, index.php) on first upload. Store relative paths.
  - Verify: Class loads without errors; static analysis of allowed MIME types includes image/jpeg, image/png, application/pdf
  - Done when: ExamUploadService instantiates, has `upload()` method accepting file array + metadata, returns relative path or error

- [x] **T04: Create ExamService with business logic and verification script** `est:1h`
  - Why: Ties everything together — the service layer that S02 and S03 will consume. Verification script proves the entire slice works.
  - Files: `src/Learners/Services/ExamService.php`, `tests/exam/verify-exam-service.php`
  - Do: Implement `recordExamResult()` (validates percentage 0-100, delegates to repo upsert + optional upload), `getExamProgress()` (returns all 5 steps with status), `isExamComplete()` (checks all 5 steps have results, final has certificate), `getExamResultsForTracking()`. Write verification script that exercises all methods with assertions. Service returns `['success' => bool, 'data' => ..., 'error' => '...']` arrays.
  - Verify: `php tests/exam/verify-exam-service.php` — all assertions pass (requires schema deployed to DB first)
  - Done when: ExamService fully functional, verification script documents and proves all public method contracts

## Files Likely Touched

- `schema/learner_exam_results.sql`
- `src/Learners/Enums/ExamStep.php`
- `src/Learners/Repositories/ExamRepository.php`
- `src/Learners/Services/ExamUploadService.php`
- `src/Learners/Services/ExamService.php`
- `tests/exam/verify-exam-schema.php`
- `tests/exam/verify-exam-service.php`
