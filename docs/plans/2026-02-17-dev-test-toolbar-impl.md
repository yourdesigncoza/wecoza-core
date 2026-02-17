# Dev Test Toolbar Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a dev toolbar that auto-fills WeCoza forms with realistic SA test data and can wipe transactional DB data.

**Architecture:** JS toolbar + form fillers loaded only when WP_DEBUG=true. PHP controller for script enqueue + AJAX wipe handler. See design doc: `docs/plans/2026-02-17-dev-test-toolbar-design.md`

**Tech Stack:** Vanilla JS (no build step), PHP 8+, PostgreSQL, WordPress AJAX

---

### Task 1: Create directory structure + data pools

**Files:**
- Create: `assets/js/dev/form-fillers/data-pools.js`

SA names, streets, banks, SETAs, branch codes, postal codes, qualifications, subjects. Arrays only — no logic.

### Task 2: Data generators

**Files:**
- Create: `assets/js/dev/form-fillers/generators.js`

Functions: `generateSAID(dob, gender)`, `generatePhone()`, `generateEmail(first, last)`, `generateDate(minYearsAgo, maxYearsAgo)`, `pickRandom(array)`, `pickRandomOption(selectEl)`, `setFieldValue(id, value)`, `setSelectValue(id, value)`, `setRadioValue(name, value)`, `setCheckbox(id, checked)`, `waitForAjaxIdle(timeout)`.

### Task 3: PHP backend — DevToolbarController

**Files:**
- Create: `src/Dev/DevToolbarController.php`

- Hooks into `wp_enqueue_scripts` at priority 999
- Only enqueues when `WP_DEBUG === true`
- Enqueues all JS files from `assets/js/dev/` in correct order
- Localizes `wecoza_dev_toolbar` with `ajaxUrl` and `nonce`

### Task 4: PHP backend — WipeDataHandler

**Files:**
- Create: `src/Dev/WipeDataHandler.php`

- AJAX action `wecoza_dev_wipe_data`
- Checks: `WP_DEBUG`, nonce, `manage_options` capability
- Explicit allowlist of 33 transactional tables
- `TRUNCATE table RESTART IDENTITY CASCADE` for each
- Clears WP transients with `_transient_wecoza_` prefix
- Returns JSON with results

### Task 5: Register Dev module in plugin bootstrap

**Files:**
- Modify: `wecoza-core.php`

Add conditional loading of Dev module when `WP_DEBUG === true`.

### Task 6: Toolbar UI

**Files:**
- Create: `assets/js/dev/dev-toolbar.js`

- Creates floating toolbar DOM element
- Detects form on page via selector map
- Fill button: calls appropriate filler
- Fill+Submit button: calls filler then clicks submit
- Wipe button: confirm dialog → AJAX call → reload
- Collapse/expand toggle

### Task 7: Location form filler

**Files:**
- Create: `assets/js/dev/form-fillers/location-filler.js`

Fields: `street_address`, `suburb`, `town`, `province` (select), `postal_code`, `latitude`, `longitude`.
Note: Submit button `#submit_location_btn` is hidden until duplicate check. Toolbar must click `#check_duplicate_btn` first, wait, then submit appears.

### Task 8: Client form filler

**Files:**
- Create: `assets/js/dev/form-fillers/client-filler.js`

Form: `#clients-form`. Sync cascading: `.js-province-select` → `.js-town-select` → `.js-suburb-select` (pre-loaded hierarchy, trigger change events sequentially).
Fields: `client_name`, `company_registration_nr`, `site_name`, contact fields, `seta`, `client_status`, dates.

### Task 9: Learner form filler

**Files:**
- Create: `assets/js/dev/form-fillers/learner-filler.js`

Form: `#learners-form`. AJAX-loaded dropdowns — must wait for `fetch_learners_dropdown_data` to complete before picking values.
Fields: personal info, ID type radio, address, AJAX dropdowns (`city_town_id`, `province_region_id`, `highest_qualification`), conditional assessment fields, employment status toggle.

### Task 10: Agent form filler

**Files:**
- Create: `assets/js/dev/form-fillers/agent-filler.js`

Form: `#agents-form`. Static province dropdown, text address fields.
Fields: personal info, ID type radio, contact, SACE details, address, preferred working areas (3 selects), phase/subjects, quantum scores, banking details, dates.
Note: File uploads skipped (criminal record, signed agreement).

### Task 11: Class form filler

**Files:**
- Create: `assets/js/dev/form-fillers/class-filler.js`

Form: `#classes-form`. Most complex form.
Async: `client_id` → triggers AJAX for `site_id` options. `class_type` → triggers load of `class_subject`.
Fields: client/site, class type/subject, start date, schedule pattern, day checkboxes, time inputs.
Note: Many sections (learners, QA, notes, agents) are post-creation. Focus on fields needed for initial create.
