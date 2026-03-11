---
phase: 60-backend-layer
plan: 01
subsystem: payments
tags: [postgres, repository-pattern, agent-orders, invoicing, attendance]

# Dependency graph
requires:
  - phase: 59-database-schema
    provides: agent_orders and agent_monthly_invoices tables with UNIQUE constraints
provides:
  - AgentOrderRepository with ON CONFLICT idempotent upsert and active order lookup
  - AgentInvoiceRepository with findOrCreateDraft, getSessionsForMonth (queries class_attendance_sessions)
  - AgentOrderService with ensureOrderForClass, saveOrderRate, getActiveOrder
  - AgentInvoiceService with calculateMonthSummary, submitInvoice (discrepancy stored), reviewInvoice
affects: [61-ajax-endpoints, 62-ui-components, 63-reporting]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - BaseRepository extension with column whitelisting for new domain tables
    - ON CONFLICT DO NOTHING for idempotent upserts (concurrent-safe)
    - Service/Repository separation: services hold business logic, repositories hold raw SQL
    - isAllAbsentSession private method: branches on status (agent_absent, captured, pending, client_cancelled)

key-files:
  created:
    - src/Agents/Repositories/AgentOrderRepository.php
    - src/Agents/Repositories/AgentInvoiceRepository.php
    - src/Agents/Services/AgentOrderService.php
    - src/Agents/Services/AgentInvoiceService.php
  modified: []

key-decisions:
  - "ensureOrderForClass guards class_id/agent_id > 0 to prevent orphan orders"
  - "isAllAbsentSession: client_cancelled sessions do NOT count as agent-absent (client-side cancellation)"
  - "findOrCreateDraft throws RuntimeException on total DB failure to surface bugs early"
  - "calculateMonthSummary normalises invoiceMonth to Y-m-01 so callers can pass any date in month"

patterns-established:
  - "ON CONFLICT DO NOTHING + subsequent SELECT for idempotent upserts (no PHP existence check)"
  - "Repository methods use $this->db->query() for raw SQL, fetchAll(PDO::FETCH_ASSOC) for multi-row"
  - "Services accept nullable start_date, defaulting to date('Y-m-d') via ?string = null"

requirements-completed: [ORD-02, INV-03]

# Metrics
duration: 2min
completed: 2026-03-11
---

# Phase 60 Plan 01: Backend Layer Summary

**Four-class backend layer for agent orders and invoicing: repositories with ON CONFLICT idempotent upserts, service with all-absent attendance calculation and discrepancy tracking**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-11T10:40:03Z
- **Completed:** 2026-03-11T10:42:15Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- AgentOrderRepository and AgentInvoiceRepository created extending BaseRepository with full column whitelisting; both use PostgreSQL ON CONFLICT DO NOTHING for idempotent upserts
- AgentInvoiceRepository::getSessionsForMonth queries class_attendance_sessions with calendar-month boundaries computed in PHP
- AgentInvoiceService::calculateMonthSummary implements all-absent detection branching on session status (agent_absent=always, captured=check learner_data, pending/client_cancelled=false); stores discrepancy_hours = claimed - calculated

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AgentOrderRepository and AgentInvoiceRepository** - `9f9db39` (feat)
2. **Task 2: Create AgentOrderService and AgentInvoiceService** - `d6c8450` (feat)

## Files Created/Modified

- `src/Agents/Repositories/AgentOrderRepository.php` - agent_orders table access; ensureOrderExists with ON CONFLICT DO NOTHING; findActiveOrderForClass; findOrdersForClass
- `src/Agents/Repositories/AgentInvoiceRepository.php` - agent_monthly_invoices table access; findOrCreateDraft with ON CONFLICT DO NOTHING; getSessionsForMonth querying class_attendance_sessions; findInvoicesForClassAgent
- `src/Agents/Services/AgentOrderService.php` - ensureOrderForClass (guards empty IDs, defaults start_date to today); getActiveOrder; saveOrderRate with rate_type validation
- `src/Agents/Services/AgentInvoiceService.php` - calculateMonthSummary with isAllAbsentSession logic; submitInvoice stores discrepancy_hours; reviewInvoice validates approved/disputed status

## Decisions Made

- `client_cancelled` sessions do NOT count as all-absent — this is a client-side event, not agent absence. Only `agent_absent` and `captured` sessions with all-zero learner_data count.
- `findOrCreateDraft` throws `RuntimeException` on DB failure (not silent null return) to surface bugs in Plan 02 AJAX layer early.
- `ensureOrderForClass` guards `$classId <= 0 || $agentId <= 0` and returns null without DB call — prevents orphan orders when class has no assigned agent yet.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- All four backend classes ready for Plan 02 (AJAX endpoints) to consume
- AgentOrderService::ensureOrderForClass can be called from class save hooks
- AgentInvoiceService::submitInvoice and reviewInvoice provide the full lifecycle for the invoice AJAX handlers
- No blockers

---
*Phase: 60-backend-layer*
*Completed: 2026-03-11*

## Self-Check: PASSED

All 4 files confirmed on disk. Both task commits confirmed in git log.
