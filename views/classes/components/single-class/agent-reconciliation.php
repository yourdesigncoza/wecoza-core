<?php
/**
 * Single Class Display - Agent Invoice Reconciliation Component
 *
 * Admin-only table showing all monthly invoices for the class/agent pair.
 * Enables admins to review submitted invoices, spot overclaims via red
 * row highlighting, and approve or dispute each month individually.
 *
 * Visible only to users with manage_options capability.
 * Hidden when no agent is assigned to the class.
 *
 * JS in assets/js/classes/agent-invoice.js drives all AJAX interactions:
 *   - loadReconciliationTable() populates rows via wecoza_invoice_list
 *   - handleReviewAction() handles approve/dispute via wecoza_invoice_review
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables (from $component_data):
 *   - $class: Array of class data from the database
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Admin-only guard
if (!current_user_can('manage_options')) {
    return;
}

// Ensure variables are available
$class = $class ?? [];

// Return early if no class data or no assigned agent
if (empty($class) || empty($class['class_agent'])) {
    return;
}
?>

<!-- Agent Invoice Reconciliation (admin only) -->
<div class="card mb-4" id="agent-reconciliation-card">
    <div class="card-header">
        <h4 class="mb-0">
            <i class="bi bi-clipboard-check me-2"></i><?= esc_html__('Invoice Reconciliation', 'wecoza-core'); ?>
        </h4>
    </div>
    <div class="card-body">

        <!-- Loading Spinner -->
        <div id="reconciliation-loading" class="text-center py-3">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            <span class="text-muted"><?= esc_html__('Loading reconciliation data...', 'wecoza-core'); ?></span>
        </div>

        <!-- Alert Container -->
        <div id="reconciliation-alert"></div>

        <!-- Empty State -->
        <div id="reconciliation-empty" class="d-none text-center text-muted py-3">
            <i class="bi bi-inbox me-2"></i><?= esc_html__('No invoices found for this class.', 'wecoza-core'); ?>
        </div>

        <!-- Reconciliation Table -->
        <div class="table-responsive d-none" id="reconciliation-table-wrapper">
            <table class="table table-sm table-hover" id="reconciliation-table">
                <thead>
                    <tr>
                        <th><?= esc_html__('Month', 'wecoza-core'); ?></th>
                        <th class="text-end"><?= esc_html__('Class Hours', 'wecoza-core'); ?></th>
                        <th class="text-end"><?= esc_html__('Claimed Hours', 'wecoza-core'); ?></th>
                        <th class="text-end"><?= esc_html__('Payable Hours', 'wecoza-core'); ?></th>
                        <th class="text-end"><?= esc_html__('Discrepancy', 'wecoza-core'); ?></th>
                        <th><?= esc_html__('Status', 'wecoza-core'); ?></th>
                        <th><?= esc_html__('Actions', 'wecoza-core'); ?></th>
                    </tr>
                </thead>
                <tbody id="reconciliation-tbody">
                    <!-- Populated by JS via wecoza_invoice_list -->
                </tbody>
            </table>
        </div>

    </div>
</div>
