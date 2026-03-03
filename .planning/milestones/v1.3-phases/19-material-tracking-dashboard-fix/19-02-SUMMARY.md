---
phase: 19
plan: 02
subsystem: material-tracking-presentation
tags: [material-tracking, presenter, views, shortcode, ui, event-dates]
requires: [19-01]
provides:
  - Event-based delivery tracking UI
  - Delivery date column display
  - Event status badges (Pending/Completed)
  - Supplementary cron notification badges
  - Simplified filters (status + search only)
affects: []
tech-stack:
  added: []
  patterns:
    - Presenter maps event_dates JSONB to display format
    - Views display delivery events with supplementary cron info
    - Checkbox actions pass event_index for per-event tracking
key-files:
  created: []
  modified:
    - src/Events/Views/Presenters/MaterialTrackingPresenter.php
    - src/Events/Shortcodes/MaterialTrackingShortcode.php
    - views/events/material-tracking/dashboard.php
    - views/events/material-tracking/list-item.php
    - views/events/material-tracking/statistics.php
decisions:
  - title: Event-based status badges as primary, cron notification badges as supplementary
    rationale: Events represent actual delivery schedule, cron notifications are just automated reminders
    alternatives: ["Could have kept cron-based filtering", "Could have hidden cron info entirely"]
    chosen: Display both - event status prominent, cron badges secondary
  - title: Remove notification type filter
    rationale: Cron notifications are supplementary info, not a primary dimension for filtering
    alternatives: ["Keep filter but mark as 'advanced'", "Move to expandable filter section"]
    chosen: Remove entirely - users filter by event status (pending/completed) and search
  - title: Pass event_index in checkbox actions
    rationale: Enables per-event tracking when multiple delivery events exist for same class
    alternatives: ["Use event_date as identifier", "Use composite key in backend"]
    chosen: event_index (from event_dates array position) is stable and unique per class
duration: 3 minutes
completed: 2026-02-06
---

# Phase 19 Plan 02: Presenter/Views/Shortcode Update Summary

**One-liner:** Updated presentation layer to display event_dates-sourced delivery records with delivery date column, event-based status badges, and supplementary cron notification badges

## Overview

Plan 19-01 rewrote the repository/service to query event_dates JSONB as the primary data source. Plan 19-02 updates the presentation layer (presenter, views, shortcode) to display the new data shape correctly: added delivery date column, event-based status badges (Pending/Completed), supplementary cron notification badges (7d/5d), and simplified filters (status + search only).

**Purpose:** Display delivery events from event_dates with proper UI representation

**Context:** Material Tracking Dashboard was showing 0 records because it relied on cron-created tracking records. After Plan 19-01 switched to event_dates as primary source, the UI needed updates to reflect the new data model.

## What Was Built

### Task 1: MaterialTrackingPresenter Rewrite (Commit: 55589b8)

**Updated `presentRecords()` method:**
- Maps event_dates fields: `event_date`, `event_description`, `event_index`, `event_status`
- Removed old fields: `id`, `materials_delivered_at`, `action_button_html`
- Keeps class and client info, original_start_date
- Adds `notification_type`, `notification_sent_at` from LEFT JOIN cron records

**New methods added:**
- `mapEventStatus(string $eventStatus): string` - Converts event status to delivery status (completed → delivered, pending → pending)
- `getEventStatusBadge(string $status): string` - Event-based badges (Pending/Completed) using Phoenix classes

**Updated methods:**
- `getNotificationBadge(?string $type): string` - Now accepts nullable, returns blank for null (no emojis, just "7d"/"5d" text)
- `presentStatistics(array $stats): array` - Changed from total/pending/notified/delivered to total/pending/completed

**Removed methods:**
- `getStatusBadge()` - Replaced by `getEventStatusBadge()`
- `getActionButton()` - Actions now handled via checkbox in view

### Task 2: Views and Shortcode Update (Commit: 4adc771)

**dashboard.php changes:**
- Status filter dropdown: changed from All/Pending/Notified/Delivered to All/Pending/Completed
- Removed notification type filter dropdown entirely
- Added "Delivery Date" column header (7 columns total now)
- Updated empty state colspan from 6 to 7
- Updated JavaScript `sortTable()` to handle `event_date` sort key
- Updated `filterRecords()` to remove `typeFilter` logic
- Updated mark-delivered handler to pass `event_index` alongside `class_id`
- Updated stat counters from `stat-notified`/`stat-delivered` to `stat-pending`/`stat-completed`

**list-item.php changes:**
- Added `data-event-date` attribute to `<tr>`
- Removed `data-notification-type` attribute (not a filter dimension)
- Added new "Delivery Date" column cell with `event_date` + optional `event_description`
- Added `data-event-index` attribute to checkbox for per-event tracking
- Checkbox checked/disabled based on `delivery_status` (delivered = checked+disabled)

**statistics.php changes:**
- Changed `$statKeys` from `['total', 'pending', 'notified', 'delivered']` to `['total', 'pending', 'completed']`

**MaterialTrackingShortcode.php changes:**
- Removed `DEFAULT_DAYS_RANGE` constant
- Updated `shortcode_atts` to only accept `limit` and `status` (removed `notification_type` and `days_range`)
- Updated `parseAttributes()` to return only `limit` and `status`
- Updated `getStatistics()` call to pass no parameters (was passing `days_range`)

## Decisions Made

