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
- 🚧 **v6.0 Agent Attendance Capture** — Phases 48-51 (in progress)

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

### 🚧 v6.0 Agent Attendance Capture (In Progress)

**Milestone Goal:** Build an attendance capture UI where agents record per-learner hours for each class session, feeding the existing (but unused) `logHours()` infrastructure to make progression tracking actually work.

- [x] **Phase 48: Foundation** — Schema, progress calculation fix, and logHours signature extension (completed 2026-02-23)
- [ ] **Phase 49: Backend Logic** — AttendanceRepository, AttendanceService, session management rules
- [ ] **Phase 50: AJAX Endpoints** — Six endpoints connecting service layer to frontend
- [ ] **Phase 51: Frontend** — Attendance view, capture modal, JS wiring, month filter

## Phase Details

### Phase 48: Foundation
**Goal**: The data layer exists and progress calculation uses the correct field — all downstream attendance work builds on accurate infrastructure
**Depends on**: Phase 46 (v5.0 complete)
**Requirements**: PROG-01, PROG-02, PROG-03, BACK-01, BACK-02, BACK-03
**Success Criteria** (what must be TRUE):
  1. Learner progression bars read from hours_trained (not hours_present) in getProgressPercentage() and isHoursComplete()
  2. Overall learner progress aggregation (getLearnerOverallProgress()) uses hours_trained for in-progress LPs
  3. The class_attendance_sessions table exists in PostgreSQL with a unique constraint on (class_id, session_date)
  4. ProgressionService::logHours() and LearnerProgressionModel::addHours() accept session_id and created_by without breaking existing callers
**Plans:** 2/2 plans complete

Plans:
- [ ] 48-01-PLAN.md — Progress fix: change hours_present to hours_trained in model, service, SQL queries, view templates, and JS
- [ ] 48-02-PLAN.md — Schema + signature extension: class_attendance_sessions CREATE TABLE SQL, extend logHours() and addHours() with session_id and created_by

### Phase 49: Backend Logic
**Goal**: Attendance business logic is fully encapsulated — sessions are created, queried, validated, and exception-marked server-side with correct hours propagation
**Depends on**: Phase 48
**Requirements**: BACK-04, BACK-05, BACK-06, SESS-01, SESS-02, SESS-03, SESS-04, SESS-05
**Success Criteria** (what must be TRUE):
  1. AttendanceRepository enforces column whitelisting and the unique (class_id, session_date) constraint prevents duplicate session records
  2. AttendanceService generates the correct scheduled session dates for a class by delegating to ScheduleService::generateScheduleEntries()
  3. AttendanceService rejects capture if the submitted date is not a legitimate scheduled date for that class
  4. Sessions marked "Client Cancelled" or "Agent Absent" create a session record with zero hours — no hours logged to learner_lp_tracking accumulators
  5. Admin delete of a captured session reverses the accumulated hours from learner_lp_tracking and removes the session record
**Plans**: TBD

Plans:
- [ ] 49-01: AttendanceRepository — findByClass(), findByClassAndDate(), createSession(), updateSession(), deleteSession() with column whitelisting
- [ ] 49-02: AttendanceService — generateSessionList(), validateSessionDate(), captureAttendance(), markException(), deleteAndReverseHours()

### Phase 50: AJAX Endpoints
**Goal**: All six frontend operations have working, secured AJAX endpoints that return structured JSON and delegate all logic to AttendanceService
**Depends on**: Phase 49
**Requirements**: UI-06, ATT-05
**Success Criteria** (what must be TRUE):
  1. wecoza_attendance_get_sessions returns session list with correct status and action state for each session
  2. wecoza_attendance_capture creates a session record, calls logHours() for each learner, and returns success
  3. wecoza_attendance_mark_exception creates a zero-hours session with the correct exception status
  4. wecoza_attendance_get_detail returns per-learner hours breakdown for a captured session
  5. wecoza_attendance_admin_delete reverses hours from tracking accumulators and removes the session record
  6. All five endpoints validate nonce and return structured JSON error responses on failure
**Plans**: TBD

Plans:
- [ ] 50-01: AttendanceAjaxHandlers — all six handlers with nonce validation and service delegation; wire registration into wecoza-core.php and enqueue JS config via ClassController

### Phase 51: Frontend
**Goal**: Agent opens a class page, navigates sessions by month, captures attendance via modal, and views prior sessions read-only — progression data updates without any separate action
**Depends on**: Phase 50
**Requirements**: ATT-01, ATT-02, ATT-03, ATT-04, UI-01, UI-02, UI-03, UI-04, UI-05
**Success Criteria** (what must be TRUE):
  1. The single class display page shows an Attendance section with summary cards (total sessions, captured, pending)
  2. The session table shows each scheduled session with date, day, time range, hours, status badge, and action button
  3. Month filter tabs appear above the session table and clicking a tab narrows the list to sessions in that month only
  4. Clicking "Capture" opens a modal with enrolled learners, hours_trained pre-filled, and hours_present defaulting to the same value (adjustable down in 0.5-step increments, min 0)
  5. Hours absent auto-calculates as hours_trained minus hours_present in real time without manual input
  6. Clicking "View" on a captured session shows a read-only per-learner hours breakdown
**Plans**: TBD

Plans:
- [ ] 51-01: Attendance view template — attendance.php component with summary cards, month tabs, session table; insert into single-class-display.view.php; enqueue JS in ClassController
- [ ] 51-02: attendance-capture.js — session list rendering, month filter logic, capture modal, view-detail modal, all AJAX calls wired to backend

## Progress

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
| 48. Foundation | 2/2 | Complete    | 2026-02-23 | - |
| 49. Backend Logic | v6.0 | 0/2 | Not started | - |
| 50. AJAX Endpoints | v6.0 | 0/1 | Not started | - |
| 51. Frontend | v6.0 | 0/2 | Not started | - |

**Total: 46 phases complete, 103 plans executed — v6.0 in progress (phases 48-51)**

---
*Last updated: 2026-02-23 after v6.0 roadmap created*
