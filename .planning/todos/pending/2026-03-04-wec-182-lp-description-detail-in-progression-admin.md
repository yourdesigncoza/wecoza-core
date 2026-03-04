---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 [3b] Progression Admin: LP description detail"
area: learners
linear: https://linear.app/wecoza/issue/WEC-182
blocked_by: mario-clarification
files:
  - assets/js/learners/progression-admin.js
---

## Problem

Mario: "More details on the Learning Programme description must be more detailed, is it AET Communication or REALLL Communication, is it Business Admin NQF 2 or 3 LP1?"

Currently the progression admin table only shows `subject_name` (e.g., "Communication"). The `subject_code` is available in the data but not rendered.

Asked Mario: is `subject_code + subject_name` enough (e.g., "119631 — Communication"), or does he need the programme prefix (e.g., "BA3 — Communication LP1")? If the latter, where does that mapping come from?

## Solution

TBD — depends on Mario's answer.

- If code + name: simple JS change in `renderTable()` to show `row.subject_code + ' — ' + row.subject_name`
- If programme prefix: may need to join `class_type_subjects` → `class_types` to get the programme name, or add a new field
