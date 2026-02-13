# Form Field Wiring Audit: Classes Module

**Audited:** 2026-02-12
**Baseline:** docs/FORM-FIELDS-REFERENCE.md
**DB Tables:** `classes` (28 columns), `qa_visits` (8 columns), `agent_replacements` (separate table)

## Summary

| Metric | Count |
|--------|-------|
| Fields checked | 42 |
| Forward path PASS | 34 |
| Forward path FAIL | 0 |
| Forward path WARN | 8 |
| Reverse path PASS | 22 |
| Reverse path FAIL | 1 |
| Reverse path WARN | 1 |
| Dynamic wiring PASS | 8 |
| Dynamic wiring FAIL | 0 |
| Orphaned DB columns | 2 |
| Orphaned form fields | 0 |

## Forward Path (Form -> DB)

### Basic Class Details

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `class_id` | PASS | PASS (intval) | PASS | PASS | N/A (PK) | PASS | PASS |
| `client_id` | PASS | PASS (intval) | PASS | WARN (no FK check) | PASS | PASS | PASS |
| `site_id` | PASS | PASS | WARN (no intval) | WARN (no FK check) | PASS | PASS | **WARN** |
| `site_address` -> `class_address_line` | PASS | PASS (sanitizeText) | PASS | N/A (readonly) | PASS | PASS | PASS |
| `class_type` | PASS | PASS (sanitizeText) | PASS | WARN (no whitelist) | PASS | PASS | PASS |
| `class_subject` | PASS | PASS (sanitizeText) | PASS | WARN (no whitelist) | PASS | PASS | PASS |
| `class_duration` | PASS | PASS (intval) | PASS | N/A (auto) | PASS | PASS | PASS |
| `class_code` | PASS | PASS (sanitizeText) | PASS | N/A (auto) | PASS | PASS | PASS |
| `class_start_date` -> `original_start_date` | PASS | PASS (mapped via schedule_start_date) | PASS | PASS | PASS | PASS | PASS |

### Schedule (JSONB: `schedule_data`)

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `schedule_pattern` | PASS | PASS | PASS | PASS (whitelist) | PASS (via schedule_data) | PASS | PASS |
| `schedule_days[]` | PASS | PASS | PASS (array_intersect) | PASS (day whitelist) | PASS | PASS | PASS |
| `schedule_day_of_month` | PASS | PASS | PASS (intval) | PASS (1-31) | PASS | PASS | PASS |
| `schedule_start_date` | PASS | PASS | PASS (isValidDate) | PASS | PASS | PASS | PASS |
| `schedule_end_date` | PASS | PASS | PASS (isValidDate) | PASS | PASS | PASS | PASS |
| `day-start-time` / `day-end-time` | PASS | PASS | PASS (sanitize_text_field) | PASS (HH:MM regex) | PASS | PASS | PASS |

### Exception Dates (JSONB: `schedule_data.exceptionDates`)

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `exception_dates[]` / `exception_reasons[]` | PASS | PASS | PASS (sanitize_text_field) | PASS (isValidDate) | PASS | PASS | PASS |

### Stop/Restart Dates (JSONB: `stop_restart_dates`)

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `stop_dates[]` / `restart_dates[]` | PASS | PASS | **WARN** (no sanitize) | **WARN** (no date validation) | PASS | PASS | **WARN** |

### Event Dates (JSONB: `event_dates`)

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `event_types[]` | PASS | PASS | PASS (sanitizeText) | PASS | PASS | PASS | PASS |
| `event_descriptions[]` | PASS | PASS | PASS (sanitizeText) | PASS | PASS | PASS | PASS |
| `event_dates_input[]` | PASS | PASS | PASS (sanitizeText) | PASS | PASS | PASS | PASS |
| `event_statuses[]` | PASS | PASS | PASS | PASS (whitelist) | PASS | PASS | PASS |
| `event_notes[]` | PASS | PASS | PASS (sanitizeText) | PASS | PASS | PASS | PASS |

