# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v9.0 — Agent Orders & Payment Tracking

**Shipped:** 2026-03-11
**Phases:** 5 | **Plans:** 6 | **Sessions:** ~1

### What Was Built
- agent_orders + agent_monthly_invoices PostgreSQL tables with rate change history and idempotent seed
- AgentOrderRepository, AgentInvoiceRepository, AgentInvoiceService with monthly hour calculations
- Six AJAX endpoints (order save/get, invoice calculate/submit/review/list) + auto-order on class save
- All-absent confirmation guard in attendance capture JS
- Agent Rate Card + Monthly Invoice UI inline on single class view
- Admin reconciliation table with approve/dispute workflow and discrepancy highlighting

### What Worked
- Entire milestone shipped in a single day (5 phases, 6 plans, 32 commits)
- Inline UI approach — agent invoice section on existing class view, no new pages needed
- Denormalizing class_id/agent_id on invoices simplified reconciliation queries significantly
- Non-blocking auto-order creation — class save never fails due to order errors
- All-absent guard as pure JS UX — simple implementation, server-side calculation remains source of truth

### What Was Inefficient
- Summary one_liner fields not populated — gsd-tools summary-extract returned null for all summaries
- STATE.md accumulated duplicate/overlapping decisions from both roadmap-level and phase-level entries
- Phase 59 SQL still required manual execution (DDL restriction) — adds human-in-the-loop delay

### Patterns Established
- Agent orders as append-only rate history (new row per rate change, not UPDATE)
- Invoice calculation engine pattern: service queries sessions → aggregates hours → computes discrepancy
- In-place row update pattern for review actions (avoid full table reload)
- Conditional JS enqueue pattern (only load agent-invoice.js when classAgent > 0)

### Key Lessons
1. Denormalization for reconciliation queries was the right call — joins through agent_orders would have been complex
2. ON DELETE RESTRICT on FKs prevents orphaned invoices — defensive referential integrity pays off
3. Browser-level all-absent guard is elegant — minimal code, maximum UX improvement

### Cost Observations
- Model mix: ~30% opus, ~70% sonnet
- Sessions: ~1
- Notable: Fastest 5-phase milestone — all executed in single session

---

## Milestone: v8.0 — Page Tracking & Report Extraction

**Shipped:** 2026-03-09
**Phases:** 3 | **Plans:** 5 | **Sessions:** ~2

### What Was Built
- Page number capture in attendance modal (required field, blank each session, JSONB persistence)
- Page progression display on admin panel (green bar using MAX(page_number) JSONB lateral subquery)
- ReportRepository with CTE-based SQL joining 6 tables for per-class reports
- `[wecoza_class_learner_report]` shortcode with Phoenix-themed UI, AJAX preview, streaming CSV download

### What Worked
- JSONB enrichment pattern — page_number added to existing learner_data column with zero schema changes for Phase 56
- CTE-based report queries — clean separation of monthly hours and page number aggregation
- Reusing Phoenix theme patterns for report UI — consistent look, minimal custom CSS
- Single-day milestone execution — all 3 phases (5 plans) shipped in one session

### What Was Inefficient
- Phase 58-02 required 8 iterative fix commits — JOIN column mismatch (subject_code vs subject_id), spinner ID collision, schedule v2.0 format not anticipated in plan
- RPT-06 checkbox in REQUIREMENTS.md not updated after completion — traceability table stale

### Patterns Established
- JSONB lateral subquery pattern for aggregating per-learner data from attendance sessions
- Report data layer pattern: Repository (SQL) + Service (enrichment/formatting) separation
- Report shortcode pattern: localize class list → AJAX preview → CSV download via GET
- 12-column padded CSV for Excel compatibility

### Key Lessons
1. Schema assumptions in plans should be verified against actual DB before execution (subject_code vs subject_id caused 3 fix commits)
2. JSONB continues to be the right choice for flexible per-learner data — no schema migrations needed
3. When building report UIs, test with real data early to catch format/parsing edge cases

### Cost Observations
- Model mix: ~40% opus, ~60% sonnet
- Sessions: ~2
- Notable: Fastest milestone (single day), but Phase 58-02 had most iterative fixes of any plan in v8.0

---

## Milestone: v7.0 — Agent Attendance Access

**Shipped:** 2026-03-05
**Phases:** 3 | **Plans:** 7 | **Sessions:** ~3

### What Was Built
- Exception button UX + stopped-class capture gate (WEC-182 feedback)
- `wp_agent` WordPress role with `capture_attendance` capability
- Agent-to-WP-user lifecycle (AgentWpUserService auto-provisioning)
- Dedicated agent attendance page with JSONB class lookup
- Three-hook redirect cage locking agents to attendance-only access

### What Worked
- Pre-shipping Phase 53 as quick-17 before roadmap creation — unblocked the plan-heavy phases
- WP role approach (instead of token-based auth) — simple, nonce-compatible, no new auth infrastructure
- JSONB containment query for backup agent lookup — single query for both primary and backup assignments
- App CPT for attendance page — consistent with existing /app/ pattern

### What Was Inefficient
- Milestone audit ran before Phase 55 was implemented, then was stale — audit should have been deferred
- Phase 55-02 was already implemented when /gsd:execute-phase was invoked — retroactive SUMMARY creation needed

### Patterns Established
- Three-hook agent cage pattern: login_redirect + admin_init + template_redirect with slug allowlist
- AgentWpUserService for wp_agent lifecycle management (create/update/delete syncs)
- plugins_loaded priority 6 for role registration (survives plugin updates)

### Key Lessons
1. Quick-task pre-shipping works well for simple requirements — saves a full plan cycle
2. Check if code is already implemented before spawning executor agents
3. Milestone audits are most useful right before completion, not mid-implementation

### Cost Observations
- Model mix: ~30% opus, ~70% sonnet
- Sessions: ~3
- Notable: Phase 53 shipped as quick-task (0 plans), saved significant overhead

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Key Change |
|-----------|----------|--------|------------|
| v9.0 | ~1 | 5 | Single-session 5-phase execution, append-only rate history, inline invoice UI |
| v8.0 | ~2 | 3 | Single-day execution, CTE report pattern, JSONB lateral subqueries |
| v7.0 | ~3 | 3 | Quick-task pre-shipping, retroactive summary creation |
| v6.0 | ~4 | 5 | First attendance capture milestone, 13 plans |
| v5.0 | ~3 | 3 | Progression tracking with LP lifecycle |

### Top Lessons (Verified Across Milestones)

1. Pre-shipping simple fixes as quick-tasks saves full plan cycles (v7.0 Phase 53)
2. WP-native patterns (roles, capabilities, hooks) consistently simpler than custom auth (v6.0, v7.0)
3. JSONB for flexible data storage continues to work well (v1.2, v6.0, v7.0, v8.0 — zero schema changes for page tracking)
4. Schema assumptions in plans should be verified against actual DB before execution (v8.0 Phase 58-02)
5. Denormalization for reporting/reconciliation queries is worth the trade-off — cleaner SQL, faster reads (v9.0)
6. Non-blocking side-effect creation (auto-order on class save) prevents cascading failures (v9.0)
