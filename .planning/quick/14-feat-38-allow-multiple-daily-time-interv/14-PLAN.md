---
phase: 14-feat-38-allow-multiple-daily-time-interv
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - views/classes/components/class-capture-partials/create-class.php
  - views/classes/components/class-capture-partials/update-class.php
  - assets/js/classes/class-schedule-form.js
  - src/Classes/Services/FormDataProcessor.php
  - src/Classes/Services/ScheduleService.php
  - src/Classes/Services/AttendanceService.php
  - views/classes/components/single-class/details-general.php
  - views/classes/components/single-class-display.view.php
autonomous: false
requirements: [FEAT-38]

must_haves:
  truths:
    - "User can add multiple time intervals per day (e.g. 08:00-12:00 and 13:00-17:00)"
    - "Total daily hours = sum of all intervals, excluding gaps (lunch breaks)"
    - "Schedule statistics correctly reflect summed interval hours, not contiguous span"
    - "Calendar events display each interval as separate time block per day"
    - "Existing single-interval classes continue to work without data migration"
    - "Single class detail view shows all intervals per day"
  artifacts:
    - path: "assets/js/classes/class-schedule-form.js"
      provides: "Multi-interval UI controls and data collection"
    - path: "src/Classes/Services/FormDataProcessor.php"
      provides: "Validation of intervals array in timeData"
    - path: "src/Classes/Services/ScheduleService.php"
      provides: "Multi-interval schedule entry generation and calendar events"
    - path: "views/classes/components/class-capture-partials/create-class.php"
      provides: "Add Interval button in day-time-section-template"
    - path: "views/classes/components/single-class/details-general.php"
      provides: "Display multiple intervals per day"
  key_links:
    - from: "class-schedule-form.js getAllTimeData()"
      to: "FormDataProcessor::validateTimeData()"
      via: "schedule_data hidden field JSON"
      pattern: "intervals.*array"
    - from: "ScheduleService::getTimesForDay()"
      to: "generateWeeklyEntries/generateBiweeklyEntries"
      via: "returns array of intervals instead of single startTime/endTime"
      pattern: "getTimesForDay.*intervals"
    - from: "class-schedule-form.js calculateScheduleStatistics()"
      to: "stat-total-hours display"
      via: "sum of all interval durations per day"
---

<objective>
Allow multiple daily time intervals for class scheduling so lunch breaks and gaps
do not count as training hours.

Purpose: Currently each day has exactly one start/end time pair. Classes running
08:00-12:00 and 13:00-17:00 are forced to store 08:00-17:00 (9 hours) when actual
training is 8 hours. This feature adds an "Add Interval" button per day card,
allowing N intervals per day. Duration calculation sums only actual intervals.

Output: Updated JS, PHP services, views, and backward-compatible data format.
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@CLAUDE.md
@src/Classes/Services/ScheduleService.php
@src/Classes/Services/FormDataProcessor.php
@src/Classes/Services/AttendanceService.php
@assets/js/classes/class-schedule-form.js
@views/classes/components/class-capture-partials/create-class.php
@views/classes/components/class-capture-partials/update-class.php
@views/classes/components/single-class/details-general.php
@views/classes/components/single-class-display.view.php

<interfaces>
<!-- Current data format in schedule_data JSON (timeData section) -->

Single mode (unchanged):
```json
{
  "timeData": {
    "mode": "single",
    "single": { "startTime": "09:00", "endTime": "17:00", "duration": 8 }
  }
}
```

Per-day mode CURRENT (single interval per day):
```json
{
  "timeData": {
    "mode": "per-day",
    "perDayTimes": {
      "Monday": { "startTime": "08:00", "endTime": "17:00", "duration": 9 },
      "Wednesday": { "startTime": "08:00", "endTime": "17:00", "duration": 9 }
    }
  }
}
```

Per-day mode NEW (multiple intervals per day, backward compatible):
```json
{
  "timeData": {
    "mode": "per-day",
    "perDayTimes": {
      "Monday": {
        "intervals": [
          { "startTime": "08:00", "endTime": "12:00" },
          { "startTime": "13:00", "endTime": "17:00" }
        ],
        "duration": 8
      },
      "Wednesday": {
        "intervals": [
          { "startTime": "09:00", "endTime": "15:00" }
        ],
        "duration": 6
      }
    }
  }
}
```

