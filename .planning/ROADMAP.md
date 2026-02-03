# Roadmap: WeCoza Core v1.2 - Event Tasks Refactor

**Created:** 2026-02-03
**Milestone:** v1.2
**Phases:** 13-17 (continues from v1.1)

## Overview

Replace trigger-based task system with manual event capture integration. Tasks will be derived from user-entered events in the class form instead of auto-generated from database INSERT/UPDATE operations.

## Phase Summary

| Phase | Name | Goal | Requirements |
|-------|------|------|--------------|
| 13 | Database Cleanup | Remove trigger infrastructure | DB-01, DB-02, DB-03 |
| 14 | Task System Refactor | Rewrite TaskManager for event-based tasks | TASK-01..04, REPO-01, REPO-02 |
| 15 | Bidirectional Sync | Implement dashboard ↔ form synchronization | SYNC-01..05, REPO-03 |
| 16 | Presentation Layer | Update UI components for new data flow | UI-01, UI-02, UI-03 |
| 17 | Code Cleanup | Remove deprecated files | CLEAN-01..06 |

---

## Phase 13: Database Cleanup

**Goal:** Remove PostgreSQL trigger infrastructure that auto-generates tasks from class changes.

**Requirements:** DB-01, DB-02, DB-03

**Plans:** 1 plan (complete)

Plans:
- [x] 13-01-PLAN.md — Drop trigger, function, and table

**Success Criteria:**
1. Trigger `log_class_change_trigger` no longer exists on classes table
2. Function `log_class_change()` no longer exists
3. Table `class_change_logs` no longer exists
4. No errors on class INSERT/UPDATE operations

**Notes:**
- Execute SQL migration to drop trigger, function, and table
- Verify class create/update forms still work after trigger removal
- This is a breaking change - old task system stops working

---

## Phase 14: Task System Refactor

**Goal:** Rewrite core task services to build tasks from event_dates JSONB instead of change logs.

**Requirements:** TASK-01, TASK-02, TASK-03, TASK-04, REPO-01, REPO-02

**Plans:** 2 plans (complete)

Plans:
- [x] 14-01-PLAN.md — Add buildTasksFromEvents() factory method to TaskManager
- [x] 14-02-PLAN.md — Update repository and service for direct class queries

**Success Criteria:**
1. TaskManager builds TaskCollection from `classes.event_dates` array
2. Agent Order Number task always present with status derived from `order_nr` field
3. Each event in `event_dates[]` becomes a task with ID `event-{index}`
4. Task labels format as "{type}: {description}" or just "{type}"
5. ClassTaskRepository queries classes directly (no JOIN to change logs)
6. ClassTaskService passes class data to new TaskManager methods

**Notes:**
- Keep Task and TaskCollection models (reuse existing)
- TaskManager gets new method `buildTasksFromEvents($class)`
- Agent Order Number is special: writes to `classes.order_nr` on completion

---

## Phase 15: Bidirectional Sync

**Goal:** Implement synchronization between task dashboard and class form event data.

**Requirements:** SYNC-01, SYNC-02, SYNC-03, SYNC-04, SYNC-05, REPO-03

**Plans:** 2 plans (complete)

Plans:
- [x] 15-01-PLAN.md — Foundation methods (JSONB update, notes preservation, metadata passthrough)
- [x] 15-02-PLAN.md — TaskController refactor (class_id, completion/reopen persistence)

**Success Criteria:**
1. Completing task updates `event_dates[N].status` to 'Completed'
2. Completing task sets `event_dates[N].completed_by` to current user ID
3. Completing task sets `event_dates[N].completed_at` to current timestamp
4. Reopening task sets status to 'Pending' and clears completion metadata
5. Reopening task preserves notes field
6. Agent Order Number completion writes note value to `classes.order_nr`
7. FormDataProcessor handles `completed_by`/`completed_at` fields on form save

**Notes:**
- TaskController AJAX handler does the JSONB update
- Form → Dashboard sync is automatic (dashboard reads fresh data)
- Existing events without metadata treated as incomplete

---

## Phase 16: Presentation Layer

**Goal:** Update UI components to display event-based tasks with existing interaction patterns.

**Requirements:** UI-01, UI-02, UI-03

**Plans:** 2 plans

Plans:
- [ ] 16-01-PLAN.md — Fix JavaScript AJAX parameter (log_id to class_id) and clean view templates
- [ ] 16-02-PLAN.md — UI verification checkpoint

**Success Criteria:**
1. ClassTaskPresenter formats event-based tasks for display
2. Open Tasks column shows pending events + Agent Order Number
3. Completed Tasks column shows completed events with user/timestamp
4. Complete/Reopen buttons work with new data flow
5. All classes appear in dashboard (even those with zero events)
6. Search and filter functionality preserved

**Notes:**
- Preserve existing HTML/CSS structure
- Badge shows "OPEN +N" where N = count of pending tasks
- Agent Order Number input requires value (cannot complete empty)

---

## Phase 17: Code Cleanup

**Goal:** Remove deprecated files that are no longer used after refactor.

**Requirements:** CLEAN-01, CLEAN-02, CLEAN-03, CLEAN-04, CLEAN-05, CLEAN-06

**Success Criteria:**
1. `src/Events/Models/ClassChangeSchema.php` removed
2. `src/Events/Services/ClassChangeListener.php` removed
3. `src/Events/Services/TaskTemplateRegistry.php` removed
4. `src/Events/Repositories/ClassChangeLogRepository.php` removed
5. `src/Events/Models/ClassChangeLogDTO.php` removed
6. `src/Events/Enums/ChangeOperation.php` removed
7. No PHP errors or undefined class references
8. All imports/uses of removed classes cleaned up

**Notes:**
- Search codebase for any references to removed classes
- Update any autoloader configurations if needed
- Run full test suite after cleanup

---

## Dependencies

```
Phase 13 (DB Cleanup)
    |
Phase 14 (Task Refactor) <- depends on triggers being gone
    |
Phase 15 (Sync) <- depends on new TaskManager
    |
Phase 16 (UI) <- depends on sync working
    |
Phase 17 (Cleanup) <- must be last, removes old code
```

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Data loss from dropping class_change_logs | Export to CSV before dropping (optional audit trail) |
| Breaking existing task completions | Tasks now stored in event_dates, not change logs |
| Concurrent form/dashboard edits | JSONB update is atomic, last write wins |
| Missing events in old classes | Classes with empty event_dates still show Agent Order Number |

---
*Roadmap created: 2026-02-03*
*Last updated: 2026-02-03 after Phase 16 planning*
