# Dev Test Toolbar — Design Document

**Date:** 2026-02-17
**Status:** Draft v2 — post Gemini review + codebase verification

## Problem

WeCoza has 7 forms across 5 modules with ~160+ fields total. Manual testing requires typing realistic data into every field, navigating cascading dropdowns, and respecting a dependency chain (Locations → Clients → Learners → Agents → Classes). This is slow and error-prone.

## Solution

A floating dev toolbar (debug mode only) with three buttons:

| Button | Action |
|--------|--------|
| **Fill** | Populate all fields on the current form with realistic SA test data |
| **Fill + Submit** | Fill all fields and auto-click the form's submit button |
| **Wipe** | TRUNCATE transactional WeCoza PG tables, reset sequences, clear WP transients. Preserves reference/lookup tables. |

## Design Decisions

1. **No separate DB seeder** — forms seed the database through the real submission pipeline, testing validation and business logic simultaneously
2. **DOM-first approach** — dropdown values are read from the DOM, never hardcoded. Plain `<select>` elements throughout (no Select2, no Choices.js — agent form actively destroys Select2)
3. **Hardcoded SA data pools** — no external libraries. Names, phones, banks, SETAs etc. stored as JS arrays. SA ID numbers generated algorithmically (Luhn checksum)
4. **Debug mode only** — toolbar loads only when `WP_DEBUG === true`. AJAX wipe handler also checks server-side
5. **Native HTML5 date inputs** — all date fields use `type="date"`, no flatpickr or third-party date pickers. Simple `.value = 'YYYY-MM-DD'` assignment
6. **Explicit table allowlist for wipe** — reference/lookup tables are preserved, only transactional data is truncated

## Forms Covered

| Module | Form | Shortcode | ~Fields |
|--------|------|-----------|---------|
| Clients | Location Capture | `[wecoza_locations_capture]` | 8 |
| Clients | Client Capture | `[wecoza_capture_clients]` | 13 |
| Clients | Client Update | `[wecoza_update_clients]` | 13 |
| Learners | Learner Capture | `[wecoza_learners_form]` | 25 |
| Learners | Learner Update | `[wecoza_learners_update_form]` | 25 |
| Agents | Agent Capture | `[wecoza_capture_agents]` | 40 |
| Classes | Class Capture | `[wecoza_capture_class]` | 50+ |

**Seeding order (dependency chain):**
```
1. Locations (no dependencies)
2. Clients (needs locations for Province/Town/Suburb)
3. Learners (no strict dependency, but benefits from existing data)
4. Agents (no strict dependency)
5. Classes (needs clients, locations, learners, agents)
```

## Data Generation Strategy

### Text Fields — SA Data Pools

```
firstNames:  ['Sipho', 'Thandiwe', 'Johan', 'Naledi', 'Pieter', 'Zanele', ...]
surnames:    ['Nkosi', 'Van der Merwe', 'Dlamini', 'Botha', 'Mkhize', ...]
streets:     ['Main Road', 'Voortrekker Rd', 'Church St', 'Long St', ...]
banks:       ['ABSA', 'FNB', 'Standard Bank', 'Nedbank', 'Capitec']
setas:       ['BANKSETA', 'CATHSSETA', 'CHIETA', 'ETDP SETA', 'EWSETA', ...]
```

### SA ID Number — Algorithmic

```
Format: YYMMDD GSSS C A Z
- YYMMDD: date of birth
- GSSS:   gender (0000-4999 female, 5000-9999 male)
- C:      citizenship (0=SA, 1=permanent resident)
- A:      usually 8
- Z:      Luhn checksum digit
```

Generated to be structurally valid (passes Luhn check).

### Contact Info — Derived

```
Phone:  0XX XXX XXXX (prefixes: 071, 082, 063, 079, 060, 061, ...)
Email:  firstname.surname@testmail.co.za (derived from generated name)
```

### Dropdowns — DOM Reading

