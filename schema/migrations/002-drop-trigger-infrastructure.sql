-- Migration 002: Drop Trigger Infrastructure
-- Phase 13: Database Cleanup (v1.2 Event Tasks Refactor)
--
-- Removes PostgreSQL trigger infrastructure that auto-generates tasks from class changes.
-- This is a breaking change - task system will not work until Phase 14 rewrites TaskManager.
--
-- Objects removed:
--   - TRIGGER: classes_log_insert_update (on public.classes)
--   - FUNCTION: log_class_change()
--   - TABLE: class_change_logs (includes sequence, indexes, FK constraint)

BEGIN;

-- Set search path
SET search_path TO public;

-- 1. Drop trigger first (depends on function)
DROP TRIGGER IF EXISTS classes_log_insert_update ON classes;

-- 2. Drop function (no longer needed without trigger)
DROP FUNCTION IF EXISTS log_class_change();

-- 3. Drop table (CASCADE removes indexes, sequence, FK constraints)
DROP TABLE IF EXISTS class_change_logs CASCADE;

COMMIT;

-- Verification queries (run after migration to confirm removal)
-- These should all return 0 rows:
--
-- SELECT tgname FROM pg_trigger WHERE tgname = 'classes_log_insert_update';
-- SELECT proname FROM pg_proc WHERE proname = 'log_class_change';
-- SELECT tablename FROM pg_tables WHERE tablename = 'class_change_logs' AND schemaname = 'public';
