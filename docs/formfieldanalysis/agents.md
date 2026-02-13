# Form Field Wiring Audit: Agents Module

**Audited:** 2026-02-12
**Baseline:** docs/FORM-FIELDS-REFERENCE.md
**DB Tables:** `agents`, `agent_meta`, `agent_notes`, `agent_absences`

---

## Summary

| Metric | Count |
|--------|-------|
| Fields checked | 40 |
| Forward path PASS | 23 |
| Forward path FAIL | 0 |
| Forward path WARN | 17 |
| Reverse path PASS | 36 |
| Reverse path FAIL | 1 |
| Dynamic wiring PASS | 8 |
| Dynamic wiring FAIL | 0 |
| Orphaned DB columns | 2 |
| Orphaned form fields | 0 |

---

## Forward Path (Form -> DB)

### Personal Information

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `title` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `first_name` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `second_name` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `surname` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `initials` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `gender` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `race` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

### Identification & Contact

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `id_type` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `sa_id_no` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `passport_number` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `tel_number` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `email_address` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

### Address

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `address_line_1` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `address_line_2` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `residential_suburb` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `city_town` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `province_region` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| `postal_code` | PASS | PASS | PASS | PASS | PASS | PASS | PASS |

### SACE Registration

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `sace_number` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `sace_registration_date` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `sace_expiry_date` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |

### Working Areas

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `preferred_working_area_1` | PASS | PASS | **WARN** | PASS | PASS | PASS | **WARN** |
| `preferred_working_area_2` | PASS | PASS | **WARN** | N/A | PASS | PASS | **WARN** |
| `preferred_working_area_3` | PASS | PASS | **WARN** | N/A | PASS | PASS | **WARN** |

### Phase & Subjects

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `phase_registered` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `subjects_registered` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `highest_qualification` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `agent_training_date` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |

### Quantum Assessments

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `quantum_assessment` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `quantum_maths_score` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `quantum_science_score` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |

### Legal & Compliance

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `criminal_record_date` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `criminal_record_file` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| `signed_agreement_date` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `signed_agreement_file` | PASS | PASS | PASS | N/A | PASS | PASS | PASS |

### Banking Details

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `bank_name` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `account_holder` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `account_number` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `branch_code` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |
| `account_type` | PASS | PASS | PASS | **WARN** | PASS | PASS | **WARN** |

---

## Reverse Path (DB -> Form)

| DB Column | Repo Fetch | Controller Pass | Field Mapping | Escaping | Edit Pre-pop | Status |
|-----------|-----------|-----------------|---------------|----------|-------------|--------|
| `first_name` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `second_name` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `surname` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `initials` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `title` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `gender` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `race` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `id_type` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `sa_id_no` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `passport_number` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `tel_number` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `email_address` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `residential_address_line` | PASS | PASS | PASS (`address_line_1`) | PASS | PASS | PASS |
| `address_line_2` | PASS | PASS | PASS (self-map) | PASS | PASS | PASS |
| `residential_suburb` | PASS | PASS | PASS (self-map) | PASS | PASS | PASS |
| `city` | PASS | PASS | PASS (self-map) | PASS | PASS | PASS |
| `province` | PASS | PASS | PASS (self-map) | PASS | PASS | PASS |
| **`residential_postal_code`** | PASS | PASS | **FAIL** | PASS | **FAIL** | **FAIL** |
| `preferred_working_area_1` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `preferred_working_area_2` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `preferred_working_area_3` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `sace_number` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `sace_registration_date` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `sace_expiry_date` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `phase_registered` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `subjects_registered` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `highest_qualification` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `quantum_assessment` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `quantum_maths_score` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `quantum_science_score` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `agent_training_date` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `criminal_record_date` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `criminal_record_file` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `signed_agreement_date` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `signed_agreement_file` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `bank_name` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `bank_account_number` | PASS | PASS | PASS (`account_number`) | PASS | PASS | PASS |
| `bank_branch_code` | PASS | PASS | PASS (`branch_code`) | PASS | PASS | PASS |
| `account_holder` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `account_type` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |

---

## Dynamic Data Wiring

| Field | Source Type | AJAX Registered | JS Calls | Cascade Complete | Hidden Updated | Status |
|-------|-----------|-----------------|----------|-----------------|----------------|--------|
| `title` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `gender` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `race` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `province_region` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `preferred_working_area_1/2/3` | Service (server-side) | N/A | N/A | N/A | N/A | PASS |
| `phase_registered` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `account_type` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `id_type` (radio) | Hardcoded options | N/A | PASS (toggle) | PASS | N/A | PASS |

---

## Orphan Detection

### DB Columns Without Form Fields

| Table | Column | Data Type | Possible Reason |
|-------|--------|-----------|----------------|
| `agents` | `agent_notes` | text | Notes managed via separate `agent_notes` table, not direct column |
| `agents` | `residential_town_id` | integer | Legacy field in repository whitelist, no form input exists |

### Form Fields Without DB Columns

