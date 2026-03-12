# M002: Change History & Audit Trail — Context

**Gathered:** 2026-03-11
**Status:** Ready for planning

## Project Description

Implement a two-part history system per WEC-189: (1) a lightweight, high-level audit trail logging who changed classes and learners, and (2) entity relationship history views surfacing the full timeline of each entity's relationships (learner↔class, agent↔class, client↔class, etc.).

## Why This Milestone

Mario confirmed (WEC-189 comment 2026-03-10) that entity relationship history is **more important** than change audit. The system already tracks some events (class_events, class_status_history, learner_hours_log) but does not surface a unified history view per entity. Field-level change tracking for classes and learners is "nice to have" at a high level only — no before/after values needed. 3-year retention.

## User-Visible Outcome

### When this milestone is complete, the user can:

- View a **Learner History** tab showing class history, client history, levels completed, portfolios, and progression dates
- View an **Agent History** tab showing class history, client history, subjects facilitated, performance notes, and QA reports
- View a **Client History** tab showing all learners, classes, agents, and monthly reports
- View a **Class History** tab showing learner history, agent history, progression history, notes, and events
- See a simple audit log entry when a class or learner record is changed (who, what entity, when — high level)

### Entry point / environment

- Entry point: Single-entity display pages (shortcodes: `[wecoza_single_learner_display]`, `[wecoza_single_agent]`, `[wecoza_display_single_class]`, client detail page)
- Environment: WordPress frontend (browser), authenticated users
- Live dependencies: PostgreSQL database

## Completion Class

- Contract complete: History tabs render correct data from existing relational tables; audit log captures class/learner changes
- Integration complete: History tabs appear on all 4 entity detail pages; audit log fires on real create/update AJAX calls
- Operational complete: 3-year retention cleanup mechanism exists (WP-Cron or manual)

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Navigate to a learner's detail page and see their full class/client/progression history
- Navigate to an agent's detail page and see their class/client/QA history
- Update a learner or class field and see the audit log entry created
- History data is derived from existing relational tables — no data duplication

## Risks and Unknowns

- **Relationship data gaps** — current schema stores `learner_ids` as JSONB array on classes, not a junction table. Historical agent placement tracked via `initial_class_agent` + `class_agent` but no timestamped history. Risk: some history may be incomplete for past records.
- **Performance on large datasets** — history queries join multiple tables. Risk: slow on classes with many learners. Mitigation: pagination + indexed queries.

## Existing Codebase / Prior Art

- `wecoza_events.audit_log` — existing table, currently unused by any PHP code. Can be repurposed for the audit trail.
- `public.class_events` — tracks CLASS_INSERT, CLASS_UPDATE, CLASS_DELETE, LEARNER_ADD/REMOVE events with JSONB `event_data`. Already captures significant class changes.
- `public.class_status_history` — tracks class status transitions with reason/notes/changed_by.
- `public.learner_lp_tracking` — tracks LP progression (status, hours, dates, completion).
- `public.learner_progression_portfolios` — portfolio files per LP tracking entry.
- `public.learner_exam_results` — exam step results per LP tracking entry.
- `public.learner_hours_log` — timestamped hours entries with source tracking.
- `public.qa_visits` — QA visit records linked to classes.
- `src/Events/Services/EventDispatcher.php` — existing event dispatch for class changes, already captures old/new data diffs.

> See `.gsd/DECISIONS.md` for all architectural and pattern decisions — it is an append-only register; read it during planning, append to it during execution.

## Scope

### In Scope

- Entity history tabs on all 4 entity detail pages (learner, agent, client, class)
- High-level audit trail for class and learner changes (who changed what entity, when)
- 3-year retention policy mechanism
- Deriving history from existing relational data where possible

### Out of Scope / Non-Goals

- Field-level before/after change tracking (Mario explicitly said high level only)
- Agent or client field-change audit logging (only classes and learners)
- Real-time change notifications
- "Undo" or rollback capability
- Monthly report history (no monthly reports module exists yet)

## Technical Constraints

- PostgreSQL (not MySQL)
- Must use existing MVC + Repository pattern
- CSS in `ydcoza-styles.css` only
- JS in `assets/js/` directory
- Read-only DB access during development — provide schema SQL files for developer to run
- Follow existing shortcode/AJAX patterns

## Integration Points

- `EventDispatcher` — hook into existing class change dispatch to write audit log entries
- AJAX handlers for learner/class updates — hook to capture change events
- Single-entity display shortcodes — add history tab UI
- Existing repository classes — query history data through them

## Open Questions

- How to handle historical agent changes on classes? Currently only `class_agent` and `initial_class_agent` are stored. May need an `agent_class_history` table for future changes. — Will implement going forward; past history limited to current + initial agent.
