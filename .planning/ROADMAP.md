# Roadmap: WeCoza Core

## Milestones

- ✅ **v1.0 Events Integration** — Phases 1-7 (shipped 2026-02-02)
- ✅ **v1.1 Quality & Performance** — Phases 8-12 (shipped 2026-02-02)
- ✅ **v1.2 Event Tasks Refactor** — Phases 13-18 (shipped 2026-02-05)
- ✅ **v1.3 Fix Material Tracking Dashboard** — Phases 19-20 (shipped 2026-02-06)
- ✅ **v2.0 Clients Integration** — Phases 21-25 (shipped 2026-02-12)
- ✅ **v3.0 Agents Integration** — Phases 26-30 (shipped 2026-02-12)
- 🔄 **v3.1 Form Field Wiring Fixes** — Phases 31-35

---

## v3.1 Form Field Wiring Fixes (Phases 31-35)

**Milestone:** v3.1 Form Field Wiring Fixes
**Depth:** Standard
**Phases:** 5 (31-35)
**Created:** 2026-02-13

### Overview

Fix all critical, warning, and cleanup issues from comprehensive form field wiring audits across 5 modules. Addresses 6 critical issues (data loss, broken wiring), 28 warnings (security, sanitization, dead code). Source audits in `docs/formfieldanalysis/*.md` provide exact file paths, line numbers, and recommended fixes.

---

### Phase 31: Learners Module Fixes

**Goal:** Fix all critical data loss bugs and security warnings in Learners module forms.

**Source:** `docs/formfieldanalysis/learners-audit.md`

**Dependencies:** None

**Requirements:**
- LRNR-01: Fix `numeracy_level` missing from update shortcode POST processing
- LRNR-02: Resolve `sponsors[]` orphaned field
- LRNR-03: Clean up phantom fields (`date_of_birth`, `suburb`)
- LRNR-04: Remove duplicate `placement_assessment_date` field
- LRNR-05: Remove `nopriv` AJAX registrations
- LRNR-06: Fix `employment_status` initial visibility bug
- LRNR-07: Use `intval()` for `highest_qualification` FK ID
- LRNR-08: Add date format validation for `placement_assessment_date`
- LRNR-09: Fix template literal XSS risk
- LRNR-10: Clean up dead code

**Success Criteria:**
1. No data loss on learner update - `numeracy_level` persists correctly across updates
2. Sponsors feature resolved - either fully implemented with DB persistence or UI removed from both forms
3. Security hardened - all `nopriv` endpoints removed, verified by grep
4. Dead code eliminated - unused AJAX endpoints removed, orphaned form fields cleaned up
5. All fields properly sanitized - FK IDs use `intval()`, dates validated, XSS risks patched

**Plans:** 2 plans

Plans:
- [ ] 31-01-PLAN.md — Verify all 10 LRNR requirements against current code (safety-first)
- [ ] 31-02-PLAN.md — Implement remaining fixes (XSS patch, doc cleanup, dead code removal)

---

### Phase 32: Classes Module Fixes

**Goal:** Fix critical reverse path bugs and security issues in Classes module forms.

**Source:** `docs/formfieldanalysis/classes-audit.md`

**Dependencies:** None

**Requirements:**
- CLS-01: Fix `order_nr` reverse path
- CLS-02: Set `class_agent` from `initial_class_agent` on create
- CLS-03: Remove `nopriv` from QA write endpoints
- CLS-04: Sanitize `stop_dates[]`/`restart_dates[]` with validation
- CLS-05: Type-cast `site_id` with `intval()`
- CLS-06: Sanitize `learner_ids`/`exam_learners` per-entry
- CLS-07: Fix `initial_class_agent` pre-selection
- CLS-08: Migrate agents/supervisors from static arrays to DB queries
- CLS-09: Validate `backup_agent_dates[]` as valid date format

**Success Criteria:**
1. No data loss on class update - `order_nr` survives round-trip from DB to form to DB
2. Class agent properly initialized - new classes have `class_agent` set from `initial_class_agent`
3. QA security hardened - unauthenticated write endpoints removed (create, delete, export, submit)
4. All date arrays validated - stop dates, restart dates, backup agent dates sanitized with format checks
5. Agents/supervisors dynamic - dropdown data reflects current agents table, not hardcoded static lists

---

### Phase 33: Agents Module Fixes

**Goal:** Fix postal code reverse path bug and add missing server-side validation.

