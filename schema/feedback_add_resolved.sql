-- schema/feedback_add_resolved.sql
-- Add resolved tracking columns to feedback_submissions
-- Run manually: psql -U wecoza -d wecoza -f schema/feedback_add_resolved.sql

ALTER TABLE feedback_submissions
    ADD COLUMN IF NOT EXISTS is_resolved BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS resolved_by VARCHAR(255),
    ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_feedback_resolved ON feedback_submissions(is_resolved);
