# Client Address Data Flow Research

## Root Cause Summary

**Address fields are NOT lost during save.** The address data (street_address, suburb, postal_code, province, town) was never stored in the `clients` table -- it lives in the `locations` table, linked via `sites.place_id`. The system works correctly: `client_town_id` (the location reference) IS being saved to `sites.place_id`, and address data IS hydrated back on read.

**The "Filtered out" log messages are a RED HERRING.** They come from `filterClientDataForForm()` in `ClientsController.php:742-805`, which runs AFTER data is retrieved from the DB and BEFORE rendering the update form. It filters out non-scalar/derived fields to keep the form template safe -- this is a READ path filter, not a WRITE path filter.

## Data Flow Trace

### 1. FORM -> JS (client-capture.js:500-576)

The form uses `new FormData(form[0])` which captures ALL named form inputs:
- `client_town_id` - suburb select value (actually a `location_id` from the `locations` table)
- `client_province` - province select name (display only, not saved to DB)
- `client_town` - town select name (display only, not saved to DB)
- `client_suburb` - hidden input (display value, not saved)
- `client_town_name` - hidden input (display value, not saved)
- `client_street_address` - text input (display only, auto-populated from location)
- `client_postal_code` - text input (display only, auto-populated from location)
- `head_site_id` - hidden input with site ID
- `site_name` - text input

### 2. JS -> PHP: sanitizeClientFormData() (ClientAjaxHandlers.php:410-464)

Two separate data arrays are built:

**$client array** (saved to `clients` table):
- `client_town_id` = `(int) $data['client_town_id']` -- this IS extracted from POST

**$site array** (saved to `sites` table):
- `site_id` = from `head_site_id`
- `site_name` = from `site_name`
- `place_id` = same value as `client_town_id` (the location_id)

The address display fields (`client_street_address`, `client_suburb`, `client_postal_code`, `client_province`, `client_town`) are NOT extracted by sanitizeClientFormData() -- this is correct because they are display-only fields derived from the locations table.

### 3. PHP SAVE: saveHeadSite() (SitesModel.php:409-449)

`saveHeadSite()` calls `filterSitePayload()` which whitelists: `site_name`, `place_id`, `parent_site_id`.

So the `place_id` (which holds the location_id value from `client_town_id`) IS being saved to the `sites` table.

### 4. DB -> PHP READ: hydrateClients() (SitesModel.php:499-555)

On read, `hydrateClients()`:
1. Gets head sites for clients via `getHeadSitesForClients()`
2. For each client, sets `$row['client_town_id'] = $site['place_id']`
3. If `$site['location']` exists (populated by `hydrateLocationForSites()`), maps:
   - `client_street_address` = `location['street_address']`
   - `client_suburb` = `location['suburb']`
   - `client_postal_code` = `location['postal_code']`
   - `client_province` = `location['province']`
   - `client_town` = `location['town']`

### 5. PHP -> VIEW: filterClientDataForForm() (ClientsController.php:742-805)

In the UPDATE shortcode path (`updateClientShortcode()`), line 403:
```php
$client = $this->filterClientDataForForm($client);
```

This filter keeps ONLY scalar fields. The address display fields (`client_street_address`, `client_suburb`, etc.) are NOT in the `$scalarFields` whitelist, so they get filtered out.

**BUT:** The view template (`client-update-form.view.php`) retrieves address data from `$client['client_province']`, `$client['client_town']`, etc. (lines 35-39). Since these were filtered out, they resolve to empty strings via the `??` fallback.

## Database Schema Evidence

### clients table (NO client_town_id column!)
```sql
CREATE TABLE public.clients (
    client_id integer NOT NULL,
    client_name varchar(100),
    company_registration_number varchar(50),
    seta varchar(100),
    client_status varchar(50),
    financial_year_end date,
    bbbee_verification_date date,
    created_at timestamp DEFAULT now(),
    updated_at timestamp DEFAULT now(),
    main_client_id integer,
    contact_person varchar(100),
    contact_person_email varchar(100),
    contact_person_cellphone varchar(20),
    contact_person_tel varchar(20),
    contact_person_position varchar(50),
    deleted_at timestamp
);
```

