---
status: resolved
trigger: "When creating a new class, the learner_ids JSONB column stores all 1s (e.g. [1,1,1,1,1,1]) instead of the actual selected learner IDs."
created: 2026-02-18T00:00:00Z
updated: 2026-02-18T00:05:00Z
---

## Current Focus

hypothesis: CONFIRMED — FormDataProcessor called intval() on array objects, PHP returns 1 for any array passed to intval()
test: Read FormDataProcessor.php lines 85-93
expecting: Bug confirmed and fixed
next_action: DONE

## Symptoms

expected: learner_ids column stores actual learner IDs like [42, 57, 103, ...]
actual: learner_ids column stores [1,1,1,1,1,1] — all entries are 1, count matches number of learners added
errors: None reported
reproduction: Create a new class, add learners to it, save. Check the database — learner_ids will be all 1s.
started: Unknown — possibly a recent regression

## Eliminated

- hypothesis: JS sends wrong attribute (checked index vs value)
  evidence: JS sends JSON objects with correct IDs via class_learners_data hidden field
  timestamp: 2026-02-18

- hypothesis: class-filler.js dev tool uses bad data
  evidence: Dev filler calls selectionTable.selectedLearners.add(learner.id) correctly — IDs are valid
  timestamp: 2026-02-18

## Evidence

- timestamp: 2026-02-18
  checked: assets/js/classes/learner-selection-table.js updateLearnersDataField()
  found: Stores JSON array of objects: [{id: "42", name: "...", level: "...", status: "..."}] in hidden field #class_learners_data
  implication: IDs are correct in JS; the problem is on the PHP side

- timestamp: 2026-02-18
  checked: src/Classes/Services/FormDataProcessor.php lines 85-93
  found: `array_map('intval', $learnerData)` where $learnerData is an array of associative arrays (objects). PHP's intval() called on an array always returns 1.
  implication: ROOT CAUSE — every learner object gets intval()'d to 1

- timestamp: 2026-02-18
  checked: exam_learners processing in same file lines 102-109
  found: Same bug — exam_learners also stores objects, same intval(array) = 1 issue
  implication: Fix needed for both learner_ids and exam_learners

## Resolution

root_cause: In FormDataProcessor.php, the JS sends class_learners_data as a JSON array of objects [{id, name, level, status}]. PHP decodes this into an array of associative arrays. The original code called array_map('intval', $learnerData) — applying intval() to each associative array. In PHP, intval(array) always returns 1 regardless of the array contents, producing [1,1,1,...].

fix: Changed both learner_ids and exam_learners processing to extract the 'id' key from each item before calling intval(). If the item is already a scalar (for backwards compatibility), it falls through to intval($item) directly.

verification: Code review confirms the fix extracts $item['id'] from each object element before intval(), producing the correct integer IDs.

files_changed:
  - src/Classes/Services/FormDataProcessor.php
