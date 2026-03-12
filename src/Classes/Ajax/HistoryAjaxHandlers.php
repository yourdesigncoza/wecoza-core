<?php
declare(strict_types=1);

/**
 * AJAX Handlers for Entity History
 *
 * Provides AJAX endpoint for fetching entity relationship
 * history timelines via HistoryService.
 *
 * @package WeCoza\Classes\Ajax
 * @since 1.1.0
 */

namespace WeCoza\Classes\Ajax;

use WeCoza\Classes\Services\HistoryService;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX handlers for entity history.
 */
function register_history_ajax_handlers(): void
{
    add_action('wp_ajax_wecoza_get_entity_history', __NAMESPACE__ . '\handle_get_entity_history');
    // No wp_ajax_nopriv_ — site requires login per project policy.
}

/**
 * Handle entity history AJAX request.
 *
 * POST params:
 *  - nonce: Security nonce
 *  - entity_type: 'class', 'agent', 'learner', or 'client'
 *  - entity_id: Integer ID
 *
 * Returns: JSON with timeline data for the requested entity.
 */
function handle_get_entity_history(): void
{
    try {
        // Verify nonce
        if (!check_ajax_referer('wecoza_history_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid security token.'], 403);
            return;
        }

        $entityType = isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : '';
        $entityId = isset($_POST['entity_id']) ? intval($_POST['entity_id']) : 0;

        if (empty($entityType) || $entityId <= 0) {
            wp_send_json_error(['message' => 'entity_type and entity_id are required.'], 400);
            return;
        }

        $validTypes = ['class', 'agent', 'learner', 'client'];
        if (!in_array($entityType, $validTypes, true)) {
            wp_send_json_error([
                'message' => 'Invalid entity_type. Must be one of: ' . implode(', ', $validTypes),
            ], 400);
            return;
        }

        $service = new HistoryService();

        $timeline = match ($entityType) {
            'class' => $service->getClassTimeline($entityId),
            'agent' => $service->getAgentTimeline($entityId),
            'learner' => $service->getLearnerTimeline($entityId),
            'client' => $service->getClientTimeline($entityId),
        };

        wp_send_json_success([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'timeline' => $timeline,
        ]);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Failed to load entity history.',
            'error' => WP_DEBUG ? $e->getMessage() : null,
        ], 500);
    }
}

add_action('init', __NAMESPACE__ . '\register_history_ajax_handlers');
