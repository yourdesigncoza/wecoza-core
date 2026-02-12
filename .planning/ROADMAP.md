# Roadmap: WeCoza Core

## Milestones

- ✅ **v1.0 Events Integration** - Phases 1-7 (shipped 2026-02-02)
- ✅ **v1.1 Quality & Performance** - Phases 8-12 (shipped 2026-02-02)
- ✅ **v1.2 Event Tasks Refactor** - Phases 13-18 (shipped 2026-02-05)
- ✅ **v1.3 Fix Material Tracking Dashboard** - Phases 19-20 (shipped 2026-02-06)
- 🚧 **v2.0 Clients Integration** - Phases 21-25 (in progress)

## Phases

<details>
<summary>✅ v1.0 Events Integration (Phases 1-7) - SHIPPED 2026-02-02</summary>

### Phase 1: Foundation Architecture
**Goal**: Establish src/Events/ namespace and core infrastructure
**Plans**: 2 plans

Plans:
- [x] 01-01: Namespace structure and PSR-4 autoloading
- [x] 01-02: Database consolidation to PostgresConnection

### Phase 2: Task Management Core
**Goal**: Complete task system with dashboard
**Plans**: 3 plans

Plans:
- [x] 02-01: Task models and repositories
- [x] 02-02: Task service and controller
- [x] 02-03: Dashboard views and AJAX handlers

### Phase 3: Material Tracking
**Goal**: Material delivery tracking with automated alerts
**Plans**: 2 plans

Plans:
- [x] 03-01: Material tracking models and cron job
- [x] 03-02: Material tracking dashboard UI

### Phase 4: AI Summarization
**Goal**: OpenAI integration with PII protection
**Plans**: 2 plans

Plans:
- [x] 04-01: OpenAI service and PII filtering
- [x] 04-02: AI summary integration with task system

### Phase 5: Email Notifications
**Goal**: Automated email notifications on class changes
**Plans**: 2 plans

Plans:
- [x] 05-01: Email templates and notification service
- [x] 05-02: Cron job for email delivery

### Phase 6: PostgreSQL Triggers
**Goal**: Database triggers for automatic event capture
**Plans**: 1 plan

Plans:
- [x] 06-01: Trigger migration and testing

### Phase 7: Testing & Verification
**Goal**: Full integration testing
**Plans**: 1 plan

Plans:
- [x] 07-01: UAT and bug fixes

</details>

<details>
<summary>✅ v1.1 Quality & Performance (Phases 8-12) - SHIPPED 2026-02-02</summary>

### Phase 8: Bug Fixes
**Goal**: Fix critical bugs found in code analysis
**Plans**: 2 plans

Plans:
- [x] 08-01: Column name fixes and PDO error handling
- [x] 08-02: Portfolio save and file upload fixes

### Phase 9: Security Hardening
**Goal**: Production-ready security improvements
**Plans**: 2 plans

Plans:
- [x] 09-01: Exception sanitization and MIME validation
- [x] 09-02: SQL injection prevention with quoteIdentifier

### Phase 10: Performance Optimization
**Goal**: Action Scheduler integration for async processing
**Plans**: 3 plans

Plans:
- [x] 10-01: Action Scheduler setup
- [x] 10-02: Batch processing migration
- [x] 10-03: Performance testing

### Phase 11: Data Privacy
**Goal**: Enhanced PII protection
**Plans**: 3 plans

Plans:
- [x] 11-01: Remove PII mapping exposure
- [x] 11-02: Heuristic PII detection
- [x] 11-03: Email masking improvements

### Phase 12: Architecture Improvements
**Goal**: Code quality with PHP 8.1 features
**Plans**: 3 plans

Plans:
- [x] 12-01: Typed DTOs with readonly properties
- [x] 12-02: Enum refactoring
- [x] 12-03: SRP and service extraction

</details>

<details>
<summary>✅ v1.2 Event Tasks Refactor (Phases 13-18) - SHIPPED 2026-02-05</summary>

### Phase 13: Event System Foundation
**Goal**: Replace triggers with manual event capture
**Plans**: 3 plans

Plans:
- [x] 13-01: Event schema design (JSONB event_dates)
- [x] 13-02: EventDispatcher service
- [x] 13-03: Form integration for event capture

### Phase 14: Task Derivation
**Goal**: Tasks derived from event_dates JSONB
**Plans**: 3 plans

