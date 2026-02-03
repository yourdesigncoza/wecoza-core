# Requirements: WeCoza Core v1.2 - Event Tasks Refactor

**Defined:** 2026-02-03
**Core Value:** Single source of truth for all WeCoza functionality

## v1.2 Requirements

Requirements for Event Tasks Refactor milestone. Replaces trigger-based task system with manual event capture.

### Database Cleanup

- [ ] **DB-01**: Drop PostgreSQL trigger `log_class_change_trigger` on classes table
- [ ] **DB-02**: Drop trigger function `log_class_change()`
- [ ] **DB-03**: Drop `class_change_logs` table

### Task System Refactor

- [ ] **TASK-01**: Rewrite TaskManager to build tasks from `classes.event_dates` JSONB
- [ ] **TASK-02**: Implement Agent Order Number as special always-present task
- [ ] **TASK-03**: Generate task IDs as `agent-order` and `event-{index}`
- [ ] **TASK-04**: Derive task labels from event type and description

### Data Sync

- [ ] **SYNC-01**: Update TaskController to write completion to `event_dates` JSONB
- [ ] **SYNC-02**: Add `completed_by` field to event schema (WordPress user ID)
- [ ] **SYNC-03**: Add `completed_at` field to event schema (ISO timestamp)
- [ ] **SYNC-04**: Implement reopen behavior (preserve notes, clear completion metadata)
- [ ] **SYNC-05**: Write Agent Order Number completion to `classes.order_nr` field

### Repository & Service Changes

- [ ] **REPO-01**: Simplify ClassTaskRepository to query classes directly (no JOIN to change logs)
- [ ] **REPO-02**: Update ClassTaskService to pass class data to TaskManager
- [ ] **REPO-03**: Update FormDataProcessor to handle completion metadata fields

### Presentation Layer

- [ ] **UI-01**: Update ClassTaskPresenter to present event-based tasks
- [ ] **UI-02**: Preserve existing UI interaction (Open/Completed columns, Complete/Reopen buttons)
- [ ] **UI-03**: Show all classes in dashboard (even those with no events)

### Code Cleanup

- [ ] **CLEAN-01**: Remove `src/Events/Models/ClassChangeSchema.php`
- [ ] **CLEAN-02**: Remove `src/Events/Services/ClassChangeListener.php`
- [ ] **CLEAN-03**: Remove `src/Events/Services/TaskTemplateRegistry.php`
- [ ] **CLEAN-04**: Remove `src/Events/Repositories/ClassChangeLogRepository.php`
- [ ] **CLEAN-05**: Remove `src/Events/Models/ClassChangeLogDTO.php`
- [ ] **CLEAN-06**: Remove `src/Events/Enums/ChangeOperation.php`

## Future Requirements

Deferred to future milestones.

### Notifications
- **NOTIF-01**: Email notifications on event status changes (if needed)

### Reporting
- **RPT-01**: Event completion reports by type/date range

## Out of Scope

Explicitly excluded from this milestone.

| Feature | Reason |
|---------|--------|
| AI summaries for events | Trigger-based AI summaries being removed with triggers |
| Email notifications on event changes | Notifications tied to old trigger system, revisit later |
| Material tracking changes | Separate system, not affected by this refactor |
| New event types | 8 existing types sufficient for v1.2 |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DB-01 | Phase 13 | Pending |
| DB-02 | Phase 13 | Pending |
| DB-03 | Phase 13 | Pending |
| TASK-01 | Phase 14 | Pending |
| TASK-02 | Phase 14 | Pending |
| TASK-03 | Phase 14 | Pending |
| TASK-04 | Phase 14 | Pending |
| SYNC-01 | Phase 15 | Pending |
| SYNC-02 | Phase 15 | Pending |
| SYNC-03 | Phase 15 | Pending |
| SYNC-04 | Phase 15 | Pending |
| SYNC-05 | Phase 15 | Pending |
| REPO-01 | Phase 14 | Pending |
| REPO-02 | Phase 14 | Pending |
| REPO-03 | Phase 15 | Pending |
| UI-01 | Phase 16 | Pending |
| UI-02 | Phase 16 | Pending |
| UI-03 | Phase 16 | Pending |
| CLEAN-01 | Phase 17 | Pending |
| CLEAN-02 | Phase 17 | Pending |
| CLEAN-03 | Phase 17 | Pending |
| CLEAN-04 | Phase 17 | Pending |
| CLEAN-05 | Phase 17 | Pending |
| CLEAN-06 | Phase 17 | Pending |

**Coverage:**
- v1.2 requirements: 24 total
- Mapped to phases: 24
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-03*
*Last updated: 2026-02-03 after initial definition*
