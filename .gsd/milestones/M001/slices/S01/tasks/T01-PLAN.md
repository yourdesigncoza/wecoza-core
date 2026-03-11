---
estimated_steps: 4
estimated_files: 4
---

# T01: Create exam schema SQL and ExamStep enum

**Slice:** S01 — Exam Data Layer & Service
**Milestone:** M001

## Description

Create the `learner_exam_results` PostgreSQL table schema and the `ExamStep` PHP enum. These are the foundational artifacts — the DB table defines the data shape and the enum provides type-safe step references used by all subsequent code. Also create a schema verification script that validates the SQL file structure.

## Steps

1. Read `schema/wecoza_db_schema_bu_march_10.sql` lines around `learner_lp_tracking` to confirm FK target column name and type
2. Write `schema/learner_exam_results.sql` with: `result_id SERIAL PRIMARY KEY`, `tracking_id INTEGER NOT NULL REFERENCES learner_lp_tracking(tracking_id) ON DELETE CASCADE`, `exam_step VARCHAR(10) NOT NULL CHECK (exam_step IN ('mock_1','mock_2','mock_3','sba','final'))`, `percentage NUMERIC(5,2) CHECK (percentage >= 0 AND percentage <= 100)`, `file_path VARCHAR(500)`, `file_name VARCHAR(255)`, `recorded_by INTEGER`, `recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `UNIQUE(tracking_id, exam_step)`
3. Write `src/Learners/Enums/ExamStep.php` following `ProgressionStatus` pattern — backed string enum with cases MOCK_1, MOCK_2, MOCK_3, SBA, FINAL, plus `label()`, `badgeClass()`, `tryFromString()`, `requiresFile()` (true for sba and final)
4. Write `tests/exam/verify-exam-schema.php` that reads the SQL file and asserts: table name present, all column names present, CHECK constraints present, UNIQUE constraint present, FK reference correct

## Must-Haves

- [ ] Schema SQL is valid PostgreSQL, deployable via `psql -f`
- [ ] FK references `learner_lp_tracking(tracking_id)` with `ON DELETE CASCADE`
- [ ] UNIQUE constraint on `(tracking_id, exam_step)` prevents duplicate results
- [ ] CHECK constraint on `exam_step` matches exactly the 5 enum values
- [ ] CHECK constraint on `percentage` enforces 0–100 range
- [ ] ExamStep enum has all 5 cases with correct string backing values
- [ ] `requiresFile()` method returns true for SBA and FINAL only
- [ ] Verification script validates SQL structure

## Verification

- Run `php tests/exam/verify-exam-schema.php` — all checks pass
- Run `php -l src/Learners/Enums/ExamStep.php` — no syntax errors
- Run `php -r "require_once 'src/Learners/Enums/ExamStep.php'; echo WeCoza\Learners\Enums\ExamStep::MOCK_1->value;"` — outputs `mock_1`

## Observability Impact

- Signals added/changed: None (schema + enum are static artifacts)
- How a future agent inspects this: Read `schema/learner_exam_results.sql`, inspect `ExamStep::cases()`
- Failure state exposed: Schema verification script reports specific missing elements

## Inputs

- `schema/wecoza_db_schema_bu_march_10.sql` — FK target table structure
- `src/Learners/Enums/ProgressionStatus.php` — enum pattern to follow

## Expected Output

- `schema/learner_exam_results.sql` — deployable DDL for the exam results table
- `src/Learners/Enums/ExamStep.php` — PHP 8.1 string-backed enum with 5 cases and helper methods
- `tests/exam/verify-exam-schema.php` — schema structure validation script
