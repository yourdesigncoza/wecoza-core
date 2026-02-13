# Form Field Wiring Audit: Clients Module

**Audited:** 2026-02-12
**Baseline:** docs/FORM-FIELDS-REFERENCE.md
**DB Tables:** `clients`, `locations`

## Summary

| Metric | Count |
|--------|-------|
| Fields checked | 33 |
| Forward path PASS | 21 |
| Forward path WARN | 1 |
| Forward path FAIL | 0 |
| Reverse path PASS | 19 |
| Reverse path FAIL | 0 |
| Dynamic wiring PASS | 6 |
| Dynamic wiring FAIL | 0 |
| Orphaned DB columns | 0 |
| Orphaned form fields | 0 |
| Unused AJAX endpoints | 7 |

## Forward Path (Form -> DB)

### Client Capture/Update Form

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `id` | PASS | PASS | PASS (`intval`) | N/A (PK) | N/A (PK) | PASS (`client_id`) | PASS |
| `head_site_id` | PASS | PASS | PASS (`(int)`) | N/A | N/A (sites tbl) | N/A (sites tbl) | PASS |
| `client_name` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (config rules) | PASS | PASS | PASS |
| `company_registration_nr` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (required, unique) | PASS | PASS (mapped to `company_registration_number`) | PASS |
| `site_name` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (site validation) | N/A (sites tbl) | N/A (sites tbl) | PASS |
| `is_sub_client` | PASS | PASS | PASS (boolean check) | N/A (UI toggle) | N/A (UI-only) | N/A (UI-only) | PASS |
| `main_client_id` | PASS | PASS | PASS (`(int)`) | PASS (extensive) | PASS | PASS | PASS |
| `client_province` | PASS | N/A | N/A | N/A | N/A | N/A (UI cascade) | PASS |
| `client_town` | PASS | N/A | N/A | N/A | N/A | N/A (UI cascade) | PASS |
| `client_town_id` | PASS | PASS | PASS (`(int)`) | PASS (site validation) | PASS (dead code) | **WARN** (column missing) | **WARN** |
| `client_postal_code` | PASS | N/A | N/A | N/A | N/A | N/A (UI-only) | PASS |
| `client_suburb` | PASS | N/A | N/A | N/A | N/A | N/A (UI cascade) | PASS |
| `client_town_name` | PASS | N/A | N/A | N/A | N/A | N/A (UI cascade) | PASS |
| `client_street_address` | PASS | N/A | N/A | N/A | N/A | N/A (UI-only) | PASS |
| `contact_person` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `contact_person_email` | PASS | PASS | PASS (`sanitize_email`) | PASS (email) | PASS | PASS | PASS |
| `contact_person_cellphone` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `contact_person_tel` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `contact_person_position` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `seta` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (`in` validation) | PASS | PASS | PASS |
| `client_status` | PASS | PASS | PASS (`sanitize_text_field`) | PASS | PASS | PASS | PASS |
| `financial_year_end` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (date) | PASS | PASS | PASS |
| `bbbee_verification_date` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (date) | PASS | PASS | PASS |

### Location Capture/Edit Form

| Field | HTML | Controller | Sanitize | Validate | Repo Whitelist | DB Column | Status |
|-------|------|-----------|----------|----------|----------------|-----------|--------|
| `location_id` | PASS | PASS | PASS (`(int)`) | N/A (PK) | N/A (PK) | PASS | PASS |
| `wecoza_clients_google_address_search` | PASS | N/A | N/A | N/A | N/A | N/A (UI-only) | PASS |
| `street_address` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (required, max 200) | PASS | PASS | PASS |
| `suburb` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (required, max 50) | PASS | PASS | PASS |
| `town` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (required, max 50) | PASS | PASS | PASS |
| `province` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (required, in options) | PASS | PASS | PASS |
| `postal_code` | PASS | PASS | PASS (`sanitize_text_field`) | PASS (required, max 10) | PASS | PASS | PASS |
| `latitude` | PASS | PASS | PASS (`sanitize_text_field` + comma replace) | PASS (range -90..90) | PASS | PASS (`numeric`) | PASS |
| `longitude` | PASS | PASS | PASS (`sanitize_text_field` + comma replace) | PASS (range -180..180) | PASS | PASS (`numeric`) | PASS |
| `wecoza_locations_form_nonce` | PASS | PASS | PASS (`wp_verify_nonce`) | N/A | N/A | N/A (CSRF) | PASS |

## Reverse Path (DB -> Form)

### Clients Table

