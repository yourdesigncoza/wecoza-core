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

**Current Milestone: v1.1 Quality & Performance**

Goal: Address 21 issues from code analysis + architectural improvements for production readiness.

**Security (6):**
- [ ] SEC-01: Add `quoteIdentifier()` helper for PostgreSQL reserved words
- [ ] SEC-02: Remove PII mappings from DataObfuscator return value
- [ ] SEC-03: Strengthen email masking (show domain only)
- [ ] SEC-04: Add MIME type validation on PDF uploads
- [ ] SEC-05: Reduce verbose exception logging (schema leak risk)
- [ ] SEC-06: Add heuristic field detection for custom PII fields

**Performance (5):**
- [ ] PERF-01: Increase NotificationProcessor BATCH_LIMIT to 50+
- [ ] PERF-02: Implement async email via Action Scheduler
- [ ] PERF-03: Separate AI enrichment job from email sending job
- [ ] PERF-04: Increase lock TTL to prevent race conditions
- [ ] PERF-05: Add memory cleanup for long-running DataObfuscator

**Bugs (4):**
- [ ] BUG-01: Fix column name mismatch (`sa_id_no` vs `sa_id_number`)
- [ ] BUG-02: Fix savePortfolios() overwrite bug (append, don't replace)
- [ ] BUG-03: Implement missing `processPortfolioDetails()` method
- [ ] BUG-04: Fix unsafe `$pdo` access in catch block

**Quality (4):**
- [ ] QUAL-01: Fix invalid model name (`gpt-5-mini` → `gpt-4o-mini`)
- [ ] QUAL-02: Extract DTOs for `$record`, `$context`, `$summary` arrays
- [ ] QUAL-03: Implement PHP 8.1 Enums for status strings
- [ ] QUAL-04: Make API URL configurable (support Azure/proxy)

**Architecture (2):**
- [ ] ARCH-01: Refactor `generateSummary()` for Single Responsibility
- [ ] ARCH-02: Add BaseRepository `count()` method for pagination

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
*Last updated: 2026-02-02 after v1.1 milestone started*
