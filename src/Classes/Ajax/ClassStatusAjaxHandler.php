<?php
declare(strict_types=1);

/**
 * AJAX Handlers for Class Status Transitions
 *
 * Provides AJAX handlers for class status management: activate (draft→active),
 * stop (active→stopped), reactivate (stopped→active), and status history.
 *
 * All handlers validate nonce via check_ajax_referer and require manage_options
 * capability. Transitions are wrapped in DB transactions (CC2) with idempotency
 * guards (CC3) and full input sanitization (CC4).
 *
 * No wp_ajax_nopriv_ actions — site requires login per project policy.
 *
 * Note: class_status = 'stopped' is class deactivation (access control).
 *       stop_restart_dates is schedule-pause logic. These are distinct concepts.
 *
 * @package WeCoza\Classes\Ajax
 * @since 1.0.0
 */

namespace WeCoza\Classes\Ajax;

use PDO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Valid stop reasons for stopping a class.
 */
const CLASS_STATUS_STOP_REASONS = ['programme_ended', 'temporary_hold', 'annual_stop'];

/**
 * Valid target statuses accepted by the update endpoint.
 */
const CLASS_STATUS_ALLOWED_TARGETS = ['active', 'stopped'];

/**
 * Verify the class status nonce on every request.
 *
 * Shared helper so nonce validation is never duplicated across handlers.
 * Uses the existing 'wecoza_class_nonce' created by ClassController::enqueueAndLocalizeSingleClassScript().
 * Calls wp_send_json_error and exits immediately on failure.
 */