| DB Column | Repo Fetch | Controller Pass | Field Mapping | Escaping | Edit Pre-pop | Status |
|-----------|-----------|-----------------|---------------|----------|-------------|--------|
| `client_name` | PASS (`SELECT *`) | PASS (`wecoza_view`) | PASS (direct) | PASS (`esc_attr` via ViewHelpers) | PASS | PASS |
| `company_registration_number` | PASS (`SELECT *`) | PASS | PASS (`columnCandidates` maps to `company_registration_nr`) | PASS | PASS | PASS |
| `seta` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `client_status` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `financial_year_end` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `bbbee_verification_date` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `main_client_id` | PASS | PASS | PASS (direct) | PASS | PASS (`selected()`) | PASS |
| `contact_person` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `contact_person_email` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `contact_person_cellphone` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `contact_person_tel` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `contact_person_position` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |

### Locations Table

| DB Column | Repo Fetch | Controller Pass | Field Mapping | Escaping | Edit Pre-pop | Status |
|-----------|-----------|-----------------|---------------|----------|-------------|--------|
| `street_address` | PASS (explicit SELECT) | PASS (`wecoza_view`) | PASS (direct) | PASS (`esc_attr` via ViewHelpers) | PASS | PASS |
| `suburb` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `town` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `province` | PASS | PASS | PASS (direct) | PASS | PASS (`selected()`) | PASS |
| `postal_code` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `latitude` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |
| `longitude` | PASS | PASS | PASS (direct) | PASS | PASS | PASS |

## Dynamic Data Wiring

| Field | Source Type | AJAX Registered | JS Calls | Cascade Complete | Hidden Updated | Status |
|-------|-----------|-----------------|----------|-----------------|----------------|--------|
| `client_province` | DB (location hierarchy) | PASS (`wecoza_get_locations`) | PASS (lazy load) | PASS (province->town) | N/A | PASS |
| `client_town` | DB (cascaded from province) | N/A (same endpoint) | N/A | PASS (town->suburb) | PASS (`js-town-hidden`) | PASS |
| `client_town_id` | DB (cascaded from town) | N/A | N/A | PASS (suburb->address) | PASS (`js-suburb-hidden`, postal, address) | PASS |
| `main_client_id` | DB (`getMainClients()`) | PASS (`wecoza_get_main_clients`) | N/A (server-rendered) | N/A | N/A | PASS |
| `seta` | Config (hardcoded array) | N/A | N/A | N/A | N/A | PASS |
| `client_status` | Config (hardcoded array) | N/A | N/A | N/A | N/A | PASS |
| `province` (location form) | Config (hardcoded array) | N/A | N/A | N/A | N/A | PASS |

## Orphan Detection

### DB Columns Without Form Fields

| Table | Column | Data Type | Possible Reason |
|-------|--------|-----------|----------------|
| `clients` | `deleted_at` | timestamp | System column - soft delete flag, managed programmatically |

### Form Fields Without DB Columns

| Field | Type | UI-Only? | Notes |
|-------|------|----------|-------|
| `is_sub_client` | checkbox | Yes | Toggles `main_client_id` visibility |
| `client_province` | select | Yes | Cascade helper -> resolves to `client_town_id` |
| `client_town` | select | Yes | Cascade helper -> resolves to `client_town_id` |
| `client_postal_code` | text | Yes | Auto-populated from location, readonly |
| `client_suburb` | hidden | Yes | Auto-populated from location cascade |
| `client_town_name` | hidden | Yes | Auto-populated from location cascade |
| `client_street_address` | text | Yes | Auto-populated from location, stored in locations table |
| `head_site_id` | hidden | Yes | Routed to sites table via SitesModel |
| `site_name` | text | Yes | Routed to sites table via SitesModel |
| `wecoza_clients_google_address_search` | text | Yes | Google Places autocomplete helper |
| `wecoza_locations_form_nonce` | hidden | Yes | CSRF nonce |

### Unused AJAX Endpoints

| Action | Registered In | Called From JS? |
|--------|--------------|-----------------|
| `wecoza_save_location` | `ClientAjaxHandlers:43` | No - returns "Not implemented yet" |
| `wecoza_get_main_clients` | `ClientAjaxHandlers:39` | No JS calls - server-rendered only |
| `wecoza_save_sub_site` | `ClientAjaxHandlers:47` | No JS calls found |
| `wecoza_get_head_sites` | `ClientAjaxHandlers:48` | No JS calls found |
| `wecoza_get_sub_sites` | `ClientAjaxHandlers:49` | No JS calls found |
| `wecoza_delete_sub_site` | `ClientAjaxHandlers:50` | No JS calls found |
| `wecoza_get_sites_hierarchy` | `ClientAjaxHandlers:51` | No JS calls found |

### JS Selectors Targeting Missing DOM

| Selector | JS File | Found In View? |
|----------|---------|----------------|
| (none found) | - | - |

## Issues

### Critical (Broken Wiring)

None found.

### Warning (Missing Best Practice)

