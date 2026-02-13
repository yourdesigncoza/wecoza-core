# WeCoza Core

## What This Is

WordPress plugin providing unified infrastructure for WeCoza: learner management, class management, client & location management, LP progression tracking, event/task management, and notification system. Consolidates previously separate plugins into a single maintainable codebase with PostgreSQL backend and MVC architecture.

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
- ✓ Material Tracking Dashboard shows classes with Deliveries events from event_dates JSONB — v1.3
- ✓ Bridge event tasks system and material tracking dashboard — v1.3
- ✓ Clients module integration (ARCH-01..08) — architecture migration to wecoza-core — v2.0
- ✓ Client management (CLT-01..09) — full CRUD, hierarchy, search, filter, CSV export — v2.0
- ✓ Location management (LOC-01..07) — Google Maps, geocoordinates, duplicate detection — v2.0
- ✓ Sites hierarchy (SITE-01..04) — head sites, sub-sites, location hydration — v2.0
- ✓ Client shortcodes (SC-01..05) — 5 shortcodes for clients, locations, sites — v2.0
- ✓ Standalone plugin cleanup (CLN-01..02) — plugin deactivated, .integrate/ removed — v2.0
- ✓ Agents module integration (13 classes, 3 shortcodes, 6 templates, 5 JS files) — v3.0
- ✓ Form field wiring fixes across 5 modules (34 issues: 6 critical, 28 warnings) — v3.1
- ✓ XSS vulnerability fix in Learners showAlert() — v3.1
- ✓ Classes data integrity (order_nr reverse path, class_agent init, DB-backed agent lists) — v3.1
- ✓ Server-side validation for 14 agent required fields + AgentDisplayService DRY refactor — v3.1
- ✓ Clients security (removed 7 unused AJAX endpoints, unified nonces, eliminated double submission) — v3.1
- ✓ Events late escaping (wp_kses_post on presenter HTML) + tracking table sync — v3.1

### Active

(No active requirements — use `/gsd:new-milestone` to define next milestone)

### Out of Scope

- Packages feature (learners on different subjects) — deferred per WEC-168 discussion
- New reporting features — separate milestone
- Mobile app — not planned
- OAuth/social login — not required
- Client billing/invoicing — separate domain
- CRM-style pipeline — overkill for current needs
- Client portal (self-service) — would require auth system changes

## Context

### Current State (v3.1 Shipped)

**Codebase:** `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/`
- **Total:** ~46,000+ lines of PHP across 5 modules
- **Agents module:** 14+ PHP files in `src/Agents/` (includes AgentDisplayService)
- **Events module:** 40+ PHP files in `src/Events/`
- **Clients module:** 15+ PHP files in `src/Clients/`
- **Learners module:** `src/Learners/`
- **Classes module:** `src/Classes/`
- **View templates:** 22+ templates in `views/`
- **JavaScript:** 21+ JS files across `assets/js/`
- **Test coverage:** 4 test files in `tests/Events/`, 1 integration test in `tests/integration/`
- **Form field audits:** `docs/formfieldanalysis/*.md` (5 modules audited)

**Architecture:**
- `core/` — Framework abstractions (Base classes, security helpers)
- `src/Learners/` — Learner module
- `src/Classes/` — Classes module
- `src/Events/` — Events module with notification system
- `src/Clients/` — Clients module (client, location, site management)
- `views/` — PHP templates
- `assets/` — JS/CSS files
- `vendor/` — Action Scheduler 3.9.3 (Composer-managed)

**Tech stack:** PostgreSQL, PHP 8.1+, WordPress 6.0+, OpenAI API (configurable), Action Scheduler, Google Maps Places API

**Shortcodes:**
- Events: `[wecoza_event_tasks]`, `[wecoza_material_tracking]`, `[wecoza_notification_dashboard]`
- Clients: `[wecoza_capture_clients]`, `[wecoza_display_clients]`, `[wecoza_locations_capture]`, `[wecoza_locations_list]`, `[wecoza_locations_edit]`, `[wecoza_client_update]`

**Async jobs (Action Scheduler):**
- `wecoza_process_notifications` — Batch notification processing
- `wecoza_process_event` — AI enrichment per event
- `wecoza_send_notification_email` — Email delivery per recipient

**Cron jobs:**
- `wecoza_material_notifications_check` — Daily material alerts

### Known Issues

- AJAX handler (wecoza_mark_material_delivered) needs event_index parameter support (v1.3 tech debt)
- Controllers pass deprecated notification_type/days_range params to service (v1.3 tech debt)
- Settings page may need admin menu entry for easier discovery
- 2 test failures in AI summarization test suite (test format issues, not production bugs)
- agent_meta table doesn't exist (v3.0 FEAT-02 — metadata features not available yet)

## Constraints

- **Tech stack:** PostgreSQL (not MySQL), PHP 8.0+, WordPress 6.0+
- **Architecture:** Must follow existing MVC patterns in wecoza-core
- **Dependencies:** OpenAI API key required for AI summaries, Google Maps API key for location autocomplete
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
| Event_dates as primary dashboard source | Cron records alone caused "0 records" bug | ✓ v1.3 |
| Event-based status filtering | Events represent user intent, cron is supplementary | ✓ v1.3 |
| Remove days_range filter | Events exist permanently in JSONB, not time-windowed | ✓ v1.3 |
| Full client plugin integration | Unified codebase, consistent patterns, single dependency | ✓ v2.0 |
| Models return arrays not instances | Callers (controllers, views) all expect arrays | ✓ v2.0 |
| CRUD convenience methods on PostgresConnection | Models depend on these rather than raw PDO | ✓ v2.0 |
| Soft-delete via deleted_at | Preserves data while hiding from queries | ✓ v2.0 |
| Cache getTableColumns() per request | Eliminates ~16 redundant information_schema queries | ✓ v2.0 |
| Inline script fixes over extraction | Inline scripts localized to view, don't need global config | ✓ v2.0 |

| Form field wiring audit before fixes | Comprehensive audit identifies all issues before coding | ✓ v3.1 |
| Module-by-module fix approach | Independent scopes, testable per module | ✓ v3.1 (34/34 requirements) |
| wp_kses_post() for presenter HTML | Allows intended markup, strips dangerous tags | ✓ v3.1 |
| Tracking table sync (no transaction) | JSONB is source of truth, tracking is secondary | ✓ v3.1 |
| AgentDisplayService for shared methods | Eliminates ~200 lines of duplication | ✓ v3.1 |
| DB-backed agent/supervisor dropdowns | Live queries replace hardcoded fake names | ✓ v3.1 |

---
*Last updated: 2026-02-13 after v3.1 milestone completion*
