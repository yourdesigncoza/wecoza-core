-- Add soft-delete columns to class_events table
-- Run this SQL manually in your PostgreSQL client before using the delete notification feature.
-- Generated: 2026-02-18

ALTER TABLE class_events ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ DEFAULT NULL;
ALTER TABLE class_events ADD COLUMN IF NOT EXISTS deleted_by INTEGER DEFAULT NULL;
