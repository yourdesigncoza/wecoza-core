# M002: Entity History & Audit Trail

**Vision:** Surface entity relationship history across all four core entities (class, learner, agent, client) on their single-entity display pages, so staff can quickly answer "what happened with this entity?" without digging through multiple screens. Backed by a lightweight, high-level-only audit log (admin-accessible shortcode, not user-facing) for basic change tracking. Per Mario (WEC-189): entity history visibility is the priority; audit trail is nice-to-have and must stay simple — no granular field-change tracking, no "playing policeman."

## Success Criteria

- On the single class page, a user can see: agent assignments (current + historical), enrolled learners, learner progression summary, status change history, class notes, and event history with dates
- On the single agent page, a user can see: classes facilitated (with dates and duration), clients trained at, subjects/levels/modules facilitated, performance notes, and QA visit reports
- On the single learner page, a user can see: class enrollment history with LP status, client history (which clients they trained at), levels/subjects completed with progression dates (start/completion), and portfolio records per level
- On the single client page, a user can see: all classes run, agents who trained there, full learner list (not just counts), and class status summaries
- High-level audit log entries are written when class or learner data changes — action codes only (e.g. `CLASS_STATUS_CHANGED`, `LEARNER_ADDED`), entity type + ID, no PII, no field-value diffs
- Audit log is viewable via a `[wecoza_audit_log]` shortcode (to be gatekept to admin-only pages)
- Audit log entries can be purged after 3-year retention via scheduled cleanup
- `agent_class_history` table tracks agent-class assignment changes with timestamps

## Explicit Scope Boundaries

**In scope:**
- All data points listed in WEC-189 per entity (see success criteria above)
- Simple, read-only history sections added to existing single-entity pages
- Audit log shortcode for admin pages
- Automated 3-year purge via WP-Cron

**Out of scope (deferred):**
- Agent → learner history (Mario marked "optional/nice-to-have")
- Client → monthly report history (separate reporting feature, not entity relationships)
- Interactive/animated timeline UI — use clean tables and lists matching existing page style
- Granular field-change audit diffs — Mario explicitly warned against this
- User-facing audit log querying — admin shortcode only

**Legacy data handling:**
- Historical agent assignments before `agent_class_history` deployment will show as "current assignment" only, with no change dates. UI labels this clearly.

## Key Risks / Unknowns

- **Performance with large datasets** — Clients/agents with years of history could produce slow queries. Mitigated with pagination per sub-section and lazy-loading via AJAX.
- **Existing page layout integration** — History sections must fit existing single-entity pages without disrupting current functionality.

## Proof Strategy

- Performance → retire in S03 by showing paginated history loads without lag on real production data
- Layout integration → retire in S03/S04 by browser-verifying history sections on actual entity pages

## Verification Classes

- Contract verification: PHP integration tests for HistoryRepository, AuditService, HistoryService (data shape, empty results, live data)
- Integration verification: AJAX endpoints return correct history data; audit log writes fire on class/learner AJAX saves; shortcode renders
- Operational verification: WP-Cron audit purge fires and deletes old entries; pagination handles large datasets
- UAT / human verification: History sections display correctly on single entity pages with real data; audit shortcode works on admin page

## Milestone Definition of Done

This milestone is complete only when all are true:

- All 4 entity history sections are rendered on their single-entity display pages showing **all data points** from WEC-189 (enumerated in success criteria)
- HistoryService returns correct timeline data for all 4 entity types (proven by integration tests with live DB)
- AuditService writes action-code entries on class/learner saves, reads by entity, and purges by retention (proven by integration tests)
- `agent_class_history` DDL deployed and recording assignment changes on class save
- `[wecoza_audit_log]` shortcode renders audit entries with filtering by entity type
- WP-Cron scheduled event purges audit entries older than 3 years
- AJAX endpoints serve history data with proper nonce verification
- History sections are browser-verified on real entity pages with production data
- All test suites pass
- Legacy/pre-feature data displays gracefully (no broken UI for missing timestamps)

## Requirement Coverage

- No `REQUIREMENTS.md` found — operating in legacy compatibility mode.
- Covers: WEC-189 (Entity History & Audit Trail) per `docs/mario/WEC-189-history-audit.md`
- Explicitly deferred: Agent→learner history (optional per Mario), Client→monthly report history (separate feature)
- Orphan risks: None — all WEC-189 items mapped or explicitly deferred

## Slices

- [x] **S01: History Data Layer & Audit Service** `risk:medium` `depends:[]`
  > After this: PHP integration tests prove HistoryRepository returns correct timeline data for all 4 entities from existing DB tables; AuditService writes and reads audit log entries; `agent_class_history` DDL is ready for deployment. ✅ 105 checks passing.

