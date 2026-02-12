<?php
/**
 * Plugin Name: WeCoza Core
 * Plugin URI: https://yourdesign.co.za/
 * Description: Core infrastructure for WeCoza plugins - shared database, models, controllers, and utilities.
 * Version: 1.0.0
 * Author: YourDesign.co.za
 * Author URI: https://yourdesign.co.za/
 * Text Domain: wecoza-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: Proprietary
 *
 * @package WeCoza\Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Plugin Constants
|--------------------------------------------------------------------------
*/

define('WECOZA_CORE_VERSION', '1.0.0');
define('WECOZA_CORE_PATH', plugin_dir_path(__FILE__));
define('WECOZA_CORE_DIR', plugin_dir_path(__FILE__)); // Alias for WECOZA_CORE_PATH
define('WECOZA_CORE_URL', plugin_dir_url(__FILE__));
define('WECOZA_CORE_BASENAME', plugin_basename(__FILE__));
define('WECOZA_CORE_FILE', __FILE__);

/*
|--------------------------------------------------------------------------
| PSR-4 Autoloader
|--------------------------------------------------------------------------
|
| Register autoloaders for all WeCoza namespaces. This allows classes to be
| loaded automatically when first used, without manual require statements.
|
*/

spl_autoload_register(function (string $class) {
    // Namespace to directory mapping
    $namespaces = [
        'WeCoza\\Core\\' => WECOZA_CORE_PATH . 'core/',
        'WeCoza\\Learners\\' => WECOZA_CORE_PATH . 'src/Learners/',
        'WeCoza\\Classes\\' => WECOZA_CORE_PATH . 'src/Classes/',
        'WeCoza\\Events\\' => WECOZA_CORE_PATH . 'src/Events/',
        'WeCoza\\Clients\\' => WECOZA_CORE_PATH . 'src/Clients/',
        'WeCoza\\Agents\\' => WECOZA_CORE_PATH . 'src/Agents/',
        'WeCoza\\Settings\\' => WECOZA_CORE_PATH . 'src/Settings/',
        'WeCoza\\ShortcodeInspector\\' => WECOZA_CORE_PATH . 'src/ShortcodeInspector/',
    ];

    foreach ($namespaces as $prefix => $baseDir) {
        // Check if class uses this namespace
        $prefixLength = strlen($prefix);
        if (strncmp($prefix, $class, $prefixLength) !== 0) {
            continue;
        }

        // Get relative class name
        $relativeClass = substr($class, $prefixLength);

        // Convert namespace separators to directory separators
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        // Require file if it exists
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/*
|--------------------------------------------------------------------------
| Load Helper Functions
|--------------------------------------------------------------------------
|
| Load global helper functions that are available throughout the plugin
| and to any dependent plugins/themes.
|
*/

require_once WECOZA_CORE_PATH . 'core/Helpers/functions.php';

/*
|--------------------------------------------------------------------------
| Load Action Scheduler
|--------------------------------------------------------------------------
|
| Action Scheduler must be loaded before plugins_loaded hook for proper
| initialization. Used for async email/AI processing in notifications.
|
*/

$action_scheduler_path = WECOZA_CORE_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if (file_exists($action_scheduler_path)) {
    require_once $action_scheduler_path;
}

/*
|--------------------------------------------------------------------------
| Frontend Asset Enqueue
|--------------------------------------------------------------------------
*/

add_action('wp_enqueue_scripts', function () {
    // Enqueue Learners CSS
    wp_enqueue_style(
        'wecoza-learners-style',
        WECOZA_CORE_URL . 'assets/css/learners-style.css',
        [],
        WECOZA_CORE_VERSION
    );

    // Enqueue global Learners JavaScript (handles initials generation, delete confirmations, etc.)
    wp_enqueue_script(
        'wecoza-learners-app',
        WECOZA_CORE_URL . 'assets/js/learners/learners-app.js',
        ['jquery'],
        WECOZA_CORE_VERSION,
        true
    );

    // Localize script
    wp_localize_script('wecoza-learners-app', 'WeCozaLearners', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('learners_nonce_action'),
        'plugin_url' => WECOZA_CORE_URL,
        'uploads_url' => wp_upload_dir()['baseurl'],
        'home_url' => home_url(),
        'display_learners_url' => home_url('app/all-learners'),
        'view_learner_url' => home_url('app/view-learner'),
        'update_learner_url' => home_url('app/update-learners')
    ]);

    // Localize notifications data for dashboard AJAX
    wp_localize_script('wecoza-learners-app', 'WeCozaNotifications', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wecoza_notifications_nonce'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Plugin Initialization
|--------------------------------------------------------------------------
|
| Initialize the plugin on 'plugins_loaded' hook with priority 5.
| This ensures WeCoza Core is loaded before dependent plugins (which
| should use priority 10 or higher).
|
*/

add_action('plugins_loaded', function () {
    // Load configuration
    $config = wecoza_config('app');

    // Initialize text domain for translations
    load_plugin_textdomain(
        'wecoza-core',
        false,
        dirname(WECOZA_CORE_BASENAME) . '/languages'
    );

    /**
     * Fires when WeCoza Core is fully loaded and ready.
     *
     * Dependent plugins should hook into this action to ensure
     * WeCoza Core classes and functions are available.
     *
     * @since 1.0.0
     */
    do_action('wecoza_core_loaded');

    /*
    |--------------------------------------------------------------------------
    | Initialize Modules
    |--------------------------------------------------------------------------
    */

    // Initialize Learners Module
    if (class_exists(\WeCoza\Learners\Controllers\LearnerController::class)) {
        new \WeCoza\Learners\Controllers\LearnerController();
    }

    // Initialize Classes Module
    if (class_exists(\WeCoza\Classes\Controllers\ClassController::class)) {
        $classController = new \WeCoza\Classes\Controllers\ClassController();
        $classController->initialize();
    }

    if (class_exists(\WeCoza\Classes\Controllers\ClassAjaxController::class)) {
        $classAjaxController = new \WeCoza\Classes\Controllers\ClassAjaxController();
        $classAjaxController->initialize();
    }

    if (class_exists(\WeCoza\Classes\Controllers\QAController::class)) {
        $qaController = new \WeCoza\Classes\Controllers\QAController();
        $qaController->initialize();
    }

    if (class_exists(\WeCoza\Classes\Controllers\PublicHolidaysController::class)) {
        $publicHolidaysController = \WeCoza\Classes\Controllers\PublicHolidaysController::getInstance();
        $publicHolidaysController->initialize();
    }

    // Initialize Events Module
    if (class_exists(\WeCoza\Events\Shortcodes\EventTasksShortcode::class)) {
        \WeCoza\Events\Shortcodes\EventTasksShortcode::register();
    }
    if (class_exists(\WeCoza\Events\Shortcodes\MaterialTrackingShortcode::class)) {
        \WeCoza\Events\Shortcodes\MaterialTrackingShortcode::register();
    }
    // AI Summary shortcode (re-enabled for class_events table in Phase 18)
    if (class_exists(\WeCoza\Events\Shortcodes\AISummaryShortcode::class)) {
        \WeCoza\Events\Shortcodes\AISummaryShortcode::register();
    }
    if (class_exists(\WeCoza\Events\Controllers\TaskController::class)) {
        \WeCoza\Events\Controllers\TaskController::register();
    }
    if (class_exists(\WeCoza\Events\Controllers\MaterialTrackingController::class)) {
        \WeCoza\Events\Controllers\MaterialTrackingController::register();
    }
    if (class_exists(\WeCoza\Events\Admin\SettingsPage::class)) {
        \WeCoza\Events\Admin\SettingsPage::register();
    }

    // Initialize Settings Module (WeCoza Settings page under Settings menu)
    if (class_exists(\WeCoza\Settings\SettingsPage::class)) {
        \WeCoza\Settings\SettingsPage::register();
    }

    // Initialize Shortcode Inspector (Tools > WeCoza Shortcodes)
    if (class_exists(\WeCoza\ShortcodeInspector\ShortcodeInspector::class)) {
        \WeCoza\ShortcodeInspector\ShortcodeInspector::register();
    }

    // Initialize Clients Module
    if (class_exists(\WeCoza\Clients\Controllers\ClientsController::class)) {
        new \WeCoza\Clients\Controllers\ClientsController();
    }
    if (class_exists(\WeCoza\Clients\Controllers\LocationsController::class)) {
        new \WeCoza\Clients\Controllers\LocationsController();
    }
    if (class_exists(\WeCoza\Clients\Ajax\ClientAjaxHandlers::class)) {
        new \WeCoza\Clients\Ajax\ClientAjaxHandlers();
    }

    // Initialize Agents Module
    if (class_exists(\WeCoza\Agents\Controllers\AgentsController::class)) {
        new \WeCoza\Agents\Controllers\AgentsController();
    }
    if (class_exists(\WeCoza\Agents\Ajax\AgentsAjaxHandlers::class)) {
        new \WeCoza\Agents\Ajax\AgentsAjaxHandlers();
    }

    // Action Scheduler Performance Tuning
    add_filter('action_scheduler_queue_runner_time_limit', function () {
        return 60;  // 60 seconds (default is 30)
    });

    add_filter('action_scheduler_queue_runner_batch_size', function () {
        return 50;  // Match NotificationProcessor BATCH_LIMIT
    });

    // Material Notification Cron Handler
    add_action('wecoza_material_notifications_check', function () {
        if (!class_exists(\WeCoza\Events\Services\MaterialNotificationService::class)) {
            return;
        }

        $service = new \WeCoza\Events\Services\MaterialNotificationService();

        // Check and send 7-day (orange) notifications
        $orangeClasses = $service->findOrangeStatusClasses();
        if (!empty($orangeClasses)) {
            $sentOrange = $service->sendMaterialNotifications($orangeClasses, 'orange');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WeCoza Material Cron: Sent {$sentOrange} orange (7-day) notifications");
            }
        }

        // Check and send 5-day (red) notifications
        $redClasses = $service->findRedStatusClasses();
        if (!empty($redClasses)) {
            $sentRed = $service->sendMaterialNotifications($redClasses, 'red');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WeCoza Material Cron: Sent {$sentRed} red (5-day) notifications");
            }
        }
    });

    // Notification Processor Cron Handler (re-enabled for class_events table in Phase 18)
    add_action('wecoza_process_notifications', function () {
        if (!class_exists(\WeCoza\Events\Services\NotificationProcessor::class)) {
            return;
        }

        $processor = \WeCoza\Events\Services\NotificationProcessor::boot();
        $processor->process();
    });

    // Action Scheduler Job Handlers for Async Notifications (re-enabled for class_events table in Phase 18)
    add_action('wecoza_process_event', function (int $eventId) {
        if (!class_exists(\WeCoza\Events\Services\NotificationEnricher::class)) {
            return;
        }

        $enricher = \WeCoza\Events\Services\NotificationEnricher::boot();
        $result = $enricher->enrich($eventId);

        if ($result['success'] && $result['should_email']) {
            // Schedule email for each recipient
            $recipients = $result['recipients'] ?? [];
            foreach ($recipients as $recipient) {
                as_enqueue_async_action(
                    'wecoza_send_notification_email',
                    [
                        'event_id' => $eventId,
                        'recipient' => $recipient,
                        'email_context' => $result['email_context'],
                    ],
                    'wecoza-notifications'
                );
            }
        }
    }, 10, 1);

    add_action('wecoza_send_notification_email', function (int $eventId, string $recipient, array $emailContext = []) {
        if (!class_exists(\WeCoza\Events\Services\NotificationEmailer::class)) {
            return;
        }

        $emailer = \WeCoza\Events\Services\NotificationEmailer::boot();
        $emailer->send($eventId, $recipient, $emailContext);
    }, 10, 3);

    /*
    |--------------------------------------------------------------------------
    | Notification Dashboard AJAX Handlers
    |--------------------------------------------------------------------------
    |
    | AJAX handlers for marking notifications as viewed/acknowledged from
    | the notification dashboard UI.
    |
    */

    // Mark notification as viewed
    add_action('wp_ajax_wecoza_mark_notification_viewed', function () {
        check_ajax_referer('wecoza_notifications_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $eventId = absint($_POST['event_id'] ?? 0);
        if ($eventId <= 0) {
            wp_send_json_error(['message' => 'Invalid event ID'], 400);
        }

        if (!class_exists(\WeCoza\Events\Services\NotificationDashboardService::class)) {
            wp_send_json_error(['message' => 'Dashboard service not available'], 500);
        }

        $service = \WeCoza\Events\Services\NotificationDashboardService::boot();
        $success = $service->markAsViewed($eventId);

        if ($success) {
            wp_send_json_success(['message' => 'Marked as viewed']);
        } else {
            wp_send_json_error(['message' => 'Failed to update'], 500);
        }
    });

    // Mark notification as acknowledged
    add_action('wp_ajax_wecoza_mark_notification_acknowledged', function () {
        check_ajax_referer('wecoza_notifications_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $eventId = absint($_POST['event_id'] ?? 0);
        if ($eventId <= 0) {
            wp_send_json_error(['message' => 'Invalid event ID'], 400);
        }

        if (!class_exists(\WeCoza\Events\Services\NotificationDashboardService::class)) {
            wp_send_json_error(['message' => 'Dashboard service not available'], 500);
        }

        $service = \WeCoza\Events\Services\NotificationDashboardService::boot();
        $success = $service->markAsAcknowledged($eventId);

        if ($success) {
            wp_send_json_success(['message' => 'Acknowledged']);
        } else {
            wp_send_json_error(['message' => 'Failed to update'], 500);
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Load Learners Shortcodes
    |--------------------------------------------------------------------------
    |
    | Full-featured shortcodes for learner management forms and displays.
    | These extend the basic MVC shortcodes in LearnerController.
    |
    */

    // Learners display shortcode [wecoza_display_learners]
    require_once WECOZA_CORE_PATH . 'src/Learners/Shortcodes/learners-display-shortcode.php';

    // Learners capture form shortcode [wecoza_learners_form]
    require_once WECOZA_CORE_PATH . 'src/Learners/Shortcodes/learners-capture-shortcode.php';

    // Single learner display shortcode [wecoza_single_learner_display]
    require_once WECOZA_CORE_PATH . 'src/Learners/Shortcodes/learner-single-display-shortcode.php';

    // Learners update form shortcode [wecoza_learners_update_form]
    require_once WECOZA_CORE_PATH . 'src/Learners/Shortcodes/learners-update-shortcode.php';

    /*
    |--------------------------------------------------------------------------
    | Load Learners AJAX Handlers
    |--------------------------------------------------------------------------
    |
    | AJAX handlers using legacy action names for backward compatibility
    | with existing JavaScript files.
    |
    */

    require_once WECOZA_CORE_PATH . 'src/Learners/Ajax/LearnerAjaxHandlers.php';

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Uncomment for debugging:
        // error_log('WeCoza Core: Plugin loaded successfully');
    }
}, 5);

/*
|--------------------------------------------------------------------------
| Activation & Deactivation Hooks
|--------------------------------------------------------------------------
*/

register_activation_hook(__FILE__, function () {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('WeCoza Core requires PHP 8.0 or higher.', 'wecoza-core'),
            __('Plugin Activation Error', 'wecoza-core'),
            ['back_link' => true]
        );
    }

    // Check for PDO PostgreSQL extension
    if (!extension_loaded('pdo_pgsql')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('WeCoza Core requires the PDO PostgreSQL extension (pdo_pgsql).', 'wecoza-core'),
            __('Plugin Activation Error', 'wecoza-core'),
            ['back_link' => true]
        );
    }

    // Set default options if not already set
    $defaults = [
        'wecoza_core_version' => WECOZA_CORE_VERSION,
        'wecoza_core_activated' => current_time('mysql'),
    ];

    foreach ($defaults as $option => $value) {
        if (get_option($option) === false) {
            add_option($option, $value);
        }
    }

    // Flush rewrite rules
    flush_rewrite_rules();

    // Register custom capabilities for learner management
    // Only Administrators should have access to PII data
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_learners');
        $admin->add_cap('view_material_tracking');
        $admin->add_cap('manage_material_tracking');

        // Clients module capabilities
        $admin->add_cap('manage_wecoza_clients');
        $admin->add_cap('view_wecoza_clients');
        $admin->add_cap('edit_wecoza_clients');
        $admin->add_cap('delete_wecoza_clients');
        $admin->add_cap('export_wecoza_clients');
    }

    // Schedule material notification cron if not already scheduled
    if (!wp_next_scheduled('wecoza_material_notifications_check')) {
        wp_schedule_event(time(), 'daily', 'wecoza_material_notifications_check');
    }

    // Unschedule legacy email notification cron (replaced by wecoza_process_notifications)
    $legacyTimestamp = wp_next_scheduled('wecoza_email_notifications_process');
    if ($legacyTimestamp) {
        wp_unschedule_event($legacyTimestamp, 'wecoza_email_notifications_process');
    }

    // Schedule notification processor cron (re-enabled for class_events table in Phase 18)
    if (!wp_next_scheduled('wecoza_process_notifications')) {
        wp_schedule_event(time(), 'hourly', 'wecoza_process_notifications');
    }

    /**
     * Fires when WeCoza Core is activated.
     *
     * @since 1.0.0
     */
    do_action('wecoza_core_activated');
});

register_deactivation_hook(__FILE__, function () {
    // Flush rewrite rules
    flush_rewrite_rules();

    // Remove custom capabilities
    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap('manage_learners');
        $admin->remove_cap('view_material_tracking');
        $admin->remove_cap('manage_material_tracking');

        // Remove Clients module capabilities
        $admin->remove_cap('manage_wecoza_clients');
        $admin->remove_cap('view_wecoza_clients');
        $admin->remove_cap('edit_wecoza_clients');
        $admin->remove_cap('delete_wecoza_clients');
        $admin->remove_cap('export_wecoza_clients');
    }

    // Unschedule material notification cron
    $timestamp = wp_next_scheduled('wecoza_material_notifications_check');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wecoza_material_notifications_check');
    }

    // Unschedule notification processor cron
    $notificationTimestamp = wp_next_scheduled('wecoza_process_notifications');
    if ($notificationTimestamp) {
        wp_unschedule_event($notificationTimestamp, 'wecoza_process_notifications');
    }

    // Unschedule legacy email notification cron (if any lingering)
    $legacyEmailTimestamp = wp_next_scheduled('wecoza_email_notifications_process');
    if ($legacyEmailTimestamp) {
        wp_unschedule_event($legacyEmailTimestamp, 'wecoza_email_notifications_process');
    }

    /**
     * Fires when WeCoza Core is deactivated.
     *
     * @since 1.0.0
     */
    do_action('wecoza_core_deactivated');
});

/*
|--------------------------------------------------------------------------
| Admin Notices
|--------------------------------------------------------------------------
*/

add_action('admin_notices', function () {
    // Check if PostgreSQL password is configured
    if (empty(get_option('wecoza_postgres_password', ''))) {
        // Only show on plugins page or WeCoza pages
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'plugins' || strpos($screen->id, 'wecoza') !== false)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('WeCoza Core:', 'wecoza-core') . '</strong> ';
            echo esc_html__('PostgreSQL password not configured. Please set the wecoza_postgres_password option.', 'wecoza-core');
            echo '</p></div>';
        }
    }
});

