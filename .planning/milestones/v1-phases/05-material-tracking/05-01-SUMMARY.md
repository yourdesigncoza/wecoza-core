---
phase: 05-material-tracking
plan: 01
subsystem: events-infrastructure
tags: [capabilities, cron, wordpress, notifications, material-tracking]
requires:
  - 03-01 (Events module bootstrap integration)
  - 04-01 (Task management verification)
provides:
  - Material tracking capabilities (view_material_tracking, manage_material_tracking)
  - Daily WP Cron scheduling for material delivery notifications
  - Automated 7-day (orange) and 5-day (red) alert system
affects:
  - 05-02 (Material tracking UI and services)
  - Future material tracking features requiring capability checks
tech-stack:
  added: []
  patterns:
    - WordPress capability management
    - WP Cron scheduling pattern
    - Service-based notification architecture
key-files:
  created: []
  modified:
    - wecoza-core.php (82 lines added: capability registration, cron scheduling, handler)
decisions: []
metrics:
  duration: "2min"
  completed: "2026-02-02"
---

# Phase 5 Plan 01: Material Tracking Capabilities and Cron Summary

**One-liner:** WordPress capability registration and daily cron scheduling for automated material delivery notifications (7-day orange, 5-day red alerts)

## What Was Delivered

Integrated material tracking capabilities and automated notification scheduling into wecoza-core.php plugin initialization.

**Capabilities Added:**
- `view_material_tracking` - Grants access to material tracking dashboard
- `manage_material_tracking` - Allows marking materials as delivered

**Cron Scheduling:**
- Daily WP Cron event: `wecoza_material_notifications_check`
- Automated execution of MaterialNotificationService
- Separate processing for orange (7-day) and red (5-day) alerts

## Requirements Verified

All plan requirements confirmed:

| Requirement | Description | Status |
|-------------|-------------|--------|
| MATL-06 | Capability registration for material tracking | ✓ Implemented |
| MATL-02 | 7-day (orange status) notification alerts | ✓ Scheduled |
| MATL-03 | 5-day (red status) notification alerts | ✓ Scheduled |

## Technical Implementation

### Task 1: Capability Registration

**Activation Hook Changes:**
```php
$admin->add_cap('view_material_tracking');
$admin->add_cap('manage_material_tracking');
```

**Deactivation Hook Changes:**
```php
$admin->remove_cap('view_material_tracking');
$admin->remove_cap('manage_material_tracking');
```

**Pattern:** Mirrors existing `manage_learners` capability pattern for consistency.

### Task 2: WP Cron Scheduling

**Activation Hook - Schedule Event:**
```php
if (!wp_next_scheduled('wecoza_material_notifications_check')) {
    wp_schedule_event(time(), 'daily', 'wecoza_material_notifications_check');
}
```

**Deactivation Hook - Unschedule Event:**
```php
$timestamp = wp_next_scheduled('wecoza_material_notifications_check');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'wecoza_material_notifications_check');
}
```

**Cron Handler:**
```php
add_action('wecoza_material_notifications_check', function () {
    if (!class_exists(\WeCoza\Events\Services\MaterialNotificationService::class)) {
        return;
    }

    $service = new \WeCoza\Events\Services\MaterialNotificationService();

    // Orange (7-day) alerts
    $orangeClasses = $service->findOrangeStatusClasses();
    if (!empty($orangeClasses)) {
        $sentOrange = $service->sendMaterialNotifications($orangeClasses, 'orange');
        // Debug logging if WP_DEBUG enabled
    }

    // Red (5-day) alerts
    $redClasses = $service->findRedStatusClasses();
    if (!empty($redClasses)) {
        $sentRed = $service->sendMaterialNotifications($redClasses, 'red');
        // Debug logging if WP_DEBUG enabled
    }
});
```

**Handler Placement:** After Events Module initialization (line 210) to ensure MaterialNotificationService class is available.

**Safety Features:**
- Class existence check prevents errors if service doesn't exist
- Separate processing for orange and red alerts
- Debug logging only when WP_DEBUG enabled
- Empty result check prevents unnecessary processing

## Verification Results

