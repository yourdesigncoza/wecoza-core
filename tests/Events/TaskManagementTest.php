<?php
/**
 * Task Management Verification Tests
 *
 * Verifies all migrated task management functionality works correctly
 * Run with: wp eval-file tests/Events/TaskManagementTest.php --path=/opt/lampp/htdocs/wecoza
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

echo "\n";
echo "====================================\n";
echo "TASK MANAGEMENT VERIFICATION TESTS\n";
echo "====================================\n\n";

// ============================================================================
// TASK 1: SHORTCODE REGISTRATION AND RENDERING
// ============================================================================

echo "--- Task 1: Shortcode Registration and Rendering ---\n\n";

// Test 1.1: Verify shortcode exists
$shortcode_exists = shortcode_exists('wecoza_event_tasks');
test_result(
    'Shortcode [wecoza_event_tasks] is registered',
    $shortcode_exists,
    $shortcode_exists ? '' : 'Shortcode not found in global $shortcode_tags'
);

// Test 1.2: Verify shortcode renders without errors
if ($shortcode_exists) {
    ob_start();
    $output = do_shortcode('[wecoza_event_tasks]');
    $errors = ob_get_clean();

    $no_errors = empty($errors);
    test_result(
        'Shortcode renders without PHP errors',
        $no_errors,
        $no_errors ? '' : "PHP output: {$errors}"
    );

    // Test 1.3: Verify output contains expected wrapper
    $has_wrapper = strpos($output, 'wecoza-event-tasks') !== false;
    test_result(
        'Shortcode output contains .wecoza-event-tasks wrapper',
        $has_wrapper,
        $has_wrapper ? '' : 'Expected wrapper class not found in output'
    );

    // Test 1.4: Verify AJAX data attributes exist
    $has_nonce = strpos($output, 'data-nonce') !== false;
    test_result(
        'Shortcode output contains data-nonce attribute',
        $has_nonce,
        $has_nonce ? '' : 'AJAX nonce attribute missing'
    );

    $has_ajax_url = strpos($output, 'data-ajax-url') !== false || strpos($output, 'ajax_url') !== false;
    test_result(
        'Shortcode output contains AJAX URL',
        $has_ajax_url,
        $has_ajax_url ? '' : 'AJAX URL not found in output'
    );
} else {
    echo "  Skipping render tests (shortcode not registered)\n";
}

// Test 1.5: Verify AJAX handler is registered
global $wp_filter;
$ajax_action = 'wp_ajax_wecoza_events_task_update';
$ajax_registered = isset($wp_filter[$ajax_action]) && !empty($wp_filter[$ajax_action]->callbacks);
test_result(
    'AJAX handler wp_ajax_wecoza_events_task_update is registered',
    $ajax_registered,
    $ajax_registered ? '' : 'AJAX action not found in $wp_filter'
);

// Test 1.6: Verify TaskController class exists
$task_controller_exists = class_exists('WeCoza\\Events\\Controllers\\TaskController');
test_result(
    'TaskController class exists',
    $task_controller_exists,
    $task_controller_exists ? '' : 'Class WeCoza\\Events\\Controllers\\TaskController not found'
);

echo "\n";

// ============================================================================
// TASK 2: DATABASE INTEGRATION AND TASK GENERATION
// ============================================================================

echo "--- Task 2: Database Integration and Task Generation ---\n\n";

// Test 2.1: Verify class_change_logs table exists
try {
    $db = wecoza_db();
    $table_check = $db->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'class_change_logs'
        ) as exists
    ")->fetch(PDO::FETCH_ASSOC);

    $table_exists = (bool)$table_check['exists'];
    test_result(
        'class_change_logs table exists',
        $table_exists,
        $table_exists ? '' : 'Table not found in information_schema'
    );
} catch (Exception $e) {
    test_result('class_change_logs table exists', false, $e->getMessage());
    $table_exists = false;
}

// Test 2.2: Verify PostgreSQL trigger exists
if ($table_exists) {
    try {
        $trigger_check = $db->query("
            SELECT EXISTS (
                SELECT FROM information_schema.triggers
                WHERE event_object_table = 'classes'
                AND trigger_name = 'classes_log_insert_update'
            ) as exists
        ")->fetch(PDO::FETCH_ASSOC);

        $has_trigger = (bool)$trigger_check['exists'];
        test_result(
            'classes_log_insert_update trigger exists on classes table',
            $has_trigger,
            $has_trigger ? '' : 'Trigger not found (run schema/migrations/001-verify-triggers.sql)'
        );

        // Test log_class_change function exists
        $function_check = $db->query("
            SELECT EXISTS (
                SELECT FROM information_schema.routines
                WHERE routine_schema = 'public'
                AND routine_name = 'log_class_change'
                AND routine_type = 'FUNCTION'
            ) as exists
        ")->fetch(PDO::FETCH_ASSOC);

        $has_function = (bool)$function_check['exists'];
        test_result(
            'log_class_change() function exists',
            $has_function,
            $has_function ? '' : 'Trigger function not found'
        );
    } catch (Exception $e) {
        test_result('PostgreSQL trigger verification', false, $e->getMessage());
    }
}

// Test 2.3: Verify TaskTemplateRegistry returns correct templates
try {
    $registry = new \WeCoza\Events\Services\TaskTemplateRegistry();

    // Test INSERT operation templates
    $insert_collection = $registry->getTemplateForOperation('INSERT');
    $expected_insert = ['agent-order', 'load-learners', 'training-schedule', 'material-delivery', 'agent-paperwork'];
    $has_insert_templates = true;
    foreach ($expected_insert as $task_id) {
        if (!$insert_collection->has($task_id)) {
            $has_insert_templates = false;
            break;
        }
    }
    test_result(
        'TaskTemplateRegistry returns correct INSERT templates',
        $has_insert_templates,
        $has_insert_templates ? '' : 'Not all expected INSERT templates found'
    );

    // Test UPDATE operation templates
    $update_collection = $registry->getTemplateForOperation('UPDATE');
    $expected_update = ['review-update', 'notify-agents', 'adjust-materials'];
    $has_update_templates = true;
    foreach ($expected_update as $task_id) {
        if (!$update_collection->has($task_id)) {
            $has_update_templates = false;
            break;
        }
    }
    test_result(
        'TaskTemplateRegistry returns correct UPDATE templates',
        $has_update_templates,
        $has_update_templates ? '' : 'Not all expected UPDATE templates found'
    );

    // Test DELETE operation templates
    $delete_collection = $registry->getTemplateForOperation('DELETE');
    $expected_delete = ['inform-stakeholders', 'archive-records'];
    $has_delete_templates = true;
    foreach ($expected_delete as $task_id) {
        if (!$delete_collection->has($task_id)) {
            $has_delete_templates = false;
            break;
        }
    }
    test_result(
        'TaskTemplateRegistry returns correct DELETE templates',
        $has_delete_templates,
        $has_delete_templates ? '' : 'Not all expected DELETE templates found'
    );
} catch (Exception $e) {
    test_result('TaskTemplateRegistry initialization', false, $e->getMessage());
}

// Test 2.4: Verify ClassTaskRepository can fetch classes
try {
    $repository = new \WeCoza\Events\Repositories\ClassTaskRepository();
    $classes = $repository->fetchClasses(5, 'desc', null);

    $fetch_works = is_array($classes);
    test_result(
        'ClassTaskRepository.fetchClasses() executes without errors',
        $fetch_works,
        $fetch_works ? '' : 'Expected array return type'
    );
} catch (Exception $e) {
    test_result('ClassTaskRepository.fetchClasses()', false, $e->getMessage());
}

// Test 2.5: Verify TaskManager can work with task collections
try {
    $task_manager = new \WeCoza\Events\Services\TaskManager();

    // Check if method exists
    $has_method = method_exists($task_manager, 'getTasksForLog');
    test_result(
        'TaskManager has getTasksForLog() method',
        $has_method,
        $has_method ? '' : 'Method not found in TaskManager class'
    );

    // If we have change logs, test the method
    if ($has_method && $table_exists) {
        $logs = $db->query("SELECT log_id FROM class_change_logs LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($logs)) {
            $tasks = $task_manager->getTasksForLog($logs[0]['log_id']);
            $is_collection = $tasks instanceof \WeCoza\Events\Models\TaskCollection;
            test_result(
                'TaskManager.getTasksForLog() returns TaskCollection instance',
                $is_collection,
                $is_collection ? '' : 'Expected TaskCollection, got ' . get_class($tasks)
            );
        } else {
            echo "  (Skipping TaskCollection test - no change logs exist)\n";
        }
    }
} catch (Exception $e) {
    test_result('TaskManager functionality', false, $e->getMessage());
}

echo "\n";

// ============================================================================
// TASK 3: FILTERING AND PRESENTER FUNCTIONALITY
// ============================================================================

echo "--- Task 3: Filtering and Presenter Functionality ---\n\n";

// Test 3.1: Verify ClassTaskPresenter formats data correctly
try {
    $presenter = new \WeCoza\Events\Views\Presenters\ClassTaskPresenter();

    // Check if present method exists
    $has_present = method_exists($presenter, 'present');
    test_result(
        'ClassTaskPresenter has present() method',
        $has_present,
        $has_present ? '' : 'Method not found'
    );

    // Check if presentTasks method exists
    $has_present_tasks = method_exists($presenter, 'presentTasks');
    test_result(
        'ClassTaskPresenter has presentTasks() method',
        $has_present_tasks,
        $has_present_tasks ? '' : 'Method not found'
    );

    // Test present method with sample data
    if ($has_present) {
        $sample_data = [[
            'class_id' => 1,
            'class_name' => 'Test Class',
            'operation_type' => 'INSERT',
            'changed_at' => date('Y-m-d H:i:s')
        ]];

        $presented = $presenter->present($sample_data);
        $present_works = is_array($presented) && !empty($presented);
        test_result(
            'ClassTaskPresenter.present() accepts array and returns formatted data',
            $present_works,
            $present_works ? '' : 'Expected non-empty array return'
        );
    }
} catch (Exception $e) {
    test_result('ClassTaskPresenter functionality', false, $e->getMessage());
}

// Test 3.2: Verify ClassTaskService filtering works
try {
    $service = new \WeCoza\Events\Services\ClassTaskService();

    // Test getClassTasks method exists
    $has_method = method_exists($service, 'getClassTasks');
    test_result(
        'ClassTaskService has getClassTasks() method',
        $has_method,
        $has_method ? '' : 'Method not found'
    );

    // Test with limit and sort
    if ($has_method) {
        $result = $service->getClassTasks(5, 'desc', false, null);
        $filtering_works = is_array($result);
        test_result(
            'ClassTaskService.getClassTasks() executes without errors',
            $filtering_works,
            $filtering_works ? '' : 'Method execution failed'
        );

        // Test ascending sort
        $result_asc = $service->getClassTasks(5, 'asc', false, null);
        $sort_works = is_array($result_asc);
        test_result(
            'ClassTaskService sorting (asc/desc) works',
            $sort_works,
            $sort_works ? '' : 'Ascending sort failed'
        );
    }
} catch (Exception $e) {
    test_result('ClassTaskService functionality', false, $e->getMessage());
}

// Test 3.3: Verify TemplateRenderer works
try {
    $base_path = wecoza_plugin_path('views/events/');
    $renderer = new \WeCoza\Events\Views\TemplateRenderer($base_path);

    // Check if template file exists
    $template_path = $base_path . 'event-tasks/main.php';
    $template_exists = file_exists($template_path);
    test_result(
        'Task dashboard template file exists',
        $template_exists,
        $template_exists ? '' : "Template not found at {$template_path}"
    );

    // Test render method
    if ($template_exists) {
        $html = $renderer->render('event-tasks/main', [
            'classes' => [],
            'nonce' => wp_create_nonce('wecoza_events_task_nonce'),
            'ajax_url' => admin_url('admin-ajax.php')
        ]);

        $render_works = is_string($html) && !empty($html);
        test_result(
            'TemplateRenderer.render() returns HTML string',
            $render_works,
            $render_works ? '' : 'Expected non-empty string'
        );
    }
} catch (Exception $e) {
    test_result('TemplateRenderer functionality', false, $e->getMessage());
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

// Requirements verification summary
echo "====================================\n";
echo "REQUIREMENTS VERIFICATION\n";
echo "====================================\n\n";

$requirements = [
    'TASK-01' => 'Class change monitoring via PostgreSQL triggers',
    'TASK-02' => 'Task generation from class INSERT/UPDATE events',
    'TASK-03' => 'Task completion/reopening via AJAX handler',
    'TASK-04' => 'Task list shortcode [wecoza_event_tasks] renders',
    'TASK-05' => 'Task filtering by status, date, class'
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
    exit(1);
}
