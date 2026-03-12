<?php
declare(strict_types=1);

/**
 * WeCoza Core - Excessive Hours AJAX Handlers
 *
 * AJAX endpoints for the excessive hours report dashboard.
 * Provides data loading and resolution actions.
 *
 * @package WeCoza\Reports\ExcessiveHours
 * @since 1.0.0
 */

namespace WeCoza\Reports\ExcessiveHours;

use WeCoza\Core\Helpers\AjaxSecurity;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle AJAX request to get excessive hours flagged learners.
 *
 * Supports DataTable server-side processing.
 * Action: wecoza_get_excessive_hours
 */
function handle_get_excessive_hours(): void
{
    try {
        AjaxSecurity::requireNonce('excessive_hours_nonce');

        $service = new ExcessiveHoursService();

        // Map DataTable parameters
        $params = [
            'draw'            => AjaxSecurity::post('draw', 'int', 1),
            'start'           => AjaxSecurity::post('start', 'int', 0),
            'length'          => AjaxSecurity::post('length', 'int', 50),
            'status'          => AjaxSecurity::post('filter_status', 'string', 'open'),
            'client_id'       => AjaxSecurity::post('filter_client_id', 'int'),
            'class_type_code' => AjaxSecurity::post('filter_class_type', 'string'),
            'search'          => AjaxSecurity::post('search_value', 'string'),
        ];

        // Handle DataTable's built-in search
        if (empty($params['search']) && isset($_POST['search']['value'])) {
            $params['search'] = sanitize_text_field($_POST['search']['value']);
        }

        // Handle DataTable's built-in ordering
        if (isset($_POST['order'][0])) {
            $columnMap = [
                0 => 'learner_name',
                1 => 'class_code',
                2 => 'class_type_name',
                3 => 'client_name',
                4 => 'hours_trained',
                5 => 'subject_duration',
                6 => 'overage_hours',
                7 => 'flag_status',
            ];
            $colIdx = (int) ($_POST['order'][0]['column'] ?? 6);
            $params['order_by'] = $columnMap[$colIdx] ?? 'overage_hours';
            $params['order_dir'] = ($_POST['order'][0]['dir'] ?? 'desc');
        }

        $result = $service->getFlaggedLearners($params);

        wp_send_json([
            'draw'            => $result['draw'] ?? 1,
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['total'],
            'data'            => $result['data'],
            'open_count'      => $result['open_count'],
            'resolved_count'  => $result['resolved_count'],
            'demo_mode'       => $result['demo_mode'] ?? false,
        ]);
    } catch (Exception $e) {
        wecoza_log("Excessive hours AJAX error: " . $e->getMessage(), 'error');
        AjaxSecurity::sendError($e->getMessage(), 500);
    }
}

/**
 * Handle AJAX request to resolve an excessive hours flag.
 *
 * Action: wecoza_resolve_excessive_hours
 */
function handle_resolve_excessive_hours(): void
{
    try {
        AjaxSecurity::requireNonce('excessive_hours_nonce');

        // Validate required fields
        AjaxSecurity::requireFields($_POST, ['tracking_id', 'action_taken']);

        $trackingId = AjaxSecurity::requireValidId($_POST['tracking_id'] ?? 0, 'tracking_id');
        $actionTaken = AjaxSecurity::sanitizeString($_POST['action_taken'] ?? '');
        $notes = AjaxSecurity::sanitizeTextarea($_POST['resolution_notes'] ?? '');

        // Validate action_taken against whitelist
        if (!in_array($actionTaken, ExcessiveHoursRepository::ALLOWED_ACTIONS, true)) {
            AjaxSecurity::sendError(
                'Invalid action. Allowed: ' . implode(', ', array_values(ExcessiveHoursService::ACTION_LABELS)),
                400
            );
            return;
        }

        $service = new ExcessiveHoursService();
        $result = $service->resolveFlag($trackingId, $actionTaken, $notes ?: null);

        AjaxSecurity::sendSuccess($result, 'Flag resolved successfully.');
    } catch (Exception $e) {
        wecoza_log("Excessive hours resolve error: " . $e->getMessage(), 'error');
        AjaxSecurity::sendError($e->getMessage(), 400);
    }
}

/**
 * Handle AJAX request to get resolution history for a tracking record.
 *
 * Action: wecoza_get_excessive_hours_history
 */
function handle_get_excessive_hours_history(): void
{
    try {
        AjaxSecurity::requireNonce('excessive_hours_nonce');

        $trackingId = AjaxSecurity::requireValidId($_POST['tracking_id'] ?? 0, 'tracking_id');

        $service = new ExcessiveHoursService();
        $history = $service->getResolutionHistory($trackingId);

        // Enrich with user names
        foreach ($history as &$row) {
            if (!empty($row['resolved_by'])) {
                $user = get_userdata((int) $row['resolved_by']);
                $row['resolved_by_name'] = $user ? $user->display_name : 'Unknown';
            }
            if (!empty($row['created_at'])) {
                $row['created_at_display'] = wp_date('j M Y, H:i', strtotime($row['created_at']));
            }
            $row['action_label'] = ExcessiveHoursService::ACTION_LABELS[$row['action_taken']] ?? $row['action_taken'];
        }

        AjaxSecurity::sendSuccess(['history' => $history]);
    } catch (Exception $e) {
        AjaxSecurity::sendError($e->getMessage(), 400);
    }
}

/*
|--------------------------------------------------------------------------
| Register AJAX Actions
|--------------------------------------------------------------------------
*/

add_action('wp_ajax_wecoza_get_excessive_hours', __NAMESPACE__ . '\handle_get_excessive_hours');
add_action('wp_ajax_wecoza_resolve_excessive_hours', __NAMESPACE__ . '\handle_resolve_excessive_hours');
add_action('wp_ajax_wecoza_get_excessive_hours_history', __NAMESPACE__ . '\handle_get_excessive_hours_history');
