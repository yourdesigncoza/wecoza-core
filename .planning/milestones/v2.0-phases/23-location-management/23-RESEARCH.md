# Phase 23: Location Management - Research

**Researched:** 2026-02-11
**Domain:** Google Maps Places Autocomplete Integration, Location CRUD, Geocoding, Duplicate Detection
**Confidence:** HIGH

## Summary

Phase 23 implements Location Management — a standalone CRUD system for geographic locations (suburb, town, province, postal code, geocoordinates) with Google Maps Places autocomplete for easy data entry. This is the third module in the Clients Integration project (Phase 21: Foundation, Phase 22: Client CRUD, Phase 23: Location Management).

**All code already exists** in the source plugin at `.integrate/wecoza-clients-plugin/` and was structurally migrated in Phase 21. Phase 23 verifies, debugs, and activates the location-specific functionality following the same testing pattern as Phase 22.

**Core Components:**
1. **Location CRUD:** Create, read, update locations with address fields and coordinates
2. **Google Maps Autocomplete:** Optional integration when API key configured (uses modern PlaceAutocompleteElement)
3. **Duplicate Detection:** AJAX endpoint checks for similar addresses before saving
4. **Manual Entry Fallback:** All fields editable manually when Google Maps unavailable
5. **Location Hierarchy:** Cached province → town → suburb structure for fast dropdowns

**Primary recommendation:** Test each shortcode systematically (capture, list, edit), verify Google Maps integration with and without API key, confirm duplicate detection works, ensure location hierarchy cache refreshes correctly. Focus on data integrity (coordinate validation, duplicate warnings) and graceful degradation (manual entry when autocomplete fails).

## Standard Stack

### Already Migrated in Phase 21
| Component | Location | Purpose | Status |
|-----------|----------|---------|--------|
| LocationsController | `src/Clients/Controllers/` | 3 shortcodes, Google Maps enqueue | ✓ Migrated |
| LocationsModel | `src/Clients/Models/` | Validation, CRUD, duplicate detection | ✓ Migrated |
| SitesModel | `src/Clients/Models/` | Location hierarchy caching | ✓ Migrated |
| View Templates (3) | `views/clients/` | location-capture-form, locations-list, edit | ✓ Migrated |
| JavaScript (2) | `assets/js/clients/` | location-capture.js, locations-list.js | ✓ Migrated |

### Core Dependencies
| Component | Version | Purpose | Usage Pattern |
|-----------|---------|---------|---------------|
| Google Maps JavaScript API | Latest (weekly) | Maps & Places libraries | Loaded async with API key |
| PlaceAutocompleteElement | 2025+ | Modern autocomplete widget | Replaces deprecated Autocomplete |
| PostgresConnection | Core 1.0.0 | Database access | Via `wecoza_db()` |
| ViewHelpers | Clients 1.0.0 | Form field rendering | Shared with Client forms |

### Database Schema (Already Exists)
**Table:** `public.locations`

```sql
CREATE TABLE public.locations (
    location_id integer PRIMARY KEY,
    street_address text,
    suburb varchar(50),
    town varchar(50),
    province varchar(50),
    postal_code varchar(10),
    longitude numeric(9,6),
    latitude numeric(9,6),
    created_at timestamp DEFAULT now(),
    updated_at timestamp DEFAULT now(),
    CONSTRAINT locations_street_address_nonblank CHECK (street_address IS NULL OR btrim(street_address) <> '')
);
```

**Key Relationships:**
- `sites.place_id` → `locations.location_id` (client sites reference locations)
- `agents.preferred_working_area_1/2/3` → `locations.location_id` (agent territories)
- `learners.city_town_id` → `locations.location_id` (learner addresses)

**Installation:** Schema already exists in production. No migrations needed.

## Architecture Patterns

### Pattern 1: Google Maps PlaceAutocompleteElement (Modern API)
**What:** New autocomplete widget replacing deprecated google.maps.places.Autocomplete
**When to use:** All new Google Maps implementations (required as of March 1, 2025)
**Example:**
```javascript
// Source: Context7 /websites/developers_google_maps_javascript_place
// Modern approach with importLibrary
google.maps.importLibrary('places').then(function(library) {
    const placeAutocomplete = new library.PlaceAutocompleteElement({
        includedRegionCodes: ['za'],         // South Africa only
        requestedLanguage: 'en',
        requestedRegion: 'za'
    });

    placeAutocomplete.className = 'form-control form-control-sm';
    container.appendChild(placeAutocomplete);

    // Handle selection
    placeAutocomplete.addEventListener('gmp-select', function(event) {
        const place = event.placePrediction.toPlace();
        place.fetchFields({
            fields: ['addressComponents', 'location']
        }).then(function() {
            populateFromPlace(place.addressComponents, place.location);
        });
    });
});
```

**Current Implementation:** Verified in `assets/js/clients/location-capture.js` lines 66-118
- Uses PlaceAutocompleteElement (modern API)
- Restricts to South Africa ('za')
- Replaces original input with autocomplete element
- Handles gmp-select event for place selection
- Extracts address components and coordinates

### Pattern 2: Address Component Extraction
**What:** Parse Google Maps address_components array to populate form fields
**When to use:** After user selects autocomplete result
**Example:**
```javascript
// Source: Context7 - address component mapping
function populateFromPlace(components, location) {
    let streetNumber = '';
    let route = '';
    let suburb = '';
    let town = '';
    let province = '';
    let postalCode = '';

    components.forEach(function(component) {
        const types = component.types;

        if (types.includes('street_number')) {
            streetNumber = component.longText || component.long_name;
        }
        if (types.includes('route')) {
            route = component.longText || component.long_name;
        }
        if (types.includes('sublocality_level_1') ||
            types.includes('sublocality') ||
            types.includes('neighborhood')) {
            suburb = component.longText || component.long_name;
        }
        if (types.includes('locality') ||
            types.includes('administrative_area_level_2')) {
            town = component.longText || component.long_name;
        }
        if (types.includes('administrative_area_level_1')) {
            province = component.longText || component.long_name;
        }
        if (types.includes('postal_code')) {
            postalCode = component.longText || component.long_name;
        }
    });

    // Combine street_number + route for street_address
    const streetAddress = streetNumber && route
        ? streetNumber + ' ' + route
        : route;

    // Populate form fields
    $('#street_address').val(streetAddress);
    $('#suburb').val(suburb);
    $('#town').val(town);
    $('#province').val(province);
    $('#postal_code').val(postalCode);

    // Extract coordinates
    const lat = location.lat();
    const lng = location.lng();
    $('#latitude').val(lat.toFixed(6));
    $('#longitude').val(lng.toFixed(6));
}
```

