---
phase: 62-agent-invoice-ui
plan: "01"
subsystem: classes
tags: [agent-invoice, rate-card, ajax, ui]
dependency_graph:
  requires:
    - 60-01: AgentOrdersAjaxHandlers wecoza_order_get, wecoza_order_save
    - 60-02: AgentInvoiceAjaxHandlers wecoza_invoice_calculate, wecoza_invoice_submit, wecoza_invoice_list
  provides:
    - Agent Rate Card UI (admin-only, single class view)
    - Monthly Invoice UI (agent + admin, single class view)
  affects:
    - views/classes/components/single-class-display.view.php
    - src/Classes/Controllers/ClassController.php
tech_stack:
  added: []
  patterns:
    - PHP view component with capability guard (manage_options)
    - jQuery AJAX module with WeCozaSingleClass config object
    - Phoenix card + badge patterns for status display
key_files:
  created:
    - views/classes/components/single-class/agent-rate-card.php
    - views/classes/components/single-class/agent-invoice.php
    - assets/js/classes/agent-invoice.js
  modified:
    - views/classes/components/single-class-display.view.php
    - src/Classes/Controllers/ClassController.php
decisions:
  - Agent role always sees claim form; backend (wecoza_invoice_submit) handles duplicate submission uniqueness
  - Admin role calls wecoza_invoice_list to resolve invoice status before deciding which state to show
  - agent-invoice.js conditionally enqueued only when classAgent > 0 (no invoice UI without assigned agent)
  - ordersNonce and classAgent added to WeCozaSingleClass localized object for JS consumption
metrics:
  duration: "3 minutes"
  completed_date: "2026-03-11"
  tasks_completed: 2
  files_changed: 5
---

# Phase 62 Plan 01: Agent Invoice UI Summary

Agent Rate Card (admin-only) and Monthly Invoice section (agent + admin) built as inline PHP components on the single class view, wired to Phase 60 AJAX endpoints via a new jQuery module.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create PHP view components and wire into single class display | 91867ef | agent-rate-card.php, agent-invoice.php, single-class-display.view.php, ClassController.php |
| 2 | Create agent-invoice.js with AJAX interactions | 643e03b | assets/js/classes/agent-invoice.js |

## What Was Built

### agent-rate-card.php

Admin-only card (guarded by `current_user_can('manage_options')`). Returns early when no `class_agent` is set. Contains:
- Hidden `#agent-order-id` input (populated by JS via `wecoza_order_get`)
- Rate type `<select>` (hourly/daily) — disabled until order loads
- Rate amount `<input type="number">` with R prefix label — disabled until order loads
- Save Rate button `#btn-save-agent-rate` — disabled until order loads
- Status feedback span `#agent-rate-status`

### agent-invoice.php

Visible to both admin and wp_agent roles. Returns early when no `class_agent` is set or class is in `draft` status. Contains:
- Month picker `#invoice-month-picker` defaulting to current YYYY-MM
- Summary metrics row (Class Hours, Absent Days, Absent Hours, Payable Hours) — hidden until calculate completes
- Loading spinner `#invoice-loading`
- Alert container `#invoice-alert`
- Claim form `#invoice-claim-form` with claimed hours input and notes textarea
- Submitted state `#invoice-submitted` with Phoenix status badge, claimed hours, discrepancy warning, notes

### agent-invoice.js

Self-contained jQuery AJAX module. On DOM ready:
1. Admin: calls `loadOrder()` → populates rate card via `wecoza_order_get`
2. All roles: calls `calculateInvoice()` for current month → populates summary via `wecoza_invoice_calculate`
3. After calculate: admin calls `loadInvoiceStatus()` via `wecoza_invoice_list` to determine state; agent always sees claim form
4. Month picker change triggers `calculateInvoice()` recalculate
5. Save Rate → `wecoza_order_save` → green "Saved" flash
6. Submit Claim → `wecoza_invoice_submit` → swaps claim form for read-only submitted state

### ClassController.php Changes

- Registers `wecoza-agent-invoice-js` script
- Conditionally enqueues it when `classAgent > 0`
- Adds `ordersNonce` and `classAgent` to `WeCozaSingleClass` localized object

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] agent-rate-card.php exists and lints clean
- [x] agent-invoice.php exists and lints clean
- [x] agent-invoice.js exists and parses clean (node -c)
- [x] single-class-display.view.php includes both components after Attendance section
- [x] ClassController.php localizes ordersNonce and classAgent, registers and enqueues agent-invoice.js
- [x] Commits 91867ef and 643e03b exist in git log

## Self-Check: PASSED
