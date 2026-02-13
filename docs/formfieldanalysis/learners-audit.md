# Form Field Wiring Audit: Learners Module

**Audited:** 2026-02-12
**Baseline:** docs/FORM-FIELDS-REFERENCE.md (lines 285-375)
**DB Tables:** `learners`, `learner_lp_tracking`, `learner_portfolios`

## Summary

| Metric | Count |
|--------|-------|
| Fields checked (base learner) | 27 |
| Forward path PASS | 19 |
| Forward path FAIL | 3 |
| Forward path WARN | 5 |
| Reverse path PASS | 25 |
| Reverse path FAIL | 0 |
| Reverse path WARN | 1 |
| Dynamic wiring PASS | 8 |
| Dynamic wiring WARN | 1 |
| Orphaned DB columns | 1 |
| Orphaned form fields | 1 |
| Unused AJAX endpoints | 5 |
| JS selectors targeting missing DOM | 2 |

**Note:** LP Progression fields (11) are managed entirely by `ProgressionService`, not form POST. Portfolio upload (3) is handled by `PortfolioUploadService`. Both subsystems are excluded from the main forward/reverse path audit and noted separately.

---

## Forward Path (Form → DB)

### Base Learner Fields

| Field | HTML | Shortcode POST | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|----------------|----------|----------|----------------|-----------|--------|
| `title` | PASS | PASS | PASS (`sanitize_text_field`) | WARN (no enum) | PASS | PASS | WARN |
| `first_name` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (HTML required) | PASS | PASS | PASS |
| `second_name` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (optional) | PASS | PASS | PASS |
| `initials` | PASS (readonly) | PASS | PASS (`sanitize_text_field`) | PASS (auto-gen) | PASS | PASS | PASS |
| `surname` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (HTML required) | PASS | PASS | PASS |
| `gender` | PASS | PASS | PASS (`sanitize_text_field`) | WARN (no enum) | PASS | PASS | WARN |
| `race` | PASS | PASS | PASS (`sanitize_text_field`) | WARN (no enum) | PASS | PASS | WARN |
| `sa_id_no` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (JS Luhn checksum) | PASS | PASS | PASS |
| `passport_number` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (JS 6-12 alphanum) | PASS | PASS | PASS |
| `tel_number` | PASS | PASS | PASS (`sanitize_text_field`) | WARN (no format check) | PASS | PASS | WARN |
| `alternative_tel_number` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (optional) | PASS | PASS | PASS |
| `email_address` | PASS | PASS | PASS (`sanitize_email`) | PASS (type=email) | PASS | PASS | PASS |
| `date_of_birth` | **FAIL** (not in form) | **FAIL** | N/A | N/A | N/A | **FAIL** (not in DB) | **FAIL** |
| `address_line_1` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `address_line_2` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (optional) | PASS | PASS | PASS |
| `suburb` | **FAIL** (not in form) | **FAIL** | N/A | N/A | PASS (dead code) | **FAIL** (not in DB) | **FAIL** |
| `city_town_id` | PASS | PASS | PASS (`intval`) | PASS (FK dropdown) | PASS | PASS | PASS |
| `province_region_id` | PASS | PASS | PASS (`intval`) | PASS (FK dropdown) | PASS | PASS | PASS |
| `postal_code` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `highest_qualification` | PASS | PASS | WARN (`sanitize_text_field` on FK ID) | PASS (FK validated in repo) | PASS | PASS | WARN |
| `assessment_status` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `placement_assessment_date` | WARN (duplicated) | PASS | PASS (`sanitize_text_field`) | WARN (no date format) | PASS | PASS | WARN |
| `numeracy_level` | PASS | **FAIL** (missing from update) | PASS (`intval`) | PASS | PASS | PASS | **FAIL** |
| `communication_level` | PASS | PASS | PASS (`intval`) | PASS | PASS | PASS | PASS |
| `employment_status` | PASS | PASS | PASS (`filter_var(BOOLEAN)`) | PASS | PASS | PASS (boolean) | PASS |
| `employer_id` | PASS | PASS | PASS (`intval`) | PASS (FK dropdown) | PASS | PASS | PASS |
| `disability_status` | PASS | PASS | PASS (`filter_var(BOOLEAN)`) | PASS | PASS | PASS (boolean) | PASS |

### LP Progression Fields (Managed by ProgressionService)

