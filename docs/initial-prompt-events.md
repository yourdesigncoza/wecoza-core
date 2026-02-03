# Task: Refactor Events & Tasks Integration to Use Manual Event Capture

## Context
The current Events & Tasks system (shortcode `[wecoza_event_tasks]`) is outdated because the way events are captured in the class creation workflow has fundamentally changed.

## Previous Implementation (Current/Outdated)
- Events were **programmatically generated** from database fields
- System calculated events based on `class.original_start_date` and schedule data
- Events were derived automatically using `ScheduleService::generateEventsFromScheduleData()`
- Tasks were generated from `class_change_logs` via PostgreSQL triggers on INSERT/UPDATE operations
- Task templates came from `TaskTemplateRegistry` based on operation type

## New Implementation (Target State)
In the **Create New Class** form (`views/classes/components/class-capture-partials/create-class.php`), there is now a manual **Event Dates** section where users can add multiple events with:
- **Event Type** dropdown (deliveries, collections, exams, mock exams, SBA, learner packs, etc.)
- **Description** text field
- **Date** picker
- **Status** dropdown (e.g., Pending, Completed)
- **Notes** text area
- **Add Event** button to add multiple events dynamically

This data is currently stored (likely in a JSON field or related table), but it is **not integrated** with the Events & Tasks shortcode/dashboard.

## Required Actions

### Step 1: Investigation
1. Review the **previous/current** events generation logic:
   - `src/Events/Services/ClassTaskService.php`
   - `src/Classes/Services/ScheduleService.php`
   - `src/Events/Repositories/ClassTaskRepository.php`
   - How events are currently generated and displayed in `[wecoza_event_tasks]`

2. Review the **new** manual event capture system:
   - `views/classes/components/class-capture-partials/create-class.php` - Event Dates section
   - `views/classes/components/class-capture-partials/update-class.php` - Event Dates section
   - `assets/js/classes/class-schedule-form.js` - JavaScript handling event rows
   - Database schema: Identify where manual events are stored (likely `classes.event_dates` JSON column or similar)
   - Backend processing: Find where form submission saves these events

### Step 2: Design & Brainstorm
Propose an integration strategy that answers:
- Should manual events **replace** or **supplement** programmatically generated events?
- How should manual events map to the existing task system?
- Should each manual event type trigger specific tasks from `TaskTemplateRegistry`?
- How should the Events & Tasks dashboard display both event types (if supplementing)?
- What database schema changes are needed (if any)?
- How should the `ClassTaskPresenter` and `EventTasksShortcode` be modified?

### Step 3: Implementation Plan
After brainstorming, create a detailed implementation plan with:
- Files to modify
- New database fields/tables (if needed)
- Migration strategy from old to new system
- Backward compatibility considerations

## Deliverables
1. **Analysis document**: Comparison of old vs new event capture systems
2. **Integration proposal**: Recommended approach with pros/cons
3. **Implementation plan**: Step-by-step technical plan with file changes

Do NOT implement code yet - focus on understanding and planning first.