# Phase 34: Clients Module Fixes - Research

**Researched:** 2026-02-13
**Domain:** Form submission integrity, AJAX security, nonce unification, dead code removal
**Confidence:** HIGH

## Summary

Phase 34 addresses 5 form field wiring issues in the Clients module identified by comprehensive audit (`docs/formfieldanalysis/clients-audit.md`). The fixes target three key areas: (1) duplicate AJAX submissions on update form where inline JS handler conflicts with external `client-capture.js`, (2) nonce action inconsistency across forms (`wecoza_clients_ajax` vs `clients_nonce_action`), (3) missing nonce field in capture form preventing non-AJAX fallback, (4) phantom column `client_town_id` in repository whitelists that doesn't exist in database schema, and (5) 7 unused AJAX endpoints registered but never called from JavaScript.

All issues are surgical fixes with exact file paths and line numbers from the audit. No architectural changes needed - this is pure cleanup to eliminate wasted AJAX calls, unify security patterns, and reduce attack surface.

**Primary recommendation:** Remove inline submit handler from update form (lines 401-607), add `wp_nonce_field()` to capture form, unify all nonce actions to `clients_nonce_action`, remove `client_town_id` from repository whitelists, and delete 7 unused AJAX endpoints.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress | 6.0+ | CMS platform | Plugin target platform |
| PHP | 8.0+ | Server language | Required for typed properties, match expressions |
| PostgreSQL | 13+ | Database | Project standard (not MySQL) |
| jQuery | WP bundled | DOM/AJAX | WordPress standard for admin area |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress AJAX API | Core | AJAX routing | All async form submissions |
| WordPress Nonce API | Core | CSRF protection | Every form submission (AJAX and non-AJAX) |
| WordPress Sanitization API | Core | Input sanitization | All user input processing |
| Fetch API | Browser native | AJAX requests | Modern alternative to jQuery.ajax (used in inline handler) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Unified JS handler (client-capture.js) | Inline handlers per form | Inline handlers cause duplication, harder to maintain |
| Single nonce action | Per-form nonce actions | Single action simplifies, audit recommends unification |
| Delete unused endpoints | Comment them out | Deletion reduces attack surface (audit recommendation) |

**Installation:**

No new dependencies. All fixes use existing WordPress core APIs and plugin infrastructure.

## Architecture Patterns

### Existing Project Structure (Clients Module)

```
src/Clients/
├── Ajax/                  # ClientAjaxHandlers.php (17 endpoints, 7 unused)
├── Controllers/           # ClientsController, LocationsController
├── Helpers/               # ViewHelpers (form rendering utilities)
├── Models/                # ClientsModel, LocationsModel, SitesModel, ClientCommunicationsModel
└── Repositories/          # ClientRepository, LocationRepository (CRUD + column whitelisting)

views/clients/
├── components/
│   ├── client-capture-form.view.php    # Create form (MISSING nonce field)
│   └── client-update-form.view.php     # Edit form (has inline JS duplicate handler)

assets/js/clients/
├── client-capture.js      # Handles BOTH create and update forms via jQuery
├── client-search.js
├── clients-display.js
├── clients-table.js
├── location-capture.js
└── locations-list.js
```

### Pattern 1: Unified Form Handler (Recommended)

**What:** Single JavaScript file (`client-capture.js`) handles both create and update forms via shared submit handler.

**When to use:** When create and update forms have identical field structure and submission logic.

**Current Implementation:**
```javascript
// Source: assets/js/clients/client-capture.js:500-568
form.on('submit', function (event) {
    if (!form[0].checkValidity()) {
        form.addClass('was-validated');
        return;
    }

    event.preventDefault();
    form.addClass('was-validated');

    var formData = new FormData(form[0]);
    formData.append('action', config.actions.save);  // 'wecoza_save_client'
    formData.append('nonce', config.nonce);          // From wp_localize_script

    setSubmittingState(true);

    $.ajax({
        url: config.ajaxUrl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json'
    }).done(function (response) {
        // Handle success/error
    });
});
```

**Problem (CLT-01):** Update form has BOTH this handler AND inline Fetch API handler (lines 407-445), causing duplicate AJAX calls on every submit.

**Fix:** Remove inline handler from `client-update-form.view.php` lines 401-607 (entire `<script>` block).

### Pattern 2: Non-AJAX Fallback Support

**What:** Forms include `wp_nonce_field()` in HTML for server-side POST processing when JavaScript disabled.

**When to use:** Always. Defense-in-depth for accessibility and graceful degradation.

