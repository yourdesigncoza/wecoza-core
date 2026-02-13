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
use WeCoza\Classes\Services\UploadService;

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

        // QA Analytics AJAX handlers - using instance methods
        add_action('wp_ajax_get_qa_analytics', [$this, 'getQAAnalytics']);
        add_action('wp_ajax_nopriv_get_qa_analytics', [$this, 'getQAAnalytics']);
        add_action('wp_ajax_get_qa_summary', [$this, 'getQASummary']);
        add_action('wp_ajax_nopriv_get_qa_summary', [$this, 'getQASummary']);
        add_action('wp_ajax_get_qa_visits', [$this, 'getQAVisits']);
        add_action('wp_ajax_nopriv_get_qa_visits', [$this, 'getQAVisits']);
        add_action('wp_ajax_create_qa_visit', [$this, 'createQAVisit']);
        add_action('wp_ajax_export_qa_reports', [$this, 'exportQAReports']);

        // QA Operations AJAX handlers
        add_action('wp_ajax_delete_qa_report', [$this, 'deleteQAReport']);
        add_action('wp_ajax_get_class_qa_data', [$this, 'getClassQAData']);
        add_action('wp_ajax_nopriv_get_class_qa_data', [$this, 'getClassQAData']);
        add_action('wp_ajax_submit_qa_question', [$this, 'submitQAQuestion']);

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
    public function getQAAnalytics(): void
    {
        $this->requireNonce('qa_dashboard_nonce');

        $start_date = $this->input('start_date', 'string', '');
        $end_date = $this->input('end_date', 'string', '');
        $department = $this->input('department', 'string', '');

        $qa_model = new QAModel();
        $analytics_data = $qa_model->getAnalyticsData($start_date, $end_date, $department);

        $this->sendSuccess($analytics_data);
    }

    /**
     * AJAX handler for getting QA summary data
     */
    public function getQASummary(): void
    {
        $this->requireNonce('qa_dashboard_nonce');

        $qa_model = new QAModel();
        $summary_data = $qa_model->getSummaryData();

        $this->sendSuccess($summary_data);
    }

    /**
     * AJAX handler for getting QA visits for a specific class
     */
    public function getQAVisits(): void
    {
        $this->requireNonce('qa_dashboard_nonce');

        $class_id = $this->input('class_id', 'int', 0);

        if (!$class_id) {
            $this->sendError('Invalid class ID');
            return;
        }

        $qa_model = new QAModel();
        $visits = $qa_model->getVisitsByClass($class_id);

        $this->sendSuccess($visits);
    }

    /**
     * AJAX handler for creating a new QA visit
     */
    public function createQAVisit(): void
    {
        $this->requireNonce('qa_dashboard_nonce');

        $visit_data = [
            'class_id' => $this->input('class_id', 'int', 0),
            'visit_date' => $this->input('visit_date', 'string', ''),
            'visit_time' => $this->input('visit_time', 'string', ''),
            'visit_type' => $this->input('visit_type', 'string', 'routine'),
            'qa_officer_id' => $this->input('qa_officer_id', 'int', 0),
            'visit_duration' => $this->input('visit_duration', 'int', 0),
            'overall_rating' => $this->input('overall_rating', 'int', 0),
            'attendance_count' => $this->input('attendance_count', 'int', 0),
            'instructor_present' => $this->input('instructor_present', 'bool', true),
            'equipment_status' => $this->input('equipment_status', 'string', ''),
            'venue_condition' => $this->input('venue_condition', 'string', ''),
            'safety_compliance' => $this->input('safety_compliance', 'bool', true),
            'findings' => $this->input('findings', 'json', []),
            'recommendations' => $this->input('recommendations', 'json', []),
            'action_items' => $this->input('action_items', 'json', []),
            'visit_notes' => $this->input('visit_notes', 'textarea', ''),
            'follow_up_required' => $this->input('follow_up_required', 'bool', false),
            'follow_up_date' => $this->input('follow_up_date', 'string', ''),
            'created_by' => get_current_user_id()
        ];

        $qa_model = new QAModel();
        $result = $qa_model->createVisit($visit_data);

        if ($result) {
            $this->sendSuccess(['visit_id' => $result], 'QA visit created successfully');
        } else {
            $this->sendError('Failed to create QA visit');
        }
    }

    /**
     * AJAX handler for exporting QA reports
     */
    public function exportQAReports(): void
    {
        $this->requireNonce('qa_dashboard_nonce');

        $format = $this->input('format', 'string', 'csv');
        $start_date = $this->input('start_date', 'string', '');
        $end_date = $this->input('end_date', 'string', '');

        $qa_model = new QAModel();
        $export_data = $qa_model->getExportData($start_date, $end_date);

        if ($format === 'csv') {
            $filename = sanitize_file_name('qa-reports-' . date('Y-m-d') . '.csv');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            fputcsv($output, ['Visit ID', 'Class ID', 'Visit Date', 'Officer', 'Rating', 'Duration', 'Notes']);

            foreach ($export_data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        } elseif ($format === 'pdf') {
            $this->sendError('PDF export not implemented yet');
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
        $uploadService = new UploadService();
        return $uploadService->uploadQAReports($files, $visitData);
    }

    /**
     * Delete a QA report file from the server
     */
    private static function deleteQAReportFile(string $filePath): bool
    {
        $uploadService = new UploadService();
        return $uploadService->deleteQAReportFile($filePath);
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
    public function getClassQAData(): void
    {
        $this->requireNonce('wecoza_class_nonce');

        $class_id = $this->input('class_id', 'int', 0);

        if ($class_id <= 0) {
            $this->sendError('Invalid class ID');
            return;
        }

        $class = ClassRepository::getSingleClass($class_id);

        if (!$class) {
            $this->sendError('Class not found');
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

        $this->sendSuccess([
            'qa_visit_dates' => $qa_visit_dates,
            'qa_reports' => $qa_reports
        ]);
    }

    /**
     * AJAX: Submit a QA question
     */
    public function submitQAQuestion(): void
    {
        $this->requireNonce('wecoza_class_nonce');

        $class_id = $this->input('class_id', 'int', 0);
        $question = $this->input('question', 'textarea', '');
        $context = $this->input('context', 'textarea', '');

        if ($class_id <= 0 || empty($question)) {
            $this->sendError('Invalid input data');
            return;
        }

        $attachment_url = '';
        $attachment_path = '';
        $filename = '';

        if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadService = new UploadService();
            $result = $uploadService->uploadQAQuestionAttachment($class_id, $_FILES['attachment']);

            if (!$result['success']) {
                $this->sendError($result['message']);
                return;
            }

            $attachment_url = $result['url'];
            $attachment_path = $result['path'];
            $filename = $result['name'];
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
            $this->sendError('Class not found');
            return;
        }

        $qa_data = isset($class['qa_data']) && is_array($class['qa_data'])
            ? $class['qa_data']
            : [];

        $qa_data[] = $question_data;

        $this->sendSuccess([
            'question' => $question_data
        ], 'Question submitted successfully');
    }

    /**
     * AJAX: Delete a QA report
     */
    public function deleteQAReport(): void
    {
        $this->requireNonce('wecoza_class_nonce');

        $class_id = $this->input('class_id', 'int', 0);
        $report_index = $this->input('report_index', 'int', -1);

        if ($class_id <= 0 || $report_index < 0) {
            $this->sendError('Invalid input data');
            return;
        }

        $class = ClassRepository::getSingleClass($class_id);

        if (!$class) {
            $this->sendError('Class not found');
            return;
        }

        $reports = isset($class['qa_reports']) && is_array($class['qa_reports'])
            ? $class['qa_reports']
            : [];

        if (!isset($reports[$report_index])) {
            $this->sendError('Report not found');
            return;
        }

        $report = $reports[$report_index];
        $file_path = '';

        if (isset($report['file_path'])) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/' . $report['file_path'];
        }

        array_splice($reports, $report_index, 1);

        try {
            $sql = "UPDATE public.classes SET qa_reports = ?, updated_at = NOW() WHERE class_id = ?";
            $this->db()->query($sql, [json_encode($reports), $class_id]);

            if ($file_path && file_exists($file_path)) {
                unlink($file_path);
            }

            $this->sendSuccess([
                'remaining_reports' => count($reports)
            ], 'Report deleted successfully');
        } catch (\Exception $e) {
            $this->sendError('Failed to delete report');
        }
    }
}
