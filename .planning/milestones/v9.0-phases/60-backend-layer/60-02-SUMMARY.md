---
phase: 60-backend-layer
plan: 02
subsystem: api
tags: [wordpress, ajax, agent-orders, agent-invoices, php]

# Dependency graph
requires:
  - phase: 60-01
    provides: AgentOrderService and AgentInvoiceService with all business logic

provides:
  - Six WordPress AJAX endpoints for agent orders and invoices (AgentOrdersAjaxHandlers)
  - Auto-create agent_orders row on class save when order_nr + class_agent are set (ORD-02)
  - Bootstrap registration of AgentOrdersAjaxHandlers in wecoza-core.php

affects:
  - 62-frontend-layer — will call these AJAX endpoints from JS
  - future invoice review UI — uses wecoza_invoice_review and wecoza_invoice_list

# Tech tracking
tech-stack:
  added: []
  patterns:
    - AjaxSecurity pattern (requireNonce + requireCapability + sendSuccess/sendError) applied to all six handlers
    - Non-blocking service call pattern (try/catch, log only) for class-save hook
    - invoice_month normalised to YYYY-MM-01 at AJAX layer so callers pass YYYY-MM

key-files:
  created:
    - src/Agents/Ajax/AgentOrdersAjaxHandlers.php
  modified:
    - src/Classes/Controllers/ClassAjaxController.php
    - wecoza-core.php

key-decisions:
  - "handleCalculate is pure read — no DB write on calculate endpoint"
  - "ensureAgentOrderExists is non-blocking — class save never fails due to order creation errors"
  - "invoice_month YYYY-MM normalised to YYYY-MM-01 at AJAX boundary, not inside service"

patterns-established:
  - "AJAX handlers: requireNonce('wecoza_orders_nonce') before requireCapability on every handler"
  - "Non-blocking side-effects: inline try/catch with wecoza_log, never throws up"

requirements-completed: [ORD-02, INV-03]

# Metrics
duration: 4min
completed: 2026-03-11
---

# Phase 60 Plan 02: AJAX Endpoints & Class-Save Hook Summary

**Six WordPress AJAX endpoints wired to AgentOrderService/AgentInvoiceService, plus auto-order creation on class save (ORD-02)**

## Performance

- **Duration:** ~4 min
- **Started:** 2026-03-11T10:43:00Z
- **Completed:** 2026-03-11T10:45:52Z
- **Tasks:** 2
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments

- Created `AgentOrdersAjaxHandlers` with six AJAX endpoints, each enforcing `wecoza_orders_nonce` nonce and appropriate capability (`manage_options` or `capture_attendance`)
- Wired `ClassAjaxController::saveClassAjax()` to call `ensureAgentOrderExists()` after LP creation — fulfils ORD-02
- Bootstrapped `AgentOrdersAjaxHandlers` in `wecoza-core.php` with class_exists guard, consistent with existing agent bootstrap pattern

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AgentOrdersAjaxHandlers with six endpoints** - `4452f6b` (feat)
2. **Task 2: Wire class-save hook and bootstrap registration** - `62a0783` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified

- `src/Agents/Ajax/AgentOrdersAjaxHandlers.php` — Six AJAX endpoints; uses AjaxSecurity throughout; all bodies wrapped in try/catch(Throwable)
- `src/Classes/Controllers/ClassAjaxController.php` — Added `ensureAgentOrderExists()` call in `saveClassAjax()` and the private static method itself
- `wecoza-core.php` — Bootstrap registration of AgentOrdersAjaxHandlers after AgentsAjaxHandlers

## Decisions Made

- `handleCalculate` is deliberately pure read — no DB writes. The frontend calls this for a preview before the user decides to submit.
- `ensureAgentOrderExists` is non-blocking. A failure to auto-create an order must never roll back or error the class save.
- `invoice_month` YYYY-MM is normalised to YYYY-MM-01 at the AJAX boundary (before calling the service), keeping the service interface simple.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Backend layer (Phase 60) is now complete: repositories, services, and AJAX endpoints all wired
- Phase 62 (frontend layer) can now call all six endpoints using nonce `wecoza_orders_nonce`
- `handleCalculate` returns `class_hours_total`, `all_absent_days`, `all_absent_hours`, `calculated_payable_hours` as documented in Plan 01

---
*Phase: 60-backend-layer*
*Completed: 2026-03-11*
