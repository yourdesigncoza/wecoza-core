-- Migration: Add total_pages column to class_type_subjects
-- Purpose: Track total workbook pages per learning programme for page progression display
-- Date: 2026-03-09
-- NOTE: Run this migration manually before testing page progression features.

-- Step 1: Add the total_pages column
ALTER TABLE class_type_subjects
    ADD COLUMN IF NOT EXISTS total_pages INTEGER DEFAULT NULL;

COMMENT ON COLUMN class_type_subjects.total_pages IS
    'Total number of pages in the workbook for this subject. Used to calculate page progression percentage.';

-- Step 2: Seed sensible defaults for existing subjects.
-- Mario can override these values later via the admin panel or direct SQL.
-- Default: 100 pages for all workbook-based subjects (a reasonable starting point).
UPDATE class_type_subjects
   SET total_pages = 100,
       updated_at  = NOW()
 WHERE total_pages IS NULL
   AND is_active = true;