BACKWARD COMPAT: Old format (startTime/endTime at top level, no intervals array)
must be auto-normalized to new format (single-element intervals array) wherever read.

Key PHP methods that need multi-interval awareness:
- FormDataProcessor::validateTimeData(array $timeData): array
- FormDataProcessor::validateSingleTimeData(array $singleData): array  -- reuse for each interval
- FormDataProcessor::validatePerDayTimeData(array $perDayData): array
- ScheduleService::getTimesForDay(array $timeData, ?string $dayName): ?array  -- now returns array of intervals
- ScheduleService::generateWeeklyEntries() -- generates N entries per date (one per interval)
- ScheduleService::calculateEventDuration() -- unchanged, works per interval
- AttendanceService::calculateHoursFromTimes() -- called per interval, sum results

Key JS functions that need multi-interval awareness:
- generatePerDaySections(selectedDays) -- add "Add Interval" button per day card
- getAllTimeData() -- collect intervals array per day
- calculateTimeDuration() -- unchanged, called per interval
- calculateScheduleStatistics() -- sum all interval durations per day
- recalculateEndDate() -- sum all interval durations for hoursPerWeek
- validatePerDayTimeSelections() -- validate each interval + check overlap between intervals on same day
- loadExistingScheduleData/populateFormWithScheduleData -- restore intervals from saved data
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Multi-interval UI and JS data collection</name>
  <files>
    views/classes/components/class-capture-partials/create-class.php
    views/classes/components/class-capture-partials/update-class.php
    assets/js/classes/class-schedule-form.js
  </files>
  <action>
**A) Update the day-time-section-template in BOTH create-class.php and update-class.php:**

The template (id="day-time-section-template") currently has a single start-time/end-time pair. Restructure it to:

1. Wrap the existing start/end time fields in a container div with class `interval-row` and `data-interval-index="0"`.
2. Add an "Add Interval" button (`btn btn-sm btn-subtle-primary add-interval-btn`) below the interval row(s), with icon `bi-plus-circle` and text "Add Interval". Place it inside `.card-body` after the intervals container.
3. Add a "Remove" button (`btn btn-sm btn-link text-danger remove-interval-btn`) on each interval row EXCEPT the first (the first interval is always required). Use `bi-x-circle` icon.
4. Wrap all interval rows in a div with class `intervals-container`.
5. The duration display at bottom should show TOTAL duration across all intervals.
6. Keep the "Copy to all days" button in the header -- it copies ALL intervals from the first day to all other days.

The template structure per day card should be:
```html
<div class="intervals-container">
  <div class="interval-row" data-interval-index="0">
    <div class="d-flex align-items-center gap-2">
      <div class="flex-grow-1">
        <label>Start</label>
        <select class="interval-start-time" ...>...</select>
      </div>
      <div class="flex-grow-1">
        <label>End</label>
        <select class="interval-end-time" ...>...</select>
      </div>
      <div class="align-self-end">
        <!-- remove btn, hidden on first interval -->
      </div>
    </div>
  </div>
</div>
<button class="add-interval-btn">+ Add Interval</button>
```

IMPORTANT: Rename CSS classes from `day-start-time`/`day-end-time` to `interval-start-time`/`interval-end-time` in the template. The JS will be updated to match.

**B) Update class-schedule-form.js:**

1. **generatePerDaySections(selectedDays):** When cloning the template, initialize with one interval row. Bind click handler for `.add-interval-btn` that clones the first interval-row, increments data-interval-index, clears selected values, shows the remove button, and appends to .intervals-container. Bind delegated click handler for `.remove-interval-btn` that removes the interval-row and re-indexes remaining rows. After add/remove, recalculate duration and call updateScheduleData().

2. **addIntervalRow($daySection):** New function. Clones the template interval-row (first row of that day section), increments index, clears values, shows remove button, appends to `.intervals-container`. Max 4 intervals per day (disable add button when reached). Re-attach event handlers for time change on new selects.

3. **removeIntervalRow($row):** New function. Removes the row, re-indexes siblings, re-enables add button if below max. Recalculates duration.

