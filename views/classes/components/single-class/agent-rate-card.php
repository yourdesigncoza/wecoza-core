<?php
/**
 * Single Class Display - Agent Rate Card Component
 *
 * Admin-only card for setting the rate type and amount for the agent
 * assigned to this class. Reads the current order on load via
 * wecoza_order_get AJAX and saves via wecoza_order_save AJAX.
 *
 * Visible only to users with manage_options capability.
 * Hidden when no agent is assigned to the class.
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

<!-- Agent Rate Card (admin only) -->
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title mb-3">
            <i class="bi bi-cash-stack me-2"></i><?= esc_html__('Agent Rate', 'wecoza-core'); ?>
        </h5>

        <input type="hidden" id="agent-order-id" value="">

        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="agent-rate-type" class="form-label form-label-sm">
                    <?= esc_html__('Rate Type', 'wecoza-core'); ?>
                </label>
                <select class="form-select form-select-sm" id="agent-rate-type" disabled>
                    <option value=""><?= esc_html__('Select...', 'wecoza-core'); ?></option>
                    <option value="hourly"><?= esc_html__('Hourly', 'wecoza-core'); ?></option>
                    <option value="daily"><?= esc_html__('Daily', 'wecoza-core'); ?></option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="agent-rate-amount" class="form-label form-label-sm">
                    <?= esc_html__('Rate Amount', 'wecoza-core'); ?> (R)
                </label>
                <input
                    type="number"
                    class="form-control form-control-sm"
                    id="agent-rate-amount"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    disabled
                >
            </div>

            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="button" class="btn btn-phoenix-primary btn-sm" id="btn-save-agent-rate" disabled>
                    <i class="bi bi-floppy me-1"></i><?= esc_html__('Save Rate', 'wecoza-core'); ?>
                </button>
                <span id="agent-rate-status" class="small"></span>
            </div>
        </div>

    </div>
</div>