/*
|--------------------------------------------------------------------------
| CLI Commands (WP-CLI Support)
|--------------------------------------------------------------------------
*/

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('wecoza', function ($args, $assocArgs) {
        // Test database connection
        if (isset($args[0]) && $args[0] === 'test-db') {
            $db = \WeCoza\Core\Database\PostgresConnection::getInstance();

            if ($db->testConnection()) {
                WP_CLI::success('PostgreSQL connection successful!');
                WP_CLI::log('Version: ' . $db->getVersion());
            } else {
                WP_CLI::error('PostgreSQL connection failed. Check your credentials.');
            }
            return;
        }

        // Show version
        if (isset($args[0]) && $args[0] === 'version') {
            WP_CLI::log('WeCoza Core version: ' . WECOZA_CORE_VERSION);
            return;
        }

        // Default: show help
        WP_CLI::log('WeCoza Core CLI Commands:');
        WP_CLI::log('  wp wecoza test-db    - Test PostgreSQL connection');
        WP_CLI::log('  wp wecoza version    - Show plugin version');
    });

    // AI Summary CLI command (re-enabled for class_events table in Phase 18)
    if (class_exists(\WeCoza\Events\CLI\AISummaryStatusCommand::class)) {
        \WeCoza\Events\CLI\AISummaryStatusCommand::register();
    }
}