**Correct Implementation (Update Form):**
```php
// Source: views/clients/components/client-update-form.view.php:122
<form id="clients-form" class="needs-validation" method="POST">
    <?php wp_nonce_field('wecoza_clients_ajax', 'nonce'); ?>
    <!-- form fields -->
</form>
```

**Controller Fallback Handler:**
```php
// Source: src/Clients/Controllers/ClientsController.php:406-408
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonce']) && !wp_doing_ajax()) {
    if (!wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')) {
        $errors[] = __('Security check failed. Please try again.', 'wecoza-core');
    }
    // ... process form
}
```

**Problem (CLT-02 + CLT-04):**
1. Capture form MISSING `wp_nonce_field()` entirely
2. Update form uses `'wecoza_clients_ajax'` nonce action
3. Controller expects `'clients_nonce_action'` nonce action
4. Mismatch = fallback broken

**Fix:**
- Add `<?php wp_nonce_field('clients_nonce_action', 'nonce'); ?>` to capture form after `<form>` tag
- Change update form line 122 from `'wecoza_clients_ajax'` to `'clients_nonce_action'`

### Pattern 3: Repository Column Whitelisting (Security)

**What:** Repository methods (`getAllowedInsertColumns()`, `getAllowedUpdateColumns()`) define which form fields can reach database, preventing mass assignment vulnerabilities.

**When to use:** Always. Every repository CRUD operation filters data through whitelists.

**Implementation:**
```php
// Source: src/Clients/Repositories/ClientRepository.php:72-90
protected function getAllowedInsertColumns(): array
{
    return [
        'client_name',
        'company_registration_nr',
        'seta',
        'client_status',
        'financial_year_end',
        'bbbee_verification_date',
        'main_client_id',
        'client_town_id',              // DEAD CODE - CLT-03
        'contact_person',
        'contact_person_email',
        'contact_person_cellphone',
        'contact_person_tel',
        'contact_person_position',
        'created_at',
        'updated_at',
    ];
}
```

**Problem (CLT-03):** `client_town_id` listed in whitelist but column does NOT exist in `clients` table schema:

```sql
-- Source: schema/wecoza_db_schema_bu_feb_12_2.sql
CREATE TABLE public.clients (
    client_id integer NOT NULL,
    client_name character varying(100),
    company_registration_number character varying(50),
    seta character varying(100),
    client_status character varying(50),
    financial_year_end date,
    bbbee_verification_date date,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    main_client_id integer,
    contact_person character varying(100),
    contact_person_email character varying(100),
    contact_person_cellphone character varying(20),
    contact_person_tel character varying(20),
    contact_person_position character varying(50),
    deleted_at timestamp without time zone
);
-- NO client_town_id column
```

**Explanation:** Client location stored in `sites` table via `place_id` FK to `locations` table. The `SitesModel::saveHeadSite()` handles location storage, not `ClientRepository`. The `client_town_id` in whitelist is orphaned dead code.

**Fix:** Remove `'client_town_id',` from lines 82 (insert) and 108 (update) of `ClientRepository.php`.

### Pattern 4: AJAX Endpoint Registration (Attack Surface)

**What:** `wp_ajax_*` hooks register server-side handlers for async requests.

**When to use:** Only register endpoints that JavaScript actually calls.

**Current Registration:**
```php
// Source: src/Clients/Ajax/ClientAjaxHandlers.php:29-52
protected function registerHandlers()
{
    // ACTIVE endpoints (called from JS)
    add_action('wp_ajax_wecoza_save_client', [$this, 'saveClient']);
    add_action('wp_ajax_wecoza_get_client', [$this, 'getClient']);
    add_action('wp_ajax_wecoza_delete_client', [$this, 'deleteClient']);
    add_action('wp_ajax_wecoza_search_clients', [$this, 'searchClients']);
    add_action('wp_ajax_wecoza_get_locations', [$this, 'getLocations']);
    add_action('wp_ajax_wecoza_check_location_duplicates', [$this, 'checkLocationDuplicates']);

    // UNUSED endpoints (NO JS calls found) - CLT-05
    add_action('wp_ajax_wecoza_get_main_clients', [$this, 'getMainClients']);          // Line 39
    add_action('wp_ajax_wecoza_save_location', [$this, 'saveLocation']);               // Line 43
    add_action('wp_ajax_wecoza_save_sub_site', [$this, 'saveSubSite']);               // Line 47
    add_action('wp_ajax_wecoza_get_head_sites', [$this, 'getHeadSites']);             // Line 48
    add_action('wp_ajax_wecoza_get_sub_sites', [$this, 'getSubSites']);               // Line 49
    add_action('wp_ajax_wecoza_delete_sub_site', [$this, 'deleteSubSite']);           // Line 50
    add_action('wp_ajax_wecoza_get_sites_hierarchy', [$this, 'getSitesHierarchy']);   // Line 51
}
```

