# Phase 5: Material Tracking - Research

**Researched:** 2026-02-02
**Domain:** WordPress AJAX handlers, capability checks, cron scheduling, notification systems
**Confidence:** HIGH

## Summary

Phase 5 involves verifying and completing the material tracking system that was migrated from the events plugin in earlier phases. The code already exists in `src/Events/` (MaterialTrackingController, MaterialTrackingShortcode, MaterialTrackingRepository, MaterialTrackingDashboardService, MaterialNotificationService) but has not been tested or verified. The database schema (`class_material_tracking` table) already exists with proper indexes and constraints.

The primary work is verification and potentially adding missing functionality:
1. **Shortcode verification:** Confirm `[wecoza_material_tracking]` renders dashboard correctly
2. **AJAX handler verification:** Confirm mark-delivered AJAX action works
3. **Alert system verification:** Confirm 7-day and 5-day notification services work
4. **Capability registration:** Add `view_material_tracking` and `manage_material_tracking` capabilities (currently referenced but not registered)
5. **Cron scheduling:** MaterialNotificationService exists but may need WP Cron integration for automated alerts

**Primary recommendation:** Create a verification test suite similar to Phase 4's TaskManagementTest.php, register the missing capabilities during plugin activation, and implement WP Cron scheduling for automated alert notifications.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress AJAX API | WP 6.0+ | Async operations | Standard WP pattern, nonce security built-in |
| WordPress Cron API | WP 6.0+ | Scheduled notifications | Native scheduling, no external dependencies |
| wp_mail() | WP 6.0+ | Email notifications | Built-in mail function with filtering |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PostgresConnection singleton | wecoza-core | Database queries | All repository operations |
| BaseRepository | wecoza-core | CRUD operations | Column whitelisting, secure queries |
| AjaxSecurity | wecoza-core | Nonce/capability checks | AJAX handler security |
| TemplateRenderer | Events module | View rendering | Dashboard and component templates |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP Cron | External cron (crontab) | WP Cron is simpler but less reliable under low traffic; acceptable for this use case |
| wp_mail() | PHPMailer directly | wp_mail() hooks allow SMTP plugins, better compatibility |

**Installation:**
```bash
# No additional installation needed - all components exist in wecoza-core
# Run composer dump-autoload if autoloader issues occur
composer dump-autoload
```

## Architecture Patterns

### Existing Project Structure
```
src/Events/
├── Controllers/
│   └── MaterialTrackingController.php   # AJAX handler for mark-delivered
├── Repositories/
│   └── MaterialTrackingRepository.php   # Database operations
├── Services/
│   ├── MaterialTrackingDashboardService.php  # Dashboard data + permissions
│   └── MaterialNotificationService.php       # Email notification logic
├── Shortcodes/
│   └── MaterialTrackingShortcode.php    # [wecoza_material_tracking]
└── Views/Presenters/
    └── MaterialTrackingPresenter.php    # Data formatting for views

views/events/material-tracking/
├── dashboard.php    # Main dashboard template
├── statistics.php   # Statistics strip
├── list-item.php    # Table row template
└── empty-state.php  # No records message
```

### Pattern 1: Capability-Gated Dashboard Access
**What:** Service layer checks user capabilities before returning data
**When to use:** For all permission-sensitive operations
**Example:**
```php
// Source: src/Events/Services/MaterialTrackingDashboardService.php (lines 93-106)
public function canViewDashboard(): bool
{
    return current_user_can('view_material_tracking') || current_user_can('manage_options');
}

public function canManageMaterialTracking(): bool
{
    return current_user_can('manage_material_tracking') || current_user_can('manage_options');
}
```

### Pattern 2: AJAX Handler with Nonce Verification
**What:** Controller validates nonce and capability before processing request
**When to use:** All AJAX write operations
**Example:**
```php
// Source: src/Events/Controllers/MaterialTrackingController.php (lines 56-84)
public function handleMarkDelivered(): void
{
    check_ajax_referer('wecoza_material_tracking_action', 'nonce');

    if (!is_user_logged_in()) {
        $this->responder->error(__('Please sign in to manage material tracking.', 'wecoza-events'), 403);
    }

    if (!$this->service->canManageMaterialTracking()) {
        $this->responder->error(__('You do not have permission to manage material tracking.', 'wecoza-events'), 403);
    }

    $classId = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
    // ... process
}
```

### Pattern 3: WP Cron Scheduled Events
**What:** Register cron event on activation, hook handler for scheduled execution
**When to use:** Automated notification sending
**Example:**
```php
// Registration pattern from WordPress Cron API
// During plugin activation:
if (!wp_next_scheduled('wecoza_material_notifications_check')) {
    wp_schedule_event(time(), 'daily', 'wecoza_material_notifications_check');
}

// Hook handler (in bootstrap or services):
add_action('wecoza_material_notifications_check', function() {
    $service = new MaterialNotificationService();

    // Check for 7-day (orange) alerts
    $orangeClasses = $service->findOrangeStatusClasses();
    $service->sendMaterialNotifications($orangeClasses, 'orange');

    // Check for 5-day (red) alerts
    $redClasses = $service->findRedStatusClasses();
    $service->sendMaterialNotifications($redClasses, 'red');
});
```

