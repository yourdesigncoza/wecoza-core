# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-23)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure
**Current focus:** v6.0 Agent Attendance Capture — Phase 48: Foundation

## Current Position

Phase: 47 of 51 (Regulatory Export)
Plan: 1 of 2 in current phase — Plan 01 complete
Status: In progress
Last activity: 2026-02-23 — Phase 47 Plan 01 complete: regulatory export backend (repository methods + AJAX handlers)

Progress: [██████████████████░░] 46/50 phases complete

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
- v6.0: Progress calculation uses hours_trained (not hours_present) per Mario's clarification — PROG-01/02/03 fix is Phase 48
- v6.0: Any logged-in user can capture attendance (no agent-only restriction)
- v6.0: Captured sessions are locked (view-only); admin can delete + re-capture for audit integrity
- v6.0: Backdating allowed for any past scheduled date up to today
- [Phase 47-01]: Admin-only access (manage_options) enforced on both regulatory endpoints — PII data requires highest privilege
- [Phase 47-01]: Separate first_name/surname columns returned (not CONCAT) to support regulatory form field mapping
- [Phase 47-01]: UTF-8 BOM prepended to CSV output for Excel UTF-8 compatibility

### Pending Todos

None.

### Blockers/Concerns

| Source | Issue | Impact |
|--------|-------|--------|
| v4.0 tech debt | Address dual-write period active, old agent address columns remain | Must eventually remove old columns |

## Session Continuity

Last session: 2026-02-23
Stopped at: Completed 47-regulatory-export-01-PLAN.md
Resume file: —

**Next action:** Execute Phase 47 Plan 02 (frontend regulatory export UI)