Plans:
- [x] 14-01: Event-to-task mapping logic
- [x] 14-02: Agent Order Number always-present task
- [x] 14-03: Dashboard refactor for event-based tasks

### Phase 15: Bidirectional Sync
**Goal**: Dashboard and form stay in sync
**Plans**: 3 plans

Plans:
- [x] 15-01: Completion metadata in event_dates
- [x] 15-02: Form reads completion state
- [x] 15-03: Dashboard writes back to JSONB

### Phase 16: Notification System
**Goal**: Email + dashboard notifications with AI
**Plans**: 3 plans

Plans:
- [x] 16-01: Notification models and service
- [x] 16-02: Dashboard UI with unread filter
- [x] 16-03: Email delivery with Action Scheduler

### Phase 17: Code Cleanup
**Goal**: Remove deprecated trigger-based code
**Plans**: 2 plans

Plans:
- [x] 17-01: Identify deprecated files
- [x] 17-02: Delete and verify no breakage

### Phase 18: Multi-Recipient Config
**Goal**: WordPress Settings API for notification recipients
**Plans**: 2 plans

Plans:
- [x] 18-01: Settings page UI
- [x] 18-02: Recipient configuration per event type

</details>

<details>
<summary>✅ v1.3 Fix Material Tracking Dashboard (Phases 19-20) - SHIPPED 2026-02-06</summary>

### Phase 19: Material Dashboard Rewrite
**Goal**: Show classes with Deliveries events from JSONB
**Plans**: 2 plans

Plans:
- [x] 19-01: Repository query event_dates JSONB
- [x] 19-02: Event-based status badges and filters

### Phase 20: Dashboard Enhancements
**Goal**: Add delivery date column and simplify filters
**Plans**: 1 plan

Plans:
- [x] 20-01: Delivery date extraction and UI polish

</details>

### 🚧 v2.0 Clients Integration (In Progress)

**Milestone Goal:** Integrate standalone wecoza-clients-plugin into wecoza-core as unified Clients module with client CRUD, location management, sites hierarchy, and Google Maps integration.