### Funding & Exam

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `seta_funded` | PASS | PASS (bool convert) | PASS | PASS | PASS | PASS | PASS |
| `seta_id` -> `seta` | PASS | PASS (sanitizeText) | PASS | WARN (no whitelist) | PASS | PASS | PASS |
| `exam_class` | PASS | PASS (bool convert) | PASS | PASS | PASS | PASS | PASS |
| `exam_type` | PASS | PASS (sanitizeText) | PASS | WARN (no validate) | PASS | PASS | PASS |

### Learners (JSONB)

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `class_learners_data` -> `learner_ids` | PASS | PASS (json_decode) | **WARN** (no ID sanitize) | **WARN** (no FK check) | PASS | PASS | **WARN** |
| `exam_learners` (hidden) | PASS | PASS (json_decode) | **WARN** (no ID sanitize) | **WARN** (no FK check) | PASS | PASS | **WARN** |

### Agents & Assignments

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `initial_class_agent` | PASS | PASS (intval) | PASS | WARN (no FK) | PASS | PASS | PASS |
| `initial_agent_start_date` | PASS | PASS (sanitizeText) | PASS | WARN (no date validate) | PASS | PASS | PASS |
| `project_supervisor` -> `project_supervisor_id` | PASS | PASS (intval) | PASS | WARN (no FK) | PASS | PASS | PASS |
| `backup_agent_ids[]` / `backup_agent_dates[]` | PASS | PASS (intval on ID) | PASS | WARN (date not validated) | PASS | PASS | PASS |
| `replacement_agent_ids[]` / `replacement_agent_dates[]` | PASS | PASS (intval on ID) | PASS | PASS (requires both) | PASS | PASS (separate table) | PASS |

### Notes (JSONB: `class_notes_data`, AJAX-driven)

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `note-content` | PASS | PASS (saveClassNote) | PASS (sanitize_textarea_field) | PASS (required) | PASS | PASS | PASS |
| `note-priority` | PASS | PASS | PASS (sanitize_text_field) | PASS | PASS | PASS | PASS |
| `note-category` | PASS | PASS | PASS (sanitize_text_field) | PASS | PASS | PASS | PASS |

### QA Visits (`qa_visits` table, separate from `classes`)

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `qa_visit_dates[]` | PASS | PASS (QAController) | PASS (sanitize_text_field) | WARN (no date validate) | N/A (QAVisitModel) | PASS | PASS |
| `qa_visit_types[]` | PASS | PASS | PASS (sanitize_text_field) | PASS | N/A | PASS | PASS |
| `qa_officers[]` | PASS | PASS | PASS (sanitize_text_field) | PASS | N/A | PASS | PASS |
| `qa_reports[]` | PASS | PASS (UploadService) | PASS (PDF only, 10MB) | PASS | N/A | PASS (latest_document jsonb) | PASS |

---

## Reverse Path (DB -> Form)

