-- Migration: Rewire LP tracking from `products` to `class_type_subjects`
--
-- This migration:
-- 1. Clears test data (references old product_ids)
-- 2. Drops old FK constraints referencing products table
-- 3. Renames product_id -> class_type_subject_id in learner_lp_tracking and learner_hours_log
-- 4. Adds new FK constraints referencing class_type_subjects table
-- 5. Renames legacy learner_progressions columns for consistency
--
-- Run this BEFORE deploying the updated PHP/JS code.

BEGIN;

-- Clear test data (references old product_ids that won't exist as class_type_subject_ids)
DELETE FROM learner_hours_log;
DELETE FROM learner_lp_tracking;

-- Drop old FK constraints
ALTER TABLE learner_lp_tracking DROP CONSTRAINT IF EXISTS fk_lp_tracking_product;
ALTER TABLE learner_hours_log DROP CONSTRAINT IF EXISTS fk_hours_log_product;

-- Rename columns
ALTER TABLE learner_lp_tracking RENAME COLUMN product_id TO class_type_subject_id;
ALTER TABLE learner_hours_log RENAME COLUMN product_id TO class_type_subject_id;

-- Add new FK constraints
ALTER TABLE learner_lp_tracking
  ADD CONSTRAINT fk_lp_tracking_subject
  FOREIGN KEY (class_type_subject_id) REFERENCES class_type_subjects(class_type_subject_id);

ALTER TABLE learner_hours_log
  ADD CONSTRAINT fk_hours_log_subject
  FOREIGN KEY (class_type_subject_id) REFERENCES class_type_subjects(class_type_subject_id);

-- Legacy table (learner_progressions) — rename for consistency
ALTER TABLE learner_progressions RENAME COLUMN from_product_id TO from_subject_id;
ALTER TABLE learner_progressions RENAME COLUMN to_product_id TO to_subject_id;

COMMIT;
