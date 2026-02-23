-- =============================================================================
-- class_attendance_sessions
-- =============================================================================
-- Tracks each scheduled session for a class.
-- One row per (class_id, session_date) — the UNIQUE constraint prevents
-- duplicates so capturing can only happen once per session.
--
-- Status values:
--   pending           - Session is scheduled but not yet acted on
--   captured          - Attendance has been recorded for this session
--   client_cancelled  - Client cancelled the session (does NOT count toward hours_trained)
--   agent_absent      - Agent was absent (does NOT count toward hours_trained)
--
-- captured_by / captured_at are NULL until the session status changes away
-- from 'pending'.
-- =============================================================================

CREATE TABLE IF NOT EXISTS class_attendance_sessions (
    session_id        SERIAL PRIMARY KEY,

    -- Class reference (FK to classes table)
    class_id          INTEGER      NOT NULL,

    -- The scheduled date of this session (matches the class schedule)
    session_date      DATE         NOT NULL,

    -- Current state of the session
    status            VARCHAR(30)  NOT NULL DEFAULT 'pending'
                          CONSTRAINT class_attendance_sessions_status_check
                          CHECK (status IN ('pending', 'captured', 'client_cancelled', 'agent_absent')),

    -- Hours from the class schedule for this session (e.g. 2.0, 3.5)
    scheduled_hours   NUMERIC(5,1) NOT NULL,

    -- Optional free-text notes (e.g. reason for cancellation)
    notes             TEXT,

    -- WP user ID of the person who captured/marked this session (NULL until captured)
    captured_by       INTEGER,

    -- When the session was captured/marked
    captured_at       TIMESTAMP,

    -- Audit timestamps
    created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    -- Prevent duplicate sessions: one row per class per date
    CONSTRAINT class_attendance_sessions_class_date_unique
        UNIQUE (class_id, session_date)
);

-- Index on class_id for efficient class-level lookups
-- (The unique constraint creates a composite index on (class_id, session_date),
--  but a single-column index helps when filtering by class_id alone)
CREATE INDEX IF NOT EXISTS idx_class_attendance_sessions_class_id
    ON class_attendance_sessions (class_id);
