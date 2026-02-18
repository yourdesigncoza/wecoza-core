# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-18)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure
**Current focus:** v5.0 Learner Progression — Phase 44 in progress (plan 01 complete)

## Current Position

Phase: 44 of 47 in v5.0 (AJAX Wiring + Class Integration)
Plan: 1 of 3 in current phase
Status: In progress
Last activity: 2026-02-18 — 44-01 complete (progression AJAX handlers registered)

Progress: 43 phases complete across 9 milestones (91 plans executed)

## Milestone History

| Version | Name | Shipped | Phases | Plans |
|---------|------|---------|--------|-------|
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

- 42-01: LookupTableRepository does not extend BaseRepository — BaseRepository uses static $table, runtime config injection requires standalone class
- 42-01: TABLES constant lives in LookupTableController; AjaxHandler calls getTableConfig() — single source of truth
- 42-02: btn-subtle-* over btn-phoenix-* for in-table action buttons; wrapped in btn-group — matches app-wide pattern
- v5.0: AJAX handlers exist in docs/learner-progression/progression-ajax-handlers.php — need namespace fix (WeCoza\Services -> WeCoza\Learners\Services) and registration in wecoza-core.php
- 44-01: Portfolio file required (not optional) to mark LP complete — enforces data integrity
- 44-01: validate_portfolio_file() as shared namespace function (DRY) used by both mark-complete and portfolio-upload handlers
- 44-01: Collision acknowledgement uses wecoza_class_nonce (matches frontend class form context)
- [Phase 44]: Sorting by last_completion_date on th header — YYYY-MM-DD format sorts correctly via localeCompare
- [Phase 44]: logCollisionAcknowledgement() uses sendBeacon — fire-and-forget audit trail, never blocks UI
- [Phase 44]: 44-02: In-place card updates replace page reload — badge, progress bar, admin actions update without navigation
- [Phase 44]: 44-02: Confirmation modal required before mark-complete to prevent accidental LP completions

### Pending Todos

None.

### Blockers/Concerns

| Source | Issue | Impact |
|--------|-------|--------|
| v4.0 tech debt | Address dual-write period active, old columns remain | Must eventually remove old columns |

### Quick Tasks Completed

See: .planning/STATE.md historical section (collapsed in previous session)

## Performance Metrics

| Phase | Plan | Duration | Tasks | Files |
|-------|------|----------|-------|-------|
| 42 | 01 | 2 min | 2/2 | 4 |
| 42 | 02 | 15 min | 2/2 | 2 |
| 43 | 01 | 10 min | 2/2 | 0 |
| 44 | 01 | 2 min | 2/2 | 2 |
| Phase 44 P03 | 3 | 3 tasks | 4 files |
| Phase 44 P02 | 3 | 2 tasks | 2 files |

## Session Continuity

Last session: 2026-02-18
Stopped at: Completed 44-01-PLAN.md (progression AJAX handlers)
Resume file: .planning/phases/44-ajax-wiring-class-integration/44-01-SUMMARY.md

**Next action:** Execute 44-02-PLAN.md
