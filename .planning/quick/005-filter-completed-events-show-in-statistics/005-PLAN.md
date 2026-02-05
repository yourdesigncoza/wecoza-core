---
phase: quick-005
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/js/classes/class-schedule-form.js
  - views/classes/components/class-capture-partials/update-class.php
  - views/classes/components/class-capture-partials/create-class.php
autonomous: true

must_haves:
  truths:
    - "Completed events are hidden from Event Dates form section"
    - "Completed events appear in Schedule Statistics Event Dates table"
    - "Status column visible in Schedule Statistics Event Dates table"
    - "Non-completed events (Pending, Cancelled) remain editable in form"
  artifacts:
    - path: "assets/js/classes/class-schedule-form.js"
      provides: "Filtering logic and Status column in stats table"
      contains: "event.status === 'Completed'"
    - path: "views/classes/components/class-capture-partials/update-class.php"
      provides: "Status column header in stats table"
      contains: "<th>Status</th>"
    - path: "views/classes/components/class-capture-partials/create-class.php"
      provides: "Status column header in stats table"
      contains: "<th>Status</th>"
  key_links:
    - from: "assets/js/classes/class-schedule-form.js"
      to: "initEventDates()"
      via: "filter completed events before rendering"
      pattern: "status.*Completed"
---

<objective>
Filter completed events from Event Dates form section and display them (with Status column) in Schedule Statistics Event Dates table.

Purpose: Completed events should not clutter the editable form; they belong in the read-only statistics section for reference.
Output: Modified JS filtering logic, updated PHP table headers with Status column
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@assets/js/classes/class-schedule-form.js (lines 1068-1235 - initEventDates, collectEventDatesForStats, updateEventDatesStatistics)
@views/classes/components/class-capture-partials/update-class.php (lines 764-920 - Event Dates form and Schedule Statistics table)
@views/classes/components/class-capture-partials/create-class.php (lines 414-610 - Event Dates form and Schedule Statistics table)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Filter completed events from form, add Status column to stats table</name>
  <files>
    assets/js/classes/class-schedule-form.js
    views/classes/components/class-capture-partials/update-class.php
    views/classes/components/class-capture-partials/create-class.php
  </files>
  <action>
    **In class-schedule-form.js:**

    1. In `initEventDates()` (around line 1104-1127), modify the existing events forEach loop to skip rendering events with status === 'Completed':
       ```javascript
       existingEvents.forEach(function(event) {
           // Skip completed events - they only show in statistics
           if (event.status === 'Completed') {
               return;
           }
           // ... rest of existing row rendering code
       });
       ```

    2. In `updateEventDatesStatistics()` (around line 1182-1235), add Status column to the dynamically created rows:
       - Current columns: Type, Description, Date, Notes
       - New order: Type, Description, Date, Status, Notes
       - After line 1221 (`$('<td>').text(formatDateDDMMYYYY(event.date))`), add:
         ```javascript
         $('<td>').text(event.status || 'Pending'),
         ```
       - The status should render before Notes

    3. In `collectEventDatesForStats()` (around line 1143-1163), ensure ALL events are collected (including completed ones) - this already includes status, so no change needed there.

    **In update-class.php (around line 907-913):**

    Update the Schedule Statistics Event Dates table subheader row to include Status column:
    ```html
    <tr class="ydcoza-table-subheader" style="font-size: 0.85em; font-weight: 500;">
       <th></th>
       <th>Type</th>
       <th>Description</th>
       <th>Date</th>
       <th>Status</th>
       <th>Notes</th>
    </tr>
    ```
    Also update colspan on empty row (line 916) from 4 to 5.

    **In create-class.php (around line 601-610):**

    Same changes as update-class.php - add Status column header and update colspan on empty row.
  </action>
  <verify>
    1. Load an existing class with event dates that have different statuses (Pending, Completed, Cancelled)
    2. Verify Event Dates form shows only Pending and Cancelled events (NOT Completed)
    3. Open Schedule Statistics section
    4. Verify Event Dates table shows ALL events including Completed ones
    5. Verify Status column displays correctly (Pending, Completed, Cancelled)
    6. Verify column order is: Type, Description, Date, Status, Notes
  </verify>
  <done>
    - Completed events hidden from Event Dates form section
    - All events (including Completed) shown in Schedule Statistics
    - Status column visible in Schedule Statistics Event Dates table
    - Column order correct: Type, Description, Date, Status, Notes
  </done>
</task>

<task type="auto">
  <name>Task 2: Include completed events from database in statistics collection</name>
  <files>assets/js/classes/class-schedule-form.js</files>
  <action>
    The `collectEventDatesForStats()` function only collects from visible form rows (`.event-date-row:not(.d-none)`). Since completed events are no longer rendered in the form, they won't appear in statistics.

    Fix: Store completed events separately and merge them back for statistics display.

    1. Add a module-level variable to store completed events at the top of the IIFE (around line 6):
       ```javascript
       let completedEvents = [];
       ```

    2. In `initEventDates()`, when parsing existingEvents, collect completed events into the array:
       ```javascript
       // Clear previous completed events
       completedEvents = [];

       existingEvents.forEach(function(event) {
           // Store completed events for statistics display
           if (event.status === 'Completed') {
               completedEvents.push({
                   type: event.type || '',
                   description: event.description || '',
                   date: event.date || '',
                   status: 'Completed',
                   notes: event.notes || ''
               });
               return; // Skip rendering in form
           }
           // ... rest of existing row rendering code
       });
       ```

    3. In `collectEventDatesForStats()`, merge form events with completed events:
       ```javascript
       function collectEventDatesForStats() {
           const events = [];
           // Collect from visible form rows
           $('.event-date-row:not(.d-none):not(#event-date-row-template)').each(function() {
               // ... existing collection code
           });
           // Add completed events that aren't shown in form
           events.push(...completedEvents);
           return events;
       }
       ```

    This ensures completed events from the database appear in statistics even though they're not rendered in the form.
  </action>
  <verify>
    1. Create a class with 3 events: one Pending, one Completed, one Cancelled
    2. Save the class
    3. Reload the edit form
    4. Form should show only 2 events (Pending, Cancelled)
    5. Statistics should show all 3 events with correct Status values
    6. Add a new event, save - completed events should persist in statistics
  </verify>
  <done>
    - Completed events stored separately during form load
    - Statistics display merges form events + completed events
    - All events appear in statistics regardless of status
  </done>
</task>

</tasks>

<verification>
1. Create test class with multiple events having different statuses
2. Verify form filtering works (completed hidden)
3. Verify statistics shows all events with Status column
4. Verify editing/saving preserves completed events
5. Test on both create-class and update-class forms
</verification>

<success_criteria>
- Event Dates form hides completed events from editing
- Schedule Statistics Event Dates table shows all events including completed
- Status column added to Schedule Statistics Event Dates table
- No data loss when saving classes with completed events
</success_criteria>

<output>
After completion, create `.planning/quick/005-filter-completed-events-show-in-statistics/005-SUMMARY.md`
</output>
