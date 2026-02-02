# WeCoza Core - Events Integration

## What This Is

WordPress plugin providing unified infrastructure for WeCoza: learner management, class management, LP progression tracking, and event/task management. Consolidates previously separate plugins (wecoza-classes-plugin, wecoza-learners-plugin, wecoza-events-plugin) into a single maintainable codebase with PostgreSQL backend and MVC architecture.

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

### Active

(None — use `/gsd:new-milestone` to define next milestone requirements)

### Out of Scope

- Packages feature (learners on different subjects) — deferred per WEC-168 discussion
- New reporting features — separate milestone
- Mobile app — not planned
- OAuth/social login — not required

## Context

### Current State (v1 Shipped)

**Codebase:** `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/`
- **Total:** ~15,000 lines of PHP across 3 modules
- **Events module:** 37 PHP files, 6,288 LOC in `src/Events/`
- **View templates:** 9 templates in `views/events/`
- **Test coverage:** 4 test files in `tests/Events/`

**Architecture:**
- `core/` — Framework abstractions (Base classes)
- `src/Learners/` — Learner module
- `src/Classes/` — Classes module
- `src/Events/` — Events module (new in v1)
- `views/` — PHP templates
- `assets/` — JS/CSS files

**Tech stack:** PostgreSQL, PHP 8.0+, WordPress 6.0+, OpenAI API

**Shortcodes (Events module):**
- `[wecoza_event_tasks]` — Task management dashboard
- `[wecoza_material_tracking]` — Material delivery tracking
- `[wecoza_insert_update_ai_summary]` — AI-generated summaries

**Cron jobs:**
- `wecoza_email_notifications_process` — Hourly email notifications
- `wecoza_material_notifications_check` — Daily material alerts

### Known Issues

- Phase 3 (Bootstrap Integration) lacks formal VERIFICATION.md (functionality verified via dependent phases)
- Settings page may need admin menu entry for easier discovery
- 2 test failures in AI summarization test suite (test format issues, not production bugs)

## Constraints

- **Tech stack:** PostgreSQL (not MySQL), PHP 8.0+, WordPress 6.0+
- **Architecture:** Must follow existing MVC patterns in wecoza-core
- **Dependencies:** OpenAI API key required for AI summaries
- **Compatibility:** Must not break existing Learners/Classes functionality
- **Database:** PostgreSQL triggers and functions must be migrated

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Migrate all events features | User confirmed all features needed | ✓ All 24 requirements shipped |
| Fix delivery_date during migration | Cleaner than pre-fixing in old plugin | ✓ Zero delivery_date references |
| Use src/Events/ structure | Consistent with src/Learners/, src/Classes/ | ✓ 37 files in proper structure |
| Namespace: WeCoza\Events\* | Consistent with WeCoza\Learners\*, WeCoza\Classes\* | ✓ All classes use correct namespace |
| Single PostgresConnection | Eliminate duplicate database code | ✓ All modules use wecoza_db() |
| Use gpt-5-mini model | Balance cost vs quality for summaries | ✓ Working with error handling |
| PII protection via DataObfuscator | Prevent leaking sensitive data to OpenAI | ✓ Trait applied to AISummaryService |
| Hourly email cron | Non-blocking notifications without real-time complexity | ✓ wp_schedule_event configured |

---
*Last updated: 2026-02-02 after v1 milestone completion*