**Verification (grep results):** Searched all `assets/js/clients/*.js` for action names:
```bash
grep -r "wecoza_save_location\|wecoza_save_sub_site\|wecoza_get_head_sites\|wecoza_get_sub_sites\|wecoza_delete_sub_site\|wecoza_get_sites_hierarchy\|wecoza_get_main_clients" assets/js/clients/
# Result: No files found
```

**Analysis:**
- `wecoza_save_location` (line 43): Handler returns `"Not implemented yet"` string literal
- `wecoza_get_main_clients` (line 39): Dropdown populated server-side in controller, not via AJAX
- Sub-site endpoints (lines 47-51): Feature planned but no UI implemented

**Fix (CLT-05):** Delete 7 endpoint registrations (lines 39, 43, 47-51) and their handler methods (lines 369-382, 402-412, 444-481, 484-502, 505-523, 526-550, 553-571).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSRF protection | Custom token system | WordPress nonces (`wp_nonce_field()`, `wp_verify_nonce()`) | Battle-tested, handles expiration, integrates with WP session |
| Form submission | Multiple handlers per form | Single unified handler (client-capture.js) | Eliminates race conditions, reduces duplicate code |
| Input sanitization | Regex validation | `sanitize_text_field()`, `sanitize_email()` | Handles edge cases (null bytes, encoding attacks, etc.) |
| AJAX routing | Direct POST to PHP file | WordPress AJAX API (`wp_ajax_*` hooks) | Handles authentication, nonces, JSON encoding automatically |

**Key insight:** Duplication (inline submit handlers, multiple nonce actions) creates maintenance burden and inconsistency. Unification is always preferred when forms have identical submission logic.

## Common Pitfalls

### Pitfall 1: Nonce Action String Inconsistency

**What goes wrong:** Form generates nonce with action A, controller verifies with action B, security check always fails.

**Why it happens:** Copy-paste from different modules without updating nonce action strings.

**Evidence in Clients Module:**
- Update form: `wp_nonce_field('wecoza_clients_ajax', 'nonce')`
- Controller: `wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')`
- AJAX handlers: `AjaxSecurity::requireNonce('clients_nonce_action')`
- Location form: `wp_nonce_field('submit_locations_form', 'wecoza_locations_form_nonce')`

**How to avoid:**
1. Choose ONE nonce action string per module (`clients_nonce_action`)
2. Use it in ALL forms, controllers, and AJAX handlers
3. Document it in module README

**Warning signs:** Non-AJAX form submission works during testing with JS disabled, but fails with "Security check failed" error.

**Fix (CLT-04):** Unify all client forms and handlers to use `clients_nonce_action`.

### Pitfall 2: Inline Submit Handlers Conflicting with External JS

**What goes wrong:** Form has inline `<script>` tag with submit handler AND external JS file with submit handler. Both fire on submit, causing duplicate AJAX calls.

**Why it happens:** Form copied from different codebase that used inline handlers, then external JS added later without removing inline handler.

**Evidence:**
```php
// views/clients/components/client-update-form.view.php:407-445
<script>
form.addEventListener('submit', function(event) {
    event.preventDefault();
    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
        method: 'POST',
        body: formData  // NO action parameter - this fails
    })
});
</script>
```

**AND**

```javascript
// assets/js/clients/client-capture.js:500-568 (enqueued for update page)
form.on('submit', function(event) {
    event.preventDefault();
    formData.append('action', 'wecoza_save_client');  // This succeeds
    $.ajax({ url: config.ajaxUrl, data: formData });
});
```

**Result:** Every update triggers TWO AJAX requests. Inline fetch fails (no action), external jQuery succeeds. Wastes server resources, confuses debugging.

**How to avoid:**
1. NEVER mix inline and external submit handlers
2. Choose one pattern (external JS preferred for reusability)
3. Grep for `addEventListener('submit'` or `.on('submit'` before adding handlers

**Warning signs:** Network tab shows duplicate POST requests to `admin-ajax.php`, one fails with no response data.

**Fix (CLT-01):** Delete entire inline `<script>` block (lines 401-607).

### Pitfall 3: Missing Non-AJAX Fallback Nonce

