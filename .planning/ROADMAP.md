# Roadmap: WeCoza Core

## Milestones

- ✅ **v1 Events Integration** — Phases 1-7 (shipped 2026-02-02)
- ✅ **v1.1 Quality & Performance** — Phases 8-12 (shipped 2026-02-02)
- ✅ **v1.2 Event Tasks Refactor** — Phases 13-18 (shipped 2026-02-05)
- ✅ **v1.3 Fix Material Tracking** — Phase 19 (shipped 2026-02-06)
- ✅ **v2.0 Clients Integration** — Phases 21-25 (shipped 2026-02-12)
- ✅ **v3.0 Agents Integration** — Phases 26-30 (shipped 2026-02-12)
- ✅ **v3.1 Form Field Wiring Fixes** — Phases 31-35 (shipped 2026-02-13)
- ✅ **v4.0 Technical Debt** — Phases 36-41 (shipped 2026-02-16)
- ✅ **v4.1 Lookup Table Admin** — Phases 42-43 (shipped 2026-02-17)
- ✅ **v5.0 Learner Progression** — Phases 44-46 (shipped 2026-02-23)
- ✅ **v6.0 Agent Attendance Capture** — Phases 48-52 (shipped 2026-02-24)
- 🚧 **v7.0 Agent Attendance Access** — Phases 53-55 (in progress)

## Phases

<details>
<summary>✅ v1 Events Integration (Phases 1-7) — SHIPPED 2026-02-02</summary>

- [x] Phase 1-7: Events module migration, task management, material tracking, AI summarization, notifications
- 13 plans total

See: `.planning/milestones/v1-ROADMAP.md`

</details>

<details>
<summary>✅ v1.1 Quality & Performance (Phases 8-12) — SHIPPED 2026-02-02</summary>

- [x] Phase 8-12: Security hardening, performance improvements, bug fixes, architecture refactoring
- 13 plans total

See: `.planning/milestones/v1.1-ROADMAP.md`

</details>

<details>
<summary>✅ v1.2 Event Tasks Refactor (Phases 13-18) — SHIPPED 2026-02-05</summary>

- [x] Phase 13-18: Event-based task system, bidirectional sync, notification system, code cleanup
- 16 plans total

See: `.planning/milestones/v1.2-ROADMAP.md`

</details>

<details>
<summary>✅ v1.3 Fix Material Tracking (Phase 19) — SHIPPED 2026-02-06</summary>

- [x] Phase 19: Material Tracking Dashboard rewired to event_dates JSONB
- 2 plans total

See: `.planning/milestones/v1.3-ROADMAP.md`

</details>

<details>
<summary>✅ v2.0 Clients Integration (Phases 21-25) — SHIPPED 2026-02-12</summary>

- [x] Phase 21-25: Client CRUD, location management, sites hierarchy, shortcodes, cleanup
- 10 plans total

See: `.planning/milestones/v2.0-ROADMAP.md`

</details>

<details>
<summary>✅ v3.0 Agents Integration (Phases 26-30) — SHIPPED 2026-02-12</summary>

- [x] Phase 26-30: Agent module migration, CRUD, file uploads, statistics, notes, absences
- 11 plans total

See: `.planning/milestones/v3.0-ROADMAP.md`

</details>

<details>
<summary>✅ v3.1 Form Field Wiring Fixes (Phases 31-35) — SHIPPED 2026-02-13</summary>

- [x] Phase 31-35: Learners XSS fix, Classes data integrity, Agents validation, Clients security, Events escaping
- 8 plans total

See: `.planning/milestones/v3.1-ROADMAP.md`

</details>

<details>
<summary>✅ v4.0 Technical Debt (Phases 36-41) — SHIPPED 2026-02-16</summary>

- [x] Phase 36: Service Layer Extraction (3/3 plans) — completed 2026-02-16
- [x] Phase 37: Model Architecture Unification (2/2 plans) — completed 2026-02-16
- [x] Phase 38: Address Storage Normalization (2/2 plans) — completed 2026-02-16
- [x] Phase 39: Repository Pattern Enforcement (2/2 plans) — completed 2026-02-16
- [x] Phase 40: Return Type Hints & Constants (3/3 plans) — completed 2026-02-16
- [x] Phase 41: Architectural Verification (2/2 plans) — completed 2026-02-16

See: `.planning/milestones/v4.0-ROADMAP.md`

</details>

<details>
<summary>✅ v4.1 Lookup Table Admin (Phases 42-43) — SHIPPED 2026-02-17</summary>

- [x] Phase 42: Lookup Table CRUD Infrastructure + Qualifications Shortcode (2/2 plans) — completed 2026-02-17
- [x] Phase 43: Placement Levels Shortcode (1/1 plan) — completed 2026-02-17

See: `.planning/milestones/v4.1-ROADMAP.md`

</details>

<details>
<summary>✅ v5.0 Learner Progression (Phases 44-46) — SHIPPED 2026-02-23</summary>

- [x] Phase 44: AJAX Wiring + Class Integration (3/3 plans) — completed 2026-02-18
- [x] Phase 45: Admin Management (3/3 plans) — completed 2026-02-18
- [x] Phase 46: Learner Progression Report (3/3 plans) — completed 2026-02-19
- Phase 47: Regulatory Export — deferred to v7+

See: `.planning/milestones/v5.0-ROADMAP.md`

</details>

<details>
<summary>✅ v6.0 Agent Attendance Capture (Phases 48-52) — SHIPPED 2026-02-24</summary>

