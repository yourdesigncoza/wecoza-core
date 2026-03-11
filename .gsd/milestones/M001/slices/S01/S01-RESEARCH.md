# S01: Exam Data Layer & Service — Research

**Date:** 2026-03-11

## Summary

S01 requires a new `learner_exam_results` table, an `ExamStep` enum, an `ExamRepository`, and an `ExamService`. The codebase has mature, well-established patterns for all of these — the existing `LearnerProgressionRepository`, `ProgressionService`, `PortfolioUploadService`, and `ProgressionStatus` enum provide direct templates to follow. The DB schema is PostgreSQL with serial PKs, FK constraints, and CHECK constraints.

The main complexity is wiring exam completion into the existing `ProgressionService::markLPComplete()` as an alternative to the POE (portfolio) path. The `classes.exam_class` boolean already exists and can be used to determine flow. File uploads for SBA scans and certificates follow the `PortfolioUploadService` pattern exactly.

No external libraries or unfamiliar technologies are involved — this is pure PHP 8.0+ with PDO/PostgreSQL.

## Recommendation

Follow the existing patterns exactly:

1. **Schema:** Create `learner_exam_results` table with `tracking_id` FK to `learner_lp_tracking`, `exam_step` VARCHAR with CHECK constraint, `percentage` NUMERIC, optional file columns, audit columns. Write SQL to `schema/learner_exam_results.sql`.
2. **Enum:** Create `ExamStep` PHP enum in `src/Learners/Enums/ExamStep.php` mirroring `ProgressionStatus` pattern.
3. **Repository:** Create `ExamRepository` in `src/Learners/Repositories/` extending `BaseRepository`, following `LearnerProgressionRepository` patterns (column whitelisting, parameterized queries, error handling).
4. **Service:** Create `ExamService` in `src/Learners/Services/` following `ProgressionService` patterns. Methods: `recordExamResult()`, `getExamProgress()`, `isExamComplete()`, `getExamResultsForTracking()`.
5. **Upload:** Create `ExamUploadService` in `src/Learners/Services/` following `PortfolioUploadService` exactly — separate upload dir (`uploads/exam-documents/`), same validation, same security (index.php, .htaccess).

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| DB connection | `wecoza_db()` / `PostgresConnection::getInstance()` | Singleton, lazy-loaded, all repos use it |
| Repository base | `BaseRepository` (core/Abstract/) | Column whitelisting, common CRUD patterns |
| File upload security | `PortfolioUploadService` pattern | `.htaccess`, `index.php`, MIME validation, extension whitelist |
| PHP enum pattern | `ProgressionStatus` enum | `label()`, `badgeClass()`, `tryFromString()` — proven pattern |
| Error handling | `error_log("WeCoza Core: ...")` pattern | Consistent across all repos/services |

## Existing Code and Patterns

- `src/Learners/Repositories/LearnerProgressionRepository.php` — Direct template for `ExamRepository`. Uses `baseQuery()` with JOINs, column whitelist in `insert()`/`update()`, `savePortfolioFile()` pattern for related table inserts. Uses `RETURNING` clause for insert IDs.
- `src/Learners/Services/ProgressionService.php` — Template for `ExamService`. Key method `markLPComplete()` currently requires portfolio upload — needs exam-path alternative. `recordProgression()` writes to legacy table on completion.
- `src/Learners/Services/PortfolioUploadService.php` — Clone for `ExamUploadService`. Uses `wp_upload_dir()`, `wp_mkdir_p()`, `finfo_open()` for MIME detection, 10MB limit, PDF/DOC/DOCX only. Also adds image types for SBA scans (JPG/PNG).
- `src/Learners/Enums/ProgressionStatus.php` — Template for `ExamStep` enum. Uses `string` backing type, `label()`, `badgeClass()`, `tryFromString()`.
- `src/Classes/Models/ClassModel.php` — Already has `examClass` (bool), `examType` (varchar), `examLearners` (JSONB array of learner IDs). These fields exist in the `classes` table and are fully wired.
- `core/Abstract/BaseRepository.php` — Base class with `$db`, `$table`, `$primaryKey`. Child repos override and add custom queries.
- `src/Learners/Models/LearnerProgressionModel.php` — Extends `BaseModel`. Has `$casts` for type safety, getters/setters. `ExamResultModel` would follow same pattern but is optional for S01 (repository-only approach works).

