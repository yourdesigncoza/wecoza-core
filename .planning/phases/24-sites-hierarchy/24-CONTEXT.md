# Phase 24: Sites Hierarchy - Context

**Gathered:** 2026-02-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Verify and fix sites hierarchy integration within wecoza-core. Sites are NOT standalone — they are embedded within the client create/edit flow. This phase ensures head site creation (with client), sub-site management, parent-child display in client listing, and location hydration all work end-to-end. Matches legacy wecoza-clients-plugin behavior exactly.

</domain>

<decisions>
## Implementation Decisions

### Integration Scope (from legacy analysis)
- Sites have NO standalone shortcodes, controller, views, or JS — this matches the legacy plugin
- Sites are managed exclusively through client capture/update forms
- The `SitesModel.php` is already migrated to `src/Clients/Models/`
- 5 AJAX handlers already registered in `ClientAjaxHandlers.php`: `wecoza_save_sub_site`, `wecoza_get_head_sites`, `wecoza_get_sub_sites`, `wecoza_delete_sub_site`, `wecoza_get_sites_hierarchy`
- Client forms already include site fields (site_name, location hierarchy, street address)

### Head Site Creation Flow (match legacy)
- When creating a new main client, head site is auto-created from client name + selected location
- `parent_site_id = NULL` indicates head site
- Validated via `SitesModel::validateHeadSite()` (requires site_name + place_id)
- Head site cache refreshed after save

### Sub-Site Creation Flow (match legacy)
- Sub-sites created when "Is SubClient" checkbox is checked on client form
- Main client dropdown appears, user selects parent client
- Sub-site links to parent client's head site via `parent_site_id`
- Validated via `SitesModel::validateSubSite()` — parent must exist and belong to same client
- Database trigger `trg_sites_same_client` enforces client_id match between parent/child

### Site Listing Display (match legacy)
- Sites are shown within the clients table, NOT a separate listing
- Client hydration via `SitesModel::hydrateClients()` adds head_site data to each client row
- Town column in clients table comes from head site's location (suburb, town, province via place_id)
- No dedicated sites listing page exists in legacy — don't create one

### Location Selection for Sites (match legacy)
- Uses location hierarchy: Province > Town > Suburb cascading dropdowns
- Suburb selection auto-fills postal code, street address
- Location hierarchy fetched via `wecoza_get_locations` AJAX endpoint
- SitesModel caches location hierarchy in WordPress transient `wecoza_clients_location_cache`

### Database Schema (already exists)
- `sites` table: `site_id`, `client_id`, `site_name`, `parent_site_id`, `place_id`, `created_at`, `updated_at`
- DB views: `v_client_head_sites` (parent_site_id IS NULL), `v_client_sub_sites` (parent_site_id IS NOT NULL)
- Trigger: `trg_sites_same_client` — prevents cross-client parent-child relationships
- FK constraints: CASCADE on client delete, RESTRICT on parent site delete, RESTRICT on location delete
- Indexes: `idx_sites_client_hierarchy`, `idx_sites_client_place`, `idx_sites_place_lookup`, `idx_sites_site_name_lower`

### Verification Focus (same pattern as Phases 22-23)
- Phase 22 and 23 revealed wiring bugs: DOM ID mismatches, AJAX action name discrepancies, localization key differences, missing nopriv removal
- This phase should follow the same verify-and-fix pattern:
  1. Test head site creation via client capture form
  2. Test sub-site creation via sub-client form
  3. Test site data display in clients listing (town/location hydration)
  4. Test site data loading in client update form
  5. Fix any AJAX, DOM, or integration issues found

### Claude's Discretion
- Order of verification tasks
- How to structure fix plans if issues are found
- Whether to split into 1 or 2 plan files

</decisions>

<specifics>
## Specific Ideas

- Follow Phase 22-23 pattern: Plan 1 for shortcode rendering/wiring fixes, Plan 2 for AJAX endpoint testing and E2E verification
- Legacy ClientsController is 1251 lines — sites logic is embedded in `handleFormSubmission()`, `sanitizeFormData()`, and several `ajax*()` methods
- The `wecoza_get_branch_clients` AJAX endpoint (returns head + sub-sites for a client) may also need verification
- Head site cache uses WordPress transient `wecoza_clients_head_sites_cache` — verify cache invalidation works
- Location hierarchy lazy-loads via AJAX on form render — verify this path works for site location selection

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 24-sites-hierarchy*
*Context gathered: 2026-02-12*