#### Phase 21: Foundation Architecture
**Goal**: Establish Clients module namespace, database, views, and integration hooks
**Depends on**: Phase 20 (v1.3 complete)
**Requirements**: ARCH-01, ARCH-02, ARCH-03, ARCH-04, ARCH-05, ARCH-06, ARCH-07, ARCH-08
**Success Criteria** (what must be TRUE):
  1. Clients module classes load from `src/Clients/` with `WeCoza\Clients\` namespace
  2. All database queries use `wecoza_db()` singleton instead of standalone DatabaseService
  3. View templates render via `wecoza_view('clients/...')` from `views/clients/`
  4. JavaScript assets load from `assets/js/clients/` through wecoza-core asset registration
  5. Shortcodes register through wecoza-core entry point and render correctly
**Plans**: 2 plans

Plans:
- [x] 21-01-PLAN.md -- Foundation: autoloader, config, ViewHelpers, 4 Models, 2 Repositories
- [x] 21-02-PLAN.md -- Controllers, Views, JS assets, AJAX handlers, wecoza-core.php wiring

#### Phase 22: Client Management
**Goal**: Full client CRUD with hierarchy, search, filter, CSV export, and statistics
**Depends on**: Phase 21
**Requirements**: CLT-01, CLT-02, CLT-03, CLT-04, CLT-05, CLT-06, CLT-07, CLT-08, CLT-09, SC-01, SC-02
**Success Criteria** (what must be TRUE):
  1. User can create client with company details, contact person, status, and SETA via `[wecoza_capture_clients]`
  2. User can view sortable/paginated clients list with search and filter via `[wecoza_display_clients]`
  3. User can edit existing client and soft-delete client (sets deleted_at)
  4. User can set client as main or sub-client and view sub-clients under main client
  5. User can export filtered clients to CSV and view client statistics (counts, SETA breakdown)
**Plans**: 2 plans

Plans:
- [x] 22-01-PLAN.md -- Shortcode rendering verification and wiring fixes
- [x] 22-02-PLAN.md -- AJAX endpoint testing and end-to-end CRUD verification

#### Phase 23: Location Management
**Goal**: Location CRUD with Google Maps autocomplete, geocoordinates, and duplicate detection
**Depends on**: Phase 21
**Requirements**: LOC-01, LOC-02, LOC-03, LOC-04, LOC-05, LOC-06, LOC-07, SC-03, SC-04, SC-05
**Success Criteria** (what must be TRUE):
  1. User can create location with suburb, town, postal code, province via `[wecoza_locations_capture]`
  2. User can search locations using Google Maps Places autocomplete when API key configured
  3. User can manually enter location when Google Maps unavailable
  4. System stores latitude/longitude for locations and warns about duplicate addresses
  5. User can view/edit locations via `[wecoza_locations_list]` and `[wecoza_locations_edit]`
**Plans**: 2 plans

Plans:
- [x] 23-01-PLAN.md -- Shortcode rendering, DOM wiring fixes, and method signature corrections
- [x] 23-02-PLAN.md -- AJAX endpoint fixes and end-to-end CRUD verification

#### Phase 24: Sites Hierarchy
**Goal**: Head sites and sub-sites with parent-child relationships and location hydration
**Depends on**: Phase 22, Phase 23
**Requirements**: SITE-01, SITE-02, SITE-03, SITE-04
**Success Criteria** (what must be TRUE):
  1. User can create head sites (main client locations) and sub-sites linked to head site
  2. User can view parent-child site relationships in site listing
  3. System hydrates site data with location details from locations table (suburb, town, province)
**Plans**: TBD

Plans:
- [ ] 24-01: TBD

#### Phase 25: Integration Testing & Cleanup
**Goal**: Verify full integration and remove standalone plugin artifacts
**Depends on**: Phase 21, Phase 22, Phase 23, Phase 24
**Requirements**: CLN-01, CLN-02
**Success Criteria** (what must be TRUE):
  1. All client/location/site functionality works identically to standalone plugin
  2. Standalone wecoza-clients-plugin can be deactivated without breaking functionality
  3. `.integrate/wecoza-clients-plugin/` folder removed from repository
**Plans**: TBD

Plans:
- [ ] 25-01: TBD

## Progress

**Execution Order:**
Phases execute in numeric order.

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundation Architecture | v1.0 | 2/2 | Complete | 2026-02-02 |
| 2. Task Management Core | v1.0 | 3/3 | Complete | 2026-02-02 |
| 3. Material Tracking | v1.0 | 2/2 | Complete | 2026-02-02 |
| 4. AI Summarization | v1.0 | 2/2 | Complete | 2026-02-02 |
| 5. Email Notifications | v1.0 | 2/2 | Complete | 2026-02-02 |
| 6. PostgreSQL Triggers | v1.0 | 1/1 | Complete | 2026-02-02 |
| 7. Testing & Verification | v1.0 | 1/1 | Complete | 2026-02-02 |
| 8. Bug Fixes | v1.1 | 2/2 | Complete | 2026-02-02 |
| 9. Security Hardening | v1.1 | 2/2 | Complete | 2026-02-02 |
| 10. Performance Optimization | v1.1 | 3/3 | Complete | 2026-02-02 |
| 11. Data Privacy | v1.1 | 3/3 | Complete | 2026-02-02 |
| 12. Architecture Improvements | v1.1 | 3/3 | Complete | 2026-02-02 |
| 13. Event System Foundation | v1.2 | 3/3 | Complete | 2026-02-05 |
| 14. Task Derivation | v1.2 | 3/3 | Complete | 2026-02-05 |
| 15. Bidirectional Sync | v1.2 | 3/3 | Complete | 2026-02-05 |
| 16. Notification System | v1.2 | 3/3 | Complete | 2026-02-05 |
| 17. Code Cleanup | v1.2 | 2/2 | Complete | 2026-02-05 |
| 18. Multi-Recipient Config | v1.2 | 2/2 | Complete | 2026-02-05 |
| 19. Material Dashboard Rewrite | v1.3 | 2/2 | Complete | 2026-02-06 |
| 20. Dashboard Enhancements | v1.3 | 1/1 | Complete | 2026-02-06 |
| 21. Foundation Architecture | v2.0 | 2/2 | Complete | 2026-02-11 |
| 22. Client Management | v2.0 | 2/2 | Complete | 2026-02-11 |
| 23. Location Management | v2.0 | 2/2 | Complete | 2026-02-12 |
| 24. Sites Hierarchy | v2.0 | 0/? | Not started | - |
| 25. Integration Testing & Cleanup | v2.0 | 0/? | Not started | - |
