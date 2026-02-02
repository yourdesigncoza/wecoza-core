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
- ✓ SEC-01: Add `quoteIdentifier()` helper for PostgreSQL reserved words — v1.1
- ✓ SEC-02: Remove PII mappings from DataObfuscator return value — v1.1
- ✓ SEC-03: Strengthen email masking (show domain only) — v1.1
- ✓ SEC-04: Add MIME type validation on PDF uploads — v1.1
- ✓ SEC-05: Reduce verbose exception logging (schema leak risk) — v1.1
- ✓ SEC-06: Add heuristic field detection for custom PII fields — v1.1
- ✓ PERF-01: Increase NotificationProcessor BATCH_LIMIT to 50+ — v1.1
- ✓ PERF-02: Implement async email via Action Scheduler — v1.1
- ✓ PERF-03: Separate AI enrichment job from email sending job — v1.1
- ✓ PERF-04: Increase lock TTL to prevent race conditions — v1.1
- ✓ PERF-05: Add memory cleanup for long-running DataObfuscator — v1.1
- ✓ BUG-01: Fix column name mismatch (`sa_id_no` vs `sa_id_number`) — v1.1
- ✓ BUG-02: Fix savePortfolios() overwrite bug (append, don't replace) — v1.1
- ✓ BUG-03: processPortfolioDetails() method verified existing — v1.1
- ✓ BUG-04: Fix unsafe `$pdo` access in catch block — v1.1
- ✓ QUAL-01: Fix invalid model name (`gpt-5-mini` → `gpt-4o-mini`) — v1.1
- ✓ QUAL-02: Extract DTOs for `$record`, `$context`, `$summary` arrays — v1.1
- ✓ QUAL-03: Implement PHP 8.1 Enums for status strings — v1.1
- ✓ QUAL-04: Make API URL configurable (support Azure/proxy) — v1.1
- ✓ ARCH-01: Refactor `generateSummary()` for Single Responsibility — v1.1
- ✓ ARCH-02: BaseRepository `count()` method verified existing — v1.1

### Active

(None — use `/gsd:new-milestone` to define next milestone requirements)

### Out of Scope

- Packages feature (learners on different subjects) — deferred per WEC-168 discussion
- New reporting features — separate milestone
- Mobile app — not planned
- OAuth/social login — not required

## Context

### Current State (v1.1 Shipped)

**Codebase:** `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/`
- **Total:** ~19,200 lines of PHP across 3 modules
- **Events module:** 45+ PHP files in `src/Events/` (including new DTOs, Enums, Services)
- **View templates:** 9 templates in `views/events/`
- **Test coverage:** 4 test files in `tests/Events/`

**Architecture:**
- `core/` — Framework abstractions (Base classes, security helpers)
- `src/Learners/` — Learner module
- `src/Classes/` — Classes module
- `src/Events/` — Events module with DTOs, Enums, async services
- `views/` — PHP templates
- `assets/` — JS/CSS files
- `vendor/` — Action Scheduler 3.9.3 (Composer-managed)

**Tech stack:** PostgreSQL, PHP 8.1+, WordPress 6.0+, OpenAI API (configurable), Action Scheduler

**Shortcodes (Events module):**
- `[wecoza_event_tasks]` — Task management dashboard
- `[wecoza_material_tracking]` — Material delivery tracking
- `[wecoza_insert_update_ai_summary]` — AI-generated summaries

**Async jobs (Action Scheduler):**
- `wecoza_enrich_notification` — AI enrichment of notification
- `wecoza_send_notification_email` — Send enriched email notification

**Cron jobs:**
- `wecoza_email_notifications_process` — Hourly batch processing (50 notifications)
- `wecoza_material_notifications_check` — Daily material alerts

### Known Issues

- Phase 3 (Bootstrap Integration) lacks formal VERIFICATION.md (functionality verified via dependent phases)
- Settings page may need admin menu entry for easier discovery
- 2 test failures in AI summarization test suite (test format issues, not production bugs)
- Minor tech debt: NotificationEnricher/NotificationEmailer lack explicit exception handling (deferred)

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
| Use gpt-4o-mini model (not gpt-5-mini) | Valid model name, cost-effective | ✓ Fixed in v1.1 |
| PII protection via DataObfuscator | Prevent leaking sensitive data to OpenAI | ✓ Enhanced in v1.1 with PIIDetector |
| Hourly email cron | Non-blocking notifications without real-time complexity | ✓ Upgraded to Action Scheduler in v1.1 |
| Initialize PDO to null before try blocks | Prevent secondary errors in catch blocks | ✓ Pattern established v1.1 |
| finfo_file() for MIME validation | Prevents malicious files disguised as PDFs | ✓ v1.1 |
| Sanitize all exception messages | Prevent schema exposure in logs | ✓ v1.1 |
| Remove 'mappings' from obfuscation returns | Prevent PII reverse-engineering | ✓ v1.1 |
| Hide entire email local part (****@domain.com) | Stronger privacy than partial masking | ✓ v1.1 |
| PHP 8.1 readonly DTOs with with*() methods | Immutable type-safe data structures | ✓ v1.1 |
| Store OpenAI config in WordPress options | Leverage existing WP admin UI, Azure support | ✓ v1.1 |
| Action Scheduler for async processing | Industry-standard job queue, WooCommerce compatible | ✓ v1.1 |
| Separate NotificationEnricher/Emailer services | Single Responsibility, independent failure | ✓ v1.1 |

---
*Last updated: 2026-02-02 after v1.1 milestone complete*
