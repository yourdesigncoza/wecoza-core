<?php
declare(strict_types=1);

/**
 * Agent Orders & Invoices AJAX Handlers
 *
 * Handles AJAX requests for agent orders and monthly invoices using the AjaxSecurity pattern.
 *
 * @package WeCoza\Agents
 * @since 3.0.0
 */

namespace WeCoza\Agents\Ajax;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Agents\Services\AgentOrderService;
use WeCoza\Agents\Services\AgentInvoiceService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Orders & Invoices AJAX Handlers
 *
 * Provides six AJAX endpoints:
 *   - wecoza_order_save      (POST, manage_options)
 *   - wecoza_order_get       (POST, capture_attendance)
 *   - wecoza_invoice_calculate (POST, capture_attendance) — pure read
 *   - wecoza_invoice_submit  (POST, capture_attendance)
 *   - wecoza_invoice_review  (POST, manage_options)
 *   - wecoza_invoice_list    (POST, manage_options)
 *
 * All endpoints require nonce (wecoza_orders_nonce). No nopriv handlers.
 *
 * @since 3.0.0
 */
class AgentOrdersAjaxHandlers
{
    /**
     * Order service instance
     *
     * @var AgentOrderService
     */
    private AgentOrderService $orderService;

    /**
     * Invoice service instance
     *
     * @var AgentInvoiceService
     */
    private AgentInvoiceService $invoiceService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->orderService   = new AgentOrderService();
        $this->invoiceService = new AgentInvoiceService();
        $this->registerHandlers();
    }

    /**
     * Register AJAX handlers
     *
     * @return void
     */
    private function registerHandlers(): void
    {
        // No nopriv handlers — entire WP environment requires login
        add_action('wp_ajax_wecoza_order_save',          [$this, 'handleOrderSave']);
        add_action('wp_ajax_wecoza_order_get',           [$this, 'handleOrderGet']);
        add_action('wp_ajax_wecoza_invoice_calculate',   [$this, 'handleCalculate']);
        add_action('wp_ajax_wecoza_invoice_submit',      [$this, 'handleSubmit']);
        add_action('wp_ajax_wecoza_invoice_review',      [$this, 'handleReview']);
        add_action('wp_ajax_wecoza_invoice_list',        [$this, 'handleList']);
    }

    /**
     * Save order rate (POST, manage_options)
     *
     * Expects: order_id (int), rate_type (string), rate_amount (float)
     *
     * @return void
     */
    public function handleOrderSave(): void
    {
        AjaxSecurity::requireNonce('wecoza_orders_nonce');
        AjaxSecurity::requireCapability('manage_options');

        try {
            $orderId     = AjaxSecurity::post('order_id', 'int', 0);
            $rateType    = AjaxSecurity::post('rate_type', 'string', '');
            $rateAmount  = (float) AjaxSecurity::post('rate_amount', 'float', 0.0);

            if ($orderId <= 0) {
                AjaxSecurity::sendError('Invalid order ID.', 400);
            }

            if (!in_array($rateType, ['hourly', 'daily'], true)) {
                AjaxSecurity::sendError('rate_type must be "hourly" or "daily".', 400);
            }

            if ($rateAmount < 0) {
                AjaxSecurity::sendError('rate_amount must be >= 0.', 400);
            }

            $success = $this->orderService->saveOrderRate($orderId, $rateType, $rateAmount);

            if ($success) {
                AjaxSecurity::sendSuccess(['order_id' => $orderId], 'Order rate saved.');
            } else {
                AjaxSecurity::sendError('Failed to save order rate.', 500);
            }
        } catch (\Throwable $e) {
            wecoza_log('AgentOrdersAjaxHandlers::handleOrderSave error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred.', 500);
        }
    }

    /**
     * Get active order for a class/agent pair (POST, capture_attendance)
     *
     * Expects: class_id (int), agent_id (int)
     *
     * @return void
     */
    public function handleOrderGet(): void
    {
        AjaxSecurity::requireNonce('wecoza_orders_nonce');
        AjaxSecurity::requireCapability('capture_attendance');

        try {
            $classId = AjaxSecurity::post('class_id', 'int', 0);
            $agentId = AjaxSecurity::post('agent_id', 'int', 0);

            if ($classId <= 0 || $agentId <= 0) {
                AjaxSecurity::sendError('Invalid class_id or agent_id.', 400);
            }

            $order = $this->orderService->getActiveOrder($classId, $agentId);

            if ($order === null) {
                AjaxSecurity::sendError('No active order found for this class/agent combination.', 404);
            }

            AjaxSecurity::sendSuccess(['order' => $order]);
        } catch (\Throwable $e) {
            wecoza_log('AgentOrdersAjaxHandlers::handleOrderGet error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred.', 500);
        }
    }

    /**
     * Calculate invoice summary for a month (POST, capture_attendance) — pure read, no DB write
     *
     * Expects: class_id (int), agent_id (int), invoice_month (string, format: YYYY-MM)
     *
     * Returns: class_hours_total, all_absent_days, all_absent_hours, calculated_payable_hours
     *
     * @return void
     */
    public function handleCalculate(): void
    {
        AjaxSecurity::requireNonce('wecoza_orders_nonce');
        AjaxSecurity::requireCapability('capture_attendance');

        try {
            $classId   = AjaxSecurity::post('class_id', 'int', 0);
            $agentId   = AjaxSecurity::post('agent_id', 'int', 0);
            $rawMonth  = AjaxSecurity::post('invoice_month', 'string', '');

            if ($classId <= 0 || $agentId <= 0) {
                AjaxSecurity::sendError('Invalid class_id or agent_id.', 400);
            }

            if (!preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
                AjaxSecurity::sendError('invoice_month must be in YYYY-MM format.', 400);
            }

            // Normalise to first day of month so service can handle any date in month
            $invoiceMonth = $rawMonth . '-01';

            $summary = $this->invoiceService->calculateMonthSummary($classId, $agentId, $invoiceMonth);

            AjaxSecurity::sendSuccess($summary);
        } catch (\Throwable $e) {
            wecoza_log('AgentOrdersAjaxHandlers::handleCalculate error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred.', 500);
        }
    }

    /**
     * Submit invoice for a month (POST, capture_attendance)
     *
     * Stores discrepancy between claimed and calculated hours via service.
     *
     * Expects: class_id (int), agent_id (int), invoice_month (string, YYYY-MM),
     *          claimed_hours (float), notes (string, optional)
     *
     * @return void
     */
    public function handleSubmit(): void
    {
        AjaxSecurity::requireNonce('wecoza_orders_nonce');
        AjaxSecurity::requireCapability('capture_attendance');

        try {
            $classId      = AjaxSecurity::post('class_id', 'int', 0);
            $agentId      = AjaxSecurity::post('agent_id', 'int', 0);
            $rawMonth     = AjaxSecurity::post('invoice_month', 'string', '');
            $claimedHours = (float) AjaxSecurity::post('claimed_hours', 'float', 0.0);
            $notes        = AjaxSecurity::post('notes', 'string', null);

            if ($classId <= 0 || $agentId <= 0) {
                AjaxSecurity::sendError('Invalid class_id or agent_id.', 400);
            }

            if (!preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
                AjaxSecurity::sendError('invoice_month must be in YYYY-MM format.', 400);
            }

            if ($claimedHours < 0) {
                AjaxSecurity::sendError('claimed_hours must be >= 0.', 400);
            }

            // Normalise to first day of month
            $invoiceMonth = $rawMonth . '-01';

            $result = $this->invoiceService->submitInvoice(
                $classId,
                $agentId,
                $invoiceMonth,
                $claimedHours,
                $notes ?: null
            );

            AjaxSecurity::sendSuccess($result, 'Invoice submitted successfully.');
        } catch (\Throwable $e) {
            wecoza_log('AgentOrdersAjaxHandlers::handleSubmit error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred.', 500);
        }
    }

    /**
     * Review (approve or dispute) an invoice (POST, manage_options)
     *
     * Expects: invoice_id (int), status (string: 'approved' or 'disputed')
     *
     * @return void
     */
    public function handleReview(): void
    {
        AjaxSecurity::requireNonce('wecoza_orders_nonce');
        AjaxSecurity::requireCapability('manage_options');

        try {
            $invoiceId = AjaxSecurity::post('invoice_id', 'int', 0);
            $status    = AjaxSecurity::post('status', 'string', '');

            if ($invoiceId <= 0) {
                AjaxSecurity::sendError('Invalid invoice_id.', 400);
            }

            if (!in_array($status, ['approved', 'disputed'], true)) {
                AjaxSecurity::sendError('status must be "approved" or "disputed".', 400);
            }

            $success = $this->invoiceService->reviewInvoice($invoiceId, $status, get_current_user_id());

            if ($success) {
                AjaxSecurity::sendSuccess(['invoice_id' => $invoiceId, 'status' => $status], 'Invoice reviewed.');
            } else {
                AjaxSecurity::sendError('Failed to review invoice.', 500);
            }
        } catch (\Throwable $e) {
            wecoza_log('AgentOrdersAjaxHandlers::handleReview error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred.', 500);
        }
    }

    /**
     * List invoices for a class/agent pair (POST, manage_options)
     *
     * Expects: class_id (int), agent_id (int)
     *
     * @return void
     */
    public function handleList(): void
    {
        AjaxSecurity::requireNonce('wecoza_orders_nonce');
        AjaxSecurity::requireCapability('manage_options');

        try {
            $classId = AjaxSecurity::post('class_id', 'int', 0);
            $agentId = AjaxSecurity::post('agent_id', 'int', 0);

            if ($classId <= 0 || $agentId <= 0) {
                AjaxSecurity::sendError('Invalid class_id or agent_id.', 400);
            }

            $invoices = $this->invoiceService->getInvoicesForClassAgent($classId, $agentId);

            AjaxSecurity::sendSuccess(['invoices' => $invoices]);
        } catch (\Throwable $e) {
            wecoza_log('AgentOrdersAjaxHandlers::handleList error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred.', 500);
        }
    }
}
