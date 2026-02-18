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
use WeCoza\Learners\Models\LearnerProgressionModel;
use WeCoza\Learners\Repositories\LearnerProgressionRepository;
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
 * Fetch paginated, filtered progressions for admin management table
 *
 * AJAX action: get_admin_progressions
 */
function handle_get_admin_progressions(): void
{
    try {
        verify_learner_access('learners_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        // Build validated filters from GET params
        $allowedStatuses = ['in_progress', 'completed', 'on_hold'];
        $filters = [];

        if (!empty($_GET['client_id'])) {
            $filters['client_id'] = intval($_GET['client_id']);
        }
        if (!empty($_GET['class_id'])) {
            $filters['class_id'] = intval($_GET['class_id']);
        }
        if (!empty($_GET['product_id'])) {
            $filters['product_id'] = intval($_GET['product_id']);
        }
        if (!empty($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true)) {
            $filters['status'] = sanitize_key($_GET['status']);
        }

        $pageSize   = 25;
        $page       = max(1, isset($_GET['page']) ? intval($_GET['page']) : 1);
        $offset     = ($page - 1) * $pageSize;

        $service = new ProgressionService();
        $result  = $service->getProgressionsForAdmin($filters, $pageSize, $offset);

        wp_send_json_success([
            'data'         => $result['data'],
            'total'        => $result['total'],
            'pages'        => $result['pages'],
            'current_page' => $page,
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Bulk-complete multiple LP progressions without requiring portfolio files
 *
 * Bypasses the portfolio requirement of ProgressionService::markLPComplete() by
 * calling LearnerProgressionModel::markComplete() directly. This is intentional:
 * bulk admin operations trade portfolio enforcement for operational speed.
 *
 * AJAX action: bulk_complete_progressions
 */
function handle_bulk_complete_progressions(): void
{
    try {
        verify_learner_access('learners_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        if (!isset($_POST['tracking_ids']) || !is_array($_POST['tracking_ids'])) {
            throw new Exception('tracking_ids must be a non-empty array.');
        }

        $trackingIds = array_map('intval', $_POST['tracking_ids']);
        $trackingIds = array_filter($trackingIds); // remove zeros

        if (empty($trackingIds)) {
            throw new Exception('No valid tracking IDs provided.');
        }

        if (count($trackingIds) > 50) {
            throw new Exception('Maximum 50 progressions can be bulk-completed at once.');
        }

        $completedBy = get_current_user_id();
        $completed   = [];
        $failed      = [];

        foreach ($trackingIds as $id) {
            try {
                $model = LearnerProgressionModel::getById($id);

                if (!$model) {
                    throw new Exception("Progression not found.");
                }

                if ($model->isCompleted()) {
                    throw new Exception("LP is already marked as complete.");
                }

                // Bypass portfolio requirement — direct model call (intentional for bulk admin)
                if (!$model->markComplete($completedBy)) {
                    throw new Exception("Failed to update progression record.");
                }

                $completed[] = $id;

            } catch (Exception $e) {
                $failed[] = ['id' => $id, 'error' => $e->getMessage()];
            }
        }

        wp_send_json_success([
            'completed' => $completed,
            'failed'    => $failed,
            'count'     => count($completed),
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Retrieve full hours log for a given tracking ID along with basic progression info
 *
 * AJAX action: get_progression_hours_log
 */
function handle_get_progression_hours_log(): void
{
    try {
        verify_learner_access('learners_nonce');

        $trackingId = isset($_GET['tracking_id']) ? intval($_GET['tracking_id']) : 0;
        if (!$trackingId) {
            throw new Exception('tracking_id is required.');
        }

        $progression = LearnerProgressionModel::getById($trackingId);
        if (!$progression) {
            throw new Exception('Progression not found.');
        }

        $repository = new LearnerProgressionRepository();
        $hoursLog   = $repository->getHoursLog($trackingId);

        wp_send_json_success([
            'progression' => [
                'tracking_id'      => $progression->getTrackingId(),
                'learner_name'     => $progression->getLearnerName(),
                'product_name'     => $progression->getProductName(),
                'status'           => $progression->getStatus(),
                'hours_present'    => $progression->getHoursPresent(),
                'hours_trained'    => $progression->getHoursTrained(),
                'product_duration' => $progression->getProductDuration(),
            ],
            'hours_log' => $hoursLog,
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Manually start a new LP for a learner (admin-initiated)
 *
 * AJAX action: start_learner_progression
 */
function handle_start_learner_progression(): void
{
    try {
        verify_learner_access('learners_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $learnerId = isset($_POST['learner_id']) ? intval($_POST['learner_id']) : 0;
        if (!$learnerId) {
            throw new Exception('learner_id is required.');
        }

        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$productId) {
            throw new Exception('product_id is required.');
        }

        $classId = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
        $notes   = !empty($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : null;

        $service    = new ProgressionService();
        $progression = $service->startLearnerProgression($learnerId, $productId, $classId, $notes);

        wp_send_json_success([
            'tracking_id' => $progression->getTrackingId(),
            'message'     => 'LP started successfully.',
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Toggle an LP between in_progress and on_hold states
 *
 * AJAX action: toggle_progression_hold
 */
function handle_toggle_progression_hold(): void
{
    try {
        verify_learner_access('learners_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $trackingId = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        if (!$trackingId) {
            throw new Exception('tracking_id is required.');
        }

        $allowedActions = ['hold', 'resume'];
        $action = isset($_POST['action']) ? sanitize_key($_POST['action']) : '';
        if (!in_array($action, $allowedActions, true)) {
            throw new Exception("action must be 'hold' or 'resume'.");
        }

        $model = LearnerProgressionModel::getById($trackingId);
        if (!$model) {
            throw new Exception('Progression not found.');
        }

        if ($action === 'hold') {
            if (!$model->isInProgress()) {
                throw new Exception("Cannot put on hold: LP status is '{$model->getStatus()}', expected 'in_progress'.");
            }
            $model->putOnHold('Put on hold by admin');
        } else {
            if (!$model->isOnHold()) {
                throw new Exception("Cannot resume: LP status is '{$model->getStatus()}', expected 'on_hold'.");
            }
            $model->resume();
        }

        wp_send_json_success([
            'tracking_id' => $trackingId,
            'new_status'  => $model->getStatus(),
            'message'     => $action === 'hold'
                ? 'LP has been put on hold.'
                : 'LP has been resumed.',
        ]);

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
    add_action('wp_ajax_get_admin_progressions',           __NAMESPACE__ . '\handle_get_admin_progressions');
    add_action('wp_ajax_bulk_complete_progressions',       __NAMESPACE__ . '\handle_bulk_complete_progressions');
    add_action('wp_ajax_get_progression_hours_log',        __NAMESPACE__ . '\handle_get_progression_hours_log');
    add_action('wp_ajax_start_learner_progression',        __NAMESPACE__ . '\handle_start_learner_progression');
    add_action('wp_ajax_toggle_progression_hold',          __NAMESPACE__ . '\handle_toggle_progression_hold');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_progression_ajax_handlers');