**Event-based status as primary UI element:**
- Event status (pending/completed) drives the main status badge and filter
- Cron notification badges (orange 7d / red 5d) shown as supplementary info
- Users filter by event status, not by notification type

**Delivery Date as separate column:**
- Shows the actual delivery event date from event_dates JSONB
- Includes event description as subtitle if present
- Sortable like Class Start Date

**Event index for per-event tracking:**
- Checkbox actions pass `event_index` to identify which delivery event in the event_dates array
- Enables future support for multiple delivery events per class
- More stable than using date as identifier

**Simplified filters:**
- Kept: Search (class code/subject/client) + Status (All/Pending/Completed)
- Removed: Notification type filter (supplementary dimension)
- Removed: Days range filter (events exist permanently in event_dates)

## Technical Details

**Data Flow:**
1. Repository queries event_dates JSONB + LEFT JOIN class_material_tracking
2. Service passes through repository data
3. Presenter maps to display format: event_date, event_status, notification_type
4. Views render with Phoenix badge classes
5. JavaScript handles sort/filter/checkbox actions

**Key Presenter Mappings:**
```php
'event_date' => formatDate($record['event_date'])
'event_status' => strtolower($record['event_status'] ?? 'pending')
'delivery_status' => mapEventStatus($eventStatus) // completed → delivered
'status_badge_html' => getEventStatusBadge($eventStatus) // Pending/Completed
'notification_badge_html' => getNotificationBadge($type) // 7d/5d or blank
```

**JavaScript Sort Enhancement:**
- Added `event_date` sort key handling in date sorting section
- Reads from `$(row).data('event-date')` attribute

**Statistics Update:**
- Dashboard now shows: Total Deliveries / Pending / Completed
- No "Notified" stat (cron dimension removed from primary UI)

## Files Modified

**Core Presenter:**
- `src/Events/Views/Presenters/MaterialTrackingPresenter.php` - Maps event_dates data to display format

**View Templates:**
- `views/events/material-tracking/dashboard.php` - Added Delivery Date column, updated filters/stats
- `views/events/material-tracking/list-item.php` - Added event_date cell, updated checkbox attributes
- `views/events/material-tracking/statistics.php` - Changed stat keys to total/pending/completed

**Shortcode:**
- `src/Events/Shortcodes/MaterialTrackingShortcode.php` - Removed days_range and notification_type attributes

## Deviations from Plan

None - plan executed exactly as written.

## Testing Performed

**Verification steps completed:**
1. PHP syntax check passed on all 5 modified files
2. Verified dashboard.php has 7 table columns (added Delivery Date)
3. Verified status filter has All/Pending/Completed options (no Notified)
4. Verified notification type filter removed from UI
5. Verified list-item.php has data-event-date and data-event-index attributes
6. Verified statistics.php uses total/pending/completed stat keys
7. Verified shortcode removed days_range and notification_type parsing
8. Verified JavaScript sort handles event_date sort key
9. Verified JavaScript mark-delivered passes event_index
10. Verified JavaScript stat counters update stat-pending/stat-completed

## Next Phase Readiness

**Blockers:** None

**Concerns:**
- AJAX handler (`wecoza_mark_material_delivered`) still expects old signature without `event_index` parameter
- Controllers still call service with old `notification_type` and `days_range` parameters
- Need to update AJAX handler to mark specific event as completed in event_dates JSONB

**Recommendations:**
- Phase 19 Plan 03 should update AJAX handlers and controllers
- Update mark-as-delivered AJAX to accept `event_index` and update event_dates JSONB
- Remove `days_range` and `notification_type` parameters from controller calls

## Task Commits

| Task | Name | Commit | Duration | Files |
|------|------|--------|----------|-------|
| 1 | Update MaterialTrackingPresenter for event_dates data shape | 55589b8 | 2 min | MaterialTrackingPresenter.php |
| 2 | Update views and shortcode for new data shape | 4adc771 | 1 min | dashboard.php, list-item.php, statistics.php, MaterialTrackingShortcode.php |

**Total Duration:** 3 minutes

## Knowledge for Future Sessions

**Event-based Material Tracking UI:**
- Primary dimension: Event status (pending/completed) from event_dates JSONB
- Secondary dimension: Cron notification type (orange 7d / red 5d) from class_material_tracking LEFT JOIN
- Filter: Status (All/Pending/Completed) + Search (class code/subject/client)
- Sort: Class Code, Client Name, Class Start Date, Delivery Date (event_date), Notification Type, Status
- Action: Checkbox with data-class-id and data-event-index passes event_index to mark specific delivery event complete

**Presenter Pattern:**
- Maps repository data to display format with badge HTML, formatted dates, derived fields
- `getEventStatusBadge()` for event-based badges (Pending/Completed)
- `getNotificationBadge()` for supplementary cron badges (7d/5d or blank)
- `mapEventStatus()` converts event status to delivery status for backward compatibility

**View Architecture:**
- dashboard.php: Container, filters, table structure, JavaScript handlers
- list-item.php: Table row template with data attributes for sort/filter
- statistics.php: Stat strip with clickable stat items
- All use Phoenix badge classes, no custom CSS needed

**Shortcode Simplification:**
- Removed days_range (events exist permanently)
- Removed notification_type (supplementary info, not a filter)
- Kept limit and status (core filtering)

## Self-Check: PASSED

**Created files verified:** None (docs-only plan - skip check)

**Commits verified:**
- 55589b8 exists ✓
- 4adc771 exists ✓
