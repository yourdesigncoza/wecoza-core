---
phase: 59-database-schema
plan: 01
subsystem: database
tags: [postgresql, ddl, migrations, agent-orders, invoices]

requires: []
provides:
  - agent_orders table with rate period support (UNIQUE class_id+agent_id+start_date)
  - agent_monthly_invoices table with calculated fields and status workflow
  - Seed migration from active classes to agent_orders
affects:
  - 60-agent-order-ui
  - 61-invoice-generation
  - 62-agent-invoice-submission
  - 63-admin-invoice-review

tech-stack:
  added: []
  patterns:
    - "Rate period versioning via UNIQUE(class_id, agent_id, start_date) — new row per rate change, no UPDATE"
    - "Denormalized class_id+agent_id on child table for efficient reconciliation queries without join"
    - "ON DELETE RESTRICT on invoice FK prevents deleting orders with invoice history"
    - "Idempotent seed scripts using ON CONFLICT DO NOTHING wrapped in BEGIN/COMMIT"

key-files:
  created:
    - schema/001_create_agent_orders.sql
    - schema/002_create_agent_monthly_invoices.sql
    - schema/003_seed_agent_orders_from_classes.sql
  modified: []

key-decisions:
  - "Rate changes recorded as new agent_orders rows (different start_date), not UPDATE — preserves history"
  - "class_id and agent_id denormalized onto agent_monthly_invoices for simpler reconciliation queries"
  - "ON DELETE RESTRICT on agent_monthly_invoices.order_id — cannot delete orders that have invoices"
  - "Seed sets rate_amount=0.00 for all migrated orders — admin must set real rates via UI after migration"

patterns-established:
  - "SQL migrations numbered sequentially (001, 002, 003) with comment block header"
  - "All tables owned by 'John', updated_at maintained via reusable update_updated_at_column() trigger"

requirements-completed: [ORD-03, ORD-04]

duration: 2min
completed: 2026-03-11
---

# Phase 59 Plan 01: Database Schema Summary

**PostgreSQL foundation tables for agent payment tracking: agent_orders (rate periods) and agent_monthly_invoices (calculated fields + approval workflow), with idempotent seed from active classes**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-11T10:05:15Z
- **Completed:** 2026-03-11T10:06:55Z
- **Tasks:** 2/2 complete
- **Files modified:** 3

## Accomplishments
- Three SQL migration files created, all verified against plan specifications
- agent_orders: UNIQUE(class_id+agent_id+start_date) constraint enables rate period versioning without UPDATE
- agent_monthly_invoices: complete calculated-field schema with draft/submitted/approved/disputed workflow
- Seed script is idempotent (ON CONFLICT DO NOTHING), transaction-wrapped, with summary SELECT
- All tables follow existing schema conventions: "John" owner, update_updated_at_column() trigger, COMMENT ON TABLE/COLUMN

## Task Commits

Each task was committed atomically:

1. **Task 1: Write SQL migration files** - `a6c1046` (feat)

2. **Task 2: Execute SQL migrations against PostgreSQL** - (user executed — confirmed 5 agent_orders rows seeded)

**Plan metadata:** `(docs commit — see final commit hash)`

## Files Created/Modified
- `schema/001_create_agent_orders.sql` - DDL for agent_orders table with constraints, index, trigger, comments
- `schema/002_create_agent_monthly_invoices.sql` - DDL for agent_monthly_invoices table with 3 indexes, FK with ON DELETE RESTRICT, trigger, comments
- `schema/003_seed_agent_orders_from_classes.sql` - Idempotent seed INSERT from active classes, transaction-wrapped

## Decisions Made
- Seed rate_amount defaults to 0.00 — plan specifies admin sets real rates after migration via UI
- Force-added SQL files to git despite `schema/` being in .gitignore (migration files are tracked artifacts, unlike large backup dumps)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- `schema/` directory is listed in .gitignore (existing large backup dumps excluded). Used `git add -f` to force-track the new migration files only (backup dumps remain excluded). No plan impact.

## User Setup Required

**Completed.** All three SQL files were executed manually by the user on 2026-03-11. Confirmed: 5 agent_orders rows seeded from active classes (5 active classes with class_agent assigned).

## Next Phase Readiness
- SQL files ready to execute — all three created with correct DDL, constraints, indexes, triggers
- After execution: Phase 60 (Agent Order UI) can begin — it depends on agent_orders table existing
- Admin will need to set rate_amount for seeded orders (all default to 0.00) via Phase 60 UI

---
*Phase: 59-database-schema*
*Completed: 2026-03-11*

## Self-Check: PASSED

- [x] schema/001_create_agent_orders.sql — FOUND
- [x] schema/002_create_agent_monthly_invoices.sql — FOUND
- [x] schema/003_seed_agent_orders_from_classes.sql — FOUND
- [x] Commit a6c1046 — FOUND
