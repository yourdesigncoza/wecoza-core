# Phase 7: Email Notifications - Research

**Researched:** 2026-02-02
**Domain:** WordPress Email Notifications with WP-Cron
**Confidence:** HIGH

## Summary

Phase 7 implements automated email notifications for class INSERT and UPDATE events. The good news is that **most of the infrastructure already exists** in the codebase from previous phases. The NotificationProcessor, NotificationSettings, and NotificationEmailPresenter classes are already implemented and functional. WP Cron patterns are already established in Phase 5 for material notifications.

The primary work for this phase involves:
1. Verifying the existing email notification flow works correctly (NotificationProcessor already sends emails via wp_mail)
2. Ensuring WP Cron properly triggers the notification processor
3. Confirming admin settings page allows configuring recipients
4. Creating verification tests to prove the system works end-to-end

**Primary recommendation:** Focus on verification and testing rather than new development - the NotificationProcessor and NotificationSettings already implement EMAIL-01 through EMAIL-04 requirements.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| wp_mail() | WordPress 6.0+ | Email sending | WordPress native, handles SMTP, hooks for customization |
| WP-Cron | WordPress 6.0+ | Scheduled task execution | WordPress native, integrates with Options API |
| Settings API | WordPress 6.0+ | Admin configuration | WordPress native, handles security, nonces, sanitization |

### Already Implemented (Phase 4-6)
| Class | Location | Purpose | Status |
|-------|----------|---------|--------|
| NotificationProcessor | src/Events/Services/ | Processes class_change_logs and sends emails | COMPLETE |
| NotificationSettings | src/Events/Services/ | Reads recipient config from wp_options | COMPLETE |
| NotificationEmailPresenter | src/Events/Views/Presenters/ | Formats email HTML | COMPLETE |
| SettingsPage | src/Events/Admin/ | Admin UI for configuring recipients | COMPLETE |
| email-summary.php | views/events/event-tasks/ | Email HTML template | COMPLETE |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| wp_next_scheduled() | WordPress 6.0+ | Check if cron event exists | Prevent duplicate scheduling |
| wp_schedule_event() | WordPress 6.0+ | Register recurring cron | During plugin activation |
| wp_unschedule_event() | WordPress 6.0+ | Remove cron event | During plugin deactivation |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| wp_mail() | SMTP plugins | External dependency, but better deliverability |
| WP-Cron | Server cron | Better timing precision, but requires server access |
| Custom queue | Action Scheduler | Robust job queue, but adds dependency |

**No additional installation required** - all functionality uses WordPress core APIs.

## Architecture Patterns

### Existing Project Structure
```
src/Events/
  Services/
    NotificationProcessor.php   # Processes logs, sends emails
    NotificationSettings.php    # Reads recipient configuration
    ...
  Views/
    Presenters/
      NotificationEmailPresenter.php  # Formats email content
  Admin/
    SettingsPage.php            # Admin settings UI
views/events/
  event-tasks/
    email-summary.php           # Email HTML template
```

### Pattern 1: Existing NotificationProcessor Flow
**What:** Reads unprocessed rows from class_change_logs, generates AI summary (optional), sends email via wp_mail
**When to use:** Class INSERT/UPDATE events need email notifications
**Implementation:**
```php
// Source: src/Events/Services/NotificationProcessor.php (existing)
// 1. Fetch unprocessed logs
$rows = $this->fetchRows($lastProcessed, self::BATCH_LIMIT);

// 2. Get recipient based on operation type (INSERT/UPDATE)
$recipient = $this->settings->getRecipientForOperation($operation);

// 3. Format email using presenter
$mailData = $this->presenter->present([...]);

// 4. Send via wp_mail
$sent = wp_mail($recipient, $mailData['subject'], $mailData['body'], $mailData['headers']);

// 5. Track last processed ID
update_option(self::OPTION_LAST_ID, $latestId, false);
```

### Pattern 2: NotificationSettings Configuration
**What:** Maps operation types to wp_option keys for recipients
**When to use:** Determining who receives INSERT vs UPDATE notifications
**Implementation:**
```php
// Source: src/Events/Services/NotificationSettings.php (existing)
public function getRecipientForOperation(string $operation): ?string
{
    return match (strtoupper($operation)) {
        'INSERT' => $this->resolve(['wecoza_notification_class_created']),
        'UPDATE' => $this->resolve(['wecoza_notification_class_updated']),
        default => null,
    };
}
```

