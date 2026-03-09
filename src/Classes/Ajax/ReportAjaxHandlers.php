<?php
declare(strict_types=1);

/**
 * AJAX Handlers for Report Extraction
 *
 * Provides AJAX endpoints for generating class report previews
 * and streaming CSV downloads.
 *
 * @package WeCoza\Classes\Ajax
 * @since 1.0.0
 */

namespace WeCoza\Classes\Ajax;

use WeCoza\Classes\Services\ReportService;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a class report preview (JSON response).
 *
 * AJAX action: generate_class_report
 */
function handle_generate_class_report(): void
{
    try {
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        // Verify nonce
        check_ajax_referer('class_learner_report_nonce', 'nonce');

        // Get and validate parameters
        $classId = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        if (!$classId) {
            throw new Exception('Class ID is required.');
        }

        $year  = isset($_POST['year']) ? absint($_POST['year']) : (int) date('Y');
        $month = isset($_POST['month']) ? absint($_POST['month']) : (int) date('n');

        // Validate month range
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        $service = new ReportService();
        $reportData = $service->generateClassReport($classId, $year, $month);

        wp_send_json_success($reportData);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Stream a CSV download of the class report.
 *
 * AJAX action: download_class_report_csv
 * Uses GET parameters since it triggers a file download.
 */
function handle_download_class_report_csv(): void
{
    // Capability check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Verify nonce (GET params for download links)
    check_ajax_referer('class_learner_report_nonce', 'nonce');

    $classId = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
    $year    = isset($_GET['year']) ? absint($_GET['year']) : (int) date('Y');
    $month   = isset($_GET['month']) ? absint($_GET['month']) : (int) date('n');

    if (!$classId) {
        wp_die('Class ID is required.');
    }

    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }

    try {
        $service = new ReportService();
        $reportData = $service->generateClassReport($classId, $year, $month);
        $csvRows = $service->formatCsvRows($reportData);

        // Build filename using class code from report header
        $classCode = $reportData['header']['class_code'] ?? ('class-' . $classId);
        $classCode = preg_replace('/[^a-zA-Z0-9_-]/', '-', $classCode);
        $filename = sprintf('class-report-%s-%04d-%02d.csv', $classCode, $year, $month);

        // Clear output buffer before headers
        if (ob_get_length()) {
            ob_clean();
        }

        // Stream CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        foreach ($csvRows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;

    } catch (Exception $e) {
        wp_die('Error generating report: ' . esc_html($e->getMessage()));
    }
}

/**
 * Register report extraction AJAX handlers
 */
function register_report_ajax_handlers(): void
{
    add_action('wp_ajax_generate_class_report', __NAMESPACE__ . '\handle_generate_class_report');
    add_action('wp_ajax_download_class_report_csv', __NAMESPACE__ . '\handle_download_class_report_csv');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_report_ajax_handlers');
