# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-23)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure
**Current focus:** v6.0 Class Activation Logic — Phase 52

## Current Position

Phase: 52 of 52 (Class Activation Logic)
Plan: 4 of 6 in current phase — Plan 04 complete
Status: In progress
Last activity: 2026-02-24 — Phase 52 Plan 04 complete: three-way Draft/Active/Stopped Phoenix badge on classes listing (wecoza_resolve_class_status()), 6th status summary card on single-class detail page, ClassController active_count updated to use class_status rather than legacy schedule-pause check

Progress: [████████████████████] 49/51 phases in progress

## Milestone History

| Version | Name | Shipped | Phases | Plans |
|---------|------|---------|--------|-------|
| v5.0 | Learner Progression | 2026-02-23 | 44-46 | 9 |
| v4.1 | Lookup Table Admin | 2026-02-17 | 42-43 | 3 |
| v4.0 | Technical Debt | 2026-02-16 | 36-41 | 14 |
| v3.1 | Form Field Wiring Fixes | 2026-02-13 | 31-35 | 8 |
| v3.0 | Agents Integration | 2026-02-12 | 26-30 | 11 |
| v2.0 | Clients Integration | 2026-02-12 | 21-25 | 10 |
| v1.3 | Fix Material Tracking Dashboard | 2026-02-06 | 19-20 | 3 |
| v1.2 | Event Tasks Refactor | 2026-02-05 | 13-18 | 16 |
| v1.1 | Quality & Performance | 2026-02-02 | 8-12 | 13 |
| v1 | Events Integration | 2026-02-02 | 1-7 | 13 |

See: .planning/ROADMAP.md for current milestone detail

## Accumulated Context

### Decisions

- WEC-178: Mario confirmed agents capture per-learner hours per class session; schedule defines max hours; Client Cancelled / Agent Absent don't count toward hours_trained
- [Phase 48-01]: Progress calculation uses hours_trained (not hours_present) per Mario's clarification — fixed in PHP model (getProgressPercentage/isHoursComplete), service (getLearnerOverallProgress), SQL CTEs (ClassRepository/LearnerRepository/LearnerProgressionRepository), views, and JS
- v6.0: Any logged-in user can capture attendance (no agent-only restriction)
- v6.0: Captured sessions are locked (view-only); admin can delete + re-capture for audit integrity
- v6.0: Backdating allowed for any past scheduled date up to today
- [Phase 47-01]: Admin-only access (manage_options) enforced on both regulatory endpoints — PII data requires highest privilege
- [Phase 47-01]: Separate first_name/surname columns returned (not CONCAT) to support regulatory form field mapping
- [Phase 47-01]: UTF-8 BOM prepended to CSV output for Excel UTF-8 compatibility
- [Phase 47-02]: Client dropdown populated from first fetch response (guard flag) — no separate AJAX call needed
- [Phase 47-02]: CSV export uses window.location.href redirect — browser handles file download from Content-Disposition header
- [Phase 48-02]: class_attendance_sessions status column uses VARCHAR(30) CHECK constraint on 4 values: pending, captured, client_cancelled, agent_absent
- [Phase 48-02]: captured_by uses WP user ID (not agent record ID) for consistency with learner_hours_log.created_by
- [Phase 48-02]: session_id/created_by added as ?int = null to logHours/addHours — null values filtered by repository array_intersect_key, fully backward-compatible
- [Phase 49-01]: UNIQUE constraint on (class_id, session_date) enforced at DB level — AttendanceRepository trusts constraint and lets DB throw on duplicates, no application-level check needed
- [Phase 49-02]: perDayTimes->perDay format mapping + mode='per-day' in generateSessionList before calling ScheduleService — handles both old start_time and new startTime DB key formats
- [Phase 49-02]: captureAttendance uses error-tolerant foreach loop — per-learner logHours failures don't abort the whole capture
- [Phase 49-02]: deleteHoursLogBySessionId lives in LearnerProgressionRepository (not raw SQL in service) — all hours-log DB access stays in repository layer
- [Phase 50-01]: Shared verify_attendance_nonce() helper instead of inline check_ajax_referer in each handler — DRY pattern matching ProgressionAjaxHandlers approach
- [Phase 50-01]: Range validation in AJAX handler layer (not service) — handler owns HTTP input normalization; service owns business rules
- [Phase 50-01]: No wp_ajax_nopriv_ registrations — site requires login per project policy
- [Phase 51-01]: attendance-capture.js depends on wecoza-single-class-display-js so WeCozaSingleClass config is available before attendance JS runs
- [Phase 51-01]: learnerIds decoded from JSON string to PHP array before wp_localize_script — JS receives plain array
- [Phase 51-01]: Admin delete button rendered conditionally via current_user_can('manage_options') in PHP template
- [Phase 52]: wecoza_resolve_class_status() uses null-coalescing fallback for migration window compatibility
- [Phase 52]: ClassModel classStatus defaults to 'draft' matching DB column default
- [Phase 52-02]: normaliseOrderNumber() changed to public static — DRY reuse by ClassStatusAjaxHandler (Plan 05)
- [Phase 52-02]: Separate :order_nr_check PDO param for CASE expression — PDO cannot bind same name twice in one statement
- [Phase 52-02]: wecoza_resolve_class_status() used in ClassTaskPresenter badge formatter for migration-window NULL safety
- [Phase 52-03]: require_active_class() calls wp_send_json_error + exit directly — consistent with verify_attendance_nonce() pattern in same file
- [Phase 52-03]: Attendance view/delete endpoints unguarded — capture/exception only; history remains accessible regardless of class status
- [Phase 52]: Active count uses wecoza_resolve_class_status() === 'active' not legacy isClassCurrentlyStopped() schedule-pause check

### Roadmap Evolution

- Phase 52 added: Class Activation Logic (WEC-179/180)

### Pending Todos

None.

### Blockers/Concerns

| Source | Issue | Impact |
|--------|-------|--------|
| v4.0 tech debt | Address dual-write period active, old agent address columns remain | Must eventually remove old columns |

## Session Continuity

Last session: 2026-02-24
Stopped at: Completed 52-04-PLAN.md (all tasks)
Resume file: —

**Next action:** Phase 52 Plan 04 complete. Proceed to Phase 52 Plan 05 (status management UI — activate/stop/reactivate actions, modal + AJAX handler).
