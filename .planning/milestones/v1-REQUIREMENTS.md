# Requirements Archive: v1 Events Integration

**Archived:** 2026-02-02
**Status:** ✅ SHIPPED

This is the archived requirements specification for v1.
For current requirements, see `.planning/REQUIREMENTS.md` (created for next milestone).

---

# Requirements: WeCoza Core - Events Integration

**Defined:** 2026-02-02
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin architecture

## v1 Requirements

Requirements for events plugin migration into wecoza-core.

### Infrastructure

- [x] **INFRA-01**: Namespace conversion from `WeCozaEvents\*` to `WeCoza\Events\*`
- [x] **INFRA-02**: File structure reorganization from `includes/` to `src/Events/`
- [x] **INFRA-03**: Replace events plugin database connection with wecoza-core's `PostgresConnection`
- [x] **INFRA-04**: Add PSR-4 autoloading for `WeCoza\Events\*` namespace
- [x] **INFRA-05**: Migrate PostgreSQL schema (triggers, functions) to wecoza-core
- [x] **INFRA-06**: Fix `delivery_date` column references (removed column)
- [x] **INFRA-07**: Integrate events module initialization into wecoza-core bootstrap
- [x] **INFRA-08**: Migrate events plugin activation/deactivation hooks

### Task Management

- [x] **TASK-01**: Class change monitoring via PostgreSQL triggers on `public.classes`
- [x] **TASK-02**: Task generation from class INSERT/UPDATE events
- [x] **TASK-03**: Task completion/reopening via AJAX handler
- [x] **TASK-04**: Task list shortcode `[wecoza_event_tasks]` renders task dashboard
- [x] **TASK-05**: Task filtering by status, date, class

### Material Tracking

- [x] **MATL-01**: Material delivery status tracking per class
- [x] **MATL-02**: 7-day pre-start alert notifications for material delivery
- [x] **MATL-03**: 5-day pre-start alert notifications for material delivery
- [x] **MATL-04**: Material tracking shortcode `[wecoza_material_tracking]` renders dashboard
- [x] **MATL-05**: Mark materials delivered via AJAX handler
- [x] **MATL-06**: Material tracking capability checks (`view_material_tracking`, `manage_material_tracking`)

### AI Summarization

- [x] **AI-01**: OpenAI GPT integration for class change summarization
- [x] **AI-02**: AI summary generation on class change events
- [x] **AI-03**: AI summary shortcode `[wecoza_insert_update_ai_summary]` displays summaries
- [x] **AI-04**: API key configuration via WordPress options

### Email Notifications

- [x] **EMAIL-01**: Automated email notifications on class INSERT events
- [x] **EMAIL-02**: Automated email notifications on class UPDATE events
- [x] **EMAIL-03**: WordPress cron integration for scheduled notifications
- [x] **EMAIL-04**: Configurable notification recipients

## v2 Requirements

Deferred to future release.

### Reporting

- **REPORT-01**: Progression reports for regulatory compliance (Umalusi, DHET)
- **REPORT-02**: Monthly progression report (who progressed)
- **REPORT-03**: Project-wide learner progression overview

### Packages

- **PKG-01**: Package support (learners on different subjects in same class)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Packages feature | Deferred per WEC-168 discussion with client |
| New reporting features | Separate milestone |
| Mobile app | Not planned |
| OAuth/social login | Not required |
| Real-time notifications | WebSocket complexity, defer to future |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| INFRA-01 | Phase 1: Code Foundation | Complete |
| INFRA-02 | Phase 1: Code Foundation | Complete |
| INFRA-03 | Phase 1: Code Foundation | Complete |
| INFRA-04 | Phase 1: Code Foundation | Complete |
| INFRA-05 | Phase 2: Database Migration | Complete |
| INFRA-06 | Phase 2: Database Migration | Complete |
| INFRA-07 | Phase 3: Bootstrap Integration | Complete |
| INFRA-08 | Phase 3: Bootstrap Integration | Complete |
| TASK-01 | Phase 4: Task Management | Complete |
| TASK-02 | Phase 4: Task Management | Complete |
| TASK-03 | Phase 4: Task Management | Complete |
| TASK-04 | Phase 4: Task Management | Complete |
| TASK-05 | Phase 4: Task Management | Complete |
| MATL-01 | Phase 5: Material Tracking | Complete |
| MATL-02 | Phase 5: Material Tracking | Complete |
| MATL-03 | Phase 5: Material Tracking | Complete |
| MATL-04 | Phase 5: Material Tracking | Complete |
| MATL-05 | Phase 5: Material Tracking | Complete |
| MATL-06 | Phase 5: Material Tracking | Complete |
| AI-01 | Phase 6: AI Summarization | Complete |
| AI-02 | Phase 6: AI Summarization | Complete |
| AI-03 | Phase 6: AI Summarization | Complete |
| AI-04 | Phase 6: AI Summarization | Complete |
| EMAIL-01 | Phase 7: Email Notifications | Complete |
| EMAIL-02 | Phase 7: Email Notifications | Complete |
| EMAIL-03 | Phase 7: Email Notifications | Complete |
| EMAIL-04 | Phase 7: Email Notifications | Complete |

**Coverage:**
- v1 requirements: 24 total
- Shipped: 24 (100%)
- Dropped: 0

---

## Milestone Summary

**Shipped:** 24 of 24 v1 requirements (100%)
**Adjusted:** None — all requirements implemented as specified
**Dropped:** None

---
*Archived: 2026-02-02 as part of v1 milestone completion*
