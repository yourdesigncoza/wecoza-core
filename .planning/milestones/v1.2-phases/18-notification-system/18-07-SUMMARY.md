---
phase: 18
plan: 07
subsystem: events/admin
tags: [admin, settings, notifications, email, ui]

# Dependency graph
requires:
  - "18-01: EventType enum for event type values and labels"
  - "18-06: NotificationSettings service with multi-recipient support"
provides:
  - "Admin UI for notification recipient configuration"
  - "Test notification AJAX handler"
  - "Settings validation with error feedback"
affects:
  - "18-08: Email delivery testing relies on this configuration"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "WordPress Settings API for configuration storage"
    - "AJAX handler with nonce verification for test notifications"

# File tracking
key-files:
  created:
    - views/events/admin/notification-settings.php
  modified:
    - src/Events/Admin/SettingsPage.php

# Decisions
decisions:
  - id: settings-api-integration
    choice: "Use WordPress Settings API inline rendering"
    rationale: "Standard WP pattern, automatic save handling"
  - id: multi-recipient-storage
    choice: "Save to both OPTION_RECIPIENTS and NotificationSettings service"
    rationale: "Maintains backward compatibility and ensures NotificationSettings is source of truth"

# Metrics
metrics:
  duration: "15 minutes"
  completed: "2026-02-05"
---

# Phase 18 Plan 07: Admin Settings UI for Notification Recipients

Multi-recipient notification configuration via WordPress admin settings page with per-event-type email fields and test notification functionality.

## Commit Log

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 9901714 | Add notification recipient configuration UI to SettingsPage |
| 2 | ac48e88 | Create notification-settings.php template |
| 3 | (included in 1) | AJAX handler for test notifications |

## Summary

### What Was Built

**SettingsPage Enhancements (src/Events/Admin/SettingsPage.php):**
- New "Event-Type Notification Recipients" settings section
- Per-event-type textarea fields for comma-separated email addresses:
  - Class Created
  - Class Updated
  - Learner Added
  - Learner Removed
  - Status Changed
- Input validation via `sanitizeRecipientSettings()`:
  - Parses comma-separated emails
  - Validates each with `filter_var(FILTER_VALIDATE_EMAIL)`
  - Shows admin error notice for invalid emails
  - Saves valid emails to NotificationSettings service
- "Send Test" button per event type with AJAX functionality
- `handleTestNotification()` AJAX handler:
  - Nonce verification for CSRF protection
  - Capability check (manage_options)
  - Uses NotificationEmailPresenter to format test email
  - Reports success/failure with recipient count

**Notification Settings Template (views/events/admin/notification-settings.php):**
- Standalone reusable template for rendering recipient configuration
- Can be used outside the Settings API context if needed
- Includes inline CSS for styling
- Includes inline JavaScript for test notification AJAX

### Key Implementation Details

**Event Types Configurable:**
```php
EventType::CLASS_INSERT   -> "Class Created"
EventType::CLASS_UPDATE   -> "Class Updated"
EventType::LEARNER_ADD    -> "Learner Added"
EventType::LEARNER_REMOVE -> "Learner Removed"
EventType::STATUS_CHANGE  -> "Status Changed"
```

**Settings Flow:**
1. Admin enters comma-separated emails in textarea
2. On save, `sanitizeRecipientSettings()` validates each email
3. Valid emails saved to `wecoza_notification_recipients` option
4. Also synced to NotificationSettings service via `setRecipientsForEventType()`
5. Invalid emails trigger admin error notice

**Test Notification Flow:**
1. Admin clicks "Send Test" button
2. AJAX POST to `wecoza_send_test_notification` action
3. Handler verifies nonce and capability
4. Retrieves recipients from NotificationSettings service
5. Uses NotificationEmailPresenter to format email
6. Sends test email with "[TEST]" prefix to all recipients
7. Returns success/failure message with count

### Files Changed

| File | Change |
|------|--------|
| src/Events/Admin/SettingsPage.php | +267 lines: multi-recipient section, validation, AJAX handler |
| views/events/admin/notification-settings.php | +113 lines: standalone template |

## Deviations from Plan

None - plan executed exactly as written.

## Testing Notes

**Manual verification required:**
1. Navigate to WP Admin > WeCoza > Event Notifications
2. Scroll to "Event-Type Notification Recipients" section
3. Enter test emails (comma-separated) for any event type
4. Click "Save Changes" - verify emails persist
5. Enter invalid email (e.g., "notanemail") - verify error notice appears
6. Click "Send Test" button - verify test email received

## Next Steps

Plan 18-08: Email delivery integration and full end-to-end notification testing.