| DB Column | Repo Fetch | Controller Pass | Field Mapping | Escaping | Edit Pre-pop | Status |
|-----------|-----------|-----------------|---------------|----------|-------------|--------|
| `client_id` | PASS | PASS | PASS | PASS (esc_html) | PASS (hidden + display) | PASS |
| `site_id` | PASS | PASS | PASS | PASS (esc_attr) | PASS (hidden) | PASS |
| `class_address_line` | PASS | PASS | PASS (-> site_address) | PASS (esc_html) | PASS | PASS |
| `class_type` | PASS | PASS | PASS | PASS (esc_html) | PASS (hidden + display) | PASS |
| `class_subject` | PASS | PASS | PASS | PASS (esc_html) | PASS (hidden + display) | PASS |
| `class_code` | PASS | PASS | PASS | PASS (esc_html) | PASS | PASS |
| `class_duration` | PASS | PASS | PASS | PASS | PASS | PASS |
| `original_start_date` | PASS | PASS | PASS (-> class_start_date) | PASS (esc_attr) | PASS | PASS |
| `seta_funded` | PASS (Yes/No string) | PASS | PASS | PASS (selected attr) | PASS | PASS |
| `seta` | PASS | PASS | PASS (-> seta_id) | PASS | PASS | PASS |
| `exam_class` | PASS (Yes/No string) | PASS | PASS | PASS | PASS | PASS |
| `exam_type` | PASS | PASS | PASS | PASS | PASS | PASS |
| `class_agent` | PASS | PASS | **WARN** (see note) | PASS (esc_html) | **WARN** (pre-selects initial_class_agent) | **WARN** |
| `initial_class_agent` | PASS | PASS | PASS | PASS | PASS | PASS |
| `initial_agent_start_date` | PASS | PASS | PASS | PASS (esc_attr) | PASS | PASS |
| `project_supervisor_id` | PASS | PASS | PASS (-> project_supervisor) | PASS | PASS | PASS |
| `learner_ids` (jsonb) | PASS | PASS | PASS | PASS (json_encode for JS) | PASS (JS pre-selects) | PASS |
| `exam_learners` (jsonb) | PASS | PASS | PASS | PASS | PASS | PASS |
| `backup_agent_ids` (jsonb) | PASS | PASS | PASS | PASS | PASS | PASS |
| `agent_replacements` | PASS (separate table) | PASS | PASS | PASS | PASS (update only) | PASS |
| `schedule_data` (jsonb) | PASS | PASS | PASS | PASS (json_encode) | PASS (JS reads) | PASS |
| `stop_restart_dates` (jsonb) | PASS | PASS | PASS | PASS | PASS | PASS |
| `event_dates` (jsonb) | PASS | PASS | PASS | PASS | PASS | PASS |
| `class_notes_data` (jsonb) | PASS (cached) | PASS | PASS | PASS (AJAX) | PASS (AJAX load) | PASS |
| `order_nr` | **FAIL** (not in getSingleClass) | **FAIL** | N/A | N/A | **FAIL** | **FAIL** |

**Note on `class_agent`:** In update-class.php line 1509, the `initial_class_agent` select pre-selects using `class_agent` value (not `initial_class_agent`). This means if agent replacements change the current agent, the "Initial Class Agent" dropdown shows the *current* agent, not the *original* one. Semantically incorrect but functionally doesn't break the form.

---

## Dynamic Data Wiring

| Field | Source Type | AJAX Registered | JS Calls | Cascade Complete | Hidden Updated | Status |
|-------|-----------|-----------------|----------|-----------------|----------------|--------|
| `client_id` -> `site_id` | DB (preloaded) | N/A (JS-filtered) | PASS | PASS | N/A | PASS |
| `site_id` -> `site_address` | DB (preloaded) | N/A (JS lookup) | PASS | PASS | N/A | PASS |
| `class_type` -> `class_subject` | AJAX | PASS (`get_class_subjects` + nopriv) | PASS | PASS (also updates duration + code) | N/A | PASS |
| `seta_funded` -> `seta_id` | Config (static) | N/A | PASS (toggle visibility) | PASS | N/A | PASS |
| `exam_class` -> `exam_type` | Text input | N/A | PASS (toggle visibility) | PASS | N/A | PASS |
| `schedule_pattern` -> day controls | JS-driven | N/A | PASS | PASS | PASS (hidden inputs) | PASS |
| learner checkboxes -> `class_learners_data` | JS-driven | N/A | PASS | N/A | PASS | PASS |
| exam select -> `exam_learners` | JS-driven | N/A | PASS | N/A | PASS | PASS |

---

## Orphan Detection

### DB Columns Without Form Fields

| Table | Column | Data Type | Possible Reason |
|-------|--------|-----------|----------------|
| `classes` | `order_nr_metadata` | jsonb | **Orphan.** No form field, not in FormDataProcessor, not in repo whitelist. Never written or read by application code in the classes module. |
| `classes` | `class_agent` | integer | **Partial orphan.** No form field to set it directly. Read in update view for display, but on create it is saved as null. Likely intended to track the "current" agent but no mechanism updates it when agent replacements are made. |

### Form Fields Without DB Columns

