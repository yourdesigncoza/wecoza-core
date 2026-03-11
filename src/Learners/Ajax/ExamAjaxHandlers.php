<?php
declare(strict_types=1);

/**
 * AJAX Handlers for Learner Exam Results
 *
 * Provides AJAX handlers for exam operations: record result,
 * fetch progress, and delete result. Supports the 5-step exam
 * flow (mock_1, mock_2, mock_3, sba, final).
 *
 * @package WeCoza\Learners\Ajax
 * @since 1.2.0
 */

namespace WeCoza\Learners\Ajax;

use WeCoza\Learners\Enums\ExamStep;
use WeCoza\Learners\Models\LearnerProgressionModel;
use WeCoza\Learners\Services\ExamService;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Record or update an exam result for a learner LP tracking entry.
 *
 * POST params: tracking_id, exam_step, percentage (0–100)
 * Optional: $_FILES['exam_file'] for SBA/final evidence upload
 *
 * AJAX action: record_exam_result
 */
function handle_record_exam_result(): void
{
    try {
        verify_learner_access('learners_nonce');

        $trackingId = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        if (!$trackingId) {
            throw new Exception('tracking_id is required.');
        }

        $stepString = isset($_POST['exam_step']) ? sanitize_key($_POST['exam_step']) : '';
        $step = ExamStep::tryFromString($stepString);
        if ($step === null) {
            throw new Exception('Invalid or missing exam_step. Valid steps: mock_1, mock_2, mock_3, sba, final.');
        }

        if (!isset($_POST['percentage']) || $_POST['percentage'] === '') {
            throw new Exception('percentage is required.');
        }
        $percentage = floatval($_POST['percentage']);
        if ($percentage < 0 || $percentage > 100) {
            throw new Exception('percentage must be between 0 and 100.');
        }

        // Optional file upload for SBA/final steps
        $file = null;
        if (isset($_FILES['exam_file']) && !empty($_FILES['exam_file']['tmp_name'])) {
            $file = $_FILES['exam_file'];
        }

        $service = new ExamService();
        $result = $service->recordExamResult(
            $trackingId,
            $step,
            $percentage,
            $file,
            get_current_user_id()
        );

        if (!$result['success']) {
            throw new Exception($result['error']);
        }

        // --- LP auto-completion check ---
        $lpCompleted = false;
        $lpError     = null;
        if ($service->isExamComplete($trackingId)) {
            $progressionModel = LearnerProgressionModel::getById($trackingId);
            if ($progressionModel && $progressionModel->isCompleted()) {
                error_log("WeCoza ExamAjax: LP already completed for tracking_id={$trackingId}, skipping");
            } elseif ($progressionModel) {
                try {
                    if ($progressionModel->markComplete(get_current_user_id())) {
                        $lpCompleted = true;
                        error_log("WeCoza ExamAjax: LP auto-completed for tracking_id={$trackingId}");
                    }
                } catch (Exception $markEx) {
                    $lpError = $markEx->getMessage();
                    error_log("WeCoza ExamAjax: markComplete failed for tracking_id={$trackingId} - " . $lpError);
                }
            }
        }

        $responseData = [
            'message'      => 'Exam result recorded successfully.',
            'data'         => $result['data'],
            'lp_completed' => $lpCompleted,
        ];
        if ($lpError !== null) {
            $responseData['lp_error'] = $lpError;
        }
        wp_send_json_success($responseData);

    } catch (Exception $e) {
        error_log("WeCoza ExamAjax: handle_record_exam_result - " . $e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Fetch exam progress for a learner LP tracking entry.
 *
 * GET params: tracking_id
 *
 * AJAX action: get_exam_progress
 */
function handle_get_exam_progress(): void
{
    try {
        verify_learner_access('learners_nonce');

        $trackingId = isset($_GET['tracking_id']) ? intval($_GET['tracking_id']) : 0;
        if (!$trackingId) {
            throw new Exception('tracking_id is required.');
        }

        $service = new ExamService();
        $progress = $service->getExamProgress($trackingId);

        // Enrich step data with derived fields for client-side rendering
        if (!empty($progress['steps'])) {
            foreach ($progress['steps'] as $stepKey => &$stepData) {
                if ($stepData === null) {
                    continue;
                }
                // Resolve recorded_by user name
                if (!empty($stepData['recorded_by'])) {
                    $user = get_userdata((int) $stepData['recorded_by']);
                    $stepData['recorded_by_name'] = $user ? $user->display_name : 'User #' . $stepData['recorded_by'];
                }
                // Resolve file URL from file_path
                if (!empty($stepData['file_path'])) {
                    $stepData['file_url'] = content_url(str_replace(WP_CONTENT_DIR, '', $stepData['file_path']));
                }
            }
            unset($stepData);
        }

        wp_send_json_success($progress);

    } catch (Exception $e) {
        error_log("WeCoza ExamAjax: handle_get_exam_progress - " . $e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Delete an exam result for a learner LP tracking entry.
 *
 * POST params: tracking_id, exam_step
 *
 * AJAX action: delete_exam_result
 */
function handle_delete_exam_result(): void
{
    try {
        verify_learner_access('learners_nonce');

        $trackingId = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        if (!$trackingId) {
            throw new Exception('tracking_id is required.');
        }

        $stepString = isset($_POST['exam_step']) ? sanitize_key($_POST['exam_step']) : '';
        $step = ExamStep::tryFromString($stepString);
        if ($step === null) {
            throw new Exception('Invalid or missing exam_step. Valid steps: mock_1, mock_2, mock_3, sba, final.');
        }

        $service = new ExamService();
        $result = $service->deleteExamResult($trackingId, $step);

        if (!$result['success']) {
            throw new Exception($result['error']);
        }

        wp_send_json_success([
            'message' => 'Exam result deleted successfully.',
        ]);

    } catch (Exception $e) {
        error_log("WeCoza ExamAjax: handle_delete_exam_result - " . $e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Register all exam AJAX handlers
 */
function register_exam_ajax_handlers(): void
{
    add_action('wp_ajax_record_exam_result',  __NAMESPACE__ . '\handle_record_exam_result');
    add_action('wp_ajax_get_exam_progress',   __NAMESPACE__ . '\handle_get_exam_progress');
    add_action('wp_ajax_delete_exam_result',   __NAMESPACE__ . '\handle_delete_exam_result');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_exam_ajax_handlers');
