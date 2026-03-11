<?php
/**
 * Single Class Display - Agent Monthly Invoice Component
 *
 * Displays the monthly invoice section for both admin and wp_agent roles.
 * Allows agents to view monthly summaries (class hours, absent days/hours,
 * payable hours) and submit claimed hours for payment reconciliation.
 *
 * After submission the form swaps to a read-only state showing the invoice
 * status, claimed hours, discrepancy (if any), and notes.
 *
 * Admins see the full invoice list status for the selected month.
 * Agents always see the claim form; the submit endpoint enforces uniqueness.
 *
 * Hidden when:
 *   - Class has no assigned agent
 *   - Class status is 'draft'
 *
 * JS in assets/js/classes/agent-invoice.js drives all AJAX interactions.
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables (from $component_data):
 *   - $class: Array of class data from the database
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];

// Return early if no class data or no assigned agent
if (empty($class) || empty($class['class_agent'])) {
    return;
}

// Return early if class is still in draft
$classStatus = wecoza_resolve_class_status($class);
if ($classStatus === 'draft') {
    return;
}

// Default month picker value to current YYYY-MM
$currentMonth = wp_date('Y-m');
?>

<!-- Agent Monthly Invoice Section -->
<div class="card mb-4" id="agent-invoice-card">
    <div class="card-header">
        <h4 class="mb-0">
            <i class="bi bi-receipt me-2"></i><?= esc_html__('Monthly Invoice', 'wecoza-core'); ?>
        </h4>
    </div>
    <div class="card-body">

        <!-- Month Picker -->
        <div class="row mb-4">
            <div class="col-md-4">
                <label for="invoice-month-picker" class="form-label form-label-sm">
                    <?= esc_html__('Invoice Month', 'wecoza-core'); ?>
                </label>
                <input
                    type="month"
                    class="form-control form-control-sm"
                    id="invoice-month-picker"
                    value="<?= esc_attr($currentMonth); ?>"
                >
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="invoice-loading" class="d-none text-center py-3">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            <span class="text-muted"><?= esc_html__('Loading invoice data...', 'wecoza-core'); ?></span>
        </div>

        <!-- Alert Container -->
        <div id="invoice-alert"></div>

        <!-- Invoice Summary (shown after calculate) -->
        <div id="invoice-summary" class="d-none mb-4">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 bg-body-highlight">
                        <div class="card-body p-3 text-center">
                            <h6 class="text-body-tertiary small mb-1"><?= esc_html__('Class Hours', 'wecoza-core'); ?></h6>
                            <span class="fs-5 fw-bold" id="inv-class-hours">—</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 bg-body-highlight">
                        <div class="card-body p-3 text-center">
                            <h6 class="text-body-tertiary small mb-1"><?= esc_html__('Absent Days', 'wecoza-core'); ?></h6>
                            <span class="fs-5 fw-bold" id="inv-absent-days">—</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 bg-body-highlight">
                        <div class="card-body p-3 text-center">
                            <h6 class="text-body-tertiary small mb-1"><?= esc_html__('Absent Hours', 'wecoza-core'); ?></h6>
                            <span class="fs-5 fw-bold" id="inv-absent-hours">—</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 bg-body-highlight">
                        <div class="card-body p-3 text-center">
                            <h6 class="text-body-tertiary small mb-1"><?= esc_html__('Payable Hours', 'wecoza-core'); ?></h6>
                            <span class="fs-5 fw-bold text-success" id="inv-payable-hours">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Claim Form (shown when no submitted invoice for this month) -->
        <div id="invoice-claim-form" class="d-none">
            <hr class="mb-4">
            <h6 class="mb-3"><?= esc_html__('Submit Claim', 'wecoza-core'); ?></h6>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="inv-claimed-hours" class="form-label form-label-sm">
                        <?= esc_html__('Claimed Hours', 'wecoza-core'); ?>
                    </label>
                    <input
                        type="number"
                        class="form-control form-control-sm"
                        id="inv-claimed-hours"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                    >
                </div>
                <div class="col-md-6">
                    <label for="inv-claim-notes" class="form-label form-label-sm">
                        <?= esc_html__('Notes (optional)', 'wecoza-core'); ?>
                    </label>
                    <textarea
                        class="form-control form-control-sm"
                        id="inv-claim-notes"
                        rows="2"
                        placeholder="<?= esc_attr__('Any additional context for this claim...', 'wecoza-core'); ?>"
                    ></textarea>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-phoenix-success btn-sm w-100" id="btn-submit-invoice">
                        <i class="bi bi-send me-1"></i><?= esc_html__('Submit Claim', 'wecoza-core'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Submitted State (read-only, shown when invoice already submitted/approved/disputed) -->
        <div id="invoice-submitted" class="d-none">
            <hr class="mb-4">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span id="inv-status-badge"></span>
                <span class="text-muted small">
                    <?= esc_html__('Claimed Hours:', 'wecoza-core'); ?>
                    <strong id="inv-submitted-hours">—</strong>
                </span>
                <span id="inv-discrepancy-badge" class="d-none"></span>
            </div>
            <p id="inv-submitted-notes" class="text-body-tertiary small mt-2 mb-0 d-none"></p>
        </div>

    </div>
</div>
