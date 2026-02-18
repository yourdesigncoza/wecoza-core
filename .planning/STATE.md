# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-17)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin architecture
**Current focus:** Planning next milestone

## Current Position

Phase: 43 — Placement Levels Shortcode
Plan: 01 COMPLETE — auto-increment DDL applied, end-to-end CRUD verified
Status: Phase 43 COMPLETE — Milestone v4.1 Lookup Table Admin shipped
Last activity: 2026-02-17 - Executed 43-01: DDL fix + human verified all 7 CRUD steps

Progress: 43 phases complete across 8 milestones (89 plans executed), milestone v4.1 Lookup Table Admin shipped

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

See: .planning/MILESTONES.md for full details

## Accumulated Context

### Decisions

- 42-01: LookupTableRepository does not extend BaseRepository — BaseRepository uses static $table, runtime config injection requires standalone class
- 42-01: TABLES constant lives in LookupTableController; AjaxHandler calls getTableConfig() — single source of truth
- 42-01: SHORTCODE_MAP constant in controller for clean tag-to-tableKey dispatch
- 42-02: Used btn-subtle-* over btn-phoenix-* for in-table action buttons; wrapped in btn-group — matches app-wide pattern
- 42-02: PHP-to-JS config via embedded JSON script tag avoids per-shortcode wp_localize_script registration

### Roadmap Evolution

- Phase 42 added: Lookup Table CRUD Infrastructure + Qualifications Shortcode
- Phase 43 added: Placement Levels Shortcode
- Milestone v4.1 created: Lookup Table Admin (Phases 42-43)
- Phase 42, Plan 01 complete: backend infrastructure (Repository, AjaxHandler, Controller)
- Phase 42, Plan 02 complete: frontend view template + JS CRUD manager; human verified
- Phase 43, Plan 01 complete: DDL sequence fix on placement_level_id; all 7 CRUD steps verified; milestone v4.1 shipped

### Pending Todos

None.

### Blockers/Concerns

| Source | Issue | Impact |
|--------|-------|--------|
| v1.3 tech debt | AJAX handler needs event_index parameter support | Mark-as-delivered doesn't update event_dates JSONB yet |
| v1.3 tech debt | Controllers pass deprecated params to service | Harmless but messy |
| v3.0 FEAT-02 | agent_meta table doesn't exist | Metadata features not available yet |
| v4.0 tech debt | Address dual-write period active, old columns remain | Must eventually remove old columns (ADDR-06/07) |
| v4.0 tech debt | Classes module SVC-04 gap | ClassController still has thick methods |
| v4.0 tech debt | LocationsModel/SitesModel missing type hints | TYPE-02 gap |
| v4.0 tech debt | Events constants not fully extracted | CONST-04 gap |

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 001 | Event notes not showing in Open Tasks view | 2026-02-03 | 02ab22e | [001-event-notes-not-showing-in-tasks](./quick/001-event-notes-not-showing-in-tasks/) |
| 002 | Add event dates to Open Tasks view | 2026-02-03 | 95b11a2 | [002-add-date-to-open-tasks-view](./quick/002-add-date-to-open-tasks-view/) |
| 003 | Add Edit Class button to Actions column | 2026-02-05 | d6d5828 | [003-add-edit-class-button-to-actions-column-](./quick/003-add-edit-class-button-to-actions-column-/) |
| 004 | Fix task metadata and preserve notes on reopen | 2026-02-05 | cab8521 | [004-fix-task-metadata-and-preserve-notes-on-reopen](./quick/004-fix-task-metadata-and-preserve-notes-on-reopen/) |
| 005 | Filter completed events from form, show in statistics | 2026-02-05 | 7e8956b | [005-filter-completed-events-show-in-statistics](./quick/005-filter-completed-events-show-in-statistics/) |
| 006 | Add green badge for completed status in Event Dates | 2026-02-05 | 504653f | [006-add-green-badge-for-completed-status](./quick/006-add-green-badge-for-completed-status/) |
| 007 | Remove Events text, fix Event Dates table columns | 2026-02-05 | b0074ec | [007-remove-events-text-fix-table-columns](./quick/007-remove-events-text-fix-table-columns/) |
| 008 | Fix Event Dates heading border colspan | 2026-02-05 | 7c997f4 | [008-fix-event-dates-heading-border](./quick/008-fix-event-dates-heading-border/) |
| 009 | Rename AI Summary/AI Generation Details in email template | 2026-02-05 | ebb2a43 | [009-update-email-template-rename-ai-summary-](./quick/009-update-email-template-rename-ai-summary-/) |
| 010 | Convert Bootstrap badges to Phoenix style | 2026-02-05 | 9742c0b | [010-update-bootstrap-badges-to-phoenix-style](./quick/010-update-bootstrap-badges-to-phoenix-style/) |
| 011 | Improve post-save UX in client capture form | 2026-02-17 | d5cccbe | [11-after-saving-a-new-client-via-wecoza-cap](./quick/11-after-saving-a-new-client-via-wecoza-cap/) |
| 012 | Improve post-save UX in agent capture form | 2026-02-17 | cdb36ee | [12-after-saving-a-new-agent-via-wecoza-capt](./quick/12-after-saving-a-new-agent-via-wecoza-capt/) |
| 013 | Notification cards: agent name, acknowledge badge, delete | 2026-02-18 | fd37095 | [13-notification-card-agent-name-display-ack](./quick/13-notification-card-agent-name-display-ack/) |

## Performance Metrics

| Phase | Plan | Duration | Tasks | Files |
|-------|------|----------|-------|-------|
| 42 | 01 | 2 min | 2/2 | 4 |
| 42 | 02 | 15 min | 2/2 | 2 |
| 43 | 01 | 10 min | 2/2 | 0 |

## Session Continuity

Last session: 2026-02-18
Stopped at: Quick Task 13 — notification card enhancements (agent name, badge swap, delete) — awaiting human verify of all 3 features
Resume file: N/A

**Next action:** Verify notification cards at [wecoza_insert_update_ai_summary] shortcode page, then run schema/add-soft-delete-to-class-events.sql DDL
