-- Phase 54: Add wp_user_id column to agents table
-- Links agent records to WordPress user accounts
-- Run this manually in psql or pgAdmin before testing Phase 55

ALTER TABLE agents ADD COLUMN IF NOT EXISTS wp_user_id INTEGER;

-- Unique partial index: one WP user = one active agent
-- Pattern matches idx_agents_email_unique and idx_agents_sa_id_unique
CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS idx_agents_wp_user_id_unique
    ON agents (wp_user_id)
    WHERE wp_user_id IS NOT NULL AND status <> 'deleted';
