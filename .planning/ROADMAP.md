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
- 🚧 **v4.1 Lookup Table Admin** — Phases 42-43

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
| 42-43 | v4.1 | 0 | In Progress | — |

**Total: 43 phases, 87 plans, 8 milestones shipped, 1 in progress**

### Phase 42: Lookup Table CRUD Infrastructure + Qualifications Shortcode

**Goal:** Build generic LookupTables module (Controller, Repository, Ajax handler, view, JS). Register [wecoza_manage_qualifications] shortcode. Inline-editable Phoenix-styled table for CRUD on learner_qualifications table.
**Depends on:** Phase 41
**Plans:** 2/2 plans complete

Plans:
- [ ] 42-01-PLAN.md -- Backend: Repository + AJAX handler + Controller + wecoza-core.php registration
- [ ] 42-02-PLAN.md -- Frontend: View template + JavaScript + human verification

### Phase 43: Placement Levels Shortcode

**Goal:** Register [wecoza_manage_placement_levels] shortcode using Phase 42 infrastructure. Same UI pattern, configured for 3-column table (level code + description).
**Depends on:** Phase 42
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd:plan-phase 43 to break down)

---
*Last updated: 2026-02-17 after v4.1 milestone creation*