4. **getAllTimeData():** Change per-day collection from:
   ```js
   perDayTimes[day] = { startTime, endTime, duration }
   ```
   to:
   ```js
   perDayTimes[day] = { intervals: [{startTime, endTime}, ...], duration: totalDuration }
   ```
   Iterate `.interval-row` elements within each `.per-day-time-section`, collect each interval's start/end times. Calculate duration as sum of individual interval durations.

5. **calculatePerDayDuration(day):** Update to sum durations across all intervals in that day section. Display total in the `.duration-value` span.

6. **validatePerDayTimeSelections():** For each day section, validate each interval individually (start < end, 30min minimum, 8h max per interval). ALSO validate no overlap between intervals on the same day: sort intervals by start time, ensure each interval's start >= previous interval's end. Show overlap error on the offending interval-end-time select.

7. **validateRequiredTimeFields():** Update to check each interval row has both start and end times filled.

8. **initPerDayTimeHandlers():** Update selectors from `.day-start-time, .day-end-time` to `.interval-start-time, .interval-end-time`. Use event delegation on `.per-day-time-section` to handle dynamically added interval rows.

9. **calculateScheduleStatistics():** In per-day mode, `dayData.duration` already represents total interval hours (set by getAllTimeData), so the existing logic `totalWeeklyHours += dayData.duration` should work unchanged. But verify it reads from the new structure correctly.

10. **recalculateEndDate():** Same -- reads `timeData.perDayTimes[day].duration` which is the sum. Should work unchanged, but verify.

11. **Copy to all days button handler:** Update to copy ALL interval rows (not just single start/end) from the first day to every other day. Clone the entire `.intervals-container` content.

12. **loadExistingScheduleData / populateFormWithScheduleData:** When restoring per-day times from saved data, check if `perDayTimes[day].intervals` exists (new format) or if `perDayTimes[day].startTime` exists (old format). For old format, normalize to `{ intervals: [{ startTime, endTime }], duration }`. For new format, create the corresponding number of interval rows and populate values.

**BACKWARD COMPAT in JS:** Any code that previously read `perDayTimes[day].startTime` or `perDayTimes[day].endTime` directly must first normalize: if no `intervals` array exists but `startTime`/`endTime` do, wrap into single-element intervals array.

Create a helper function `normalizePerDayTimes(perDayTimes)` that ensures every day entry has an `intervals` array, handling both old and new format.
  </action>
  <verify>
    Load the class capture form in browser, select weekly pattern, check Monday and Wednesday.
    Verify each day card shows one interval with start/end time selects plus "Add Interval" button.
    Click "Add Interval" on Monday -- second interval row appears with remove button.
    Set times: Monday interval 1 = 08:00-12:00, interval 2 = 13:00-17:00. Duration shows 8.0h.
    Wednesday single interval 09:00-15:00 shows 6.0h.
    Open browser console, check no JS errors. Inspect hidden schedule_data field -- should contain
    intervals array format.
  </verify>
  <done>
    - Day cards support 1-4 time intervals per day with add/remove controls
    - Duration calculation sums all intervals (not contiguous span)
    - Copy-to-all copies all intervals from first day to other days
    - getAllTimeData() outputs intervals array format
    - Old single-interval data auto-normalizes on load
    - Overlap validation prevents intervals from overlapping on same day
    - No JS console errors
  </done>
</task>

<task type="auto">
  <name>Task 2: PHP backend multi-interval support (validation, schedule generation, display)</name>
  <files>
    src/Classes/Services/FormDataProcessor.php
    src/Classes/Services/ScheduleService.php
    src/Classes/Services/AttendanceService.php
    views/classes/components/single-class/details-general.php
    views/classes/components/single-class-display.view.php
  </files>
  <action>
**A) FormDataProcessor.php -- validatePerDayTimeData():**

Update to handle the new intervals array format. For each day entry:
- If `intervals` key exists and is array: validate each interval using validateSingleTimeData(). Also validate no overlaps (sort by startTime, ensure each start >= previous end). Calculate total `duration` as sum of interval durations.
- If `intervals` key does NOT exist but `startTime`/`endTime` exist (old format): normalize to `{ intervals: [{ startTime, endTime, duration }], duration }` for backward compat.
- Store validated result with `intervals` array and `duration` float.