| Field | Source | Status | Notes |
|-------|--------|--------|-------|
| `learner_id` | Auto-assigned | PASS | FK to `learners.id` |
| `product_id` | Class assignment | PASS | FK to `products.product_id` |
| `class_id` | Class assignment | PASS | FK to `classes.class_id` |
| `hours_trained` | Auto-calculated | PASS | Via `logHours()` |
| `hours_present` | Auto-calculated | PASS | Via `logHours()` |
| `hours_absent` | Auto-calculated | PASS | Via `logHours()` |
| `status` | Service-managed | PASS | `startLearnerProgression()` / `markLPComplete()` |
| `start_date` | Auto: current date | PASS | Set in `startLearnerProgression()` |
| `completion_date` | Auto: on complete | PASS | Set in `markLPComplete()` |
| `notes` | Optional input | PASS | Via AJAX |
| `portfolio_file_path` | File upload | PASS | Via `PortfolioUploadService` |

### Portfolio Upload

| Field | Source | Status | Notes |
|-------|--------|--------|-------|
| `scanned_portfolio[]` | File input | PASS | PDF only, MIME validated server-side |
| `tracking_id` | Hidden input | PASS | Auto-generated PK |
| `nonce` | Hidden | PASS | `learners_nonce` |

---

## Reverse Path (DB → Form)

### Read Path: `LearnerRepository::findByIdWithMappings()` (lines 100-137)

Uses `SELECT learners.*` with JOINs to resolve FKs:
- `highest_qualification` → `learner_qualifications.qualification` (NAME overrides ID)
- `city_town_id` → `locations.town` (town name)
- `province_region_id` → `locations.province` (province name)
- `employer_id` → `employers.employer_name`
- `numeracy_level` → `learner_placement_level.level` (level name overrides ID)
- `communication_level` → `learner_placement_level.level` (level name overrides ID)
- `employment_status` → CASE boolean to "Employed"/"Unemployed"
- `disability_status` → CASE boolean to "Yes"/"No"
- `scanned_portfolio` → Reconstructed from `learner_portfolios` JOIN

| DB Column | Repo Fetch | Controller Pass | Field Mapping | Escaping | Edit Pre-pop | Status |
|-----------|-----------|-----------------|---------------|----------|-------------|--------|
| `title` | PASS (`SELECT *`) | PASS (`toDbArray()`) | PASS (direct) | PASS (`esc_attr` via `===`) | PASS | PASS |
| `first_name` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `second_name` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `initials` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `surname` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `gender` | PASS | PASS | PASS | PASS (`selected()`) | PASS | PASS |
| `race` | PASS | PASS | PASS | PASS (`selected()`) | PASS | PASS |
| `sa_id_no` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `passport_number` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `tel_number` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `alternative_tel_number` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `email_address` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `address_line_1` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `address_line_2` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `city_town_id` | PASS (JOIN) | PASS | PASS | PASS (`selected()`) | PASS | PASS |
| `province_region_id` | PASS (JOIN) | PASS | PASS | PASS (`selected()`) | PASS | PASS |
| `postal_code` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `highest_qualification` | PASS (JOIN→name) | PASS | PASS | PASS (`selected()`) | PASS (name-to-name) | PASS |
| `assessment_status` | PASS | PASS | PASS | PASS (`selected()`) | PASS | PASS |
| `placement_assessment_date` | PASS | PASS | PASS | PASS (`esc_attr`) | PASS | PASS |
| `numeracy_level` | PASS (JOIN→name) | PASS | PASS | PASS (JS template) | PASS (name comparison) | PASS |
| `communication_level` | PASS (JOIN→name) | PASS | PASS | PASS (JS template) | PASS (name comparison) | PASS |
| `employment_status` | PASS (CASE→string) | PASS | PASS | PASS (`selected()`) | **WARN** (see note) | **WARN** |
| `employer_id` | PASS (JOIN) | PASS | PASS | PASS (`selected()`) | PASS | PASS |
| `disability_status` | PASS (CASE→string) | PASS | PASS | PASS (==) | PASS | PASS |
| `scanned_portfolio` | PASS (portfolio JOIN) | PASS | PASS | PASS (`esc_attr`) | PASS (separate display) | PASS |

