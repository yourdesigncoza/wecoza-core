<?php
/**
 * Progression AJAX Handlers
 *
 * Handles AJAX requests for learner LP progression operations
 * - mark_progression_complete: Mark LP as complete with portfolio upload
 *
 * @package WeCoza_Learners
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX actions
 */
add_action('wp_ajax_mark_progression_complete', 'wecoza_handle_mark_progression_complete');

/**
 * Handle marking an LP as complete
 *
 * Requires:
 * - Admin capability (manage_options)
 * - Valid nonce
 * - tracking_id
 * - portfolio_file (optional but recommended)
 */
function wecoza_handle_mark_progression_complete(): void {
    // Verify nonce
    if (!check_ajax_referer('learners_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.');
        return;
    }

    // Check admin capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }

    // Get tracking ID
    $trackingId = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
    if (!$trackingId) {
        wp_send_json_error('Missing tracking ID.');
        return;
    }

    // Get current user ID for completion record
    $completedBy = get_current_user_id();

    // Handle portfolio file if uploaded
    $portfolioFile = null;
    if (isset($_FILES['portfolio_file']) && !empty($_FILES['portfolio_file']['tmp_name'])) {
        $file = $_FILES['portfolio_file'];

        // A. Sanitize filename to prevent directory traversal
        $filename = sanitize_file_name($file['name']);

        // B. Validate file size (10MB max)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            wp_send_json_error('File is too large. Maximum size is 10MB.');
            return;
        }

        // C. Verify MIME type and extension using WordPress deep check
        $allowedMimes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        $fileCheck = wp_check_filetype_and_ext($file['tmp_name'], $filename, $allowedMimes);

        if (!$fileCheck['ext'] || !$fileCheck['type']) {
            wp_send_json_error('Invalid file type. Allowed: PDF, DOC, DOCX.');
            return;
        }

        // Update file array with sanitized name
        $file['name'] = $filename;
        $portfolioFile = $file;
    }

    try {
        // Load ProgressionService
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'services/ProgressionService.php';

        $service = new \WeCoza\Services\ProgressionService();
        $result = $service->markLPComplete($trackingId, $completedBy, $portfolioFile);

        wp_send_json_success([
            'message' => 'LP marked as complete successfully.',
            'tracking_id' => $result->getTrackingId(),
            'completion_date' => $result->getCompletionDate(),
        ]);

    } catch (\Exception $e) {
        // Log specific error but send generic message to client
        error_log('Progression completion error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while processing the request.');
    }
}

/**
 * Handle uploading a progression portfolio (without marking complete)
 * Can be used for adding additional portfolio files to an existing progression
 */
add_action('wp_ajax_upload_progression_portfolio', 'wecoza_handle_upload_progression_portfolio');

function wecoza_handle_upload_progression_portfolio(): void {
    // Verify nonce
    if (!check_ajax_referer('learners_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.');
        return;
    }

    // Check admin capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }

    // Get tracking ID
    $trackingId = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
    if (!$trackingId) {
        wp_send_json_error('Missing tracking ID.');
        return;
    }

    // Check for file
    if (!isset($_FILES['portfolio_file']) || empty($_FILES['portfolio_file']['tmp_name'])) {
        wp_send_json_error('No file uploaded.');
        return;
    }

    $file = $_FILES['portfolio_file'];

    // A. Sanitize filename to prevent directory traversal
    $filename = sanitize_file_name($file['name']);

    // B. Validate file size (10MB max)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        wp_send_json_error('File is too large. Maximum size is 10MB.');
        return;
    }

    // C. Verify MIME type and extension using WordPress deep check
    $allowedMimes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $fileCheck = wp_check_filetype_and_ext($file['tmp_name'], $filename, $allowedMimes);

    if (!$fileCheck['ext'] || !$fileCheck['type']) {
        wp_send_json_error('Invalid file type. Allowed: PDF, DOC, DOCX.');
        return;
    }

    // Update file array with sanitized name
    $file['name'] = $filename;
    $portfolioFile = $file;

    try {
        // Load PortfolioUploadService
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'services/PortfolioUploadService.php';

        $service = new \WeCoza\Services\PortfolioUploadService();
        $uploadedBy = get_current_user_id();
        $result = $service->uploadProgressionPortfolio($trackingId, $portfolioFile, $uploadedBy);

        if (!$result['success']) {
            wp_send_json_error('Upload failed. Please try again.');
            return;
        }

        wp_send_json_success([
            'message' => 'Portfolio uploaded successfully.',
            'file_id' => $result['file_id'],
            'file_path' => $result['file_path'],
            'file_url' => $result['file_url'],
        ]);

    } catch (\Exception $e) {
        // Log specific error but send generic message to client
        error_log('Portfolio upload error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while uploading the file.');
    }
}

/**
 * Get progression details via AJAX
 * Can be used for refreshing progression data without page reload
 */
add_action('wp_ajax_get_learner_progressions', 'wecoza_handle_get_learner_progressions');
add_action('wp_ajax_nopriv_get_learner_progressions', 'wecoza_handle_get_learner_progressions');

function wecoza_handle_get_learner_progressions(): void {
    // Verify nonce
    if (!check_ajax_referer('learners_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.');
        return;
    }

    // Get learner ID
    $learnerId = isset($_GET['learner_id']) ? intval($_GET['learner_id']) : 0;
    if (!$learnerId) {
        wp_send_json_error('Missing learner ID.');
        return;
    }

    try {
        // Load ProgressionService
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'services/ProgressionService.php';

        $service = new \WeCoza\Services\ProgressionService();

        $currentLP = $service->getCurrentLPDetails($learnerId);
        $history = $service->getProgressionHistory($learnerId);
        $overall = $service->getLearnerOverallProgress($learnerId);

        wp_send_json_success([
            'current_lp' => $currentLP,
            'history' => $history,
            'overall' => $overall,
        ]);

    } catch (\Exception $e) {
        // Log specific error but send generic message to client
        error_log('Get progressions error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while fetching progression data.');
    }
}
