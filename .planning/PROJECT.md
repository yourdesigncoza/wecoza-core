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

### Active

- [ ] Migrate Events module into wecoza-core
- [ ] Task management (class change monitoring, task tracking)
- [ ] Material tracking (delivery status, 7-day/5-day alerts)
- [ ] AI summarization (OpenAI integration for class change summaries)
- [ ] Email notifications (automated notifications on class changes)
- [ ] PostgreSQL triggers migration (class_change_logs, triggers)
- [ ] Unified database connection (consolidate to single PostgresConnection)
- [ ] PSR-4 autoloading for Events module
- [ ] Fix delivery_date column references (bug from schema migration)

### Out of Scope

- Packages feature (learners on different subjects) — deferred per WEC-168 discussion
- New reporting features — separate milestone
- Mobile app — not planned
- OAuth/social login — not required

## Context

**Source codebase:** `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-events-plugin/`
- ~7,700 lines of code, 33 PHP files
- Good MVC architecture similar to wecoza-core
- Uses separate database connection (needs consolidation)
- Manual require_once (needs PSR-4 conversion)
- 3 shortcodes, 2 AJAX handlers
- OpenAI API integration for AI summaries

**Current architecture (wecoza-core):**
- `core/` — Framework abstractions (Base classes)
- `src/Learners/` — Learner module
- `src/Classes/` — Classes module
- `views/` — PHP templates
- `assets/` — JS/CSS files

**Target architecture after migration:**
- `src/Events/` — Events module (new)
- Same patterns: Controllers, Models, Repositories, Services, Shortcodes

**Known issues:**
- Events plugin references `c.delivery_date` column that was dropped
- Events plugin has own database connection class (duplicate of core)

## Constraints

- **Tech stack:** PostgreSQL (not MySQL), PHP 8.0+, WordPress 6.0+
- **Architecture:** Must follow existing MVC patterns in wecoza-core
- **Dependencies:** OpenAI API key required for AI summaries
- **Compatibility:** Must not break existing Learners/Classes functionality
- **Database:** PostgreSQL triggers and functions must be migrated

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Migrate all events features | User confirmed all features needed | — Pending |
| Fix delivery_date during migration | Cleaner than pre-fixing in old plugin | — Pending |
| Use src/Events/ structure | Consistent with src/Learners/, src/Classes/ | — Pending |
| Namespace: WeCoza\Events\* | Consistent with WeCoza\Learners\*, WeCoza\Classes\* | — Pending |

---
*Last updated: 2026-02-02 after initialization*
