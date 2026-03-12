# S01: Data Layer + AJAX API

**Goal:** Build the complete data layer and AJAX API for excessive hours detection and resolution tracking
**Demo:** AJAX endpoints return correct excessive-hours data from real DB; resolutions can be created and queried

## Must-Haves

- DB migration SQL for `excessive_hours_resolutions` table with composite index
- Repository with live query (INNER JOIN on classes/class_types, LATERAL for resolutions)
- Service with business logic (class type constant, 30-day rolling window)
- AJAX handlers with nonce + capability + input validation
- Namespace registration in wecoza-core.php

## Proof Level

- This slice proves: integration
- Real runtime required: yes
- Human/UAT required: no (AJAX tested via script)

## Verification

- `php tests/excessive-hours/verify-repository.php` — queries return expected shape with real data
- AJAX endpoint responds correctly via wp-admin/admin-ajax.php

## Observability / Diagnostics

- Runtime signals: wecoza_log on resolution create, error logging on query failure
- Inspection surfaces: AJAX endpoint returns structured JSON with counts
- Failure visibility: Exception messages in JSON error responses
- Redaction constraints: none (no PII in logs, learner data only in responses)

## Integration Closure

- Upstream surfaces consumed: `learner_lp_tracking`, `class_type_subjects`, `learners`, `classes`, `class_types`, `clients` tables; `AjaxSecurity` helper
- New wiring introduced: shortcode stub registration, AJAX hook registration in wecoza-core.php
- What remains: S02 builds dashboard UI consuming these AJAX endpoints

## Tasks

- [ ] **T01: Schema migration + Repository** `est:45m`
  - Why: Foundation — the DB table and query layer everything depends on
  - Files: `schema/excessive_hours_resolutions.sql`, `src/Reports/ExcessiveHours/ExcessiveHoursRepository.php`
  - Do: Create migration SQL with table + composite index + FK constraint. Build repository with `findFlagged()` (live query with LATERAL + 30-day window), `createResolution()`, `countOpen()`, `getResolutionHistory()`
  - Verify: `php tests/excessive-hours/verify-repository.php` — findFlagged returns array, countOpen returns int
  - Done when: Repository queries execute successfully against real DB

- [ ] **T02: Service + AJAX handlers + wiring** `est:45m`
  - Why: Expose the data layer via AJAX with proper security
  - Files: `src/Reports/ExcessiveHours/ExcessiveHoursService.php`, `src/Reports/ExcessiveHours/ExcessiveHoursAjaxHandlers.php`, `wecoza-core.php`
  - Do: Service wraps repository with business logic (class type constant, pagination, sorting). AJAX handlers use AjaxSecurity pattern (nonce, capability, sanitize, whitelist action_taken). Register namespace autoload + AJAX hooks in wecoza-core.php
  - Verify: curl/browser test of admin-ajax.php?action=wecoza_get_excessive_hours returns JSON
  - Done when: Both AJAX endpoints respond with correct data structure

## Files Likely Touched

- `schema/excessive_hours_resolutions.sql`
- `src/Reports/ExcessiveHours/ExcessiveHoursRepository.php`
- `src/Reports/ExcessiveHours/ExcessiveHoursService.php`
- `src/Reports/ExcessiveHours/ExcessiveHoursAjaxHandlers.php`
- `wecoza-core.php`
- `tests/excessive-hours/verify-repository.php`
