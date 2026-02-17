# Debug Report: Agent Capture FK Violation

**Status:** RESOLVED
**Date:** 2026-02-17
**Shortcode:** `wecoza_capture_agents`

## Symptom

Inserting a new agent fails with:

```
SQLSTATE[23503]: Foreign key violation: 7 ERROR: insert or update on table "agents"
violates foreign key constraint "agents_preferred_working_area_1_fkey"
DETAIL: Key (preferred_working_area_1)=(13) is not present in table "locations".
```

## Root Cause

`WorkingAreasService::load_working_areas()` returned a hardcoded array with IDs 1-14, but the `locations` table only contained 7 rows (IDs 1-7). The `agents` table has FK constraints on `preferred_working_area_1/2/3` referencing `locations(location_id)`. Selecting any hardcoded option with ID > 7 caused the FK violation.

## Fix

Replaced the hardcoded array in `WorkingAreasService::load_working_areas()` with a dynamic query:

```php
SELECT location_id, suburb, town, province, postal_code FROM locations ORDER BY town, suburb
```

**File changed:** `src/Agents/Services/WorkingAreasService.php`

Dropdowns now only show locations that exist in the database, making FK violations impossible.