| Field | Type | UI-Only? | Notes |
|-------|------|----------|-------|
| `google_address_search` | text | Yes | Google Places autocomplete helper, no `name` attr |
| `editing_agent_id` | hidden | Yes | Edit mode identifier, used to detect agent_id |
| `wecoza_agents_form_nonce` | hidden | Yes | CSRF nonce |

### Unused AJAX Endpoints

| Action | Registered In | Called From JS? |
|--------|--------------|-----------------|
| `wecoza_agents_paginate` | `AgentsAjaxHandlers.php:52` | Yes (`agents-ajax-pagination.js:157`) |
| `wecoza_agents_delete` | `AgentsAjaxHandlers.php:53` | Yes (`agent-delete.js:44`) |

*(No orphaned endpoints)*

### JS Selectors Targeting Missing DOM

*(No orphaned selectors found)*

---

## Issues

### Critical (Broken Wiring)

1. **`postal_code` / `residential_postal_code`** - Missing field mapping in `FormHelpers::$field_mapping`. View uses `FormHelpers::get_field_value($agent, 'postal_code')` but no mapping exists from `postal_code` -> `residential_postal_code`. **Postal code is NOT pre-populated in edit mode.** Forward path works (controller maps `postal_code` -> `residential_postal_code` in `collectFormData()`), but reverse path is broken.
   - File: `src/Agents/Helpers/FormHelpers.php:18-93` (missing mapping)
   - File: `views/agents/components/agent-capture-form.view.php:291` (uses unmapped key)

### Warning (Missing Best Practice)

1. **14 fields required in HTML but not validated server-side** - The following fields have `required` in the HTML form but `validateFormData()` does not check them: `title`, `subjects_registered`, `highest_qualification`, `agent_training_date`, `quantum_assessment`, `quantum_maths_score`, `quantum_science_score`, `signed_agreement_date`, `bank_name`, `account_holder`, `account_number`, `branch_code`, `account_type`, `residential_suburb`. Clients can bypass HTML validation via dev tools or API calls.
   - File: `src/Agents/Controllers/AgentsController.php:570-663`

2. **`preferred_working_area_1/2/3` not sanitized at controller level** - Read from `$_POST` without sanitization in `collectFormData()` (lines 452-454). Sanitized later in `AgentRepository::sanitizeAgentData()` via `sanitizeWorkingArea()`. Defense-in-depth recommends sanitizing at controller level too.
   - File: `src/Agents/Controllers/AgentsController.php:452-454`

3. **`agents.agent_notes` column unused by form** - The `agent_notes` text column in the `agents` table is in the repository's insert/update whitelist and sanitization map, but no form field writes to it. Notes are managed via the separate `agent_notes` table.
   - File: `src/Agents/Repositories/AgentRepository.php:120`

4. **`residential_town_id` orphaned in whitelist** - Column is in `getAllowedInsertColumns()` and `sanitizeAgentData()` but no form field populates it.
   - File: `src/Agents/Repositories/AgentRepository.php:123`

5. **Duplicate code across controller and AJAX handler** - `getAgentStatistics()`, `mapAgentFields()`, `mapSortColumn()`, and `getDisplayColumns()` are duplicated verbatim in `AgentsController.php` and `AgentsAjaxHandlers.php`. DRY violation.
   - Files: `AgentsController.php:743-938`, `AgentsAjaxHandlers.php:199-389`

### Info (Observations)

1. **`email` display alias** - `FormHelpers::$field_mapping` includes `'email' => 'email_address'` as a convenience alias. The display table uses `email` as a key in `mapAgentFields()`. Correct and intentional.

2. **`id_type` baseline doc** - Baseline doc lists `id_type` db_column as `-` but it maps to the `id_type` column in DB. Minor doc inaccuracy.

3. **All escaping correct** - All form field outputs use `FormHelpers::get_field_value()` which applies `esc_attr()` internally. Select fields use WordPress `selected()` / `checked()` helpers. File URLs use `esc_url()`. No XSS vectors found.

4. **Google Places integration solid** - Both new API (`PlaceAutocompleteElement`) and old API fallback correctly populate address fields with proper province mapping.

---

## Recommendations

1. **Fix `postal_code` mapping (Critical)** - Add `'postal_code' => 'residential_postal_code'` to `FormHelpers::$field_mapping` in `src/Agents/Helpers/FormHelpers.php`.

2. **Add server-side validation for 14 required fields** - Extend `validateFormData()` in `src/Agents/Controllers/AgentsController.php` to check all fields marked required in the HTML form.

3. **Sanitize working areas in controller** - Add `absint()` to `preferred_working_area_1/2/3` in `collectFormData()`.

4. **Extract shared display methods** - Move `getAgentStatistics()`, `mapAgentFields()`, `mapSortColumn()`, `getDisplayColumns()` into a shared service class to eliminate duplication between controller and AJAX handler.

5. **Clean up orphaned columns** - Remove `agent_notes` from `agents` table insert/update whitelist if notes are exclusively managed via `agent_notes` table. Remove `residential_town_id` from whitelist if unused.
