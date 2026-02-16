-- ============================================================================
-- Migration: 38-01-agent-address-to-locations.sql
-- Purpose: Add location_id FK column to agents table
-- Phase: 38 - Address Storage Normalization
-- Date: 2026-02-16
-- ============================================================================
--
-- This migration adds a location_id column to the agents table that references
-- the shared public.locations table. This normalizes address storage across
-- the system (agents, clients, learners all use the same locations table).
--
-- IMPORTANT: Old address columns are preserved for a dual-write period during
-- Phase 38-02. They will be dropped in a later phase after code is fully
-- migrated to use location_id.
--
-- Run BEFORE the PHP data migration script (38-01-agent-address-to-locations.php).
-- ============================================================================

BEGIN;

-- Add location_id column to agents table (nullable during migration period)
ALTER TABLE agents ADD COLUMN location_id INTEGER DEFAULT NULL;

-- Add foreign key constraint referencing public.locations
-- ON DELETE SET NULL preserves agent record if location is deleted
ALTER TABLE agents ADD CONSTRAINT fk_agents_location
  FOREIGN KEY (location_id) REFERENCES public.locations(location_id)
  ON DELETE SET NULL;

-- Add index for FK lookups and queries filtering by location
CREATE INDEX idx_agents_location_id ON agents(location_id);

COMMIT;

-- ============================================================================
-- Post-migration notes:
-- - Run 38-01-agent-address-to-locations.php to copy address data
-- - Verify all agents with addresses have location_id set
-- - Old columns remain until dual-write period is complete
-- ============================================================================