### Pattern 3: WP-Cron Handler Pattern (from Phase 5)
**What:** Register cron hook, schedule on activation, unschedule on deactivation
**When to use:** Any scheduled background task
**Implementation:**
```php
// Source: wecoza-core.php (existing pattern from material notifications)
// 1. Register handler
add_action('wecoza_email_notifications_process', function () {
    $processor = NotificationProcessor::boot();
    $processor->process();
});

// 2. Schedule on activation
if (!wp_next_scheduled('wecoza_email_notifications_process')) {
    wp_schedule_event(time(), 'hourly', 'wecoza_email_notifications_process');
}

// 3. Unschedule on deactivation
$timestamp = wp_next_scheduled('wecoza_email_notifications_process');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'wecoza_email_notifications_process');
}
```

### Anti-Patterns to Avoid
- **Sending emails synchronously during class save:** Blocks user request, causes timeout on failure
- **Not checking wp_next_scheduled before scheduling:** Creates duplicate cron events
- **Forgetting to unschedule on deactivation:** Orphaned cron jobs continue running

## Don't Hand-Roll

Problems that have existing solutions in the codebase:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Email sending | Custom mail function | wp_mail() | WordPress handles SMTP, hooks, content-type |
| Recipient config | Custom config files | WordPress Options API | Settings API handles security, sanitization |
| HTML emails | Inline HTML strings | NotificationEmailPresenter | Separation of concerns, reusable templates |
| Scheduling | Custom timers | WP-Cron | Reliable, integrates with WordPress lifecycle |
| Lock mechanism | File locks | Transients | NotificationProcessor already uses transient locks |

**Key insight:** The entire email notification system is already implemented. Do not rebuild - verify and test.

## Common Pitfalls

### Pitfall 1: WECOZA_EVENTS_PLUGIN_DIR Not Defined
**What goes wrong:** NotificationEmailPresenter references `WECOZA_EVENTS_PLUGIN_DIR . 'includes/Views/event-tasks/email-summary.php'` but the constant is not defined
**Why it happens:** Legacy reference from before Events module was merged into wecoza-core
**How to avoid:** Either define the constant or update the path in NotificationEmailPresenter
**Warning signs:** Emails contain raw JSON instead of formatted HTML
**Recommendation:** Update NotificationEmailPresenter to use `wecoza_plugin_path('views/events/event-tasks/email-summary.php')`

### Pitfall 2: Cron Not Scheduled
**What goes wrong:** Notifications never send
**Why it happens:** Plugin activated before cron registration code was added, or cron cleared
**How to avoid:** Check wp_next_scheduled() in tests, ensure activation hook schedules correctly
**Warning signs:** class_change_logs have unprocessed rows with no emails sent

### Pitfall 3: Email Recipient Not Configured
**What goes wrong:** Notifications silently skipped
**Why it happens:** Admin hasn't configured wecoza_notification_class_created or wecoza_notification_class_updated options
**How to avoid:** Add admin notice if notifications enabled but no recipients configured
**Warning signs:** NotificationSettings::getRecipientForOperation() returns null

### Pitfall 4: wp_mail() Returns True But Email Not Delivered
**What goes wrong:** wp_mail() returns true (accepted by mail system) but email never arrives
**Why it happens:** Server SMTP not configured, email marked as spam, DNS issues
**How to avoid:** Use SMTP plugin in production, test with real email addresses
**Warning signs:** wp_mail() succeeds but no emails received

### Pitfall 5: Duplicate Cron Events
**What goes wrong:** Multiple notifications for the same event
**Why it happens:** wp_schedule_event() called without checking wp_next_scheduled()
**How to avoid:** Always check wp_next_scheduled() before scheduling
**Warning signs:** Multiple cron entries with same hook in WP Crontrol

## Code Examples

Verified patterns from existing codebase:

### Sending HTML Email (NotificationProcessor existing)
```php
// Source: src/Events/Services/NotificationProcessor.php
$mailData = $this->presenter->present([
    'operation' => $operation,
    'row' => $row,
    'recipient' => $recipient,
    'new_row' => $newRow,
    'old_row' => $oldRow,
    'diff' => $diff,
    'summary' => $summaryRecord,
    'email_context' => $emailContext,
]);

$subject = $mailData['subject'];
$body = $mailData['body'];
$headers = $mailData['headers'];

$sent = wp_mail($recipient, $subject, $body, $headers);
```

### Getting Recipient from Settings (NotificationSettings existing)
```php
// Source: src/Events/Services/NotificationSettings.php
$recipient = $this->settings->getRecipientForOperation('INSERT');
// Returns email from get_option('wecoza_notification_class_created')

$recipient = $this->settings->getRecipientForOperation('UPDATE');
// Returns email from get_option('wecoza_notification_class_updated')
```

