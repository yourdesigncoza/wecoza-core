---
phase: 22-client-management
plan: 02
subsystem: clients
tags: [ajax, crud, soft-delete, csv-export, hierarchy, javascript, php]

requires:
  - phase: 22-client-management
    plan: 01
    provides: Working shortcode rendering, fixed hydrate() bug
  - phase: 21-foundation-architecture
    provides: Migrated Clients module classes, views, JS, AJAX handlers
provides:
  - All 15 client AJAX endpoints verified and fixed
  - PostgresConnection CRUD convenience methods (insert, update, delete, getAll, getRow, getValue)
  - Soft-delete via deleted_at column instead of hard-delete
  - JS-PHP connectivity fixes across all client JS files
affects: [23-location-management, 24-sites-hierarchy]

tech-stack:
  added: []
  patterns: [soft-delete-pattern, wp_send_json_success-data-wrapper, camelCase-localization-keys]

key-files:
  created:
    - schema/migrations/002-add-deleted-at-to-clients.sql
  modified:
    - core/Database/PostgresConnection.php
    - src/Clients/Models/ClientsModel.php
    - src/Clients/Models/SitesModel.php
    - src/Clients/Models/ClientCommunicationsModel.php
    - src/Clients/Controllers/ClientsController.php
    - src/Clients/Controllers/LocationsController.php
    - assets/js/clients/client-capture.js
    - assets/js/clients/clients-table.js
    - assets/js/clients/client-search.js

key-decisions:
  - "Added CRUD convenience methods to PostgresConnection rather than using raw PDO in models"
  - "Soft-delete uses deleted_at timestamp column, all queries filter WHERE deleted_at IS NULL"
  - "JS localization uses camelCase keys (ajaxUrl, nonce) matching existing JS conventions"

patterns-established:
  - "Soft-delete pattern: deleteById() sets deleted_at instead of hard-deleting"
  - "wp_send_json_success wraps data in .data — JS must access response.data.field"
  - "Models not extending BaseModel should not call hydrate() or parent methods"

duration: 184min
completed: 2026-02-11
---

# Phase 22 Plan 02: AJAX Endpoint Testing & CRUD Verification Summary

**Fixed 15 client AJAX endpoints: PostgresConnection CRUD methods, soft-delete, JS-PHP connectivity (4 mismatches), model method signatures, and removed broken BaseModel extends**

## Performance

- **Duration:** 3h 4m (across two sessions)
- **Started:** 2026-02-11
- **Completed:** 2026-02-11
- **Tasks:** 3 (2 auto + 1 checkpoint)
- **Files modified:** 10

## Accomplishments
- Added insert(), update(), delete(), getAll(), getRow(), getValue(), tableHasColumn() to PostgresConnection
- Implemented soft-delete: ClientsModel::deleteById() sets deleted_at timestamp
- Fixed ajax_url → ajaxUrl localization key mismatch across all JS files
- Fixed response.data wrapping in 3 JS files (wp_send_json_success convention)
- Fixed ClientsModel::update($id, $data) and delete($id) method signatures
- Removed broken BaseModel extends from SitesModel and ClientCommunicationsModel
- Fixed LocationsController Google Maps API key option name
- Fixed SitesModel::getAllSitesWithHierarchy() calling undefined method

## Task Commits

1. **Task 1A (prior): Implement soft-delete** - `e000145` (fix)
2. **Task 1A: Fix core CRUD AJAX endpoints and JS-PHP connectivity** - `daf25a4` (fix)
3. **Task 1B: Fix secondary AJAX endpoints (sites, locations, hierarchy)** - `8fb5e9d` (fix)

