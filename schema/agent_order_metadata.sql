-- Add metadata column for Agent Order Number task completion tracking
-- Records who completed the task and when

ALTER TABLE classes ADD COLUMN IF NOT EXISTS order_nr_metadata JSONB;

COMMENT ON COLUMN classes.order_nr_metadata IS 'Completion metadata for Agent Order Number task: {completed_by, completed_at}';
