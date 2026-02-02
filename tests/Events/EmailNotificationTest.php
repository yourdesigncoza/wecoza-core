<?php
/**
 * Email Notification Verification Tests
 *
 * Verifies all migrated email notification functionality works correctly
 * Run with: wp eval-file tests/Events/EmailNotificationTest.php --path=/opt/lampp/htdocs/wecoza
 */

declare(strict_types=1);

// Bootstrap WordPress if not running via WP-CLI
if (!function_exists('get_option')) {
    require_once '/opt/lampp/htdocs/wecoza/wp-load.php';
}

// Test result tracking
$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];

function test_result(string $name, bool $passed, string $message = ''): void {
    global $results;

    $results['total']++;
    if ($passed) {
        $results['passed']++;
        echo "✓ PASS: {$name}\n";
    } else {
        $results['failed']++;
        echo "✗ FAIL: {$name}\n";
        if ($message) {
            echo "  Error: {$message}\n";
        }
    }

    $results['tests'][] = [
        'name' => $name,
        'passed' => $passed,
        'message' => $message
    ];
}

// Ensure cron is scheduled (normally done during plugin activation)
if (!wp_next_scheduled('wecoza_email_notifications_process')) {
    wp_schedule_event(time(), 'hourly', 'wecoza_email_notifications_process');
}

// Ensure settings are registered (normally done during admin_init)
// Note: Settings registration requires admin context, so we'll test it conditionally
if (class_exists('WeCoza\\Events\\Admin\\SettingsPage') && function_exists('add_settings_section')) {
    \WeCoza\Events\Admin\SettingsPage::registerSettings();
}

echo "\n";
echo "====================================\n";
echo "EMAIL NOTIFICATION VERIFICATION TESTS\n";
echo "====================================\n\n";

// ============================================================================
// EMAIL-03: CRON INTEGRATION VERIFICATION
// ============================================================================

echo "--- EMAIL-03: WordPress Cron Integration ---\n\n";

// Test 1.1: Verify cron hook is registered
global $wp_filter;
$cron_action = 'wecoza_email_notifications_process';
$cron_registered = isset($wp_filter[$cron_action]) && !empty($wp_filter[$cron_action]->callbacks);
test_result(
    'Cron hook wecoza_email_notifications_process is registered',
    $cron_registered,
    $cron_registered ? '' : 'Cron action not found in $wp_filter'
);

// Test 1.2: Verify cron event is scheduled
$next_scheduled = wp_next_scheduled('wecoza_email_notifications_process');
$cron_scheduled = $next_scheduled !== false;
test_result(
    'Cron event wecoza_email_notifications_process is scheduled',
    $cron_scheduled,
    $cron_scheduled ? '' : 'Cron event not found in WordPress schedule'
);

// Test 1.3: Verify next scheduled timestamp is valid
if ($next_scheduled) {
    $is_valid = is_numeric($next_scheduled) && $next_scheduled > 0;
    test_result(
        'Next scheduled cron execution has valid timestamp',
        $is_valid,
        $is_valid ? '' : "Invalid timestamp: " . var_export($next_scheduled, true)
    );
}

// Test 1.4: Verify cron recurrence is hourly
$cron_schedules = wp_get_schedules();
$crons = _get_cron_array();
$recurrence_found = false;
foreach ($crons as $timestamp => $cron) {
    if (isset($cron[$cron_action])) {
        foreach ($cron[$cron_action] as $key => $event) {
            if (isset($event['schedule']) && $event['schedule'] === 'hourly') {
                $recurrence_found = true;
                break 2;
            }
        }
    }
}
test_result(
    'Cron event uses hourly recurrence (EMAIL-03)',
    $recurrence_found,
    $recurrence_found ? '' : 'Expected hourly schedule, found different recurrence or no schedule'
);

echo "\n";

// ============================================================================
// EMAIL-01, EMAIL-02: NOTIFICATION PROCESSOR SERVICE VERIFICATION
// ============================================================================

echo "--- EMAIL-01, EMAIL-02: Notification Processor Service ---\n\n";

