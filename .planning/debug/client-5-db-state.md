# Database Investigation: client_id=5 Address Data

**Date:** 2026-02-13
**Investigator:** DB-INVESTIGATOR
**Client:** AgriGrow Farms (client_id=5)

---

## Executive Summary

**ROOT CAUSE IDENTIFIED:** 8 out of 9 sites for client_id=5 have **NULL `place_id`** values. Only site_id=2 (East Park) has a valid `place_id=15`.

The `clients` table has **NO address columns**. All address data is stored in the `locations` table, referenced via `sites.place_id`.

---

## Database State

### 1. Sites Table Schema

```
site_id                          (integer)
client_id                        (integer)
site_name                        (character varying)
created_at                       (timestamp without time zone)
updated_at                       (timestamp without time zone)
parent_site_id                   (integer)
place_id                         (integer)  ← CRITICAL: Links to locations table
```

### 2. Sites for client_id=5 (9 total)

| site_id | site_name                    | place_id | Status |
|---------|------------------------------|----------|--------|
| 15      | Epsilon Tech Hub             | NULL     | ❌ No location |
| 16      | Epsilon Tech Showroom        | NULL     | ❌ No location |
| 17      | Epsilon Tech Support Center  | NULL     | ❌ No location |
| 18      | Central                      | NULL     | ❌ No location |
| 20      | Bloem Central                | NULL     | ❌ No location |
| 24      | Randfontein                  | NULL     | ❌ No location (HEAD SITE) |
| 19      | Central 2                    | NULL     | ❌ No location |
| 21      | Bloem Central 2              | NULL     | ❌ No location |
| 2       | East Park                    | **15**   | ✅ Has location |

**HEAD SITE:** site_id=24 (Randfontein) — but it has **NULL place_id**!

---

### 3. Clients Table Schema

```
client_id                        (integer)
client_name                      (character varying)
company_registration_number      (character varying)
seta                             (character varying)
client_status                    (character varying)
financial_year_end               (date)
bbbee_verification_date          (date)
created_at                       (timestamp without time zone)
updated_at                       (timestamp without time zone)
main_client_id                   (integer)
contact_person                   (character varying)
contact_person_email             (character varying)
contact_person_cellphone         (character varying)
contact_person_tel               (character varying)
contact_person_position          (character varying)
deleted_at                       (timestamp without time zone)
```

**NO ADDRESS COLUMNS** in the clients table.

---

### 4. Client Record (client_id=5)

```
client_id:            5
client_name:          AgriGrow Farms
company_registration: 2021/579135/07
seta:                 AgriSETA
client_status:        Active Client
contact_person:       John Montgomery
contact_email:        laudes.michael@gmail.com
contact_cellphone:    0791778896
```

No address fields in raw record.

---

### 5. Locations Table Schema

```
location_id                      (integer)
suburb                           (character varying)
town                             (character varying)
province                         (character varying)
postal_code                      (character varying)
longitude                        (numeric)
latitude                         (numeric)
created_at                       (timestamp without time zone)
updated_at                       (timestamp without time zone)
street_address                   (text)
```

**This is where addresses live.** Sites link to locations via `sites.place_id = locations.location_id`.

---

### 6. Location Lookup for place_id=15

**Query attempted but NO result returned.**

This suggests either:
- The location was deleted
- Wrong table name used (tried `locations`, `places`, `sa_places`)
- Data inconsistency

---

## 7. ClientsModel::getById(5) Hydration Result

The hydrated result shows:

```php
[client_id] => 5
[client_name] => AgriGrow Farms
...
[head_site] => Array (
    [site_id] => 24
    [site_name] => Randfontein
    [place_id] =>              ← EMPTY!
    [location] =>              ← EMPTY!
)
[site_id] => 24
[site_name] => Randfontein
[client_town_id] =>            ← EMPTY
[client_street_address] =>     ← EMPTY
[client_suburb] =>             ← EMPTY
[client_postal_code] =>        ← EMPTY
[client_province] =>           ← EMPTY
[client_town] =>               ← EMPTY
[client_location] =>           ← EMPTY
```

**All address fields are EMPTY because the head site (site_id=24) has `place_id=NULL`.**

---

## Root Cause Analysis

### Why are address fields empty?

1. **Head site has NULL place_id:**
   Site ID 24 (Randfontein) is the head site, but `place_id` is NULL.

2. **ClientsModel relies on head_site.location:**
   The hydration process in `SitesModel::hydrateClients()` attempts to populate:
   - `client_street_address`
   - `client_suburb`
   - `client_town`
   - `client_province`
   - `client_postal_code`

   These are derived from `head_site.location`, which comes from `sites.place_id → locations.*`.

3. **NULL place_id = no location data:**
   When `place_id` is NULL, there's nothing to hydrate.

---

## Data Quality Issues

### Missing place_id assignments

**8 out of 9 sites lack location data:**
- Sites 15, 16, 17, 18, 19, 20, 21, 24

**Possible causes:**
1. Site creation form doesn't enforce location selection
2. Existing sites migrated without place_id mapping
3. Locations table not properly linked during data import
4. Form validation doesn't require place_id

---

## Recommendations

### Immediate Fix (Code-level)

1. **Fix site creation forms:**
   Ensure all new sites MUST have a valid `place_id`.

2. **Add validation:**
   Prevent head site assignment if `place_id` is NULL.

3. **Update form to show warning:**
   If editing a client with head_site.place_id=NULL, display warning.

### Data Fix (Manual)

1. **Assign locations to sites:**
   Update sites 15-24 to have valid `place_id` values.

2. **Create missing location records:**
   For "Randfontein", "Bloem Central", etc., create entries in `locations` table.

### Architectural Improvement

1. **Consider denormalizing:**
   Add a `default_location_id` to the `clients` table directly, separate from sites.

2. **Add database constraints:**
   - Foreign key: `sites.place_id → locations.location_id`
   - Check constraint: Head sites MUST have `place_id NOT NULL`

---

## Next Steps for Team

1. **CODE-REPAIR agent:** Fix form validation and head site assignment logic
2. **User:** Manually update site records to assign place_id values
3. **Testing:** Verify address fields populate after place_id is set

---

## Test Query to Fix site_id=24

```sql
-- Example: Assign a location to the Randfontein head site
UPDATE sites
SET place_id = (
    SELECT location_id
    FROM locations
    WHERE town = 'Randfontein'
    LIMIT 1
)
WHERE site_id = 24;
```

**Note:** User must run this manually (per security policy).