```js
function pickRandomOption(selectElement) {
    const options = [...selectElement.options].filter(o => o.value !== '');
    return options[Math.floor(Math.random() * options.length)];
}
```

Never generates dropdown values. Always selects from what the database returned.

### Cascading Dropdowns — Per-Module Patterns

**IMPORTANT:** Cascading behaviour differs by module. Verified from codebase:

**Client form (Province → Town → Suburb):**
- Hierarchy is **pre-loaded as a JS object** on page load (not AJAX per-cascade)
- `populateProvinces()`, `populateTowns()`, `populateSuburbs()` are synchronous DOM manipulation
- Fill approach: set Province `.val()` → trigger `change` → Towns populate instantly → set Town → trigger `change` → Suburbs populate instantly
- **No MutationObserver needed** — just sequential `.val().trigger('change')` calls

**Agent form (address fields):**
- Province is a **static `<select>`** with hardcoded values + a `provinceMap` lookup
- Address fields (street, suburb, town, postal code) are **plain text inputs** filled by Google Places API
- Fill approach: set Province dropdown → fill text inputs directly
- **No cascading, no async**

**Class form (Client → Site):**
- Client dropdown triggers **AJAX call** to load associated sites
- This is the **only true async cascade** — needs async handling
- Fill approach: set Client → trigger `change` → wait for Site dropdown to populate → pick Site
- **Use `jQuery(document).ajaxComplete()` or poll `jQuery.active === 0`** to detect when sites load

**Location form:**
- All plain text inputs (street, suburb, town, postal code) + Province `<select>`
- Google Places integration fills fields but we bypass it for test data
- **No cascading, no async**

### Async Handling (Class Form Only)

```js
// Only the class form needs async waiting (Client → Site AJAX)
function waitForAjaxIdle(callback, timeout = 5000) {
    const start = Date.now();
    const check = () => {
        if (jQuery.active === 0) {
            // Extra 100ms debounce after last AJAX completes
            setTimeout(callback, 100);
        } else if (Date.now() - start < timeout) {
            setTimeout(check, 50);
        } else {
            console.warn('[DevToolbar] AJAX timeout — some dropdowns may be empty');
            callback(); // proceed anyway, flag incomplete fields
        }
    };
    check();
}
```

### Conditional / Dynamic Fields

Some fields appear/disappear based on other selections:
- **Learner form:** Employment details hidden until "Employed" selected. Employer field conditional.
- **Learner form:** ID type toggle (SA ID vs Passport) changes field validation
- **Agent form:** Criminal record date shown only when "Yes" selected
- **Class form:** Schedule pattern changes visible day/time fields

Fill approach: set the controlling field first, trigger `change`, then fill the dependent fields that become visible. Check `field.is(':visible')` before filling.

### Dates — Native HTML5

All date fields use `<input type="date">`. No third-party date picker libraries.

```js
// Simple direct value assignment
dateInput.value = '2025-08-15';
dateInput.dispatchEvent(new Event('change', { bubbles: true }));
```

Date ranges:
```
Date of birth:  18-55 years ago
Start dates:    within last 6 months
End dates:      3-12 months after start date
SACE dates:     registration in past, expiry in future
```

### Radio Buttons & Checkboxes

```js
// Radio: find the group, pick random option, click it
const radios = form.querySelectorAll('input[name="id_type"]');
const pick = radios[Math.floor(Math.random() * radios.length)];
pick.checked = true;
pick.dispatchEvent(new Event('change', { bubbles: true }));

// Checkbox: randomly check/uncheck
checkbox.checked = Math.random() > 0.5;
checkbox.dispatchEvent(new Event('change', { bubbles: true }));
```

### File Uploads — Skipped

File upload fields (portfolios, documents) are skipped. Console message indicates they need manual handling.

## Wipe Functionality