### Pattern 4: Capability Registration on Activation
**What:** Add custom capabilities to Administrator role during plugin activation
**When to use:** When introducing new permission-gated features
**Example:**
```php
// Source: wecoza-core.php (lines 292-297) - existing pattern
// Add to existing activation hook:
$admin = get_role('administrator');
if ($admin) {
    $admin->add_cap('manage_learners');      // existing
    $admin->add_cap('view_material_tracking');   // NEW
    $admin->add_cap('manage_material_tracking'); // NEW
}
```

### Anti-Patterns to Avoid
- **Capability checks in view layer only:** Always check in service layer before returning data, not just in templates
- **Hardcoded email recipients:** Use WordPress options (get_option) for configurable recipients
- **Direct PDO without repository:** Use MaterialTrackingRepository for all database operations
- **Missing nonce in AJAX forms:** Always include wp_create_nonce() in view, check in controller

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| AJAX nonce verification | Manual $_POST checking | check_ajax_referer() | Handles edge cases, standardized |
| Date calculations | Manual date math | PostgreSQL date functions | Database-level precision, timezone handling |
| Duplicate notification prevention | Application-level locking | UNIQUE constraint + ON CONFLICT | Database-level atomicity |
| Email sending | Direct mail() | wp_mail() | Filterable, SMTP plugin compatible |
| Permission checks | Custom permission logic | current_user_can() + capability registration | WordPress role system, plugin compatible |

**Key insight:** The material tracking code already follows these patterns. The task is verification, not reimplementation.

## Common Pitfalls

### Pitfall 1: Capabilities Not Registered
**What goes wrong:** `view_material_tracking` and `manage_material_tracking` capabilities are checked in code but never registered, so only `manage_options` users (admins) can access.
**Why it happens:** Capabilities must be added to roles during plugin activation; the events plugin migration may have missed this step.
**How to avoid:**
1. Add capability registration to `register_activation_hook` in wecoza-core.php
2. Also add to deactivation hook for clean removal
3. Create one-time upgrade routine for existing installations
**Warning signs:** Dashboard shows "You do not have permission" for users who should have access

### Pitfall 2: WP Cron Not Scheduled
**What goes wrong:** MaterialNotificationService has `findOrangeStatusClasses()` and `findRedStatusClasses()` methods, but nothing calls them automatically.
**Why it happens:** The events plugin may have used external cron; migration needs WP Cron integration.
**How to avoid:**
1. Register cron event during activation
2. Add action handler for cron hook
3. Verify with `wp cron event list` command
**Warning signs:** Notifications never send automatically, must be triggered manually

### Pitfall 3: Timezone Mismatches in Date Comparisons
**What goes wrong:** PostgreSQL query compares `CURRENT_DATE + INTERVAL '7 days'` but WordPress/PHP uses different timezone.
**Why it happens:** Database and application timezone settings can differ.
**How to avoid:**
1. Ensure PostgreSQL uses same timezone as WordPress (check `date_default_timezone_get()`)
2. Or use explicit timezone conversion in queries
3. Test around midnight to catch edge cases
**Warning signs:** Classes trigger notifications on wrong days, off-by-one errors

### Pitfall 4: Email Configuration Missing
**What goes wrong:** `sendMaterialNotifications()` reads from `get_option('wecoza_notification_material_delivery')` which may not be set.
**Why it happens:** Option was in events plugin settings but may not have been migrated.
**How to avoid:**
1. Verify option exists in Events Admin Settings page
2. Add default value fallback
3. Log clear error when recipient is not configured
**Warning signs:** "No valid recipient email configured" in error_log

### Pitfall 5: Shortcode Not Registered in Bootstrap
**What goes wrong:** Shortcode class exists but `[wecoza_material_tracking]` returns nothing.
**Why it happens:** `MaterialTrackingShortcode::register()` may not be called in wecoza-core.php.
**How to avoid:**
1. Verify registration in wecoza-core.php (confirmed: lines 193-194)
2. Check `shortcode_exists('wecoza_material_tracking')` in tests
3. Ensure class is autoloaded (composer dump-autoload)
**Warning signs:** Shortcode outputs empty string, no PHP errors

## Code Examples

Verified patterns from existing codebase:

### Notification Email Sending
```php
// Source: src/Events/Services/MaterialNotificationService.php (lines 119-167)
public function sendMaterialNotifications(array $classes, string $notificationType): int
{
    $recipientEmail = (string) get_option('wecoza_notification_material_delivery', '');

    if ($recipientEmail === '' || !is_email($recipientEmail)) {
        error_log('WeCoza Material Notification: No valid recipient email configured');
        return 0;
    }

    $sent = 0;
    foreach ($classes as $class) {
        $classId = (int) $class['class_id'];
        $subject = sprintf('[WeCoza] Material Delivery Required - %s Status - %s',
            $notificationType === 'orange' ? 'Orange (7 days)' : 'Red (5 days)',
            $class['class_code']
        );

        $body = $this->buildEmailBody($class, $notificationType, $statusLabel);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (wp_mail($recipientEmail, $subject, $body, $headers)) {
            $this->trackingRepo->markNotificationSent($classId, $notificationType);
            $sent++;
        }
    }
    return $sent;
}
```

