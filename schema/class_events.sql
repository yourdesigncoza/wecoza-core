--
-- class_events table schema for WeCoza notification system
-- Phase 18: Notification System Infrastructure
--

-- Drop table if exists (for development purposes)
-- DROP TABLE IF EXISTS public.class_events CASCADE;

CREATE TABLE IF NOT EXISTS public.class_events (
    -- Primary key
    event_id SERIAL PRIMARY KEY,

    -- Event classification
    event_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INTEGER NOT NULL,

    -- Actor
    user_id INTEGER,

    -- Payload
    event_data JSONB NOT NULL DEFAULT '{}',

    -- AI enrichment
    ai_summary JSONB,

    -- Notification workflow
    notification_status VARCHAR(20) NOT NULL DEFAULT 'pending',

    -- Timestamps
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enriched_at TIMESTAMP WITH TIME ZONE,
    sent_at TIMESTAMP WITH TIME ZONE,
    viewed_at TIMESTAMP WITH TIME ZONE,
    acknowledged_at TIMESTAMP WITH TIME ZONE,

    -- Constraints
    CONSTRAINT chk_event_type CHECK (event_type IN (
        'CLASS_INSERT',
        'CLASS_UPDATE',
        'CLASS_DELETE',
        'LEARNER_ADD',
        'LEARNER_REMOVE',
        'LEARNER_UPDATE',
        'STATUS_CHANGE'
    )),
    CONSTRAINT chk_entity_type CHECK (entity_type IN ('class', 'learner')),
    CONSTRAINT chk_notification_status CHECK (notification_status IN (
        'pending',
        'enriching',
        'sending',
        'sent',
        'failed'
    ))
);

-- Index: Composite index for entity lookups (most common query pattern)
CREATE INDEX IF NOT EXISTS idx_class_events_entity
    ON public.class_events (entity_type, entity_id);

-- Index: Notification status for queue processing
CREATE INDEX IF NOT EXISTS idx_class_events_status
    ON public.class_events (notification_status)
    WHERE notification_status IN ('pending', 'enriching', 'sending');

-- Index: Timeline display (created_at DESC for recent events)
CREATE INDEX IF NOT EXISTS idx_class_events_created_at
    ON public.class_events (created_at DESC);

-- Index: User events lookup
CREATE INDEX IF NOT EXISTS idx_class_events_user_id
    ON public.class_events (user_id)
    WHERE user_id IS NOT NULL;

-- Index: Unread events (viewed_at IS NULL)
CREATE INDEX IF NOT EXISTS idx_class_events_unread
    ON public.class_events (created_at DESC)
    WHERE viewed_at IS NULL;

-- Table comment
COMMENT ON TABLE public.class_events IS
    'Application-level event storage for WeCoza notification system. Events are captured from class and learner operations, enriched with AI summaries, and processed into notifications.';

-- Column comments
COMMENT ON COLUMN public.class_events.event_id IS 'Auto-incrementing primary key';
COMMENT ON COLUMN public.class_events.event_type IS 'Type of event: CLASS_INSERT, CLASS_UPDATE, CLASS_DELETE, LEARNER_ADD, LEARNER_REMOVE, LEARNER_UPDATE, STATUS_CHANGE';
COMMENT ON COLUMN public.class_events.entity_type IS 'Entity being tracked: class or learner';
COMMENT ON COLUMN public.class_events.entity_id IS 'ID of the entity (class_id or learner_id)';
COMMENT ON COLUMN public.class_events.user_id IS 'WordPress user ID who triggered the event (null for system events)';
COMMENT ON COLUMN public.class_events.event_data IS 'JSONB payload containing new_row, old_row, diff, and metadata';
COMMENT ON COLUMN public.class_events.ai_summary IS 'AI-generated summary of the event for notifications';
COMMENT ON COLUMN public.class_events.notification_status IS 'Workflow status: pending -> enriching -> sending -> sent (or failed)';
COMMENT ON COLUMN public.class_events.created_at IS 'When the event was captured';
COMMENT ON COLUMN public.class_events.enriched_at IS 'When AI enrichment completed';
COMMENT ON COLUMN public.class_events.sent_at IS 'When notification was delivered';
COMMENT ON COLUMN public.class_events.viewed_at IS 'When user viewed the notification';
COMMENT ON COLUMN public.class_events.acknowledged_at IS 'When user acknowledged/dismissed the notification';