### Flow
1. Click "Wipe" button
2. Browser `confirm()` dialog: "This will DELETE ALL WeCoza transactional data and reset IDs. Reference tables (locations, products, class_types) will be preserved. Continue?"
3. AJAX POST to `admin-ajax.php` with action `wecoza_dev_wipe_data`
4. Server-side handler:
   - Checks `WP_DEBUG === true`
   - Verifies nonce
   - Checks `manage_options` capability
   - Connects via `wecoza_db()`
   - Runs `TRUNCATE table_name RESTART IDENTITY CASCADE` for each table in the **transactional allowlist**
   - Clears WeCoza WP transients
   - Returns JSON with count of tables truncated + any errors
5. Toolbar shows success message with details
6. Page reloads after 2 seconds

### Table Classification (Explicit Allowlist)

**TRUNCATE (transactional data — 33 tables):**
```
agents, agent_meta, agent_notes, agent_absences, agent_orders, agent_replacements,
clients, client_communications,
learners, learner_hours_log, learner_lp_tracking, learner_placement_level,
learner_portfolios, learner_products, learner_progression_portfolios,
learner_progressions, learner_qualifications, learner_sponsors,
classes, class_agents, class_events, class_material_tracking, class_notes,
class_schedules, class_subjects,
sites, attendance_registers, collections, deliveries, employers,
exams, exam_results, files, history, latest_document, progress_reports, qa_visits
```

**PRESERVE (reference/lookup data — 8 tables):**
```
locations          — Province/Town/Suburb hierarchy (feeds cascading dropdowns)
class_types        — Class type definitions (reference data)
class_type_subjects — Subject mappings per class type
products           — Learning programmes/qualifications (reference data)
user_roles         — Role definitions (system config)
user_permissions   — Permission mappings (system config)
users              — User accounts (system config, mirrors WP users)
sites_migration_backup — Historical backup data
sites_address_audit    — Audit trail
```

**CRITICAL:** The `locations` table MUST be preserved. It contains the Province → Town → Suburb hierarchy that feeds the client form cascading dropdowns. Truncating it would break all client/class forms.

### WP Transient Cleanup

```php
// Delete all transients with wecoza_ prefix
DELETE FROM wp_options WHERE option_name LIKE '_transient_wecoza_%'
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_wecoza_%'
```

## File Structure

```
assets/
  js/
    dev/
      dev-toolbar.js              # Toolbar UI, button handlers, form detection
      form-fillers/
        data-pools.js             # SA names, phones, banks, SETAs, streets
        generators.js             # SA ID algorithm, phone/email formatters, date helpers
        location-filler.js        # Location form (plain text inputs + province select)
        client-filler.js          # Client form (sync cascading Province→Town→Suburb)
        learner-filler.js         # Learner form (conditional employment/ID type fields)
        agent-filler.js           # Agent form (static province + text address fields)
        class-filler.js           # Class form (async Client→Site + complex schedule)
src/
  Dev/
    DevToolbarController.php      # Enqueues scripts only when WP_DEBUG
    WipeDataHandler.php           # AJAX handler: truncate allowlist + clear transients
```

Note: `cascade-handler.js` removed from original design. Client cascading is synchronous (pre-loaded hierarchy) and class form async is handled inline with `jQuery.active` polling. No generic MutationObserver module needed.

## Fill + Submit: Completion Signal

Since the only async operation is the class form's Client → Site AJAX:

1. **Non-class forms:** Fill is synchronous. Submit immediately after filling.
2. **Class form:** Fill all sync fields → set Client dropdown → trigger `change` → poll `jQuery.active === 0` → fill Site dropdown → fill remaining fields → submit.

```js
async function fillAndSubmit(form, fillerFn) {
    await fillerFn(form);  // fillerFn returns Promise (resolves after any async cascades)
    const submitBtn = form.querySelector('[type="submit"], .btn-submit');
    if (submitBtn) submitBtn.click();
}
```

Each filler function returns a Promise. Synchronous fillers resolve immediately. Class filler resolves after AJAX idle.

## Toolbar UI

Floating fixed-position bar at bottom-right of viewport:

