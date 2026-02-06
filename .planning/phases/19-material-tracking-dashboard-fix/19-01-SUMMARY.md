---
phase: 19-material-tracking-dashboard-fix
plan: 01
subsystem: database
tags: [postgresql, jsonb, material-tracking, event-dates, repository]

# Dependency graph
requires:
  - phase: 18-notification-system
    provides: event_dates JSONB structure in classes table
provides:
  - Material Tracking Repository querying event_dates JSONB for Deliveries events
  - Dashboard data sourced from user-entered events instead of cron-only records
  - Search capability for class code/subject/client name
affects: [material-tracking-dashboard, cron-notifications]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "JSONB query pattern with CROSS JOIN LATERAL for array elements"
    - "Event status filtering using LOWER() for case-insensitive comparison"
    - "NULL-safe JSONB queries with COALESCE(c.event_dates, '[]'::jsonb)"

key-files:
  created: []
  modified:
    - src/Events/Repositories/MaterialTrackingRepository.php
    - src/Events/Services/MaterialTrackingDashboardService.php

key-decisions:
  - "Query event_dates JSONB as primary data source, LEFT JOIN class_material_tracking for supplementary cron info"
  - "Remove days_range filter (events exist permanently, not time-windowed)"
  - "Status filter uses event-based values ('pending', 'completed') instead of cron values ('notified', 'delivered')"
  - "Map 'delivered' to 'completed' in service layer for backward compatibility"

patterns-established:
  - "JSONB array query pattern: CROSS JOIN LATERAL jsonb_array_elements(COALESCE(c.event_dates, '[]'::jsonb)) WITH ORDINALITY"
  - "Event type filtering: elem->>'type' = 'Deliveries' (case-sensitive, capital D)"
  - "Event status filtering: LOWER(elem->>'status') for case-insensitive comparison"
  - "Event index calculation: (elem_index - 1) to convert PostgreSQL 1-indexed to PHP 0-indexed"

# Metrics
duration: 2min
completed: 2026-02-06
---

# Phase 19 Plan 01: Repository and Service Rewrite Summary

**Material Tracking Repository refactored to query event_dates JSONB for Deliveries events, replacing cron-only data source**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-06T09:30:50Z
- **Completed:** 2026-02-06T09:32:16Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Repository queries classes.event_dates JSONB for Deliveries events as primary data source
- Statistics count delivery events from JSONB (total, pending, completed)
- Dashboard supports text search for class code/subject/client name
- Service layer validates event-based status filters ('pending', 'completed')
- All 5 existing cron methods preserved unchanged

## Task Commits

Each task was committed atomically:

1. **Task 1: Rewrite MaterialTrackingRepository JSONB queries** - `937f5c7` (refactor)
2. **Task 2: Update MaterialTrackingDashboardService filter logic** - `3f31128` (refactor)

## Files Created/Modified
- `src/Events/Repositories/MaterialTrackingRepository.php` - Rewrote getTrackingDashboardData and getTrackingStatistics to query event_dates JSONB with LEFT JOIN to class_material_tracking for supplementary cron data
- `src/Events/Services/MaterialTrackingDashboardService.php` - Updated filter validation for event-based status values, added search support, removed notification_type and days_range filters

## Decisions Made

1. **Event_dates as primary data source:** Material Tracking dashboard now queries user-entered Deliveries events from event_dates JSONB instead of only showing cron-generated records from class_material_tracking. This fixes the "0 records" issue where dashboard was empty until cron created tracking records.

2. **Cron data as supplementary:** LEFT JOIN class_material_tracking to preserve supplementary notification info (notification_type, notification_sent_at) without requiring cron records to exist. All 5 cron methods (markNotificationSent, wasNotificationSent, getDeliveryStatus, getTrackingRecords, markDelivered) remain unchanged.

3. **Event-based status filtering:** Status filter now uses event statuses ('pending', 'completed') instead of cron delivery statuses ('notified', 'delivered'). Service layer maps 'delivered' to 'completed' for backward compatibility.

4. **Remove time window:** Removed days_range parameter - events exist permanently in JSONB, not time-windowed like cron records.

5. **Search capability:** Added search filter for class_code, class_subject, and client_name using ILIKE for case-insensitive matching.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Ready for Phase 19-02 (Controller and API rewrite) which will update AJAX handlers and dashboard controller to use the new service signature.

**Blockers/Concerns:**
- Controllers and AJAX handlers still call old service signature with notification_type and days_range parameters. This will be addressed in 19-02.

## Self-Check: PASSED

All files and commits verified to exist.

---
*Phase: 19-material-tracking-dashboard-fix*
*Completed: 2026-02-06*
