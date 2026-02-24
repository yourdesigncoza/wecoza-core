# WeCoza Core

## What This Is

WordPress plugin providing unified infrastructure for WeCoza: learner management, class management, client & location management, LP progression tracking, event/task management, and notification system. Consolidates previously separate plugins into a single maintainable codebase with PostgreSQL backend, MVC architecture, service layer pattern, unified model hierarchy, and full return type coverage.

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
- ✓ Service layer extraction (LearnerService, AgentService, ClientService) — v4.0
- ✓ Model architecture unification (ClientsModel, AgentModel extend BaseModel) — v4.0
- ✓ Address storage normalization (agents linked to shared locations table, dual-write) — v4.0
- ✓ Repository pattern enforcement (80%+ queries via BaseRepository, quoteIdentifier) — v4.0
- ✓ Return type hints on all public methods across all modules — v4.0
- ✓ Constants extraction (AppConstants with SCREAMING_SNAKE_CASE) — v4.0
- ✓ Generic lookup table CRUD infrastructure (config-driven Repository, AjaxHandler, Controller) — v4.1
- ✓ Qualifications admin shortcode `[wecoza_manage_qualifications]` with inline-edit Phoenix table — v4.1
- ✓ Placement levels admin shortcode `[wecoza_manage_placement_levels]` via config reuse — v4.1
- ✓ AJAX wiring for progression (mark-complete, portfolio upload, data fetch, collision log) — v5.0
- ✓ Admin progression management panel with filters, bulk ops, hold/resume — v5.0
- ✓ Learner progression report with timeline, employer filter, summary cards (WEC-165) — v5.0
- ✓ Progress calculation fix (hours_trained instead of hours_present across PHP/SQL/JS) — v6.0
- ✓ Attendance capture UI with per-learner hours, session list, month filter, capture/view/exception modals (WEC-178) — v6.0
- ✓ AttendanceService/Repository with session CRUD, exception marking, admin delete with hours reversal — v6.0
- ✓ 5 attendance AJAX endpoints with nonce validation and input normalization — v6.0
- ✓ Three-way class status lifecycle (draft/active/stopped) with manager controls and auto-activate (WEC-179/180) — v6.0
- ✓ Attendance lock gate on non-active classes (view + server-side guard) — v6.0
- ✓ Class status history audit trail with FOR UPDATE row lock concurrency safety — v6.0

### Active

(No active milestone — use `/gsd:new-milestone` to start next milestone)

### Out of Scope

- Packages feature (learners on different subjects) — deferred per WEC-168 discussion
- Regulatory reporting for Umalusi/DHET (monthly progressions, CSV export) — deferred from v5.0 Phase 47
- New reporting features — separate milestone
- Mobile app — not planned
- OAuth/social login — not required
- Client billing/invoicing — separate domain
- CRM-style pipeline — overkill for current needs
- Client portal (self-service) — would require auth system changes
- Test suite creation — separate milestone (refactoring-only scope in v4.0)
- Frontend JavaScript refactoring — PHP-only scope in v4.0

## Context

### Current State (v6.0 Shipped)

**Codebase:** `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/`
- **Total:** ~83,500 lines of PHP across 5 modules + LookupTables
- **Agents module:** 16+ PHP files in `src/Agents/` (includes AgentService, AgentDisplayService)
- **Events module:** 40+ PHP files in `src/Events/`
- **Clients module:** 17+ PHP files in `src/Clients/` (includes ClientService)
- **Learners module:** `src/Learners/` (includes LearnerService, ProgressionService)
- **Classes module:** `src/Classes/` (includes AttendanceService, AttendanceRepository, ClassStatusAjaxHandler)
- **LookupTables module:** `src/LookupTables/` (3 files — config-driven CRUD for any lookup table)
- **Core:** `core/Abstract/AppConstants.php` — shared constants
- **View templates:** 25+ templates in `views/` (includes attendance.php, lookup-tables/manage.view.php)
- **Shortcodes:** `[wecoza_manage_qualifications]`, `[wecoza_manage_placement_levels]`
- **JavaScript:** 22+ JS files across `assets/js/` (includes attendance-capture.js)
- **Test coverage:** 4 test files in `tests/Events/`, 1 integration test, 1 architecture verification script
- **Form field audits:** `docs/formfieldanalysis/*.md` (5 modules audited)

**Architecture:**
- `core/` — Framework abstractions (BaseController, BaseModel, BaseRepository, AppConstants, AjaxSecurity)
- `src/Learners/` — Learner module with LearnerService
- `src/Classes/` — Classes module
- `src/Events/` — Events module with notification system
- `src/Clients/` — Clients module with ClientService
- `src/Agents/` — Agents module with AgentService (models extend BaseModel)
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
- Address migration dual-write period active — old agent address columns still present (ADDR-06/07 deferred)
- Classes module SVC-04 gap — ClassController still has thick methods (future tech debt)
- LocationsModel/SitesModel missing return type hints (TYPE-02 gap — future tech debt)
- Events module constants not fully extracted (CONST-04 gap — future tech debt)

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
| Service layer pattern (validate-delegate-respond) | Thin controllers, testable business logic | ✓ v4.0 |
| ClientsModel/AgentModel extend BaseModel | Unified model hierarchy, shared validation | ✓ v4.0 |
| ArrayAccess for backward-compatible array syntax | Existing callers unchanged after model refactor | ✓ v4.0 |
| Dual-write for address migration | Zero-downtime migration, graceful degradation | ✓ v4.0 |
| Direct SQL for location sync (bypass LocationsModel) | Avoids longitude/latitude validation on existing data | ✓ v4.0 |
| BaseRepository method delegation (findBy/updateBy) | Consistent CRUD, reduces manual SQL | ✓ v4.0 |
| quoteIdentifier() for all dynamic column names | SQL injection prevention in ORDER BY clauses | ✓ v4.0 |
| AppConstants with SCREAMING_SNAKE_CASE | Shared constants, eliminates magic numbers | ✓ v4.0 |
| 4 acceptable deviations documented as tech debt | Classes/Locations/Events gaps deferred to future | ⚠️ v4.0 |
| Config-driven LookupTableRepository (not extending BaseRepository) | BaseRepository uses static $table; runtime config needs standalone class | ✓ v4.1 |
| TABLES + SHORTCODE_MAP constants in Controller | Single source of truth; AjaxHandler calls getTableConfig() | ✓ v4.1 |
| PHP-to-JS config via embedded JSON script tag | Avoids per-shortcode wp_localize_script registration | ✓ v4.1 |

| Agent attendance capture (WEC-178) | Mario: agents capture per-learner hours per session; exceptions don't count | ✓ v6.0 |
| Progress calc uses hours_trained | Mario: "total hours trained for tracking, not present hours" | ✓ v6.0 |
| Any logged-in user can capture | No agent-only restriction, simpler auth model | ✓ v6.0 |
| Captured sessions locked | View-only after submit; admin can delete + re-capture | ✓ v6.0 |
| Backdating allowed | Agent can capture for any past scheduled date up to today | ✓ v6.0 |
| Three-way class status (draft/active/stopped) | Manager controls class lifecycle; auto-activate on order_nr | ✓ v6.0 |
| Attendance lock on non-active classes | Prevents capturing hours on draft/stopped classes | ✓ v6.0 |
| Status transitions use FOR UPDATE row lock | Concurrency safety for concurrent status changes | ✓ v6.0 |
| Phoenix Feather SVG for status badges | Consistent with Phoenix theme; DRY helper across 5 views | ✓ v6.0 |

---
*Last updated: 2026-02-24 after v6.0 milestone*