## Constraints

- **Read-only DB from agent** — Schema SQL must be written to `schema/` directory; developer runs it manually.
- **PHP 8.0+** — Can use enums, match expressions, typed properties, named arguments.
- **PostgreSQL** — Use `RETURNING` for insert IDs, `CHECK` constraints for enum validation, `SERIAL` for auto-increment PKs.
- **FK constraint** — `learner_exam_results.tracking_id` must reference `learner_lp_tracking.tracking_id`. Use `ON DELETE CASCADE` (if tracking is deleted, exam results go too — same pattern as `learner_progression_portfolios`).
- **Single LP constraint** — Only one `in_progress` LP per learner. Exam results attach to the tracking record, not the learner directly (D002).
- **Namespace** — New files go under `WeCoza\Learners\` namespace (PSR-4 mapped to `src/Learners/`).
- **Allowed file types for SBA/certificates** — Need to expand beyond PDF/DOC to include images (JPG, PNG) for scanned documents. JPEG MIME types: `image/jpeg`, `image/png`.

## Common Pitfalls

- **Forgetting CHECK constraint on exam_step** — Without a DB-level CHECK, invalid step values could be inserted. Use `CHECK (exam_step IN ('mock_1', 'mock_2', 'mock_3', 'sba', 'final'))` in the schema.
- **Duplicate exam results** — Need a UNIQUE constraint on `(tracking_id, exam_step)` to prevent recording the same step twice. Updates should use upsert or check-then-insert pattern.
- **File path inconsistency** — `PortfolioUploadService` stores relative paths (`portfolios/filename.pdf`). `ExamUploadService` must follow the same convention (`exam-documents/filename.pdf`), not absolute paths.
- **Not handling re-recording** — Office staff may need to update a previously recorded exam result (e.g., correcting a percentage). The service should support both create and update, not just insert.
- **Missing percentage validation** — Percentages should be 0-100. Validate in service layer AND in DB with `CHECK (percentage >= 0 AND percentage <= 100)`.

## Open Risks

- **Exam completion triggering LP completion** — `ProgressionService::markLPComplete()` currently couples portfolio upload with completion. S01 should NOT modify this method yet (that's S03/S04 territory), but the `ExamService::isExamComplete()` method must be designed so it can be consumed by `markLPComplete()` later. Risk: if the completion interface is wrong, S03 has to rework it.
- **exam_learners JSONB usage** — The `classes.exam_learners` column stores which learners in a class are exam-track. The `ExamService` needs to verify a learner is in this list before recording results. But the JSONB format (array of learner IDs) is not guaranteed — need to verify the actual data shape in production.
- **No existing unit test infrastructure** — The `tests/` directory exists but test coverage is minimal. Contract verification for ExamService logic will need to be done via PHP script execution or AJAX endpoint testing rather than formal PHPUnit.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | Available in `<available_skills>` | Not needed — no WP-specific skill required |
| PostgreSQL | N/A | Standard PDO usage, no special skill needed |
| PHP 8.0 Enums | N/A | Well-understood, existing pattern in codebase |

No external skills are needed for this slice — it's entirely internal PHP/PostgreSQL work following existing patterns.

## Sources

- `schema/wecoza_db_schema_bu_march_10.sql` — Current DB schema with `learner_lp_tracking` (lines 1584-1650), `learner_progression_portfolios` (lines 1770-1800), `classes.exam_class/exam_type/exam_learners` (lines 837-854)
- `src/Learners/Services/ProgressionService.php` — LP lifecycle service, `markLPComplete()` method at line 121
- `src/Learners/Repositories/LearnerProgressionRepository.php` — Full repository pattern with `insert()`, `update()`, `savePortfolioFile()`
- `src/Learners/Services/PortfolioUploadService.php` — File upload pattern with security measures
- `src/Learners/Enums/ProgressionStatus.php` — PHP 8.1 enum pattern
- `.gsd/DECISIONS.md` — D001 (single table), D002 (FK to tracking), D003 (upload pattern)