function verify_class_status_nonce(): void
{
    if (!check_ajax_referer('wecoza_class_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'wecoza-core')]);
        exit;
    }
}

/**
 * Handle class status transitions: draft→active, active→stopped, stopped→active.
 *
 * Enforces idempotency (CC3) — same-state transitions rejected with 400.
 * Wraps UPDATE + INSERT in a DB transaction (CC2).
 * On draft→active, writes order_nr_metadata JSON for CC7 compatibility.
 *
 * AJAX action: wecoza_class_status_update
 * Method: POST
 * Params:
 *   - class_id   (int, required)
 *   - new_status (string, required) — 'active' or 'stopped'
 *   - order_nr   (string, required when new_status='active' and class is draft)
 *   - stop_reason (string, required when new_status='stopped') — one of CLASS_STATUS_STOP_REASONS
 *   - notes      (string, optional)
 */
function handle_class_status_update(): void
{
    verify_class_status_nonce();

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'wecoza-core')], 403);
        exit;
    }

    // --- Input extraction and sanitization (CC4) ---
    $classId    = absint($_POST['class_id'] ?? 0);
    $newStatus  = sanitize_key($_POST['new_status'] ?? '');
    $orderNr    = sanitize_text_field($_POST['order_nr'] ?? '');
    $stopReason = sanitize_key($_POST['stop_reason'] ?? '');
    $notes      = sanitize_textarea_field($_POST['notes'] ?? '');

    if ($classId <= 0) {
        wp_send_json_error(['message' => __('class_id is required.', 'wecoza-core')], 400);
        exit;
    }

    // Whitelist validate new_status
    if (!in_array($newStatus, CLASS_STATUS_ALLOWED_TARGETS, true)) {
        wp_send_json_error(['message' => __('Invalid status value.', 'wecoza-core')], 400);
        exit;
    }

    // --- Pre-validate inputs that don't need DB state ---
    $currentUserId = get_current_user_id();

    if ($newStatus === 'active') {
        $normalisedOrderNr = \WeCoza\Events\Services\TaskManager::normaliseOrderNumber($orderNr);
    }

    if ($newStatus === 'stopped' && !in_array($stopReason, CLASS_STATUS_STOP_REASONS, true)) {
        wp_send_json_error(['message' => __('A valid stop reason is required.', 'wecoza-core')], 400);
        exit;
    }

    // --- DB Transaction (CC2) with row-level lock for concurrency safety ---
    $pdo = wecoza_db()->getPdo();
    $pdo->beginTransaction();
    try {
        // Lock the row and fetch current status inside the transaction
        $stmt = $pdo->prepare('SELECT class_status, order_nr FROM classes WHERE class_id = :class_id FOR UPDATE');
        $stmt->execute([':class_id' => $classId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            wp_send_json_error(['message' => __('Class not found.', 'wecoza-core')], 404);
            exit;
        }

        $currentStatus = wecoza_resolve_class_status($row);

        // --- Idempotency guard (CC3) ---
        if ($currentStatus === $newStatus) {
            $pdo->rollBack();
            wp_send_json_error(
                ['message' => sprintf(__('Class is already %s.', 'wecoza-core'), $newStatus)],
                400
            );
            exit;
        }

        // --- Transition validation ---
        if ($currentStatus === 'draft' && $newStatus === 'active') {
            if ($normalisedOrderNr === '') {
                $pdo->rollBack();
                wp_send_json_error(['message' => __('An order number is required to activate this class.', 'wecoza-core')], 400);
                exit;
            }
        } elseif ($currentStatus === 'active' && $newStatus === 'stopped') {
            // stop_reason already validated above
        } elseif ($currentStatus === 'stopped' && $newStatus === 'active') {
            // no extra requirements (order_nr already exists)
        } else {
            $pdo->rollBack();
            wp_send_json_error(
                ['message' => sprintf(__('Invalid status transition: %s → %s.', 'wecoza-core'), $currentStatus, $newStatus)],
                400
            );
            exit;
        }
        if ($currentStatus === 'draft' && $newStatus === 'active') {
            // Write order_nr and order_nr_metadata for CC7 compatibility
            $metadata = json_encode([
                'completed_by' => $currentUserId,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            $updateStmt = $pdo->prepare(
                "UPDATE classes
                 SET class_status = 'active',
                     order_nr = :order_nr,
                     order_nr_metadata = :metadata,
                     updated_at = NOW()
                 WHERE class_id = :class_id"
            );
            $updateStmt->execute([
                ':order_nr'  => $normalisedOrderNr,
                ':metadata'  => $metadata,
                ':class_id'  => $classId,
            ]);
        } elseif ($currentStatus === 'active' && $newStatus === 'stopped') {
            $updateStmt = $pdo->prepare(
                "UPDATE classes SET class_status = 'stopped', updated_at = NOW() WHERE class_id = :class_id"
            );
            $updateStmt->execute([':class_id' => $classId]);
        } elseif ($currentStatus === 'stopped' && $newStatus === 'active') {
            $updateStmt = $pdo->prepare(
                "UPDATE classes SET class_status = 'active', updated_at = NOW() WHERE class_id = :class_id"
            );
            $updateStmt->execute([':class_id' => $classId]);
        }

        // Record status history for every transition
        $histStmt = $pdo->prepare(
            "INSERT INTO class_status_history (class_id, old_status, new_status, reason, notes, changed_by)
             VALUES (:class_id, :old_status, :new_status, :reason, :notes, :changed_by)"
        );
        $histStmt->execute([
            ':class_id'   => $classId,
            ':old_status' => $currentStatus,
            ':new_status' => $newStatus,
            ':reason'     => ($newStatus === 'stopped') ? $stopReason : null,
            ':notes'      => $notes !== '' ? $notes : null,
            ':changed_by' => $currentUserId,
        ]);

        $pdo->commit();

    } catch (\Exception $e) {
        $pdo->rollBack();
        wecoza_log('Class status transition failed: ' . $e->getMessage(), 'error');
        wp_send_json_error(['message' => __('Status update failed. Please try again.', 'wecoza-core')], 500);
        exit;
    }

    wp_send_json_success([
        'status'  => $newStatus,
        'message' => __('Class status updated.', 'wecoza-core'),
    ]);
}

/**
 * Return the status history for a class.
 *
 * Resolves WP user display names in PHP (no cross-DB JOIN — wp_users is in MySQL,
 * class_status_history is in PostgreSQL; per Research Pitfall 7).
 *
 * AJAX action: wecoza_class_status_history
 * Method: POST
 * Params: class_id (int, required)
 */
function handle_class_status_history(): void
{
    verify_class_status_nonce();

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'wecoza-core')], 403);
        exit;
    }

    $classId = absint($_POST['class_id'] ?? 0);

    if ($classId <= 0) {
        wp_send_json_error(['message' => __('class_id is required.', 'wecoza-core')], 400);
        exit;
    }

    $pdo  = wecoza_db()->getPdo();
    $stmt = $pdo->prepare(
        "SELECT id, old_status, new_status, reason, notes, changed_by, changed_at
         FROM class_status_history
         WHERE class_id = :class_id
         ORDER BY changed_at DESC"
    );
    $stmt->execute([':class_id' => $classId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resolve WP user display names in PHP — no cross-DB JOIN possible
    foreach ($rows as &$row) {
        $user = get_userdata((int) $row['changed_by']);
        $row['changed_by_name'] = $user ? $user->display_name : __('Unknown', 'wecoza-core');
    }
    unset($row);

    wp_send_json_success(['history' => $rows]);
}

/**
 * Register all class status AJAX handlers.
 */
function register_class_status_ajax_handlers(): void
{
    add_action('wp_ajax_wecoza_class_status_update',  __NAMESPACE__ . '\handle_class_status_update');
    add_action('wp_ajax_wecoza_class_status_history', __NAMESPACE__ . '\handle_class_status_history');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_class_status_ajax_handlers');