**What goes wrong:** AJAX submission works (nonce appended by JS), but non-AJAX fallback fails security check.

**Why it happens:** Developer tests with JavaScript enabled, never notices HTML form is missing `wp_nonce_field()`.

**Evidence (Capture Form):**
```php
// views/clients/components/client-capture-form.view.php
<form id="clients-form" method="POST">
    <!-- NO wp_nonce_field() -->
    <input type="text" name="client_name">
    <!-- fields -->
</form>
```

**Controller expects nonce:**
```php
// src/Clients/Controllers/ClientsController.php:214-215
if (!wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')) {
    $errors[] = __('Security check failed.');
}
```

**JavaScript appends nonce:**
```javascript
// assets/js/clients/client-capture.js:512
formData.append('nonce', config.nonce);  // Works for AJAX only
```

**Result:** AJAX works, but if JS disabled (accessibility, privacy tools, slow networks), form submission always fails.

**How to avoid:**
1. ALWAYS add `wp_nonce_field()` to HTML form
2. Test with JavaScript disabled (`about:config` → `javascript.enabled = false`)
3. Treat AJAX as enhancement, not requirement

**Warning signs:** Accessibility audit fails, users with NoScript report broken forms.

**Fix (CLT-02):** Add `<?php wp_nonce_field('clients_nonce_action', 'nonce'); ?>` after `<form>` tag in capture form.

### Pitfall 4: Phantom Columns in Repository Whitelists

**What goes wrong:** Form submits field X, repository whitelist includes X, but database INSERT/UPDATE fails silently or throws error.

**Why it happens:** Database schema changed (column removed/renamed) but repository whitelist not updated.

**Evidence:**
```php
// Repository whitelist includes
'client_town_id',  // Expects this column in clients table

// Actual database schema (confirmed via schema/wecoza_db_schema_bu_feb_12_2.sql)
CREATE TABLE public.clients (
    client_id integer NOT NULL,
    -- ... 15 columns total
    -- NO client_town_id column
);
```

**How repository handles it:**
```php
// ClientsModel::resolveColumn() (line 82-108 per audit reference)
// Returns NULL for unknown columns
// prepareDataForSave() silently drops null mappings
```

**Result:** Data submission succeeds (no error), but `client_town_id` value silently discarded. Location data correctly saved via `SitesModel::saveHeadSite()` using `place_id` instead. Whitelist entry is harmless dead code.

**How to avoid:**
1. After schema changes, grep for old column names across entire codebase
2. Maintain schema changelog noting renames/removals
3. Add database integration tests that validate schema matches repository expectations

**Warning signs:** Form field submitted but value never appears in database, no error logged.

**Fix (CLT-03):** Remove `'client_town_id'` from `getAllowedInsertColumns()` and `getAllowedUpdateColumns()` arrays.

## Code Examples

Verified patterns from project codebase:

### AJAX Handler with Unified Nonce

```php
// Source: src/Clients/Ajax/ClientAjaxHandlers.php:57-62
public function saveClient() {
    AjaxSecurity::requireNonce('clients_nonce_action');

    if (!current_user_can('manage_wecoza_clients')) {
        AjaxSecurity::sendError('Permission denied.');
    }

    $clientId = isset($_POST['id']) ? intval($_POST['id']) : 0;
    // ... process form data
}
```

### Non-AJAX Fallback Pattern

```php
// Source: src/Clients/Controllers/ClientsController.php:214-232
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonce']) && !wp_doing_ajax()) {
    if (!wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')) {
        $errors[] = __('Security check failed. Please try again.', 'wecoza-core');
    } else {
        $result = $this->handleFormSubmission($atts['id']);
        if ($result['success']) {
            $success = true;
            $client = $result['client'];
        } else {
            $errors = $result['errors'];
        }
    }
}
```

### Nonce Field in View (Correct)

```php
// Source: views/clients/components/client-update-form.view.php:122
<form id="clients-form" class="needs-validation" method="POST">
    <?php wp_nonce_field('clients_nonce_action', 'nonce'); ?>
    <input type="hidden" name="id" value="<?php echo esc_attr($client['id']); ?>">
    <!-- form fields -->
</form>
```

### jQuery AJAX Submission with Nonce

