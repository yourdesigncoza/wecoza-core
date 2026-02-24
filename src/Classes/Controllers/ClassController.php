<?php
declare(strict_types=1);

/**
 * WeCoza Core - Class Controller
 *
 * Core controller for handling class-related operations.
 * Handles shortcode rendering and WordPress page management.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Controllers
 * @since 1.0.0
 */

namespace WeCoza\Classes\Controllers;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Abstract\BaseController;
use WeCoza\Classes\Models\ClassModel;
use WeCoza\Classes\Repositories\ClassRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ClassController extends BaseController
{
    /**
     * Initialize the controller
     */
    public function initialize(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('init', [$this, 'ensureRequiredPages']);
    }

    /**
     * Register all class-related shortcodes
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_capture_class', [$this, 'captureClassShortcode']);
        add_shortcode('wecoza_display_classes', [$this, 'displayClassesShortcode']);
        add_shortcode('wecoza_display_single_class', [$this, 'displaySingleClassShortcode']);
    }

    /**
     * Ensure required pages exist for the plugin functionality
     */
    public function ensureRequiredPages(): void
    {
        if (!current_user_can('manage_options') || get_transient('wecoza_pages_checked')) {
            return;
        }

        set_transient('wecoza_pages_checked', true, HOUR_IN_SECONDS);

        $class_details_page = get_page_by_path('app/display-single-class');

        if (!$class_details_page) {
            $app_page = get_page_by_path('app');
            $app_page_id = 0;

            if (!$app_page) {
                $app_page_id = wp_insert_post([
                    'post_title' => 'App',
                    'post_content' => '<h2>WeCoza Application</h2><p>Welcome to the WeCoza training management system.</p>',
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => 'app',
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ]);
            } else {
                $app_page_id = $app_page->ID;
            }

            if ($app_page_id && !is_wp_error($app_page_id)) {
                wp_insert_post([
                    'post_title' => 'Display Single Class',
                    'post_content' => '<h2>Class Details</h2>
<p>View detailed information about this training class.</p>

[wecoza_display_single_class]

<hr>

<div class="row mt-4">
    <div class="col-md-6">
        <a href="/app/all-classes/" class="btn btn-secondary">← Back to All Classes</a>
    </div>
    <div class="col-md-6 text-end">
        <a href="/app/update-class/?mode=update" class="btn btn-primary">Edit Class</a>
    </div>
</div>',
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => 'display-single-class',
                    'post_parent' => $app_page_id,
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ]);
            }
        }
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueueAssets(): void
    {
        if (!$this->shouldEnqueueAssets()) {
            return;
        }

        // FullCalendar CDN
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar/index.global.min.js',
            [],
            '6.1.15',
            true
        );

        wp_enqueue_script(
            'wecoza-calendar-js',
            WECOZA_CORE_URL . 'assets/js/classes/wecoza-calendar.js',
            ['jquery', 'fullcalendar'],
            WECOZA_CORE_VERSION,
            true
        );

        // Utility scripts
        wp_enqueue_script(
            'wecoza-escape-utils-js',
            WECOZA_CORE_URL . 'assets/js/classes/utils/escape.js',
            [],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-date-utils-js',
            WECOZA_CORE_URL . 'assets/js/classes/utils/date-utils.js',
            [],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-table-manager-js',
            WECOZA_CORE_URL . 'assets/js/classes/utils/table-manager.js',
            [],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-ajax-utils-js',
            WECOZA_CORE_URL . 'assets/js/classes/utils/ajax-utils.js',
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        // Plugin JavaScript files
        wp_enqueue_script(
            'wecoza-class-js',
            WECOZA_CORE_URL . 'assets/js/classes/class-capture.js',
            ['jquery', 'wecoza-escape-utils-js', 'wecoza-date-utils-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-class-schedule-form-js',
            WECOZA_CORE_URL . 'assets/js/classes/class-schedule-form.js',
            ['jquery', 'wecoza-learner-level-utils-js', 'wecoza-date-utils-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-learner-level-utils-js',
            WECOZA_CORE_URL . 'assets/js/classes/learner-level-utils.js',
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-class-types-js',
            WECOZA_CORE_URL . 'assets/js/classes/class-types.js',
            ['jquery', 'wecoza-class-js', 'wecoza-learner-level-utils-js', 'wecoza-escape-utils-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-classes-table-search-js',
            WECOZA_CORE_URL . 'assets/js/classes/classes-table-search.js',
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script(
            'wecoza-learner-selection-table-js',
            WECOZA_CORE_URL . 'assets/js/classes/learner-selection-table.js',
            ['jquery', 'wecoza-escape-utils-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_register_script(
            'wecoza-single-class-display-js',
            WECOZA_CORE_URL . 'assets/js/classes/single-class-display.js',
            ['jquery', 'wecoza-calendar-js', 'wecoza-escape-utils-js', 'wecoza-date-utils-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_register_script(
            'wecoza-attendance-capture-js',
            WECOZA_CORE_URL . 'assets/js/classes/attendance-capture.js',
            ['jquery', 'wecoza-single-class-display-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-class-js', 'wecozaClass', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wecoza_class_nonce'),
            'siteAddresses' => ClassRepository::getSiteAddresses(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'conflictCheckEnabled' => true
        ]);

        try {
            $publicHolidaysController = PublicHolidaysController::getInstance();
            $currentYear = (int) wp_date('Y');
            $nextYear = $currentYear + 1;

            $currentYearHolidays = $publicHolidaysController->getHolidaysForCalendar($currentYear);
            $nextYearHolidays = $publicHolidaysController->getHolidaysForCalendar($nextYear);
            $allHolidays = array_merge($currentYearHolidays, $nextYearHolidays);

            wp_localize_script('wecoza-class-schedule-form-js', 'wecozaPublicHolidays', [
                'events' => $allHolidays
            ]);
        } catch (\Exception $e) {
            wecoza_log('Failed to load public holidays: ' . $e->getMessage(), 'warning');
        }

        wp_localize_script('wecoza-calendar-js', 'wecozaCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wecoza_calendar_nonce'),
            'fallbackCdn' => 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js',
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }

    /**
     * Check if we should enqueue assets on current page
     */
    private function shouldEnqueueAssets(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        $shortcodes = ['wecoza_capture_class', 'wecoza_display_classes', 'wecoza_display_single_class'];

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle class capture shortcode
     */
    public function captureClassShortcode($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to access this content.</p>';
        }

        $atts = shortcode_atts([
            'redirect_url' => '',
        ], $atts);

        $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : 'create';
        $class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

        if ($mode === 'update') {
            if ($class_id <= 0) {
                return $this->handleUpdateMode($atts, null);
            }
            return $this->handleUpdateMode($atts, $class_id);
        }

        return $this->handleCreateMode($atts);
    }

    /**
     * Handle create mode logic
     */
    private function handleCreateMode(array $atts): string
    {
        $viewData = [
            'mode' => 'create',
            'class_data' => null,
            'clients' => ClassRepository::getClients(),
            'sites' => ClassRepository::getSites(),
            'agents' => ClassRepository::getAgents(),
            'supervisors' => ClassRepository::getSupervisors(),
            'learners' => ClassRepository::getLearners(),
            'setas' => ClassRepository::getSeta(),
            'class_types' => ClassRepository::getClassTypes(),
            'yes_no_options' => ClassRepository::getYesNoOptions(),
            'class_notes_options' => ClassRepository::getClassNotesOptions(),
            'redirect_url' => $atts['redirect_url']
        ];

        return wecoza_view('classes/components/class-capture-form', $viewData, true);
    }

    /**
     * Handle update mode logic
     */
    private function handleUpdateMode(array $atts, ?int $class_id): string
    {
        $class = null;
        $debug = isset($_GET['debug']) && $_GET['debug'] === '1';

        if ($class_id) {
            $class = ClassRepository::getSingleClass($class_id);

            if (empty($class)) {
                return '<div class="alert alert-subtle-danger">Class not found.</div>';
            }

            if ($debug) {
                $this->logDebugData($class_id, $class);
            }
        }

        $viewData = [
            'mode' => 'update',
            'class_data' => $class,
            'class_id' => $class_id,
            'clients' => ClassRepository::getClients(),
            'sites' => ClassRepository::getSites(),
            'agents' => ClassRepository::getAgents(),
            'supervisors' => ClassRepository::getSupervisors(),
            'learners' => ClassRepository::getLearners(),
            'setas' => ClassRepository::getSeta(),
            'class_types' => ClassRepository::getClassTypes(),
            'yes_no_options' => ClassRepository::getYesNoOptions(),
            'class_notes_options' => ClassRepository::getClassNotesOptions(),
            'redirect_url' => $atts['redirect_url']
        ];

        return wecoza_view('classes/components/class-capture-form', $viewData, true);
    }

    /**
     * Handle display classes shortcode
     */
    public function displayClassesShortcode($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to access this content.</p>';
        }

        $atts = shortcode_atts([
            'limit' => AppConstants::DEFAULT_PAGE_SIZE,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'show_loading' => true,
        ], $atts);

        try {
            $classes = ClassRepository::getAllClasses($atts);
            $classes = ClassRepository::enrichClassesWithAgentNames($classes);

            $activeClassesCount = 0;
            foreach ($classes as $class) {
                if (!$this->isClassCurrentlyStopped($class)) {
                    $activeClassesCount++;
                }
            }

            $viewData = [
                'classes' => $classes,
                'show_loading' => $atts['show_loading'],
                'total_count' => count($classes),
                'active_count' => $activeClassesCount,
                'controller' => $this
            ];

            return wecoza_view('classes/components/classes-display', $viewData, true);

        } catch (\Exception $e) {
            return '<div class="alert alert-subtle-danger">Error loading classes: ' . esc_html($e->getMessage()) . '</div>';
        }
    }

    /**
     * Handle display single class shortcode
     */
    public function displaySingleClassShortcode($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to access this content.</p>';
        }

        $atts = shortcode_atts([
            'class_id' => 0,
            'show_loading' => true,
        ], $atts);

        $class_id = $atts['class_id'] ?: (isset($_GET['class_id']) ? intval($_GET['class_id']) : 0);

        if (empty($class_id) || $class_id <= 0) {
            return '<div class="alert alert-warning">No valid class ID provided.</div>';
        }

        try {
            $class = ClassRepository::getSingleClass($class_id);

            $viewData = [
                'class' => $class,
                'show_loading' => $atts['show_loading'],
                'error_message' => ''
            ];

            if (empty($class)) {
                $viewData['error_message'] = "Class with ID {$class_id} was not found in the database.";
            }

            if (!empty($class)) {
                $this->enqueueAndLocalizeSingleClassScript($class, $atts['show_loading']);
            }

            return wecoza_view('classes/components/single-class-display', $viewData, true);

        } catch (\Exception $e) {
            return '<div class="alert alert-subtle-danger">Error loading class: ' . esc_html($e->getMessage()) . '</div>';
        }
    }

    /**
     * Enqueue and localize the single-class-display.js script with class data
     */
    private function enqueueAndLocalizeSingleClassScript(array $class, bool $showLoading): void
    {
        wp_enqueue_script('wecoza-single-class-display-js');
        wp_enqueue_script('wecoza-attendance-capture-js');

        $newClassPage = get_page_by_path('app/new-class');
        $editUrl = $newClassPage
            ? add_query_arg(['mode' => 'update', 'class_id' => $class['class_id']], get_permalink($newClassPage->ID))
            : add_query_arg(['mode' => 'update', 'class_id' => $class['class_id']], home_url('/app/new-class/'));

        $classesUrl = esc_url(home_url('/app/all-classes'));

        $notesData = $class['class_notes_data'] ?? [];
        if (is_string($notesData)) {
            $notesData = json_decode($notesData, true) ?: [];
        }

        $learnerIds = $class['learner_ids'] ?? [];
        if (is_string($learnerIds)) {
            $learnerIds = json_decode($learnerIds, true) ?: [];
        }

        // Compute class status once — used for both classStatus and isAttendanceLocked (avoids double-call).
        $classStatus = wecoza_resolve_class_status($class);

        wp_localize_script('wecoza-single-class-display-js', 'WeCozaSingleClass', [
            'classId' => $class['class_id'] ?? null,
            'classCode' => $class['class_code'] ?? '',
            'classSubject' => $class['class_subject'] ?? '',
            'startDate' => $class['original_start_date'] ?? '',
            'deliveryDate' => $class['delivery_date'] ?? '',
            'duration' => $class['class_duration'] ?? '',
            'scheduleData' => $class['schedule_data'] ?? null,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'classesUrl' => $classesUrl,
            'editUrl' => esc_url_raw($editUrl),
            'calendarNonce' => wp_create_nonce('wecoza_calendar_nonce'),
            'classNonce' => wp_create_nonce('wecoza_class_nonce'),
            'attendanceNonce' => wp_create_nonce('wecoza_attendance_nonce'),
            'classStatus' => $classStatus,
            'isAttendanceLocked' => $classStatus !== 'active',
            'orderNr' => $class['order_nr'] ?? '',
            'canEdit' => current_user_can('edit_posts') || current_user_can('manage_options'),
            'isAdmin' => current_user_can('manage_options'),
            'showLoading' => $showLoading,
            'notesData' => $notesData,
            'learnerIds' => $learnerIds,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }

    /**
     * Check if a class is currently stopped based on stop_restart_dates
     */
    public function isClassCurrentlyStopped(array $class): bool
    {
        if (empty($class['stop_restart_dates'])) {
            return false;
        }

        $stopRestartDates = is_string($class['stop_restart_dates'])
            ? json_decode($class['stop_restart_dates'], true)
            : $class['stop_restart_dates'];

        if (!is_array($stopRestartDates) || empty($stopRestartDates)) {
            return false;
        }

        $currentDate = wp_date('Y-m-d');

        foreach ($stopRestartDates as $period) {
            if (!isset($period['stop_date']) || !isset($period['restart_date'])) {
                continue;
            }

            $stopDate = $period['stop_date'];
            $restartDate = $period['restart_date'];

            if ($currentDate >= $stopDate && $currentDate <= $restartDate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log debug data for class updates (development only)
     */
    private function logDebugData(int $class_id, array $class_data): void
    {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wecoza-logs/update-form/' . wp_date('Y-m-d');

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $timestamp = wp_date('H-i-s');
        $log_file = $log_dir . '/' . $timestamp . '-class-' . $class_id . '-data.json';

        $debug_data = [
            'timestamp' => current_time('mysql'),
            'class_id' => $class_id,
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'class_data' => $class_data,
            'field_analysis' => [
                'client_id' => [
                    'value' => $class_data['client_id'] ?? null,
                    'type' => gettype($class_data['client_id'] ?? null),
                    'exists' => isset($class_data['client_id'])
                ],
                'site_id' => [
                    'value' => $class_data['site_id'] ?? null,
                    'type' => gettype($class_data['site_id'] ?? null),
                    'exists' => isset($class_data['site_id'])
                ],
                'class_type' => [
                    'value' => $class_data['class_type'] ?? null,
                    'type' => gettype($class_data['class_type'] ?? null),
                    'exists' => isset($class_data['class_type'])
                ],
                'seta_funded' => [
                    'value' => $class_data['seta_funded'] ?? null,
                    'type' => gettype($class_data['seta_funded'] ?? null),
                    'exists' => isset($class_data['seta_funded'])
                ],
                'exam_class' => [
                    'value' => $class_data['exam_class'] ?? null,
                    'type' => gettype($class_data['exam_class'] ?? null),
                    'exists' => isset($class_data['exam_class'])
                ],
                'schedule_data' => [
                    'exists' => isset($class_data['schedule_data']),
                    'is_array' => is_array($class_data['schedule_data'] ?? null),
                    'keys' => array_keys($class_data['schedule_data'] ?? [])
                ]
            ]
        ];

        file_put_contents($log_file, json_encode($debug_data, JSON_PRETTY_PRINT));

        $summary_file = $log_dir . '/' . $timestamp . '-class-' . $class_id . '-summary.log';
        $summary = "Update Form Debug Log\n";
        $summary .= "=====================\n";
        $summary .= "Timestamp: " . current_time('mysql') . "\n";
        $summary .= "Class ID: $class_id\n";
        $summary .= "User ID: " . get_current_user_id() . "\n\n";
        $summary .= "Field Population Status:\n";

        foreach ($debug_data['field_analysis'] as $field => $info) {
            $status = $info['exists'] ? '✓' : '✗';
            $summary .= "$status $field: ";
            if ($field === 'schedule_data') {
                $summary .= $info['exists'] ? 'Present' : 'Missing';
            } else {
                $summary .= $info['exists'] ? $info['value'] . ' (' . $info['type'] . ')' : 'NOT SET';
            }
            $summary .= "\n";
        }

        file_put_contents($summary_file, $summary);
    }
}
