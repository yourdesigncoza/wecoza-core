# Roadmap: WeCoza Core v1.3

**Milestone:** v1.3 Fix Material Tracking Dashboard
**Start phase:** 19 (continues from v1.2 which ended at phase 18)
**Requirements:** 9 (DASH-01..04, FILT-01..03, CRON-01..02)

## Phase 19: Material Tracking Dashboard Data Source Fix

**Goal:** Rewire the Material Tracking Dashboard to show classes with "Deliveries" events from `classes.event_dates` JSONB instead of only showing cron-created notification records.

**Requirements:** DASH-01, DASH-02, DASH-03, DASH-04, FILT-01, FILT-02, FILT-03, CRON-01, CRON-02

**Context:** The dashboard currently reads from `class_material_tracking` table (populated only by daily cron when classes are exactly 7/5 days from start). The Event Tasks system stores "Deliveries: Material Tracking" events in `classes.event_dates` JSONB. These two systems are disconnected — the dashboard shows 0 records even when delivery events exist.

**Approach:** Rewrite `MaterialTrackingDashboardService` and `MaterialTrackingRepository` to query `classes.event_dates` JSONB for Deliveries-type events, joining with class/client/site data. Preserve cron notification data as supplementary info. Update presenter and views to reflect new data shape.

**Success Criteria:**
1. Dashboard shows all classes that have at least one "Deliveries" event in `event_dates` JSONB
2. Statistics bar shows correct counts of total/pending/completed deliveries
3. Each row shows class code, subject, client/site, start date, delivery event date, and status
4. Cron notification status (orange/red, sent date) shown where records exist in `class_material_tracking`
5. Search and filter work on the new data source
6. Existing cron notification system continues to function independently

**Files likely modified:**
- `src/Events/Services/MaterialTrackingDashboardService.php` — new query logic
- `src/Events/Repositories/MaterialTrackingRepository.php` — new JSONB queries
- `src/Events/Views/Presenters/MaterialTrackingPresenter.php` — new data shape
- `views/events/material-tracking/dashboard.php` — updated columns/layout
- `views/events/material-tracking/list-item.php` — updated row rendering
- `views/events/material-tracking/statistics.php` — updated stat display

---
*Roadmap created: 2026-02-06*
