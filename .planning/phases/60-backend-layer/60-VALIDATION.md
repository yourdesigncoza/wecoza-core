---
phase: 60
slug: backend-layer
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-11
---

# Phase 60 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None — no automated test infrastructure in project |
| **Config file** | None — Wave 0 gap |
| **Quick run command** | Manual DB inspection via MCP read-only queries |
| **Full suite command** | Manual AJAX calls + MCP DB verification |
| **Estimated runtime** | ~60 seconds (manual) |

---

## Sampling Rate

- **After every task commit:** Manual DB inspection via MCP read-only queries
- **After every plan wave:** Verify via read-only MCP SQL queries against agent_orders and agent_monthly_invoices
- **Before `/gsd:verify-work`:** All six AJAX endpoints return expected JSON via browser network tab
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 60-01-01 | 01 | 1 | ORD-02 | integration | MCP: `SELECT * FROM agent_orders WHERE class_id = ?` | N/A manual | ⬜ pending |
| 60-01-02 | 01 | 1 | ORD-02 | integration | AJAX: `wecoza_order_save` / `wecoza_order_get` | N/A manual | ⬜ pending |
| 60-02-01 | 02 | 1 | INV-03 | integration | MCP: `SELECT * FROM agent_monthly_invoices WHERE agent_id = ? AND month = ?` | N/A manual | ⬜ pending |
| 60-02-02 | 02 | 1 | INV-03 | integration | AJAX: `wecoza_invoice_calculate` / `wecoza_invoice_submit` | N/A manual | ⬜ pending |
| 60-02-03 | 02 | 1 | INV-03 | integration | AJAX: `wecoza_invoice_review` / `wecoza_invoice_list` | N/A manual | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] No PHP unit test framework — verify by DB inspection and manual AJAX calls
- [ ] Verification strategy: use MCP read-only queries to confirm DB state after triggering class save and invoice submit actions

*Existing infrastructure covers verification via manual DB and AJAX inspection.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Order auto-created on class save | ORD-02 | Requires triggering class save AJAX with order_nr + class_agent | 1. Save class with order_nr and class_agent via UI 2. Query `agent_orders` for new row |
| calculateMonthSummary returns correct totals | INV-03 | Requires attendance data in DB + service call | 1. Ensure attendance sessions exist 2. Call `wecoza_invoice_calculate` AJAX 3. Verify JSON response values |
| Discrepancy stored on invoice submit | INV-03 | Requires agent-claimed hours vs calculated | 1. Call `wecoza_invoice_submit` with claimed_hours 2. Query `agent_monthly_invoices` for discrepancy_hours |
| Nonce + capability enforcement | ORD-02, INV-03 | Security boundary test | 1. Call endpoints without nonce — expect 403 2. Call with wrong capability — expect 403 |

---

## Validation Sign-Off

- [ ] All tasks have manual verification procedures documented
- [ ] Sampling continuity: manual verification after each task commit
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
