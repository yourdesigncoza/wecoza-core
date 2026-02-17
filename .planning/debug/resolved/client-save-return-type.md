---
status: resolved
trigger: "Saving client via wecoza_capture_clients fails with ClientCommunicationsModel::getLatestCommunication() return type mismatch and ClientService::getClient() returning ClientsModel object instead of array"
created: 2026-02-17T15:08:00Z
updated: 2026-02-17T15:44:00Z
---

## Current Focus

hypothesis: CONFIRMED - Multiple strict_types=1 return type mismatches in Clients module
test: Trace save flow from AJAX handler through service/model layers
expecting: Type mismatches between declared return types and actual returned types
next_action: RESOLVED

## Symptoms

expected: Creating/saving a client via wecoza_capture_clients shortcode should succeed without errors
actual: Multiple errors - ClientCommunicationsModel return type mismatch, ClientService returning model objects instead of arrays, and intermittent 400 from duplicate registration numbers in dev form filler
errors:
  1. "[WeCoza Core][ERROR] Error saving client: WeCoza\Clients\Models\ClientCommunicationsModel::getLatestCommunication(): Return value must be of type ?array, bool returned"
  2. "TypeError: array_merge(): Argument #1 must be of type array, WeCoza\Clients\Models\ClientsModel given in ClientService.php:412"
  3. "TypeError: WeCoza\Clients\Services\ClientService::getClient(): Return value must be of type ?array, WeCoza\Clients\Models\ClientsModel returned in ClientService.php:322"
  4. "POST admin-ajax.php 400 (Bad Request)" - validation error from duplicate company registration number
reproduction: Use wecoza_capture_clients shortcode to create/save a client
started: v4.0 technical debt milestone added declare(strict_types=1)

## Eliminated

- AJAX handler registration issue (handlers registered correctly on plugins_loaded priority 5)
- Nonce mismatch (action name matches in controller and handler)
- WordPress core 400 (was actually validation 400 from duplicate registration)

## Evidence

- timestamp: 2026-02-17T15:08:00Z
  checked: src/Clients/Models/ClientCommunicationsModel.php line 70
  found: getLatestCommunication() declares ?array return but wecoza_db()->getRow() returns array|false. When no communications exist for a new client, false is returned, violating the ?array type.
  implication: Non-fatal (caught by try/catch in AJAX handler) but logged as error

- timestamp: 2026-02-17T15:11:00Z
  checked: src/Clients/Services/ClientService.php lines 322, 236, 401, 419
  found: ClientService calls $this->model->getById() which returns ?static (ClientsModel instance), but service methods declare ?array return types. ClientsModel implements ArrayAccess but is not an array.
  implication: Fatal errors on array_merge() and return type checks under strict_types=1

- timestamp: 2026-02-17T15:44:00Z
  checked: Debug log after adding validation error logging
  found: 400 was from validation rejecting duplicate company_registration_nr. Dev form filler picks from pool of 6 static registrations.
  implication: After first client created, subsequent fills reuse same registration and fail uniqueness check

## Resolution

root_cause: Three distinct issues, all caused by v4.0 strict_types=1 addition:

1. **ClientCommunicationsModel::getLatestCommunication()** - getRow() returns false when no rows found, but method typed as ?array
2. **ClientService::getClient/getClientDetails()** - ClientsModel::getById() returns model instance, not array
3. **Dev form filler** - Static pool of 6 registration numbers causes duplicate validation failures

fix:
1. Added `?: null` to coerce false->null in getLatestCommunication() (line 70)
2. Added ->toArray() conversion at all 4 getById() call sites in ClientService
3. Changed client-filler.js to generate random registration numbers instead of picking from static pool

verification: Debug log clean after fixes. Client save succeeds. Form filler generates unique registrations.

files_changed:
  - src/Clients/Models/ClientCommunicationsModel.php
  - src/Clients/Services/ClientService.php
  - assets/js/dev/form-fillers/client-filler.js