- [x] **S02: History Service Facade, AJAX & Audit Wiring** `risk:medium` `depends:[S01]` ✅ 144 checks
  > After this: HistoryService facade merges repository data into per-entity timeline arrays covering all WEC-189 data points. AJAX endpoint returns entity history JSON. AuditService::log() is called from class and learner save handlers with action codes. WP-Cron purge event registered. Audit log shortcode renders a filterable table. All proven by integration tests against live DB.

- [x] **S03: Class & Agent History UI** `risk:medium` `depends:[S02]` ✅
  > After this: Single class page shows a history section with agent assignments, enrolled learners with progression, status changes, class notes, and event dates — loaded via AJAX with pagination. Single agent page shows classes facilitated (with dates/duration), clients trained at, subjects/levels taught, performance notes, and QA visit summaries. Browser-verified on real pages.

- [x] **S04: Learner & Client History UI** `risk:low` `depends:[S02]` ✅
  > After this: Single learner page shows class enrollment history with LP status, client training history, levels completed with start/completion dates, and portfolio records per level — loaded via AJAX. Single client page shows all classes, all agents, and full learner list. Browser-verified on real pages.

- [x] **S05: Integration Verification & Polish** `risk:low` `depends:[S03, S04]` ✅ browser-verified
  > After this: All 4 history sections browser-verified on real entity pages with production data. Performance confirmed (pagination, lazy-load). WP-Cron audit purge verified. Audit shortcode tested on admin page. Legacy data handled gracefully. Regression checks on existing functionality pass.

## Boundary Map

### S01 → S02

Produces:
- `HistoryRepository::getClassHistory(classId)` → `{agent_assignments[], learner_assignments[], status_changes[], stop_restart_dates[]}`
- `HistoryRepository::getLearnerHistory(learnerId)` → `{class_enrollments[], hours_logged[]}`
- `HistoryRepository::getAgentHistory(agentId)` → `{primary_classes[], backup_classes[], notes[], absences[]}`
- `HistoryRepository::getClientHistory(clientId)` → `{classes[], locations[]}`
- `HistoryRepository::getAgentClassHistory(classId)` / `getAgentClassHistoryByAgent(agentId)` → assignment rows from `agent_class_history`
- `schema/agent_class_history.sql` — DDL for agent-class assignment tracking
- `tests/History/bootstrap.php` — standalone PG test bootstrap
- `wecoza_events.audit_log` table (pre-existing)

Consumes:
- nothing (first slice)

### S02 → S03

Produces:
- `HistoryService::getClassTimeline(classId)` → merged array: agent assignments, learner list with LP status, status changes, class notes (from JSONB), event dates (from event_dates JSONB + events_log)
- `HistoryService::getAgentTimeline(agentId)` → merged array: classes facilitated (with type/subject/duration), clients derived from classes, agent notes, QA visits (from qa_visits table)
- `AuditService::log(action, entityType, entityId, context)` → writes to `wecoza_events.audit_log` with action code
- `AuditService::getEntityLog(entityType, entityId, limit, offset)` → paginated audit entries
- `AuditService::purgeOlderThan(months)` → retention cleanup
- `AuditService::registerCronPurge()` → WP-Cron scheduled event for 3-year cleanup
- AJAX endpoint: `wp_ajax_wecoza_get_entity_history` → returns entity history JSON (nonce-verified)
- Audit wiring: `AuditService::log()` called from class save and learner save AJAX handlers
- `[wecoza_audit_log]` shortcode → renders filterable audit log table

Consumes from S01:
- `HistoryRepository` — all query methods
- `wecoza_events.audit_log` table

### S02 → S04

Produces:
- `HistoryService::getLearnerTimeline(learnerId)` → merged array: class enrollments with LP status + client info, levels/subjects completed with start/completion dates, portfolio records (from learner_progression_portfolios)
- `HistoryService::getClientTimeline(clientId)` → merged array: all classes with status, agents derived from classes, learner list derived from classes.learner_ids
- AJAX endpoint reusable for all 4 entity types

Consumes from S01:
- `HistoryRepository` — learner and client query methods

### S03 → S05

Produces:
- `views/classes/components/single-class/history-section.php` — class history section (tables/lists, not interactive timeline)
- `views/agents/components/history-section.php` — agent history section
- CSS in `ydcoza-styles.css` for history section styling
- JS in `assets/js/classes/` and `assets/js/agents/` for AJAX history loading + pagination

Consumes from S02:
- `HistoryService::getClassTimeline()`, `getAgentTimeline()`
- AJAX endpoint for entity history

### S04 → S05

Produces:
- `views/learners/components/history-section.php` — learner history section
- `views/clients/components/history-section.php` — client history section
- JS for AJAX learner/client history loading + pagination

Consumes from S02:
- `HistoryService::getLearnerTimeline()`, `getClientTimeline()`
- AJAX endpoint for entity history
