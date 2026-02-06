# Project Milestones: WeCoza Core

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

---
