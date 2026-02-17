# Project Milestones: WeCoza Core

## v4.0 Technical Debt (Shipped: 2026-02-16)

**Delivered:** Refactored WeCoza Core architecture with service layer extraction, model unification, address storage normalization, repository pattern enforcement, return type hints, and constants extraction across all 5 modules.

**Phases completed:** 36-41 (14 plans total, 21 tasks)

**Key accomplishments:**

- Created LearnerService, AgentService, ClientService — controllers reduced to thin validate-delegate-respond pattern (~83% handler reduction)
- ClientsModel and AgentModel now extend BaseModel with ArrayAccess backward compatibility and unified validation
- Migration scripts + dual-read/dual-write linking agents to shared locations table with graceful degradation
- Comprehensive SQL audit; 80%+ simple queries replaced with BaseRepository methods; quoteIdentifier enforced
- AppConstants class with SCREAMING_SNAKE_CASE; return type hints on all public methods across all modules
- Automated verification of all 28 requirements; 4 acceptable deviations documented as future tech debt

**Stats:**

- 80 files modified, 23,214 insertions, 1,774 deletions
- 77,552 lines of PHP total
- 6 phases, 14 plans, 28 requirements
- Same day from start to ship (2026-02-16, ~6 hours)

**Git range:** `8d7cc4b` → `9d50625`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)

---

## v3.1 Form Field Wiring Fixes (Shipped: 2026-02-13)

**Delivered:** Fixed all critical data loss bugs, XSS vulnerabilities, security gaps, and code duplication across 5 modules identified by comprehensive form field wiring audits.

**Phases completed:** 31-35 (8 plans total)

**Key accomplishments:**

- Fixed XSS vulnerability in Learners showAlert() using DOM construction; verified all 10 LRNR requirements
- Fixed Classes data integrity bugs (order_nr, class_agent), replaced hardcoded agent/supervisor arrays with DB queries + transient caching
- Added server-side validation for 14 required agent fields; extracted 4 display methods into AgentDisplayService (~200 lines dedup)
- Eliminated double AJAX submission in Clients (removed 208-line inline script), removed 7 unused endpoints, unified nonces
- Added wp_kses_post() late escaping on all presenter HTML, synced tracking table in markDelivered()

**Stats:**

- 55 files modified, 7,069 insertions, 1,225 deletions
- 46,467 lines of PHP total
- 5 phases, 8 plans, 34 requirements
- Same day from start to ship (2026-02-13)

**Git range:** `c8258df` → `c2faacc`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)

---

## v3.0 Agents Integration (Shipped: 2026-02-12)

**Delivered:** Migrated standalone wecoza-agents-plugin into wecoza-core as unified Agents module with agent CRUD, file uploads, working areas, statistics, notes, and absences management.

**Phases completed:** 26-30 (11 plans total)

**Key accomplishments:**

- Migrated 13 PHP classes into wecoza-core with WeCoza\Agents\ namespace and PSR-4 autoloading
- Full agent CRUD with 3 shortcodes (capture, display, single), file uploads, working areas
- AgentModel standalone (not extending BaseModel) preserving FormHelpers integration
- 44+ verification checks, feature parity test script, standalone plugin cleanly deactivated
- Agent statistics, notes, and absences management with AJAX handlers

**Stats:**

- 62 files modified, 19,508 insertions, 941 deletions
- 5 phases, 11 plans
- Same day as v2.0 ship (2026-02-12)

**Git range:** `ce5c548` → `2c687f7`

**What's next:** v3.1 Form Field Wiring Fixes

---

## v2.0 Clients Integration (Shipped: 2026-02-12)

**Delivered:** Integrated standalone wecoza-clients-plugin into wecoza-core as unified Clients module with client CRUD, location management, sites hierarchy, Google Maps integration, and CSV export.

**Phases completed:** 21-25 (10 plans total)

**Key accomplishments:**

- Migrated standalone clients plugin (4,581 LOC) into wecoza-core with WeCoza\Clients\ namespace and PSR-4 autoloading
- Full client CRUD with main/sub-client hierarchy, search/filter, soft-delete, CSV export, statistics
- Location management with Google Maps Places autocomplete, geocoordinates, duplicate detection
- Sites hierarchy with auto-created head sites, sub-sites, parent-child relationships, location hydration
- 44-check automated feature parity test, 100% pass rate, standalone plugin cleanly deactivated
- Repository cleanup — .integrate/ staging removed, all standalone artifacts eliminated

**Stats:**

- 59 files modified, 15,583 lines added
- 4,581 lines of PHP in Clients module
- 5 phases, 10 plans, 35 requirements
- 2 days from start to ship (2026-02-11 → 2026-02-12)

**Git range:** `474f674` → `d718045`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)

---

## v1.3 Fix Material Tracking Dashboard (Shipped: 2026-02-06)

**Delivered:** Rewired Material Tracking Dashboard to show classes with Deliveries events from event_dates JSONB instead of only cron-created records.

**Phases completed:** 19 (2 plans total)

