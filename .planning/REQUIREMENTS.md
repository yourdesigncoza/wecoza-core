# Requirements: WeCoza Core v2.0 — Clients Integration

**Defined:** 2026-02-11
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin architecture

## v2.0 Requirements

Requirements for integrating wecoza-clients-plugin into wecoza-core as the Clients module.

### Architecture Migration

- [ ] **ARCH-01**: Clients module lives in `src/Clients/` with namespace `WeCoza\Clients\`
- [ ] **ARCH-02**: All classes use PSR-4 autoloading registered in wecoza-core's autoloader
- [ ] **ARCH-03**: Database queries use `wecoza_db()` singleton instead of standalone DatabaseService
- [ ] **ARCH-04**: View templates live in `views/clients/` using `wecoza_view()` helper
- [ ] **ARCH-05**: JavaScript assets live in `assets/js/clients/` registered through wecoza-core
- [ ] **ARCH-06**: Configuration merged into wecoza-core's `config/` structure
- [ ] **ARCH-07**: Shortcodes registered through wecoza-core entry point (`wecoza-core.php`)
- [ ] **ARCH-08**: AJAX handlers use `AjaxSecurity` nonce/capability patterns from core

### Client Management

- [ ] **CLT-01**: User can create a new client with company details, contact person, status, and SETA
- [ ] **CLT-02**: User can view list of all clients with sortable columns and pagination
- [ ] **CLT-03**: User can search clients by name and filter by status/SETA
- [ ] **CLT-04**: User can edit an existing client's details
- [ ] **CLT-05**: User can soft-delete a client (sets deleted_at, preserves data)
- [ ] **CLT-06**: User can designate a client as main client or sub-client of another
- [ ] **CLT-07**: User can view sub-clients belonging to a main client
- [ ] **CLT-08**: User can export clients list to CSV
- [ ] **CLT-09**: User can view client statistics (counts by status, SETA breakdown)

### Location Management

- [ ] **LOC-01**: User can create a location with suburb, town, postal code, and province
- [ ] **LOC-02**: User can search locations using Google Maps Places autocomplete (when API key set)
- [ ] **LOC-03**: User can manually enter location when Google Maps unavailable
- [ ] **LOC-04**: System stores latitude/longitude geocoordinates for locations
- [ ] **LOC-05**: User can view list of all locations with search and pagination
- [ ] **LOC-06**: User can edit an existing location
- [ ] **LOC-07**: System detects and warns about duplicate locations by street address

### Sites Hierarchy

- [ ] **SITE-01**: User can create head sites (main client locations)
- [ ] **SITE-02**: User can create sub-sites linked to a head site
- [ ] **SITE-03**: User can view parent-child site relationships
- [ ] **SITE-04**: System hydrates site data with location details from locations table

### Shortcodes

- [ ] **SC-01**: `[wecoza_capture_clients]` renders client creation/edit form
- [ ] **SC-02**: `[wecoza_display_clients]` renders clients table with search, filter, pagination, export
- [ ] **SC-03**: `[wecoza_locations_capture]` renders location creation form with Google Maps
- [ ] **SC-04**: `[wecoza_locations_list]` renders locations table with search and pagination
- [ ] **SC-05**: `[wecoza_locations_edit]` renders location edit form

### Cleanup

- [ ] **CLN-01**: Standalone wecoza-clients-plugin can be deactivated after migration verified
- [ ] **CLN-02**: `.integrate/` folder removed after successful integration

## Future Requirements

### Client Enhancements

- **CLT-F01**: Client communication history tracking (notes, calls, emails)
- **CLT-F02**: Client document management (file uploads)
- **CLT-F03**: Client-to-class linking (which clients have which classes)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Client billing/invoicing | Not part of current plugin, separate domain |
| CRM-style pipeline | Overkill for current needs |
| Client portal (self-service) | Would require auth system changes |
| Legacy folder migration | Reference only, marked DO NOT MODIFY |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ARCH-01 | Phase 21 | Pending |
| ARCH-02 | Phase 21 | Pending |
| ARCH-03 | Phase 21 | Pending |
| ARCH-04 | Phase 21 | Pending |
| ARCH-05 | Phase 21 | Pending |
| ARCH-06 | Phase 21 | Pending |
| ARCH-07 | Phase 21 | Pending |
| ARCH-08 | Phase 21 | Pending |
| CLT-01 | Phase 22 | Pending |
| CLT-02 | Phase 22 | Pending |
| CLT-03 | Phase 22 | Pending |
| CLT-04 | Phase 22 | Pending |
| CLT-05 | Phase 22 | Pending |
| CLT-06 | Phase 22 | Pending |
| CLT-07 | Phase 22 | Pending |
| CLT-08 | Phase 22 | Pending |
| CLT-09 | Phase 22 | Pending |
| LOC-01 | Phase 23 | Pending |
| LOC-02 | Phase 23 | Pending |
| LOC-03 | Phase 23 | Pending |
| LOC-04 | Phase 23 | Pending |
| LOC-05 | Phase 23 | Pending |
| LOC-06 | Phase 23 | Pending |
| LOC-07 | Phase 23 | Pending |
| SITE-01 | Phase 24 | Pending |
| SITE-02 | Phase 24 | Pending |
| SITE-03 | Phase 24 | Pending |
| SITE-04 | Phase 24 | Pending |
| SC-01 | Phase 22 | Pending |
| SC-02 | Phase 22 | Pending |
| SC-03 | Phase 23 | Pending |
| SC-04 | Phase 23 | Pending |
| SC-05 | Phase 23 | Pending |
| CLN-01 | Phase 25 | Pending |
| CLN-02 | Phase 25 | Pending |

**Coverage:**
- v2.0 requirements: 35 total
- Mapped to phases: 35
- Unmapped: 0

---
*Requirements defined: 2026-02-11*
*Last updated: 2026-02-11 after roadmap creation*
