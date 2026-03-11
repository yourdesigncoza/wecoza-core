<?php
declare(strict_types=1);

/**
 * Agent Order Service
 *
 * Business logic for agent order management. Handles idempotent order creation,
 * active order lookup, and rate updates.
 *
 * @package WeCoza\Agents\Services
 * @since 9.0.0
 */

namespace WeCoza\Agents\Services;

use WeCoza\Agents\Repositories\AgentOrderRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Order Service
 *
 * @since 9.0.0
 */
class AgentOrderService
{
    /**
     * @var AgentOrderRepository
     */
    private AgentOrderRepository $orderRepo;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->orderRepo = new AgentOrderRepository();
    }

    /**
     * Ensure an order row exists for the given class+agent pair
     *
     * Creates the order if absent. Idempotent — safe to call on every class save.
     * Only creates an order when both class_id and agent_id are non-zero.
     *
     * @param int         $classId           Class ID
     * @param int         $agentId           Agent ID
     * @param string|null $originalStartDate Optional start date (Y-m-d). Defaults to today.
     * @return int|null                      order_id or null on failure / missing IDs
     */
    public function ensureOrderForClass(int $classId, int $agentId, ?string $originalStartDate = null): ?int
    {
        if ($classId <= 0 || $agentId <= 0) {
            return null;
        }

        $startDate = $originalStartDate ?? date('Y-m-d');

        return $this->orderRepo->ensureOrderExists(
            $classId,
            $agentId,
            $startDate,
            (int) get_current_user_id()
        );
    }

    /**
     * Get the active order for a class+agent pair
     *
     * @param int $classId Class ID
     * @param int $agentId Agent ID
     * @return array|null  Order row or null if none active
     */
    public function getActiveOrder(int $classId, int $agentId): ?array
    {
        return $this->orderRepo->findActiveOrderForClass($classId, $agentId);
    }

    /**
     * Update the rate on an existing order
     *
     * @param int    $orderId    Order ID to update
     * @param string $rateType   Rate type: 'hourly' or 'daily'
     * @param float  $rateAmount Rate amount (non-negative)
     * @return bool              True on success, false on invalid input or DB failure
     */
    public function saveOrderRate(int $orderId, string $rateType, float $rateAmount): bool
    {
        if (!in_array($rateType, ['hourly', 'daily'], true)) {
            return false;
        }

        return $this->orderRepo->update($orderId, [
            'rate_type'  => $rateType,
            'rate_amount' => $rateAmount,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
