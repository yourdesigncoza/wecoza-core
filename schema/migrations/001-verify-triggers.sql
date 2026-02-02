-- Migration: 001-verify-triggers.sql
-- Purpose: Ensure class change logging triggers exist and function correctly
-- Idempotent: Safe to run multiple times (uses CREATE OR REPLACE)
-- Date: 2026-02-02

BEGIN;

-- Set search_path for consistent schema resolution
SET search_path TO public;

-- =============================================================================
-- FUNCTION: log_class_change()
-- Logs INSERT and UPDATE operations on classes table to class_change_logs
-- Computes JSONB diff for UPDATE operations
-- Sends pg_notify for real-time listeners
-- =============================================================================
CREATE OR REPLACE FUNCTION public.log_class_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    op TEXT := TG_OP;
    event_time TIMESTAMP WITHOUT TIME ZONE := NOW();
    new_data JSONB := to_jsonb(NEW);
    old_data JSONB := CASE WHEN TG_OP = 'UPDATE' THEN to_jsonb(OLD) ELSE NULL END;
    diff JSONB := '{}'::jsonb;
BEGIN
    IF op = 'UPDATE' THEN
        diff := (
            SELECT COALESCE(
                jsonb_object_agg(key, jsonb_build_object('old', old_data -> key, 'new', new_data -> key)),
                '{}'::jsonb
            )
            FROM (
                SELECT key FROM jsonb_object_keys(new_data) AS new_keys(key)
                UNION
                SELECT key FROM jsonb_object_keys(COALESCE(old_data, '{}'::jsonb)) AS old_keys(key)
            ) AS keys(key)
            WHERE (old_data -> key) IS DISTINCT FROM (new_data -> key)
        );
    ELSE
        diff := new_data;
    END IF;

    INSERT INTO public.class_change_logs (class_id, operation, changed_at, new_row, old_row, diff)
    VALUES (NEW.class_id, op, event_time, new_data, old_data, diff);

    PERFORM pg_notify(
        'class_change_channel',
        json_build_object(
            'operation', op,
            'class_id', NEW.class_id,
            'class_code', NEW.class_code,
            'class_subject', NEW.class_subject,
            'changed_at', event_time,
            'diff', diff
        )::text
    );

    RETURN NEW;
END;
$$;

COMMENT ON FUNCTION public.log_class_change() IS
'Trigger function that logs class INSERT/UPDATE to class_change_logs with JSONB diff calculation';

-- =============================================================================
-- TRIGGER: classes_log_insert_update
-- Fires AFTER INSERT OR UPDATE on public.classes
-- =============================================================================
DROP TRIGGER IF EXISTS classes_log_insert_update ON public.classes;
CREATE TRIGGER classes_log_insert_update
    AFTER INSERT OR UPDATE ON public.classes
    FOR EACH ROW
    EXECUTE FUNCTION public.log_class_change();

-- =============================================================================
-- FUNCTION: update_updated_at_column()
-- Generic trigger function to auto-update updated_at timestamp
-- =============================================================================
CREATE OR REPLACE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

COMMENT ON FUNCTION public.update_updated_at_column() IS
'Generic trigger function to auto-update updated_at column on UPDATE';

-- =============================================================================
-- VERIFICATION: Confirm triggers exist
-- =============================================================================
DO $$
DECLARE
    trigger_count INT;
    function_count INT;
BEGIN
    -- Check trigger exists
    SELECT COUNT(*) INTO trigger_count
    FROM pg_trigger t
    JOIN pg_class c ON t.tgrelid = c.oid
    JOIN pg_namespace n ON c.relnamespace = n.oid
    WHERE t.tgname = 'classes_log_insert_update'
      AND c.relname = 'classes'
      AND n.nspname = 'public';

    IF trigger_count = 0 THEN
        RAISE EXCEPTION 'Migration verification failed: classes_log_insert_update trigger not found on public.classes';
    END IF;

    -- Check function exists
    SELECT COUNT(*) INTO function_count
    FROM pg_proc p
    JOIN pg_namespace n ON p.pronamespace = n.oid
    WHERE p.proname = 'log_class_change'
      AND n.nspname = 'public';

    IF function_count = 0 THEN
        RAISE EXCEPTION 'Migration verification failed: public.log_class_change() function not found';
    END IF;

    RAISE NOTICE 'Migration verification successful: trigger and function exist';
END $$;

COMMIT;
