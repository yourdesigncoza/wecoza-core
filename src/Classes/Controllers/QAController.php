<?php
/**
 * WeCoza Core - QA Controller
 *
 * Controller for handling QA analytics dashboard and widget operations.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Controllers
 * @since 1.0.0
 */

namespace WeCoza\Classes\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Classes\Models\QAModel;
use WeCoza\Classes\Models\QAVisitModel;
use WeCoza\Classes\Repositories\ClassRepository;

if (!defined('ABSPATH')) {
    exit;
}

class QAController extends BaseController
{
    /**
     * Initialize the controller
     */
    public function initialize(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // QA Analytics AJAX handlers
        add_action('wp_ajax_get_qa_analytics', [__CLASS__, 'getQAAnalytics']);
        add_action('wp_ajax_nopriv_get_qa_analytics', [__CLASS__, 'getQAAnalytics']);
        add_action('wp_ajax_get_qa_summary', [__CLASS__, 'getQASummary']);
        add_action('wp_ajax_nopriv_get_qa_summary', [__CLASS__, 'getQASummary']);
        add_action('wp_ajax_get_qa_visits', [__CLASS__, 'getQAVisits']);
        add_action('wp_ajax_nopriv_get_qa_visits', [__CLASS__, 'getQAVisits']);
        add_action('wp_ajax_create_qa_visit', [__CLASS__, 'createQAVisit']);
        add_action('wp_ajax_nopriv_create_qa_visit', [__CLASS__, 'createQAVisit']);
        add_action('wp_ajax_export_qa_reports', [__CLASS__, 'exportQAReports']);
        add_action('wp_ajax_nopriv_export_qa_reports', [__CLASS__, 'exportQAReports']);

        // QA Operations AJAX handlers
        add_action('wp_ajax_delete_qa_report', [$this, 'deleteQAReport']);
        add_action('wp_ajax_nopriv_delete_qa_report', [$this, 'deleteQAReport']);
        add_action('wp_ajax_get_class_qa_data', [__CLASS__, 'getClassQAData']);
        add_action('wp_ajax_nopriv_get_class_qa_data', [__CLASS__, 'getClassQAData']);
        add_action('wp_ajax_submit_qa_question', [__CLASS__, 'submitQAQuestion']);
        add_action('wp_ajax_nopriv_submit_qa_question', [__CLASS__, 'submitQAQuestion']);

        add_action('admin_menu', [$this, 'addQADashboardMenu']);
    }

    /**
     * Register shortcodes
     */
    public function registerShortcodes(): void
    {
        add_shortcode('qa_dashboard_widget', [$this, 'renderQADashboardWidget']);
        add_shortcode('qa_analytics_dashboard', [$this, 'renderQAAnalyticsDashboard']);
    }

    /**
     * Enqueue necessary assets for QA dashboard
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_script(
            'wecoza-escape-utils-js',
            WECOZA_CORE_URL . 'assets/js/classes/utils/escape.js',
            [],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);

        wp_enqueue_script(
            'qa-dashboard-scripts',
            WECOZA_CORE_URL . 'assets/js/classes/qa-dashboard.js',
            ['jquery', 'chartjs', 'wecoza-escape-utils-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('qa-dashboard-scripts', 'qaAjax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('qa_dashboard_nonce')
        ]);
    }

    /**
     * Add QA dashboard menu to WordPress admin
     */
    public function addQADashboardMenu(): void
    {
        add_menu_page(
            'QA Analytics Dashboard',
            'QA Analytics',
            'manage_options',
            'qa-analytics-dashboard',
            [$this, 'renderQAAnalyticsDashboard'],
            'dashicons-chart-area',
            6
        );
    }

    /**
     * Render QA analytics dashboard
     */
    public function renderQAAnalyticsDashboard(): string
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        ob_start();
        include WECOZA_CORE_PATH . 'views/classes/qa-analytics-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Render QA dashboard widget shortcode
     */
    public function renderQADashboardWidget($atts): string
    {
        $atts = shortcode_atts([
            'show_charts' => 'true',
            'show_summary' => 'true',
            'limit' => '5'
        ], $atts);

        ob_start();
        include WECOZA_CORE_PATH . 'views/classes/qa-dashboard-widget.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler for getting QA analytics data
     */
    public static function getQAAnalytics(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'qa_dashboard_nonce')) {
            wp_die('Security check failed');
        }

        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $department = sanitize_text_field($_POST['department'] ?? '');

        $qa_model = new QAModel();
        $analytics_data = $qa_model->getAnalyticsData($start_date, $end_date, $department);

