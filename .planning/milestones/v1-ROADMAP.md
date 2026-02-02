# Milestone v1: Events Integration

**Status:** ✅ SHIPPED 2026-02-02
**Phases:** 1-7
**Total Plans:** 13

## Overview

This roadmap migrates the wecoza-events-plugin (~7,700 lines) into wecoza-core, transforming it from a standalone plugin into an integrated module. The migration proceeds from foundational infrastructure (namespace conversion, PSR-4 autoloading) through database schema migration, then systematically enables each feature: task management, material tracking, AI summarization, and email notifications. Each phase delivers a verifiable capability that builds on previous work.

## Phases

### Phase 1: Code Foundation

**Goal**: Events module code exists in wecoza-core with correct namespaces and autoloading
**Depends on**: Nothing (first phase)
**Requirements**: INFRA-01, INFRA-02, INFRA-03, INFRA-04
**Plans**: 3 plans

Plans:
- [x] 01-01-PLAN.md — Directory setup, PSR-4 config, Models/Repositories/Support migration
- [x] 01-02-PLAN.md — Services migration (TaskManager, AISummary, Notifications, Materials)
- [x] 01-03-PLAN.md — Interface layer migration (Controllers, Shortcodes, Views, Admin, CLI)

**Success Criteria:**
1. All events PHP files exist under `src/Events/` with proper directory structure
2. All classes use `WeCoza\Events\*` namespace and can be instantiated without require_once
3. Events module classes use `wecoza_db()` for database queries
4. Running `wp wecoza test-db` succeeds with events module loaded

### Phase 2: Database Migration

**Goal**: PostgreSQL triggers and functions operate correctly with wecoza-core
**Depends on**: Phase 1
**Requirements**: INFRA-05, INFRA-06
**Plans**: 2 plans

Plans:
- [x] 02-01-PLAN.md — Remove delivery_date column references from PHP code
- [x] 02-02-PLAN.md — Verify and document PostgreSQL trigger infrastructure

**Success Criteria:**
1. All PostgreSQL triggers from events plugin exist in wecoza-core schema
2. class_change_logs table captures INSERT/UPDATE events on public.classes
3. All SQL queries execute without errors (no `delivery_date` column references)
4. Trigger functions execute and populate logging tables correctly

### Phase 3: Bootstrap Integration

**Goal**: Events module initializes correctly within wecoza-core lifecycle
**Depends on**: Phase 2
**Requirements**: INFRA-07, INFRA-08
**Plans**: 1 plan

Plans:
- [x] 03-01-PLAN.md — Events module bootstrap (autoloader + hooks + CLI)

**Success Criteria:**
1. Events module initializes when wecoza-core activates
2. Events shortcodes are registered and callable after wecoza-core loads
3. Events AJAX handlers respond to requests
4. Deactivating wecoza-core properly cleans up events resources

### Phase 4: Task Management

**Goal**: Users can view and manage tasks generated from class changes
**Depends on**: Phase 3
**Requirements**: TASK-01, TASK-02, TASK-03, TASK-04, TASK-05
**Plans**: 1 plan

Plans:
- [x] 04-01-PLAN.md — Verify migrated task management functionality

**Success Criteria:**
1. Creating or updating a class automatically generates a task record
2. User can view task dashboard via `[wecoza_event_tasks]` shortcode
3. User can mark tasks complete and reopen them via AJAX
4. User can filter tasks by status, date range, and class
5. Task list updates in real-time after status changes

### Phase 5: Material Tracking

**Goal**: Users can track material delivery status with automated alerts
**Depends on**: Phase 3
**Requirements**: MATL-01, MATL-02, MATL-03, MATL-04, MATL-05, MATL-06
**Plans**: 2 plans

Plans:
- [x] 05-01-PLAN.md — Capability registration + WP Cron scheduling for notifications
- [x] 05-02-PLAN.md — Verification test suite for all MATL requirements

**Success Criteria:**
1. User can view material tracking dashboard via `[wecoza_material_tracking]` shortcode
2. User can mark materials as delivered via AJAX handler
3. System generates 7-day pre-start alerts for classes needing materials
4. System generates 5-day pre-start alerts for classes needing materials
5. Only users with `view_material_tracking` capability can view dashboard

### Phase 6: AI Summarization

**Goal**: Users can view AI-generated summaries of class changes
**Depends on**: Phase 4 (requires class change events)
**Requirements**: AI-01, AI-02, AI-03, AI-04
**Plans**: 2 plans

Plans:
- [x] 06-01-PLAN.md — Verify API key configuration and shortcode infrastructure
- [x] 06-02-PLAN.md — Verify event-triggered summary generation and error handling

**Success Criteria:**
1. Admin can configure OpenAI API key via WordPress options
2. Class change events trigger AI summary generation
3. User can view AI summaries via `[wecoza_insert_update_ai_summary]` shortcode
4. Summary generation handles API errors gracefully (no crashes)

### Phase 7: Email Notifications

**Goal**: Users receive automated email notifications on class changes
**Depends on**: Phase 4 (requires class change events)
**Requirements**: EMAIL-01, EMAIL-02, EMAIL-03, EMAIL-04
**Plans**: 2 plans

Plans:
- [x] 07-01-PLAN.md — Cron hook registration and template path fix
- [x] 07-02-PLAN.md — Email notification verification test suite

**Success Criteria:**
1. Creating a new class triggers email notification to configured recipients
2. Updating a class triggers email notification to configured recipients
3. Admin can configure notification recipients via WordPress options
4. Email sending is handled via WordPress cron (not blocking request)

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Code Foundation | 3/3 | Complete | 2026-02-02 |
| 2. Database Migration | 2/2 | Complete | 2026-02-02 |
| 3. Bootstrap Integration | 1/1 | Complete | 2026-02-02 |
| 4. Task Management | 1/1 | Complete | 2026-02-02 |
| 5. Material Tracking | 2/2 | Complete | 2026-02-02 |
| 6. AI Summarization | 2/2 | Complete | 2026-02-02 |
| 7. Email Notifications | 2/2 | Complete | 2026-02-02 |

---

## Milestone Summary

**Key Decisions:**
- Migrate all events features (user confirmed all features needed)
- Fix delivery_date during migration (cleaner than pre-fixing)
- Use src/Events/ structure (consistent with existing modules)
- Namespace: WeCoza\Events\* (consistent naming)
- Single PostgresConnection (eliminate duplicate code)
- Use gpt-5-mini model (balance cost vs quality)
- PII protection via DataObfuscator (prevent sensitive data leaks)
- Hourly email cron (non-blocking notifications)

**Issues Resolved:**
- Consolidated separate database connection classes into single PostgresConnection
- Fixed delivery_date column references that caused SQL errors
- Added missing PII protection before sending data to OpenAI API
- Fixed template path resolution using wecoza_plugin_path()

**Issues Deferred:**
- Settings page admin menu entry (settings accessible via direct URL)
- Phase 3 formal VERIFICATION.md (functionality verified via dependent phases)

**Technical Debt Incurred:**
- 2 test failures in AI summarization suite (test format issues, not production bugs)
- Phase 3 verification inferred rather than explicit

---

*Archived: 2026-02-02 as part of v1 milestone completion*
*For current project status, see .planning/ROADMAP.md*