```javascript
// Source: assets/js/clients/client-capture.js:500-523
form.on('submit', function (event) {
    event.preventDefault();

    var formData = new FormData(form[0]);
    formData.append('action', config.actions.save);  // 'wecoza_save_client'
    formData.append('nonce', config.nonce);          // From wp_localize_script

    $.ajax({
        url: config.ajaxUrl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json'
    }).done(function (response) {
        if (response && response.success) {
            renderMessage('success', response.data.message);
        } else {
            renderMessage('error', extractErrors(response.data.errors));
        }
    });
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Inline submit handlers | External unified JS files | 2025-2026 (gradual migration) | Update form still has inline handler (CLT-01), capture form doesn't |
| Per-form nonce actions | Module-level unified nonce | Ongoing standardization | Clients module has 3 different nonce actions (CLT-04) |
| Manual nonce appending in JS | HTML nonce field + JS fallback | Current best practice | Capture form missing HTML nonce (CLT-02) |
| Register all planned endpoints | Register only active endpoints | Security hardening trend | 7 unused endpoints increase attack surface (CLT-05) |

**Deprecated/outdated:**
- **Inline `<script>` blocks in view files:** Replaced by enqueued external JS files for better caching, CSP compliance, and reusability. Update form still has 207-line inline block (lines 401-607) that duplicates `client-capture.js` functionality.
- **Per-form nonce action strings:** Modern WordPress practice uses module-level or plugin-level nonce actions for consistency. Clients module has `wecoza_clients_ajax`, `clients_nonce_action`, `submit_locations_form` across 3 different contexts.

## Open Questions

1. **Sub-site management feature status:**
   - 7 unused endpoints suggest planned multi-site hierarchy feature
   - Is this feature deferred to future milestone?
   - If yes, should we keep stub handlers or remove entirely?
   - **Recommendation:** Remove (CLT-05). Endpoints can be re-added when feature is implemented. Keeping unused handlers increases audit workload and attack surface.

2. **Location form nonce action:**
   - Location form uses `submit_locations_form` nonce action
   - Should this also unify to `clients_nonce_action`?
   - **Recommendation:** YES for consistency, but NOT in scope for Phase 34 (CLT requirements don't mention location form). Consider for Phase 35 or general cleanup phase.

3. **Main clients dropdown population:**
   - `wecoza_get_main_clients` endpoint registered but dropdown populated server-side
   - Was this endpoint used in earlier implementation?
   - **Recommendation:** Remove (part of CLT-05). Current server-side approach is more efficient (fewer roundtrips).

## Sources

### Primary (HIGH confidence)
- `docs/formfieldanalysis/clients-audit.md` - Comprehensive field wiring audit with exact file paths and line numbers
- `src/Clients/Ajax/ClientAjaxHandlers.php` - AJAX endpoint registrations and handlers
- `src/Clients/Repositories/ClientRepository.php` - Column whitelists (lines 72-90, 98-117)
- `views/clients/components/client-update-form.view.php` - Inline submit handler (lines 401-607)
- `views/clients/components/client-capture-form.view.php` - Missing nonce field verification
- `assets/js/clients/client-capture.js` - Unified submit handler (lines 500-568)
- `schema/wecoza_db_schema_bu_feb_12_2.sql` - Database schema showing clients table has NO client_town_id column

### Secondary (MEDIUM confidence)
- `.planning/phases/31-learners-module-fixes/31-RESEARCH.md` - Similar pattern of duplicate handlers and nonce unification
- `.planning/phases/32-classes-module-fixes/32-RESEARCH.md` - Similar pattern of unused AJAX endpoint removal
- `.planning/phases/33-agents-module-fixes/33-RESEARCH.md` - Similar pattern of field mapping and repository whitelist cleanup

### Tertiary (for context)
- `src/Clients/Controllers/ClientsController.php` - Non-AJAX fallback handlers (lines 214-232, 406-424)
- `src/Clients/Models/SitesModel.php` - Actual location storage via `saveHeadSite()` method

## Metadata

**Confidence breakdown:**
- CLT-01 (inline handler removal): HIGH - Verified duplicate handlers in lines 401-607 (inline) and client-capture.js:500-568 (external)
- CLT-02 (capture form nonce): HIGH - Verified missing `wp_nonce_field()` in capture form, present in update form line 122
- CLT-03 (phantom column): HIGH - Verified `client_town_id` in repository lines 82+108, NOT in database schema
- CLT-04 (nonce unification): HIGH - Verified 3 different nonce actions via grep: `wecoza_clients_ajax`, `clients_nonce_action`, `submit_locations_form`
- CLT-05 (unused endpoints): HIGH - Verified 7 endpoints registered in lines 39-51, grep found ZERO JavaScript calls to any of them

**Research date:** 2026-02-13
**Valid until:** 60 days (stable module, low churn rate)
