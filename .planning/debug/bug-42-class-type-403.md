---
status: awaiting_human_verify
trigger: "Users cannot add new class types — AJAX request to admin-ajax.php returns 403 Forbidden."
created: 2026-03-03T00:00:00Z
updated: 2026-03-03T00:01:00Z
---

## Current Focus

hypothesis: CONFIRMED — LookupTableAjaxHandler write operations call requireAuth('lookup_table_nonce', $config['capability']) where capability is 'manage_options'. Non-admin logged-in users fail current_user_can('manage_options') and receive 403. Nonce itself is valid.
test: Traced full call chain: JS -> wp_ajax_wecoza_lookup_table -> handleRequest() -> handleCreate() -> requireAuth() -> requireCapability('manage_options') -> 403
expecting: Removing capability check (or changing to is_user_logged_in()) will fix the 403 for all logged-in users
next_action: Apply fix — remove requireCapability from write handlers, keep nonce check only

## Symptoms

expected: User fills in a new class type name and submits the form. The class type should be saved and appear in the list.
actual: The form submission triggers an AJAX call to /wecoza/wp-admin/admin-ajax.php which returns 403 Forbidden. Nothing is saved.
errors: |
  /wecoza/wp-admin/admin-ajax.php:1 Failed to load resource: the server responded with a status of 403 (Forbidden)
reproduction: Navigate to wecoza.co.za/wecoza/app/manage-class-types/ (shortcode: wecoza_manage_class_types), try to add a new class type.
started: Reported 2026-03-03. Unknown if ever worked.

## Eliminated

- hypothesis: Nonce mismatch (PHP uses different action string from JS)
  evidence: Both PHP (wp_create_nonce) and JS (WeCozaLookupTables.nonce) use 'lookup_table_nonce'. Consistent throughout codebase.
  timestamp: 2026-03-03T00:01:00Z

- hypothesis: AJAX action not registered (WordPress returning -1 or 0)
  evidence: LookupTableAjaxHandler instantiated in plugins_loaded at priority 5, registers wp_ajax_wecoza_lookup_table. Browser gets 403, not 0/-1.
  timestamp: 2026-03-03T00:01:00Z

- hypothesis: Script not enqueued (WeCozaLookupTables undefined in JS)
  evidence: If WeCozaLookupTables were undefined, JS would throw ReferenceError before making any AJAX call. The AJAX IS being made (server returns 403).
  timestamp: 2026-03-03T00:01:00Z

- hypothesis: Server-level 403 (htaccess or security plugin)
  evidence: No .htaccess rules for AJAX. No security plugins installed. 403 is application-level from wp_send_json_error($response, 403).
  timestamp: 2026-03-03T00:01:00Z

## Evidence

- timestamp: 2026-03-03T00:00:30Z
  checked: LookupTableController.php TABLES constant
  found: All lookup tables (including class_types) have 'capability' => 'manage_options'
  implication: Only WordPress admins can pass the capability check in write operations

- timestamp: 2026-03-03T00:00:45Z
  checked: LookupTableAjaxHandler.php handleCreate/handleUpdate/handleDelete
  found: All call AjaxSecurity::requireAuth('lookup_table_nonce', $config['capability']) which calls requireNonce THEN requireCapability
  implication: Non-admin logged-in users pass nonce check but fail capability check — exactly produces 403

- timestamp: 2026-03-03T00:00:50Z
  checked: git commit ab3e9b8 "fix(auth): remove capability restrictions so all logged-in users have full access"
  found: Removed manage_options from 10 progression handlers, 1 attendance handler, 8 client handlers. LookupTableAjaxHandler was NOT included in this fix.
  implication: The same problem that was fixed elsewhere was overlooked for the LookupTables module

- timestamp: 2026-03-03T00:00:55Z
  checked: AjaxSecurity::requireAuth() implementation
  found: Calls requireNonce first then requireCapability. Both return 403 on failure. The 403 for logged-in users is from the capability check.
  implication: Fix is to replace requireAuth with requireNonce only (matching the pattern from ab3e9b8)

## Resolution

root_cause: LookupTableAjaxHandler write operations (create/update/delete) call AjaxSecurity::requireAuth() which enforces manage_options capability. Logged-in non-admin users pass the nonce check but fail current_user_can('manage_options'), receiving HTTP 403. The LookupTables module was missed when commit ab3e9b8 removed capability restrictions from other modules.
fix: Replaced requireAuth('lookup_table_nonce', $config['capability']) with requireNonce('lookup_table_nonce') in handleCreate, handleUpdate, handleDelete. Login is the access gate per project auth policy (commit ab3e9b8).
verification: Code review confirmed all three write handlers now use requireNonce only. Nonce action 'lookup_table_nonce' matches JS WeCozaLookupTables.nonce. Awaiting human verification on the live page.
files_changed:
  - src/LookupTables/Ajax/LookupTableAjaxHandler.php