```
+---------------------------------------+
|  WeCoza Dev Tools                 [_] |
|  [Fill]  [Fill + Submit]  [Wipe All]  |
|  Status: Client Capture form detected |
+---------------------------------------+
```

- Semi-transparent dark background
- Collapse/expand toggle `[_]`
- Shows which form was detected (or "No WeCoza form found")
- Wipe button is red with confirmation
- Minimal inline CSS (no external stylesheet dependency)
- z-index high enough to float above Bootstrap modals

## Form Detection

Each WeCoza form identified by wrapper element (to be verified during implementation):

```js
const FORM_MAP = {
    '#locationCaptureForm':        'location',
    '#clientCaptureForm':          'client',
    '#clientUpdateForm':           'client',
    '#learnerCaptureForm':         'learner',
    '#learnerUpdateForm':          'learner',
    '#agentCaptureForm':           'agent',
    '.wecoza-class-capture-form':  'class',
};
```

These selectors will be verified against actual form markup during implementation.

## Security

- **JS only loads when `WP_DEBUG === true`** (checked in PHP enqueue)
- **AJAX wipe handler checks `WP_DEBUG` server-side** (cannot be called in production)
- **Nonce verification** on wipe AJAX call
- **Capability check** — wipe requires `manage_options` capability (admin only)
- **Explicit table allowlist** — only named tables are truncated, never dynamic discovery
- **No test data markers in DB** — data created via forms is indistinguishable from real data (intentional: tests the real pipeline)

## Limitations

- File upload fields are skipped (require manual interaction)
- Forms must be visited individually in dependency order
- Class form Client → Site depends on AJAX response (5s timeout)
- SA ID generation produces structurally valid but fictional IDs
- Class form is the most complex (~50+ fields with partials) — build incrementally
- Google Places integration on location/agent forms is bypassed (test data fills fields directly)

## Future Enhancements (v2)

- **Snapshot & Restore** — save generated form state to localStorage, replay with one click. Useful for re-testing the same scenario repeatedly.
- **Edge Case / Chaos mode** — fill with boundary values (max-length strings, special characters, XSS payloads) for validation testing.
- **Form counter** — show "3 of 5 forms seeded" progress across the dependency chain.

## Review Notes (Gemini Review 2026-02-17)

Gemini review was conducted and findings verified against the actual codebase:

| Gemini Finding | Verdict | Reason |
|---------------|---------|--------|
| flatpickr date picker handling | **Invalid** | All date fields use native HTML5 `type="date"` |
| Select2/Choices.js API calls | **Invalid** | Agent form actively destroys Select2. All forms use plain `<select>` |
| MutationObserver for all cascading | **Overstated** | Client cascading is synchronous (pre-loaded hierarchy). Only class form Client→Site uses AJAX |
| Race conditions with async | **Valid (limited)** | Only applies to class form. Mitigated with `jQuery.active` polling |
| Explicit table allowlist for wipe | **Valid (critical)** | `locations` table is reference data — truncating it breaks all forms |
| Fill+Submit stable state pattern | **Valid (simplified)** | `jQuery.active === 0` + debounce sufficient since most cascading is sync |
| Snapshot & Restore feature | **Valid (deferred to v2)** | Useful but not essential for v1 |

## Testing the Toolbar Itself

Manual verification checklist per form:
- [ ] Toolbar appears on the page
- [ ] Correct form detected and displayed in status
- [ ] "Fill" populates all visible fields
- [ ] Conditional fields appear and fill correctly
- [ ] Client form cascading resolves (Province → Town → Suburb)
- [ ] Class form async cascade resolves (Client → Site)
- [ ] Radio buttons and checkboxes are set
- [ ] Date fields contain valid dates in correct ranges
- [ ] "Fill + Submit" triggers successful form submission
- [ ] Submitted data appears correctly in display/list views
- [ ] "Wipe" clears transactional data only
- [ ] Reference tables (locations, products, class_types) survive wipe
- [ ] Toolbar does NOT appear when WP_DEBUG is false
