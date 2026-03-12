# S01: History Data Layer & Audit Log

**Goal:** Build the data layer that queries entity relationship history from existing tables and writes high-level audit log entries for class/learner changes.
**Demo:** PHP integration tests prove HistoryService returns correct timeline data for all 4 entities; AuditService writes and reads audit log entries; `agent_class_history` schema exists.

## Must-Haves

- `HistoryService` with `getLearnerHistory()`, `getAgentHistory()`, `getClientHistory()`, `getClassHistory()`
- `HistoryRepository` executing optimized queries against existing relational tables
- `AuditService` writing to `wecoza_events.audit_log` with `log()`, `getEntityLog()`, `purgeOlderThan()`
- `AuditRepository` for audit log CRUD
- Schema SQL for `agent_class_history` table (developer runs it)
- Hooks into existing EventDispatcher and learner AJAX handlers for audit logging

## Proof Level

- This slice proves: contract + integration
- Real runtime required: yes (PostgreSQL queries)
- Human/UAT required: no

## Verification

- `php tests/History/HistoryServiceTest.php` — verifies all 4 entity history methods return expected data shapes
- `php tests/History/AuditServiceTest.php` — verifies audit log write/read/purge
- Manual SQL verification that `agent_class_history` DDL is syntactically correct

## Observability / Diagnostics

- Runtime signals: `wecoza_log()` on audit log write failures
- Inspection surfaces: `wecoza_events.audit_log` table queryable directly; `AuditService::getEntityLog()` method
- Failure visibility: Exceptions from repository methods propagate with context; audit log failures logged but never block the parent operation
- Redaction constraints: No PII in audit log `message` field — entity type + ID only, no field values

## Integration Closure

- Upstream surfaces consumed: `wecoza_db()`, `EventDispatcher`, existing repository classes, `AjaxSecurity`
- New wiring introduced: WordPress hooks (`wecoza_class_changed`, `wecoza_learner_changed`) fired from AJAX handlers; AuditService listens to these hooks
- What remains: S02 (UI tabs), S03 (admin view + retention cron)

## Tasks

- [ ] **T01: HistoryRepository — entity relationship queries** `est:2h`
  - Why: Core data access layer. All history views depend on these queries. Must prove JSONB `learner_ids` querying works efficiently.
  - Files: `src/History/Repositories/HistoryRepository.php`
  - Do: Create `HistoryRepository` with methods for each entity's relationships. Use existing tables: `classes` (learner_ids JSONB, class_agent), `learner_lp_tracking`, `learner_progression_portfolios`, `learner_exam_results`, `learner_hours_log`, `class_events`, `class_status_history`, `qa_visits`. For learner→classes query, use `jsonb_array_elements_text(learner_ids)` or `learner_ids @> '["123"]'::jsonb`. Include pagination support (limit/offset). Follow existing BaseRepository pattern with column whitelisting.
  - Verify: Unit-level method signature tests; SQL query dry-run against live DB (SELECT only)
  - Done when: All 4 entity history query methods exist, follow existing patterns, and produce correct SQL

- [ ] **T02: HistoryService — business logic and data shaping** `est:1h`
  - Why: Service layer transforms raw repository data into structured timeline arrays for UI consumption. Handles cross-entity joins and data enrichment (e.g., resolving agent names, client names from IDs).
  - Files: `src/History/Services/HistoryService.php`
  - Do: Create `HistoryService` with `getLearnerHistory($learnerId)`, `getAgentHistory($agentId)`, `getClientHistory($clientId)`, `getClassHistory($classId)`. Each returns an associative array with sections (e.g., learner returns `['classes' => [...], 'clients' => [...], 'progressions' => [...], 'portfolios' => [...]]`). Use constructor injection with null-coalescing defaults (D005 pattern). Resolve names inline via JOIN in repository queries, not N+1.
  - Verify: `php tests/History/HistoryServiceTest.php`
  - Done when: All 4 methods return well-structured arrays with all sections per WEC-189 requirements