Note: There is NO `client_town_id` column in the clients table. The `ClientsModel` has it in `columnCandidates` and `fillable`, but `resolveColumn()` will return `null` for it since the DB column doesn't exist. This means `prepareDataForSave()` skips it -- `client_town_id` is never written to the clients table.

### sites table (HAS place_id)
```sql
CREATE TABLE public.sites (
    site_id integer NOT NULL,
    client_id integer NOT NULL,
    site_name varchar(100) NOT NULL,
    created_at timestamp DEFAULT now(),
    updated_at timestamp DEFAULT now(),
    parent_site_id integer,
    place_id integer
);
```

### locations table
```sql
CREATE TABLE public.locations (
    location_id integer NOT NULL,
    suburb varchar(50),
    town varchar(50),
    province varchar(50),
    postal_code varchar(10),
    longitude numeric(9,6),
    latitude numeric(9,6),
    created_at timestamp DEFAULT now(),
    updated_at timestamp DEFAULT now(),
    street_address text
);
```

## The Real Problem: filterClientDataForForm()

The bug is in `ClientsController::filterClientDataForForm()` (line 742). It runs on the update form path and strips out the hydrated address fields before they reach the view template.

**$scalarFields whitelist** (line 750-757):
```php
$scalarFields = array(
    'id', 'client_name', 'company_registration_nr', 'seta', 'client_status',
    'financial_year_end', 'bbbee_verification_date', 'main_client_id',
    'client_town_id',
    'created_at', 'updated_at',
    'contact_person', 'contact_person_email', 'contact_person_cellphone',
    'contact_person_tel', 'contact_person_position',
);
```

**Missing from whitelist:**
- `client_street_address`
- `client_suburb`
- `client_postal_code`
- `client_province`
- `client_town`
- `site_id`
- `site_name`

The method does extract `site_id` and `site_name` from `$client['head_site']` array (lines 769-782), but the address display fields are completely lost.

After filtering, the update form receives `$client` with empty address fields. The view template tries to populate dropdowns from `$client['client_province']`, `$client['client_town']`, `$client['client_suburb']`, etc. -- all are empty, so the address section appears blank.

However, `client_town_id` IS preserved, and `$location_selected['locationId']` gets set from it. The view builds the province/town/suburb cascading dropdowns from the location hierarchy data, so if `client_province` and `client_town` are empty, the cascading dropdowns won't pre-select properly even though the underlying `client_town_id` reference is correct.

## Fix Recommendation

**Add the address display fields to the `$scalarFields` whitelist** in `filterClientDataForForm()`:

```php
$scalarFields = array(
    'id', 'client_name', 'company_registration_nr', 'seta', 'client_status',
    'financial_year_end', 'bbbee_verification_date', 'main_client_id',
    'client_town_id',
    'created_at', 'updated_at',
    'contact_person', 'contact_person_email', 'contact_person_cellphone',
    'contact_person_tel', 'contact_person_position',
    // Address display fields (hydrated from locations table via SitesModel)
    'client_street_address', 'client_suburb', 'client_postal_code',
    'client_province', 'client_town',
    // Site fields
    'site_id', 'site_name',
);
```

This is the ONLY change needed. The save path works correctly -- `place_id` is being persisted to the `sites` table, and address data is correctly hydrated on read. The issue is purely a form rendering filter that's too aggressive.

## Verification Steps

1. After fix, load update form for existing client with address data
2. Verify province/town/suburb dropdowns are pre-populated
3. Verify street address and postal code are pre-populated
4. Save form -- verify data persists (place_id saved to sites table)
5. Reload form -- verify data still shows
