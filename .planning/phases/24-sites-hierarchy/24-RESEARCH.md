# Phase 24: Sites Hierarchy - Research

**Researched:** 2026-02-12
**Domain:** Sites Parent-Child Hierarchy Verification & Client-Site Integration Testing
**Confidence:** HIGH

## Summary

Phase 24 verifies and fixes the sites hierarchy system — head sites (main client locations) and sub-sites (branch locations) with parent-child relationships. Unlike Phases 22-23 which activated standalone modules, sites are NOT standalone. They are deeply embedded within the client create/edit flow with no separate UI, controller methods, or shortcodes.

**All code already exists** and was migrated in Phase 21. SitesModel (860 lines) contains the full parent-child hierarchy logic, location hydration, and caching. Five AJAX endpoints are registered in ClientAjaxHandlers. Sites data flows through the client capture/update forms automatically.

**Core Pattern (from CONTEXT.md):** This matches the legacy wecoza-clients-plugin exactly:
- Head site created when main client created (site_name + location via province/town/suburb cascade)
- Sub-site created when "Is SubClient" checkbox checked (links to parent client's head site)
- Sites displayed within clients table as location data (town column from head site)
- NO dedicated sites listing page, NO standalone shortcodes

**Primary recommendation:** Follow Phase 22-23 verification pattern. Test head site creation via client form, test sub-site creation via sub-client form, verify site data hydration in clients listing, fix any AJAX wiring bugs (action names, DOM IDs, localization keys). Focus on integration points, not standalone features that don't exist.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Integration Scope (from legacy analysis)**
- Sites have NO standalone shortcodes, controller, views, or JS — this matches the legacy plugin
- Sites are managed exclusively through client capture/update forms
- The `SitesModel.php` is already migrated to `src/Clients/Models/`
- 5 AJAX handlers already registered in `ClientAjaxHandlers.php`: `wecoza_save_sub_site`, `wecoza_get_head_sites`, `wecoza_get_sub_sites`, `wecoza_delete_sub_site`, `wecoza_get_sites_hierarchy`
- Client forms already include site fields (site_name, location hierarchy, street address)

**Head Site Creation Flow (match legacy)**
- When creating a new main client, head site is auto-created from client name + selected location
- `parent_site_id = NULL` indicates head site
- Validated via `SitesModel::validateHeadSite()` (requires site_name + place_id)
- Head site cache refreshed after save

**Sub-Site Creation Flow (match legacy)**
- Sub-sites created when "Is SubClient" checkbox is checked on client form
- Main client dropdown appears, user selects parent client
- Sub-site links to parent client's head site via `parent_site_id`
- Validated via `SitesModel::validateSubSite()` — parent must exist and belong to same client
- Database trigger `trg_sites_same_client` enforces client_id match between parent/child

**Site Listing Display (match legacy)**
- Sites are shown within the clients table, NOT a separate listing
- Client hydration via `SitesModel::hydrateClients()` adds head_site data to each client row
- Town column in clients table comes from head site's location (suburb, town, province via place_id)
- No dedicated sites listing page exists in legacy — don't create one

**Location Selection for Sites (match legacy)**
- Uses location hierarchy: Province > Town > Suburb cascading dropdowns
- Suburb selection auto-fills postal code, street address
- Location hierarchy fetched via `wecoza_get_locations` AJAX endpoint
- SitesModel caches location hierarchy in WordPress transient `wecoza_clients_location_cache`

**Database Schema (already exists)**
- `sites` table: `site_id`, `client_id`, `site_name`, `parent_site_id`, `place_id`, `created_at`, `updated_at`
- DB views: `v_client_head_sites` (parent_site_id IS NULL), `v_client_sub_sites` (parent_site_id IS NOT NULL)
- Trigger: `trg_sites_same_client` — prevents cross-client parent-child relationships
- FK constraints: CASCADE on client delete, RESTRICT on parent site delete, RESTRICT on location delete
- Indexes: `idx_sites_client_hierarchy`, `idx_sites_client_place`, `idx_sites_place_lookup`, `idx_sites_site_name_lower`

**Verification Focus (same pattern as Phases 22-23)**
- Phase 22 and 23 revealed wiring bugs: DOM ID mismatches, AJAX action name discrepancies, localization key differences, missing nopriv removal
- This phase should follow the same verify-and-fix pattern:
  1. Test head site creation via client capture form
  2. Test sub-site creation via sub-client form
  3. Test site data display in clients listing (town/location hydration)
  4. Test site data loading in client update form
  5. Fix any AJAX, DOM, or integration issues found

### Claude's Discretion
- Order of verification tasks
- How to structure fix plans if issues are found
- Whether to split into 1 or 2 plan files

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Already Migrated in Phase 21
| Component | Location | Purpose | Status |
|-----------|----------|---------|--------|
| SitesModel | `src/Clients/Models/` | Head/sub-site CRUD, location hydration, caching | ✓ Migrated (860 lines) |
| ClientAjaxHandlers | `src/Clients/Ajax/` | 5 site-related endpoints (save_sub_site, get_head_sites, etc.) | ✓ Migrated |
| ClientsModel | `src/Clients/Models/` | Integrates sites via getSitesModel() | ✓ Migrated |
| Client Forms | `views/clients/components/` | Site fields embedded in capture/update forms | ✓ Migrated |
| Client JS | `assets/js/clients/client-capture.js` | Location hierarchy dropdowns (province/town/suburb) | ✓ Migrated |

### Core Dependencies (Already Present)
| Component | Version | Purpose | Usage Pattern |
|-----------|---------|---------|---------------|
| PostgresConnection | Core 1.0.0 | Database access | Via `wecoza_db()` |
| ViewHelpers | Clients 1.0.0 | Form field rendering | Shared with Client forms |
| WordPress Transients | Core WP API | Location/head site caching | get_transient(), set_transient() |

### Database Schema (Already Exists)

**Table:** `public.sites`
```sql
CREATE TABLE public.sites (
    site_id integer PRIMARY KEY,
    client_id integer NOT NULL,
    site_name varchar(100) NOT NULL,
    parent_site_id integer,                      -- NULL = head site, NOT NULL = sub-site
    place_id integer,                            -- FK to locations.location_id
    created_at timestamp DEFAULT now(),
    updated_at timestamp DEFAULT now()
);

-- Views
CREATE VIEW v_client_head_sites AS
  SELECT s.*, c.client_name, l.suburb, l.town, l.province, l.postal_code
  FROM sites s
  JOIN clients c ON c.client_id = s.client_id
  LEFT JOIN locations l ON l.location_id = s.place_id
  WHERE s.parent_site_id IS NULL;

CREATE VIEW v_client_sub_sites AS
  SELECT s.*, parent_s.site_name AS parent_site_name, l.suburb, l.town, l.province
  FROM sites s
  JOIN sites parent_s ON parent_s.site_id = s.parent_site_id
  LEFT JOIN locations l ON l.location_id = s.place_id
  WHERE s.parent_site_id IS NOT NULL;

-- Trigger: Prevents cross-client parent-child relationships
CREATE TRIGGER trg_sites_same_client
  BEFORE INSERT OR UPDATE OF client_id, parent_site_id ON sites
  FOR EACH ROW EXECUTE FUNCTION fn_sites_same_client();

-- Constraints
ALTER TABLE sites ADD CONSTRAINT fk_sites_client
  FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE;
ALTER TABLE sites ADD CONSTRAINT sites_parent_site_id_fkey
  FOREIGN KEY (parent_site_id) REFERENCES sites(site_id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE sites ADD CONSTRAINT sites_place_id_fkey
  FOREIGN KEY (place_id) REFERENCES locations(location_id) ON UPDATE CASCADE ON DELETE RESTRICT;
```

**Key Relationships:**
- Head site: `parent_site_id IS NULL`, one per client
- Sub-site: `parent_site_id = parent_head_site.site_id`, many per parent
- Location hydration: `place_id` → `locations.location_id` for suburb/town/province/postal_code
- Client cascade delete: When client deleted, all sites deleted automatically

**Installation:** Schema already exists in production. No migrations needed.

## Architecture Patterns

### Pattern 1: Head Site Auto-Creation (Client Form Integration)
**What:** When user creates main client, head site created automatically from client name + selected location
**When to use:** Client capture form submission (ClientAjaxHandlers::saveClient)
**Example:**
```php
// Source: src/Clients/Ajax/ClientAjaxHandlers.php lines 117-155
// After client saved
$siteData['site_name_fallback'] = $clientData['client_name'] ?? '';
if (!empty($siteData['site_id']) && !$sitesModel->ensureSiteBelongsToClient($siteData['site_id'], $clientId)) {
    AjaxSecurity::sendError('Selected site does not belong to this client.');
}

// Save site based on type
if (!empty($siteData['parent_site_id'])) {
    // Sub-site flow (covered in Pattern 2)
} else {
    // Head site flow
    $siteId = $sitesModel->saveHeadSite($clientId, $siteData);
    if (!$siteId) {
        AjaxSecurity::sendError('Failed to save site details. Please try again.');
    }
}
```

**Key Points:**
- No separate site form — site data comes from client form fields (site_name, client_town_id)
- `parent_site_id` NULL indicates head site
- `place_id` comes from location hierarchy (province/town/suburb cascade)
- Head site cache refreshed after save via `refreshHeadSiteCache([$clientId])`

### Pattern 2: Sub-Site Creation (SubClient Checkbox Flow)
**What:** When "Is SubClient" checkbox checked, main client dropdown appears, sub-site links to parent client's head site
**When to use:** Client capture form with sub-client relationship
**Example:**
```php
// Source: src/Clients/Ajax/ClientAjaxHandlers.php lines 583-637
// Sanitize form data to determine sub-client relationship
$isSubClient = isset($data['is_sub_client']) && $data['is_sub_client'] === 'on';
if ($isSubClient && !empty($data['main_client_id'])) {
    $client['main_client_id'] = (int) $data['main_client_id'];
} else {
    $client['main_client_id'] = null;
}

// Build site payload with parent relationship
if (!empty($client['main_client_id'])) {
    $sitesModel = new SitesModel();
    $mainClientSite = $sitesModel->getHeadSite($client['main_client_id']);
    if ($mainClientSite && !empty($mainClientSite['site_id'])) {
        $site['parent_site_id'] = $mainClientSite['site_id'];  // Link to parent head site
    }
} else {
    $site['parent_site_id'] = null;
}

// Validate sub-site
$expectedClientId = null;
if ($isNew && !empty($clientData['main_client_id'])) {
    $expectedClientId = $clientData['main_client_id'];
}
$siteErrors = $sitesModel->validateSubSite($clientId, $siteData['parent_site_id'], $siteData, $expectedClientId);
```

**Key Points:**
- Checkbox triggers main client dropdown via JS (`client-capture.js`)
- Parent site ID fetched from main client's head site
- Database trigger enforces same client_id between parent and child
- Validation confirms parent site exists and belongs to main client

### Pattern 3: Location Hydration in Client Listing
**What:** Clients table displays town/location data from head site, hydrated via `SitesModel::hydrateClients()`
**When to use:** Client listing shortcode (displayClientsShortcode)
**Example:**
```php
// Source: src/Clients/Models/SitesModel.php lines 499-555
public function hydrateClients(array &$clients) {
    if (empty($clients)) return;

    $clientIds = array_column($clients, 'id');
    $headSites = $this->getHeadSitesForClients($clientIds);  // Cached fetch

    foreach ($clients as &$row) {
        $clientId = (int) ($row['id'] ?? 0);
        if (!$clientId || !isset($headSites[$clientId])) continue;

        $site = $headSites[$clientId];
        $row['head_site'] = $site;
        $row['site_id'] = $site['site_id'];
        $row['site_name'] = $site['site_name'];
        $row['client_town_id'] = $site['place_id'];

        // Get address data from location
        if (!empty($site['location'])) {
            $location = $site['location'];
            $row['client_street_address'] = $location['street_address'] ?? '';
            $row['client_suburb'] = $location['suburb'] ?? '';
            $row['client_postal_code'] = $location['postal_code'] ?? '';
            $row['client_province'] = $location['province'] ?? '';
            $row['client_town'] = $location['town'] ?? '';         // Used in Town column
            $row['client_location'] = $location;
        }
    }
}
```

**Key Points:**
- Batch fetch head sites for all clients (one query, not N+1)
- Location data hydrated from `locations` table via `place_id`
- Cached in WordPress transient `wecoza_clients_head_sites_cache`
- Town column in clients table populated from this data

### Pattern 4: Location Hierarchy Cache (Province → Town → Suburb)
**What:** Three-level location dropdown cascade backed by cached hierarchy
**When to use:** Client forms with location selection
**Example:**
```php
// Source: src/Clients/Models/SitesModel.php lines 106-183
public function rebuildLocationCache() {
    $rows = $this->fetchAllLocations();  // SELECT * FROM locations ORDER BY province, town, suburb

    $hierarchy = array();
    foreach ($rows as $row) {
        $province = $row['province'];
        $town = $row['town'];
        $suburb = $row['suburb'];

        // Build nested structure: provinces → towns → suburbs
        if (!isset($provinceIndex[$province])) {
            $hierarchy[] = array('name' => $province, 'towns' => array());
        }
        if (!isset($townIndex[$province][$town])) {
            $hierarchy[$provincePos]['towns'][] = array('name' => $town, 'suburbs' => array());
        }
        $hierarchy[$provincePos]['towns'][$townPos]['suburbs'][] = array(
            'id' => $row['location_id'],
            'name' => $suburb,
            'postal_code' => $row['postal_code'],
            'street_address' => $row['street_address']
        );
    }

    $cache = array('hierarchy' => $hierarchy, 'map' => $map);
    set_transient('wecoza_clients_location_cache', $cache, 0);  // Never expires
    return $cache;
}
```

**Key Points:**
- Lazy-loaded via AJAX (`wecoza_get_locations` endpoint)
- JavaScript (`client-capture.js`) populates dropdowns from hierarchy
- Selection triggers auto-fill of postal code, street address
- Cache invalidated on location create/update

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Parent-child validation | Custom logic checking client_id match | Database trigger `trg_sites_same_client` | Prevents TOCTOU race conditions, enforced at DB level |
| Location data denormalization | Storing suburb/town/province in sites table | FK to `locations` table + hydration | Single source of truth, avoids sync issues |
| Site uniqueness within client | Application-level checks | Unique indexes on `(client_id, LOWER(site_name))` WHERE conditions | Database enforces uniqueness atomically |
| N+1 query problem for head sites | Loop calling `getHeadSite($clientId)` per row | Batch fetch via `getHeadSitesForClients($clientIds)` | Reduces 100 queries to 1 |
| Cache invalidation bugs | Manual cache clearing scattered in code | Centralized `refreshHeadSiteCache($clientIds)` called after CRUD | Single responsibility, easier to audit |

**Key insight:** Sites are NOT a standalone module — parent-child logic, location hydration, and caching are deeply integrated into the client workflow. Fighting this coupling creates more bugs than it solves.

## Common Pitfalls

### Pitfall 1: Treating Sites as Standalone Module
**What goes wrong:** Creating dedicated sites listing page, sites controller, sites shortcodes
**Why it happens:** Assumption that sites need their own UI because they have their own table
**How to avoid:** Follow legacy plugin pattern — sites are always accessed through client forms/listings
**Warning signs:** User story requesting "sites management page", tasks creating `SitesController`

### Pitfall 2: AJAX Action Name Mismatches (Learned from Phase 22-23)
**What goes wrong:** JS sends `action: 'save_sub_site'` but handler registered as `wp_ajax_wecoza_save_sub_site`
**Why it happens:** WordPress AJAX requires exact match between action value and hook suffix
**How to avoid:** Grep for `action:` in JS files, compare to `add_action('wp_ajax_` in PHP
**Warning signs:** AJAX returns 0 or 400, debug.log shows "Unknown action"

### Pitfall 3: Localization Key Inconsistencies (Learned from Phase 23)
**What goes wrong:** JS reads `wecozaClients.ajax_url` but PHP localizes `ajaxUrl` (camelCase)
**Why it happens:** Phase 22 standardized camelCase but legacy code used snake_case
**How to avoid:** Verify localization keys match between `wp_localize_script()` and JS usage
**Warning signs:** JS error "Cannot read property 'ajax_url' of undefined"

### Pitfall 4: N+1 Queries in Client Listing
**What goes wrong:** Loop calling `getHeadSite($clientId)` for each client row
**Why it happens:** Treating site hydration as one-off operation instead of batch
**How to avoid:** Use `getHeadSitesForClients($clientIds)` for batch fetch, called once before loop
**Warning signs:** Slow page load, debug log shows 100+ identical queries

### Pitfall 5: Head Site Cache Staleness
**What goes wrong:** Client updated but listing still shows old site name/location
**Why it happens:** Cache not refreshed after site save
**How to avoid:** Every site save must call `refreshHeadSiteCache([$clientId])`
**Warning signs:** User reports "I changed the site name but it still shows the old one"

### Pitfall 6: Sub-Site Validation Bypass
**What goes wrong:** Sub-site created with parent from different client
**Why it happens:** Validation uses wrong client_id (new sub-client vs existing parent's client)
**How to avoid:** Pass `$expectedClientId` to `validateSubSite()` for new sub-clients
**Warning signs:** Database trigger error "Parent client_id does not match child client_id"

### Pitfall 7: Location Hierarchy Not Loaded
**What goes wrong:** Province/Town/Suburb dropdowns empty on form render
**Why it happens:** Hierarchy lazy-loaded via AJAX but not triggered on page load
**How to avoid:** Client forms auto-trigger `wecoza_get_locations` if hierarchy not in localization
**Warning signs:** Empty dropdowns, JS error "hierarchy is not defined"

## Code Examples

### Example 1: Head Site Creation (Client Save Handler)
```php
// Source: src/Clients/Ajax/ClientAjaxHandlers.php lines 117-155
// Context: After client saved, create/update head site
$siteData = $payload['site'];
$siteData['site_name_fallback'] = $clientData['client_name'] ?? '';

if (!empty($siteData['parent_site_id'])) {
    // Sub-site flow (see Example 2)
} else {
    // Head site flow
    $siteId = $sitesModel->saveHeadSite($clientId, $siteData);
    if (!$siteId) {
        AjaxSecurity::sendError('Failed to save site details. Please try again.');
    }
}

// Log communication if status changed
if ($communicationType !== '') {
    $communicationsModel = $model->getCommunicationsModel();
    $latestType = $communicationsModel->getLatestCommunicationType($clientId);
    if ($latestType !== $communicationType) {
        $communicationsModel->logCommunication($clientId, $siteId, $communicationType);
    }
}
```

### Example 2: Sub-Site Validation and Save
```php
// Source: src/Clients/Ajax/ClientAjaxHandlers.php lines 79-88
// Validate sub-site with expected client ID for new sub-clients
if (!empty($siteData['parent_site_id'])) {
    $expectedClientId = null;
    if ($isNew && !empty($clientData['main_client_id'])) {
        $expectedClientId = $clientData['main_client_id'];  // Parent client's ID
    }
    $siteErrors = $sitesModel->validateSubSite($clientId, $siteData['parent_site_id'], $siteData, $expectedClientId);
}

// Save sub-site with fallback to head site
if (!empty($siteData['parent_site_id'])) {
    $saveOptions = array('fallback_to_head_site' => true);
    if (!empty($expectedClientId)) {
        $saveOptions['expected_client_id'] = (int) $expectedClientId;
    }

    $subSiteResult = $sitesModel->saveSubSite($clientId, $siteData['parent_site_id'], $siteData, $saveOptions);
    if (!$subSiteResult) {
        AjaxSecurity::sendError('Failed to save sub-site details. Please try again.');
    }
}
```

### Example 3: Batch Head Site Fetch with Cache
```php
// Source: src/Clients/Models/SitesModel.php lines 349-363
public function getHeadSitesForClients(array $clientIds) {
    $ids = array_filter(array_map('intval', $clientIds));
    if (empty($ids)) return array();

    $cache = $this->primeHeadSiteCache($ids);  // Batch fetch missing entries
    $map = array();
    foreach ($ids as $clientId) {
        if (!empty($cache['map'][$clientId])) {
            $map[$clientId] = $cache['map'][$clientId];
        }
    }
    return $map;
}

// Usage in client listing
$sitesModel = new SitesModel();
$sitesModel->hydrateClients($clients);  // Adds head_site data to each client row
```

### Example 4: Location Hierarchy Cascade (Form View)
```php
// Source: views/clients/components/client-capture-form.view.php lines 210-246
// Province dropdown
echo ViewHelpers::renderField('select', 'client_province', 'Province',
    $selected_province,
    array(
        'required' => true,
        'col_class' => 'col-md-3 js-province-field',
        'class' => 'js-province-select',
        'options' => $province_options,
        'error' => $errors['client_province'] ?? ''
    )
);

// Town dropdown (hidden until province selected)
echo ViewHelpers::renderField('select', 'client_town', 'Town',
    $selected_town,
    array(
        'required' => true,
        'col_class' => 'col-md-3 js-town-field' . ($has_province ? '' : ' d-none'),
        'class' => 'js-town-select',
        'options' => $town_options,
    )
);

// Suburb dropdown (hidden until town selected, populates place_id)
echo ViewHelpers::renderField('select', 'client_town_id', 'Suburb',
    $selected_location_id,
    array(
        'required' => true,
        'col_class' => 'col-md-3 js-suburb-field' . ($has_town ? '' : ' d-none'),
        'class' => 'js-suburb-select',
        'options' => $suburb_options,
        'error' => $errors['client_town_id'] ?? ''
    )
);
```

### Example 5: AJAX Endpoints Registration
```php
// Source: src/Clients/Ajax/ClientAjaxHandlers.php lines 46-52
protected function registerHandlers() {
    // Site management AJAX handlers
    add_action('wp_ajax_wecoza_save_sub_site', array($this, 'saveSubSite'));
    add_action('wp_ajax_wecoza_get_head_sites', array($this, 'getHeadSites'));
    add_action('wp_ajax_wecoza_get_sub_sites', array($this, 'getSubSites'));
    add_action('wp_ajax_wecoza_delete_sub_site', array($this, 'deleteSubSite'));
    add_action('wp_ajax_wecoza_get_sites_hierarchy', array($this, 'getSitesHierarchy'));
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Standalone sites module with dedicated UI | Sites embedded in client forms | Legacy design (pre-2026) | Simpler UX, fewer navigation steps |
| Store location text in sites table | FK to locations + hydration | Phase 21 migration | Single source of truth, no sync bugs |
| Per-client cache queries | Batch fetch with transient cache | Phase 21 migration | Reduced queries from N to 1 |
| Snake_case localization keys | camelCase keys | Phase 22 standardization | Consistency with modern JS conventions |

**Deprecated/outdated:**
- Separate sites listing page: Never existed in legacy, don't create
- `DatabaseService` class: Replaced by `wecoza_db()` in Phase 21
- `wp_ajax_nopriv_*` handlers: Entire WP environment requires login (CLAUDE.md)

## Verification Strategy (Learned from Phases 22-23)

### Phase 22 Bugs Found
1. AJAX action name mismatches (JS vs PHP handler)
2. Missing PostgresConnection CRUD methods
3. BaseModel static property conflicts
4. Column mapping inconsistencies

### Phase 23 Bugs Found
1. AJAX action name: `check_location_duplicates` vs `wecoza_check_location_duplicates`
2. Localization key: `ajax_url` vs `ajaxUrl` (snake_case vs camelCase)
3. Unnecessary `wp_ajax_nopriv_*` handler for authenticated-only action
4. Error handling hiding submit button when should show

### Phase 24 Verification Checklist
Apply same debugging pattern to sites:

**1. AJAX Action Name Consistency**
- Grep JS files for `action:` values
- Compare to `add_action('wp_ajax_` registrations
- Verify exact match (including `wecoza_` prefix)

**2. Localization Key Consistency**
- Check `wp_localize_script()` in ClientsController
- Verify JS reads same keys (camelCase: `ajaxUrl`, `actions.save`)
- Confirm nonce key matches: `nonce` → `clients_nonce_action`

**3. Form Field Name Consistency**
- Verify `<input name="site_name">` matches `$_POST['site_name']` in handler
- Check `client_town_id` (location dropdown) propagates to `$siteData['place_id']`
- Confirm hidden field `head_site_id` matches handler expectations

**4. Database Trigger Enforcement**
- Verify `trg_sites_same_client` prevents cross-client parent-child
- Test creating sub-site with mismatched parent (should fail with exception)

**5. Cache Invalidation**
- Verify `refreshHeadSiteCache()` called after head site save
- Test: Update site name, refresh client listing, confirm change visible

**6. Client Hydration**
- Verify clients listing calls `SitesModel::hydrateClients()`
- Check Town column populated from `$client['client_town']`
- Confirm batch fetch not N+1 queries

## Open Questions

1. **Sub-Site AJAX Endpoints Usage**
   - What we know: 5 endpoints registered (`save_sub_site`, `get_head_sites`, etc.)
   - What's unclear: Are these used by client forms or are they for future features?
   - Recommendation: Test each endpoint via WP-CLI, verify they work but may not be actively called by current UI

2. **Location Hierarchy Lazy Loading**
   - What we know: Hierarchy lazy-loaded via AJAX if not in initial localization
   - What's unclear: Does client form always trigger load, or are there cases where dropdowns stay empty?
   - Recommendation: Test form render with empty localization, verify AJAX fires automatically

3. **Sub-Site Delete Restriction**
   - What we know: FK constraint `ON DELETE RESTRICT` prevents deleting parent with children
   - What's unclear: Does UI handle this gracefully or show raw error?
   - Recommendation: Test deleting main client with sub-clients, verify error message is user-friendly

## Sources

### Primary (HIGH confidence)
- **Source Code Analysis:** `src/Clients/Models/SitesModel.php` (860 lines, complete implementation)
- **AJAX Handlers:** `src/Clients/Ajax/ClientAjaxHandlers.php` (5 endpoints: lines 47-52, 448-575)
- **Database Schema:** `schema/wecoza_db_schema_bu_feb_12.sql` (lines 4620-4628, 5044-5094, 7108-7734)
- **Phase 22 Bugs:** `.planning/phases/22-client-management/22-02-PLAN.md` (AJAX wiring fixes)
- **Phase 23 Bugs:** `.planning/phases/23-location-management/23-02-PLAN.md` (Action names, localization keys)
- **User Context:** `.planning/phases/24-sites-hierarchy/24-CONTEXT.md` (All implementation decisions locked)

### Secondary (MEDIUM confidence)
- **Legacy Plugin Pattern:** Phase 21 migration notes confirm no standalone sites UI existed
- **Client Form Views:** `views/clients/components/client-capture-form.view.php` (lines 126, 162-204 for site fields)
- **Client JS:** `assets/js/clients/client-capture.js` (location hierarchy cascade, lines 110-350)

### Tertiary (LOW confidence)
None — all findings based on direct source code analysis

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All code migrated in Phase 21, fully analyzed
- Architecture: HIGH - Patterns extracted from working SitesModel implementation
- Pitfalls: HIGH - Directly learned from Phase 22-23 bug fixes
- Integration points: HIGH - Form views and AJAX handlers fully mapped

**Research approach:**
1. Read Phase 22-23 RESEARCH.md and PLAN files to learn debugging pattern
2. Analyze SitesModel.php for complete hierarchy implementation
3. Trace AJAX endpoints in ClientAjaxHandlers.php
4. Map form fields in client-capture-form.view.php to handler POST keys
5. Verify database schema for triggers, constraints, indexes
6. Extract bug patterns from Phase 22-23 fixes to apply to Phase 24

**Research date:** 2026-02-12
**Valid until:** 60 days (stable legacy migration pattern, unlikely to change)
