---
phase: 18-notification-system
plan: 06
subsystem: notifications
tags:
  - wordpress
  - hooks
  - ajax
  - cron
  - action-scheduler
  - multi-recipient

dependency_graph:
  requires:
    - 18-03  # NotificationProcessor update
    - 18-04  # NotificationDashboardService
    - 18-05  # Controller event integration
  provides:
    - notification-hooks-enabled
    - multi-recipient-support
    - dashboard-ajax-handlers
    - cron-scheduling
  affects:
    - future email delivery plans
    - notification dashboard UI

tech_stack:
  added: []
  patterns:
    - multi-recipient-notification
    - wordpress-ajax-handler
    - wordpress-cron-scheduling
    - action-scheduler-integration

key_files:
  created: []
  modified:
    - wecoza-core.php
    - src/Events/Services/NotificationSettings.php
    - src/Events/Services/NotificationProcessor.php
    - src/Events/Services/NotificationEnricher.php

decisions:
  - id: multi-recipient-option
    description: "Use wecoza_notification_recipients option for array storage"
    rationale: "Supports multiple recipients per event type with legacy fallback"
  - id: process-event-action
    description: "Use wecoza_process_event for AI enrichment, wecoza_send_notification_email for delivery"
    rationale: "Separates enrichment from email sending for better error recovery"
  - id: ajax-nonce
    description: "Use wecoza_notifications_nonce for dashboard AJAX"
    rationale: "Separate nonce from learners to avoid conflicts"
  - id: hourly-cron
    description: "Process notifications hourly via wecoza_process_notifications"
    rationale: "Balance between timeliness and server load"

metrics:
  duration: 227s
  completed: "2026-02-05"
---

# Phase 18 Plan 06: Enable Notification Hooks Summary

Enabled notification system hooks and added multi-recipient support with dashboard AJAX handlers.

## What Was Built

### 1. NotificationSettings Multi-Recipient Support
- `getRecipientsByEventType(EventType)` - Get array of recipients for event type
- `getRecipientsForEventType(string)` - Get array supporting both EventType values and legacy operations
- `getRecipientForOperation(string)` - Backward compatible single-recipient lookup (deprecated)
- `setRecipientsForEventType(EventType, array)` - Configure recipients per event type
- `getAllRecipientSettings()` - Get all recipient configurations
- `mapOperationToEventType(string)` - Map legacy INSERT/UPDATE/DELETE to EventType
- `validateEmail(string)` - Email validation helper
- Option key: `wecoza_notification_recipients` with structure `['CLASS_INSERT' => ['email1', 'email2'], ...]`
- Fallback to legacy single-email options for backward compatibility

### 2. Notification Hooks in wecoza-core.php
- `wecoza_process_notifications` - Cron handler for batch processing
- `wecoza_process_event` - Action Scheduler job for AI enrichment
- `wecoza_send_notification_email` - Action Scheduler job for email delivery
- Multi-recipient support via foreach loop in both processor and enricher
- Re-enabled AISummaryShortcode registration
- Re-enabled AISummaryStatusCommand CLI
- Removed all DEPRECATED comments for notification system

### 3. Cron Scheduling
- Activation: Schedules `wecoza_process_notifications` hourly
- Activation: Unschedules legacy `wecoza_email_notifications_process`
- Deactivation: Unschedules both `wecoza_process_notifications` and legacy cron

### 4. Dashboard AJAX Handlers
- `wp_ajax_wecoza_mark_notification_viewed` - Mark notification as viewed
- `wp_ajax_wecoza_mark_notification_acknowledged` - Mark notification as acknowledged
- Both handlers use NotificationDashboardService
- Both handlers require `read` capability
- Both handlers validate nonce via `wecoza_notifications_nonce`
- Added WeCozaNotifications localized script data with nonce

### 5. Service Updates
- NotificationProcessor: Uses `getRecipientsForEventType()`, schedules emails per recipient
- NotificationEnricher: Returns `recipients` array instead of single `recipient`

## File Changes

| File | Change |
|------|--------|
| `src/Events/Services/NotificationSettings.php` | Added multi-recipient methods |
| `src/Events/Services/NotificationProcessor.php` | Updated for multi-recipient |
| `src/Events/Services/NotificationEnricher.php` | Updated to return recipients array |
| `wecoza-core.php` | Enabled hooks, added AJAX handlers |

## Commits

| Hash | Description |
|------|-------------|
| 22fee1f | feat(18-06): add multi-recipient support to NotificationSettings |
| 6a7fd9d | feat(18-06): enable notification hooks in wecoza-core.php |
| 7c7c853 | feat(18-06): add AJAX handlers for dashboard interactions |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Updated NotificationEnricher for multi-recipient**
- **Found during:** Task 2
- **Issue:** NotificationEnricher returned single `recipient` but hooks expect `recipients` array
- **Fix:** Updated enrich() to call getRecipientsForEventType() and return recipients array
- **Files modified:** src/Events/Services/NotificationEnricher.php
- **Commit:** 6a7fd9d

**2. [Rule 2 - Missing Critical] Updated NotificationProcessor for multi-recipient**
- **Found during:** Task 2
- **Issue:** NotificationProcessor used single recipient from getRecipientForOperation()
- **Fix:** Updated to use getRecipientsForEventType() and loop for email scheduling
- **Files modified:** src/Events/Services/NotificationProcessor.php
- **Commit:** 6a7fd9d

## Verification Results

| Check | Status |
|-------|--------|
| wecoza-core.php passes PHP lint | PASS |
| NotificationSettings supports array of recipients | PASS |
| wecoza_process_event action registered | PASS (line 278) |
| wecoza_send_notification_email action registered | PASS (line 303) |
| Cron job scheduled for wecoza_process_notifications | PASS (line 481) |
| AISummaryShortcode re-enabled | PASS (line 219) |
| Dashboard AJAX handlers registered | PASS (lines 323, 350) |

## Next Phase Readiness

Phase 18 core notification system is now fully enabled:
- Events captured from ClassAjaxController (18-05)
- Events stored in class_events table (18-01)
- Processor schedules AI enrichment and email jobs (18-06)
- Enricher generates AI summaries and returns recipients (18-03, 18-06)
- Emailer sends to each recipient (18-03)
- Dashboard can mark viewed/acknowledged (18-04, 18-06)

Remaining for Phase 18:
- Plan 18-07: Notification dashboard view/UI
- Plan 18-08: Testing and documentation
