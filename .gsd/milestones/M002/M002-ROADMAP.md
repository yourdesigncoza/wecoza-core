# M002: Change History & Audit Trail

**Vision:** Unified entity history views and a lightweight audit trail so staff can see the full relationship timeline of any learner, agent, client, or class — and know who changed class/learner records.

## Success Criteria

- Learner detail page shows a History tab with class history, client history, levels completed, portfolios, and progression dates
- Agent detail page shows a History tab with class history, client history, subjects facilitated, performance notes, and QA reports
- Client detail page shows a History tab with all learners, classes, and agents associated
- Class detail page shows a History tab with learner history, agent history, progression history, notes, and events
- Class and learner changes are logged to `wecoza_events.audit_log` with user, entity, action, and timestamp
- Audit log entries are high-level only (no field-level before/after values)
- 3-year retention cleanup mechanism exists

## Key Risks / Unknowns

- **JSONB relationship storage** — `learner_ids` on classes is a JSONB array, not a junction table. Querying "which classes was learner X in?" requires scanning all class records. Risk: performance on large datasets. Mitigation: use PostgreSQL JSONB operators with proper indexing.
- **Agent placement history gaps** — only current + initial agent stored. No timestamped history of agent replacements. Risk: incomplete agent-class history. Mitigation: create `agent_class_history` table for future tracking; for past data, derive from `class_events` where available.

## Proof Strategy

- **JSONB query performance** → retire in S01 by proving history queries return correct results from existing data with acceptable performance
- **Agent placement history** → retire in S01 by building the `agent_class_history` schema and populating from `class_events` data

## Verification Classes

- Contract verification: PHP integration tests verifying repository queries return correct history data
- Integration verification: Browser verification of history tabs rendering on entity detail pages
- Operational verification: Audit log entries created on real class/learner updates via AJAX
- UAT / human verification: Visual review of history tab content and layout

## Milestone Definition of Done

This milestone is complete only when all are true:

- All 4 entity history tabs render correct data on their respective detail pages
- Audit log captures class and learner changes at high level
- History data is derived from existing relational tables — no unnecessary duplication
- 3-year retention cleanup mechanism is in place
- All slices are browser-verified on running WordPress instance

## Requirement Coverage

- Covers: WEC-189 (entity history + audit trail)
- Partially covers: none
- Leaves for later: Monthly report history (no module exists), detailed field-level audit
- Orphan risks: none

## Slices

- [ ] **S01: History Data Layer & Audit Log** `risk:high` `depends:[]`
  > After this: HistoryRepository queries return correct entity relationship data from existing tables; audit log entries are written on class/learner changes; `agent_class_history` table exists for future agent placement tracking. Verified by PHP integration tests.

- [ ] **S02: Entity History UI Tabs** `risk:medium` `depends:[S01]`
  > After this: All 4 entity detail pages (learner, agent, client, class) display a History tab with timeline data. Browser-verified on running WordPress instance.

- [ ] **S03: Audit Trail Integration & Retention** `risk:low` `depends:[S01]`
  > After this: Audit log entries appear in a viewable log (admin or entity-level); 3-year retention cleanup runs via WP-Cron. Browser-verified end-to-end: update a class → see audit entry.

## Boundary Map

### S01 → S02

Produces:
- `HistoryService` with methods: `getLearnerHistory()`, `getAgentHistory()`, `getClientHistory()`, `getClassHistory()` — each returns structured arrays of relationship timeline data
- `AuditService` with `log()` method writing to `wecoza_events.audit_log`
- Schema SQL for `agent_class_history` table

Consumes:
- nothing (first slice)

### S01 → S03

Produces:
- `AuditService::log()` method for writing entries
- `AuditService::getEntityLog()` method for reading entries
- `AuditService::purgeOlderThan()` method for retention cleanup

Consumes:
- nothing (first slice)

### S02 → S03

Produces:
- History tab UI component structure (tab layout, AJAX loading pattern)

Consumes:
- S01: HistoryService methods for data
