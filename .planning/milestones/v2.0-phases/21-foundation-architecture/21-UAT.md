---
status: testing
phase: 21-foundation-architecture
source: 21-01-SUMMARY.md, 21-02-SUMMARY.md
started: 2026-02-11T12:00:00Z
updated: 2026-02-11T12:30:00Z
---

## Current Test

number: 3
name: Clients display table renders
expected: |
  Visit the page with `[wecoza_display_clients]` shortcode. You should see a clients table/listing with search, filters, and pagination controls. Existing client data displays in the table rows.
awaiting: user response

## Tests

### 1. WordPress loads without PHP fatal errors
expected: Visit any page on the WeCoza site. Page loads normally without white screen, fatal error, or "Plugin could not be activated" notices.
result: pass

### 2. Client capture form renders
expected: Visit the page with `[wecoza_capture_clients]` shortcode. You should see a client creation form with fields like company name, contact person, SETA dropdown, province, status, etc. The form renders without broken HTML or PHP errors.
result: issue
reported: "Shows 'Something went wrong. Please try again.' error. Likely shortcode conflict with standalone wecoza-clients-plugin still active. Multiple fatal errors fixed during testing: BaseModel static property conflicts, missing PostgresConnection CRUD methods (getAll, getRow, getValue, insert, update, delete), missing tableHasColumn method."
severity: major

### 3. Clients display table renders
expected: Visit the page with `[wecoza_display_clients]` shortcode. You should see a clients table/listing with search, filters, and pagination controls. Existing client data displays in the table rows.
result: [pending]

### 4. Location capture form renders
expected: Visit the page with `[wecoza_locations_capture]` shortcode. You should see a location form with fields like suburb, town, postal code, province. If Google Maps API key is configured, the autocomplete search field should appear.
result: [pending]

### 5. Locations list renders
expected: Visit the page with `[wecoza_locations_list]` shortcode. You should see a locations table/listing with search. Existing location data displays in the table.
result: [pending]

### 6. JavaScript assets load on shortcode pages
expected: On any page with a clients/locations shortcode, open browser DevTools (F12) > Console tab. No JavaScript errors related to wecoza-client or wecoza-location scripts. In Network tab, you should see JS files loading from `/wp-content/plugins/wecoza-core/assets/js/clients/`.
result: [pending]

### 7. No debug.log errors from Clients module
expected: Check `/wp-content/debug.log` — no new PHP errors, warnings, or notices referencing `WeCoza\Clients\`, `ClientsController`, `LocationsController`, `ClientAjaxHandlers`, or any files under `src/Clients/`.
result: [pending]

## Summary

total: 7
passed: 1
issues: 1
pending: 5
skipped: 0

## Gaps

- truth: "Client capture form renders without errors via wecoza-core shortcode"
  status: failed
  reason: "User reported: Shows 'Something went wrong' error. Standalone wecoza-clients-plugin may be conflicting. Multiple migration bugs found and fixed (BaseModel incompatibility, missing CRUD methods)."
  severity: major
  test: 2
  root_cause: ""
  artifacts: []
  missing: []
  debug_session: ""
