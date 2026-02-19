-- =============================================================================
-- Migration: Drop all legacy/unused tables
--
-- These 16 tables have 0 rows and no active code references in the codebase.
-- They were created during Oct 2024 planning phase and never wired up, or were
-- superseded by JSONB columns in the classes table / WordPress systems.
--
-- Tables dropped (16):
--   agent_orders           — no code
--   agent_replacements     — superseded by JSONB backup_agent_ids in classes
--   attendance_registers   — no code, hours tracked via learner_hours_log
--   class_agents           — superseded by JSONB class_agent in classes
--   class_notes            — superseded by JSONB class_notes_data in classes
--   class_schedules        — superseded by JSONB schedule in classes
--   collections            — no code
--   deliveries             — no code
--   files                  — no code, uploads via PortfolioUploadService
--   history                — no code, audit trail never implemented
--   latest_document        — no code
--   sites_address_audit    — no code, audit trail never wired
--   sites_migration_backup — one-time migration backup, no longer needed
--   user_permissions       — WordPress capabilities used instead
--   user_roles             — WordPress roles used instead
--   users                  — WordPress wp_users used instead
--
-- FK constraints dropped from ACTIVE tables (before dropping users):
--   classes.project_supervisor_id → users  (constraint dropped, column kept)
--   client_communications.user_id → users  (constraint dropped, column kept)
--
-- Sequences dropped with CASCADE (owned by dropped tables).
--
-- Run manually against PostgreSQL.
-- =============================================================================

BEGIN;

-- -------------------------------------------------------------------------
-- Step 0: Drop FK constraints from ACTIVE tables that reference `users`
-- (must happen before we can DELETE from users)
-- -------------------------------------------------------------------------
ALTER TABLE classes DROP CONSTRAINT IF EXISTS classes_project_supervisor_id_fkey;
ALTER TABLE client_communications DROP CONSTRAINT IF EXISTS client_communications_user_id_fkey;

-- -------------------------------------------------------------------------
-- Clear legacy seed/test data from tables that have rows
-- (none of these are referenced by active application code)
-- -------------------------------------------------------------------------
DELETE FROM user_permissions;      -- 54 rows: legacy permission mappings (WordPress capabilities used)
DELETE FROM sites_address_audit;   -- 35 rows: old site address snapshots from June 2025
DELETE FROM sites_migration_backup;-- 35 rows: one-time migration backup
DELETE FROM user_roles;            -- 16 rows: legacy role definitions (WordPress roles used)
DELETE FROM users;                 -- 16 rows: legacy user accounts (WordPress wp_users used)

-- -------------------------------------------------------------------------
-- Safety check: verify all tables are now empty
-- -------------------------------------------------------------------------
DO $$
DECLARE
    _tbl  TEXT;
    _count BIGINT;
    _tables TEXT[] := ARRAY[
        'agent_orders', 'agent_replacements', 'attendance_registers',
        'class_agents', 'class_notes', 'class_schedules',
        'collections', 'deliveries', 'files', 'history',
        'latest_document', 'sites_address_audit', 'sites_migration_backup',
        'user_permissions', 'user_roles', 'users'
    ];
BEGIN
    FOREACH _tbl IN ARRAY _tables LOOP
        EXECUTE format('SELECT count(*) FROM %I', _tbl) INTO _count;
        IF _count > 0 THEN
            RAISE EXCEPTION 'Table % still has % rows — aborting', _tbl, _count;
        END IF;
    END LOOP;
    RAISE NOTICE 'All 16 tables verified empty — proceeding with drop';
END $$;

-- -------------------------------------------------------------------------
-- Step 1: Drop tables (CASCADE handles owned sequences + child FKs)
-- Order: children first, then parents
-- -------------------------------------------------------------------------

-- Tables that FK to other dead tables (user_permissions → users)
DROP TABLE IF EXISTS user_permissions CASCADE;

-- Tables that FK only to active tables (agents, classes, learners)
DROP TABLE IF EXISTS agent_orders CASCADE;
DROP TABLE IF EXISTS agent_replacements CASCADE;
DROP TABLE IF EXISTS attendance_registers CASCADE;
DROP TABLE IF EXISTS class_agents CASCADE;
DROP TABLE IF EXISTS class_notes CASCADE;
DROP TABLE IF EXISTS class_schedules CASCADE;
DROP TABLE IF EXISTS collections CASCADE;
DROP TABLE IF EXISTS deliveries CASCADE;
DROP TABLE IF EXISTS files CASCADE;
DROP TABLE IF EXISTS history CASCADE;
DROP TABLE IF EXISTS latest_document CASCADE;

-- Standalone tables (no FKs or self-referencing only)
DROP TABLE IF EXISTS sites_address_audit CASCADE;
DROP TABLE IF EXISTS sites_migration_backup CASCADE;
DROP TABLE IF EXISTS user_roles CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- -------------------------------------------------------------------------
-- Step 2: Drop orphaned sequences (CASCADE above handles owned ones,
-- but explicit cleanup for any that survive)
-- -------------------------------------------------------------------------
DROP SEQUENCE IF EXISTS agent_orders_order_id_seq;
DROP SEQUENCE IF EXISTS agent_replacements_replacement_id_seq;
DROP SEQUENCE IF EXISTS attendance_registers_register_id_seq;
DROP SEQUENCE IF EXISTS class_notes_note_id_seq;
DROP SEQUENCE IF EXISTS class_schedules_schedule_id_seq;
DROP SEQUENCE IF EXISTS collections_collection_id_seq;
DROP SEQUENCE IF EXISTS deliveries_delivery_id_seq;
DROP SEQUENCE IF EXISTS files_file_id_seq;
DROP SEQUENCE IF EXISTS history_history_id_seq;
DROP SEQUENCE IF EXISTS user_permissions_permission_id_seq;
DROP SEQUENCE IF EXISTS user_roles_role_id_seq;
DROP SEQUENCE IF EXISTS users_user_id_seq;

COMMIT;

-- -------------------------------------------------------------------------
-- Verification (run after commit)
-- -------------------------------------------------------------------------
-- SELECT tablename FROM pg_tables
-- WHERE schemaname = 'public'
--   AND tablename IN (
--     'agent_orders', 'agent_replacements', 'attendance_registers',
--     'class_agents', 'class_notes', 'class_schedules',
--     'collections', 'deliveries', 'files', 'history',
--     'latest_document', 'sites_address_audit', 'sites_migration_backup',
--     'user_permissions', 'user_roles', 'users'
--   );
-- Expected: 0 rows
--
-- Verify active tables still work:
-- SELECT count(*) FROM classes;
-- SELECT count(*) FROM client_communications;
