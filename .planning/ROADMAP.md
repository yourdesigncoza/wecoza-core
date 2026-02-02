# Roadmap: WeCoza Core - Events Integration

## Overview

This roadmap migrates the wecoza-events-plugin (~7,700 lines) into wecoza-core, transforming it from a standalone plugin into an integrated module. The migration proceeds from foundational infrastructure (namespace conversion, PSR-4 autoloading) through database schema migration, then systematically enables each feature: task management, material tracking, AI summarization, and email notifications. Each phase delivers a verifiable capability that builds on previous work.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Code Foundation** - Namespace conversion, file structure, PSR-4 autoloading
- [ ] **Phase 2: Database Migration** - PostgreSQL triggers, functions, and schema fixes
- [ ] **Phase 3: Bootstrap Integration** - Module initialization and activation hooks
- [ ] **Phase 4: Task Management** - Class change monitoring and task dashboard
- [ ] **Phase 5: Material Tracking** - Delivery status tracking and alerts
- [ ] **Phase 6: AI Summarization** - OpenAI integration for class change summaries
- [ ] **Phase 7: Email Notifications** - Automated notifications on class changes

## Phase Details

### Phase 1: Code Foundation
**Goal**: Events module code exists in wecoza-core with correct namespaces and autoloading
**Depends on**: Nothing (first phase)
**Requirements**: INFRA-01, INFRA-02, INFRA-03, INFRA-04
**Success Criteria** (what must be TRUE):
  1. All events PHP files exist under `src/Events/` with proper directory structure (Controllers, Models, Repositories, Services, Shortcodes)
  2. All classes use `WeCoza\Events\*` namespace and can be instantiated without require_once
  3. Events module classes use `wecoza_db()` for database queries instead of separate connection class
  4. Running `wp wecoza test-db` succeeds with events module loaded
**Plans**: 3 plans

Plans:
- [x] 01-01-PLAN.md — Directory setup, PSR-4 config, Models/Repositories/Support migration
- [x] 01-02-PLAN.md — Services migration (TaskManager, AISummary, Notifications, Materials)
- [x] 01-03-PLAN.md — Interface layer migration (Controllers, Shortcodes, Views, Admin, CLI)

### Phase 2: Database Migration
**Goal**: PostgreSQL triggers and functions operate correctly with wecoza-core
**Depends on**: Phase 1
**Requirements**: INFRA-05, INFRA-06
**Success Criteria** (what must be TRUE):
  1. All PostgreSQL triggers from events plugin exist in wecoza-core schema
  2. class_change_logs table captures INSERT/UPDATE events on public.classes
  3. All SQL queries execute without errors (no `delivery_date` column references)
  4. Trigger functions execute and populate logging tables correctly
**Plans**: TBD

Plans:
- [ ] 02-01: TBD
- [ ] 02-02: TBD

### Phase 3: Bootstrap Integration
**Goal**: Events module initializes correctly within wecoza-core lifecycle
**Depends on**: Phase 2
**Requirements**: INFRA-07, INFRA-08
**Success Criteria** (what must be TRUE):
  1. Events module initializes when wecoza-core activates (no separate plugin activation needed)
  2. Events shortcodes are registered and callable after wecoza-core loads
  3. Events AJAX handlers respond to requests
  4. Deactivating wecoza-core properly cleans up events resources
**Plans**: TBD

Plans:
- [ ] 03-01: TBD
- [ ] 03-02: TBD

### Phase 4: Task Management
**Goal**: Users can view and manage tasks generated from class changes
**Depends on**: Phase 3
**Requirements**: TASK-01, TASK-02, TASK-03, TASK-04, TASK-05
**Success Criteria** (what must be TRUE):
  1. Creating or updating a class automatically generates a task record
  2. User can view task dashboard via `[wecoza_event_tasks]` shortcode
  3. User can mark tasks complete and reopen them via AJAX
  4. User can filter tasks by status, date range, and class
  5. Task list updates in real-time after status changes
**Plans**: TBD

Plans:
- [ ] 04-01: TBD
- [ ] 04-02: TBD
- [ ] 04-03: TBD

### Phase 5: Material Tracking
**Goal**: Users can track material delivery status with automated alerts
**Depends on**: Phase 3
**Requirements**: MATL-01, MATL-02, MATL-03, MATL-04, MATL-05, MATL-06
**Success Criteria** (what must be TRUE):
  1. User can view material tracking dashboard via `[wecoza_material_tracking]` shortcode
  2. User can mark materials as delivered via AJAX handler
  3. System generates 7-day pre-start alerts for classes needing materials
  4. System generates 5-day pre-start alerts for classes needing materials
  5. Only users with `view_material_tracking` capability can view dashboard; only `manage_material_tracking` can mark delivered
**Plans**: TBD

Plans:
- [ ] 05-01: TBD
- [ ] 05-02: TBD
- [ ] 05-03: TBD

### Phase 6: AI Summarization
**Goal**: Users can view AI-generated summaries of class changes
**Depends on**: Phase 4 (requires class change events)
**Requirements**: AI-01, AI-02, AI-03, AI-04
**Success Criteria** (what must be TRUE):
  1. Admin can configure OpenAI API key via WordPress options
  2. Class change events trigger AI summary generation
  3. User can view AI summaries via `[wecoza_ai_summary]` shortcode
  4. Summary generation handles API errors gracefully (no crashes)
**Plans**: TBD

Plans:
- [ ] 06-01: TBD
- [ ] 06-02: TBD

### Phase 7: Email Notifications
**Goal**: Users receive automated email notifications on class changes
**Depends on**: Phase 4 (requires class change events)
**Requirements**: EMAIL-01, EMAIL-02, EMAIL-03, EMAIL-04
**Success Criteria** (what must be TRUE):
  1. Creating a new class triggers email notification to configured recipients
  2. Updating a class triggers email notification to configured recipients
  3. Admin can configure notification recipients via WordPress options
  4. Email sending is handled via WordPress cron (not blocking request)
**Plans**: TBD

Plans:
- [ ] 07-01: TBD
- [ ] 07-02: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Code Foundation | 3/3 | ✓ Complete | 2026-02-02 |
| 2. Database Migration | 0/2 | Not started | - |
| 3. Bootstrap Integration | 0/2 | Not started | - |
| 4. Task Management | 0/3 | Not started | - |
| 5. Material Tracking | 0/3 | Not started | - |
| 6. AI Summarization | 0/2 | Not started | - |
| 7. Email Notifications | 0/2 | Not started | - |

---
*Roadmap created: 2026-02-02*
*Last updated: 2026-02-02*