### Database Query with Date Filtering
```php
// Source: src/Events/Services/MaterialNotificationService.php (lines 63-110)
// Find classes needing notifications (7 or 5 days before start)
private function findClassesByDaysUntilStart(int $daysUntilStart, string $notificationType): array
{
    $sql = sprintf(
        'SELECT c.class_id, c.class_code, c.class_subject, c.original_start_date,
                cl.client_name, s.site_name
         FROM classes c
         LEFT JOIN clients cl ON c.client_id = cl.client_id
         LEFT JOIN sites s ON c.site_id = s.site_id
         WHERE c.original_start_date = CURRENT_DATE + INTERVAL \'%d days\'
           AND NOT EXISTS (
               SELECT 1 FROM class_material_tracking cmt
               WHERE cmt.class_id = c.class_id AND cmt.delivery_status = \'delivered\'
           )
           AND NOT EXISTS (
               SELECT 1 FROM class_material_tracking cmt
               WHERE cmt.class_id = c.class_id
                 AND cmt.notification_type = :type
                 AND cmt.notification_sent_at IS NOT NULL
           )
         ORDER BY c.original_start_date',
        $daysUntilStart
    );
    // ... execute
}
```

### Shortcode Registration
```php
// Source: wecoza-core.php (lines 193-194)
if (class_exists(\WeCoza\Events\Shortcodes\MaterialTrackingShortcode::class)) {
    \WeCoza\Events\Shortcodes\MaterialTrackingShortcode::register();
}
```

### AJAX Handler Registration
```php
// Source: wecoza-core.php (lines 202-203)
if (class_exists(\WeCoza\Events\Controllers\MaterialTrackingController::class)) {
    \WeCoza\Events\Controllers\MaterialTrackingController::register();
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| External cron for notifications | WP Cron integration | Phase 5 (current) | Simpler deployment, no server cron needed |
| Inline capability checks | Service-layer permission methods | Already implemented | Centralized, testable permission logic |
| Manual email construction | HTML email builder with template | Already implemented | Consistent branding, easier maintenance |

**Current Implementation Status:**
- MaterialTrackingRepository: EXISTS, extends BaseRepository (confirmed)
- MaterialTrackingDashboardService: EXISTS, has permission methods (confirmed)
- MaterialNotificationService: EXISTS, has email logic (confirmed)
- MaterialTrackingShortcode: EXISTS, registered in bootstrap (confirmed)
- MaterialTrackingController: EXISTS, registered in bootstrap (confirmed)
- View templates: EXISTS in views/events/material-tracking/ (confirmed)
- Capabilities: REFERENCED but NOT REGISTERED (needs fix)
- Cron scheduling: NOT IMPLEMENTED (needs addition)

## Open Questions

1. **Should material tracking use WP Cron or external cron?**
   - What we know: MaterialNotificationService is ready for either, no scheduling code exists
   - What's unclear: Whether WP Cron reliability is sufficient for time-sensitive notifications
   - Recommendation: Use WP Cron with daily schedule; if reliability issues arise, document external cron setup as alternative

2. **What email recipient should be the default?**
   - What we know: Uses `get_option('wecoza_notification_material_delivery')` which may not be set
   - What's unclear: Whether this option exists in current installation
   - Recommendation: Verify option exists in Settings page; if not, add to Events Admin Settings

3. **Should additional roles get material tracking capabilities?**
   - What we know: Currently only administrators (via manage_options fallback) can access
   - What's unclear: Whether other roles (Editor, custom roles) should have access
   - Recommendation: Register capabilities on Administrator only; user can add to other roles as needed

## Sources

### Primary (HIGH confidence)
- Existing codebase: src/Events/Controllers/MaterialTrackingController.php
- Existing codebase: src/Events/Shortcodes/MaterialTrackingShortcode.php
- Existing codebase: src/Events/Services/MaterialTrackingDashboardService.php
- Existing codebase: src/Events/Services/MaterialNotificationService.php
- Existing codebase: src/Events/Repositories/MaterialTrackingRepository.php
- Existing codebase: views/events/material-tracking/*.php
- Database schema: schema/wecoza_db_schema_bu_jan_29.sql (class_material_tracking table)
- Bootstrap: wecoza-core.php (lines 193-203 for registration)

### Secondary (MEDIUM confidence)
- WordPress Cron API documentation (pattern matching against CLAUDE.md hooks)
- Existing Phase 4 verification test pattern (tests/Events/TaskManagementTest.php)

### Tertiary (LOW confidence)
- None - all findings verified against existing codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using existing WordPress APIs and wecoza-core infrastructure
- Architecture: HIGH - Code already exists and follows established patterns
- Pitfalls: HIGH - Based on code review of existing implementation

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - stable WordPress APIs, existing codebase)
