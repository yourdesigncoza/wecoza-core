# Requirements: WeCoza Core v6.0 — Agent Attendance Capture

**Defined:** 2026-02-23
**Core Value:** Agents record per-learner training hours per class session, feeding the existing progression system so progress bars and reports reflect real data.

## v6.0 Requirements

### Progress Fix

- [ ] **PROG-01**: Progress percentage uses hours_trained (not hours_present) per Mario's clarification
- [ ] **PROG-02**: Hours completion check uses hours_trained against subject_duration
- [ ] **PROG-03**: Overall learner progress aggregation uses hours_trained for in-progress LPs

### Backend Integration

- [ ] **BACK-01**: `ProgressionService::logHours()` accepts optional session_id and created_by parameters (backward-compatible)
- [ ] **BACK-02**: `LearnerProgressionModel::addHours()` passes session_id and created_by to the hours log insert
- [ ] **BACK-03**: New `class_attendance_sessions` table tracks sessions with status, scheduled hours, and captured_by
- [ ] **BACK-04**: `AttendanceRepository` provides CRUD for attendance sessions with column whitelisting
- [ ] **BACK-05**: `AttendanceService` generates scheduled session dates from class `schedule_data` JSONB via `ScheduleService`
- [ ] **BACK-06**: `AttendanceService` validates session date is a legitimate scheduled date before accepting capture

### Attendance Capture

- [ ] **ATT-01**: Agent can capture per-learner hours present for a scheduled class session
- [ ] **ATT-02**: Hours_trained auto-populated from class schedule (same for all learners in a session)
- [ ] **ATT-03**: Hours present defaults to full scheduled hours; agent adjusts down for late/absent learners
- [ ] **ATT-04**: Hours absent auto-calculated as hours_trained minus hours_present
- [ ] **ATT-05**: Captured attendance feeds into existing `logHours()` pipeline, updating learner_lp_tracking accumulators

### Session Management

- [ ] **SESS-01**: Agent can mark a session as "Client Cancelled" (no hours logged for any learner)
- [ ] **SESS-02**: Agent can mark a session as "Agent Absent" (no hours logged for any learner)
- [ ] **SESS-03**: Duplicate capture prevented — one session record per class per date (DB constraint)
- [ ] **SESS-04**: Captured sessions are locked (view-only); cannot be edited after submission
- [ ] **SESS-05**: Admin can delete a captured session (reverses hours from tracking accumulators)

### UI / Frontend

- [ ] **UI-01**: Attendance section visible on single class display page with summary cards
- [ ] **UI-02**: Session list table shows scheduled sessions with date, day, time, hours, status badge, and action button
- [ ] **UI-03**: Capture modal shows enrolled learners with hours-present number input (min=0, max=scheduled, step=0.5)
- [ ] **UI-04**: Month filter tabs for navigating schedules with many sessions
- [ ] **UI-05**: View detail shows per-learner hours for previously captured sessions (read-only)
- [ ] **UI-06**: AJAX endpoints for session list, capture, mark exception, session detail, and admin delete

## v7+ Requirements

Deferred to future milestones.

### Regulatory Export (from v5.0 Phase 47)

- **REG-01**: Admin can generate monthly progressions report with date-range filter
- **REG-02**: Admin can export report as CSV for Umalusi/DHET submission
- **REG-03**: Export includes all required compliance fields

### Advanced Attendance

- **ADV-01**: Attendance capture restricted to assigned class agent only
- **ADV-02**: Backdating time window limit (e.g., 7 days)
- **ADV-03**: Attendance editing within grace period before lock

### Advanced Export

- **EXP-01**: PDF export with formatted layout (requires TCPDF/DOMPDF)
- **EXP-02**: Excel export with formatting (requires PhpSpreadsheet)

## Out of Scope

| Feature | Reason |
|---------|--------|
| QA visit integration | QA tracks venue compliance, not per-learner hours |
| Automatic hours from schedule | Mario: agents manually capture, schedule defines max only |
| Real-time attendance tracking | Overkill; agents record after the fact |
| Mobile attendance app | Web-first, WP admin works on mobile browsers |
| Biometric/GPS attendance | Not part of current workflow |
| Agent-only restriction | Decided: any logged-in user can capture (simpler) |
| Session editing after capture | Decided: locked for audit integrity |
| Assessment tracking | Mario confirmed NO assessments — hours + portfolio only |

## Traceability

Which phases cover which requirements.

| Requirement | Phase | Status |
|-------------|-------|--------|
| PROG-01 | Phase 48 | Pending |
| PROG-02 | Phase 48 | Pending |
| PROG-03 | Phase 48 | Pending |
| BACK-01 | Phase 48 | Pending |
| BACK-02 | Phase 48 | Pending |
| BACK-03 | Phase 48 | Pending |
| BACK-04 | Phase 49 | Pending |
| BACK-05 | Phase 49 | Pending |
| BACK-06 | Phase 49 | Pending |
| ATT-01 | Phase 51 | Pending |
| ATT-02 | Phase 51 | Pending |
| ATT-03 | Phase 51 | Pending |
| ATT-04 | Phase 51 | Pending |
| ATT-05 | Phase 50 | Pending |
| SESS-01 | Phase 49 | Pending |
| SESS-02 | Phase 49 | Pending |
| SESS-03 | Phase 49 | Pending |
| SESS-04 | Phase 49 | Pending |
| SESS-05 | Phase 49 | Pending |
| UI-01 | Phase 51 | Pending |
| UI-02 | Phase 51 | Pending |
| UI-03 | Phase 51 | Pending |
| UI-04 | Phase 51 | Pending |
| UI-05 | Phase 51 | Pending |
| UI-06 | Phase 50 | Pending |

**Coverage:**
- v6.0 requirements: 25 total
- Mapped to phases: 25
- Unmapped: 0

---
*Requirements defined: 2026-02-23*
*Last updated: 2026-02-23 after roadmap creation — all 25 requirements mapped*