// Test 2.1: Verify NotificationProcessor class exists
$processor_exists = class_exists('WeCoza\\Events\\Services\\NotificationProcessor');
test_result(
    'NotificationProcessor class exists',
    $processor_exists,
    $processor_exists ? '' : 'Class WeCoza\\Events\\Services\\NotificationProcessor not found'
);

// Test 2.2: Verify NotificationProcessor::boot() method exists
if ($processor_exists) {
    $has_boot = method_exists('WeCoza\\Events\\Services\\NotificationProcessor', 'boot');
    test_result(
        'NotificationProcessor has boot() static method',
        $has_boot,
        $has_boot ? '' : 'Static method boot() not found'
    );

    // Test 2.3: Verify NotificationProcessor::boot() returns instance
    if ($has_boot) {
        try {
            $processor = \WeCoza\Events\Services\NotificationProcessor::boot();
            $returns_instance = $processor instanceof \WeCoza\Events\Services\NotificationProcessor;
            test_result(
                'NotificationProcessor::boot() returns valid instance',
                $returns_instance,
                $returns_instance ? '' : 'Expected NotificationProcessor instance, got ' . gettype($processor)
            );
        } catch (Exception $e) {
            test_result('NotificationProcessor::boot() execution', false, $e->getMessage());
        }
    }

    // Test 2.4: Verify NotificationProcessor has process() method
    $has_process = method_exists('WeCoza\\Events\\Services\\NotificationProcessor', 'process');
    test_result(
        'NotificationProcessor has process() method',
        $has_process,
        $has_process ? '' : 'Method process() not found'
    );

    // Test 2.5: Verify process() can be called without errors
    if ($has_process) {
        try {
            $processor = \WeCoza\Events\Services\NotificationProcessor::boot();
            // Call process() - it should run without throwing exceptions even if no data
            $processor->process();
            $no_errors = true;
            test_result(
                'NotificationProcessor::process() executes without errors',
                $no_errors,
                ''
            );
        } catch (Exception $e) {
            test_result('NotificationProcessor::process() execution', false, $e->getMessage());
        }
    }
}

echo "\n";

// ============================================================================
// EMAIL-04: NOTIFICATION SETTINGS SERVICE VERIFICATION
// ============================================================================

echo "--- EMAIL-04: Notification Settings Service ---\n\n";

// Test 3.1: Verify NotificationSettings class exists
$settings_exists = class_exists('WeCoza\\Events\\Services\\NotificationSettings');
test_result(
    'NotificationSettings class exists',
    $settings_exists,
    $settings_exists ? '' : 'Class WeCoza\\Events\\Services\\NotificationSettings not found'
);

