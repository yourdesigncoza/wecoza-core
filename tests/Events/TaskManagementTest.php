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
// TASK 2: EVENT-BASED TASK BUILDING
// ============================================================================

echo "--- Task 2: Event-Based Task Building ---\n\n";

// Test 2.1: Verify TaskManager::buildTasksFromEvents() method exists
try {
    $task_manager = new \WeCoza\Events\Services\TaskManager();

    $has_method = method_exists($task_manager, 'buildTasksFromEvents');
    test_result(
        'TaskManager has buildTasksFromEvents() method',
        $has_method,
        $has_method ? '' : 'Method not found in TaskManager class'
    );

    // Test 2.2: Test buildTasksFromEvents with sample class data (no events)
    if ($has_method) {
        $sample_class = [
            'class_id' => 999,
            'order_nr' => null,
            'event_dates' => null
        ];

        $tasks = $task_manager->buildTasksFromEvents($sample_class);
        $is_collection = $tasks instanceof \WeCoza\Events\Models\TaskCollection;
        test_result(
            'buildTasksFromEvents returns TaskCollection',
            $is_collection,
            $is_collection ? '' : 'Expected TaskCollection, got ' . gettype($tasks)
        );

        // Test 2.3: Agent Order Number always present
        $has_agent_order = $tasks->has('agent-order');
        test_result(
            'Agent Order Number task always present',
            $has_agent_order,
            $has_agent_order ? '' : 'agent-order task not found'
        );

        // Test 2.4: Agent Order status when order_nr is empty
        if ($has_agent_order) {
            $agent_order_task = $tasks->get('agent-order');
            $is_open = !$agent_order_task->isCompleted();
            test_result(
                'Agent Order is open when order_nr is null/empty',
                $is_open,
                $is_open ? '' : 'Expected task to be open'
            );
        }

        // Test 2.5: Agent Order status when order_nr is set
        $sample_class_with_order = [
            'class_id' => 999,
            'order_nr' => 'ABC-123',
            'event_dates' => null
        ];
        $tasks_with_order = $task_manager->buildTasksFromEvents($sample_class_with_order);
        $agent_order_task = $tasks_with_order->get('agent-order');
        $is_complete = $agent_order_task->isCompleted();
        test_result(
            'Agent Order is completed when order_nr has value',
            $is_complete,
            $is_complete ? '' : 'Expected task to be completed'
        );

        // Test 2.6: Event tasks from event_dates JSONB
        $sample_class_with_events = [
            'class_id' => 999,
            'order_nr' => null,
            'event_dates' => json_encode([
                ['type' => 'Training', 'description' => 'Week 1', 'status' => 'Pending'],
                ['type' => 'Assessment', 'description' => '', 'status' => 'Completed']
            ])
        ];
        $tasks_with_events = $task_manager->buildTasksFromEvents($sample_class_with_events);

        $has_event_0 = $tasks_with_events->has('event-0');
        $has_event_1 = $tasks_with_events->has('event-1');
        test_result(
            'Event tasks have IDs event-{index}',
            $has_event_0 && $has_event_1,
            ($has_event_0 && $has_event_1) ? '' : 'Expected event-0 and event-1 tasks'
        );

        // Test 2.7: Event task labels
        if ($has_event_0 && $has_event_1) {
            $event_0 = $tasks_with_events->get('event-0');
            $event_1 = $tasks_with_events->get('event-1');

            $label_0_correct = $event_0->getLabel() === 'Training: Week 1';
            $label_1_correct = $event_1->getLabel() === 'Assessment';  // No description, just type
            test_result(
                'Event task labels formatted as {type}: {description} or {type}',
                $label_0_correct && $label_1_correct,
                ($label_0_correct && $label_1_correct) ? '' : 'Label format incorrect: ' . $event_0->getLabel() . ', ' . $event_1->getLabel()
            );

            // Test 2.8: Event task status derived from event status
            $event_0_open = !$event_0->isCompleted();
            $event_1_complete = $event_1->isCompleted();
            test_result(
                'Event task status derived from event status field',
                $event_0_open && $event_1_complete,
                ($event_0_open && $event_1_complete) ? '' : 'Status derivation incorrect'
            );
        }
    }
} catch (Exception $e) {
    test_result('TaskManager buildTasksFromEvents', false, $e->getMessage());
}

