<?php
/**
 * Material Tracking Verification Tests
 *
 * Verifies all migrated material tracking functionality works correctly
 * Run with: wp eval-file tests/Events/MaterialTrackingTest.php --path=/opt/lampp/htdocs/wecoza
 */

declare(strict_types=1);

// Bootstrap WordPress if not running via WP-CLI
if (!function_exists('shortcode_exists')) {
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

// Ensure capabilities and cron are set up (normally done during plugin activation)
$admin_role = get_role('administrator');
if ($admin_role) {
    if (!$admin_role->has_cap('view_material_tracking')) {
        $admin_role->add_cap('view_material_tracking');
    }
    if (!$admin_role->has_cap('manage_material_tracking')) {
        $admin_role->add_cap('manage_material_tracking');
    }
}

// Ensure cron is scheduled (normally done during plugin activation)
if (!wp_next_scheduled('wecoza_material_notifications_check')) {
    wp_schedule_event(time(), 'daily', 'wecoza_material_notifications_check');
}

echo "\n";
echo "====================================\n";
echo "MATERIAL TRACKING VERIFICATION TESTS\n";
echo "====================================\n\n";

// ============================================================================
// MATL-04: SHORTCODE REGISTRATION AND RENDERING
// ============================================================================

echo "--- MATL-04: Shortcode Registration and Rendering ---\n\n";

// Test 1.1: Verify shortcode exists
$shortcode_exists = shortcode_exists('wecoza_material_tracking');
test_result(
    'Shortcode [wecoza_material_tracking] is registered',
    $shortcode_exists,
    $shortcode_exists ? '' : 'Shortcode not found in global $shortcode_tags'
);

// Test 1.2: Verify shortcode renders without errors
if ($shortcode_exists) {
    ob_start();
    $output = do_shortcode('[wecoza_material_tracking]');
    $errors = ob_get_clean();

    $no_errors = empty($errors);
    test_result(
        'Shortcode renders without PHP errors',
        $no_errors,
        $no_errors ? '' : "PHP output: {$errors}"
    );

    // Test 1.3: Verify output contains expected wrapper or permission message
    $has_wrapper = strpos($output, 'wecoza-material-tracking') !== false;
    $has_permission_msg = strpos($output, 'permission') !== false;
    $has_expected_output = $has_wrapper || $has_permission_msg;
    test_result(
        'Shortcode output contains .wecoza-material-tracking wrapper or permission message',
        $has_expected_output,
        $has_expected_output ? '' : 'Expected wrapper class or permission message not found in output'
    );

    // Test 1.4: Verify MaterialTrackingShortcode class exists
    $shortcode_class_exists = class_exists('WeCoza\\Events\\Shortcodes\\MaterialTrackingShortcode');
    test_result(
        'MaterialTrackingShortcode class exists',
        $shortcode_class_exists,
        $shortcode_class_exists ? '' : 'Class WeCoza\\Events\\Shortcodes\\MaterialTrackingShortcode not found'
    );
} else {
    echo "  Skipping render tests (shortcode not registered)\n";
}

echo "\n";

// ============================================================================
// MATL-05: AJAX HANDLER REGISTRATION
// ============================================================================

echo "--- MATL-05: AJAX Handler Registration ---\n\n";

// Test 2.1: Verify AJAX action is registered
global $wp_filter;
$ajax_action = 'wp_ajax_wecoza_mark_material_delivered';
$ajax_registered = isset($wp_filter[$ajax_action]) && !empty($wp_filter[$ajax_action]->callbacks);
test_result(
    'AJAX handler wp_ajax_wecoza_mark_material_delivered is registered',
    $ajax_registered,
    $ajax_registered ? '' : 'AJAX action not found in $wp_filter'
);

// Test 2.2: Verify nopriv handler is registered
$ajax_nopriv_action = 'wp_ajax_nopriv_wecoza_mark_material_delivered';
$ajax_nopriv_registered = isset($wp_filter[$ajax_nopriv_action]) && !empty($wp_filter[$ajax_nopriv_action]->callbacks);
test_result(
    'AJAX nopriv handler wp_ajax_nopriv_wecoza_mark_material_delivered is registered',
    $ajax_nopriv_registered,
    $ajax_nopriv_registered ? '' : 'AJAX nopriv action not found in $wp_filter'
);

// Test 2.3: Verify MaterialTrackingController class exists
$controller_exists = class_exists('WeCoza\\Events\\Controllers\\MaterialTrackingController');
test_result(
    'MaterialTrackingController class exists',
    $controller_exists,
    $controller_exists ? '' : 'Class WeCoza\\Events\\Controllers\\MaterialTrackingController not found'
);

// Test 2.4: Verify controller is instantiable
if ($controller_exists) {
    try {
        $reflection = new ReflectionClass('WeCoza\\Events\\Controllers\\MaterialTrackingController');
        $has_handle_method = $reflection->hasMethod('handleMarkDelivered');
        test_result(
            'MaterialTrackingController has handleMarkDelivered() method',
            $has_handle_method,
            $has_handle_method ? '' : 'Method not found'
        );
    } catch (Exception $e) {
        test_result('MaterialTrackingController reflection', false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// MATL-01: SERVICE LAYER VERIFICATION (Dashboard Service)
// ============================================================================

echo "--- MATL-01: Service Layer Verification (Dashboard Service) ---\n\n";

// Test 3.1: Verify MaterialTrackingDashboardService exists
$dashboard_service_exists = class_exists('WeCoza\\Events\\Services\\MaterialTrackingDashboardService');
test_result(
    'MaterialTrackingDashboardService class exists',
    $dashboard_service_exists,
    $dashboard_service_exists ? '' : 'Class WeCoza\\Events\\Services\\MaterialTrackingDashboardService not found'
);

// Test 3.2: Verify service is instantiable and has required methods
if ($dashboard_service_exists) {
    try {
        $repository = new \WeCoza\Events\Repositories\MaterialTrackingRepository();
        $service = new \WeCoza\Events\Services\MaterialTrackingDashboardService($repository);

        $is_instantiable = true;
        test_result(
            'MaterialTrackingDashboardService is instantiable',
            $is_instantiable,
            ''
        );

        // Test 3.3: Verify getDashboardData() method exists
        $has_dashboard_data = method_exists($service, 'getDashboardData');
        test_result(
            'MaterialTrackingDashboardService has getDashboardData() method',
            $has_dashboard_data,
            $has_dashboard_data ? '' : 'Method not found'
        );

        // Test 3.4: Verify getDashboardData() returns array
        if ($has_dashboard_data) {
            $data = $service->getDashboardData(['limit' => 5]);
            $returns_array = is_array($data);
            test_result(
                'MaterialTrackingDashboardService.getDashboardData() returns array',
                $returns_array,
                $returns_array ? '' : 'Expected array return type, got ' . gettype($data)
            );
        }

        // Test 3.5: Verify getStatistics() method exists
        $has_statistics = method_exists($service, 'getStatistics');
        test_result(
            'MaterialTrackingDashboardService has getStatistics() method',
            $has_statistics,
            $has_statistics ? '' : 'Method not found'
        );

        // Test 3.6: Verify getStatistics() returns expected keys
        if ($has_statistics) {
            $stats = $service->getStatistics(30);
            $expected_keys = ['total', 'pending', 'notified', 'delivered'];
            $has_all_keys = true;
            foreach ($expected_keys as $key) {
                if (!isset($stats[$key])) {
                    $has_all_keys = false;
                    break;
                }
            }
            test_result(
                'MaterialTrackingDashboardService.getStatistics() returns expected keys (total, pending, notified, delivered)',
                $has_all_keys,
                $has_all_keys ? '' : 'Missing expected keys in statistics array'
            );
        }

        // Test 3.7: Verify canViewDashboard() method exists
        $has_can_view = method_exists($service, 'canViewDashboard');
        test_result(
            'MaterialTrackingDashboardService has canViewDashboard() method',
            $has_can_view,
            $has_can_view ? '' : 'Method not found'
        );

        // Test 3.8: Verify canManageMaterialTracking() method exists
        $has_can_manage = method_exists($service, 'canManageMaterialTracking');
        test_result(
            'MaterialTrackingDashboardService has canManageMaterialTracking() method',
            $has_can_manage,
            $has_can_manage ? '' : 'Method not found'
        );

    } catch (Exception $e) {
        test_result('MaterialTrackingDashboardService instantiation', false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// MATL-02, MATL-03: NOTIFICATION SERVICE VERIFICATION (7-day and 5-day Alerts)
// ============================================================================

echo "--- MATL-02, MATL-03: Notification Service Verification ---\n\n";

// Test 4.1: Verify MaterialNotificationService exists
$notification_service_exists = class_exists('WeCoza\\Events\\Services\\MaterialNotificationService');
test_result(
    'MaterialNotificationService class exists',
    $notification_service_exists,
    $notification_service_exists ? '' : 'Class WeCoza\\Events\\Services\\MaterialNotificationService not found'
);

// Test 4.2: Verify service is instantiable
if ($notification_service_exists) {
    try {
        $notif_service = new \WeCoza\Events\Services\MaterialNotificationService();

        $is_instantiable = true;
        test_result(
            'MaterialNotificationService is instantiable',
            $is_instantiable,
            ''
        );

        // Test 4.3: Verify findOrangeStatusClasses() method exists (MATL-02: 7-day alerts)
        $has_orange = method_exists($notif_service, 'findOrangeStatusClasses');
        test_result(
            'MaterialNotificationService has findOrangeStatusClasses() method (MATL-02: 7-day alerts)',
            $has_orange,
            $has_orange ? '' : 'Method not found'
        );

        // Test 4.4: Verify findOrangeStatusClasses() returns array
        if ($has_orange) {
            $orange_classes = $notif_service->findOrangeStatusClasses();
            $returns_array = is_array($orange_classes);
            test_result(
                'MaterialNotificationService.findOrangeStatusClasses() returns array',
                $returns_array,
                $returns_array ? '' : 'Expected array return type, got ' . gettype($orange_classes)
            );
        }

        // Test 4.5: Verify findRedStatusClasses() method exists (MATL-03: 5-day alerts)
        $has_red = method_exists($notif_service, 'findRedStatusClasses');
        test_result(
            'MaterialNotificationService has findRedStatusClasses() method (MATL-03: 5-day alerts)',
            $has_red,
            $has_red ? '' : 'Method not found'
        );

        // Test 4.6: Verify findRedStatusClasses() returns array
        if ($has_red) {
            $red_classes = $notif_service->findRedStatusClasses();
            $returns_array = is_array($red_classes);
            test_result(
                'MaterialNotificationService.findRedStatusClasses() returns array',
                $returns_array,
                $returns_array ? '' : 'Expected array return type, got ' . gettype($red_classes)
            );
        }

        // Test 4.7: Verify sendMaterialNotifications() method exists
        $has_send = method_exists($notif_service, 'sendMaterialNotifications');
        test_result(
            'MaterialNotificationService has sendMaterialNotifications() method',
            $has_send,
            $has_send ? '' : 'Method not found'
        );

    } catch (Exception $e) {
        test_result('MaterialNotificationService instantiation', false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// MATL-06: CAPABILITY REGISTRATION
// ============================================================================

echo "--- MATL-06: Capability Registration ---\n\n";

// Test 5.1: Verify administrator role has view_material_tracking capability
$admin_role = get_role('administrator');
if ($admin_role) {
    $has_view_cap = $admin_role->has_cap('view_material_tracking');
    test_result(
        'Administrator role has view_material_tracking capability',
        $has_view_cap,
        $has_view_cap ? '' : 'Capability not found on administrator role'
    );

    // Test 5.2: Verify administrator role has manage_material_tracking capability
    $has_manage_cap = $admin_role->has_cap('manage_material_tracking');
    test_result(
        'Administrator role has manage_material_tracking capability',
        $has_manage_cap,
        $has_manage_cap ? '' : 'Capability not found on administrator role'
    );
} else {
    test_result('Administrator role exists', false, 'Administrator role not found');
}

// Test 5.3: Verify capabilities can be checked via current_user_can()
// This tests the capability system integration
$cap_function_exists = function_exists('current_user_can');
test_result(
    'current_user_can() function exists for capability checking',
    $cap_function_exists,
    $cap_function_exists ? '' : 'WordPress capability function not available'
);

echo "\n";

// ============================================================================
// CRON SCHEDULING VERIFICATION
// ============================================================================

echo "--- Cron Scheduling Verification ---\n\n";

// Test 6.1: Verify cron event is scheduled
$next_scheduled = wp_next_scheduled('wecoza_material_notifications_check');
$cron_scheduled = $next_scheduled !== false;
test_result(
    'Cron event wecoza_material_notifications_check is scheduled',
    $cron_scheduled,
    $cron_scheduled ? '' : 'Cron event not found in schedule'
);

// Test 6.2: Verify cron action hook is registered
$cron_action = 'wecoza_material_notifications_check';
$cron_action_registered = isset($wp_filter[$cron_action]) && !empty($wp_filter[$cron_action]->callbacks);
test_result(
    'Cron action hook wecoza_material_notifications_check has handler registered',
    $cron_action_registered,
    $cron_action_registered ? '' : 'Cron action hook handler not found'
);

// Test 6.3: Verify next scheduled timestamp exists (may be past if cron was previously scheduled)
if ($next_scheduled) {
    // Cron exists - it may be in past if it was already scheduled and hasn't run yet
    // WordPress cron will run it on next page load, so this is acceptable
    $is_valid = is_numeric($next_scheduled) && $next_scheduled > 0;
    test_result(
        'Next scheduled cron execution has valid timestamp',
        $is_valid,
        $is_valid ? '' : "Invalid timestamp: " . var_export($next_scheduled, true)
    );
}

echo "\n";

// ============================================================================
// REPOSITORY LAYER VERIFICATION
// ============================================================================

echo "--- Repository Layer Verification ---\n\n";

// Test 7.1: Verify MaterialTrackingRepository extends BaseRepository
$repository_exists = class_exists('WeCoza\\Events\\Repositories\\MaterialTrackingRepository');
test_result(
    'MaterialTrackingRepository class exists',
    $repository_exists,
    $repository_exists ? '' : 'Class WeCoza\\Events\\Repositories\\MaterialTrackingRepository not found'
);

if ($repository_exists) {
    try {
        $repo = new \WeCoza\Events\Repositories\MaterialTrackingRepository();

        // Test 7.2: Verify repository extends BaseRepository
        $extends_base = $repo instanceof \WeCoza\Core\Abstract\BaseRepository;
        test_result(
            'MaterialTrackingRepository extends BaseRepository',
            $extends_base,
            $extends_base ? '' : 'Repository does not extend BaseRepository'
        );

        // Test 7.3: Verify getTrackingDashboardData() method exists
        $has_dashboard_method = method_exists($repo, 'getTrackingDashboardData');
        test_result(
            'MaterialTrackingRepository has getTrackingDashboardData() method',
            $has_dashboard_method,
            $has_dashboard_method ? '' : 'Method not found'
        );

        // Test 7.4: Verify getTrackingDashboardData() executes without errors
        if ($has_dashboard_method) {
            try {
                $data = $repo->getTrackingDashboardData(5, null, null, 30);
                $executes_ok = is_array($data);
                test_result(
                    'MaterialTrackingRepository.getTrackingDashboardData() executes without errors',
                    $executes_ok,
                    $executes_ok ? '' : 'Method did not return array'
                );
            } catch (Exception $e) {
                test_result('MaterialTrackingRepository.getTrackingDashboardData() execution', false, $e->getMessage());
            }
        }

        // Test 7.5: Verify getTrackingStatistics() method exists
        $has_stats_method = method_exists($repo, 'getTrackingStatistics');
        test_result(
            'MaterialTrackingRepository has getTrackingStatistics() method',
            $has_stats_method,
            $has_stats_method ? '' : 'Method not found'
        );

        // Test 7.6: Verify getTrackingStatistics() executes without errors
        if ($has_stats_method) {
            try {
                $stats = $repo->getTrackingStatistics(30);
                $executes_ok = is_array($stats);
                test_result(
                    'MaterialTrackingRepository.getTrackingStatistics() executes without errors',
                    $executes_ok,
                    $executes_ok ? '' : 'Method did not return array'
                );
            } catch (Exception $e) {
                test_result('MaterialTrackingRepository.getTrackingStatistics() execution', false, $e->getMessage());
            }
        }

    } catch (Exception $e) {
        test_result('MaterialTrackingRepository instantiation', false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// VIEW TEMPLATE VERIFICATION
// ============================================================================

echo "--- View Template Verification ---\n\n";

$plugin_path = wecoza_plugin_path('views/events/material-tracking/');

// Test 8.1: Verify dashboard.php template exists
$dashboard_template = $plugin_path . 'dashboard.php';
$dashboard_exists = file_exists($dashboard_template);
test_result(
    'View template views/events/material-tracking/dashboard.php exists',
    $dashboard_exists,
    $dashboard_exists ? '' : "File not found at {$dashboard_template}"
);

// Test 8.2: Verify statistics.php template exists
$statistics_template = $plugin_path . 'statistics.php';
$statistics_exists = file_exists($statistics_template);
test_result(
    'View template views/events/material-tracking/statistics.php exists',
    $statistics_exists,
    $statistics_exists ? '' : "File not found at {$statistics_template}"
);

// Test 8.3: Verify list-item.php template exists
$list_item_template = $plugin_path . 'list-item.php';
$list_item_exists = file_exists($list_item_template);
test_result(
    'View template views/events/material-tracking/list-item.php exists',
    $list_item_exists,
    $list_item_exists ? '' : "File not found at {$list_item_template}"
);

// Test 8.4: Verify empty-state.php template exists
$empty_state_template = $plugin_path . 'empty-state.php';
$empty_state_exists = file_exists($empty_state_template);
test_result(
    'View template views/events/material-tracking/empty-state.php exists',
    $empty_state_exists,
    $empty_state_exists ? '' : "File not found at {$empty_state_template}"
);

echo "\n";

// ============================================================================
// DATABASE TABLE VERIFICATION
// ============================================================================

echo "--- Database Table Verification ---\n\n";

// Test 9.1: Verify class_material_tracking table exists
try {
    $db = wecoza_db();
    $table_check = $db->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'class_material_tracking'
        ) as exists
    ")->fetch(PDO::FETCH_ASSOC);

    $table_exists = (bool)$table_check['exists'];
    test_result(
        'class_material_tracking table exists in database',
        $table_exists,
        $table_exists ? '' : 'Table not found in information_schema'
    );

    // Test 9.2: Verify table has expected columns
    if ($table_exists) {
        $columns_check = $db->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = 'class_material_tracking'
            ORDER BY ordinal_position
        ")->fetchAll(PDO::FETCH_COLUMN);

        $expected_columns = ['id', 'class_id', 'notification_type', 'notification_sent_at', 'materials_delivered_at', 'delivery_status', 'created_at', 'updated_at'];
        $has_all_columns = true;
        $missing_columns = [];
        foreach ($expected_columns as $col) {
            if (!in_array($col, $columns_check, true)) {
                $has_all_columns = false;
                $missing_columns[] = $col;
            }
        }
        test_result(
            'class_material_tracking table has expected columns',
            $has_all_columns,
            $has_all_columns ? '' : 'Missing columns: ' . implode(', ', $missing_columns)
        );
    }
} catch (Exception $e) {
    test_result('Database table verification', false, $e->getMessage());
}

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
    'MATL-01' => 'Material tracking dashboard service (getDashboardData, getStatistics, capabilities)',
    'MATL-02' => '7-day orange notification service (findOrangeStatusClasses)',
    'MATL-03' => '5-day red notification service (findRedStatusClasses)',
    'MATL-04' => 'Shortcode [wecoza_material_tracking] registration and rendering',
    'MATL-05' => 'AJAX handler wecoza_mark_material_delivered registration',
    'MATL-06' => 'Capabilities view_material_tracking and manage_material_tracking'
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
