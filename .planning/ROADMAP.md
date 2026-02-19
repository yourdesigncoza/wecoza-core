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
- 🚧 **v5.0 Learner Progression** — Phases 44-47 (in progress)

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

### 🚧 v5.0 Learner Progression (In Progress)

**Milestone Goal:** Complete learner LP progression tracking — AJAX wiring, admin management, WEC-165 reporting dashboard, and regulatory CSV export.

#### Phase 44: AJAX Wiring + Class Integration
**Goal**: The existing progression UI works end-to-end — mark-complete, portfolio upload, and data fetch all fire correctly, and class forms show progression context
**Depends on**: Phase 43
**Requirements**: AJAX-01, AJAX-02, AJAX-03, AJAX-04, CLASS-01, CLASS-02, CLASS-03
**Success Criteria** (what must be TRUE):
  1. Admin clicks "Mark Complete" on a learner progression and the status changes to completed with optional portfolio file accepted
  2. Admin uploads an additional portfolio file to an existing in-progress LP and receives a success response
  3. Progression data loads on the learner view without a page reload via the fetch handler
  4. Available Learners table in class capture shows a "Last Completed Course" column populated from progression history
  5. Adding a learner to a class who already has an active LP surfaces a visible collision warning before confirmation
**Plans:** 3/3 plans complete

Plans:
- [ ] 44-01-PLAN.md — Register progression AJAX handlers (mark-complete, portfolio-upload, fetch-data, collision-log)
- [ ] 44-02-PLAN.md — Enhance learner progressions frontend (confirmation modal, in-place updates, skeleton loading, upload progress)
- [ ] 44-03-PLAN.md — Class integration UI (Last Completed Course column, collision modal enhancement, learner modal progression display)

#### Phase 45: Admin Management
**Goal**: Admin can manage all progressions from a single shortcode — filter, bulk-complete, inspect audit trail, start new LPs, and put LPs on hold
**Depends on**: Phase 44
**Requirements**: ADMIN-01, ADMIN-02, ADMIN-03, ADMIN-04, ADMIN-05, ADMIN-06
**Success Criteria** (what must be TRUE):
  1. Admin opens `[wecoza_progression_admin]` and sees a filterable table of all progressions (client, class, LP, status)
  2. Admin selects multiple in-progress progressions, clicks "Bulk Complete", and all selected rows change status
  3. Admin clicks the audit icon on any row and sees the full hours log for that progression
  4. Admin can manually start a new LP for a learner by selecting learner + LP from a form within the page
  5. Admin can toggle a progression to On Hold or resume it, and the status badge reflects the change immediately
**Plans:** 3/3 plans complete

Plans:
- [ ] 45-01-PLAN.md — AJAX handlers for admin operations (bulk-complete, hours-log, start-LP, hold/resume, admin-fetch)
- [ ] 45-02-PLAN.md — Shortcode [wecoza_progression_admin] + view template with filters, table, modals
- [ ] 45-03-PLAN.md — JS wiring for all admin management actions (filters, bulk, audit, start, hold/resume)

#### Phase 46: Learner Progression Report
**Goal**: Admin can search learners, view their LP timeline, filter by employer, and see Phoenix summary cards — satisfying WEC-165
**Depends on**: Phase 44
**Requirements**: RPT-01, RPT-02, RPT-03, RPT-04, RPT-05, RPT-06
**Success Criteria** (what must be TRUE):
  1. Admin searches by learner name or ID and the report filters to matching learners instantly
  2. Admin selects a single learner and sees a chronological timeline of LP, class, and date entries
  3. Admin filters by employer/client company and the report collapses to learners from that company only
  4. Multi-learner view groups learners by company with expandable rows showing individual timelines
  5. Phoenix-styled summary cards above the report show totals, completion rates, and average hours at a glance
**Plans:** 2/3 plans executed

Plans:
- [ ] 46-01-PLAN.md -- Backend AJAX handler + repository methods for report queries (search, employer filter, summary stats)
- [ ] 46-02-PLAN.md -- Shortcode [wecoza_learner_progression_report] + view template with summary cards, filters, results container
- [ ] 46-03-PLAN.md -- JS wiring for search, filter, summary card population, company-grouped accordion, learner timeline rendering

#### Phase 47: Regulatory Export
**Goal**: Admin can generate a compliance-ready monthly progressions report with date-range filter and download it as CSV
**Depends on**: Phase 45
**Requirements**: REG-01, REG-02, REG-03, REG-04
**Success Criteria** (what must be TRUE):
  1. Admin selects a date range and generates a monthly progressions report showing learner, LP, class, client, dates, and hours
  2. Admin clicks "Export CSV" and a correctly structured CSV file downloads with all report columns
  3. The exported data includes all fields required for Umalusi/DHET submission without manual post-processing
**Plans**: TBD

## Progress

| Phase | Milestone | Plans | Status | Completed |
|-------|-----------|-------|--------|-----------|
| 1-7 | v1 | 13 | Complete | 2026-02-02 |
| 8-12 | v1.1 | 13 | Complete | 2026-02-02 |
| 13-18 | v1.2 | 16 | Complete | 2026-02-05 |
| 19 | v1.3 | 2 | Complete | 2026-02-06 |
| 21-25 | v2.0 | 10 | Complete | 2026-02-12 |
| 26-30 | v3.0 | 11 | Complete | 2026-02-12 |
| 31-35 | v3.1 | 8 | Complete | 2026-02-13 |
| 36-41 | v4.0 | 14 | Complete | 2026-02-16 |
| 42-43 | v4.1 | 3 | Complete | 2026-02-17 |
| 44 | 3/3 | Complete    | 2026-02-18 | - |
| 45 | 3/3 | Complete    | 2026-02-18 | - |
| 46 | 2/3 | In Progress|  | - |
| 47 | v5.0 | TBD | Not started | - |

**Total: 43 phases complete, 90 plans, 9 milestones shipped — v5.0 in progress (phases 44-47)**

---
*Last updated: 2026-02-18 after Phase 44 planning*