**employment_status pre-pop note:** The `selected()` comparison works (string "Employed" == "Employed"). However, the initial employer field visibility at `learners-update-shortcode.php:514` uses `!$learner->employment_status` which is always `false` for both "Employed" and "Unemployed" strings (both are truthy in PHP). The employer div is always visible on initial load regardless of status. The JS toggle corrects this on interaction, but initial render is wrong for unemployed learners.

---

## Dynamic Data Wiring

All select/dropdown fields are populated via a single AJAX endpoint: `fetch_learners_dropdown_data`.

| Field | Source Type | AJAX Registered | JS Calls | Cascade Complete | Hidden Updated | Status |
|-------|-----------|-----------------|----------|-----------------|----------------|--------|
| `city_town_id` | DB via AJAX | PASS | PASS | N/A (independent) | N/A | PASS |
| `province_region_id` | DB via AJAX | PASS | PASS | N/A (independent) | N/A | PASS |
| `highest_qualification` | DB via AJAX | PASS | PASS | N/A | N/A | PASS |
| `employer_id` | DB via AJAX | PASS | PASS | N/A | N/A | PASS |
| `numeracy_level` | DB via AJAX | PASS | PASS | N/A | N/A | PASS |
| `communication_level` | DB via AJAX | PASS | PASS | N/A | N/A | PASS |
| `title` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `gender` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `race` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `assessment_status` | Hardcoded options | N/A | N/A | N/A | N/A | PASS |
| `disability_status` | Hardcoded 0/1 | N/A | N/A | N/A | N/A | PASS |
| `employment_status` | Hardcoded 0/1 | N/A | N/A | N/A | N/A | PASS |

### Conditional Toggle Chains

| Trigger Field | Target Fields | JS Handler | Create Form | Update Form | Status |
|--------------|--------------|------------|-------------|-------------|--------|
| `assessment_status` = "Assessed" | `numeracy_level`, `communication_level`, `placement_assessment_date` | `togglePlacementFields()` | PASS (d-none toggle) | PASS (style display toggle) | PASS |
| `employment_status` = "1" | `employer_id` | `toggleFieldVisibility()` | PASS (d-none toggle) | **WARN** (PHP initial state bug) | WARN |
| `id_type` = "sa_id" | `sa_id_no` | `toggleIdFields()` | PASS | PASS (`checked()`) | PASS |
| `id_type` = "passport" | `passport_number` | `toggleIdFields()` | PASS | PASS (`checked()`) | PASS |

### Dropdown Data Endpoint: `fetch_learners_dropdown_data`

**Response structure** (from `LearnerAjaxHandlers::handle_fetch_dropdown_data`):
```json
{
  "cities": [{"id": "location_id", "name": "town"}],
  "provinces": [{"id": "location_id", "name": "province"}],
  "qualifications": [{"id": "id", "name": "qualification"}],
  "employers": [{"id": "employer_id", "name": "employer_name"}],
  "placement_levels": {
    "numeracy_levels": [{"id": "placement_level_id", "name": "level"}],
    "communication_levels": [{"id": "placement_level_id", "name": "level"}]
  }
}
```

**Note on province/city cascade:** Unlike the Clients module, Learners does NOT cascade province → city → suburb. Province and city are independent dropdowns. This is a design difference, not a bug. Both store `location_id` FK values but from different DISTINCT ON queries.

---

## Orphan Detection

### DB Columns Without Form Fields

| Table | Column | Data Type | Possible Reason |
|-------|--------|-----------|----------------|
| `learners` | `scanned_portfolio` | varchar | Managed by portfolio upload subsystem, not direct form input. Denormalized from `learner_portfolios` table. Not an orphan. |

### Form Fields Without DB Columns

| Field | Type | UI-Only? | Notes |
|-------|------|----------|-------|
| `id_type` | radio | Yes | Toggles SA ID vs Passport field visibility. Correctly excluded. |
| `sponsors[]` | select (dynamic) | **No - Orphaned** | Present in both create and update forms. Add/remove UI works. But NO POST processing captures `$_POST['sponsors']`, no DB table exists for sponsors, no backend handler. Completely orphaned feature. |
| `wecoza_learners_form_nonce` | hidden | Yes | CSRF token for create form |
| `wecoza_learners_update_form_nonce` | hidden | Yes | CSRF token for update form |
| `learner_id` | hidden | Yes | Edit mode identifier in update form |

### Phantom Baseline Fields (In Reference Doc But Nowhere Else)

