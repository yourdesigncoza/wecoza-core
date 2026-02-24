# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-23)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure
**Current focus:** v6.0 shipped — all phases complete

## Current Position

Phase: 52 of 52 (Class Activation Logic)
Plan: 6 of 6 — Complete
Status: Phase 52 complete, v6.0 milestone shipped
Last activity: 2026-02-24 — Phase 52 approved after UAT (3 rounds of fixes: Gemini code review, badge alignment, attendance month filter)

Progress: [████████████████████] 52/52 phases complete

## Milestone History

| Version | Name | Shipped | Phases | Plans |
|---------|------|---------|--------|-------|
| v6.0 | Agent Attendance Capture | 2026-02-24 | 48-52 | 12 |
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
- [Phase 48-01]: Progress calculation uses hours_trained (not hours_present) per Mario's clarification
- v6.0: Any logged-in user can capture attendance (no agent-only restriction)
- v6.0: Captured sessions are locked (view-only); admin can delete + re-capture for audit integrity
- v6.0: Backdating allowed for any past scheduled date up to today
- [Phase 52]: wecoza_resolve_class_status() uses null-coalescing fallback for migration window compatibility
- [Phase 52]: Three-way class status (draft/active/stopped) with manager-only transitions
- [Phase 52]: Auto-activate on order_nr entry via TaskManager with audit trail to class_status_history
- [Phase 52]: Attendance lock gate on non-active classes (view + server-side AJAX guard)
- [Phase 52]: Status transitions use FOR UPDATE row lock inside transaction for concurrency safety
- [Phase 52]: Phoenix Feather SVG icons for status badges (not Bootstrap Icons)
- [Phase 52]: wecoza_class_status_badge_svg() DRY helper for badge SVG markup across 5 views

### Roadmap Evolution

- Phase 52 added: Class Activation Logic (WEC-179/180) — completed 2026-02-24

### Pending Todos

None.

### Blockers/Concerns

| Source | Issue | Impact |
|--------|-------|--------|
| v4.0 tech debt | Address dual-write period active, old agent address columns remain | Must eventually remove old columns |
| ScheduleService | declare(strict_types=1) from commit 3897e45 broke setDate() for monthly-pattern classes — fixed with (int) casts but production needs deployment | Deploy latest code to production |

## Session Continuity

Last session: 2026-02-24
Stopped at: Phase 52 complete. v6.0 milestone shipped.
Resume file: —

**Next action:** Deploy latest code to production (includes strict_types int cast fix + attendance month filter fix). Consider starting next milestone (v7.0).