1. **`client_town_id` phantom column** - `ClientRepository::getAllowedInsertColumns()` and `getAllowedUpdateColumns()` include `client_town_id`, but this column does NOT exist in the `clients` table. The `ClientsModel::resolveColumn()` returns null for it, so `prepareDataForSave()` silently drops it. Data IS saved correctly via `SitesModel::saveHeadSite()` using the `place_id` parameter. The repo whitelist entry is dead code.
   Files: `src/Clients/Repositories/ClientRepository.php:82,108`

2. **Duplicate AJAX on update form** - `client-update-form.view.php` contains inline JS (`line 407`) that intercepts form submit via `addEventListener('submit', ...)` and sends a `fetch()` to `admin-ajax.php` WITHOUT the `action` parameter. Simultaneously, `client-capture.js` (loaded for update form too) also intercepts the submit and sends a proper AJAX call WITH `action=wecoza_save_client`. Both handlers fire on every submit. The inline fetch silently fails (no action handler), while the capture.js call succeeds. Result: every update triggers two AJAX requests, one wasted.
   Files: `views/clients/components/client-update-form.view.php:407-445`, `assets/js/clients/client-capture.js:500-568`

3. **Nonce action mismatch on update form** - Update form generates nonce with `wp_nonce_field('wecoza_clients_ajax', 'nonce')` (action: `wecoza_clients_ajax`), but the non-AJAX fallback in `updateClientShortcode()` verifies against `clients_nonce_action`. These are different nonce actions, so non-AJAX form submission would always fail the security check. Not exploitable since the update form always uses AJAX, but the fallback path is broken.
   Files: `views/clients/components/client-update-form.view.php:122`, `src/Clients/Controllers/ClientsController.php:407`

4. **Capture form missing nonce for non-AJAX fallback** - `client-capture-form.view.php` does not include a `wp_nonce_field()`. The nonce is only appended by `client-capture.js` during AJAX submission. If JavaScript is disabled, the non-AJAX fallback in `captureClientShortcode()` would fail the nonce check at line 215 because no `$_POST['nonce']` exists.
   Files: `views/clients/components/client-capture-form.view.php`, `src/Clients/Controllers/ClientsController.php:214-215`

5. **7 unused AJAX endpoints** - `wecoza_save_location` (not implemented), `wecoza_save_sub_site`, `wecoza_get_head_sites`, `wecoza_get_sub_sites`, `wecoza_delete_sub_site`, `wecoza_get_sites_hierarchy`, and `wecoza_get_main_clients` are registered but never called from any JS file. These may be intended for future sub-site management UI.
   File: `src/Clients/Ajax/ClientAjaxHandlers.php:39-51`

### Info (Observations)

1. **Column name mapping handled gracefully** - The `clients.company_registration_number` DB column differs from the form field name `company_registration_nr`. The `ClientsModel::columnCandidates` array resolves this at runtime by checking both candidate names against the actual schema. This is robust but adds slight complexity.

2. **Location data architecture** - Client location is stored indirectly: `client_town_id` in the form maps to `place_id` in the sites table, which is an FK to `locations.location_id`. The `SitesModel::hydrateClients()` method enriches client data with location details on read. This means the clients table has no direct address columns.

3. **Soft delete via `deleted_at`** - Clients table supports soft deletion. The `deleted_at` column is system-managed and correctly excluded from all forms. All queries filter `WHERE deleted_at IS NULL` by default.

4. **ViewHelpers escaping is thorough** - All form output goes through `ViewHelpers::renderField()` which uses `esc_attr()` for values, `esc_html()` for labels, and `selected()` for select options. No XSS vectors found.

5. **Location duplicate check** - The location form requires a duplicate check before the submit button is revealed (`d-none` by default). This prevents accidental duplicate location entries.

## Recommendations

1. **Remove inline submit handler from update form** - Delete the `<script>` block in `views/clients/components/client-update-form.view.php:401-607` that handles form submission. The `client-capture.js` already handles AJAX submission correctly for both capture and update forms. This eliminates the duplicate AJAX call and the nonce mismatch.

2. **Add nonce to capture form** - Add `<?php wp_nonce_field('clients_nonce_action', 'nonce'); ?>` to `views/clients/components/client-capture-form.view.php` inside the `<form>` tag, for non-AJAX fallback compatibility.

3. **Clean up `client_town_id` from repo whitelist** - Remove `client_town_id` from `ClientRepository::getAllowedInsertColumns()` and `getAllowedUpdateColumns()` since the column doesn't exist. Alternatively, add the column to the clients table if direct FK storage is desired.

4. **Audit unused AJAX endpoints** - Decide whether the 7 unused endpoints (sub-site management + save location) should be removed or if they're planned for upcoming features. If unused, removing them reduces attack surface.

5. **Unify nonce action strings** - Standardize on a single nonce action string (`clients_nonce_action`) across all client form views and controllers to prevent future mismatches.