| Field | In Baseline | In Form | In DB | In Model | Notes |
|-------|------------|---------|-------|----------|-------|
| `date_of_birth` | Yes | No | No | No | Listed in baseline doc under "Identification & Contact". Never implemented. No DB column, no form field, no model property. Baseline doc error or planned feature. |
| `suburb` | Yes | No | No | Yes (model + repo whitelist) | Listed in baseline doc under "Address". No DB column in `learners` table. Repo whitelist includes it (dead code - INSERT would fail). Model has property. Value comes from `locations` JOIN on read path only. |

### Unused AJAX Endpoints

| Action | Registered In | Called From JS? | Notes |
|--------|--------------|-----------------|-------|
| `wecoza_get_learner` | `LearnerController::registerHooks()` | **No** | Controller-registered endpoint, no JS calls this action |
| `wecoza_get_learners` | `LearnerController::registerHooks()` | **No** | Controller-registered endpoint, no JS calls this action |
| `wecoza_update_learner` | `LearnerController::registerHooks()` | **No** | Controller-registered endpoint, no JS calls this action |
| `wecoza_delete_learner` | `LearnerController::registerHooks()` | **No** | Controller-registered endpoint, no JS calls this action |
| `wecoza_learner_capture` | `LearnerController::registerShortcodes()` | N/A | Shortcode registered but actual capture uses `[wecoza_learners_form]` (shortcode file) |

### JS Calling Non-Existent AJAX Endpoints

| JS Action | JS File | Registered? | Notes |
|-----------|---------|-------------|-------|
| `update_learner_data` | `learners-app.js:149` | **No** | `#editLearnerForm` submit handler calls `update_learner_data` but no PHP handler registers this action. The registered actions are `update_learner` and `wecoza_update_learner`. |

### JS Selectors Targeting Missing DOM

| Selector | JS File | Found In View? | Notes |
|----------|---------|----------------|-------|
| `#editLearnerForm` | `learners-app.js:143` | **No** | Form submit handler exists but no view contains a form with this ID. Legacy code from edit-modal approach. |
| `#edit-learner-id`, `#edit-first-name`, `#edit-last-name`, etc. | `learners-app.js:96-136` | **No** | `populateEditForm()` targets `edit-*` prefixed IDs that don't exist in any current view. Legacy modal code. |
| `#alert-container` | `learners-app.js:676` | **No** | Alert messages target this container but it doesn't exist in shortcode views. Alerts render but may not be visible. |

### Legacy View File

| File | Status | Notes |
|------|--------|-------|
| `views/learners/learner-form.view.php` | **Unused/Legacy** | Uses different field names (`email`, `phone`, `id_number`, `address`, `city`, `province`, `country`) than the actual shortcode forms. Not referenced by any shortcode or controller. Should be reviewed for removal. |

---

## Dual Processing Paths

The Learners module has **two parallel update paths** which is a source of inconsistency:

### Path 1: Shortcode Form POST (Primary)
```
Form submit → learners-update-shortcode.php POST handler → LearnerController::updateLearner() → LearnerModel::update() → LearnerRepository::update()
```
- **Nonce:** `wecoza_learners_update_form_nonce` / `submit_learners_update_form`
- **Missing:** `numeracy_level` not captured in POST data

### Path 2: AJAX Handler (Secondary)
```
JS AJAX → wp_ajax_update_learner → LearnerAjaxHandlers::handle_update_learner() → LearnerController::updateLearner() → LearnerModel::update() → LearnerRepository::update()
```
- **Nonce:** `learners_nonce` / `nonce`
- **Includes:** All 26 fields including `numeracy_level`

### Path 3: Controller AJAX (Unused)
```
JS AJAX → wp_ajax_wecoza_update_learner → LearnerController::ajaxUpdateLearner() → sanitizeLearnerInput() → updateLearner()
```
- **Nonce:** `learners_nonce_action`
- **Not called by any JS**

---

## Issues

### Critical (Broken Wiring)

1. **`numeracy_level` missing from update shortcode POST processing.** The create shortcode captures `numeracy_level` at `learners-capture-shortcode.php:65` (`'numeracy_level' => intval($_POST['numeracy_level'])`), but the update shortcode at `learners-update-shortcode.php:76-104` omits it entirely. The field exists in the update form HTML (line 484) and is populated via AJAX (line 622), but changes are silently lost on form submit. **Data loss on every update.**
   - File: `src/Learners/Shortcodes/learners-update-shortcode.php:76-104`
   - Fix: Add `'numeracy_level' => intval($_POST['numeracy_level']),` after line 98

