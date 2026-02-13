---
phase: 34-clients-module-fixes
plan: 01
subsystem: ui, api
tags: [ajax, nonce, security, wordpress, forms]

# Dependency graph
requires:
  - phase: 21-25 (v2.0 Clients Integration)
    provides: ClientRepository, ClientAjaxHandlers, client form views
provides:
  - Single AJAX submission per client update (duplicate handler removed)
  - Nonce field on capture form for non-AJAX fallback
  - Unified nonce action (clients_nonce_action) across all client forms
  - Clean repository whitelists (phantom client_town_id removed)
  - Reduced attack surface (7 unused AJAX endpoints removed)
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "All client forms use clients_nonce_action nonce action consistently"
    - "External JS file (client-capture.js) handles all form functionality — no inline scripts"

key-files:
  created: []
  modified:
    - views/clients/components/client-update-form.view.php
    - views/clients/components/client-capture-form.view.php
    - src/Clients/Repositories/ClientRepository.php
    - src/Clients/Ajax/ClientAjaxHandlers.php

key-decisions:
  - "Removed entire 208-line inline script rather than just the duplicate submit handler — all functionality already in client-capture.js"

patterns-established:
  - "Client forms rely exclusively on client-capture.js for submission, validation, and cascade logic"

# Metrics
duration: 3min
completed: 2026-02-13
---

# Phase 34 Plan 01: Clients Module Fixes Summary

**Removed duplicate AJAX submit handler, added nonce field to capture form, unified nonce actions, cleaned phantom column, and deleted 7 unused AJAX endpoints**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-13T11:09:07Z
- **Completed:** 2026-02-13T11:11:59Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Eliminated double AJAX submission on every client update by removing 208-line inline script block
- Added wp_nonce_field to capture form for non-AJAX fallback compatibility
- Unified nonce action to clients_nonce_action across both client forms (matching controllers and AJAX handlers)
- Removed phantom client_town_id from repository insert/update whitelists
- Reduced attack surface by removing 7 unused AJAX endpoints (getMainClients, saveLocation, saveSubSite, getHeadSites, getSubSites, deleteSubSite, getSitesHierarchy)

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix forms - remove inline handler, add nonce field, unify nonce action (CLT-01/02/04)** - `e4d01ed` (fix)
2. **Task 2: Clean repository whitelists and remove unused AJAX endpoints (CLT-03/05)** - `956be70` (fix)

## Files Created/Modified
- `views/clients/components/client-update-form.view.php` - Removed inline script (208 lines), fixed nonce action to clients_nonce_action
- `views/clients/components/client-capture-form.view.php` - Added wp_nonce_field('clients_nonce_action', 'nonce')
- `src/Clients/Repositories/ClientRepository.php` - Removed client_town_id from insert and update whitelists
- `src/Clients/Ajax/ClientAjaxHandlers.php` - Removed 7 unused AJAX endpoint registrations and handler methods (171 lines)

## Decisions Made
- Removed entire 208-line inline script block from update form, not just the duplicate submit handler. The sub-client toggle and province/town/suburb cascade logic were also duplicated in client-capture.js, so removing all inline JS eliminates three sources of duplication.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 34 complete (1/1 plan done)
- Phase 33 plan 02 and phase 32 plan 02 still pending
- Phase 35 (Events Module Fixes) ready for execution

---
*Phase: 34-clients-module-fixes*
*Completed: 2026-02-13*
