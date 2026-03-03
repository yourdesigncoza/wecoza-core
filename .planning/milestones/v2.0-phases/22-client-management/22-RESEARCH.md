# Phase 22: Client Management - Research

**Researched:** 2026-02-11
**Domain:** End-to-end Client Management Integration Testing & Debugging
**Confidence:** HIGH

## Summary

Phase 22 activates the Client Management system migrated in Phase 21. All structural code (Controllers, Models, Ajax handlers, Views, JS assets) exists in wecoza-core. This phase verifies the wiring, fixes integration bugs, and ensures all shortcodes and AJAX endpoints work correctly for full client CRUD functionality.

**Core Finding:** Phase 21 completed structural migration but revealed integration issues during UAT (test #2 failed with "Something went wrong" error). Multiple bugs were fixed:
- BaseModel static property conflicts
- Missing PostgresConnection CRUD methods (getAll, getRow, getValue, insert, update, delete)
- Missing tableHasColumn method

Phase 22 focuses on verification and debugging — not new features or redesign. Goal is functional parity with the standalone plugin.

**Primary recommendation:** Systematic testing of each shortcode and AJAX endpoint, following the test sequence in 21-UAT.md. Fix bugs as discovered, prioritizing blocking issues (form rendering, data display) over polish (CSV export, statistics).

## Standard Stack

### Already Migrated in Phase 21
| Component | Location | Status | Notes |
|-----------|----------|--------|-------|
| ClientsController | `src/Clients/Controllers/` | ✓ Migrated | 3 shortcodes, asset enqueuing |
| LocationsController | `src/Clients/Controllers/` | ✓ Migrated | 3 shortcodes, Google Maps |
| ClientAjaxHandlers | `src/Clients/Ajax/` | ✓ Migrated | 15 AJAX endpoints |
| ClientsModel | `src/Clients/Models/` | ✓ Migrated | Column mapping, validation |
| SitesModel | `src/Clients/Models/` | ✓ Migrated | Location hierarchy |
| LocationsModel | `src/Clients/Models/` | ✓ Migrated | Place CRUD |
| ClientCommunicationsModel | `src/Clients/Models/` | ✓ Migrated | Contact history |
| ClientRepository | `src/Clients/Repositories/` | ✓ Migrated | Query layer |
| LocationRepository | `src/Clients/Repositories/` | ✓ Migrated | Query layer |
| ViewHelpers | `src/Clients/Helpers/` | ✓ Migrated | Form rendering |
| Views (6 templates) | `views/clients/` | ✓ Migrated | Forms & tables |
| JavaScript (6 files) | `assets/js/clients/` | ✓ Migrated | AJAX & UI |
| Config | `config/clients.php` | ✓ Created | Validation, SETA |

### Core Dependencies (Already Present)
| Component | Version | Location | Usage |
|-----------|---------|----------|-------|
| PostgresConnection | 1.0.0 | `core/Database/` | Via `wecoza_db()` |
| BaseController | 1.0.0 | `core/Abstract/` | Extended by Controllers |
| BaseModel | 1.0.0 | `core/Abstract/` | Extended by Models |
| BaseRepository | 1.0.0 | `core/Abstract/` | Extended by Repositories |
| AjaxSecurity | 1.0.0 | `core/Helpers/` | All AJAX handlers |
| Helper Functions | 1.0.0 | `core/Helpers/functions.php` | wecoza_view(), wecoza_config() |

### Database Schema (Already Exists)
| Table | Purpose | Key Features |
|-------|---------|--------------|
| `clients` | Client records | Soft delete, JSONB fields, hierarchy via main_client_id |
| `sites` | Client locations/branches | Parent-child via parent_site_id |
| `locations` | Place data | Province/town/suburb hierarchy, lat/lng |
| `client_communications` | Contact history | Communication type, timestamps |

**Installation:** No new dependencies. All infrastructure exists.

## Architecture Patterns

### Pattern 1: Shortcode Testing Flow
**What:** Verify each shortcode renders without PHP errors
**When to use:** Testing all 6 shortcodes in Phase 22
**Example:**
```
Test Sequence:
1. Visit page with [wecoza_capture_clients]
   Expected: Form renders with all fields (client name, SETA dropdown, location selects)
   Check: Browser console for JS errors, debug.log for PHP errors

2. Visit page with [wecoza_display_clients]
   Expected: Table renders with client rows, search, filters, pagination
   Check: Client data displays, statistics card shows counts

3. Visit page with [wecoza_update_clients]?mode=update&client_id=1
   Expected: Form pre-populates with client data
   Check: All fields filled, site data loaded

4. Visit page with [wecoza_locations_capture]
   Expected: Location form renders, Google Maps autocomplete if API key set
   Check: Province/town/suburb dropdowns, coordinates fields

5. Visit page with [wecoza_locations_list]
   Expected: Locations table renders with search
   Check: Location data displays

6. Visit page with [wecoza_locations_edit]?location_id=1
   Expected: Location form pre-populates
   Check: All fields filled
```

### Pattern 2: AJAX Endpoint Testing
**What:** Test each AJAX action via browser console or Postman
**When to use:** After shortcode rendering confirmed, test interactivity
**Example:**
```javascript
// Test wecoza_save_client endpoint
jQuery.ajax({
    url: wecozaClients.ajax_url,
    type: 'POST',
    data: {
        action: 'wecoza_save_client',
        nonce: wecozaClients.nonce,
        client_name: 'Test Client',
        company_registration_nr: '2024/123456/07',
        seta: 'BANKSETA',
        client_status: 'Active Client',
        contact_person: 'John Doe',
        contact_person_email: 'john@example.com',
        contact_person_cellphone: '0821234567',
        client_town_id: 1,
        financial_year_end: '2024-02-28',
        bbbee_verification_date: '2024-01-15'
    },
    success: function(response) {
        console.log('Success:', response);
    },
    error: function(xhr) {
        console.error('Error:', xhr);
    }
});
```

### Pattern 3: Debug Log Analysis
**What:** Check `/wp-content/debug.log` for errors after each test
**When to use:** After every shortcode render or AJAX call
**Example:**
```bash
# Tail debug log during testing
tail -f /opt/lampp/htdocs/wecoza/wp-content/debug.log

# Look for:
# - PHP Fatal errors (class not found, method not found)
# - PHP Warnings (undefined index, array to string conversion)
# - WeCoza-specific logs (via wecoza_log())
# - SQL errors from PostgresConnection
```

### Pattern 4: Column Mapping Verification
**What:** Ensure ClientsModel resolves database columns correctly
**When to use:** If form submission fails or data doesn't save
**Example:**
```php
// ClientsModel uses column mapping to handle schema variations
// Check resolveColumn() finds actual database columns
protected $columnCandidates = [
    'id' => ['client_id', 'id'],
    'client_name' => ['client_name'],
    'company_registration_nr' => ['company_registration_nr', 'company_registration_number'],
    'client_town_id' => ['client_town_id'], // Reference to locations.location_id
];

// Debug: Check what columns were resolved
// Add to ClientsModel::__construct():
if (defined('WP_DEBUG') && WP_DEBUG) {
    wecoza_log('ClientsModel column map: ' . print_r($this->columnMap, true));
}
```

### Pattern 5: Location Hierarchy Loading
**What:** SitesModel builds province→town→suburb hierarchy from locations table
**When to use:** Testing location dropdowns in client forms
**Example:**
```php
// SitesModel::getLocationHierarchy() caches location data
// Returns structure:
[
    [
        'name' => 'Gauteng',
        'towns' => [
            [
                'name' => 'Johannesburg',
                'suburbs' => [
                    ['id' => 1, 'name' => 'Sandton', 'postal_code' => '2196'],
                    ['id' => 2, 'name' => 'Rosebank', 'postal_code' => '2196'],
                ]
            ]
        ]
    ]
]

// Test: Check cache transient
$hierarchy = get_transient('wecoza_clients_location_cache');
if (!$hierarchy) {
    // Cache miss - will query locations table
    // Check SQL in debug log
}
```

### Pattern 6: Client Hierarchy (Main/Sub-Clients)
**What:** Clients can be standalone (main) or linked to a main client (sub-client)
**When to use:** Testing sub-client creation and site relationships
**Example:**
```php
// Main client: main_client_id IS NULL
// Sub-client: main_client_id = [parent client ID]

// When creating sub-client, site logic:
// 1. Get parent client's head site
// 2. Create sub-site under parent's head site
// 3. Link sub-client to sub-site

// Test flow:
// 1. Create main client "Company A" → gets head_site_id = 1
// 2. Create sub-client "Company A - Branch" with main_client_id = [Company A ID]
//    → creates sub-site with parent_site_id = 1
//    → links client to new sub-site
```

### Pattern 7: Form Validation Error Display
**What:** Controllers return errors array, views display per-field errors
**When to use:** Testing form submission validation
**Example:**
```php
// Controller validation:
$errors = $model->validate($clientData);
if (!empty($errors)) {
    return wecoza_view('clients/components/client-capture-form', [
        'client' => $clientData,
        'errors' => $errors,
    ], true);
}

// View rendering:
<input
    type="text"
    name="client_name"
    class="form-control <?php echo isset($errors['client_name']) ? 'is-invalid' : ''; ?>"
>
<?php if (isset($errors['client_name'])): ?>
    <div class="invalid-feedback"><?php echo esc_html($errors['client_name']); ?></div>
<?php endif; ?>
```

### Pattern 8: CSV Export Testing
**What:** exportClients() AJAX handler returns CSV file directly
**When to use:** Testing export functionality
**Example:**
```
# Test export URL directly (requires nonce):
1. Get nonce from browser: wecozaClients.nonce
2. Visit: /wp-admin/admin-ajax.php?action=wecoza_export_clients&nonce=[NONCE]
3. Should trigger CSV download: clients-export-2026-02-11.csv

# Check CSV format:
- UTF-8 BOM present (for Excel compatibility)
- Headers: ID, Client Name, Company Registration Nr, Contact Person, Email, etc.
- Data rows match clients table
```

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| New test pages | Manual page creation | Existing pages with shortcodes | Standalone plugin already has pages set up |
| Database queries | Direct SQL in debugging | wecoza_db()->getAll() | Consistent with Models |
| Error logging | echo/var_dump | wecoza_log($msg, 'error') | Respects WP_DEBUG, centralized |
| Form field testing | Manual HTML inspection | ViewHelpers::renderField() | Existing abstraction |
| AJAX debugging | Browser console only | debug.log + network tab | Multi-layer visibility |

**Key insight:** Phase 22 is about verification, not creation. All tools exist — use systematic testing to find and fix integration bugs.

## Common Pitfalls

### Pitfall 1: Testing Before Deactivating Standalone Plugin
**What goes wrong:** Both wecoza-clients-plugin AND wecoza-core Clients module active, causing conflicts
**Why it happens:** Standalone plugin still installed from pre-migration
**How to avoid:**
1. Deactivate wecoza-clients-plugin BEFORE testing wecoza-core Clients
2. Verify only wecoza-core active: Plugins → check only "WeCoza Core" enabled
3. Flush permalinks if shortcodes don't work: Settings → Permalinks → Save Changes
**Warning signs:** "Something went wrong" errors, class redeclaration errors, duplicate AJAX handlers

### Pitfall 2: Missing Nonce in JavaScript Localization
**What goes wrong:** AJAX calls fail with "Invalid nonce" even though nonce created
**Why it happens:** wp_localize_script() called but script not enqueued on page
**How to avoid:**
1. Check has_shortcode() condition in enqueueAssets() matches actual shortcode
2. Verify wp_localize_script() called AFTER wp_enqueue_script()
3. Check browser console for wecozaClients object: `console.log(wecozaClients)`
**Warning signs:** JS error "wecozaClients is not defined", AJAX 403 errors

### Pitfall 3: Location Hierarchy Cache Not Refreshed
**What goes wrong:** Location dropdown shows old data after adding new locations
**Why it happens:** SitesModel caches hierarchy in transient/option
**How to avoid:**
1. Clear cache after manual location inserts: Call refreshLocationCache()
2. Or delete transient: `delete_transient('wecoza_clients_location_cache')`
3. LocationsModel already calls refreshLocationCache() after create()
**Warning signs:** New suburbs don't appear in dropdown, outdated postal codes

### Pitfall 4: Client Form Shows "Array to string conversion"
**What goes wrong:** Warning when rendering client update form
**Why it happens:** Client data has array values (like client_location), ViewHelpers expects scalars
**How to avoid:**
- ClientsController::filterClientDataForForm() already filters out arrays
- Ensure this method called before passing $client to view
- Check view uses scalar fields only (id, client_name, seta, etc.)
**Warning signs:** PHP Warning in debug.log, form fields show "Array" text

### Pitfall 5: AJAX Handler Missing Capability Check
**What goes wrong:** Non-admin users can access restricted endpoints
**Why it happens:** AjaxSecurity::requireNonce() called but manual capability check missing
**How to avoid:**
```php
// WRONG - no capability check
public function deleteClient() {
    AjaxSecurity::requireNonce('clients_nonce_action');
    // ... delete logic
}

// RIGHT - explicit capability check
public function deleteClient() {
    AjaxSecurity::requireNonce('clients_nonce_action');
    if (!current_user_can('manage_wecoza_clients')) {
        AjaxSecurity::sendError('Permission denied.');
    }
    // ... delete logic
}
```
**Warning signs:** Unauthorized users can delete clients, security audit failures

### Pitfall 6: Google Maps API Key Missing
**What goes wrong:** Location autocomplete doesn't appear
**Why it happens:** Option `wecoza_agents_google_maps_api_key` not set
**How to avoid:**
1. Set API key: wp-admin → Settings → WeCoza Agents → Google Maps API Key
2. Or via WP-CLI: `wp option update wecoza_agents_google_maps_api_key 'YOUR_KEY'`
3. LocationsController checks for key before enqueuing Google Places script
**Warning signs:** No autocomplete field, plain input instead, JS console error "Google is not defined"

### Pitfall 7: Soft-Delete Not Applied on Delete
**What goes wrong:** Client records permanently deleted instead of soft-deleted
**Why it happens:** Model delete() method calls wecoza_db()->delete() instead of update()
**How to avoid:**
```php
// ClientsModel should soft-delete:
public function delete($id) {
    $data = ['deleted_at' => current_time('mysql')];
    return $this->update($id, $data);
}

// NOT hard-delete:
public function delete($id) {
    return wecoza_db()->delete($this->table, "id = :id", [':id' => $id]);
}
```
**Warning signs:** Deleted clients disappear completely, can't restore, foreign key violations

## Open Questions

1. **Sub-Site Creation Flow Verification**
   - What we know: Sub-clients should link to main client's head site
   - What's unclear: Does SitesModel::saveSubSite() correctly handle the parent_site_id lookup?
   - Recommendation: Test creating sub-client and verify sites table has correct parent_site_id reference. Check via database query or client detail view.

2. **Statistics Calculation Accuracy**
   - What we know: ClientsModel::getStatistics() returns counts by status
   - What's unclear: Do counts exclude soft-deleted clients?
   - Recommendation: Verify SQL includes `WHERE deleted_at IS NULL`. Test by soft-deleting a client and checking stats.

3. **CSV Export Column Selection**
   - What we know: exportClients() handler returns fixed columns
   - What's unclear: Should additional fields (SETA, financial year) be included?
   - Recommendation: Keep current columns (matches standalone plugin). Can enhance later if user requests.

4. **Location Duplicate Detection Sensitivity**
   - What we know: checkLocationDuplicates() AJAX endpoint exists
   - What's unclear: What constitutes a duplicate? Exact match vs. fuzzy match?
   - Recommendation: Test duplicate detection with similar addresses. Ensure UI shows warnings but allows override.

5. **Client Update vs. Create Form Differences**
   - What we know: Separate shortcodes: `wecoza_capture_clients` and `wecoza_update_clients`
   - What's unclear: Why separate shortcodes if both can handle create/edit?
   - Recommendation: Preserve distinction (matches standalone plugin). Update shortcode requires ?mode=update&client_id= for security/clarity.

6. **Communication Type Logging Trigger**
   - What we know: ClientCommunicationsModel logs communication when client_status changes
   - What's unclear: Should every status change trigger a log, or only certain transitions?
   - Recommendation: Current implementation logs on any status change. Test by updating client status and checking client_communications table.

## Testing Checklist

### Prerequisite Setup
- [ ] Deactivate standalone wecoza-clients-plugin
- [ ] Activate wecoza-core plugin
- [ ] Verify WP_DEBUG enabled in wp-config.php
- [ ] Clear debug.log file
- [ ] Note existing client/location count for baseline

### Shortcode Rendering Tests
- [ ] [wecoza_capture_clients] renders form
- [ ] [wecoza_display_clients] renders table
- [ ] [wecoza_update_clients] with ?mode=update&client_id=X renders pre-filled form
- [ ] [wecoza_locations_capture] renders form
- [ ] [wecoza_locations_list] renders table
- [ ] [wecoza_locations_edit] with ?location_id=X renders pre-filled form

### AJAX Endpoint Tests
- [ ] wecoza_save_client creates new client
- [ ] wecoza_save_client updates existing client
- [ ] wecoza_get_client returns client data
- [ ] wecoza_delete_client soft-deletes client
- [ ] wecoza_search_clients returns filtered results
- [ ] wecoza_get_main_clients returns clients with main_client_id IS NULL
- [ ] wecoza_export_clients triggers CSV download
- [ ] wecoza_get_locations returns hierarchy
- [ ] wecoza_save_location creates location
- [ ] wecoza_check_location_duplicates detects duplicates

### Data Flow Tests
- [ ] Create main client → verify head site created
- [ ] Create sub-client → verify sub-site created with correct parent_site_id
- [ ] Update client status → verify communication logged
- [ ] Delete client → verify deleted_at set, not hard-deleted
- [ ] Filter clients by status → results match
- [ ] Filter clients by SETA → results match
- [ ] Search clients by name → results match
- [ ] Paginate clients list → correct offset/limit

### JavaScript Tests
- [ ] Client form validation highlights errors
- [ ] Location hierarchy loads province → town → suburb
- [ ] Postal code auto-fills on suburb select
- [ ] Google Places autocomplete works (if API key set)
- [ ] Delete confirmation dialog appears
- [ ] Export button triggers CSV download
- [ ] Search filters update table results

### Error Handling Tests
- [ ] Missing required field shows validation error
- [ ] Duplicate company registration number rejected
- [ ] Invalid email format rejected
- [ ] Invalid date format rejected
- [ ] AJAX without nonce returns 403
- [ ] Non-admin user can't access manage endpoints

### Debug Log Verification
- [ ] No PHP Fatal errors
- [ ] No PHP Warnings (except expected deprecations)
- [ ] No "class not found" errors
- [ ] No "undefined index" errors
- [ ] No SQL syntax errors

## Phase 21 Integration Bugs (Already Fixed)

Context from 21-UAT.md test #2 failure:

1. **BaseModel Static Property Conflict**
   - Issue: Static $columnMapCache shared across all Models
   - Fix: Namespaced cache key per model/table
   - Status: FIXED in Phase 21

2. **Missing PostgresConnection CRUD Methods**
   - Issue: getAll(), getRow(), getValue(), insert(), update(), delete() missing
   - Fix: Added all CRUD methods to PostgresConnection
   - Status: FIXED in Phase 21

3. **Missing tableHasColumn() Method**
   - Issue: ClientsModel calls tableHasColumn() to resolve columns
   - Fix: Added tableHasColumn() to PostgresConnection
   - Status: FIXED in Phase 21

**Phase 22 Focus:** Verify these fixes work end-to-end. Test all CRUD operations, column resolution, and table checks.

## Sources

### Primary (HIGH confidence)
- Wecoza-core codebase inspection:
  - `src/Clients/Controllers/ClientsController.php` - Shortcode handlers, form processing
  - `src/Clients/Controllers/LocationsController.php` - Location shortcodes, Google Maps
  - `src/Clients/Ajax/ClientAjaxHandlers.php` - 15 AJAX endpoints
  - `src/Clients/Models/ClientsModel.php` - Column mapping, validation, stats
  - `src/Clients/Models/SitesModel.php` - Location hierarchy, site CRUD
  - `src/Clients/Models/LocationsModel.php` - Location validation
  - `config/clients.php` - Validation rules, SETA options
  - `views/clients/` - 6 view templates
  - `assets/js/clients/` - 6 JavaScript files
  - `wecoza-core.php` - Module initialization (lines 232-241)
- Phase 21 documentation:
  - `.planning/phases/21-foundation-architecture/21-RESEARCH.md` - Migration patterns
  - `.planning/phases/21-foundation-architecture/21-02-SUMMARY.md` - What was migrated
  - `.planning/phases/21-foundation-architecture/21-UAT.md` - Test results, bugs found
- Phase 22 requirements:
  - `.planning/phases/22-client-management/22-CONTEXT.md` - User decisions
  - Phase 22 success criteria (5 requirements)
  - Requirements CLT-01 through CLT-09, SC-01, SC-02

### Secondary (MEDIUM confidence)
- Standalone plugin patterns (pre-migration reference):
  - `.integrate/wecoza-clients-plugin/` - Original behavior to preserve
  - Source plugin CLAUDE.md - Original architecture

### Tertiary (LOW confidence)
- None - research based entirely on migrated code and phase documentation

## Metadata

**Confidence breakdown:**
- Migrated components: HIGH - All files exist, verified in Phase 21
- Testing approach: HIGH - Based on 21-UAT.md test sequence
- Integration bugs: HIGH - Documented in 21-UAT.md with fixes verified
- AJAX endpoints: HIGH - All 15 registered in ClientAjaxHandlers
- Database schema: HIGH - Schema unchanged from standalone plugin
- Open questions: MEDIUM - Edge cases requiring user testing

**Research date:** 2026-02-11
**Valid until:** 2026-03-15 (30 days - code stable, focus on testing)

**Completeness:** Research covers all 12 requirements (CLT-01 through CLT-09, SC-01, SC-02) and provides systematic testing approach. Phase 21 fixed major integration bugs; Phase 22 verifies end-to-end functionality. Ready for planning.