- [ ] **T03: AuditService & AuditRepository — audit log write/read/purge** `est:1.5h`
  - Why: Captures high-level change entries for classes and learners. Uses existing `wecoza_events.audit_log` table.
  - Files: `src/History/Services/AuditService.php`, `src/History/Repositories/AuditRepository.php`
  - Do: `AuditRepository` wraps INSERT/SELECT/DELETE on `wecoza_events.audit_log`. `AuditService::log($level, $action, $message, $context, $userId)` writes entries. `getEntityLog($entityType, $entityId, $limit, $offset)` reads entries filtered by entity. `purgeOlderThan($years)` deletes entries older than N years. Context JSONB stores `entity_type`, `entity_id`, `action_type`. Message is human-readable high-level summary (e.g., "Class 42 updated by John"). Never store field-level before/after values per Mario's "high level only" requirement.
  - Verify: `php tests/History/AuditServiceTest.php`
  - Done when: Audit entries can be written, read by entity, and purged by age

- [ ] **T04: Hook audit logging into existing change flows** `est:1.5h`
  - Why: Audit log must fire on real class and learner changes without duplicating EventDispatcher logic. Use WordPress action hooks for loose coupling.
  - Files: `src/History/Services/AuditService.php`, `src/Classes/Ajax/ClassStatusAjaxHandler.php`, `src/Learners/Ajax/ProgressionAjaxHandlers.php`, `wecoza-core.php`
  - Do: Fire `do_action('wecoza_entity_changed', $entityType, $entityId, $action, $userId)` from existing class/learner AJAX handlers (create, update, delete, status change). Register `AuditService` as a listener on `wecoza_entity_changed` hook in plugin bootstrap. Audit logging must be fire-and-forget — catch exceptions, log errors, never block the parent operation. Check `EventDispatcher::dispatchClassEvent()` — if it already fires hooks, piggyback on those instead of adding new `do_action()` calls.
  - Verify: Trigger a class update via AJAX; verify audit_log row exists in DB
  - Done when: Class and learner changes produce audit log entries via WordPress hooks

- [ ] **T05: Agent class history schema & backfill query** `est:1h`
  - Why: Current schema only stores current + initial agent. Need timestamped history for future agent placements. Mario wants "which classes did agent facilitate and how long."
  - Files: `schema/agent_class_history.sql`, `src/History/Repositories/HistoryRepository.php`
  - Do: Create DDL for `agent_class_history` table: `id SERIAL PK`, `agent_id INT NOT NULL`, `class_id INT NOT NULL`, `role VARCHAR(20)` (facilitator/coach/assessor/backup), `started_at DATE`, `ended_at DATE`, `created_at TIMESTAMP DEFAULT NOW()`. Add FK constraints to agents and classes. Write a backfill SELECT query (not INSERT — developer runs it) that derives initial data from `classes.class_agent`, `classes.initial_class_agent`, `classes.backup_agent_ids`. Update `HistoryRepository::getAgentClassHistory()` to query this table. Hook into class update flow to INSERT new rows when agent changes (via the `wecoza_entity_changed` hook).
  - Verify: DDL is syntactically valid; backfill query produces correct results; HistoryRepository method works
  - Done when: Schema SQL file exists; repository method queries the table; hook wiring captures future agent changes

## Files Likely Touched

- `src/History/Repositories/HistoryRepository.php` (new)
- `src/History/Services/HistoryService.php` (new)
- `src/History/Repositories/AuditRepository.php` (new)
- `src/History/Services/AuditService.php` (new)
- `schema/agent_class_history.sql` (new)
- `tests/History/HistoryServiceTest.php` (new)
- `tests/History/AuditServiceTest.php` (new)
- `wecoza-core.php` (hook registration)
- `src/Classes/Ajax/ClassStatusAjaxHandler.php` (fire audit hook)
- `src/Learners/Ajax/ProgressionAjaxHandlers.php` (fire audit hook)
- `composer.json` (PSR-4 namespace for `WeCoza\History\`)