echo "\n";

// ============================================================================
// TASK 3: REPOSITORY AND SERVICE INTEGRATION
// ============================================================================

echo "--- Task 3: Repository and Service Integration ---\n\n";

// Test 3.1: Verify ClassTaskRepository.fetchClasses() returns event_dates field
try {
    $repository = new \WeCoza\Events\Repositories\ClassTaskRepository();
    $classes = $repository->fetchClasses(5, 'desc', null);

    $fetch_works = is_array($classes);
    test_result(
        'ClassTaskRepository.fetchClasses() executes without errors',
        $fetch_works,
        $fetch_works ? '' : 'Expected array return type'
    );

    if ($fetch_works && !empty($classes)) {
        $first = $classes[0];

        // Test 3.2: Has event_dates field
        $has_event_dates = array_key_exists('event_dates', $first);
        test_result(
            'ClassTaskRepository returns event_dates field',
            $has_event_dates,
            $has_event_dates ? '' : 'event_dates field missing'
        );

        // Test 3.3: Has order_nr field
        $has_order_nr = array_key_exists('order_nr', $first);
        test_result(
            'ClassTaskRepository returns order_nr field',
            $has_order_nr,
            $has_order_nr ? '' : 'order_nr field missing'
        );

        // Test 3.4: No log_id field (removed)
        $no_log_id = !isset($first['log_id']);
        test_result(
            'ClassTaskRepository does NOT return log_id field',
            $no_log_id,
            $no_log_id ? '' : 'log_id field should be removed'
        );
    }
} catch (Exception $e) {
    test_result('ClassTaskRepository.fetchClasses()', false, $e->getMessage());
}

// Test 3.5: Verify ClassTaskService.getClassTasks() returns items without log_id
try {
    $service = new \WeCoza\Events\Services\ClassTaskService();
    $result = $service->getClassTasks(5, 'desc', false, null);

    $service_works = is_array($result);
    test_result(
        'ClassTaskService.getClassTasks() executes without errors',
        $service_works,
        $service_works ? '' : 'Method execution failed'
    );

    if ($service_works && !empty($result)) {
        $first = $result[0];

        // Test 3.6: Has class_id
        $has_class_id = isset($first['class_id']);
        test_result(
            'ClassTaskService returns class_id in result',
            $has_class_id,
            $has_class_id ? '' : 'class_id missing'
        );

        // Test 3.7: No log_id
        $no_log_id = !isset($first['log_id']);
        test_result(
            'ClassTaskService does NOT return log_id in result',
            $no_log_id,
            $no_log_id ? '' : 'log_id should be removed'
        );

        // Test 3.8: manageable is always true
        $always_manageable = $first['manageable'] === true;
        test_result(
            'All classes are manageable (manageable=true always)',
            $always_manageable,
            $always_manageable ? '' : 'manageable should be true'
        );

        // Test 3.9: tasks is TaskCollection
        $is_collection = $first['tasks'] instanceof \WeCoza\Events\Models\TaskCollection;
        test_result(
            'ClassTaskService returns TaskCollection in tasks field',
            $is_collection,
            $is_collection ? '' : 'Expected TaskCollection'
        );
    }
} catch (Exception $e) {
    test_result('ClassTaskService.getClassTasks()', false, $e->getMessage());
}

echo "\n";

// ============================================================================
// TASK 4: FILTERING AND PRESENTER FUNCTIONALITY
// ============================================================================

echo "--- Task 4: Filtering and Presenter Functionality ---\n\n";

// Test 4.1: Verify ClassTaskPresenter formats data correctly
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

// Test 4.2: Verify TemplateRenderer works
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
    'TASK-01' => 'TaskManager builds tasks from event_dates JSONB',
    'TASK-02' => 'Agent Order Number always present with order_nr status',
    'TASK-03' => 'Task IDs: agent-order and event-{index}',
    'TASK-04' => 'Task labels: {type}: {description} or {type}',
    'REPO-01' => 'ClassTaskRepository queries classes directly',
    'REPO-02' => 'ClassTaskService uses buildTasksFromEvents()'
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
