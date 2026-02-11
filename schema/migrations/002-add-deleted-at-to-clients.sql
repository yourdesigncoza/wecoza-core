-- Migration: Add deleted_at column to clients table for soft-delete functionality
-- Plan: 22-02
-- Date: 2026-02-11
--
-- This migration adds soft-delete support to the clients table.
-- Soft-deleted clients will have a timestamp in deleted_at instead of being hard-deleted.
--
-- IMPORTANT: Run this SQL manually in your PostgreSQL database before testing client deletion.

ALTER TABLE clients ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

-- Create index for better query performance (filtering out deleted records)
CREATE INDEX IF NOT EXISTS idx_clients_deleted_at ON clients(deleted_at) WHERE deleted_at IS NOT NULL;

-- Verification query (should return 0 if no clients are currently deleted)
-- SELECT COUNT(*) FROM clients WHERE deleted_at IS NOT NULL;
