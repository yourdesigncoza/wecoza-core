# Phase 22: Client Management - Context

**Gathered:** 2026-02-11
**Status:** Ready for planning

<domain>
## Phase Boundary

Full client CRUD with hierarchy, search, filter, CSV export, and statistics. Phase 21 migrated all structural code (Models, Controllers, AJAX handlers, Views, JS assets) into wecoza-core. This phase makes Client Management fully functional end-to-end: verify wiring, fix integration issues, ensure all shortcodes render and all AJAX endpoints respond correctly.

Locations and Sites hierarchy are separate phases (23, 24).

</domain>

<decisions>
## Implementation Decisions

### Migration approach
- Preserve existing standalone plugin behavior exactly — no redesign
- No known bugs to fix; the standalone plugin works correctly
- Goal is functional parity within wecoza-core architecture

### Authentication & permissions
- Entire WP site requires login — unauthenticated users cannot reach any page
- Login-gating handles access control; no additional capability checks needed beyond what exists
- Preserve existing `manage_wecoza_clients` capability checks in AJAX handlers as defense-in-depth

### Client CRUD
- Create client via `[wecoza_capture_clients]` — preserve existing form fields, validation, and submission flow
- Edit client via `[wecoza_update_clients]` — preserve existing update form behavior
- Soft-delete via AJAX (sets `deleted_at`) — preserve existing delete confirmation flow
- All form validation rules preserved from standalone plugin

### Client list & display
- `[wecoza_display_clients]` — preserve existing table columns, sorting, search, pagination
- Filter options preserved as-is from standalone plugin
- CSV export format and contents preserved as-is

### Client hierarchy
- Main/sub-client relationships preserved as-is
- Display of sub-clients under main client preserved
- No changes to hierarchy management UI

### Statistics
- SETA breakdown and client counts preserved as-is from standalone plugin

### Claude's Discretion
- Integration wiring details (how shortcodes and AJAX handlers connect through wecoza-core entry point)
- Any namespace or import path fixes needed post-Phase 21 migration
- Test approach for verifying functional parity

</decisions>

<specifics>
## Specific Ideas

No specific requirements — preserve existing standalone plugin behavior within wecoza-core architecture.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 22-client-management*
*Context gathered: 2026-02-11*
