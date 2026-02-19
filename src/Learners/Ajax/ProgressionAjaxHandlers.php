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
use WeCoza\Events\DTOs\ClassEventDTO;
use WeCoza\Events\Enums\EventType;
use WeCoza\Events\Repositories\ClassEventRepository;
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

        // Enrich with context from DB so the audit record is self-contained
        $enrichedLearners = build_collision_learner_context($learnerIds);
        $classContext      = build_collision_class_context($classId);

        // For new classes (class_id=0), fall back to form-supplied values
        $classCode = $classContext['class_code']
            ?? (isset($_POST['class_code']) ? sanitize_text_field($_POST['class_code']) : null)
            ?: null;
        $classType = $classContext['class_type']
            ?? (isset($_POST['class_type']) ? sanitize_text_field($_POST['class_type']) : null)
            ?: null;

        $dto = ClassEventDTO::create(
            eventType: EventType::LP_COLLISION,
            entityType: 'class',
            entityId: $classId,
            eventData: [
                'action'               => 'lp_collision_acknowledged',
                'affected_learners'    => $enrichedLearners,
                'class_id'             => $classId,
                'class_code'           => $classCode,
                'class_type'           => $classType,
                'acknowledged_by'      => $userId,
                'acknowledged_at'      => wp_date('c'),
            ],
            userId: $userId,
        );

        // Set notification_status to 'sent' so this audit event skips the enrichment/email pipeline
        $dto = $dto->withStatus('sent');

        $repo    = new ClassEventRepository();
        $eventId = $repo->insertEvent($dto);

        wp_send_json_success(['logged' => true, 'event_id' => $eventId]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Build enriched learner context for LP collision events
 *
 * Fetches learner names and their active LP details so the audit
 * record is self-contained and readable without joins.
 *
 * @param int[] $learnerIds
 * @return array<int, array{learner_id: int, name: string, active_lp: array|null}>
 */
function build_collision_learner_context(array $learnerIds): array
{
    if (empty($learnerIds)) {
        return [];
    }

    $db  = wecoza_db();
    $pdo = $db->getPdo();

    // Fetch learner names
    $placeholders = implode(',', array_fill(0, count($learnerIds), '?'));
    $sql = "SELECT id, first_name, surname FROM learners WHERE id IN ({$placeholders})";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($learnerIds));
    $learnerRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $nameMap = [];
    foreach ($learnerRows as $row) {
        $nameMap[(int) $row['id']] = trim($row['first_name'] . ' ' . $row['surname']);
    }

    // Fetch active LP info per learner
    $sql = <<<SQL
SELECT t.learner_id, t.tracking_id, t.status, t.class_id,
       cts.subject_name, cts.subject_code
FROM learner_lp_tracking t
JOIN class_type_subjects cts ON cts.class_type_subject_id = t.class_type_subject_id
WHERE t.learner_id IN ({$placeholders})
  AND t.status = 'in_progress'
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($learnerIds));
    $lpRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $lpMap = [];
    foreach ($lpRows as $row) {
        $lpMap[(int) $row['learner_id']] = [
            'tracking_id'  => (int) $row['tracking_id'],
            'status'       => $row['status'],
            'class_id'     => $row['class_id'] ? (int) $row['class_id'] : null,
            'subject_name' => $row['subject_name'],
            'subject_code' => $row['subject_code'],
        ];
    }

    // Merge into enriched array
    $result = [];
    foreach ($learnerIds as $id) {
        $result[] = [
            'learner_id' => $id,
            'name'       => $nameMap[$id] ?? 'Unknown',
            'active_lp'  => $lpMap[$id] ?? null,
        ];
    }

    return $result;
}

/**
 * Build class context for LP collision events
 *
 * @param int $classId
 * @return array{class_code: string|null, class_type: string|null}
 */
function build_collision_class_context(int $classId): array
{
    if ($classId <= 0) {
        return ['class_code' => null, 'class_type' => null];
    }

    $db  = wecoza_db();
    $pdo = $db->getPdo();

    $sql = <<<SQL
SELECT c.class_code, ct.class_type_name
FROM classes c
LEFT JOIN class_types ct ON ct.class_type_id = c.class_type
WHERE c.class_id = ?
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$classId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    return [
        'class_code' => $row['class_code'] ?? null,
        'class_type' => $row['class_type_name'] ?? null,
    ];
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
        if (!empty($_GET['class_type_subject_id'])) {
            $filters['class_type_subject_id'] = intval($_GET['class_type_subject_id']);
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
                'subject_name'     => $progression->getSubjectName(),
                'status'           => $progression->getStatus(),
                'hours_present'    => $progression->getHoursPresent(),
                'hours_trained'    => $progression->getHoursTrained(),
                'subject_duration' => $progression->getSubjectDuration(),
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

        $classTypeSubjectId = isset($_POST['class_type_subject_id']) ? intval($_POST['class_type_subject_id']) : 0;
        if (!$classTypeSubjectId) {
            throw new Exception('class_type_subject_id is required.');
        }

        $classId = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
        $notes   = !empty($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : null;

        $service    = new ProgressionService();
        $progression = $service->startLearnerProgression($learnerId, $classTypeSubjectId, $classId, $notes);

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
 * Return employer-grouped learner progression data for the report page.
 *
 * Accepts GET params: search, employer_id, status.
 * Returns { groups: [...], summary: {...} }.
 *
 * AJAX action: get_progression_report
 */
function handle_get_progression_report(): void
{
    try {
        verify_learner_access('learners_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        // Build validated filters from GET params
        $allowedStatuses = ['in_progress', 'completed', 'on_hold'];
        $filters         = [];

        if (!empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field($_GET['search']);
        }

        if (!empty($_GET['employer_id'])) {
            $filters['employer_id'] = intval($_GET['employer_id']);
        }

        if (!empty($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true)) {
            $filters['status'] = sanitize_key($_GET['status']);
        }

        $repository = new LearnerProgressionRepository();
        $data       = $repository->findForReport($filters);
        $stats      = $repository->getReportSummaryStats($filters);

        // Group flat rows by employer, then by learner within each employer
        $grouped = [];

        foreach ($data as $row) {
            $employerId   = $row['employer_id'] ?? 0;
            $employerName = $row['employer_name'] ?? 'Unassigned';

            if (!isset($grouped[$employerId])) {
                $grouped[$employerId] = [
                    'employer_id'   => $employerId,
                    'employer_name' => $employerName,
                    'learners'      => [],
                ];
            }

            $learnerId = $row['learner_id'];

            if (!isset($grouped[$employerId]['learners'][$learnerId])) {
                $grouped[$employerId]['learners'][$learnerId] = [
                    'learner_id'   => $learnerId,
                    'learner_name' => $row['learner_name'],
                    'progressions' => [],
                ];
            }

            $grouped[$employerId]['learners'][$learnerId]['progressions'][] = $row;
        }

        // Re-index learners arrays to sequential JSON arrays
        foreach ($grouped as &$group) {
            $group['learners'] = array_values($group['learners']);
        }
        unset($group);
        $grouped = array_values($grouped);

        wp_send_json_success([
            'groups'  => $grouped,
            'summary' => $stats,
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
    add_action('wp_ajax_get_progression_report',           __NAMESPACE__ . '\handle_get_progression_report');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_progression_ajax_handlers');