| Field | Type | UI-Only? | Notes |
|-------|------|----------|-------|
| `nonce` | hidden | Yes | CSRF token. Expected. |
| `redirect_url` | hidden | Yes | Post-submit redirect. Expected. |
| `learner-search-input` | text | Yes | Client-side search filter. Expected. |
| `schedule_end_date` | date (readonly) | Yes | Calculated by JS, stored inside `schedule_data.endDate`. Expected. |
| `schedule_total_hours` | text (readonly) | Yes | Calculated by JS. Not persisted. Expected. |

### Unused AJAX Endpoints

| Action | Registered In | Called From JS? | Notes |
|--------|--------------|-----------------|-------|
| `get_qa_analytics` (+ nopriv) | QAController:36 | qa-dashboard.js | Used by QA dashboard shortcode, not class form |
| `get_qa_summary` (+ nopriv) | QAController:38 | qa-dashboard.js | Used by QA dashboard shortcode |
| `create_qa_visit` (+ nopriv) | QAController:42 | qa-dashboard.js | **Security: allows unauthenticated visit creation** |
| `export_qa_reports` (+ nopriv) | QAController:44 | qa-dashboard.js | **Security: allows unauthenticated export** |
| `delete_qa_report` (+ nopriv) | QAController:48 | qa-dashboard.js | **Security: allows unauthenticated deletion** |
| `get_class_qa_data` (+ nopriv) | QAController:50 | qa-dashboard.js | Data exposure to unauthenticated users |
| `submit_qa_question` (+ nopriv) | QAController:52 | qa-dashboard.js | **Security: allows unauthenticated submissions** |

### JS Selectors Targeting Missing DOM

No issues found. All jQuery selectors in class-capture.js, class-schedule-form.js, class-types.js, and learner-selection-table.js have corresponding DOM elements in the form views.

---

## Issues

### Critical (Broken Wiring)

1. **`order_nr` data lost on update.** `FormDataProcessor` processes `order_nr` (line 72) and `ClassModel.save()`/`update()` includes it (lines 171, 231). However, `ClassRepository::getSingleClass()` (lines 596-625) does **not** include `order_nr` in the result array. When editing a class, `order_nr` is not passed to the view, so the form submits it as null, **overwriting the existing value**. File: `src/Classes/Repositories/ClassRepository.php:596`

2. **`class_agent` always null on create.** No form field named `class_agent` exists. `FormDataProcessor` reads `$data['class_agent']` (line 66) which is absent from POST data, so it returns null. `ClassModel.save()` writes `class_agent => null` to DB (line 160). The `initial_class_agent` is saved separately but `class_agent` (intended to track the current agent) starts as null. File: `src/Classes/Services/FormDataProcessor.php:66`

### Warning (Missing Best Practice)

1. **QA endpoints registered with `nopriv` access.** All 8 QA AJAX endpoints in `QAController::initialize()` are registered with both `wp_ajax_` and `wp_ajax_nopriv_`, allowing unauthenticated users to: create QA visits, delete QA reports, export data, and submit questions. While the site requires login globally, this is defense-in-depth failure. File: `src/Classes/Controllers/QAController.php:35-52`

2. **`stop_dates[]`/`restart_dates[]` not sanitized.** In `FormDataProcessor::processFormData()` (lines 136-148), stop and restart dates are stored directly from `$data['stop_dates']` and `$data['restart_dates']` without `sanitizeText()` or date format validation. File: `src/Classes/Services/FormDataProcessor.php:136-148`

3. **`site_id` not type-cast.** `FormDataProcessor` line 34 checks `!is_array($data['site_id'])` but doesn't apply `intval()`, unlike `client_id`. Stored as-is. File: `src/Classes/Services/FormDataProcessor.php:34`

4. **`learner_ids` / `exam_learners` JSON not sanitized per-entry.** Both fields are parsed from JSON (lines 78-95) but individual array entries are not validated as integers or checked against the `learners` table. Malformed or non-existent learner IDs can be stored. File: `src/Classes/Services/FormDataProcessor.php:78-95`

5. **`initial_class_agent` pre-selects from `class_agent` in update view.** `update-class.php` line 1509 uses `$data['class_data']['class_agent']` to set the `selected` attribute on `initial_class_agent`. This is semantically wrong: the "initial" agent dropdown shows the current agent value, not the originally assigned agent. File: `views/classes/components/class-capture-partials/update-class.php:1509`

