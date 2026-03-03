---
status: awaiting_human_verify
trigger: "event-tasks-text-overlap"
created: 2026-03-03T00:00:00Z
updated: 2026-03-03T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - task name div uses `w-30` (30% width) inside a flexbox `li` that also has `align-items-center`. Long text overflows because `w-30` = 30% of the list-item width, but there's no `overflow:hidden`, `text-overflow:ellipsis`, or `min-width:0` on the column - so text expands past its container and overlaps the sibling flex children (note input + button).
test: N/A - root cause confirmed through code reading
expecting: N/A
next_action: Apply fix - add min-width:0 + overflow/word-wrap to the label column

## Symptoms

expected: Task names fully visible, not overlapping note input field. Row: task name left | note field middle | Complete button right
actual: Task names like "Deliveries: Deliver Supplementary B..." get cut off and go behind the note input field
errors: No JS/PHP errors - purely a CSS/layout issue
reproduction: View any class with wecoza_event_tasks shortcode that has tasks with longer names
started: Current state of the UI

## Eliminated

## Evidence

- timestamp: 2026-03-03
  checked: main.php view template - open task list item (line 236-274)
  found: `<li class="list-group-item d-flex flex-row align-items-center justify-content-between gap-2 m-1">` - the li is a flex row. Inside it: `<div class="fw-semibold text-body w-30">` for the task name, then `<div class="d-flex flex-row gap-2 align-items-start flex-grow-1">` for note+button. The `w-30` class sets `width: 30% !important` but provides no overflow constraint.
  implication: In flexbox, a child with a fixed width percentage can still overflow if its content is too long, because there is no `min-width:0` or `overflow:hidden`. The text bleeds past 30% into the sibling's space.

- timestamp: 2026-03-03
  checked: JS-generated HTML in EventTasksShortcode.php (line 402-403)
  found: buildOpenTaskHtml() generates identical structure: `<div class="fw-semibold text-body w-30">` - same problem in dynamically-rendered tasks after AJAX update
  implication: Fix must address both PHP template and JS template, OR be a pure CSS fix that targets the class name.

- timestamp: 2026-03-03
  checked: Phoenix theme CSS and ydcoza-theme.css for `.w-30`
  found: `.w-30 { width: 30% !important; }` - defined in ydcoza-theme.css. No overflow or word-break properties. Phoenix extracted files have no w-30 class.
  implication: The fix should be a CSS rule targeting the task name div specifically (e.g. `.wecoza-task-list-open .fw-semibold.w-30`) to add `min-width:0; overflow-wrap:break-word; word-break:break-word;` - or better, target the specific context with `overflow:hidden; text-overflow:ellipsis; white-space:nowrap` for a clean truncation, or use `word-break:break-word` for wrapping.

## Resolution

root_cause: The task name column `<div class="fw-semibold text-body w-30">` uses `width:30%` in a flex row but has no overflow constraint (`min-width:0` is missing on flex children, and no `overflow:hidden`/`word-break`). In CSS flexbox, a flex child with percentage width does not automatically clip its text content - the text can expand beyond the declared width and overlap adjacent siblings (the note input and Complete button).
fix: Added CSS rule `.wecoza-task-list-open .list-group-item .w-30 { min-width:0; overflow-wrap:break-word; word-break:break-word; }` to ydcoza-styles.css. This constrains the flex child to its declared 30% width by setting min-width:0 (overrides the flex default of min-width:auto which allows expansion) and enables text wrapping.
verification: awaiting human confirmation
files_changed:
  - /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