**Capability Registration:**
```bash
$ grep -n "view_material_tracking\|manage_material_tracking" wecoza-core.php
324:        $admin->add_cap('view_material_tracking');
325:        $admin->add_cap('manage_material_tracking');
349:        $admin->remove_cap('view_material_tracking');
350:        $admin->remove_cap('manage_material_tracking');
```
✓ 4 lines (2 add_cap in activation, 2 remove_cap in deactivation)

**Cron Scheduling:**
```bash
$ grep -n "wecoza_material_notifications_check" wecoza-core.php
210:    add_action('wecoza_material_notifications_check', function () {
329:    if (!wp_next_scheduled('wecoza_material_notifications_check')) {
330:        wp_schedule_event(time(), 'daily', 'wecoza_material_notifications_check');
354:    $timestamp = wp_next_scheduled('wecoza_material_notifications_check');
356:        wp_unschedule_event($timestamp, 'wecoza_material_notifications_check');
```
✓ 5 lines (schedule, unschedule, action hook, 2 checks)

**Cron Handler Calls:**
```bash
$ grep -n "findOrangeStatusClasses\|findRedStatusClasses" wecoza-core.php
218:        $orangeClasses = $service->findOrangeStatusClasses();
227:        $redClasses = $service->findRedStatusClasses();
```
✓ 2 lines (one for each notification type)

**PHP Syntax:**
```bash
$ php -l wecoza-core.php
No syntax errors detected in wecoza-core.php
```
✓ Clean validation

## Deviations from Plan

None - plan executed exactly as written.

## Files Changed

### Modified

**wecoza-core.php** (+82 lines)
- Lines 324-325: Add material tracking capabilities to administrator role
- Lines 329-331: Schedule daily cron event on plugin activation
- Lines 349-350: Remove capabilities on plugin deactivation
- Lines 354-357: Unschedule cron event on plugin deactivation
- Lines 210-233: Cron handler for automated notifications

## Decisions Made

No architectural decisions required - implementation follows established WordPress patterns.

## Next Phase Readiness

**Phase 5 Material Tracking - Plan 01: COMPLETE ✓**

Infrastructure is now in place:
- Administrator role has required capabilities ✓
- Daily cron job will fire automatically ✓
- MaterialNotificationService will be called for both alert types ✓

**Ready for:** Plan 05-02 (Material tracking UI and service implementation)

**No blockers or concerns.**

## How It Works

### Capability Flow
1. Plugin activated → `register_activation_hook` fires
2. Administrator role gets `view_material_tracking` + `manage_material_tracking` capabilities
3. Material tracking pages can now check `current_user_can('view_material_tracking')` for access control
4. Plugin deactivated → capabilities removed cleanly

### Cron Flow
1. Plugin activated → `wp_schedule_event()` registers daily event
2. WordPress cron runs daily (triggered by site visits)
3. `wecoza_material_notifications_check` action fires
4. Handler checks class availability → instantiates MaterialNotificationService
5. Service queries database for classes meeting orange (7-day) criteria
6. Notifications sent for orange classes (if any)
7. Service queries database for classes meeting red (5-day) criteria
8. Notifications sent for red classes (if any)
9. Debug logs written if WP_DEBUG enabled

### Debug Example
When WP_DEBUG enabled, debug.log will show:
```
WeCoza Material Cron: Sent 3 orange (7-day) notifications
WeCoza Material Cron: Sent 1 red (5-day) notifications
```

## Testing Notes

**Manual Verification:**
```bash
# After plugin activation, verify cron scheduled:
wp cron event list | grep wecoza_material_notifications_check

# Test cron handler immediately (don't wait for daily):
wp cron event run wecoza_material_notifications_check

# Check debug.log for output:
tail -f /opt/lampp/htdocs/wecoza/wp-content/debug.log
```

**Capability Verification:**
```php
// In WordPress admin or theme:
if (current_user_can('view_material_tracking')) {
    echo "Can view material tracking dashboard";
}
if (current_user_can('manage_material_tracking')) {
    echo "Can mark materials as delivered";
}
```

## Performance Notes

- Cron handler runs daily (low frequency)
- Database queries only execute if classes exist
- No blocking operations or external API calls in cron handler
- Safe early returns prevent unnecessary processing
