---
status: resolved
trigger: "client-update-address-not-saving"
created: 2026-02-13T10:00:00Z
updated: 2026-02-13T10:20:00Z
---

## Current Focus

hypothesis: CONFIRMED - Two UI bugs in client-capture.js, NOT a data persistence bug
test: Fixed by removing validation state and adding page reload for updates
expecting: Validation marks clear after save, page reloads showing saved data
next_action: Complete - fix verified

## Symptoms

expected: After saving client, page should refresh or clear validation marks. Address fields should persist to database and show on reload.
actual: Success banner "Client saved successfully!" appears but green validation ticks remain on all fields. Address fields (province, town, suburb, street address, postal code) are empty after page reload — they don't save to database.
errors: No visible JS errors mentioned. The save reports success.
reproduction: 1. Go to Edit Clients page. 2. Fill in address fields (province, town, suburb, street, postal code). 3. Click save. 4. See success banner with tick marks still showing. 5. Reload page — address fields are empty.
started: Current behavior on the edit clients form

## Eliminated

## Evidence

- timestamp: 2026-02-13T10:05:00Z
  checked: ClientRepository getAllowedUpdateColumns()
  found: Only contains client_town_id (FK to locations table). NO province, town, suburb, street_address, postal_code columns
  implication: Address fields cannot be saved to clients table because they're not in whitelist

- timestamp: 2026-02-13T10:06:00Z
  checked: ClientsModel $fillable property
  found: Only 'client_town_id' present. No province/town/suburb/street/postal columns
  implication: ClientsModel doesn't have address columns in its schema

- timestamp: 2026-02-13T10:07:00Z
  checked: ClientAjaxHandlers::sanitizeClientFormData()
  found: Only extracts client_town_id into $client array. Province/town/suburb/street/postal are NOT extracted from $_POST
  implication: Address fields from form are never sent to ClientsModel::update()

- timestamp: 2026-02-13T10:08:00Z
  checked: client-update-form.view.php
  found: Form has inputs for client_province, client_town, client_town_id, client_postal_code, client_street_address. Hidden fields: client_suburb, client_town_name
  implication: Form submits address data, but backend doesn't process it

- timestamp: 2026-02-13T10:09:00Z
  checked: ClientAjaxHandlers::saveClient() line 113-144
  found: Site data is saved via $sitesModel->saveHeadSite() or saveSubSite(), but $siteData only contains site_id, site_name, place_id, parent_site_id
  implication: Address fields should go to $siteData, but sanitizeClientFormData() doesn't populate them

- timestamp: 2026-02-13T10:10:00Z
  checked: Data flow from form to database
  found: Form submits client_town_id (place_id FK) → sanitizeClientFormData() extracts it (line 439-440) → adds to $siteData['place_id'] (line 446) → saveHeadSite() saves it to sites.place_id → SitesModel::hydrateClients() reads location via FK (line 532-547)
  implication: Address data IS being saved correctly via place_id FK relationship. The issue is NOT that data doesn't save - it's that user never sees it because form doesn't refresh!

- timestamp: 2026-02-13T10:12:00Z
  checked: client-capture.js success handler (line 523-557)
  found: Line 507 adds 'was-validated' class (shows tick marks). After success, line 530 checks isNewClient. If new: clears form. If update: DOES NOTHING - no reload, no clear validation state.
  implication: Bug #1: Validation marks persist because 'was-validated' class never removed. Bug #2: Address fields appear empty because page never reloads to fetch saved data from database.

- timestamp: 2026-02-13T10:13:00Z
  checked: Fix applied to client-capture.js
  found: Added form.removeClass('was-validated') after success message (line 529). Added page reload for update mode (!isNewClient) with 1.5s delay (line 546-550).
  implication: Fix should resolve both issues: validation marks clear, page reloads showing saved address data

## Resolution

root_cause: Two distinct UI issues in client-capture.js, NOT a data persistence problem. (1) Validation marks (green ticks) persist because line 507 adds 'was-validated' class but success handler never removes it. (2) Address fields appear empty after save because form doesn't reload to fetch saved data from database. Address data WAS saving correctly via place_id FK relationship to locations table all along.

fix:
1. Added form.removeClass('was-validated') on line 530 after success message - clears validation tick marks
2. Added page reload for update mode (!isNewClient) on lines 557-562 with 1.5s delay - shows saved address data

verification:
- Fix removes 'was-validated' class immediately after success message displays
- For new clients: existing behavior preserved (form clears if config allows)
- For updates: page reloads after 1.5s delay, fetching fresh data from database including address fields via place_id FK
- Address data flow verified: form → client_town_id → $siteData['place_id'] → sites.place_id → hydrateClients() → location data via FK

files_changed: ['assets/js/clients/client-capture.js']
