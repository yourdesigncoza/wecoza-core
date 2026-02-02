---
status: resolved
trigger: "class-subjects-missing: After refactoring two plugins into wecoza-core, class subjects are not loading"
created: 2026-02-02T00:00:00Z
updated: 2026-02-02T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED
test: All SQL queries using $1 placeholders changed to ? placeholders
expecting: Subjects will load correctly
next_action: Archive session

## Symptoms

expected: When a class type is selected, subjects for that class type should load
actual: Error message "No subjects found for the selected class type" appears
errors: class-types.js:219 Error loading class subjects: No subjects found for the selected class type.
reproduction: Select a class type in the class capture form
started: After refactoring two separate plugins (wecoza-classes-plugin, wecoza-learners-plugin) into wecoza-core

## Eliminated

## Evidence

- timestamp: 2026-02-02T00:10:00Z
  checked: AJAX handler registration
  found: wp_ajax_get_class_subjects IS registered to WeCoza\Classes\Controllers\ClassAjaxController::getClassSubjectsAjax
  implication: Hook registration is working correctly

- timestamp: 2026-02-02T00:15:00Z
  checked: ClassTypesController::getClassSubjects('AET')
  found: Returns empty array []
  implication: Problem is in the PHP data layer, not JavaScript or AJAX

- timestamp: 2026-02-02T00:20:00Z
  checked: Database query with $1 placeholder vs literal value
  found: $1 placeholder returns false, literal 'AET' returns correct data
  implication: Placeholder syntax is wrong for PDO

- timestamp: 2026-02-02T00:25:00Z
  checked: Database query with ? placeholder vs :name placeholder
  found: Both ? and :name work correctly with PDO, $1 does not
  implication: $1 is PostgreSQL native syntax, not PDO syntax

- timestamp: 2026-02-02T00:30:00Z
  checked: Original wecoza-classes-plugin ClassTypesController.php
  found: Uses ? placeholder (line 90: WHERE class_type_code = ?)
  implication: wecoza-core accidentally used wrong placeholder syntax during refactoring

## Resolution

root_cause: ClassTypesController.php uses PostgreSQL-native $1 placeholder syntax, but PDO requires ? (positional) or :name (named) placeholders. The query WHERE class_type_code = $1 never binds the parameter, so the WHERE clause matches nothing.
fix: Replaced all $1, $2, $3 placeholders with ? in all affected files
verification: PASSED - getClassSubjects('AET') now returns 3 subjects correctly, AJAX endpoint returns valid JSON response
files_changed:
  - src/Classes/Controllers/ClassTypesController.php (3 queries fixed)
  - src/Classes/Controllers/ClassAjaxController.php (4 queries fixed)
  - src/Classes/Controllers/QAController.php (1 query fixed)
  - src/Classes/Models/QAModel.php (8 queries fixed)