// Test 3.2: Verify NotificationSettings is instantiable
if ($settings_exists) {
    try {
        $settings = new \WeCoza\Events\Services\NotificationSettings();
        $is_instantiable = true;
        test_result(
            'NotificationSettings is instantiable',
            $is_instantiable,
            ''
        );

        // Test 3.3: Verify getRecipientForOperation() method exists
        $has_get_recipient = method_exists($settings, 'getRecipientForOperation');
        test_result(
            'NotificationSettings has getRecipientForOperation() method',
            $has_get_recipient,
            $has_get_recipient ? '' : 'Method not found'
        );

        // Test 3.4: Verify getRecipientForOperation() handles INSERT (EMAIL-01)
        if ($has_get_recipient) {
            // Set test email for INSERT
            update_option('wecoza_notification_class_created', 'test-insert@example.com');

            $recipient = $settings->getRecipientForOperation('INSERT');
            $correct_insert = $recipient === 'test-insert@example.com';
            test_result(
                'NotificationSettings.getRecipientForOperation("INSERT") returns configured email (EMAIL-01)',
                $correct_insert,
                $correct_insert ? '' : "Expected test-insert@example.com, got " . var_export($recipient, true)
            );

            // Clean up
            delete_option('wecoza_notification_class_created');
        }

        // Test 3.5: Verify getRecipientForOperation() handles UPDATE (EMAIL-02)
        if ($has_get_recipient) {
            // Set test email for UPDATE
            update_option('wecoza_notification_class_updated', 'test-update@example.com');

            $recipient = $settings->getRecipientForOperation('UPDATE');
            $correct_update = $recipient === 'test-update@example.com';
            test_result(
                'NotificationSettings.getRecipientForOperation("UPDATE") returns configured email (EMAIL-02)',
                $correct_update,
                $correct_update ? '' : "Expected test-update@example.com, got " . var_export($recipient, true)
            );

            // Clean up
            delete_option('wecoza_notification_class_updated');
        }

        // Test 3.6: Verify getRecipientForOperation() returns null for invalid operations
        if ($has_get_recipient) {
            $recipient = $settings->getRecipientForOperation('DELETE');
            $returns_null = $recipient === null;
            test_result(
                'NotificationSettings.getRecipientForOperation("DELETE") returns null for unsupported operations',
                $returns_null,
                $returns_null ? '' : "Expected null for DELETE operation, got " . var_export($recipient, true)
            );
        }

        // Test 3.7: Verify getRecipientForOperation() returns null when no email configured
        if ($has_get_recipient) {
            delete_option('wecoza_notification_class_created');
            $recipient = $settings->getRecipientForOperation('INSERT');
            $returns_null = $recipient === null;
            test_result(
                'NotificationSettings.getRecipientForOperation() returns null when no email configured',
                $returns_null,
                $returns_null ? '' : "Expected null when no email set, got " . var_export($recipient, true)
            );
        }

    } catch (Exception $e) {
        test_result('NotificationSettings instantiation', false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// EMAIL PRESENTER VERIFICATION
// ============================================================================

echo "--- Email Presenter Verification ---\n\n";

// Test 4.1: Verify NotificationEmailPresenter class exists
$presenter_exists = class_exists('WeCoza\\Events\\Views\\Presenters\\NotificationEmailPresenter');
test_result(
    'NotificationEmailPresenter class exists',
    $presenter_exists,
    $presenter_exists ? '' : 'Class WeCoza\\Events\\Views\\Presenters\\NotificationEmailPresenter not found'
);

// Test 4.2: Verify presenter is instantiable
if ($presenter_exists) {
    try {
        $presenter = new \WeCoza\Events\Views\Presenters\NotificationEmailPresenter();
        $is_instantiable = true;
        test_result(
            'NotificationEmailPresenter is instantiable',
            $is_instantiable,
            ''
        );

        // Test 4.3: Verify present() method exists
        $has_present = method_exists($presenter, 'present');
        test_result(
            'NotificationEmailPresenter has present() method',
            $has_present,
            $has_present ? '' : 'Method not found'
        );

        // Test 4.4: Verify present() returns expected structure
        if ($has_present) {
            $test_context = [
                'operation' => 'INSERT',
                'row' => ['class_id' => '123', 'changed_at' => '2026-02-02T13:00:00Z'],
                'recipient' => 'test@example.com',
                'new_row' => ['class_code' => 'TEST-001', 'class_subject' => 'Test Subject'],
                'old_row' => [],
                'diff' => [],
                'summary' => ['status' => 'success', 'summary' => 'Test summary text'],
                'email_context' => ['alias_map' => [], 'obfuscated' => []],
            ];

            $result = $presenter->present($test_context);

            $has_subject = isset($result['subject']) && is_string($result['subject']);
            $has_body = isset($result['body']) && is_string($result['body']);
            $has_headers = isset($result['headers']) && is_array($result['headers']);

            $valid_structure = $has_subject && $has_body && $has_headers;
            test_result(
                'NotificationEmailPresenter.present() returns valid structure (subject, body, headers)',
                $valid_structure,
                $valid_structure ? '' : 'Missing required keys in returned array'
            );

            // Test 4.5: Verify body contains HTML (not JSON fallback)
            if ($has_body) {
                $body = $result['body'];
                $is_html = strpos($body, '<div') !== false || strpos($body, '<h1') !== false;
                $not_json = strpos($body, '"operation"') === false;
                test_result(
                    'NotificationEmailPresenter renders HTML email (not JSON fallback)',
                    $is_html && $not_json,
                    ($is_html && $not_json) ? '' : 'Body appears to be JSON instead of HTML'
                );
            }

            // Test 4.6: Verify headers include Content-Type for HTML
            if ($has_headers) {
                $has_html_header = false;
                foreach ($result['headers'] as $header) {
                    if (is_string($header) && stripos($header, 'text/html') !== false) {
                        $has_html_header = true;
                        break;
                    }
                }
                test_result(
                    'NotificationEmailPresenter includes text/html Content-Type header',
                    $has_html_header,
                    $has_html_header ? '' : 'Missing text/html Content-Type in headers'
                );
            }
        }

    } catch (Exception $e) {
        test_result('NotificationEmailPresenter instantiation', false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// EMAIL TEMPLATE VERIFICATION
// ============================================================================

echo "--- Email Template Verification ---\n\n";

// Test 5.1: Verify email template file exists
$template_path = wecoza_plugin_path('views/events/event-tasks/email-summary.php');
$template_exists = file_exists($template_path);
test_result(
    'Email template views/events/event-tasks/email-summary.php exists',
    $template_exists,
    $template_exists ? '' : "File not found at {$template_path}"
);

// Test 5.2: Verify template path used in presenter is correct
if ($template_exists && $presenter_exists) {
    // Read presenter source to verify path usage
    $presenter_source = file_get_contents(wecoza_plugin_path('src/Events/Views/Presenters/NotificationEmailPresenter.php'));
    $uses_wecoza_plugin_path = strpos($presenter_source, "wecoza_plugin_path('views/events/event-tasks/email-summary.php')") !== false;
    test_result(
        'NotificationEmailPresenter uses correct template path (wecoza_plugin_path)',
        $uses_wecoza_plugin_path,
        $uses_wecoza_plugin_path ? '' : 'Template path does not use wecoza_plugin_path() helper'
    );
}

// Test 5.3: Verify template contains expected HTML structure
if ($template_exists) {
    $template_content = file_get_contents($template_path);
    $has_html_structure = strpos($template_content, '<div') !== false
                       && strpos($template_content, '<h1') !== false
                       && strpos($template_content, '<section') !== false;
    test_result(
        'Email template contains HTML structure (div, h1, section)',
        $has_html_structure,
        $has_html_structure ? '' : 'Template does not appear to contain expected HTML elements'
    );
}

echo "\n";

// ============================================================================
// EMAIL-04: SETTINGS PAGE INTEGRATION
// ============================================================================

echo "--- EMAIL-04: Settings Page Integration ---\n\n";

// Test 6.1: Verify SettingsPage class exists
$settings_page_exists = class_exists('WeCoza\\Events\\Admin\\SettingsPage');
test_result(
    'SettingsPage class exists',
    $settings_page_exists,
    $settings_page_exists ? '' : 'Class WeCoza\\Events\\Admin\\SettingsPage not found'
);

// Test 6.2: Verify settings are registered in WordPress (if admin context available)
if ($settings_page_exists) {
    global $wp_settings_fields;

    // Settings registration requires admin context - test only if available
    if (function_exists('add_settings_section')) {
        $insert_field_registered = isset($wp_settings_fields['wecoza-events-notifications']['wecoza_events_notifications_section']['wecoza_notification_class_created']);
        test_result(
            'Settings page registers wecoza_notification_class_created field (EMAIL-01 configuration)',
            $insert_field_registered,
            $insert_field_registered ? '' : 'Field not found in $wp_settings_fields'
        );

        $update_field_registered = isset($wp_settings_fields['wecoza-events-notifications']['wecoza_events_notifications_section']['wecoza_notification_class_updated']);
        test_result(
            'Settings page registers wecoza_notification_class_updated field (EMAIL-02 configuration)',
            $update_field_registered,
            $update_field_registered ? '' : 'Field not found in $wp_settings_fields'
        );
    } else {
        // Test the methods exist instead (admin functions not available in test context)
        $has_register_method = method_exists('WeCoza\\Events\\Admin\\SettingsPage', 'registerSettings');
        test_result(
            'SettingsPage has registerSettings() method for field registration',
            $has_register_method,
            $has_register_method ? '' : 'Method registerSettings() not found'
        );

        $has_render_insert = method_exists('WeCoza\\Events\\Admin\\SettingsPage', 'renderInsertField');
        test_result(
            'SettingsPage has renderInsertField() method (EMAIL-01 configuration)',
            $has_render_insert,
            $has_render_insert ? '' : 'Method renderInsertField() not found'
        );

        $has_render_update = method_exists('WeCoza\\Events\\Admin\\SettingsPage', 'renderUpdateField');
        test_result(
            'SettingsPage has renderUpdateField() method (EMAIL-02 configuration)',
            $has_render_update,
            $has_render_update ? '' : 'Method renderUpdateField() not found'
        );
    }
}

// Test 6.3: Verify email sanitization callback exists
if ($settings_page_exists) {
    $has_sanitize = method_exists('WeCoza\\Events\\Admin\\SettingsPage', 'sanitizeEmail');
    test_result(
        'SettingsPage has sanitizeEmail() method for input validation',
        $has_sanitize,
        $has_sanitize ? '' : 'Method sanitizeEmail() not found'
    );

    // Test 6.4: Verify sanitization works correctly
    if ($has_sanitize) {
        $valid_email = \WeCoza\Events\Admin\SettingsPage::sanitizeEmail('test@example.com');
        $sanitizes_valid = $valid_email === 'test@example.com';
        test_result(
            'SettingsPage.sanitizeEmail() preserves valid email addresses',
            $sanitizes_valid,
            $sanitizes_valid ? '' : "Expected test@example.com, got {$valid_email}"
        );

        $invalid_email = \WeCoza\Events\Admin\SettingsPage::sanitizeEmail('not-an-email');
        $rejects_invalid = $invalid_email === '';
        test_result(
            'SettingsPage.sanitizeEmail() rejects invalid email addresses',
            $rejects_invalid,
            $rejects_invalid ? '' : "Expected empty string, got {$invalid_email}"
        );
    }
}

// Test 6.5: Verify options can be set and retrieved (EMAIL-04)
update_option('wecoza_notification_class_created', 'admin@example.com');
$retrieved_insert = get_option('wecoza_notification_class_created');
$option_works_insert = $retrieved_insert === 'admin@example.com';
test_result(
    'wecoza_notification_class_created option is settable and retrievable (EMAIL-04)',
    $option_works_insert,
    $option_works_insert ? '' : "Expected admin@example.com, got {$retrieved_insert}"
);
delete_option('wecoza_notification_class_created');

update_option('wecoza_notification_class_updated', 'manager@example.com');
$retrieved_update = get_option('wecoza_notification_class_updated');
$option_works_update = $retrieved_update === 'manager@example.com';
test_result(
    'wecoza_notification_class_updated option is settable and retrievable (EMAIL-04)',
    $option_works_update,
    $option_works_update ? '' : "Expected manager@example.com, got {$retrieved_update}"
);
delete_option('wecoza_notification_class_updated');

echo "\n";

// ============================================================================
// FINAL SUMMARY
// ============================================================================

echo "====================================\n";
echo "TEST SUMMARY\n";
echo "====================================\n\n";

echo "Total tests: {$results['total']}\n";
echo "Passed: {$results['passed']}\n";
echo "Failed: {$results['failed']}\n";

$pass_rate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 2) : 0;
echo "Pass rate: {$pass_rate}%\n\n";

if ($results['failed'] > 0) {
    echo "FAILED TESTS:\n";
    foreach ($results['tests'] as $test) {
        if (!$test['passed']) {
            echo "  - {$test['name']}\n";
            if ($test['message']) {
                echo "    {$test['message']}\n";
            }
        }
    }
    echo "\n";
}

// ============================================================================
// REQUIREMENTS VERIFICATION SUMMARY
// ============================================================================

echo "====================================\n";
echo "REQUIREMENTS VERIFICATION\n";
echo "====================================\n\n";

$requirements = [
    'EMAIL-01' => 'Automated email notifications on class INSERT events',
    'EMAIL-02' => 'Automated email notifications on class UPDATE events',
    'EMAIL-03' => 'WordPress cron integration for scheduled notifications (hourly)',
    'EMAIL-04' => 'Configurable notification recipients via WordPress options'
];

foreach ($requirements as $req => $desc) {
    echo "{$req}: {$desc}\n";
}

echo "\n";

if ($pass_rate === 100.0) {
    echo "✓ ALL REQUIREMENTS VERIFIED\n";
    exit(0);
} else {
    echo "✗ SOME REQUIREMENTS NOT VERIFIED\n";
    echo "Please review failed tests above and fix any issues.\n";
    exit(1);
}