**Source:** `docs/formfieldanalysis/agents.md`

**Dependencies:** None

**Requirements:**
- AGT-01: Fix `postal_code` -> `residential_postal_code` mapping
- AGT-02: Add server-side validation for 14 HTML-required fields
- AGT-03: Sanitize `preferred_working_area_1/2/3` with `absint()`
- AGT-04: Remove `agent_notes` from agents table insert/update whitelist
- AGT-05: Remove `residential_town_id` from insert whitelist
- AGT-06: Extract shared display methods into shared service (DRY)

**Success Criteria:**
1. Postal code pre-populates in edit mode - field mapping fixed, reverse path works
2. Server-side validation complete - all 14 HTML-required fields validated, bypassing HTML required not possible
3. Working areas properly sanitized - `absint()` applied at controller level for defense-in-depth
4. Dead columns removed from whitelists - `agent_notes` and `residential_town_id` cleaned up
5. Code duplication eliminated - shared display methods extracted to service, used by controller and AJAX handler

---

### Phase 34: Clients Module Fixes

**Goal:** Remove duplicate AJAX submission and unify nonce handling across client forms.

**Source:** `docs/formfieldanalysis/clients-audit.md`

**Dependencies:** None

**Requirements:**
- CLT-01: Remove inline submit handler from update form
- CLT-02: Add `wp_nonce_field()` to capture form
- CLT-03: Remove `client_town_id` from repository whitelists
- CLT-04: Unify nonce action strings to `clients_nonce_action`
- CLT-05: Remove 7 unused AJAX endpoints

**Success Criteria:**
1. Single AJAX submission per update - inline duplicate handler removed, verified by testing
2. Non-AJAX fallback functional - capture form includes nonce field, works without JavaScript
3. Dead code removed - `client_town_id` removed from whitelists, unused endpoints removed
4. Nonce actions consistent - all forms and controllers use `clients_nonce_action`
5. Attack surface reduced - 7 unused AJAX endpoints removed, verified by grep

---

### Phase 35: Events Module Fixes

**Goal:** Add late escaping for presenter-generated HTML and sync tracking table with JSONB.

**Source:** `docs/formfieldanalysis/events-module-audit.md`

**Dependencies:** None

**Requirements:**
- EVT-01: Add late escaping for `summary_html` output
- EVT-02: Add late escaping for `notification_badge_html`/`status_badge_html`
- EVT-03: Update `markDelivered()` to set `materials_delivered_at` and `delivery_status`
- EVT-04: Remove duplicate test notification JS

**Success Criteria:**
1. Late escaping enforced - all presenter-generated HTML explicitly escaped at output point
2. Material tracking table synced - `materials_delivered_at` and `delivery_status = 'delivered'` set when materials marked delivered
3. Duplicate JS removed - test notification handler consolidated to single location
4. WordPress escaping best practices followed - no pre-built HTML output without escaping

---

## Progress

**Execution Order:**
Phases execute in numeric order.

| Phase | Milestone | Requirements | Status | Completion |
|-------|-----------|--------------|--------|------------|
| 31 | v3.1 | 10 | Not started | 0% |
| 32 | v3.1 | 9 | Not started | 0% |
| 33 | v3.1 | 6 | Not started | 0% |
| 34 | v3.1 | 5 | Not started | 0% |
| 35 | v3.1 | 4 | Not started | 0% |

**Overall v3.1:** 0/34 requirements complete (0%)

---

<details>
<summary>✅ v3.0 Agents Integration (Phases 26-30) — SHIPPED 2026-02-12</summary>

**Source:** `.integrate/wecoza-agents-plugin/`
**Target:** wecoza-core `src/Agents/`
**Scope:** 13 classes, 6 templates, 5 JS files, 2 AJAX endpoints, 3 shortcodes, 4 DB tables (already exist)

- [x] Phase 26: Foundation Architecture — namespace, DB migration, model, repository, helpers — **Plans:** 2 plans — completed 2026-02-12
  - [x] 26-01-PLAN.md — Namespace registration, PostgresConnection fix, helpers migration
  - [x] 26-02-PLAN.md — AgentRepository + AgentModel creation
- [x] Phase 27: Controllers, Views, JS, AJAX — controller, AJAX handlers, views, JS, wiring — **Plans:** 3 plans — completed 2026-02-12
  - [x] 27-01-PLAN.md — AgentsController, AgentsAjaxHandlers, wecoza-core.php wiring
  - [x] 27-02-PLAN.md — View template migration (6 templates)
  - [x] 27-03-PLAN.md — JS asset migration (5 files) with unified localization
