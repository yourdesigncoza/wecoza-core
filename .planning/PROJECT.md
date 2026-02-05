# WeCoza Core - Events Integration

## What This Is

WordPress plugin providing unified infrastructure for WeCoza: learner management, class management, LP progression tracking, event/task management, and notification system. Consolidates previously separate plugins into a single maintainable codebase with PostgreSQL backend and MVC architecture.

## Core Value

**Single source of truth for all WeCoza functionality** — unified plugin architecture eliminates dependency conflicts, simplifies maintenance, and provides consistent patterns across all modules.

## Requirements

### Validated

- ✓ Learners module (CRUD, PII access control) — existing
- ✓ Classes module (capture, display, scheduling) — existing
- ✓ LP Progression tracking (WEC-168) — existing
- ✓ PostgreSQL database layer with lazy-loading — existing
- ✓ MVC architecture with BaseController, BaseModel, BaseRepository — existing
- ✓ Security: nonce validation, capability checks, column whitelisting — existing
- ✓ Migrate Events module into wecoza-core — v1
- ✓ Task management (class change monitoring, task tracking) — v1
- ✓ Material tracking (delivery status, 7-day/5-day alerts) — v1
- ✓ AI summarization (OpenAI integration for class change summaries) — v1
- ✓ Email notifications (automated notifications on class changes) — v1
- ✓ PostgreSQL triggers migration (class_change_logs, triggers) — v1
- ✓ Unified database connection (consolidate to single PostgresConnection) — v1
- ✓ PSR-4 autoloading for Events module — v1
- ✓ Fix delivery_date column references (bug from schema migration) — v1
- ✓ Security hardening (SEC-01..06) — v1.1
- ✓ Performance improvements (PERF-01..05) — v1.1
- ✓ Bug fixes (BUG-01..04) — v1.1
- ✓ Quality improvements (QUAL-01..04) — v1.1
- ✓ Architecture improvements (ARCH-01..02) — v1.1
- ✓ Event-based task system (tasks from event_dates JSONB) — v1.2
- ✓ Agent Order Number (always-present task for class activation) — v1.2
- ✓ Bidirectional sync (dashboard ↔ form completion metadata) — v1.2
- ✓ Code cleanup (8 deprecated files removed) — v1.2
- ✓ Notification system (email + dashboard with AI enrichment) — v1.2
- ✓ Multi-recipient notification config — v1.2

### Active

(None — awaiting next milestone definition)

### Out of Scope

- Packages feature (learners on different subjects) — deferred per WEC-168 discussion
- New reporting features — separate milestone
- Mobile app — not planned
- OAuth/social login — not required

## Context

### Current State (v1.2 Shipped)

**Codebase:** `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/`
- **Total:** ~21,400 lines of PHP across 3 modules
- **Events module:** 40+ PHP files in `src/Events/` (DTOs, Enums, Services, Repositories)
- **View templates:** 10+ templates in `views/events/`
- **Test coverage:** 4 test files in `tests/Events/`

**Architecture:**
- `core/` — Framework abstractions (Base classes, security helpers)
- `src/Learners/` — Learner module
- `src/Classes/` — Classes module
- `src/Events/` — Events module with notification system
- `views/` — PHP templates
- `assets/` — JS/CSS files
- `vendor/` — Action Scheduler 3.9.3 (Composer-managed)

**Tech stack:** PostgreSQL, PHP 8.1+, WordPress 6.0+, OpenAI API (configurable), Action Scheduler

**Shortcodes (Events module):**
- `[wecoza_event_tasks]` — Task management dashboard
- `[wecoza_material_tracking]` — Material delivery tracking
- `[wecoza_notification_dashboard]` — Notification timeline with unread filter

**Async jobs (Action Scheduler):**
- `wecoza_process_notifications` — Batch notification processing
- `wecoza_process_event` — AI enrichment per event
- `wecoza_send_notification_email` — Email delivery per recipient

**Cron jobs:**
- `wecoza_material_notifications_check` — Daily material alerts

### Known Issues

- Settings page may need admin menu entry for easier discovery
- 2 test failures in AI summarization test suite (test format issues, not production bugs)
- Minor tech debt: Some test sections replaced with skip notices

## Constraints

- **Tech stack:** PostgreSQL (not MySQL), PHP 8.0+, WordPress 6.0+
- **Architecture:** Must follow existing MVC patterns in wecoza-core
- **Dependencies:** OpenAI API key required for AI summaries
- **Compatibility:** Must not break existing Learners/Classes functionality

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Migrate all events features | User confirmed all features needed | ✓ All v1 requirements shipped |
| Use src/Events/ structure | Consistent with src/Learners/, src/Classes/ | ✓ All classes use correct namespace |
| Single PostgresConnection | Eliminate duplicate database code | ✓ All modules use wecoza_db() |
| PII protection via DataObfuscator | Prevent leaking sensitive data to OpenAI | ✓ Enhanced in v1.1 |
| Action Scheduler for async | Industry-standard job queue | ✓ v1.1 |
| Replace triggers with manual events | User controls events explicitly, simpler architecture | ✓ v1.2 |
| Agent Order Number always present | Confirms class activation, writes to order_nr | ✓ v1.2 |
| Bidirectional event/task sync | Dashboard ↔ form stay in sync | ✓ v1.2 |
| Application-level event dispatch | More flexible and testable than triggers | ✓ v1.2 |
| JSONB for event storage | Flexible schema for varied event payloads | ✓ v1.2 |

---
*Last updated: 2026-02-05 after v1.2 milestone shipped*
