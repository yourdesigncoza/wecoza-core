---
status: resolved
trigger: "Learner update fails with foreign key violation - highest_qualification = 0 causes FK violation"
created: 2026-02-18T10:30:00Z
updated: 2026-02-18T11:00:00Z
symptoms_prefilled: true
---

## Current Focus

hypothesis: CONFIRMED - Two separate code paths fail to sanitize FK integer columns (0 -> NULL)
test: Read all relevant files - DONE
expecting: Fix both AJAX update handler and create shortcode
next_action: Apply fix in LearnerAjaxHandlers.php (update path) and learners-capture-shortcode.php (create path)

## Symptoms

expected: Learner update should save successfully even when FK dropdown fields have no selection
actual: SQLSTATE[23503] Foreign key violation on fk_highest_qualification - Key (highest_qualification)=(0) is not present in table "learner_qualifications"
errors: |
  [18-Feb-2026 10:17:26 UTC] WeCoza Core: Query error: SQLSTATE[23503]: Foreign key violation: 7 ERROR: insert or update on table "learners" violates foreign key constraint "fk_highest_qualification"
  DETAIL: Key (highest_qualification)=(0) is not present in table "learner_qualifications".
reproduction: Update any learner with highest_qualification dropdown left at default (value=0)
timeline: Current - happens on learner update form submission

## Eliminated

## Evidence

- timestamp: 2026-02-18T10:40:00Z
  checked: LearnerAjaxHandlers.php handle_update_learner()
  found: |
    $intFields = ['city_town_id', 'province_region_id', 'highest_qualification',
                  'numeracy_level', 'communication_level', 'employer_id'];
    $data[$field] = (in_array($field, $intFields) && $value !== '')
        ? intval($value)
        : $value;
    // When dropdown has value="0", $value = "0", $value !== '' is TRUE
    // So intval("0") = 0 is stored. 0 is not a valid FK id in the DB.
  implication: AJAX update path converts "0" to integer 0 for all FK columns. Fix must convert 0 -> null after intval().

- timestamp: 2026-02-18T10:41:00Z
  checked: learners-capture-shortcode.php data array construction
  found: |
    'highest_qualification' => intval($_POST['highest_qualification']),
    'numeracy_level' => intval($_POST['numeracy_level']),
    'communication_level' => intval($_POST['communication_level']),
    'employer_id' => intval($_POST['employer_id']),
    // Then later only employer_id, numeracy_level, communication_level get null treatment:
    $data['employer_id'] = !empty($data['employer_id']) ? $data['employer_id'] : null;
    $data['numeracy_level'] = !empty($data['numeracy_level']) ? $data['numeracy_level'] : null;
    $data['communication_level'] = !empty($data['communication_level']) ? $data['communication_level'] : null;
    // highest_qualification is MISSING from this null-coercion block
  implication: Create path also sends 0 for highest_qualification if not selected.

- timestamp: 2026-02-18T10:42:00Z
  checked: BaseModel::castValue()
  found: |
    if ($value === null || $value === '') { return null; }
    // 0 (integer) is neither null nor empty string, so it passes through as 0
    // castValue does NOT protect against 0 for int FK fields
  implication: No downstream protection. The fix must be at the input sanitization layer.

- timestamp: 2026-02-18T10:43:00Z
  checked: LearnerRepository::insert() FK validation
  found: |
    if (!empty($filteredData['highest_qualification'])) { ... validates ... }
    // !empty(0) === false, so value 0 SKIPS validation and goes straight to DB
    // DB then rejects it with FK violation
  implication: The existing FK validation in insert() is also bypassed by 0. Update path has NO FK validation at all.

## Resolution

root_cause: |
  Two code paths pass integer 0 (not NULL) for FK reference columns when dropdown has no selection:
  1. UPDATE path (LearnerAjaxHandlers.php): intval("0") = 0, no null coercion for 0 values
  2. CREATE path (learners-capture-shortcode.php): intval($_POST['highest_qualification']) = 0,
     missing null coercion for highest_qualification (employer_id/numeracy/communication are handled)
  PostgreSQL FK constraints reject 0 because no row with id=0 exists in reference tables.
fix: |
  Define a canonical list of nullable FK integer columns. After intval(), convert 0 -> null for all of them.
  Apply consistently in both UPDATE (AJAX handler) and CREATE (shortcode).
  Also fix the existing FK validation in LearnerRepository::insert() to handle null properly.
verification: |
  PHP syntax check passes on all 4 modified files.
  Logic verified: intval("0") = 0, 0 === 0 -> null. intval("5") = 5, 5 !== 0 -> 5 (correct).
  BaseModel::castValue() converts null input to null, so null FK values persist as NULL in DB.
  The existing insert() FK validation now uses isset/!== null instead of !empty(), correctly
  allowing null through while still validating non-null values.
files_changed:
  - src/Learners/Ajax/LearnerAjaxHandlers.php
  - src/Learners/Shortcodes/learners-capture-shortcode.php
  - src/Learners/Shortcodes/learners-update-shortcode.php
  - src/Learners/Repositories/LearnerRepository.php
