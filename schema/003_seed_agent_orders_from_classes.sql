-- =============================================================================
-- File:    003_seed_agent_orders_from_classes.sql
-- Purpose: Seed initial agent_orders rows from all existing active classes
--          that have a class_agent assigned. Rate amounts default to 0.00 —
--          admin must set actual rates after migration via the admin UI.
--          Uses ON CONFLICT DO NOTHING so this script is safe to re-run.
-- Date:    2026-03-11
-- Order:   Must be run THIRD (after 001 and 002).
-- =============================================================================

BEGIN;

INSERT INTO public.agent_orders (
    class_id,
    agent_id,
    rate_type,
    rate_amount,
    start_date,
    notes
)
SELECT
    c.class_id,
    c.class_agent                                   AS agent_id,
    'hourly'                                        AS rate_type,
    0.00                                            AS rate_amount,
    COALESCE(c.original_start_date, CURRENT_DATE)  AS start_date,
    'Seeded from existing active class on migration — rate amount requires admin update'
                                                    AS notes
FROM public.classes c
WHERE c.class_status = 'active'
  AND c.class_agent IS NOT NULL
ON CONFLICT (class_id, agent_id, start_date) DO NOTHING;

COMMIT;

-- Summary: show how many orders exist after seeding
SELECT
    count(*) AS agent_orders_total,
    count(*) FILTER (WHERE rate_amount = 0.00) AS orders_needing_rate_update
FROM public.agent_orders;