- [x] Phase 48: Foundation (2/2 plans) — completed 2026-02-23
- [x] Phase 49: Backend Logic (2/2 plans) — completed 2026-02-23
- [x] Phase 50: AJAX Endpoints (1/1 plans) — completed 2026-02-23
- [x] Phase 51: Frontend (2/2 plans) — completed 2026-02-23
- [x] Phase 52: Class Activation Logic (6/6 plans) — completed 2026-02-24
- 13 plans total

See: `.planning/milestones/v6.0-ROADMAP.md`

</details>

### 🚧 v7.0 Agent Attendance Access (In Progress)

**Milestone Goal:** Complete remaining WEC-182 attendance feedback — agent-restricted capture page, exception button UX, and stopped-class capture until stop date.

- [x] **Phase 53: Attendance UX Fixes** - Exception button label + stopped-class capture gate (shipped via quick-17, 2026-03-04)
- [x] **Phase 54: Agent Foundation** - WordPress role, DB schema, capability guards (completed 2026-03-04)
- [ ] **Phase 55: Agent Attendance Page** - Shortcode, minimal template, login redirect

## Phase Details

### Phase 53: Attendance UX Fixes
**Goal**: Agents can see and use the exception button, and can capture attendance on stopped classes up to the stop date
**Depends on**: Phase 52
**Requirements**: EXC-01, EXC-02, STP-01, STP-02, STP-03
**Success Criteria** (what must be TRUE):
  1. Agent sees a labelled "Exception" button (not an icon-only control) in the session action column
  2. Exception button uses Phoenix filled warning style, visually distinct from other actions
  3. Agent can open and submit the capture modal for any stopped-class session whose date is on or before the class stop date
  4. Server rejects attendance capture attempts on stopped-class sessions with dates after the stop date
  5. Sessions after the stop date show no action controls (dash only)
**Plans**: Shipped via quick-17 (2026-03-04) — no plans required

Plans:
- [x] 53-00: Pre-shipped via quick-17 (exception button label + stopped-class capture gate)

### Phase 54: Agent Foundation
**Goal**: The `wp_agent` WordPress role exists with the `capture_attendance` capability, the `agents` table has a `wp_user_id` column, and all attendance AJAX handlers enforce the capability server-side
**Depends on**: Phase 53
**Requirements**: AGT-01, AGT-02, AGT-03, AGT-04
**Success Criteria** (what must be TRUE):
  1. `wp_agent` WordPress role is present after plugin activation AND after plugin update (no manual reactivation required)
  2. Administrator role retains `capture_attendance` capability — admins can still capture attendance without interruption
  3. `agents` table has a `wp_user_id` INTEGER column in the database
  4. Attendance capture and exception-marking AJAX endpoints return a permissions error when called by a logged-in user without `capture_attendance` capability
**Plans**: 2 plans

Plans:
- [ ] 54-01: Role registration + capability wiring
- [ ] 54-02: Schema migration + AgentRepository method + AJAX capability guards

### Phase 55: Agent Attendance Page
**Goal**: A logged-in agent user is redirected to a dedicated attendance page showing only their assigned classes, with no access to the rest of WeCoza
**Depends on**: Phase 54
**Requirements**: AGT-05, AGT-06, AGT-07, AGT-08
**Success Criteria** (what must be TRUE):
  1. Agent logs in and lands on the attendance page directly — no WP dashboard or WeCoza nav visible
  2. Attendance page lists only the classes where the agent is the primary agent or a backup agent
  3. Agent can capture attendance for their listed classes using the existing capture UI
  4. Navigating away from the attendance page redirects the agent back to it — no other WeCoza pages are accessible
**Plans**: 2 plans

Plans:
- [ ] 55-01-PLAN.md — AgentAccessController + shortcode + class lookup + view� AgentAccessController + shortcode + class lookup + view
- [ ] 55-02-PLAN.md — Login redirect + admin guard + page cage + bootstrap wiring� Login redirect + admin guard + page cage + bootstrap wiring

## Progress

**Execution Order:** 53 → 54 → 55

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1-7 | v1 | 13/13 | Complete | 2026-02-02 |
| 8-12 | v1.1 | 13/13 | Complete | 2026-02-02 |
| 13-18 | v1.2 | 16/16 | Complete | 2026-02-05 |
| 19 | v1.3 | 2/2 | Complete | 2026-02-06 |
| 21-25 | v2.0 | 10/10 | Complete | 2026-02-12 |
| 26-30 | v3.0 | 11/11 | Complete | 2026-02-12 |
| 31-35 | v3.1 | 8/8 | Complete | 2026-02-13 |
| 36-41 | v4.0 | 14/14 | Complete | 2026-02-16 |
| 42-43 | v4.1 | 3/3 | Complete | 2026-02-17 |
| 44-46 | v5.0 | 9/9 | Complete | 2026-02-23 |
| 48-52 | v6.0 | 13/13 | Complete | 2026-02-24 |
| 53 | v7.0 | 1/1 | Complete | 2026-03-04 |
| 54 | 3/3 | Complete    | 2026-03-04 | - |
| 55 | v7.0 | 0/2 | Not started | - |

**Total: 53 phases complete (Phase 53 pre-shipped), 112 plans executed across 11 milestones + v7.0**

---
*Last updated: 2026-03-04 — v7.0 roadmap created; Phase 53 pre-shipped via quick-17*
