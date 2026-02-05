# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-03)

**Core value:** Single source of truth for all WeCoza functionality — unified plugin architecture
**Current focus:** v1.2 Event Tasks Refactor

## Current Position

Phase: 18 (Notification System) — in progress
Plan: 07/08 complete
Status: Phase 18 in progress
Last activity: 2026-02-05 — Completed 18-07-PLAN.md (Admin Settings UI)

Progress: v1.2 ████████████████████████████░░ 93%
Progress: Phase 18 █████████████████████████░░░░░ 87% (7/8 plans)

## Milestone v1.2: Event Tasks Refactor

**Goal:** Replace trigger-based task system with manual event capture integration.

**Phases:**
| # | Phase | Status | Requirements |
|---|-------|--------|--------------|
| 13 | Database Cleanup | Complete | DB-01..03 |
| 14 | Task System Refactor | Complete | TASK-01..04, REPO-01..02 |
| 15 | Bidirectional Sync | Complete | SYNC-01..05, REPO-03 |
| 16 | Presentation Layer | In Progress | UI-01..03 |
| 17 | Code Cleanup | Pending | CLEAN-01..06 |
| 18 | Notification System | In Progress | NOTIFY-01..08 |

**Key deliverables:**
- Tasks derived from `classes.event_dates` JSONB (not triggers)
- Agent Order Number always present, writes to `order_nr`
- Bidirectional sync between dashboard and class form
- 6 deprecated files removed
- Notification events captured from controllers

## Milestone History

**v1.1 Quality & Performance (Shipped: 2026-02-02)**
- 5 phases, 13 plans, 21 requirements
- Bug fixes, security hardening, async processing
- 3 hours from definition to ship

**v1 Events Integration (Shipped: 2026-02-02)**
- 7 phases, 13 plans, 24 requirements
- Events module migration, task management, AI summaries
- 4 days from start to ship

See: .planning/MILESTONES.md for full details

## Accumulated Context

### Decisions

| Decision | Rationale | Status |
|----------|-----------|--------|
| Replace triggers with manual events | Simpler architecture, user controls events | Implemented (18-05) |
| Agent Order Number always present | Class activation confirmation | Implemented (14-01) |
| Bidirectional sync | Dashboard <-> form stay in sync | Implemented (15) |
| Preserve notes on reopen | User data should not be lost | Implemented (15-01) |
| Task IDs: agent-order, event-{index} | Predictable access pattern | Implemented (14-01) |
| Empty string order_nr = incomplete | Explicit completion semantics | Implemented (14-01) |
| Remove log_id from service return | Classes identified by class_id only | Implemented (14-02) |
| All classes manageable | No skip logic for missing logs | Implemented (14-02) |
| Tasks built at query time | Derived from event_dates, not persisted | Implemented (14-02) |
| TaskController uses class_id | log_id obsolete after trigger removal | Implemented (15-02) |
| jsonb_set() for atomic updates | Prevents race conditions | Implemented (15-01) |
| completion metadata passthrough | Form saves preserve dashboard changes | Implemented (15-01) |
| Remove log_id from presentation layer | Obsolete since Phase 13 | Implemented (16-01) |
| JSONB for event_data and ai_summary | Flexible schema for varied event payloads | Implemented (18-01) |
| Notification workflow states | pending -> enriching -> sending -> sent/failed | Implemented (18-01) |
| Partial indexes for event queries | Optimized for status queue and unread events | Implemented (18-01) |
| Always record events | Audit trail separate from notification delivery | Implemented (18-02) |
| Significant fields filter | Prevent notification spam from minor edits | Implemented (18-02) |
| Filter hook for dispatch | Site-specific customization via wecoza_event_dispatch_enabled | Implemented (18-02) |
| EventType to operation mapping | Legacy NotificationSettings uses INSERT/UPDATE/DELETE strings | Implemented (18-03) |
| Dispatch events in ClassAjaxController | Single point where class CRUD and learner roster changes occur | Implemented (18-05) |
| No changes to FormDataProcessor | Pure data transformation, no DB interaction | Implemented (18-05) |
| No changes to LearnerAjaxHandlers | Handles standalone learner CRUD, not class-learner associations | Implemented (18-05) |
| NotificationDashboardService replaces AISummaryDisplayService | Cleaner abstraction for dashboard use cases | Implemented (18-04) |
| Unread filtering via viewed_at IS NULL | Standard database null check for unread state | Implemented (18-04) |
| Click-to-mark-viewed interaction | Automatic view tracking on notification click | Implemented (18-04) |
| WordPress Settings API for recipient config | Standard WP pattern, automatic save handling | Implemented (18-07) |
| Multi-recipient per event type | Comma-separated emails, validated on save | Implemented (18-07) |

### Pending Todos

- Execute 18-08-PLAN.md (Email delivery integration)

### Blockers/Concerns

None identified.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 001 | Event notes not showing in Open Tasks view | 2026-02-03 | 02ab22e | [001-event-notes-not-showing-in-tasks](./quick/001-event-notes-not-showing-in-tasks/) |
| 002 | Add event dates to Open Tasks view | 2026-02-03 | 95b11a2 | [002-add-date-to-open-tasks-view](./quick/002-add-date-to-open-tasks-view/) |

## Session Continuity

Last session: 2026-02-05T09:37:00Z
Stopped at: Completed 18-07-PLAN.md
Resume file: None

**Next action:** Execute 18-08-PLAN.md (Email delivery integration)
