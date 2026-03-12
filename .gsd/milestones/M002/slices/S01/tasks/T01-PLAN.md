# T01 — HistoryRepository: Entity Relationship Queries

**Objective:** Create `HistoryRepository` that queries existing database tables to reconstruct entity relationship timelines for classes, learners, agents, and clients. Also create the `agent_class_history` DDL schema for tracking agent-class assignment changes over time.

## Must-Haves

1. **`HistoryRepository`** class in `src/Classes/Repositories/` extending `BaseRepository`
2. **`getClassHistory(int $classId)`** — Returns timeline of:
   - Agent assignments (from `classes.class_agent`, `classes.initial_class_agent`, `classes.backup_agent_ids`)
   - Learner assignments (from `classes.learner_ids`)
   - Status changes (from `class_status_history`)
   - Stop/restart dates (from `classes.stop_restart_dates`)
3. **`getLearnerHistory(int $learnerId)`** — Returns timeline of:
   - Class enrollments (from `learner_lp_tracking` joined with `classes`)
   - LP progression status changes (from `learner_lp_tracking`)
   - Hours logged (from `learner_hours_log`)
4. **`getAgentHistory(int $agentId)`** — Returns timeline of:
   - Classes assigned as primary agent (from `classes.class_agent`)
   - Classes assigned as backup (from `classes.backup_agent_ids`)
   - Agent notes (from `agent_notes`)
   - Agent absences (from `agent_absences`)
5. **`getClientHistory(int $clientId)`** — Returns timeline of:
   - Classes associated (from `classes.client_id`)
   - Locations (from `locations` joined via `classes.site_id`)
6. **`agent_class_history` DDL** — Schema file in `schema/` for tracking agent-class assignment changes:
   - Columns: id, class_id, agent_id, assignment_type (primary/backup), assigned_date, removed_date, changed_by, created_at
7. **Test scaffolding** — Create test files referenced by slice verification (initially failing)

## Implementation Notes

- Follow existing repository pattern (extends BaseRepository, column whitelisting)
- Return arrays of associative arrays with consistent shape per entity type
- Use JOINs where needed but keep queries readable
- JSONB columns (`learner_ids`, `backup_agent_ids`, `stop_restart_dates`) need PostgreSQL JSON operators
- All methods should handle empty results gracefully (return empty arrays)
- No PII in returned data used for audit trail — entity IDs and metadata only

## Verification

- `php tests/History/HistoryServiceTest.php` created (expected to fail — T03 dependency)
- `php tests/History/AuditServiceTest.php` created (expected to fail — T02 dependency)
- `HistoryRepository` class loads without errors
- `schema/agent_class_history.sql` contains valid PostgreSQL DDL
