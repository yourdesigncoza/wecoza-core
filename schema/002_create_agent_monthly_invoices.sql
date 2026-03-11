-- =============================================================================
-- File:    002_create_agent_monthly_invoices.sql
-- Purpose: Create the agent_monthly_invoices table. Stores one invoice record
--          per agent_order per calendar month. class_id and agent_id are
--          denormalized for simpler reconciliation queries (avoids joining
--          through agent_orders on every report query).
-- Date:    2026-03-11
-- Order:   Must be run SECOND (after 001, before 003).
-- =============================================================================

CREATE TABLE public.agent_monthly_invoices (
    invoice_id              SERIAL PRIMARY KEY,
    order_id                INTEGER NOT NULL,
    class_id                INTEGER NOT NULL,
    agent_id                INTEGER NOT NULL,
    invoice_month           DATE NOT NULL,
    class_hours_total       NUMERIC(8,2) NOT NULL DEFAULT 0.00,
    all_absent_days         INTEGER NOT NULL DEFAULT 0,
    all_absent_hours        NUMERIC(8,2) NOT NULL DEFAULT 0.00,
    calculated_payable_hours NUMERIC(8,2) NOT NULL DEFAULT 0.00,
    agent_claimed_hours     NUMERIC(8,2),
    agent_notes             TEXT,
    discrepancy_hours       NUMERIC(8,2),
    status                  VARCHAR(20) NOT NULL DEFAULT 'draft'
                                CONSTRAINT agent_monthly_invoices_status_check
                                CHECK (status IN ('draft', 'submitted', 'approved', 'disputed')),
    submitted_at            TIMESTAMP WITHOUT TIME ZONE,
    reviewed_at             TIMESTAMP WITHOUT TIME ZONE,
    reviewed_by             INTEGER,
    review_notes            TEXT,
    created_at              TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at              TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),

    CONSTRAINT agent_monthly_invoices_order_id_fk
        FOREIGN KEY (order_id) REFERENCES public.agent_orders(order_id)
        ON DELETE RESTRICT,
    CONSTRAINT agent_monthly_invoices_class_id_fk
        FOREIGN KEY (class_id) REFERENCES public.classes(class_id),
    CONSTRAINT agent_monthly_invoices_agent_id_fk
        FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id),
    CONSTRAINT agent_monthly_invoices_order_month_unique
        UNIQUE (order_id, invoice_month)
);


ALTER TABLE public.agent_monthly_invoices OWNER TO "John";


-- Indexes for common report access patterns
CREATE INDEX idx_agent_invoices_class_month
    ON public.agent_monthly_invoices (class_id, invoice_month);

CREATE INDEX idx_agent_invoices_agent_month
    ON public.agent_monthly_invoices (agent_id, invoice_month);

CREATE INDEX idx_agent_invoices_status
    ON public.agent_monthly_invoices (status);


-- Trigger: auto-update updated_at on row modification
CREATE TRIGGER trg_agent_monthly_invoices_updated_at
    BEFORE UPDATE ON public.agent_monthly_invoices
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();


-- Table and column comments
COMMENT ON TABLE public.agent_monthly_invoices IS
    'One invoice record per agent_order per calendar month. class_id and agent_id are denormalized for efficient reporting. Status workflow: draft -> submitted -> approved | disputed.';

COMMENT ON COLUMN public.agent_monthly_invoices.invoice_id IS
    'Unique internal invoice ID';

COMMENT ON COLUMN public.agent_monthly_invoices.order_id IS
    'FK to public.agent_orders — the rate period this invoice belongs to. ON DELETE RESTRICT prevents deleting orders that have invoices.';

COMMENT ON COLUMN public.agent_monthly_invoices.class_id IS
    'Denormalized FK to public.classes — copied from agent_orders at creation for simpler reconciliation queries';

COMMENT ON COLUMN public.agent_monthly_invoices.agent_id IS
    'Denormalized FK to public.agents — copied from agent_orders at creation for simpler reconciliation queries';

COMMENT ON COLUMN public.agent_monthly_invoices.invoice_month IS
    'First day of the calendar month this invoice covers (e.g. 2026-03-01). UNIQUE with order_id — one invoice per order per month.';

COMMENT ON COLUMN public.agent_monthly_invoices.class_hours_total IS
    'System-calculated total scheduled hours for this class in the invoice month';

COMMENT ON COLUMN public.agent_monthly_invoices.all_absent_days IS
    'Count of sessions in the month where all learners had 0 present hours (all-absent sessions)';

COMMENT ON COLUMN public.agent_monthly_invoices.all_absent_hours IS
    'Total hours from all-absent sessions — agent is not paid for these';

COMMENT ON COLUMN public.agent_monthly_invoices.calculated_payable_hours IS
    'System-calculated payable hours: class_hours_total minus all_absent_hours';

COMMENT ON COLUMN public.agent_monthly_invoices.agent_claimed_hours IS
    'Hours claimed by the agent on submission. NULL until agent submits the invoice.';

COMMENT ON COLUMN public.agent_monthly_invoices.agent_notes IS
    'Optional notes from the agent submitted with their invoice';

COMMENT ON COLUMN public.agent_monthly_invoices.discrepancy_hours IS
    'Difference: agent_claimed_hours minus calculated_payable_hours. Positive = agent overclaimed. NULL until agent submits.';

COMMENT ON COLUMN public.agent_monthly_invoices.status IS
    'Invoice lifecycle status: draft (system-created) | submitted (agent claimed) | approved (admin accepted) | disputed (admin flagged discrepancy)';

COMMENT ON COLUMN public.agent_monthly_invoices.submitted_at IS
    'Timestamp when the agent submitted this invoice';

COMMENT ON COLUMN public.agent_monthly_invoices.reviewed_at IS
    'Timestamp when an admin approved or disputed this invoice';

COMMENT ON COLUMN public.agent_monthly_invoices.reviewed_by IS
    'WordPress user ID of the admin who reviewed this invoice';

COMMENT ON COLUMN public.agent_monthly_invoices.review_notes IS
    'Admin notes added during review (e.g. reason for dispute)';

COMMENT ON COLUMN public.agent_monthly_invoices.created_at IS
    'Timestamp when this invoice record was created';

COMMENT ON COLUMN public.agent_monthly_invoices.updated_at IS
    'Timestamp of last update — maintained automatically by trigger';