## Files Created/Modified
- `core/Database/PostgresConnection.php` - Added CRUD convenience methods
- `schema/migrations/002-add-deleted-at-to-clients.sql` - Migration for deleted_at column
- `src/Clients/Models/ClientsModel.php` - Soft-delete, update/delete signatures, query filters
- `src/Clients/Models/SitesModel.php` - Removed BaseModel extends, fixed hierarchy method
- `src/Clients/Models/ClientCommunicationsModel.php` - Removed broken BaseModel extends
- `src/Clients/Controllers/ClientsController.php` - Updated localization keys
- `src/Clients/Controllers/LocationsController.php` - Fixed Google Maps API key option
- `assets/js/clients/client-capture.js` - Fixed response.data access, ajaxUrl key
- `assets/js/clients/clients-table.js` - Fixed delete ID field, ajaxUrl key
- `assets/js/clients/client-search.js` - Fixed response.data.clients access

## Decisions Made
- Added CRUD convenience methods to PostgresConnection — models depend on these rather than raw PDO
- Soft-delete uses deleted_at timestamp, consistent with common patterns
- JS localization uses camelCase keys matching existing JS conventions

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] PostgresConnection missing CRUD methods**
- **Found during:** Task 1A
- **Issue:** All Clients models call insert/update/delete/getAll on PostgresConnection but methods didn't exist
- **Fix:** Added 7 convenience methods wrapping PDO operations
- **Files modified:** core/Database/PostgresConnection.php
- **Committed in:** daf25a4

**2. [Rule 1 - Bug] JS localization key mismatch (ajax_url vs ajaxUrl)**
- **Found during:** Task 1A Step 6
- **Issue:** PHP registered `ajax_url` but all JS files read `ajaxUrl`
- **Fix:** Changed PHP localization to use camelCase keys
- **Files modified:** src/Clients/Controllers/ClientsController.php, 3 JS files
- **Committed in:** daf25a4

**3. [Rule 1 - Bug] wp_send_json_success data wrapper not handled in JS**
- **Found during:** Task 1A Step 6
- **Issue:** JS accessed response.client instead of response.data.client
- **Fix:** Updated 3 JS files to use response.data.* pattern
- **Files modified:** assets/js/clients/client-capture.js, clients-table.js, client-search.js
- **Committed in:** daf25a4

**4. [Rule 1 - Bug] Model method signatures (zero params)**
- **Found during:** Task 1A Steps 3-4
- **Issue:** ClientsModel::update() and delete() accepted no parameters
- **Fix:** Added proper parameter signatures: update($id, $data), deleteById($id)
- **Files modified:** src/Clients/Models/ClientsModel.php
- **Committed in:** daf25a4

**5. [Rule 3 - Blocking] Broken BaseModel extends in SitesModel and ClientCommunicationsModel**
- **Found during:** Task 1B
- **Issue:** Models extended BaseModel but don't use its features, causing method conflicts
- **Fix:** Removed extends, made models standalone like ClientsModel
- **Files modified:** src/Clients/Models/SitesModel.php, ClientCommunicationsModel.php
- **Committed in:** 8fb5e9d

**6. [Rule 1 - Bug] SitesModel undefined method and LocationsController wrong option name**
- **Found during:** Task 1B Steps 4-5
- **Fix:** Fixed getAllSitesWithHierarchy() to use existing methods, fixed API key option
- **Files modified:** src/Clients/Models/SitesModel.php, src/Clients/Controllers/LocationsController.php
- **Committed in:** 8fb5e9d

---

**Total deviations:** 6 auto-fixed (4 bugs, 2 blocking)
**Impact on plan:** All fixes essential for AJAX endpoints to function. No scope creep.

## Issues Encountered
- WP-CLI runtime testing was not possible during automated execution (MySQL/Apache state); verification was code-review based. User approved for browser testing.

## Next Phase Readiness
- Phase 22 complete — all client CRUD, hierarchy, export, statistics endpoints verified
- LocationsController fixes benefit Phase 23 (Location Management)
- SitesModel fixes benefit Phase 24 (Sites Hierarchy)

---
*Phase: 22-client-management*
*Completed: 2026-02-11*
