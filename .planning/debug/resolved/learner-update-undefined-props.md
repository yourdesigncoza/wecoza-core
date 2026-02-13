---
status: resolved
trigger: "Debug undefined property warnings"
created: 2026-02-13T00:00:00Z
updated: 2026-02-13T00:00:00Z
---

## Current Focus

hypothesis: SELECT query in repository doesn't include passport_number and address_line_2 columns
test: Read shortcode file to see how properties accessed, then check repository query
expecting: Repository SELECT query missing these columns
next_action: Read learners-update-shortcode.php lines around 300 and 351

## Symptoms

expected: Address Line 2 field shows the stored address value (or empty). Passport Number field shows stored value (or empty).
actual: Address Line 2 field displays PHP warning HTML: `<br /><b>Warning</b>: Undefined property: stdClass::$address_line_2`. Same issue for passport_number but may not be visible.
errors:
  1. PHP Warning: Undefined property: stdClass::$passport_number in /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Shortcodes/learners-update-shortcode.php on line 300
  2. PHP Warning: Undefined property: stdClass::$address_line_2 in /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Shortcodes/learners-update-shortcode.php on line 351
reproduction: Open any learner update form - the warnings appear for every learner
started: Current issue, likely introduced when properties were added to the form but not to the data retrieval layer

## Eliminated

## Evidence

- timestamp: 2026-02-13T00:05:00Z
  checked: Database schema (wecoza_db_schema_bu_feb_12_2.sql lines 3846, 3851)
  found: Both passport_number and address_line_2 columns exist in learners table
  implication: Columns exist in DB, so issue is in query or data mapping

- timestamp: 2026-02-13T00:06:00Z
  checked: LearnerRepository::baseQueryWithMappings() line 111
  found: Uses "SELECT learners.*" which should include all columns
  implication: Query should retrieve these columns

- timestamp: 2026-02-13T00:07:00Z
  checked: LearnerModel toDbArray() method lines 262, 267
  found: Model correctly maps passportNumber -> passport_number and addressLine2 -> address_line_2
  implication: Model mapping is correct

- timestamp: 2026-02-13T00:08:00Z
  checked: LearnerModel toDbArray() lines 285-286 and shortcode line 49
  found: toDbArray() filters out NULL values by default. Shortcode calls toDbArray() without includeNull=true
  implication: ROOT CAUSE FOUND - NULL values are filtered out, properties don't exist on stdClass

## Evidence

## Resolution

root_cause: In learners-update-shortcode.php line 49, `toDbArray()` is called without `$includeNull = true`. When passport_number or address_line_2 are NULL in the database, they get filtered out by array_filter() in LearnerModel::toDbArray() lines 285-286, causing the properties to not exist on the stdClass object. When the form tries to access these non-existent properties, PHP throws "Undefined property" warnings.
fix: Changed `toDbArray()` to `toDbArray(true)` in all learner shortcodes and AJAX handlers to preserve NULL values
verification: Fixed in 3 files. The fix ensures NULL values are preserved as properties on stdClass objects, preventing "Undefined property" warnings when templates access optional fields.
files_changed:
  - src/Learners/Shortcodes/learners-update-shortcode.php (line 49)
  - src/Learners/Shortcodes/learner-single-display-shortcode.php (line 40)
  - src/Learners/Ajax/LearnerAjaxHandlers.php (line 195)