Update validateTimeData() -- in `per-day` mode, pass through to updated validatePerDayTimeData(). The `single` mode path is unchanged.

Add a new private static helper: `normalizePerDayEntry(array $dayData): array` that takes either old or new format and returns normalized format with `intervals` array.

**B) FormDataProcessor.php -- reconstructScheduleData():**

In the section that builds `per_day_times` from `day_start_time`/`day_end_time` form fields (lines ~306-319), also check for `day_interval_start_time` and `day_interval_end_time` array fields that the multi-interval form may submit. Build the intervals array from these. If only the old `day_start_time[Day]` scalar fields exist, wrap into single-interval format.

**C) ScheduleService.php -- getTimesForDay():**

Currently returns `{ startTime, endTime }` or null. Change to return an array of interval objects: `[{ startTime, endTime }, ...]`. For backward compat, if the stored data has old format (no intervals array), wrap single startTime/endTime into one-element array.

In `per-day` mode: check `$timeData['perDay'][$dayName]['intervals']` first. If exists, return it. Otherwise fall back to wrapping `startTime`/`endTime` in array.

In `single` mode: return `[{ startTime, endTime }]` (one-element array).

**D) ScheduleService.php -- generateWeeklyEntries(), generateBiweeklyEntries(), generateMonthlyEntries():**

These call getTimesForDay() and create one entry per day. Now getTimesForDay returns an array of intervals. For each date, iterate all intervals and create one schedule entry per interval. Each entry keeps the same date but different start_time/end_time.

Update the entry format to also include an `interval_index` field so downstream consumers can distinguish intervals on the same date.

Example for weekly entries:
```php
$intervals = self::getTimesForDay($timeData, $dayName);
if ($intervals) {
    foreach ($intervals as $idx => $interval) {
        $entries[] = [
            'date' => $current->format('Y-m-d'),
            'start_time' => $interval['startTime'],
            'end_time' => $interval['endTime'],
            'interval_index' => $idx,
        ];
    }
}
```

**E) ScheduleService.php -- generateEventsFromV2Pattern():**

When generating calendar events from direct entries that now may have multiple entries per date, ensure each event gets a unique ID. Currently uses `'class_' . $class['class_id'] . '_' . $schedule['date']`. Append interval_index: `'class_' . $class['class_id'] . '_' . $schedule['date'] . '_' . ($schedule['interval_index'] ?? 0)`.

When generating from pattern-based data, the updated generateWeeklyEntries etc. already produce multiple entries per date, so the existing foreach loop handles it. Just ensure unique IDs.

**F) ScheduleService.php -- formatV2EventTitle():**

No changes needed -- it already formats per-entry. Each interval entry gets its own title.

**G) ScheduleService.php -- convertV2ToLegacy():**

Update per_day_times handling to include intervals if present. The legacy format consumers can use the first interval or the full array depending on their needs.

**H) AttendanceService.php:**

In the section where it maps schedule data to normalized timeData for getTimesForDay (around line 221-227), update the CRITICAL FORMAT MAPPING comment section. When reading `perDayTimes` from DB, normalize entries that have `intervals` arrays. The attendance calculation should sum hours across all intervals for a given day, not just use the single startTime/endTime.

Look for where `calculateHoursFromTimes` is called with a single start/end pair. If the schedule now has multiple intervals per day, the caller must iterate intervals and sum. Search for the pattern and update.

**I) Display views:**

1. `views/classes/components/single-class/details-general.php` (line ~195-206):
   Currently reads `$time_data['perDayTimes'][$day]` and gets `startTime`/`endTime`. Update to check for `intervals` array first. If exists, loop and display each interval separated by " & ". Example: "Monday: 8:00 AM - 12:00 PM & 1:00 PM - 5:00 PM".

   ```php
   if (isset($day_times['intervals']) && is_array($day_times['intervals'])) {
       $interval_displays = [];
       foreach ($day_times['intervals'] as $interval) {
           $sf = isset($interval['startTime']) ? 'startTime' : 'start_time';
           $ef = isset($interval['endTime']) ? 'endTime' : 'end_time';
           if (isset($interval[$sf], $interval[$ef])) {
               $s = wp_date('g:i A', strtotime($interval[$sf]));
               $e = wp_date('g:i A', strtotime($interval[$ef]));
               $interval_displays[] = "{$s} - {$e}";
           }
       }
       $day_display .= ": " . implode(' & ', $interval_displays);
   } elseif (isset($day_times[$start_field], $day_times[$end_field])) {
       // backward compat: old format without intervals array
       ...existing code...
   }
   ```