2. **`sponsors[]` field is completely orphaned.** The "Sponsored By" UI exists in both create (`learners-capture-shortcode.php:449-467`) and update (`learners-update-shortcode.php:529-548`) forms with working Add/Remove buttons. However: no `$_POST['sponsors']` processing exists in either shortcode, no `sponsors` DB column or table exists, no backend handler processes this data. Users can select sponsors but the data is silently discarded on submit.
   - Files: `learners-capture-shortcode.php:449-467`, `learners-update-shortcode.php:529-548`
   - Fix: Either implement sponsor storage (new table + POST processing) or remove the UI

3. **`date_of_birth` and `suburb` are phantom fields in baseline doc.** `date_of_birth` doesn't exist anywhere (no DB column, no form, no model). `suburb` has no DB column but is in the repo insert whitelist - attempting to INSERT with suburb would cause a PostgreSQL error. The model property and whitelist entry are dead code.
   - Files: `docs/FORM-FIELDS-REFERENCE.md:314` (date_of_birth), `docs/FORM-FIELDS-REFERENCE.md:322` (suburb), `src/Learners/Repositories/LearnerRepository.php:75` (suburb in whitelist)
   - Fix: Remove from baseline doc. Remove `suburb` from `getAllowedInsertColumns()`. Either add DB columns or confirm intentional omission.

### Warning (Missing Best Practice)

1. **`placement_assessment_date` duplicated in create form.** The field appears twice: lines 406-410 and lines 417-421 of `learners-capture-shortcode.php`. Both have `name="placement_assessment_date"`. The second instance will overwrite the first on submit (PHP takes last value for duplicate names). Both are in `.placement_date_outerdiv` containers toggled by assessment status.
   - File: `learners-capture-shortcode.php:406-421`
   - Fix: Remove the duplicate field block at lines 414-422

2. **`nopriv` AJAX endpoints expose learner data.** `fetch_learners_data` and `fetch_learners_dropdown_data` are registered with `wp_ajax_nopriv_*` hooks (`LearnerAjaxHandlers.php:293,296`). While the site requires login (mitigating risk), these endpoints would serve data to unauthenticated requests if the login requirement were ever removed. The `verify_learner_access(false)` still checks nonce but skips capability check.
   - File: `src/Learners/Ajax/LearnerAjaxHandlers.php:292-296`
   - Fix: Remove `nopriv` registrations since site requires authentication

3. **`employment_status` initial visibility bug in update form.** At `learners-update-shortcode.php:514`, `!$learner->employment_status` evaluates `false` for both "Employed" and "Unemployed" (both are non-empty strings from the CASE expression). The employer div is always visible on initial page load, even for unemployed learners. The JS toggle corrects this on user interaction but the initial render is wrong.
   - File: `src/Learners/Shortcodes/learners-update-shortcode.php:514`
   - Fix: Change to `$learner->employment_status !== 'Employed'` or check the raw boolean value

4. **`highest_qualification` uses `sanitize_text_field()` on integer FK value.** Both shortcodes apply `sanitize_text_field()` to `$_POST['highest_qualification']` which submits as an integer ID from the dropdown. While PostgreSQL handles the string-to-int cast, the AJAX handler correctly types it as `int`. The shortcode path should use `intval()`.
   - Files: `learners-capture-shortcode.php:62`, `learners-update-shortcode.php:95`
   - Fix: Change to `intval($_POST['highest_qualification'])`

5. **`placement_assessment_date` lacks date format validation.** Both shortcodes use `sanitize_text_field()` without verifying the value is a valid date. Invalid date strings could reach the DB.
   - Files: `learners-capture-shortcode.php:64`, `learners-update-shortcode.php:97`
   - Fix: Add date validation or use a dedicated date sanitizer

6. **Template literal XSS risk in JS alert messages.** `learners-app.js:498` uses `${response.data}` inside template literals injected via `.html()`. If server error messages contain HTML, this is an XSS vector.
   - File: `assets/js/learners/learners-app.js:486-510`
   - Fix: Use `.text()` for dynamic content or escape before insertion