**Current Implementation:** Verified in `location-capture.js` lines 130-218
- Handles both longText (new API) and long_name (backward compat)
- Maps Google types to form fields
- Combines street_number + route for street_address
- Province lookup handles variations (e.g., "Gauteng" vs "gauteng")
- Coordinates formatted to 6 decimal places

### Pattern 3: Conditional Asset Loading (Performance Best Practice)
**What:** Only enqueue Google Maps API on pages with location shortcodes
**When to use:** All external libraries with performance impact
**Example:**
```php
// File: src/Clients/Controllers/LocationsController.php
public function enqueueAssets() {
    global $post;

    if (!is_a($post, 'WP_Post')) {
        return;
    }

    // Check for location-related shortcodes
    $hasCapture = has_shortcode($post->post_content, 'wecoza_locations_capture');
    $hasList = has_shortcode($post->post_content, 'wecoza_locations_list');
    $hasEdit = has_shortcode($post->post_content, 'wecoza_locations_edit');

    if (!$hasCapture && !$hasList && !$hasEdit) {
        return; // No location shortcodes - don't load assets
    }

    $googleMapsKey = $this->getGoogleMapsApiKey();

    // Only load Google Maps if API key configured
    if ($googleMapsKey && ($hasCapture || $hasEdit)) {
        wp_enqueue_script(
            'google-maps-api',
            'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($googleMapsKey)
                . '&libraries=places&loading=async&v=weekly',
            [],
            null,
            true
        );
    }

    // Load location-capture.js with dependencies
    if ($hasCapture || $hasEdit) {
        $dependencies = ['jquery'];
        if ($googleMapsKey) {
            $dependencies[] = 'google-maps-api';
        }

        wp_enqueue_script(
            'wecoza-location-capture',
            wecoza_asset_url('js/clients/location-capture.js'),
            $dependencies,
            WECOZA_CORE_VERSION,
            true
        );

        // Pass config to JavaScript
        wp_localize_script('wecoza-location-capture', 'wecoza_locations', [
            'provinces' => $provinceOptions,
            'googleMapsEnabled' => (bool) $googleMapsKey,
            'messages' => [
                'autocompleteUnavailable' => __('Google Maps autocomplete is unavailable...', 'wecoza-clients'),
                'selectProvince' => __('Please choose a province.', 'wecoza-clients'),
                'requiredFields' => __('Please complete all required fields.', 'wecoza-clients'),
            ]
        ]);
    }
}
```

**Current Implementation:** Verified in `LocationsController.php` lines 30-129
- Checks `has_shortcode()` before loading ANY assets
- Google Maps only loaded if API key exists AND shortcode present
- JavaScript dependencies declared properly (jquery, optional google-maps-api)
- wp_localize_script provides runtime config
- Prevents loading on non-location pages (performance win)