### Registering Settings Field (SettingsPage existing)
```php
// Source: src/Events/Admin/SettingsPage.php
register_setting(self::OPTION_GROUP, self::OPTION_INSERT, [
    'sanitize_callback' => [self::class, 'sanitizeEmail'],
]);

add_settings_field(
    self::OPTION_INSERT,
    esc_html__('New Class notifications email', 'wecoza-events'),
    [self::class, 'renderInsertField'],
    self::PAGE_SLUG,
    self::SECTION_ID
);
```

### Scheduling Cron (wecoza-core.php existing pattern)
```php
// Source: wecoza-core.php (activation hook pattern)
if (!wp_next_scheduled('wecoza_material_notifications_check')) {
    wp_schedule_event(time(), 'daily', 'wecoza_material_notifications_check');
}
```

### Test Pattern (MaterialTrackingTest existing)
```php
// Source: tests/Events/MaterialTrackingTest.php
// Verify cron is scheduled
$next_scheduled = wp_next_scheduled('wecoza_material_notifications_check');
test_result(
    'Cron event wecoza_material_notifications_check is scheduled',
    $next_scheduled !== false,
    'Cron event not found'
);

// Verify cron action handler is registered
$cron_action = 'wecoza_material_notifications_check';
$ajax_registered = isset($wp_filter[$cron_action]) && !empty($wp_filter[$cron_action]->callbacks);
test_result(
    'Cron action hook has handler registered',
    $ajax_registered,
    'Cron handler not found in $wp_filter'
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Sync email on save | Async via cron | Already implemented | Non-blocking requests |
| Plain text emails | HTML with templates | Already implemented | Better UX |
| Hardcoded recipients | wp_options config | Already implemented | Admin configurable |

**Current state:** The NotificationProcessor already implements all EMAIL-* requirements. Phase 7 is primarily verification work.

## Key Findings Summary

### What Already Exists (HIGH confidence)
1. **NotificationProcessor** - Fully implemented, processes class_change_logs and sends via wp_mail
2. **NotificationSettings** - Reads INSERT/UPDATE recipients from wp_options
3. **NotificationEmailPresenter** - Formats HTML emails with AI summary support
4. **SettingsPage** - Admin UI with wecoza_notification_class_created and wecoza_notification_class_updated fields
5. **email-summary.php template** - HTML email template at views/events/event-tasks/

### What Needs Verification
1. **Cron scheduling** - Need to add/verify cron hook for NotificationProcessor::process()
2. **Template path fix** - NotificationEmailPresenter references undefined constant
3. **End-to-end test** - Verify INSERT/UPDATE triggers email to configured recipient

### What Needs Implementation
1. **Cron hook registration** - Add action hook for notification processing (similar to material notifications)
2. **Cron scheduling** - Schedule in activation hook, unschedule in deactivation
3. **Verification test suite** - Follow MaterialTrackingTest pattern

## Open Questions

Things that need validation during implementation:

1. **Cron frequency**
   - What we know: Material notifications run daily
   - What's unclear: Should email notifications run more frequently (hourly)?
   - Recommendation: Use hourly schedule for timely notifications

2. **Template path constant**
   - What we know: WECOZA_EVENTS_PLUGIN_DIR is not defined
   - What's unclear: Was this intentional (fallback to JSON) or oversight?
   - Recommendation: Fix path to use wecoza_plugin_path() for proper HTML templates

3. **Existing cron for notifications**
   - What we know: NotificationProcessor exists but unclear if cron hook is registered
   - What's unclear: Is it being called anywhere currently?
   - Recommendation: Verify and add cron hook if missing

## Sources

### Primary (HIGH confidence)
- Existing codebase: NotificationProcessor.php, NotificationSettings.php, SettingsPage.php
- WordPress Developer Docs: https://developer.wordpress.org/reference/functions/wp_mail/
- WordPress Cron Docs: https://developer.wordpress.org/plugins/cron/scheduling-wp-cron-events/

### Secondary (MEDIUM confidence)
- WordPress email best practices: https://wpmailsmtp.com/wordpress-email-deliverability-best-practices/
- WP-Cron best practices: https://mainwp.com/wp-cron-enhancing-scheduler-efficiency-wordpress/
- Settings API: https://developer.wordpress.org/plugins/settings/using-settings-api/

### Tertiary (LOW confidence)
- Email queue patterns: https://actionscheduler.org/ (not needed for this implementation)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using WordPress core APIs already implemented
- Architecture: HIGH - Patterns exist in codebase (Phase 5 material notifications)
- Pitfalls: HIGH - Identified specific issues in existing code (template path)

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - stable WordPress APIs)