2. `views/classes/components/single-class-display.view.php` (line ~306-323):
   The daily_hours calculation averages hours across days. Update to handle intervals:
   For each day in perDayTimes, if `intervals` exists, sum all interval durations for that day. Otherwise fall back to old startTime/endTime calculation.

**CRITICAL: All PHP normalization must handle both camelCase (startTime/endTime) and snake_case (start_time/end_time) as the codebase has both patterns.**
  </action>
  <verify>
    1. Create a new class with Monday 08:00-12:00 and 13:00-17:00, Wednesday 09:00-15:00.
       Save and verify schedule_data in database contains intervals array format.
    2. View the saved class in single class display -- Monday shows "8:00 AM - 12:00 PM and 1:00 PM - 5:00 PM".
    3. Edit the class -- intervals load correctly in the form with two rows for Monday.
    4. Calendar shows separate event blocks for each interval on Monday dates.
    5. Edit an existing class that was saved with old format (single startTime/endTime) --
       loads correctly as single interval, can add more intervals.
  </verify>
  <done>
    - FormDataProcessor validates intervals array, normalizes old format
    - ScheduleService generates separate entries per interval per date
    - Calendar events have unique IDs per interval
    - AttendanceService sums hours across all intervals per day
    - Single class display shows all intervals per day separated by ampersand
    - Daily hours calculation in display view sums intervals, not span
    - Full backward compatibility with old single-interval data
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 3: Verify multi-interval scheduling end-to-end</name>
  <files></files>
  <action>User verifies the complete multi-interval scheduling feature works correctly across create, edit, display, and calendar views. Tests both new multi-interval classes and backward compatibility with existing single-interval classes.</action>
  <what-built>Multi-interval daily time support for class scheduling. Users can now add 1-4 time intervals per day (e.g. morning 08:00-12:00 and afternoon 13:00-17:00) so lunch breaks and gaps do not inflate training hours.</what-built>
  <how-to-verify>
    1. Navigate to Class Capture form (create new class)
    2. Select weekly pattern, check Monday and Wednesday
    3. On Monday card: set first interval 08:00-12:00, click "Add Interval", set second interval 13:00-17:00
    4. Verify Monday shows duration 8.0h (not 9.0h)
    5. On Wednesday: set single interval 09:00-15:00, verify 6.0h
    6. Click "Copy to All" on Monday -- Wednesday should get both Monday intervals
    7. Remove the copied intervals from Wednesday, set back to single 09:00-15:00
    8. Save the class, view it in single class display
    9. Verify Monday shows "8:00 AM - 12:00 PM and 1:00 PM - 5:00 PM"
    10. Edit the class -- verify intervals load correctly in form
    11. Open an older existing class (single-interval format) -- verify it loads and edits normally
    12. Check calendar view shows separate event blocks per interval on Monday dates
  </how-to-verify>
  <verify>User confirms all 12 verification steps pass</verify>
  <done>User approves the feature or provides issues to fix</done>
  <resume-signal>Type "approved" or describe issues to fix</resume-signal>
</task>

</tasks>

<verification>
- New class with multiple intervals saves and loads correctly
- Old class with single interval per day loads and edits without errors
- Duration calculations reflect actual training hours (sum of intervals, not span)
- Calendar displays separate events per interval
- Schedule statistics (total hours, avg hours/month) are accurate
- No JS console errors during form interaction
- No PHP errors in debug.log during save/load
</verification>

<success_criteria>
- Users can define 1-4 time intervals per day in the class schedule form
- Duration shows sum of actual intervals, not contiguous time span
- Backward compatible with all existing class data (no migration needed)
- Calendar events render as separate blocks per interval
- Single class detail view displays all intervals per day
</success_criteria>

<output>
After completion, create `.planning/quick/14-feat-38-allow-multiple-daily-time-interv/14-SUMMARY.md`
</output>
