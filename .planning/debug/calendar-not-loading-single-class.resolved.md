---
status: awaiting_human_verify
trigger: "Calendar not loading on Display Single Class page (class_id=12). The calendar area appears blank with no visible errors."
created: 2026-02-27T00:00:00Z
updated: 2026-02-27T00:01:00Z
---

## Current Focus

hypothesis: CONFIRMED - FullCalendar initialized inside display:none container; renders with zero dimensions
test: Traced exact initialization flow in single-class-display.js
expecting: Moving calendar init to after content is revealed will fix the blank calendar
next_action: Fix single-class-display.js to initialize calendar after removing d-none from content

## Symptoms

expected: Calendar should display on the single class page showing session dates/schedule
actual: Calendar not loading on page load — appears blank, no errors shown to user
errors: No visible error messages. WordPress debug log has no relevant errors.
reproduction: Visit https://wecoza.co.za/wecoza/app/display-single-class/?class_id=12 — calendar section does not render
started: Unknown

## Eliminated

- hypothesis: Missing classId / invalid data passed to WeCozaCalendar.init
  evidence: PHP localizes WeCozaSingleClass.classId correctly from $class['class_id']; JS passes it as data.id
  timestamp: 2026-02-27T00:01:00Z

- hypothesis: AJAX handler for get_calendar_events not registered
  evidence: wp_ajax_get_calendar_events is registered in ClassAjaxController.initialize()
  timestamp: 2026-02-27T00:01:00Z

## Evidence

- timestamp: 2026-02-27T00:01:00Z
  checked: single-class-display.view.php line 99 + single-class-display.js lines 27-35, 38-43, 951
  found: |
    1. PHP renders #single-class-content with class="d-none" when show_loading=true
    2. DOMContentLoaded fires → SingleClassApp.init() runs
    3. A 500ms setTimeout is started to reveal content (remove d-none)
    4. initializeClassCalendar() is called SYNCHRONOUSLY before timeout fires
    5. FullCalendar.Calendar is constructed and .render() is called on #classCalendar
       which is INSIDE the d-none container (effectively display:none)
    6. FullCalendar renders with zero/undefined dimensions (can't measure hidden element)
    7. 500ms later: d-none removed, but FullCalendar already rendered with bad dimensions
    8. Calendar appears blank - no visible content, no errors
  implication: Root cause confirmed - calendar must be initialized AFTER content is visible

## Resolution

root_cause: FullCalendar is initialized on a DOM element that is inside a display:none container (#single-class-content with d-none class). FullCalendar cannot calculate/render dimensions on hidden elements. The initialization happens synchronously at DOMContentLoaded, before the 500ms setTimeout that reveals the content.
fix: Moved initializeClassCalendar() call inside the 500ms setTimeout callback (when showLoading=true), so FullCalendar is only initialized after #single-class-content has d-none removed and is visible. Added an else branch for showLoading=false that initializes immediately (content already visible).
verification: Awaiting user confirmation that calendar renders on the single class page
files_changed:
  - assets/js/classes/single-class-display.js
