<?php
declare(strict_types=1);

namespace WeCoza\Events\Controllers;

use WeCoza\Events\Services\MaterialTrackingDashboardService;
use WeCoza\Events\Repositories\MaterialTrackingRepository;
use WeCoza\Core\Database\PostgresConnection;

use function __;
use function absint;
use function add_action;
use function check_ajax_referer;

/**
 * Controller for material tracking AJAX actions
 */
final class MaterialTrackingController
{
    public function __construct(
        private readonly MaterialTrackingDashboardService $service,
        private readonly JsonResponder $responder
    ) {
    }

    /**
     * Register AJAX hooks
     */
    public static function register(?self $controller = null): void
    {
        $instance = $controller ?? new self(
            new MaterialTrackingDashboardService(
                new MaterialTrackingRepository(
                    PostgresConnection::getInstance()->getPdo()
                )
            ),
            new JsonResponder()
        );

        add_action('wp_ajax_wecoza_mark_material_delivered', [$instance, 'handleMarkDelivered']);
        add_action('wp_ajax_nopriv_wecoza_mark_material_delivered', [$instance, 'handleUnauthorized']);
    }

    /**
     * Handle unauthorized access
     */
    public function handleUnauthorized(): void
    {
        $this->responder->error(__('Authentication required.', 'wecoza-events'), 401);
    }

    /**
     * Handle mark as delivered action
     */
    public function handleMarkDelivered(): void
    {
        check_ajax_referer('wecoza_material_tracking_action', 'nonce');

        $classId = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $eventIndex = isset($_POST['event_index']) ? absint($_POST['event_index']) : null;

        if ($classId <= 0) {
            $this->responder->error(__('Invalid class ID.', 'wecoza-events'), 400);
        }

        if ($eventIndex === null) {
            $this->responder->error(__('Missing event index.', 'wecoza-events'), 400);
        }

        $success = $this->service->markAsDelivered($classId, $eventIndex);

        if ($success) {
            $this->responder->success([
                'message' => __('Materials marked as delivered successfully.', 'wecoza-events'),
                'class_id' => $classId,
                'event_index' => $eventIndex,
            ]);
        } else {
            $this->responder->error(__('Failed to mark materials as delivered. Please try again.', 'wecoza-events'), 500);
        }
    }
}
