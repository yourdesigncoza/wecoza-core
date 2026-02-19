-- =============================================================================
-- Migration: Drop legacy `products` table and all empty dependent tables
--
-- The `products` table was created during Oct 2024 planning phase with dummy
-- data ("Data Visualization", "Creative Writing Workshop", etc.) that never
-- reflected real WeCoza subjects. The real source of truth is
-- `class_type_subjects` (COMM, NUM, BA2LP1, etc.) — already used by class
-- forms and LP tracking (after the products-to-subjects refactor).
--
-- All dependent tables have 0 rows. Safe to drop entirely.
--
-- Tables dropped (7):
--   - exam_results        (0 rows, FKs to exams)
--   - exams               (0 rows, FKs to products, learners)
--   - class_subjects      (0 rows, FKs to classes, products)
--   - learner_products    (0 rows, FKs to learners, products)
--   - learner_progressions(0 rows, FKs to learners, products — columns already
--                          renamed to from_subject_id/to_subject_id but FK
--                          constraints still reference products)
--   - progress_reports    (0 rows, FKs to classes, learners, products)
--   - products            (15 rows dummy seed data)
--
-- Sequences dropped (4):
--   - exam_results_result_id_seq
--   - exams_exam_id_seq
--   - progress_reports_report_id_seq
--   - learner_progressions_progression_id_seq
--   - products_product_id_seq
--
-- NOT dropped (still in use):
--   - learner_lp_tracking          (active LP tracking, refactored to use class_type_subjects)
--   - learner_hours_log            (active hours log, refactored to use class_type_subjects)
--   - learner_progression_portfolios (FKs to learner_lp_tracking, not products)
--   - class_type_subjects          (the real source of truth)
--
-- Prerequisites: Run migration_products_to_subjects.sql first (already done).
-- Run manually against PostgreSQL.
-- =============================================================================

BEGIN;

-- -------------------------------------------------------------------------
-- Safety check: verify all tables are empty before dropping
-- Abort if any table has data (prevents accidental data loss)
-- -------------------------------------------------------------------------
DO $$
DECLARE
    _count BIGINT;
BEGIN
    SELECT count(*) INTO _count FROM exam_results;
    IF _count > 0 THEN RAISE EXCEPTION 'exam_results has % rows — aborting', _count; END IF;

    SELECT count(*) INTO _count FROM exams;
    IF _count > 0 THEN RAISE EXCEPTION 'exams has % rows — aborting', _count; END IF;

    SELECT count(*) INTO _count FROM class_subjects;
    IF _count > 0 THEN RAISE EXCEPTION 'class_subjects has % rows — aborting', _count; END IF;

    SELECT count(*) INTO _count FROM learner_products;
    IF _count > 0 THEN RAISE EXCEPTION 'learner_products has % rows — aborting', _count; END IF;

    SELECT count(*) INTO _count FROM learner_progressions;
    IF _count > 0 THEN RAISE EXCEPTION 'learner_progressions has % rows — aborting', _count; END IF;

    SELECT count(*) INTO _count FROM progress_reports;
    IF _count > 0 THEN RAISE EXCEPTION 'progress_reports has % rows — aborting', _count; END IF;

    RAISE NOTICE 'All tables verified empty — proceeding with drop';
END $$;

-- -------------------------------------------------------------------------
-- Drop child tables first (those that FK to products or to other children)
-- -------------------------------------------------------------------------

-- exam_results → exams (must drop before exams)
DROP TABLE IF EXISTS exam_results CASCADE;

-- These all FK directly to products (and to other parent tables like learners, classes)
DROP TABLE IF EXISTS exams CASCADE;
DROP TABLE IF EXISTS class_subjects CASCADE;
DROP TABLE IF EXISTS learner_products CASCADE;
DROP TABLE IF EXISTS learner_progressions CASCADE;
DROP TABLE IF EXISTS progress_reports CASCADE;

-- -------------------------------------------------------------------------
-- Drop the products table itself (self-referencing FK: parent_product_id)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS products CASCADE;

-- -------------------------------------------------------------------------
-- Drop orphaned sequences (CASCADE above handles owned sequences,
-- but explicit cleanup for any that survive)
-- -------------------------------------------------------------------------
DROP SEQUENCE IF EXISTS exam_results_result_id_seq;
DROP SEQUENCE IF EXISTS exams_exam_id_seq;
DROP SEQUENCE IF EXISTS progress_reports_report_id_seq;
DROP SEQUENCE IF EXISTS learner_progressions_progression_id_seq;
DROP SEQUENCE IF EXISTS products_product_id_seq;

COMMIT;

-- -------------------------------------------------------------------------
-- Verification (run after commit)
-- -------------------------------------------------------------------------
-- SELECT tablename FROM pg_tables
-- WHERE schemaname = 'public'
--   AND tablename IN ('products', 'class_subjects', 'learner_products',
--                     'exams', 'exam_results', 'progress_reports',
--                     'learner_progressions');
-- Expected: 0 rows