        wp_send_json_success($analytics_data);
    }

    /**
     * AJAX handler for getting QA summary data
     */
    public static function getQASummary(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'qa_dashboard_nonce')) {
            wp_die('Security check failed');
        }

        $qa_model = new QAModel();
        $summary_data = $qa_model->getSummaryData();

        wp_send_json_success($summary_data);
    }

    /**
     * AJAX handler for getting QA visits for a specific class
     */
    public static function getQAVisits(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'qa_dashboard_nonce')) {
            wp_die('Security check failed');
        }

        $class_id = intval($_POST['class_id'] ?? 0);

        if (!$class_id) {
            wp_send_json_error('Invalid class ID');
            return;
        }

        $qa_model = new QAModel();
        $visits = $qa_model->getVisitsByClass($class_id);

        wp_send_json_success($visits);
    }

    /**
     * AJAX handler for creating a new QA visit
     */
    public static function createQAVisit(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'qa_dashboard_nonce')) {
            wp_die('Security check failed');
        }

        $visit_data = [
            'class_id' => intval($_POST['class_id'] ?? 0),
            'visit_date' => sanitize_text_field($_POST['visit_date'] ?? ''),
            'visit_time' => sanitize_text_field($_POST['visit_time'] ?? ''),
            'visit_type' => sanitize_text_field($_POST['visit_type'] ?? 'routine'),
            'qa_officer_id' => intval($_POST['qa_officer_id'] ?? 0),
            'visit_duration' => intval($_POST['visit_duration'] ?? 0),
            'overall_rating' => intval($_POST['overall_rating'] ?? 0),
            'attendance_count' => intval($_POST['attendance_count'] ?? 0),
            'instructor_present' => filter_var($_POST['instructor_present'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'equipment_status' => sanitize_text_field($_POST['equipment_status'] ?? ''),
            'venue_condition' => sanitize_text_field($_POST['venue_condition'] ?? ''),
            'safety_compliance' => filter_var($_POST['safety_compliance'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'findings' => json_decode(stripslashes($_POST['findings'] ?? '[]'), true),
            'recommendations' => json_decode(stripslashes($_POST['recommendations'] ?? '[]'), true),
            'action_items' => json_decode(stripslashes($_POST['action_items'] ?? '[]'), true),
            'visit_notes' => sanitize_textarea_field($_POST['visit_notes'] ?? ''),
            'follow_up_required' => filter_var($_POST['follow_up_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'follow_up_date' => sanitize_text_field($_POST['follow_up_date'] ?? ''),
            'created_by' => get_current_user_id()
        ];

        $qa_model = new QAModel();
        $result = $qa_model->createVisit($visit_data);

        if ($result) {
            wp_send_json_success(['message' => 'QA visit created successfully', 'visit_id' => $result]);
        } else {
            wp_send_json_error('Failed to create QA visit');
        }
    }

    /**
     * AJAX handler for exporting QA reports
     */
    public static function exportQAReports(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'qa_dashboard_nonce')) {
            wp_die('Security check failed');
        }

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');

        $qa_model = new QAModel();
        $export_data = $qa_model->getExportData($start_date, $end_date);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="qa-reports-' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');

            fputcsv($output, ['Visit ID', 'Class ID', 'Visit Date', 'Officer', 'Rating', 'Duration', 'Notes']);

            foreach ($export_data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        } elseif ($format === 'pdf') {
            wp_send_json_error('PDF export not implemented yet');
        }

        exit;
    }

    // =====================================================================
    // QA Visit Management Methods
    // =====================================================================

    /**
     * Handle QA report file uploads
     */
    private static function handleQAReportUploads(array $files, array $visitData): array
    {
        $uploadedReports = [];

        if (empty($files['name']) || !is_array($files['name'])) {
            return $uploadedReports;
        }

        $upload_dir = wp_upload_dir();
        $qa_reports_dir = $upload_dir['basedir'] . '/qa-reports';
        $qa_reports_url = $upload_dir['baseurl'] . '/qa-reports';

        if (!file_exists($qa_reports_dir)) {
            wp_mkdir_p($qa_reports_dir);
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $visit_date = $visitData[$i]['date'] ?? date('Y-m-d');
            $visit_type = $visitData[$i]['type'] ?? 'Initial QA Visit';
            $qa_officer = $visitData[$i]['officer'] ?? '';

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            $allowed_types = ['application/pdf'];
            if (!in_array($file['type'], $allowed_types)) {
                continue;
            }

            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $base_name = 'qa_report_' . date('Ymd_His') . '_' . uniqid();
            $new_filename = $base_name . '.' . $file_extension;
            $file_path = $qa_reports_dir . '/' . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $uploadedReports[] = [
                    'filename' => $new_filename,
                    'original_name' => $file['name'],
                    'file_path' => 'qa-reports/' . $new_filename,
                    'file_url' => $qa_reports_url . '/' . $new_filename,
                    'file_size' => $file['size'],
                    'uploaded_by' => wp_get_current_user()->display_name,
                    'upload_date' => current_time('mysql')
                ];
            }
        }

        return $uploadedReports;
    }

    /**
     * Delete a QA report file from the server
     */
    private static function deleteQAReportFile(string $filePath): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $filePath;

        if (file_exists($full_path)) {
            return unlink($full_path);
        }

        return false;
    }

    /**
     * Save QA visits to the normalized structure
     */
    public static function saveQAVisits(int $classId, array $data, ?array $files = null): bool
    {
        $existingVisits = QAVisitModel::findByClassId($classId);

        foreach ($existingVisits as $visit) {
            $latestDocument = $visit->getLatestDocument();
            if ($latestDocument && isset($latestDocument['file_path'])) {
                self::deleteQAReportFile($latestDocument['file_path']);
            }
        }

        QAVisitModel::deleteByClassId($classId);

        $visitData = [];
        if (isset($data['qa_visits_data']) && !empty($data['qa_visits_data'])) {
            $decoded = json_decode(stripslashes($data['qa_visits_data']), true);
            if (is_array($decoded)) {
                $visitData = $decoded;
            }
        }

        if (empty($visitData)) {
            $visitDates = $data['qa_visit_dates'] ?? [];
            $visitTypes = $data['qa_visit_types'] ?? [];
            $officers = $data['qa_officers'] ?? [];

            for ($i = 0; $i < count($visitDates); $i++) {
                if (!empty($visitDates[$i])) {
                    $visitData[] = [
                        'date' => $visitDates[$i],
                        'type' => $visitTypes[$i] ?? 'Initial QA Visit',
                        'officer' => $officers[$i] ?? '',
                        'hasNewFile' => false
                    ];
                }
            }
        }

        $uploadedReports = [];
        if ($files && isset($files['qa_reports'])) {
            $uploadedReports = self::handleQAReportUploads($files['qa_reports'], $visitData);
        }

        foreach ($visitData as $index => $visit) {
            if (empty($visit['date'])) {
                continue;
            }

            $document = null;

            if (isset($visit['existingDocument']) && empty($visit['hasNewFile'])) {
                $document = $visit['existingDocument'];
            }

            if (isset($uploadedReports[$index])) {
                $document = $uploadedReports[$index];
            }

            $visitModel = new QAVisitModel([
                'class_id' => $classId,
                'visit_date' => sanitize_text_field($visit['date']),
                'visit_type' => sanitize_text_field($visit['type'] ?? 'Initial QA Visit'),
                'officer_name' => sanitize_text_field($visit['officer'] ?? ''),
                'latest_document' => $document
            ]);

            $visitModel->save();
        }

        return true;
    }

    /**
     * Get QA visits for a class in a format suitable for the view
     */
    public static function getQAVisitsForClass(int $classId): array
    {
        try {
            $qaVisits = QAVisitModel::findByClassId($classId);

            $visits = [];
            foreach ($qaVisits as $visit) {
                $visits[] = [
                    'date' => $visit->getVisitDate(),
                    'type' => $visit->getVisitType(),
                    'officer' => $visit->getOfficerName(),
                    'document' => $visit->getLatestDocument(),
                    'hasNewFile' => false,
                    'existingDocument' => $visit->getLatestDocument()
                ];
            }

            return ['visits' => $visits];
        } catch (\Exception $e) {
            return ['visits' => []];
        }
    }

    /**
     * Parse QA visit dates from database format to array
     */
    public static function parseQaVisitDates(string|array|null $qaVisitDates): array
    {
        if (empty($qaVisitDates) || $qaVisitDates === '0') {
            return [];
        }

        if (is_array($qaVisitDates)) {
            return $qaVisitDates;
        }

        if (is_string($qaVisitDates)) {
            $decoded = json_decode($qaVisitDates, true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }

            $dates = array_map('trim', explode(',', $qaVisitDates));
            return array_filter($dates);
        }

        return [];
    }

    /**
     * AJAX: Get class QA data for a specific class
     */
    public static function getClassQAData(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wecoza_class_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

        if ($class_id <= 0) {
            wp_send_json_error('Invalid class ID');
            return;
        }

        $class = ClassRepository::getSingleClass($class_id);

        if (!$class) {
            wp_send_json_error('Class not found');
            return;
        }

        $qa_visit_dates = [];
        if (!empty($class['qa_visit_dates'])) {
            if (is_string($class['qa_visit_dates'])) {
                $decoded = json_decode($class['qa_visit_dates'], true);
                if ($decoded !== null) {
                    $qa_visit_dates = $decoded;
                } else {
                    $qa_visit_dates = array_map('trim', explode(',', $class['qa_visit_dates']));
                }
            } elseif (is_array($class['qa_visit_dates'])) {
                $qa_visit_dates = $class['qa_visit_dates'];
            }
        }

        $qa_reports = $class['qa_reports'] ?? [];

        wp_send_json_success([
            'qa_visit_dates' => $qa_visit_dates,
            'qa_reports' => $qa_reports
        ]);
    }

    /**
     * AJAX: Submit a QA question
     */
    public static function submitQAQuestion(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wecoza_class_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        $question = isset($_POST['question']) ? sanitize_textarea_field($_POST['question']) : '';
        $context = isset($_POST['context']) ? sanitize_textarea_field($_POST['context']) : '';

        if ($class_id <= 0 || empty($question)) {
            wp_send_json_error('Invalid input data');
            return;
        }

        $attachment_url = '';
        $attachment_path = '';

        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];

            $allowed_types = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png'
            ];
            if (!in_array($file['type'], $allowed_types)) {
                wp_send_json_error('Invalid file type');
                return;
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                wp_send_json_error('File size must be less than 5MB');
                return;
            }

            $upload_dir = wp_upload_dir();
            $qa_dir = $upload_dir['basedir'] . '/qa-questions/' . $class_id;

            if (!file_exists($qa_dir)) {
                wp_mkdir_p($qa_dir);
            }

            $filename = 'question_' . uniqid() . '_' . sanitize_file_name($file['name']);
            $filepath = $qa_dir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $attachment_path = 'qa-questions/' . $class_id . '/' . $filename;
                $attachment_url = $upload_dir['baseurl'] . '/' . $attachment_path;
            }
        }

        $question_data = [
            'id' => uniqid('qa_'),
            'question' => $question,
            'context' => $context,
            'author' => wp_get_current_user()->display_name,
            'author_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'status' => 'pending',
            'answers' => []
        ];

        if ($attachment_url) {
            $question_data['attachment'] = [
                'url' => $attachment_url,
                'path' => $attachment_path,
                'name' => basename($filename)
            ];
        }

        $class = ClassRepository::getSingleClass($class_id);

        if (!$class) {
            wp_send_json_error('Class not found');
            return;
        }

        $qa_data = isset($class['qa_data']) && is_array($class['qa_data'])
            ? $class['qa_data']
            : [];

        $qa_data[] = $question_data;

        wp_send_json_success([
            'message' => 'Question submitted successfully',
            'question' => $question_data
        ]);
    }

    /**
     * AJAX: Delete a QA report
     */
    public function deleteQAReport(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wecoza_class_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }

        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        $report_index = isset($_POST['report_index']) ? intval($_POST['report_index']) : -1;

        if ($class_id <= 0 || $report_index < 0) {
            wp_send_json_error('Invalid input data');
            return;
        }

        $class = ClassRepository::getSingleClass($class_id);

        if (!$class) {
            wp_send_json_error('Class not found');
            return;
        }

        $reports = isset($class['qa_reports']) && is_array($class['qa_reports'])
            ? $class['qa_reports']
            : [];

        if (!isset($reports[$report_index])) {
            wp_send_json_error('Report not found');
            return;
        }

        $report = $reports[$report_index];
        $file_path = '';

        if (isset($report['file_path'])) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/' . $report['file_path'];
        }

        array_splice($reports, $report_index, 1);

        $db = wecoza_db();

        try {
            $sql = "UPDATE public.classes SET qa_reports = $1, updated_at = NOW() WHERE class_id = $2";
            $db->query($sql, [json_encode($reports), $class_id]);

            if ($file_path && file_exists($file_path)) {
                unlink($file_path);
            }

            wp_send_json_success([
                'message' => 'Report deleted successfully',
                'remaining_reports' => count($reports)
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Failed to delete report');
        }
    }

    /**
     * Custom upload directory for QA-related files
     */
    public static function customUploadDir(array $upload): array
    {
        $upload['subdir'] = '/wecoza-classes' . $upload['subdir'];
        $upload['path'] = $upload['basedir'] . $upload['subdir'];
        $upload['url'] = $upload['baseurl'] . $upload['subdir'];

        return $upload;
    }
}
