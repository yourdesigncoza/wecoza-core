---
id: T01
parent: S01
milestone: M001
provides:
  - learner_exam_results PostgreSQL schema DDL
  - ExamStep PHP 8.1 string-backed enum with 5 cases
  - Schema verification test script
key_files:
  - schema/learner_exam_results.sql
  - src/Learners/Enums/ExamStep.php
  - tests/exam/verify-exam-schema.php
key_decisions:
  - ExamStep enum returns nullable from tryFromString (unlike ProgressionStatus which defaults to IN_PROGRESS) since there's no sensible default exam step
patterns_established:
  - Enum pattern follows ProgressionStatus with label(), badgeClass(), tryFromString() plus domain-specific requiresFile()
observability_surfaces:
  - none (static artifacts only)
duration: ~5min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T01: Create exam schema SQL and ExamStep enum

**Created deployable PostgreSQL DDL for `learner_exam_results` table and type-safe `ExamStep` enum with all helper methods.**

## What Happened

1. Confirmed `learner_lp_tracking.tracking_id` is `integer NOT NULL` — FK target verified.
2. Created `schema/learner_exam_results.sql` with all specified columns, CHECK constraints on `exam_step` (5 values) and `percentage` (0–100), FK to `learner_lp_tracking(tracking_id) ON DELETE CASCADE`, and UNIQUE constraint on `(tracking_id, exam_step)`.
3. Created `src/Learners/Enums/ExamStep.php` following the `ProgressionStatus` pattern — 5 string-backed cases with `label()`, `badgeClass()`, `requiresFile()` (true for SBA/FINAL only), and `tryFromString()`.
4. Created `tests/exam/verify-exam-schema.php` that validates the SQL file structure (table name, all 9 columns, FK reference, ON DELETE CASCADE, all 5 CHECK values, percentage range checks, UNIQUE constraint).

## Verification

- `php tests/exam/verify-exam-schema.php` — **20/20 checks passed**
- `php -l src/Learners/Enums/ExamStep.php` — no syntax errors
- `php -r "require_once '...'; echo ExamStep::MOCK_1->value;"` — outputs `mock_1`
- `requiresFile()` confirmed: true for sba/final, false for mock_1/mock_2/mock_3

### Slice-level verification status
- ✅ `php tests/exam/verify-exam-schema.php` — passes (20/20)
- ⬜ `php tests/exam/verify-exam-service.php` — not yet created (T03)
- ⬜ Manual schema deployment — developer action

## Diagnostics

Static artifacts only. Future agent inspects via:
- `cat schema/learner_exam_results.sql` for table structure
- `php -r "require_once '...'; print_r(ExamStep::cases());"` for enum values

## Deviations

- `tryFromString()` returns `?self` (nullable) instead of defaulting like ProgressionStatus, since there's no sensible default exam step.

## Known Issues

None.

## Files Created/Modified

- `schema/learner_exam_results.sql` — DDL for learner_exam_results table
- `src/Learners/Enums/ExamStep.php` — ExamStep string-backed enum (5 cases + helpers)
- `tests/exam/verify-exam-schema.php` — Schema SQL structure validation script
