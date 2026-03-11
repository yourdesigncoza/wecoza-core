-- =============================================================================
-- File:    001_create_agent_orders.sql
-- Purpose: Create the agent_orders table for tracking agent payment orders
--          per class. Supports rate changes via UNIQUE(class_id, agent_id,
--          start_date) — same class+agent with a different start_date = new
--          rate period.
-- Date:    2026-03-11
-- Order:   Must be run FIRST (before 002 and 003).
-- =============================================================================

CREATE TABLE public.agent_orders (
    order_id        SERIAL PRIMARY KEY,
    class_id        INTEGER NOT NULL,
    agent_id        INTEGER NOT NULL,
    rate_type       VARCHAR(20) NOT NULL DEFAULT 'hourly'
                        CONSTRAINT agent_orders_rate_type_check
                        CHECK (rate_type IN ('hourly', 'daily')),
    rate_amount     NUMERIC(10,2) NOT NULL DEFAULT 0.00
                        CONSTRAINT agent_orders_rate_amount_check
                        CHECK (rate_amount >= 0),
    start_date      DATE NOT NULL DEFAULT CURRENT_DATE,
    end_date        DATE,
    notes           TEXT,
    created_at      TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at      TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    created_by      INTEGER,

    CONSTRAINT agent_orders_class_id_fk
        FOREIGN KEY (class_id) REFERENCES public.classes(class_id),
    CONSTRAINT agent_orders_agent_id_fk
        FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id),
    CONSTRAINT agent_orders_class_agent_date_unique
        UNIQUE (class_id, agent_id, start_date)
);


ALTER TABLE public.agent_orders OWNER TO "John";


-- Index for efficient lookup by class and agent (used in invoice joins, listings)
CREATE INDEX idx_agent_orders_class_agent
    ON public.agent_orders (class_id, agent_id);


-- Trigger: auto-update updated_at on row modification
CREATE TRIGGER trg_agent_orders_updated_at
    BEFORE UPDATE ON public.agent_orders
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();


-- Table and column comments
COMMENT ON TABLE public.agent_orders IS
    'Tracks agent payment orders per class. Each row represents a rate period for a specific class+agent combination. Rate changes are recorded as new rows with a different start_date.';

COMMENT ON COLUMN public.agent_orders.order_id IS
    'Unique internal order ID';

COMMENT ON COLUMN public.agent_orders.class_id IS
    'FK to public.classes — the class this order applies to';

COMMENT ON COLUMN public.agent_orders.agent_id IS
    'FK to public.agents — the agent being paid for this class';

COMMENT ON COLUMN public.agent_orders.rate_type IS
    'Payment rate basis: hourly (per hour taught) or daily (per day)';

COMMENT ON COLUMN public.agent_orders.rate_amount IS
    'Monetary rate in ZAR. Defaults to 0.00 — admin must set after migration.';

COMMENT ON COLUMN public.agent_orders.start_date IS
    'Date this rate period takes effect. Together with class_id and agent_id forms the unique rate period key.';

COMMENT ON COLUMN public.agent_orders.end_date IS
    'Date this rate period ends. NULL means current/ongoing.';

COMMENT ON COLUMN public.agent_orders.notes IS
    'Optional notes regarding this order or rate change';

COMMENT ON COLUMN public.agent_orders.created_at IS
    'Timestamp when the order row was created';

COMMENT ON COLUMN public.agent_orders.updated_at IS
    'Timestamp of last update — maintained automatically by trigger';

COMMENT ON COLUMN public.agent_orders.created_by IS
    'WordPress user ID of the admin who created this order';
