<?php
declare(strict_types=1);

/**
 * AJAX Handlers for Class Attendance Operations
 *
 * Provides AJAX handlers for attendance operations: session list, capture,
 * mark exception, session detail, and admin delete.
 *
 * All handlers validate nonce via check_ajax_referer and return structured
 * JSON via wp_send_json_success / wp_send_json_error.
 * No wp_ajax_nopriv_ actions — site requires login per project policy.
 *
 * @package WeCoza\Classes\Ajax
 * @since 1.0.0
 */

namespace WeCoza\Classes\Ajax;

use WeCoza\Classes\Services\AttendanceService;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verify the attendance nonce on every request.
 *
 * Shared helper so nonce validation is never duplicated across handlers.
 * Calls wp_send_json_error and exits immediately on failure.
 */
function verify_attendance_nonce(): void
{
    if (!check_ajax_referer('wecoza_attendance_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        exit;
    }
}

/**
 * Guard attendance capture/exception endpoints to active classes only.
 *
 * Queries class_status via wecoza_resolve_class_status() and rejects non-active
 * classes with a 403 response. Does NOT guard view-only or admin-delete endpoints —
 * those remain accessible on any class status for audit integrity.
 *
 * Note: class_status = 'stopped' is class deactivation (access control).
 *       stop_restart_dates is schedule-pause logic. These are distinct concepts.
 *
 * @param int $classId The class ID to check.
 */
function require_active_class(int $classId): void
{
    $pdo  = wecoza_db()->getPdo();
    $stmt = $pdo->prepare('SELECT class_status, order_nr FROM classes WHERE class_id = :class_id');
    $stmt->execute([':class_id' => $classId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
        wp_send_json_error(['message' => __('Class not found.', 'wecoza-core')], 404);
        exit;
    }

    $status = wecoza_resolve_class_status($row);
    if ($status !== 'active') {
        wp_send_json_error(['message' => __('Attendance capture is only allowed for active classes.', 'wecoza-core')], 403);
        exit;
    }
}

/**
 * Return the session list for a class, merged with captured session status.
 *
 * AJAX action: wecoza_attendance_get_sessions
 * Method: GET
 * Params: class_id (int, required)
 */
function handle_attendance_get_sessions(): void
{
    try {
        verify_attendance_nonce();

        $classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

        if ($classId <= 0) {
            throw new Exception('class_id is required.');
        }

        $service  = new AttendanceService();
        $sessions = $service->generateSessionList($classId);

        wp_send_json_success(['sessions' => $sessions]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Capture attendance for a class session.
 *
 * Normalizes camelCase POST keys (learnerId / hoursPresent) to snake_case
 * and validates that hours_present is within 0 and the session's scheduled hours.
 *
 * AJAX action: wecoza_attendance_capture
 * Method: POST
 * Params: class_id (int), session_date (YYYY-MM-DD), learner_hours (array)
 */
function handle_attendance_capture(): void
{
    try {
        verify_attendance_nonce();

        $classId     = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $sessionDate = isset($_POST['session_date']) ? sanitize_text_field($_POST['session_date']) : '';

        if ($classId <= 0) {
            throw new Exception('class_id is required.');
        }

        require_active_class($classId);

        if (empty($sessionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate)) {
            throw new Exception('session_date must be a valid YYYY-MM-DD date.');
        }

        if ($sessionDate > date('Y-m-d')) {
            throw new Exception('Cannot capture attendance for a future date.');
        }

        $rawLearnerHours = isset($_POST['learner_hours']) && is_array($_POST['learner_hours'])
            ? $_POST['learner_hours']
            : [];

        // Normalize: accept both camelCase and snake_case keys from JS
        $normalizedHours = [];
        foreach ($rawLearnerHours as $entry) {
            $learnerId    = isset($entry['learner_id'])   ? (int)   $entry['learner_id']
                          : (isset($entry['learnerId'])   ? (int)   $entry['learnerId']   : 0);
            $hoursPresent = isset($entry['hours_present']) ? (float) $entry['hours_present']
                          : (isset($entry['hoursPresent']) ? (float) $entry['hoursPresent'] : 0.0);

            if ($learnerId <= 0) {
                continue; // skip invalid entries
            }

            $normalizedHours[] = [
                'learner_id'    => $learnerId,
                'hours_present' => $hoursPresent,
            ];
        }

        // Range validation: hours_present must be 0 <= x <= scheduledHours
        $service        = new AttendanceService();
        $sessions       = $service->generateSessionList($classId);
        $scheduledHours = 0.0;

        foreach ($sessions as $s) {
            if ($s['date'] === $sessionDate) {
                $scheduledHours = (float) $s['scheduled_hours'];
                break;
            }
        }

        foreach ($normalizedHours as $entry) {
            if ($entry['hours_present'] < 0 || $entry['hours_present'] > $scheduledHours) {
                throw new Exception(
                    "hours_present must be between 0 and {$scheduledHours} for learner {$entry['learner_id']}"
                );
            }
        }

        $result = $service->captureAttendance($classId, $sessionDate, $normalizedHours, get_current_user_id());

        wp_send_json_success($result);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Mark a session as an exception (client_cancelled or agent_absent).
 *
 * Normalizes camelCase exceptionType from JS to snake_case.
 *
 * AJAX action: wecoza_attendance_mark_exception
 * Method: POST
 * Params: class_id (int), session_date (YYYY-MM-DD), exception_type (string), notes (string, optional)
 */
function handle_attendance_mark_exception(): void
{
    try {
        verify_attendance_nonce();

        $classId = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;

        if ($classId <= 0) {
            throw new Exception('class_id is required.');
        }

        require_active_class($classId);

        $sessionDate = isset($_POST['session_date']) ? sanitize_text_field($_POST['session_date']) : '';

        if (empty($sessionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate)) {
            throw new Exception('session_date must be a valid YYYY-MM-DD date.');
        }

        if ($sessionDate > date('Y-m-d')) {
            throw new Exception('Cannot mark exception for a future date.');
        }

        // Normalize: accept both camelCase (exceptionType) and snake_case (exception_type)
        $exceptionType = isset($_POST['exception_type'])
            ? sanitize_key($_POST['exception_type'])
            : (isset($_POST['exceptionType']) ? sanitize_key($_POST['exceptionType']) : '');

        if (empty($exceptionType)) {
            throw new Exception('exception_type is required.');
        }

        $notes = !empty($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : null;

        $service = new AttendanceService();
        $result  = $service->markException($classId, $sessionDate, $exceptionType, get_current_user_id(), $notes);

        wp_send_json_success($result);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Return per-learner hours breakdown for a captured session.
 *
 * AJAX action: wecoza_attendance_get_detail
 * Method: GET
 * Params: session_id (int, required)
 */
function handle_attendance_get_detail(): void
{
    try {
        verify_attendance_nonce();

        $sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

        if ($sessionId <= 0) {
            throw new Exception('session_id is required.');
        }

        $service = new AttendanceService();
        $result  = $service->getSessionDetail($sessionId);

        wp_send_json_success($result);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Admin delete: remove a session and reverse any associated learner hours.
 *
 * Requires manage_options capability — admin-only operation for audit integrity.
 *
 * AJAX action: wecoza_attendance_admin_delete
 * Method: POST
 * Params: session_id (int, required)
 */
function handle_attendance_admin_delete(): void
{
    try {
        verify_attendance_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $sessionId = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;

        if ($sessionId <= 0) {
            throw new Exception('session_id is required.');
        }

        $service = new AttendanceService();
        $service->deleteAndReverseHours($sessionId, get_current_user_id());

        wp_send_json_success(['deleted' => true, 'session_id' => $sessionId]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Register all attendance AJAX handlers
 */
function register_attendance_ajax_handlers(): void
{
    add_action('wp_ajax_wecoza_attendance_get_sessions',  __NAMESPACE__ . '\handle_attendance_get_sessions');
    add_action('wp_ajax_wecoza_attendance_capture',       __NAMESPACE__ . '\handle_attendance_capture');
    add_action('wp_ajax_wecoza_attendance_mark_exception', __NAMESPACE__ . '\handle_attendance_mark_exception');
    add_action('wp_ajax_wecoza_attendance_get_detail',    __NAMESPACE__ . '\handle_attendance_get_detail');
    add_action('wp_ajax_wecoza_attendance_admin_delete',  __NAMESPACE__ . '\handle_attendance_admin_delete');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_attendance_ajax_handlers');
