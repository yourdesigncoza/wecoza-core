<?php
declare(strict_types=1);

/**
 * AJAX Handlers for Learner LP Progression
 *
 * Provides AJAX handlers for LP progression operations: mark complete,
 * portfolio upload, fetch progression data, and collision logging.
 *
 * @package WeCoza\Learners\Ajax
 * @since 1.0.0
 */

namespace WeCoza\Learners\Ajax;

use WeCoza\Learners\Services\ProgressionService;
use WeCoza\Learners\Services\PortfolioUploadService;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate an uploaded portfolio file
 *
 * Shared helper used by both handle_mark_progression_complete and
 * handle_upload_progression_portfolio to avoid duplication.
 *
 * @param array $file Entry from $_FILES
 * @return array ['valid' => bool, 'file' => array|null, 'error' => string|null]
 */
function validate_portfolio_file(array $file): array
{
    // Sanitize filename to prevent directory traversal
    $filename = sanitize_file_name($file['name']);

    // Validate file size (10MB max)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'file' => null, 'error' => 'File is too large. Maximum size is 10MB.'];
    }

    // Verify MIME type and extension using WordPress deep check
    $allowedMimes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    $fileCheck = wp_check_filetype_and_ext($file['tmp_name'], $filename, $allowedMimes);

    if (!$fileCheck['ext'] || !$fileCheck['type']) {
        return ['valid' => false, 'file' => null, 'error' => 'Invalid file type. Allowed: PDF, DOC, DOCX.'];
    }

    // Return sanitized file array
    $sanitizedFile = $file;
    $sanitizedFile['name'] = $filename;

    return ['valid' => true, 'file' => $sanitizedFile, 'error' => null];
}

/**
 * Mark an LP as complete with a required portfolio upload
 *
 * AJAX action: mark_progression_complete
 */
function handle_mark_progression_complete(): void
{
    try {
        verify_learner_access('learners_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $trackingId = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        if (!$trackingId) {
            throw new Exception('Tracking ID is required.');
        }

        // Portfolio file is required to complete an LP (per user decision)
        if (!isset($_FILES['portfolio_file']) || empty($_FILES['portfolio_file']['tmp_name'])) {
            throw new Exception('Portfolio file is required to complete an LP.');
        }

        $validation = validate_portfolio_file($_FILES['portfolio_file']);
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        $service = new ProgressionService();
        $result = $service->markLPComplete($trackingId, get_current_user_id(), $validation['file']);

        wp_send_json_success([
            'message'         => 'LP marked as complete successfully.',
            'tracking_id'     => $result->getTrackingId(),
            'completion_date' => $result->getCompletionDate(),
            'status'          => 'completed',
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Upload an additional portfolio file for an existing in-progress LP
 *
 * AJAX action: upload_progression_portfolio
 */
function handle_upload_progression_portfolio(): void
{
    try {
        verify_learner_access('learners_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $trackingId = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        if (!$trackingId) {
            throw new Exception('Tracking ID is required.');
        }

        if (!isset($_FILES['portfolio_file']) || empty($_FILES['portfolio_file']['tmp_name'])) {
            throw new Exception('No file uploaded.');
        }

        $validation = validate_portfolio_file($_FILES['portfolio_file']);
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        $service = new PortfolioUploadService();
        $result = $service->uploadProgressionPortfolio($trackingId, $validation['file'], get_current_user_id());

        if (!$result['success']) {
            throw new Exception($result['message']);
        }

        wp_send_json_success([
            'message'   => 'Portfolio uploaded successfully.',
            'file_id'   => $result['file_id'],
            'file_path' => $result['file_path'],
            'file_url'  => $result['file_url'],
            'file_name' => $result['file_name'],
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Fetch learner progression data (current LP, history, overall)
 *
 * AJAX action: get_learner_progressions
 */
function handle_get_learner_progressions(): void
{
    try {
        verify_learner_access('learners_nonce');

        $learnerId = isset($_GET['learner_id']) ? intval($_GET['learner_id']) : 0;
        if (!$learnerId) {
            throw new Exception('Learner ID is required.');
        }

        $service = new ProgressionService();

        wp_send_json_success([
            'current_lp' => $service->getCurrentLPDetails($learnerId),
            'history'    => $service->getProgressionHistory($learnerId),
            'overall'    => $service->getLearnerOverallProgress($learnerId),
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Log that an admin acknowledged the LP collision warning
 *
 * AJAX action: log_lp_collision_acknowledgement
 */
function handle_log_collision_acknowledgement(): void
{
    try {
        verify_learner_access('wecoza_class_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $learnerIds = isset($_POST['learner_ids']) && is_array($_POST['learner_ids'])
            ? array_map('intval', $_POST['learner_ids'])
            : [];

        $classId = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        $userId  = get_current_user_id();

        wecoza_log(
            sprintf(
                'LP Collision Acknowledged: Admin user %d added learners [%s] to class %d despite active LPs',
                $userId,
                implode(', ', $learnerIds),
                $classId
            ),
            'info'
        );

        wp_send_json_success(['logged' => true]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Register all progression AJAX handlers
 */
function register_progression_ajax_handlers(): void
{
    add_action('wp_ajax_mark_progression_complete',        __NAMESPACE__ . '\handle_mark_progression_complete');
    add_action('wp_ajax_upload_progression_portfolio',    __NAMESPACE__ . '\handle_upload_progression_portfolio');
    add_action('wp_ajax_get_learner_progressions',        __NAMESPACE__ . '\handle_get_learner_progressions');
    add_action('wp_ajax_log_lp_collision_acknowledgement', __NAMESPACE__ . '\handle_log_collision_acknowledgement');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_progression_ajax_handlers');