7. **JS calls non-existent AJAX action `update_learner_data`.** The `#editLearnerForm` submit handler in `learners-app.js:149` sends `action: 'update_learner_data'` but no PHP handler registers this action. The edit modal form submission silently fails.
   - File: `assets/js/learners/learners-app.js:143-179`
   - Fix: Either remove the dead code or register a matching handler

### Info (Observations)

1. **LP Progression fully decoupled from learner form.** All 11 progression fields are managed by `ProgressionService` via dedicated methods (`startLearnerProgression()`, `markLPComplete()`, `logHours()`), not through form POST. This is clean architecture.

2. **Portfolio upload uses dual storage.** Files are stored in `learner_portfolios` table (normalized) and denormalized as comma-separated paths in `learners.scanned_portfolio`. The `savePortfolios()` method maintains both. The denormalized column enables efficient listing without JOIN.

3. **`learner-form.view.php` appears to be a legacy/unused template.** It uses different field names (`email`, `phone`, `id_number`, `address`, `city`, `province`, `country`) than the actual shortcode forms. No shortcode or controller references it. The actual forms are in the shortcode files.

4. **Dual controller pattern creates confusion.** `LearnerController` registers 4 AJAX actions (`wecoza_get_learner`, `wecoza_get_learners`, `wecoza_update_learner`, `wecoza_delete_learner`) that are never called by any JS. `LearnerAjaxHandlers` registers 5 different actions that ARE called. Both converge on `LearnerController` methods. The controller-registered actions are dead code.

5. **Nonce names differ between shortcodes and AJAX.** Create shortcode uses `wecoza_learners_form_nonce`/`submit_learners_form`. Update shortcode uses `wecoza_learners_update_form_nonce`/`submit_learners_update_form`. AJAX handlers use `learners_nonce`/`nonce`. Controller AJAX uses `learners_nonce_action`. All four are valid but the inconsistency adds maintenance burden.

6. **`suburb` resolves from `locations` JOIN.** While there's no `suburb` column in the `learners` table, the `baseQueryWithMappings()` SQL at `LearnerRepository.php:113` provides `locations.suburb AS suburb` via the `city_town_id` FK JOIN. Display components correctly show the suburb from the location record.

7. **`scanned_portfolio` column is denormalized.** The column stores comma-separated file paths and is maintained by `savePortfolios()` and `deletePortfolio()` methods, not by the main form POST processing. In both shortcodes, the form POST sets `scanned_portfolio` to the existing value (create: empty string, update: `$learner->scanned_portfolio`).

---

## Recommendations

1. **[CRITICAL] Add `numeracy_level` to update shortcode POST data.**
   Add `'numeracy_level' => intval($_POST['numeracy_level']),` after line 98 of `src/Learners/Shortcodes/learners-update-shortcode.php`.

2. **[CRITICAL] Resolve `sponsors[]` orphan.** Either implement sponsor persistence (create `learner_sponsors` table, add POST processing) or remove the sponsor UI from both forms to prevent user confusion.

3. **[HIGH] Fix baseline doc.** Remove `date_of_birth` and `suburb` from `docs/FORM-FIELDS-REFERENCE.md`. Remove `suburb` from `LearnerRepository::getAllowedInsertColumns()`.

4. **[HIGH] Fix employer visibility initial state.** Change `learners-update-shortcode.php:514` from `!$learner->employment_status` to `$learner->employment_status !== 'Employed'`.

5. **[MEDIUM] Remove `nopriv` AJAX hooks.** Remove lines 293 and 296 from `LearnerAjaxHandlers.php`.

6. **[MEDIUM] Remove duplicate `placement_assessment_date` field.** Delete lines 414-422 from `learners-capture-shortcode.php`.

7. **[MEDIUM] Clean up dead AJAX code.** Remove unused controller AJAX registrations from `LearnerController::registerHooks()` (lines 38-41). Remove dead `#editLearnerForm` handler and `populateEditForm()` from `learners-app.js` (lines 73-179). Consider removing `learner-form.view.php`.

8. **[LOW] Use `intval()` for `highest_qualification` in shortcodes.** Change `sanitize_text_field($_POST['highest_qualification'])` to `intval($_POST['highest_qualification'])` in both shortcode files.

9. **[LOW] Add date validation for `placement_assessment_date`.** Add format check (YYYY-MM-DD) before passing to DB.

10. **[LOW] Fix template literal XSS in JS alerts.** Use `.text()` instead of `.html()` for dynamic content, or escape user-controllable strings.
