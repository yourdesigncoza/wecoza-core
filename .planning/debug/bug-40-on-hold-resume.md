---
status: awaiting_human_verify
trigger: "Cannot change learner progression status from 'On hold' to 'Resume'. Error says 'status must be on hold to change' but the status IS already on hold."
created: 2026-03-03T00:00:00Z
updated: 2026-03-03T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - JavaScript sends intent as POST field named 'toggle' but PHP reads $_POST['action'] expecting 'hold'/'resume'. WordPress AJAX router consumes $_POST['action'] for routing, so PHP gets 'toggle_progression_hold' instead. Falls through to else/resume branch regardless of user's intent.
test: Trace JS POST data vs PHP $_POST['action'] read
expecting: Fix is to rename JS field to match a non-reserved name, and update PHP to read that field
next_action: Fix PHP to read $_POST['toggle'] (or rename both to a safe key like 'hold_action')

## Symptoms

expected: Changing status from 'On hold' should resume the learner tracking (set status back to 'in_progress')
actual: Error message says "status must be on hold to change" — contradictory because it IS already on hold
errors: "status must be on hold to change" (or similar validation error)
reproduction: Go to Learner Progression Report & Management page, find a learner with 'On hold' status, try to change/resume it
started: Reported Mar 2, 2026

## Eliminated

(none yet)

## Evidence

- timestamp: 2026-03-03T00:01:00Z
  checked: progression-admin.js handleToggleHold() AJAX call (line 798-825)
  found: JS sends POST field named 'toggle' with value 'hold' or 'resume', but PHP handler reads $_POST['action'] to get that intent
  implication: WordPress AJAX dispatcher consumes $_POST['action'] = 'toggle_progression_hold' (the hook name), so PHP reading $_POST['action'] gets the hook name string, not 'hold'/'resume'

- timestamp: 2026-03-03T00:01:30Z
  checked: ProgressionAjaxHandlers.php handle_toggle_progression_hold() lines 559-562
  found: $action = isset($_POST['action']) ? sanitize_key($_POST['action']) : ''; — reads wrong POST key
  implication: $action becomes 'toggle_progression_hold', not in allowed ['hold','resume'], so validation "action must be hold or resume" fires — OR falls through to else/resume branch

- timestamp: 2026-03-03T00:02:00Z
  checked: PHP handler lines 570-579
  found: if ($action === 'hold') {...} else { if (!$model->isOnHold()) throw... } — the else branch catches ALL non-'hold' values including the malformed 'toggle_progression_hold'
  implication: User clicking "Put on Hold" button (action='hold') sends toggle=hold, PHP reads action=toggle_progression_hold, != 'hold', falls to else (resume branch), checks isOnHold() on an in_progress record, throws "Cannot resume: LP status is 'in_progress', expected 'on_hold'" — this is the contradictory error. Also, user clicking "Resume" on an on_hold record: sends toggle=resume, PHP falls to else, isOnHold() is TRUE, calls model->resume() — this would actually work by coincidence. The real failure mode is the "Put on Hold" action.

## Resolution

root_cause: PHP handler `handle_toggle_progression_hold()` read `$_POST['action']` to get the 'hold'/'resume' intent, but WordPress AJAX dispatcher already consumes `$_POST['action']` as the routing key (value = 'toggle_progression_hold'). The JavaScript correctly sent the intent in a field named `toggle`, but the PHP read the wrong key. Result: $action = 'toggle_progression_hold', not in ['hold','resume'] validation, and the else/resume branch always executed regardless of user intent — causing "Cannot resume: LP status is 'in_progress'" error when trying to put an in_progress LP on hold.

fix: Changed PHP handler line 560 from `$_POST['action']` to `$_POST['toggle']` to match the field name the JavaScript actually sends.

verification: Fix reads the correct POST field 'toggle' which JavaScript sends as 'hold' or 'resume'. The if/else logic now correctly routes to hold vs resume branch based on the user's actual intent.

files_changed:
  - src/Learners/Ajax/ProgressionAjaxHandlers.php (line 560: $_POST['action'] → $_POST['toggle'])