6. **Agents and supervisors are hardcoded static arrays.** `ClassRepository::getAgents()` (line 419) and `getSupervisors()` (line 443) return static arrays instead of querying the `agents` table. Changes to agent data are not reflected. File: `src/Classes/Repositories/ClassRepository.php:419-452`

7. **`backup_agent_dates[]` not validated as dates.** Backup agent dates are stored without date format validation. File: `src/Classes/Services/FormDataProcessor.php:107`

### Info (Observations)

1. **`order_nr_metadata`** is an orphaned JSONB column in the `classes` table. It has no form field, is not in FormDataProcessor, and is not in the repo whitelist. It may be a vestigial column from a previous design.

2. **`class_agent` vs `initial_class_agent` design.** The DB has two agent columns suggesting a current/initial tracking pattern, but no code path updates `class_agent` when agent replacements are processed. `ClassModel::saveAgentReplacements()` only writes to the `agent_replacements` table, not the `class_agent` column.

3. **Schedule data has comprehensive dual-format support.** `FormDataProcessor::validateScheduleDataV2()` handles both camelCase and snake_case keys, with thorough validation of patterns, days, times, exception dates, and holiday overrides.

4. **QA visits stored in normalized `qa_visits` table** rather than JSONB in the `classes` table. `QAController::saveQAVisits()` deletes all existing visits and re-creates them on each class save (DELETE + INSERT pattern).

5. **`get_class_subjects` registered with nopriv.** This is intentional per comment in code ("public - required for form dropdowns"). It's read-only and low-risk.

6. **Event dates preserve completion metadata.** `FormDataProcessor` (lines 160-183) preserves `completed_by` and `completed_at` from existing event data during re-submission, preventing data loss from the notification dashboard.

---

## Recommendations

1. **Fix `order_nr` reverse path.** Add `'order_nr' => $classModel->getOrderNr()` to the result array in `ClassRepository::getSingleClass()` (after line 624). Add a hidden form field or visible field for `order_nr` if users need to see/edit it.

2. **Set `class_agent` on create.** In `FormDataProcessor::processFormData()`, add logic after line 68: `if (empty($processed['class_agent']) && !empty($processed['initial_class_agent'])) { $processed['class_agent'] = $processed['initial_class_agent']; }` so new classes start with `class_agent` = `initial_class_agent`.

3. **Remove `nopriv` from QA write endpoints.** In `QAController::initialize()`, remove `wp_ajax_nopriv_` registrations for: `create_qa_visit`, `delete_qa_report`, `export_qa_reports`, `submit_qa_question`. Keep `nopriv` only for read-only endpoints if needed.

4. **Sanitize stop/restart dates.** In `FormDataProcessor::processFormData()`, wrap date values with `sanitizeText()`: change `'stop_date' => $stopDates[$i]` to `'stop_date' => self::sanitizeText($stopDates[$i])` and same for restart dates. Add `isValidDate()` check.

5. **Type-cast `site_id`.** In `FormDataProcessor` line 34, add `intval()` or keep as string if site IDs can be non-numeric (verify DB schema).

6. **Sanitize learner/exam IDs.** After JSON decode, filter entries: `$learnerIds = array_filter(array_map('intval', $learnerIds));` to ensure only valid integers are stored.

7. **Fix `initial_class_agent` pre-selection.** In `update-class.php` line 1509, change `$data['class_data']['class_agent']` to `$data['class_data']['initial_class_agent']` so the dropdown shows the actual initial agent, not the current one.

8. **Migrate agents/supervisors to DB.** Replace static arrays in `ClassRepository::getAgents()` and `getSupervisors()` with queries to the `agents` table. This is the single biggest data integrity risk: if an agent is added, renamed, or deactivated, the class form won't reflect it.

9. **Investigate `order_nr_metadata`.** Determine if this column is needed. If not, plan a migration to drop it. If needed, wire it into the form and FormDataProcessor.