### Pattern 4: Duplicate Detection with Fuzzy Matching
**What:** AJAX endpoint finds similar addresses before saving (warns user, doesn't block)
**When to use:** Before submitting location form
**Example:**
```php
// File: src/Clients/Models/LocationsModel.php
public function checkDuplicates($streetAddress, $suburb, $town) {
    $conditions = [];
    $params = [];

    // Town: search both town AND suburb columns (handles misclassification)
    if (!empty($town)) {
        $conditions[] = '(LOWER(town) LIKE LOWER(:town_search) OR LOWER(suburb) LIKE LOWER(:town_search_suburb))';
        $params[':town_search'] = '%' . $town . '%';
        $params[':town_search_suburb'] = '%' . $town . '%';
    }

    // Suburb: search both suburb AND town columns
    if (!empty($suburb)) {
        $conditions[] = '(LOWER(suburb) LIKE LOWER(:suburb_search) OR LOWER(town) LIKE LOWER(:suburb_search_town))';
        $params[':suburb_search'] = '%' . $suburb . '%';
        $params[':suburb_search_town'] = '%' . $suburb . '%';
    }

    // Street address: exact match first, then LIKE
    if (!empty($streetAddress)) {
        $conditions[] = '(LOWER(street_address) = LOWER(:street_exact) OR LOWER(street_address) LIKE LOWER(:street_like))';
        $params[':street_exact'] = trim($streetAddress);
        $params[':street_like'] = '%' . trim($streetAddress) . '%';
    }

    if (empty($conditions)) {
        return [];
    }

    $sql = 'SELECT location_id, street_address, suburb, town, province, postal_code
            FROM public.locations
            WHERE ' . implode(' OR ', $conditions) . '
            ORDER BY street_address, suburb, town
            LIMIT 10';

    return DatabaseService::getAll($sql, $params);
}
```

**Current Implementation:** Verified in `LocationsModel.php` lines 126-166
- Searches with ILIKE (case-insensitive PostgreSQL)
- Cross-column search (suburb might be in town column and vice versa)
- Exact match + partial match for street address
- Returns up to 10 matches (enough to warn, not overwhelming)
- JavaScript displays warnings but allows override (submit button shows after check)

### Pattern 5: Coordinate Validation
**What:** Validate latitude/longitude ranges before saving
**When to use:** Location model validation
**Example:**
```php
// File: src/Clients/Models/LocationsModel.php
public function validate(array $data, $id = null) {
    $errors = [];

    // Normalize coordinates (handles comma decimal separator)
    $longitude = $this->normalizeCoordinate($data['longitude']);
    $latitude = $this->normalizeCoordinate($data['latitude']);

    // Validate longitude
    if ($longitude === null) {
        $errors['longitude'] = __('Please provide a valid longitude.', 'wecoza-clients');
    } elseif ($longitude < -180 || $longitude > 180) {
        $errors['longitude'] = __('Longitude must be between -180 and 180.', 'wecoza-clients');
    }

    // Validate latitude
    if ($latitude === null) {
        $errors['latitude'] = __('Please provide a valid latitude.', 'wecoza-clients');
    } elseif ($latitude < -90 || $latitude > 90) {
        $errors['latitude'] = __('Latitude must be between -90 and 90.', 'wecoza-clients');
    }

    return $errors;
}

protected function normalizeCoordinate($value) {
    if ($value === null) return null;

    $value = trim((string) $value);
    if ($value === '') return null;

    // Replace comma with period (handle European decimal notation)
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float) $value : null;
}
```

**Current Implementation:** Verified in `LocationsModel.php` lines 17-75, 168-181
- Handles both period and comma decimal separators
- Validates against geographic ranges (lat: -90 to 90, lng: -180 to 180)
- Returns null for invalid input (triggers error in validation)
- JavaScript also validates client-side (provides immediate feedback)

### Pattern 6: Location Hierarchy Caching
**What:** Cache province → town → suburb structure in WordPress transient for fast dropdown loading
**When to use:** Building location selects in client forms
**Example:**
```php
// File: src/Clients/Models/SitesModel.php
public function getLocationHierarchy() {
    // Try cache first
    $cached = get_transient('wecoza_clients_location_cache');
    if ($cached !== false) {
        return $cached;
    }

    // Build hierarchy from locations table
    $sql = 'SELECT location_id, suburb, town, province, postal_code
            FROM public.locations
            ORDER BY province, town, suburb';
    $locations = wecoza_db()->getAll($sql);

    $hierarchy = [];
    foreach ($locations as $loc) {
        $province = $loc['province'];
        $town = $loc['town'];
        $suburb = $loc['suburb'];

        // Initialize province
        if (!isset($hierarchy[$province])) {
            $hierarchy[$province] = [
                'name' => $province,
                'towns' => []
            ];
        }

        // Initialize town
        if (!isset($hierarchy[$province]['towns'][$town])) {
            $hierarchy[$province]['towns'][$town] = [
                'name' => $town,
                'suburbs' => []
            ];
        }

        // Add suburb
        $hierarchy[$province]['towns'][$town]['suburbs'][] = [
            'id' => $loc['location_id'],
            'name' => $suburb,
            'postal_code' => $loc['postal_code']
        ];
    }

    // Cache for 1 hour
    set_transient('wecoza_clients_location_cache', $hierarchy, HOUR_IN_SECONDS);

    return $hierarchy;
}

public function refreshLocationCache() {
    delete_transient('wecoza_clients_location_cache');
}
```

**Current Implementation:** Verified in `SitesModel.php` (full implementation)
- Transient cache expires after 1 hour
- LocationsModel calls refreshLocationCache() after create/update
- Hierarchy structure enables cascading dropdowns (province → town → suburb)
- Reduces database queries on client forms

### Pattern 7: Graceful Degradation (Manual Entry)
**What:** Show manual input fields when Google Maps autocomplete unavailable
**When to use:** API key missing, API quota exceeded, network issues
**Example:**
```php
// File: views/clients/components/location-capture-form.view.php
<?php if (!$google_maps_enabled) : ?>
    <?php echo ViewHelpers::renderAlert(
        __('Google Maps autocomplete is not configured. You can still complete all fields manually.', 'wecoza-clients'),
        'warning'
    ); ?>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <label for="wecoza_clients_google_address_search" class="form-label">Search Address</label>
        <div id="wecoza_clients_google_address_container" class="position-relative">
            <input
                type="text"
                id="wecoza_clients_google_address_search"
                class="form-control form-control-sm"
                placeholder="Start typing an address...">
            <!-- Loading/success indicators -->
        </div>
        <small class="text-muted d-block mt-2">
            Use the address search to auto-fill suburb, town, province, and coordinates.
        </small>
    </div>
</div>

<!-- All fields remain editable even after autocomplete -->
<div class="row g-3">
    <?php
    echo ViewHelpers::renderField('text', 'street_address', 'Street Address',
        $location['street_address'] ?? '', ['required' => true, 'col_class' => 'col-md-4']);
    echo ViewHelpers::renderField('text', 'suburb', 'Suburb',
        $location['suburb'] ?? '', ['required' => true, 'col_class' => 'col-md-4']);
    echo ViewHelpers::renderField('text', 'town', 'Town / City',
        $location['town'] ?? '', ['required' => true, 'col_class' => 'col-md-4']);
    ?>
</div>
```

**Current Implementation:** Verified in `location-capture-form.view.php` lines 34-82
- Warning alert shown when `$google_maps_enabled` is false
- Autocomplete field always present (degrades to plain input)
- All fields remain editable after autocomplete selection
- Manual coordinate entry with validation help text

### Pattern 8: Duplicate Check Gating
**What:** Hide submit button until duplicate check performed (prevents accidental duplicates)
**When to use:** Location create/edit forms
**Example:**
```javascript
// File: assets/js/clients/location-capture.js (in view template)
var checkDuplicateBtn = document.getElementById('check_duplicate_btn');
var submitBtn = document.getElementById('submit_location_btn');

// Initially hide submit button
submitBtn.classList.add('d-none');

// Show submit only after duplicate check
checkDuplicateBtn.addEventListener('click', function() {
    // Run AJAX duplicate check
    fetch(wecoza_ajax.ajax_url, {
        method: 'POST',
        body: formData
    }).then(function(response) {
        return response.json();
    }).then(function(result) {
        if (result.success) {
            var duplicates = result.data.duplicates || [];

            if (duplicates.length > 0) {
                // Show warning with duplicate list
                showDuplicateResults(duplicates);
            } else {
                // No duplicates
                showDuplicateAlert('No duplicate locations found.', 'success');
            }

            // Allow submit regardless (user informed, can decide)
            showSubmit();
        }
    });
});

// Hide submit if form changed after duplicate check
['street_address', 'suburb', 'town', 'province'].forEach(function(id) {
    var field = document.getElementById(id);
    if (field) {
        field.addEventListener('input', function() {
            hideSubmit();
            clearDuplicateResults();
        });
    }
});
```

**Current Implementation:** Verified in `location-capture-form.view.php` lines 123-334
- Submit button hidden by default (d-none class)
- Check Duplicates button triggers AJAX
- Submit shows after check (whether duplicates found or not)
- Form changes re-hide submit (forces re-check)
- UX: encourages users to check before saving, prevents accidental duplicates

### Anti-Patterns to Avoid

- **Don't use deprecated google.maps.places.Autocomplete:** PlaceAutocompleteElement required for new implementations (as of March 2025)
- **Don't hardcode API keys in JavaScript:** Pass via wp_localize_script or WordPress options
- **Don't block form submission on duplicates:** Warn user but allow override (they may have legitimate reason)
- **Don't skip coordinate validation:** Invalid coordinates break mapping features, always validate ranges
- **Don't forget to refresh location cache:** After location create/update, call refreshLocationCache()
- **Don't load Google Maps on every page:** Use has_shortcode() conditional loading
- **Don't assume autocomplete always works:** Provide manual entry fallback

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Address parsing from freeform text | Custom regex address parser | Google Places Autocomplete | Handles international formats, business names, landmarks |
| Geocoding (address → coordinates) | Custom geocoding service | Google Maps Geocoder (via Places) | Accurate, current, handles ambiguous addresses |
| Province/town/suburb validation | Hardcoded lists | locations table + hierarchy cache | Data-driven, handles additions without code changes |
| Duplicate address detection | Simple string comparison | Fuzzy ILIKE with cross-column search | Handles typos, variations, misclassification |
| Coordinate validation | Basic number check | Range validation + normalization | Catches invalid coordinates before database save |
| Location hierarchy dropdowns | Manual nested SQL queries | Cached getLocationHierarchy() | Fast, reduces database load |

**Key insight:** Location data is complex and changes frequently. Use external services (Google Maps) for geocoding/validation, cache hierarchies for performance, and use flexible fuzzy matching for duplicates rather than exact comparisons.

## Common Pitfalls

### Pitfall 1: Google Maps API Key Not Set
**What goes wrong:** Autocomplete field doesn't appear, JavaScript console shows "Google is not defined"
**Why it happens:** WordPress option `wecoza_google_maps_api_key` empty or missing
**How to avoid:**
1. Set API key: WP Admin → Settings → WeCoza Settings → Google Maps API Key
2. Or via WP-CLI: `wp option update wecoza_google_maps_api_key 'YOUR_API_KEY'`
3. Verify option exists: `wp option get wecoza_google_maps_api_key`
4. LocationsController checks for key before enqueuing Google Maps script
**Warning signs:** Plain text input instead of autocomplete widget, no Google Maps errors in console, "autocomplete unavailable" alert shows

### Pitfall 2: PlaceAutocompleteElement Not Replacing Input
**What goes wrong:** Autocomplete widget doesn't render, original input remains visible
**Why it happens:** JavaScript DOM manipulation timing issue, Google library not loaded
**How to avoid:**
```javascript
// CORRECT: Wait for Google Maps to load
window.WeCozaClients.Location.waitForGoogleMaps(function() {
    window.WeCozaClients.Location.initializeAutocomplete();
});

// WRONG: Try to use immediately (google.maps might not be defined)
google.maps.importLibrary('places').then(...); // Fails if called before API loaded
```
**Warning signs:** Both original input and autocomplete visible, console error "google.maps is not defined"

### Pitfall 3: Address Components Missing for South African Addresses
**What goes wrong:** Autocomplete doesn't populate suburb or town, coordinates missing
**Why it happens:** Google's address component structure varies by country
**How to avoid:**
- Check for MULTIPLE component types per field (e.g., suburb can be 'sublocality', 'sublocality_level_1', or 'neighborhood')
- Always check both longText (new API) and long_name (backward compat)
- Log component types when debugging: `console.log(component.types)`
- South Africa specifics:
  - Suburb: Often 'sublocality_level_1' or 'neighborhood'
  - Town: Usually 'locality' or 'administrative_area_level_2'
  - Province: 'administrative_area_level_1'
**Warning signs:** Some fields populate but not others, rural addresses fail more than urban

### Pitfall 4: Duplicate Check AJAX Nonce Mismatch
**What goes wrong:** Duplicate check always fails with "Security check failed"
**Why it happens:** Nonce action mismatch between form nonce and AJAX verification
**How to avoid:**
```php
// Form nonce (in view):
wp_nonce_field('submit_locations_form', 'wecoza_locations_form_nonce');

// AJAX verification (in Controller):
public function ajaxCheckLocationDuplicates() {
    $nonce = $_POST['nonce'] ?? '';

    // MUST use same action as form nonce
    if (!wp_verify_nonce($nonce, 'submit_locations_form')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
}

// JavaScript (send form nonce):
formData.append('nonce', document.querySelector('#wecoza_locations_form_nonce').value);
```
**Warning signs:** Valid user gets 403 on duplicate check, "Security check failed" in response

### Pitfall 5: Location Cache Not Refreshed After Insert
**What goes wrong:** New location doesn't appear in client form dropdowns
**Why it happens:** LocationsModel creates location but forgets to call refreshLocationCache()
**How to avoid:**
```php
// In LocationsModel::create():
public function create(array $data) {
    // ... validation, insert logic
    $locationId = wecoza_db()->insert($this->table, $payload);

    if (!$locationId) {
        return false;
    }

    // CRITICAL: Refresh cache
    $this->sitesModel->refreshLocationCache();

    return $this->getById($locationId);
}
```
**Warning signs:** New locations don't appear in dropdowns until transient expires (1 hour), manual cache clear needed

### Pitfall 6: Coordinate Comma vs Period Decimal Separator
**What goes wrong:** European users enter "52,1234" (comma), validation fails
**Why it happens:** JavaScript Number() expects period, database numeric expects period
**How to avoid:**
```php
// Model normalization (handles both):
protected function normalizeCoordinate($value) {
    $value = trim((string) $value);
    if ($value === '') return null;

    // Replace comma with period
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float) $value : null;
}

// JavaScript validation (also handle both):
function validateCoordinate(value, type) {
    // Replace comma with period
    value = value.replace(',', '.');
    var num = parseFloat(value);
    if (isNaN(num)) return false;

    if (type === 'lat') {
        return num >= -90 && num <= 90;
    } else {
        return num >= -180 && num <= 180;
    }
}
```
**Warning signs:** Validation errors on valid coordinates, non-English users report issues

### Pitfall 7: Submit Button Stays Hidden After Network Error
**What goes wrong:** User clicks "Check Duplicates", network error occurs, submit never shows
**Why it happens:** JavaScript catch block doesn't call showSubmit()
**How to avoid:**
```javascript
fetch(wecoza_ajax.ajax_url, {
    method: 'POST',
    body: formData
}).then(function(response) {
    // ... handle success
    showSubmit();
}).catch(function(error) {
    showDuplicateAlert('Error checking duplicates: ' + error.message, 'danger');

    // CRITICAL: Show submit on error too (let user proceed)
    showSubmit();
}).finally(function() {
    // Reset button state
    checkDuplicateBtn.disabled = false;
    checkDuplicateBtn.innerHTML = '<i class="fas fa-search me-1"></i> Check Duplicates';
});
```
**Warning signs:** Submit button stuck hidden after network timeout, users can't save

### Pitfall 8: Province Name Mismatch (Google vs Database)
**What goes wrong:** Autocomplete selects "Gauteng", but dropdown shows blank (value doesn't match)
**Why it happens:** Google returns "Gauteng", but database has "gauteng" or vice versa
**How to avoid:**
```javascript
// Normalize province with lookup table
var provinceLookup = {};
config.provinces.forEach(function(province) {
    provinceLookup[province.toLowerCase()] = province;
});

// When setting province from Google:
var googleProvince = component.longText; // "Gauteng"
var canonicalProvince = provinceLookup[googleProvince.toLowerCase()]; // Gets "Gauteng" from lookup

if (canonicalProvince) {
    provinceSelect.val(canonicalProvince).trigger('change');
}
```
**Warning signs:** Province field blank after autocomplete, manual correction needed every time

## Code Examples

### Example 1: Location Capture Shortcode Handler
```php
// File: src/Clients/Controllers/LocationsController.php
namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Clients\Models\LocationsModel;

class LocationsController extends BaseController
{
    protected $model;

    public function __construct() {
        add_shortcode('wecoza_locations_capture', [$this, 'captureLocationShortcode']);
        add_shortcode('wecoza_locations_list', [$this, 'listLocationsShortcode']);
        add_shortcode('wecoza_locations_edit', [$this, 'editLocationShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // AJAX handlers
        add_action('wp_ajax_check_location_duplicates', [$this, 'ajaxCheckLocationDuplicates']);
    }

    public function captureLocationShortcode($atts) {
        if (!current_user_can('manage_wecoza_clients')) {
            return '<p>' . esc_html__('Permission denied.', 'wecoza-clients') . '</p>';
        }

        $config = wecoza_config('clients');
        $provinces = array_values($config['province_options'] ?? []);
        $errors = [];
        $success = false;
        $location = [
            'street_address' => '',
            'suburb' => '',
            'town' => '',
            'province' => '',
            'postal_code' => '',
            'latitude' => '',
            'longitude' => '',
        ];

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wecoza_locations_form_nonce'])) {
            if (!wp_verify_nonce($_POST['wecoza_locations_form_nonce'], 'submit_locations_form')) {
                $errors['general'] = __('Security check failed.', 'wecoza-clients');
            } else {
                $result = $this->handleFormSubmission();
                if ($result['success']) {
                    $success = true;
                    $location = []; // Reset form
                } else {
                    $errors = $result['errors'];
                    $location = $result['data'];
                }
            }
        }

        return wecoza_view('clients/components/location-capture-form', [
            'errors' => $errors,
            'success' => $success,
            'location' => $location,
            'provinces' => $provinces,
            'google_maps_enabled' => (bool) $this->getGoogleMapsApiKey(),
        ], true);
    }

    protected function handleFormSubmission() {
        $data = $this->sanitizeFormData($_POST);
        $errors = $this->getModel()->validate($data);

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $created = $this->getModel()->create($data);

        if (!$created) {
            return [
                'success' => false,
                'errors' => ['general' => __('Failed to save location.', 'wecoza-clients')],
                'data' => $data,
            ];
        }

        return [
            'success' => true,
            'errors' => [],
            'data' => $data,
        ];
    }

    protected function sanitizeFormData($data) {
        return [
            'street_address' => isset($data['street_address']) ? sanitize_text_field($data['street_address']) : '',
            'suburb' => isset($data['suburb']) ? sanitize_text_field($data['suburb']) : '',
            'town' => isset($data['town']) ? sanitize_text_field($data['town']) : '',
            'province' => isset($data['province']) ? sanitize_text_field($data['province']) : '',
            'postal_code' => isset($data['postal_code']) ? sanitize_text_field($data['postal_code']) : '',
            'latitude' => isset($data['latitude']) ? sanitize_text_field(str_replace(',', '.', $data['latitude'])) : '',
            'longitude' => isset($data['longitude']) ? sanitize_text_field(str_replace(',', '.', $data['longitude'])) : '',
        ];
    }

    protected function getGoogleMapsApiKey() {
        return get_option('wecoza_google_maps_api_key', '');
    }
}
```

### Example 2: Duplicate Check AJAX Handler
```php
// File: src/Clients/Controllers/LocationsController.php
public function ajaxCheckLocationDuplicates() {
    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!wp_verify_nonce($nonce, 'submit_locations_form')) {
        wp_send_json_error(
            ['message' => __('Security check failed.', 'wecoza-clients')],
            403
        );
    }

    // Check capability
    if (!current_user_can('view_wecoza_clients')) {
        wp_send_json_error(
            ['message' => __('Permission denied.', 'wecoza-clients')],
            403
        );
    }

    // Sanitize inputs
    $streetAddress = isset($_POST['street_address']) ? sanitize_text_field(wp_unslash($_POST['street_address'])) : '';
    $suburb = isset($_POST['suburb']) ? sanitize_text_field(wp_unslash($_POST['suburb'])) : '';
    $town = isset($_POST['town']) ? sanitize_text_field(wp_unslash($_POST['town'])) : '';

    // Require at least one field
    if ($streetAddress === '' && $suburb === '' && $town === '') {
        wp_send_json_error(
            ['message' => __('Please provide an address to check.', 'wecoza-clients')],
            400
        );
    }

    // Check duplicates
    $duplicates = $this->getModel()->checkDuplicates($streetAddress, $suburb, $town);

    wp_send_json_success([
        'duplicates' => $duplicates
    ]);
}
```

### Example 3: Google Maps Asset Enqueue with Conditional Loading
```php
// File: src/Clients/Controllers/LocationsController.php
public function enqueueAssets() {
    global $post;

    if (!is_a($post, 'WP_Post')) {
        return;
    }

    $hasCapture = has_shortcode($post->post_content, 'wecoza_locations_capture');
    $hasList = has_shortcode($post->post_content, 'wecoza_locations_list');
    $hasEdit = has_shortcode($post->post_content, 'wecoza_locations_edit');

    if (!$hasCapture && !$hasList && !$hasEdit) {
        return;
    }

    $googleMapsKey = $this->getGoogleMapsApiKey();
    $config = wecoza_config('clients');
    $provinceOptions = array_values($config['province_options'] ?? []);

    // Enqueue for capture/edit
    if ($hasCapture || $hasEdit) {
        $dependencies = ['jquery'];

        // Only load Google Maps if API key configured
        if ($googleMapsKey) {
            if (!wp_script_is('google-maps-api', 'enqueued')) {
                wp_enqueue_script(
                    'google-maps-api',
                    'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($googleMapsKey) . '&libraries=places&loading=async&v=weekly',
                    [],
                    null,
                    true
                );
            }
            $dependencies[] = 'google-maps-api';
        }

        wp_enqueue_script(
            'wecoza-location-capture',
            wecoza_asset_url('js/clients/location-capture.js'),
            $dependencies,
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-location-capture', 'wecoza_locations', [
            'provinces' => $provinceOptions,
            'googleMapsEnabled' => (bool) $googleMapsKey,
            'messages' => [
                'autocompleteUnavailable' => __('Google Maps autocomplete unavailable.', 'wecoza-clients'),
                'selectProvince' => __('Please choose a province.', 'wecoza-clients'),
                'requiredFields' => __('Please complete all required fields.', 'wecoza-clients'),
            ],
        ]);

        wp_localize_script('wecoza-location-capture', 'wecoza_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    // Enqueue for list
    if ($hasList) {
        wp_enqueue_script(
            'wecoza-locations-list',
            wecoza_asset_url('js/clients/locations-list.js'),
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-locations-list', 'wecoza_locations_list', [
            'googleMapsEnabled' => (bool) $googleMapsKey,
        ]);
    }
}
```

### Example 4: Location Model with Validation and Duplicate Detection
```php
// File: src/Clients/Models/LocationsModel.php
namespace WeCoza\Clients\Models;

use WeCoza\Core\Abstract\BaseModel;

class LocationsModel extends BaseModel
{
    protected string $table = 'public.locations';
    protected $sitesModel;

    public function __construct() {
        $this->sitesModel = new SitesModel();
    }

    public function validate(array $data, $id = null) {
        $errors = [];

        $provinceOptions = wecoza_config('clients')['province_options'] ?? [];
        $provinceOptions = array_map('strtolower', $provinceOptions);

        // Street address
        if (empty($data['street_address'])) {
            $errors['street_address'] = __('Street address is required.', 'wecoza-clients');
        } elseif (strlen($data['street_address']) > 200) {
            $errors['street_address'] = __('Street address too long (max 200 chars).', 'wecoza-clients');
        }

        // Suburb
        if (empty($data['suburb'])) {
            $errors['suburb'] = __('Suburb is required.', 'wecoza-clients');
        } elseif (strlen($data['suburb']) > 50) {
            $errors['suburb'] = __('Suburb too long (max 50 chars).', 'wecoza-clients');
        }

        // Town
        if (empty($data['town'])) {
            $errors['town'] = __('Town is required.', 'wecoza-clients');
        } elseif (strlen($data['town']) > 50) {
            $errors['town'] = __('Town too long (max 50 chars).', 'wecoza-clients');
        }

        // Province
        if (empty($data['province'])) {
            $errors['province'] = __('Province is required.', 'wecoza-clients');
        } elseif ($provinceOptions && !in_array(strtolower($data['province']), $provinceOptions, true)) {
            $errors['province'] = __('Invalid province selected.', 'wecoza-clients');
        }

        // Postal code
        if (empty($data['postal_code'])) {
            $errors['postal_code'] = __('Postal code is required.', 'wecoza-clients');
        } elseif (strlen($data['postal_code']) > 10) {
            $errors['postal_code'] = __('Postal code too long (max 10 chars).', 'wecoza-clients');
        }

        // Coordinates
        $longitude = $this->normalizeCoordinate($data['longitude']);
        $latitude = $this->normalizeCoordinate($data['latitude']);

        if ($longitude === null) {
            $errors['longitude'] = __('Valid longitude required.', 'wecoza-clients');
        } elseif ($longitude < -180 || $longitude > 180) {
            $errors['longitude'] = __('Longitude must be -180 to 180.', 'wecoza-clients');
        }

        if ($latitude === null) {
            $errors['latitude'] = __('Valid latitude required.', 'wecoza-clients');
        } elseif ($latitude < -90 || $latitude > 90) {
            $errors['latitude'] = __('Latitude must be -90 to 90.', 'wecoza-clients');
        }

        // Check exact duplicate (all fields match)
        if (empty($errors) && $this->locationExists(
            $data['street_address'],
            $data['suburb'],
            $data['town'],
            $data['province'],
            $data['postal_code'],
            $id
        )) {
            $errors['general'] = __('This exact location already exists.', 'wecoza-clients');
        }

        return $errors;
    }

    public function create(array $data) {
        $longitude = $this->normalizeCoordinate($data['longitude']);
        $latitude = $this->normalizeCoordinate($data['latitude']);

        $payload = [
            'street_address' => $data['street_address'],
            'suburb' => $data['suburb'],
            'town' => $data['town'],
            'province' => $data['province'],
            'postal_code' => $data['postal_code'],
            'longitude' => $longitude,
            'latitude' => $latitude,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $locationId = wecoza_db()->insert($this->table, $payload);

        if (!$locationId) {
            return false;
        }

        // Refresh location cache
        $this->sitesModel->refreshLocationCache();

        return wecoza_db()->getRow(
            'SELECT location_id, street_address, suburb, town, province, postal_code, longitude, latitude
             FROM public.locations WHERE location_id = :id',
            [':id' => (int) $locationId]
        );
    }

    protected function normalizeCoordinate($value) {
        if ($value === null) return null;

        $value = trim((string) $value);
        if ($value === '') return null;

        // Handle comma decimal separator
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    public function checkDuplicates($streetAddress, $suburb, $town) {
        $conditions = [];
        $params = [];

        // Cross-column search for flexibility
        if (!empty($town)) {
            $conditions[] = '(LOWER(town) LIKE LOWER(:town_search) OR LOWER(suburb) LIKE LOWER(:town_search_suburb))';
            $params[':town_search'] = '%' . $town . '%';
            $params[':town_search_suburb'] = '%' . $town . '%';
        }

        if (!empty($suburb)) {
            $conditions[] = '(LOWER(suburb) LIKE LOWER(:suburb_search) OR LOWER(town) LIKE LOWER(:suburb_search_town))';
            $params[':suburb_search'] = '%' . $suburb . '%';
            $params[':suburb_search_town'] = '%' . $suburb . '%';
        }

        if (!empty($streetAddress)) {
            $conditions[] = '(LOWER(street_address) = LOWER(:street_exact) OR LOWER(street_address) LIKE LOWER(:street_like))';
            $params[':street_exact'] = trim($streetAddress);
            $params[':street_like'] = '%' . trim($streetAddress) . '%';
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = 'SELECT location_id, street_address, suburb, town, province, postal_code
                FROM public.locations
                WHERE ' . implode(' OR ', $conditions) . '
                ORDER BY street_address, suburb, town
                LIMIT 10';

        return wecoza_db()->getAll($sql, $params);
    }

    protected function locationExists($streetAddress, $suburb, $town, $province, $postalCode, $excludeId = null) {
        $sql = 'SELECT location_id FROM public.locations
                WHERE LOWER(street_address) = LOWER(:street_address)
                AND LOWER(suburb) = LOWER(:suburb)
                AND LOWER(town) = LOWER(:town)
                AND LOWER(province) = LOWER(:province)
                AND postal_code = :postal';

        $params = [
            ':street_address' => $streetAddress,
            ':suburb' => $suburb,
            ':town' => $town,
            ':province' => $province,
            ':postal' => $postalCode,
        ];

        if (!empty($excludeId)) {
            $sql .= ' AND location_id <> :exclude_id';
            $params[':exclude_id'] = (int) $excludeId;
        }

        $sql .= ' LIMIT 1';
        $row = wecoza_db()->getRow($sql, $params);

        return !empty($row);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| google.maps.places.Autocomplete (deprecated) | google.maps.places.PlaceAutocompleteElement | March 1, 2025 | New customers must use PlaceAutocompleteElement |
| Hardcoded API keys in JavaScript | WordPress options + wp_localize_script | Current best practice | Secure, configurable per environment |
| Manual address parsing | Google Places Autocomplete | Ongoing | Accurate geocoding, handles complex addresses |
| Exact string matching for duplicates | Fuzzy ILIKE with cross-column search | Project standard | Catches typos, variations, misclassification |
| On-demand location hierarchy queries | Cached hierarchy in transient | Project pattern | Reduces database load, fast dropdowns |
| Block submission on duplicates | Warn but allow override | UX best practice | User has final say, handles edge cases |

**Deprecated/outdated:**
- `google.maps.places.Autocomplete`: Replaced with `PlaceAutocompleteElement` (not available to new customers as of March 2025)
- Hardcoded API keys: Use WordPress options and wp_localize_script
- Synchronous Google Maps loading: Use `loading=async` parameter
- component.short_name only: Check both `longText` and `long_name` for backward compat

## Open Questions

1. **Google Maps API Key Storage**
   - What we know: Current code checks `wecoza_google_maps_api_key` option (shared with agents module)
   - What's unclear: Should Clients have separate key or continue sharing?
   - Recommendation: Continue sharing. Google Maps is organization-wide resource, no benefit to separate keys. Simplifies billing management.

2. **Duplicate Detection Sensitivity**
   - What we know: checkDuplicates() uses ILIKE with partial match
   - What's unclear: How loose should matching be? Current implementation finds "123 Main" when searching "Main"
   - Recommendation: Keep current implementation. Better to show false positives (user dismisses) than miss real duplicates. Test with real data to adjust if too noisy.

3. **Location Edit Permission Level**
   - What we know: Both capture and edit require `manage_wecoza_clients` capability
   - What's unclear: Should regular users be able to suggest location edits?
   - Recommendation: Keep restricted to manage capability. Locations are shared data — incorrect edits impact all clients/sites. Add audit trail if edit access widened later.

4. **Coordinate Precision**
   - What we know: Database stores numeric(9,6), JavaScript formats to 6 decimals
   - What's unclear: Is 6 decimal places sufficient? (6 decimals = ~10cm precision)
   - Recommendation: Keep 6 decimals. Sufficient for site addresses (not surveying). Matches Google Maps precision.

5. **Location Hierarchy Cache Duration**
   - What we know: Transient expires after 1 hour (HOUR_IN_SECONDS)
   - What's unclear: Is 1 hour too short? Too long?
   - Recommendation: Keep 1 hour. Locations change infrequently, but new additions should appear within reasonable time. Plus manual refresh on location create/update covers most cases.

6. **Google Maps Billing Alerts**
   - What we know: Google requires billing account, provides free monthly credit
   - What's unclear: Should plugin monitor API usage to prevent overages?
   - Recommendation: Out of scope for Phase 23. Monitor in Google Cloud Console. Consider usage alerts in future if high traffic.

## Testing Checklist

### Prerequisite Setup
- [ ] Verify locations table exists with all columns
- [ ] Set Google Maps API key: `wp option update wecoza_google_maps_api_key 'YOUR_KEY'`
- [ ] Enable Places API in Google Cloud Console
- [ ] Verify WP_DEBUG enabled
- [ ] Clear debug.log file

### Shortcode Rendering Tests
- [ ] [wecoza_locations_capture] renders form with all fields
- [ ] [wecoza_locations_list] renders table with existing locations
- [ ] [wecoza_locations_edit]?location_id=X renders pre-filled form
- [ ] Google Maps autocomplete appears when API key set
- [ ] Warning alert shows when API key NOT set
- [ ] All forms show properly on mobile (responsive)

### Google Maps Autocomplete Tests
- [ ] Autocomplete widget replaces input field
- [ ] Typing shows South African suggestions only
- [ ] Selecting suggestion populates street_address
- [ ] Selecting suggestion populates suburb
- [ ] Selecting suggestion populates town
- [ ] Selecting suggestion populates province (matches dropdown)
- [ ] Selecting suggestion populates postal_code
- [ ] Selecting suggestion populates latitude/longitude
- [ ] Manual edit still works after autocomplete

### Duplicate Detection Tests
- [ ] "Check Duplicates" button triggers AJAX call
- [ ] Exact match shows warning with location details
- [ ] Partial match (e.g., street name only) shows suggestions
- [ ] No match shows "No duplicates found" success
- [ ] Submit button hidden until check performed
- [ ] Submit button shows after check (regardless of result)
- [ ] Changing form fields re-hides submit button
- [ ] Network error shows error message and allows submit

### Form Validation Tests
- [ ] Empty street_address shows error
- [ ] Empty suburb shows error
- [ ] Empty town shows error
- [ ] Empty province shows error
- [ ] Empty postal_code shows error
- [ ] Empty latitude shows error
- [ ] Empty longitude shows error
- [ ] Invalid latitude (>90 or <-90) rejected
- [ ] Invalid longitude (>180 or <-180) rejected
- [ ] Comma decimal separator (e.g., "28,5") accepted and normalized
- [ ] String in coordinate field rejected

### CRUD Operation Tests
- [ ] Create location saves to database
- [ ] Create location returns success message
- [ ] Create location refreshes location cache
- [ ] Update location saves changes
- [ ] Update location preserves location_id
- [ ] List shows all locations with pagination
- [ ] Search filters locations by street/suburb/town
- [ ] Edit pre-populates all fields correctly
- [ ] Coordinates display with correct precision

### Location Cache Tests
- [ ] New location appears in client form dropdowns immediately
- [ ] Province → town → suburb cascade works
- [ ] Postal code auto-fills on suburb select
- [ ] Cache refreshes after location create
- [ ] Cache refreshes after location update
- [ ] Manual cache clear: `delete_transient('wecoza_clients_location_cache')`

### Permission Tests
- [ ] Non-logged-in user cannot access forms
- [ ] User without manage_wecoza_clients sees permission error
- [ ] Admin can create locations
- [ ] Admin can edit locations
- [ ] Admin can view locations list

### Error Handling Tests
- [ ] Missing API key shows graceful degradation
- [ ] Google Maps load failure shows manual entry option
- [ ] Network timeout on duplicate check allows submit
- [ ] Duplicate location submission blocked with clear error
- [ ] Invalid coordinates rejected with helpful message

### JavaScript Console Tests
- [ ] No errors on page load
- [ ] No errors after autocomplete selection
- [ ] No errors after form submission
- [ ] wecoza_locations object defined
- [ ] wecoza_ajax object defined

### Debug Log Verification
- [ ] No PHP Fatal errors
- [ ] No PHP Warnings
- [ ] No "class not found" errors
- [ ] No SQL syntax errors
- [ ] No "undefined index" errors

## Sources

### Primary (HIGH confidence)
- **Google Maps JavaScript API Documentation (Context7)**
  - `/websites/developers_google_maps_javascript_place` - PlaceAutocompleteElement API, address components
  - `/websites/developers_google_maps_javascript_reference` - importLibrary, async loading
  - Autocomplete options for region restrictions (includedRegionCodes)
  - Address component extraction patterns
- **Wecoza-core codebase inspection**
  - `src/Clients/Controllers/LocationsController.php` - Shortcode handlers, asset enqueuing
  - `src/Clients/Models/LocationsModel.php` - Validation, CRUD, duplicate detection
  - `src/Clients/Models/SitesModel.php` - Location hierarchy caching
  - `views/clients/components/location-capture-form.view.php` - Form template
  - `assets/js/clients/location-capture.js` - Google Maps integration, autocomplete
  - `assets/js/clients/locations-list.js` - List functionality
- **Source Plugin Reference**
  - `.integrate/wecoza-clients-plugin/app/Controllers/LocationsController.php` - Original implementation
  - `.integrate/wecoza-clients-plugin/app/Models/LocationsModel.php` - Original validation
  - `.integrate/wecoza-clients-plugin/.factory/docs/` - Factory documentation
- **Phase 21 & 22 Documentation**
  - `.planning/phases/21-foundation-architecture/21-RESEARCH.md` - Migration patterns
  - `.planning/phases/22-client-management/22-RESEARCH.md` - Testing approach

### Secondary (MEDIUM confidence)
- **WordPress Plugins & Best Practices (WebSearch 2026)**
  - [Autocomplete Google Address Plugin](https://wordpress.org/plugins/autocomplete-google-address/) - WordPress integration patterns
  - [WPForms Google Maps Integration](https://wpforms.com/how-to-make-a-google-maps-autocomplete-address-form/) - Form autocomplete implementation
  - [WordPress Options API Security](https://fullstackdigital.io/blog/how-to-safely-store-api-keys-and-access-protected-external-apis-in-wordpress/) - API key storage best practices
  - [WordPress Secrets Management](https://docs.pantheon.io/guides/wordpress-developer/wordpress-secrets-management) - Secure credential storage
  - Google Maps API billing requirements (2026)
  - PlaceAutocompleteElement requirement for new customers (March 2025)

### Tertiary (LOW confidence)
- None - research based on official documentation and verified codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All code exists, verified in Phase 21 migration
- Google Maps integration: HIGH - Official Context7 docs + verified implementation
- Database schema: HIGH - Schema inspected, relationships documented
- Testing approach: HIGH - Based on Phase 22 patterns, adapted for locations
- Pitfalls: MEDIUM-HIGH - Common issues documented in factory docs, some inferred
- Open questions: MEDIUM - Edge cases requiring user input or usage data

**Research date:** 2026-02-11
**Valid until:** 2026-03-15 (30 days - Google Maps API stable, location logic mature)

**Completeness:** Research covers all 10 requirements (LOC-01 through LOC-07, SC-03 through SC-05) and provides systematic testing approach. All code exists from Phase 21; Phase 23 verifies end-to-end functionality with emphasis on Google Maps integration and duplicate detection. Ready for planning.
