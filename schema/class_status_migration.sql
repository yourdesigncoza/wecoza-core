-- =============================================================================
-- WeCoza Core: Class Activation Status System Migration
-- Requirements: WEC-179 / WEC-180
-- =============================================================================
--
-- PURPOSE: Adds an explicit class_status column ('draft'|'active'|'stopped')
--          to the classes table, replacing the implicit status derived from
--          the presence/absence of order_nr.
--
-- EXECUTE MANUALLY: Run this SQL against the PostgreSQL database.
--
-- REMINDER: If the class_attendance_sessions table does not yet exist
-- (Phase 48 SQL has not been applied), also execute:
--   schema/class_attendance_sessions.sql
-- before running Phase 52 PHP code that references class_status.
--
-- =============================================================================
-- SEQUENCE:
--   1. Add class_status column with safe default
--   2. Backfill existing rows from order_nr
--   3. Add CHECK constraint to enforce valid values
--   4. Create class_status_history audit table
-- =============================================================================


-- Step 1: Add class_status column
-- Default 'draft' is safe — backfill in step 2 will correct active classes.
ALTER TABLE classes
    ADD COLUMN class_status VARCHAR(20) NOT NULL DEFAULT 'draft';


-- Step 2: Backfill existing data
-- Classes with a non-empty order_nr are considered active (already commissioned).
-- Classes with NULL or empty order_nr remain as draft.
UPDATE classes
    SET class_status = 'active'
    WHERE order_nr IS NOT NULL AND order_nr != '';

UPDATE classes
    SET class_status = 'draft'
    WHERE order_nr IS NULL OR order_nr = '';


-- Step 3: Add CHECK constraint to enforce valid status values
ALTER TABLE classes
    ADD CONSTRAINT chk_class_status
    CHECK (class_status IN ('draft', 'active', 'stopped'));


-- Step 4: Create class_status_history audit table
-- Records every status transition with reason, notes, and who changed it.
-- changed_by stores a WordPress user ID (resolved to display name via PHP,
-- not a cross-database JOIN to wp_users in MySQL).
CREATE TABLE class_status_history (
    id           SERIAL PRIMARY KEY,
    class_id     INTEGER NOT NULL REFERENCES classes(class_id),
    old_status   VARCHAR(20) NOT NULL,
    new_status   VARCHAR(20) NOT NULL,
    reason       VARCHAR(50),
    notes        TEXT,
    changed_by   INTEGER NOT NULL,
    changed_at   TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_class_status_history_class_id
    ON class_status_history(class_id);