**Key accomplishments:**

- Repository queries event_dates JSONB as primary data source (fixes "0 records" bug)
- Event-based status badges (Pending/Completed) replace cron-based Notified/Delivered
- Delivery Date column added to dashboard table
- Simplified filters: Status + Search replaces multi-dimensional filter set
- Cron notification data preserved as supplementary badges (7d/5d)

**Stats:**

- 7 files modified (PHP source + view templates)
- 714 lines added, 226 removed
- 1 phase, 2 plans, 4 tasks
- Same day from definition to ship (2026-02-06)

**Git range:** `refactor(19-01)` → `docs(19)`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)

---

## v1.2 Event Tasks Refactor (Shipped: 2026-02-05)

**Delivered:** Replaced trigger-based task system with manual event capture — tasks derived from user-entered events in class form, bidirectional sync between dashboard and form, and full notification system with AI enrichment.

**Phases completed:** 13-18 (16 plans total)

**Key accomplishments:**

- Event-based task system: Tasks derived from `classes.event_dates` JSONB instead of triggers
- Agent Order Number: Always-present task for class activation confirmation
- Bidirectional sync: Dashboard ↔ form completion metadata stays in sync
- Notification system: Email + dashboard notifications with AI enrichment via Action Scheduler
- Multi-recipient config: WordPress Settings API for notification recipients per event type
- Code cleanup: 8 deprecated files removed (803 lines dead code)

**Stats:**

- 71 commits
- 21,444 lines of PHP total
- 6 phases, 16 plans, 32 requirements
- 3 days from definition to ship (2026-02-03 → 2026-02-05)

**Git range:** `docs(13)` → `docs(17): complete code-cleanup phase`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)

---

## v1.1 Quality & Performance (Shipped: 2026-02-02)

**Delivered:** Production-ready polish addressing 21 issues from code analysis — bug fixes, security hardening, data privacy improvements, architecture refactoring, and async processing for high-volume notifications.

**Phases completed:** 8-12 (13 plans total)

**Key accomplishments:**

- Fixed critical bugs: column name mismatch, unsafe PDO catch blocks, portfolio save overwrite
- Security hardening: exception sanitization, MIME validation, quoteIdentifier helper
- Data privacy: removed PII mapping exposure, strengthened email masking, heuristic PII detection
- Architecture: typed DTOs with PHP 8.1 readonly properties, Enums for status strings, SRP refactoring
- AI service quality: fixed invalid model name (gpt-4o-mini), configurable API endpoint for Azure
- Performance: Action Scheduler integration, 50x batch throughput, async AI enrichment and email sending

**Stats:**

- 20+ files modified
- 19,200 lines of PHP total
- 5 phases, 13 plans, 21 requirements
- 3 hours from start to ship (same day as milestone definition)

**Git range:** `feat(08-01)` → `docs(12)`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)

---

## v1 Events Integration (Shipped: 2026-02-02)

**Delivered:** Migrated wecoza-events-plugin into wecoza-core as unified Events module with task management, material tracking, AI summarization, and email notifications.

**Phases completed:** 1-7 (13 plans total)

**Key accomplishments:**

- Migrated Events plugin code (7,700 LOC) into wecoza-core with unified namespace (WeCoza\Events\*)
- Consolidated database to single PostgresConnection with working PostgreSQL triggers
- Task management dashboard for monitoring and completing class change tasks
- Material tracking with automated 7-day and 5-day delivery alerts
- AI summarization of class changes via OpenAI GPT integration with PII protection
- Email notifications on class INSERT/UPDATE events via WordPress cron

**Stats:**

- 50 files created (37 PHP + 9 templates + 4 tests)
- 6,288 lines of PHP
- 7 phases, 13 plans, 24 requirements
- 4 days from start to ship (2026-01-29 → 2026-02-02)

**Git range:** `e03534c` → `faad62c`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)


## v4.1 Lookup Table Admin (Shipped: 2026-02-17)

**Delivered:** Generic config-driven lookup table admin infrastructure with Phoenix inline-edit UI, plus two shortcodes for qualifications and placement levels management.

**Phases completed:** 42-43 (3 plans total, 6 tasks)

**Key accomplishments:**

- Built generic LookupTables module — config-driven Repository, single AJAX endpoint with sub_action dispatch, Controller with TABLES/SHORTCODE_MAP constants
- Created Phoenix inline-edit table UI — manage.view.php template + lookup-table-manager.js with add/edit/delete, column-agnostic via config
- Shipped `[wecoza_manage_qualifications]` shortcode for learner qualifications admin CRUD
- Shipped `[wecoza_manage_placement_levels]` shortcode via config reuse — zero new PHP/JS code, only DB sequence fix needed

**Stats:**

- 14 files changed, 1,945 insertions, 14 deletions
- 2 phases, 3 plans, 6 tasks
- Same day from start to ship (2026-02-17)

**Git range:** `6cd71da` → `5b53f76`

**What's next:** TBD (use `/gsd:new-milestone` to define next milestone)

---

