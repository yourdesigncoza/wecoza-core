---
gsd_state_version: 1.0
milestone: v9.0
milestone_name: Agent Orders & Payment Tracking
status: planning
stopped_at: Completed 61-01-PLAN.md (all-absent confirmation guard)
last_updated: "2026-03-11T11:29:09.290Z"
last_activity: 2026-03-11 — Roadmap created for v9.0
progress:
  total_phases: 5
  completed_phases: 3
  total_plans: 4
  completed_plans: 4
  percent: 0
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-11)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure
**Current focus:** v9.0 — Phase 59: Database Schema

## Current Position

Phase: 59 of 63 (Database Schema)
Plan: — (not yet planned)
Status: Ready to plan
Last activity: 2026-03-11 — Roadmap created for v9.0

Progress: [░░░░░░░░░░] 0%

## Performance Metrics

**Velocity:**
- Total plans completed: 0 (v9.0) / 125 (lifetime)
- Average duration: —
- Total execution time: —

## Milestone History

| Version | Name | Shipped | Phases | Plans |
|---------|------|---------|--------|-------|
| v8.0 | Page Tracking & Report Extraction | 2026-03-09 | 56-58 | 5 |
| v7.0 | Agent Attendance Access | 2026-03-05 | 53-55 | 7 |
| v6.0 | Agent Attendance Capture | 2026-02-24 | 48-52 | 13 |
| v5.0 | Learner Progression | 2026-02-23 | 44-46 | 9 |
| v4.1 | Lookup Table Admin | 2026-02-17 | 42-43 | 3 |
| v4.0 | Technical Debt | 2026-02-16 | 36-41 | 14 |

See: .planning/MILESTONES.md for full details
| Phase 59-database-schema P01 | 2 | 1 tasks | 3 files |
| Phase 59-database-schema P01 | 2 | 2 tasks | 3 files |
| Phase 60 P01 | 2 | 2 tasks | 4 files |
| Phase 60 P02 | 4 | 2 tasks | 3 files |
| Phase 61-all-absent-confirmation P01 | 1min | 2 tasks | 1 files |

## Accumulated Context

### Decisions

- [v9.0]: Rate changes supported via new agent_orders row (UNIQUE on class_id+agent_id+start_date)
- [v9.0]: all_absent detection is pure JS (UX guard); calculation enforced server-side in AgentInvoiceService
- [v9.0]: class_id+agent_id denormalized on agent_monthly_invoices for simpler reconciliation queries
- [v9.0]: ON DELETE RESTRICT on agent_monthly_invoices.order_id — can't delete orders with invoices
- [Phase 59-database-schema]: Rate changes recorded as new agent_orders rows (different start_date), not UPDATE — preserves history
- [Phase 59-database-schema]: class_id and agent_id denormalized onto agent_monthly_invoices for simpler reconciliation queries
- [Phase 59-database-schema]: Seed sets rate_amount=0.00 for all migrated orders — admin must set real rates via UI after migration
- [Phase 60]: ensureOrderForClass guards class_id/agent_id > 0 to prevent orphan orders
- [Phase 60]: client_cancelled sessions do NOT count as agent-absent — only agent_absent and captured-with-all-zeros count
- [Phase 60]: calculateMonthSummary normalises invoiceMonth to Y-m-01 so callers can pass any date in month
- [Phase Phase 60]: handleCalculate is pure read — no DB write on calculate endpoint
- [Phase Phase 60]: ensureAgentOrderExists is non-blocking — class save never fails due to order creation errors
- [Phase Phase 60]: invoice_month YYYY-MM normalised to YYYY-MM-01 at AJAX boundary, not inside service
- [Phase 61-all-absent-confirmation]: All-absent detection is pure JS UX guard; server-side enforcement remains in AgentInvoiceService
- [Phase 61-all-absent-confirmation]: Guard placed after isValid check so NaN/missing page numbers are caught before all-absent prompt
- [Phase Phase 61-all-absent-confirmation]: Browser verification confirmed all four test scenarios: all-zero cancel, all-zero confirm, mixed hours (no dialog), single learner

### Pending Todos

- Agent edit form wp_user_id field (AGT-09, AGT-10) — deferred to future milestone
- Target page progression (TPAG-01..03) — requires Mario to define target pages per module

### Blockers/Concerns

- Phase 59 requires user to run SQL manually (no DDL via MCP)
- Rate amounts for existing migrated orders will be 0.00 — admin must set rates after migration

## Session Continuity

Last session: 2026-03-11T11:29:09.286Z
Stopped at: Completed 61-01-PLAN.md (all-absent confirmation guard)
Resume file: None

**Next action:** `/gsd:plan-phase 59`