- [x] Phase 28: Wiring Verification & Fixes — shortcode rendering, integration bugs — **Plans:** 2 plans — completed 2026-02-12
  - [x] 28-01-PLAN.md — Fix nonce mismatch, inline script duplication, missing DOM elements
  - [x] 28-02-PLAN.md — WP-CLI verification, debug log review, browser smoke test
- [x] Phase 29: Feature Verification & Performance — CRUD testing, file uploads, statistics — **Plans:** 2 plans — completed 2026-02-12
  - [x] 29-01-PLAN.md — CLI feature parity test script (shortcodes, AJAX, classes, DB, views, statistics, working areas)
  - [x] 29-02-PLAN.md — Code audit, manual browser testing (CRUD, file uploads, statistics badges, performance)
- [x] Phase 30: Integration Testing & Cleanup — parity test, plugin deactivation, source removal — **Plans:** 2 plans — completed 2026-02-12
  - [x] 30-01-PLAN.md — Pre-deactivation safety checks + standalone plugin deactivation (checkpoint)
  - [x] 30-02-PLAN.md — Dangling reference detection, source removal, project state update

</details>

<details>
<summary>✅ v2.0 Clients Integration (Phases 21-25) — SHIPPED 2026-02-12</summary>

- [x] Phase 21: Foundation Architecture (2/2 plans) — completed 2026-02-11
- [x] Phase 22: Client Management (2/2 plans) — completed 2026-02-11
- [x] Phase 23: Location Management (2/2 plans) — completed 2026-02-12
- [x] Phase 24: Sites Hierarchy (2/2 plans) — completed 2026-02-12
- [x] Phase 25: Integration Testing & Cleanup (2/2 plans) — completed 2026-02-12

</details>

<details>
<summary>✅ v1.3 Fix Material Tracking Dashboard (Phases 19-20) — SHIPPED 2026-02-06</summary>

- [x] Phase 19: Material Dashboard Rewrite (2/2 plans) — completed 2026-02-06
- [x] Phase 20: Dashboard Enhancements (1/1 plan) — completed 2026-02-06

</details>

<details>
<summary>✅ v1.2 Event Tasks Refactor (Phases 13-18) — SHIPPED 2026-02-05</summary>

- [x] Phase 13: Event System Foundation (3/3 plans) — completed 2026-02-05
- [x] Phase 14: Task Derivation (3/3 plans) — completed 2026-02-05
- [x] Phase 15: Bidirectional Sync (3/3 plans) — completed 2026-02-05
- [x] Phase 16: Notification System (3/3 plans) — completed 2026-02-05
- [x] Phase 17: Code Cleanup (2/2 plans) — completed 2026-02-05
- [x] Phase 18: Multi-Recipient Config (2/2 plans) — completed 2026-02-05

</details>

<details>
<summary>✅ v1.1 Quality & Performance (Phases 8-12) — SHIPPED 2026-02-02</summary>

- [x] Phase 8: Bug Fixes (2/2 plans) — completed 2026-02-02
- [x] Phase 9: Security Hardening (2/2 plans) — completed 2026-02-02
- [x] Phase 10: Performance Optimization (3/3 plans) — completed 2026-02-02
- [x] Phase 11: Data Privacy (3/3 plans) — completed 2026-02-02
- [x] Phase 12: Architecture Improvements (3/3 plans) — completed 2026-02-02

</details>

<details>
<summary>✅ v1.0 Events Integration (Phases 1-7) — SHIPPED 2026-02-02</summary>

- [x] Phase 1: Foundation Architecture (2/2 plans) — completed 2026-02-02
- [x] Phase 2: Task Management Core (3/3 plans) — completed 2026-02-02
- [x] Phase 3: Material Tracking (2/2 plans) — completed 2026-02-02
- [x] Phase 4: AI Summarization (2/2 plans) — completed 2026-02-02
- [x] Phase 5: Email Notifications (2/2 plans) — completed 2026-02-02
- [x] Phase 6: PostgreSQL Triggers (1/1 plan) — completed 2026-02-02
- [x] Phase 7: Testing & Verification (1/1 plan) — completed 2026-02-02

</details>

---

*Roadmap created: 2026-01-29*
*Last updated: 2026-02-13 after v3.1 roadmap created*
