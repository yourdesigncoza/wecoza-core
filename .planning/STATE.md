# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-18)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure
**Current focus:** v5.0 Learner Progression — Phase 46 Plan 02 of 3 complete

## Current Position

Phase: 46 of 47 in v5.0 (Learner Progression Report)
Plan: 2 of 3 in current phase
Status: Phase 46 Plan 02 complete — shortcode and view shell ready for JS (Plan 03)
Last activity: 2026-02-19 — 46-02 complete (progression report shortcode and view template)

Progress: 45 phases complete, 100 plans executed

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
- [Phase 45]: 45-01: Bulk complete calls model directly (not service) to bypass portfolio requirement — intentional admin trade-off documented in code comment
- [Phase 45]: 45-01: toggle_progression_hold validates current status before action, throws descriptive exception on state mismatch
- [Phase 45]: wecoza_view() used instead of direct include — matches plugin's view rendering pattern
- [Phase 45]: 45-02: Filter dropdowns rendered empty, JS populates via AJAX for separation of concerns
- [Phase 45]: 45-02: Three modals included in static view shell, not dynamically injected — simpler JS wiring
- [Phase 45]: 45-03: Filter dropdowns populated from first load data — avoids new endpoint, limitation documented for large datasets
- [Phase 45]: 45-03: handleMarkSingleComplete reuses bulk_complete_progressions action with single-element array — DRY
- [Phase 45]: 45-03: handleToggleHold updates row in-place (badge + dropdown) without full table reload for instant UX
- [Phase 46]: 46-01: findForReport uses explicit PDO::PARAM_INT/STR binding — dynamic filter set requires per-param type binding
- [Phase 46]: 46-01: getReportSummaryStats uses PostgreSQL FILTER(WHERE ...) conditional aggregation — single-pass, avoids subqueries
- [Phase 46]: 46-01: employer JOIN via learners.employer_id (not classes) — report is learner-centric
- [Phase 46]: 46-02: Shortcode follows exact progression-admin-shortcode.php pattern for consistency
- [Phase 46]: 46-02: Employer filter dropdown rendered empty — JS populates from loaded data (matches Phase 45 pattern)
- [Phase 46]: 46-02: Status pills use button elements with data-status attribute for nav-pill UX pattern

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
| 44 | 02 | 3 min | 2/2 | 2 |
| 44 | 03 | 3 min | 3/3 | 4 |
| 45 | 01 | 2 min | 2/2 | 1 |
| 45 | 02 | 2 min | 2/2 | 3 |
| 45 | 03 | 2 min | 2/2 | 1 |
| 46 | 01 | 2 min | 2/2 | 2 |
| 46 | 02 | 2 min | 2/2 | 3 |

## Session Continuity

Last session: 2026-02-19
Stopped at: Completed 46-02-PLAN.md (progression report shortcode and view template)
Resume file: .planning/phases/46-learner-progression-report/46-02-SUMMARY.md

**Next action:** Execute Phase 46 Plan 03 (progression-report.js)
