# Phase 4: Task Management - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can view and manage tasks generated from class changes via the `[wecoza_event_tasks]` shortcode. Tasks are automatically created when classes are inserted or updated, and users can mark them complete or reopen them. This phase wires up the existing Events module task management infrastructure.

</domain>

<decisions>
## Implementation Decisions

### Task Generation
- Tasks generated based on class change operations (INSERT, UPDATE, DELETE) via PostgreSQL triggers
- Task templates per operation type (from `TaskTemplateRegistry`):
  - **INSERT**: agent-order (requires order number), load-learners, training-schedule, material-delivery, agent-paperwork
  - **UPDATE**: review-update, notify-agents, adjust-materials
  - **DELETE**: inform-stakeholders, archive-records
- Tasks stored as JSON in `class_change_logs.tasks` column
- Task templates customizable via `wecoza_events_task_templates` WordPress filter
- Tasks carry forward from previous change logs for same class (task continuity)

### Dashboard Layout
- Table-based layout with expandable accordion rows for task details
- Table columns: ID, Task Status, Change, Client ID & Name, Type, Subject, Start Date (sortable), Agent ID & Name, Exam Class, SETA, Actions
- Status badges: "Open +N" (warning/yellow) or "COMPLETED" (secondary/gray)
- Change type badges: NEW (success/green), UPDATE (primary/blue)
- Expanded rows show two-column layout: Open Tasks (left) and Completed Tasks (right)
- Bootstrap/Phoenix design system consistent with existing WeCoza UI
- Default limit: 20 classes per page

### Task Workflow
- Two statuses only: `open` and `completed`
- Complete action requires: user ID, timestamp, optional note
- "Agent Order Number" task requires mandatory note field (validates and stores in `classes.order_nr`)
- Reopen action clears completion info (status → open, completed_by/at/note → null)
- Immutable Task objects (operations return new instances)
- Tasks stored in `class_change_logs` table alongside change metadata

### Filtering & Sorting
- Sort by `original_start_date` (ascending or descending), default descending
- Filter by `class_id` via URL query parameter
- Client-side search: matches class ID, code, subject, type, client, agent, SETA, change type, task labels
- Client-side dropdown filter by open task type
- Prioritize open tasks by default (classes with open tasks shown first when no filters applied)

### AJAX Handling
- Action: `wecoza_events_task_update`
- Nonce: `wecoza_events_tasks`
- Requires logged-in user
- Returns updated task lists (open/completed) for real-time UI update

### Claude's Discretion
- None — preserve existing implementation exactly as-is from events plugin

</decisions>

<specifics>
## Specific Ideas

- Preserve exact behavior from wecoza-events-plugin standalone implementation
- All code already exists in `src/Events/` — this phase verifies it works correctly integrated
- Key components already migrated:
  - `TaskManager` — task CRUD operations
  - `TaskTemplateRegistry` — task templates per operation
  - `ClassTaskService` — dashboard data fetching
  - `ClassTaskRepository` — database queries
  - `TaskController` — AJAX handlers
  - `EventTasksShortcode` — shortcode registration
  - `ClassTaskPresenter` — data formatting for views
  - View: `views/events/event-tasks/main.php`

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 04-task-management*
*Context gathered: 2026-02-02*
