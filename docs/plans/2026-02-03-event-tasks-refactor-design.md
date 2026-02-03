# Events & Tasks Refactor: Manual Event Capture Integration

**Date:** 2026-02-03
**Status:** Approved
**Milestone:** v1.2

## Overview

Replace the trigger-based task system with manual event capture. Tasks will be derived from user-entered events in the class form instead of auto-generated from database INSERT/UPDATE operations.

## Context

### Old System (Being Replaced)
- PostgreSQL triggers on `classes` table fire on INSERT/UPDATE
- Changes logged to `class_change_logs` table with JSONB diff
- Tasks auto-generated from `TaskTemplateRegistry` templates
- 5 tasks for INSERT, 3 additional for UPDATE operations

### New System (Target)
- Users manually add events in class create/update form
- Events stored in `classes.event_dates` JSONB column
- Each event becomes a completable task in the dashboard
- Bidirectional sync between form and dashboard

## Data Model

### Event Structure (Enhanced)

```json
{
  "type": "Deliveries",
  "description": "Initial materials",
  "date": "2025-03-15",
  "status": "Pending",
  "notes": "Hand deliver to site manager",
  "completed_by": null,
  "completed_at": null
}
```

**New fields:**
- `completed_by` (int|null) - WordPress user ID who completed
- `completed_at` (string|null) - ISO timestamp when completed

### Event Types
1. Deliveries
2. Collections
3. Exams
4. Mock Exams
5. SBA Collection
6. Learner Packs
7. QA Visit
8. SETA Exit

### Special Task: Agent Order Number
- Always present for every class
- Writes to `classes.order_nr` field on completion
- Status derived from: `order_nr` empty = Open, filled = Completed
- Required input (cannot complete without value)

## Task Generation Logic

```
ClassTaskService::getClassTasks()
    │
    ▼
Query classes table (SELECT class_id, event_dates, order_nr, ...)
    │
    ▼
TaskManager::buildTasksFromEvents($class)
    │
    ├── Create "Agent Order Number" task
    │   - id: "agent-order"
    │   - status: order_nr ? 'completed' : 'open'
    │   - note: order_nr value
    │
    └── For each event in event_dates[]:
        - id: "event-{index}"
        - label: "{type}: {description}" or "{type}"
        - status from event.status
        - completed_by/at from event metadata
```

## Bidirectional Sync

### Dashboard → Database (Complete Task)

```
AJAX POST: wp_ajax_wecoza_events_task_update
├── class_id: 57
├── task_id: "event-0" (or "agent-order")
├── task_action: "complete"
└── note: "Delivered to site manager"

TaskController::handleUpdate()
├── If "agent-order": UPDATE classes SET order_nr = $note
└── If "event-N":
    ├── Read event_dates JSONB
    ├── Update event_dates[N].status = 'Completed'
    ├── Update event_dates[N].completed_by = $user_id
    ├── Update event_dates[N].completed_at = now()
    ├── Update event_dates[N].notes = $note (if provided)
    └── Write back to classes.event_dates
```

### Reopen Behavior
- Sets `status = 'Pending'`
- Clears `completed_by` and `completed_at`
- **Preserves notes**

### Form → Dashboard
No special sync needed - dashboard reads fresh `event_dates` on each load.

## File Changes

### Files to REMOVE

| File | Reason |
|------|--------|
| `src/Events/Models/ClassChangeSchema.php` | PostgreSQL trigger definitions |
| `src/Events/Services/ClassChangeListener.php` | Background listener for triggers |
| `src/Events/Services/TaskTemplateRegistry.php` | Template-based task generation |
| `src/Events/Repositories/ClassChangeLogRepository.php` | Queries class_change_logs |
| `src/Events/Models/ClassChangeLogDTO.php` | DTO for change logs |
| `src/Events/Enums/ChangeOperation.php` | INSERT/UPDATE/DELETE enum |

### Files to MODIFY

| File | Changes |
|------|---------|
| `src/Events/Services/TaskManager.php` | Rewrite to read/write `classes.event_dates` |
| `src/Events/Services/ClassTaskService.php` | Query classes directly, not via change logs |
| `src/Events/Repositories/ClassTaskRepository.php` | Simplify query - no JOIN to change logs |
| `src/Events/Controllers/TaskController.php` | Update AJAX handler to modify `event_dates` JSONB |
| `src/Events/Shortcodes/EventTasksShortcode.php` | Minor updates to data flow |
| `src/Events/Views/Presenters/ClassTaskPresenter.php` | Present event-based tasks |
| `views/events/event-tasks/main.php` | Update template if needed |
| `src/Classes/Services/FormDataProcessor.php` | Handle `completed_by`/`completed_at` fields |

### Files to KEEP (Reuse)

- `src/Events/Models/Task.php` - Task domain model
- `src/Events/Models/TaskCollection.php` - Collection container

## Database Migration

```sql
-- 1. Drop the trigger on classes table
DROP TRIGGER IF EXISTS log_class_change_trigger ON public.classes;

-- 2. Drop the trigger function
DROP FUNCTION IF EXISTS public.log_class_change();

-- 3. Drop class_change_logs table
DROP TABLE IF EXISTS public.class_change_logs;
```

No DDL changes needed for `event_dates` column (JSONB is schema-less).

## Backward Compatibility

- Existing events without `completed_by`/`completed_at` treated as incomplete
- Events with `status: 'Completed'` but no metadata get current user/time on first interaction

## UI Behavior (Preserved)

- Class header with badges (NEW/UPDATE → simplified, no change tracking)
- Open Tasks column with task label + note input + Complete button
- Completed Tasks column with who + when + Reopen button
- Search/filter capabilities
- OPEN +N badge shows count of pending tasks

## Success Criteria

1. Dashboard shows all classes (even those with no events)
2. Agent Order Number task always visible, writes to `order_nr`
3. Each manual event appears as a completable task
4. Completing task updates event status bidirectionally
5. Reopen preserves notes but clears completion metadata
6. Old trigger system completely removed
7. No data loss during migration
