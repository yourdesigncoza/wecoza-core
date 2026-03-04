---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [3b] Progression Admin: LP description as class_type + subject + level"
area: learners
linear: https://linear.app/wecoza/issue/WEC-182
status: resolved
resolved_by: quick-17
resolved_date: 2026-03-04
files:
  - assets/js/learners/progression-admin.js
  - src/Learners/Repositories/LearnerProgressionRepository.php
---

## Clarification from Mario (2026-03-04)

LP description should concatenate class_type + class_subject + learner_level_module:

- "AET Communication CL1" (type + subject + level)
- "GETC AET - LO4" (type without subject + level)
- "BA2 - LP1" (type abbreviation + level)

For types that don't use class_subject (like GETC AET, Business Admin NQF 2), only use type + level/module.

## Solution

- Repository: ensure `class_type_name` (or abbreviation) and `learner_level_module` are included in progression queries
- JS `renderTable()`: build description string by concatenating available parts
- Logic: if `class_subject` exists → "TYPE SUBJECT LEVEL", else → "TYPE - LEVEL"
- Need to verify what fields are currently available in the progression data and what needs joining

## Resolution

Implemented in quick-17. `LearnerProgressionRepository.php` baseQuery() JOINs class_types and selects
`class_type_name`, `class_subject`, `subject_code`. `progression-admin.js` `buildLpDescription(row)`
concatenates TYPE + SUBJECT + CODE and is used in `renderTable()`, `renderHoursLogSummary()`, and
`buildFilterOptionsFromData()`.
