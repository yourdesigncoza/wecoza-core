/**
 * Agent Invoice JavaScript Module
 *
 * Handles the Agent Rate Card (admin only) and Monthly Invoice section
 * on the single class display page.
 *
 * Reads from window.WeCozaSingleClass:
 *   - classId       (int)    Class ID
 *   - classAgent    (int)    Agent user ID assigned to the class
 *   - ajaxUrl       (string) WordPress AJAX endpoint
 *   - ordersNonce   (string) Nonce for wecoza_orders_nonce
 *   - isAdmin       (bool)   Whether current user has manage_options
 *
 * AJAX endpoints consumed:
 *   - wecoza_order_get        GET  order for class+agent
 *   - wecoza_order_save       POST save rate type + amount
 *   - wecoza_invoice_calculate POST monthly summary (class hours, absent, payable)
 *   - wecoza_invoice_submit   POST submit claimed hours
 *   - wecoza_invoice_list     POST list invoices (admin only, manage_options)
 *
 * @package WeCoza_Classes
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    // =========================================================
    // CONFIG
    // =========================================================

    const config = window.WeCozaSingleClass || {};

    // Exit early if we're not on a single class page or no agent is assigned
    if (!config.classId || !config.classAgent) {
        return;
    }

    // Calculated payable hours cached for pre-filling the claim form
    let cachedPayableHours = 0;

    // =========================================================
    // DOM READY
    // =========================================================

    $(document).ready(function () {
        bindEvents();

        if (config.isAdmin) {
            loadOrder();
        }

        calculateInvoice();
    });

    // =========================================================
    // EVENT BINDINGS
    // =========================================================

    function bindEvents() {
        $(document).on('click', '#btn-save-agent-rate', saveRate);
        $(document).on('click', '#btn-submit-invoice', submitInvoice);
        $(document).on('change', '#invoice-month-picker', function () {
            calculateInvoice();
        });
    }

    // =========================================================
    // RATE CARD (admin only)
    // =========================================================

    /**
     * Load the current agent order (rate type + amount) for this class.
     * Populates the rate card inputs and enables them for editing.
     */
    function loadOrder() {
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_order_get',
                _ajax_nonce: config.ordersNonce,
                class_id: config.classId,
                agent_id: config.classAgent
            },
            success: function (response) {
                if (response.success && response.data && response.data.order) {
                    const order = response.data.order;
                    $('#agent-order-id').val(order.order_id || '');
                    $('#agent-rate-type').val(order.rate_type || '');
                    $('#agent-rate-amount').val(order.rate_amount || '');
                    enableRateInputs();
                } else {
                    // No order found — still allow editing (will create on save)
                    showRateStatus(
                        'No existing order found — enter a rate and save.',
                        'text-warning'
                    );
                    enableRateInputs();
                }
            },
            error: function () {
                showRateStatus('Failed to load order data.', 'text-danger');
            }
        });
    }

    /**
     * Enable rate card inputs and save button after order is loaded.
     */
    function enableRateInputs() {
        $('#agent-rate-type').prop('disabled', false);
        $('#agent-rate-amount').prop('disabled', false);
        $('#btn-save-agent-rate').prop('disabled', false);
    }

    /**
     * Save the rate type and amount for the agent order.
     */
    function saveRate() {
        const orderId = parseInt($('#agent-order-id').val(), 10) || 0;
        const rateType = $('#agent-rate-type').val();
        const rateAmount = parseFloat($('#agent-rate-amount').val());

        if (!rateType) {
            showRateStatus('Please select a rate type.', 'text-danger');
            return;
        }

        if (isNaN(rateAmount) || rateAmount < 0) {
            showRateStatus('Please enter a valid rate amount.', 'text-danger');
            return;
        }

        const $btn = $('#btn-save-agent-rate');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_order_save',
                _ajax_nonce: config.ordersNonce,
                order_id: orderId,
                rate_type: rateType,
                rate_amount: rateAmount
            },
            success: function (response) {
                if (response.success) {
                    // Store returned order_id in case it was newly created
                    if (response.data && response.data.order_id) {
                        $('#agent-order-id').val(response.data.order_id);
                    }
                    showRateStatus('Saved', 'text-success');
                    setTimeout(function () {
                        showRateStatus('', '');
                    }, 2000);
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to save rate.';
                    showRateStatus(msg, 'text-danger');
                }
            },
            error: function () {
                showRateStatus('Request failed. Please try again.', 'text-danger');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="bi bi-floppy me-1"></i>Save Rate');
            }
        });
    }

    /**
     * Show a status message next to the rate save button.
     *
     * @param {string} msg   The message text.
     * @param {string} cls   CSS class for colour (e.g. 'text-success').
     */
    function showRateStatus(msg, cls) {
        const $status = $('#agent-rate-status');
        $status.removeClass('text-success text-danger text-warning').addClass(cls).text(msg);
    }

    // =========================================================
    // MONTHLY INVOICE
    // =========================================================

    /**
     * Call wecoza_invoice_calculate for the selected month and populate
     * the summary row. Then determine which state to show (claim form or
     * submitted state).
     */
    function calculateInvoice() {
        const month = $('#invoice-month-picker').val();
        if (!month) {
            return;
        }

        showInvoiceLoading(true);
        hideInvoiceStates();
        clearInvoiceAlert();

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_invoice_calculate',
                _ajax_nonce: config.ordersNonce,
                class_id: config.classId,
                agent_id: config.classAgent,
                invoice_month: month
            },
            success: function (response) {
                showInvoiceLoading(false);

                if (response.success && response.data) {
                    const data = response.data;
                    populateSummary(data);
                    $('#invoice-summary').removeClass('d-none');
                    loadInvoiceStatus(month);
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to load invoice data.';
                    showInvoiceAlert(msg, 'warning');
                }
            },
            error: function () {
                showInvoiceLoading(false);
                showInvoiceAlert('Request failed. Please try again.', 'danger');
            }
        });
    }

    /**
     * Populate the summary metric cards from the calculate response.
     *
     * @param {Object} data  Response data from wecoza_invoice_calculate.
     */
    function populateSummary(data) {
        $('#inv-class-hours').text(formatHours(data.class_hours_total));
        $('#inv-absent-days').text(data.all_absent_days !== undefined ? data.all_absent_days : '—');
        $('#inv-absent-hours').text(formatHours(data.all_absent_hours));
        $('#inv-payable-hours').text(formatHours(data.calculated_payable_hours));

        // Cache payable hours as default for the claim form
        cachedPayableHours = parseFloat(data.calculated_payable_hours) || 0;
    }

    /**
     * Determine whether to show the claim form or submitted state.
     *
     * Admins: call wecoza_invoice_list to find the invoice for this month.
     * Agents: always show the claim form (backend handles duplicate submit).
     *
     * @param {string} month  YYYY-MM string.
     */
    function loadInvoiceStatus(month) {
        if (config.isAdmin) {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wecoza_invoice_list',
                    _ajax_nonce: config.ordersNonce,
                    class_id: config.classId,
                    agent_id: config.classAgent
                },
                success: function (response) {
                    if (response.success && response.data && response.data.invoices) {
                        const invoices = response.data.invoices;
                        const matchedInvoice = findInvoiceForMonth(invoices, month);

                        if (matchedInvoice && matchedInvoice.status !== 'draft') {
                            showSubmittedState(matchedInvoice);
                        } else {
                            showClaimForm();
                        }
                    } else {
                        // Could not list invoices — fall back to claim form
                        showClaimForm();
                    }
                },
                error: function () {
                    // On error, fall back to claim form
                    showClaimForm();
                }
            });
        } else {
            // Agent role: always show claim form
            showClaimForm();
        }
    }

    /**
     * Find an invoice matching the given YYYY-MM month string.
     *
     * The server stores invoice_month as YYYY-MM-01; we compare prefix.
     *
     * @param {Array}  invoices  Array of invoice objects.
     * @param {string} month     YYYY-MM string.
     * @returns {Object|null}    Matching invoice or null.
     */
    function findInvoiceForMonth(invoices, month) {
        if (!Array.isArray(invoices)) {
            return null;
        }

        return invoices.find(function (inv) {
            const invMonth = (inv.invoice_month || '').substring(0, 7);
            return invMonth === month;
        }) || null;
    }

    // =========================================================
    // CLAIM SUBMISSION
    // =========================================================

    /**
     * Submit a claimed hours invoice for the selected month.
     */
    function submitInvoice() {
        const month = $('#invoice-month-picker').val();
        const claimedHours = parseFloat($('#inv-claimed-hours').val());
        const notes = $('#inv-claim-notes').val().trim();

        if (!month) {
            showInvoiceAlert('Please select an invoice month.', 'warning');
            return;
        }

        if (isNaN(claimedHours) || claimedHours < 0) {
            showInvoiceAlert('Please enter a valid number of claimed hours.', 'warning');
            return;
        }

        const $btn = $('#btn-submit-invoice');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Submitting...');

        const payload = {
            action: 'wecoza_invoice_submit',
            _ajax_nonce: config.ordersNonce,
            class_id: config.classId,
            agent_id: config.classAgent,
            invoice_month: month,
            claimed_hours: claimedHours
        };

        if (notes) {
            payload.notes = notes;
        }

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: payload,
            success: function (response) {
                if (response.success && response.data) {
                    const invoice = response.data;
                    $('#invoice-claim-form').addClass('d-none');
                    showSubmittedState(invoice);
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to submit invoice.';
                    showInvoiceAlert(msg, 'danger');
                    $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i>Submit Claim');
                }
            },
            error: function () {
                showInvoiceAlert('Request failed. Please try again.', 'danger');
                $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i>Submit Claim');
            }
        });
    }

    // =========================================================
    // STATE RENDERING
    // =========================================================

    /**
     * Show the claim form and pre-fill claimed hours with payable hours.
     */
    function showClaimForm() {
        if (cachedPayableHours > 0) {
            $('#inv-claimed-hours').val(cachedPayableHours.toFixed(2));
        }
        $('#invoice-claim-form').removeClass('d-none');
        $('#invoice-submitted').addClass('d-none');
    }

    /**
     * Render the read-only submitted state for an existing invoice.
     *
     * @param {Object} invoice  Invoice object with status, claimed_hours,
     *                          discrepancy_hours, notes fields.
     */
    function showSubmittedState(invoice) {
        // Status badge
        const statusCls = statusBadgeClass(invoice.status);
        const statusLabel = capitalise(invoice.status || 'submitted');
        $('#inv-status-badge').html(
            '<span class="badge badge-phoenix ' + statusCls + '">' + escapeHtml(statusLabel) + '</span>'
        );

        // Claimed hours
        $('#inv-submitted-hours').text(formatHours(invoice.claimed_hours));

        // Discrepancy warning
        const discrepancy = parseFloat(invoice.discrepancy_hours) || 0;
        if (discrepancy > 0) {
            $('#inv-discrepancy-badge')
                .html(
                    '<span class="badge badge-phoenix badge-phoenix-warning">'
                    + '<i class="bi bi-exclamation-triangle me-1"></i>Overclaim: '
                    + formatHours(discrepancy) + ' hrs'
                    + '</span>'
                )
                .removeClass('d-none');
        } else {
            $('#inv-discrepancy-badge').addClass('d-none');
        }

        // Notes
        if (invoice.notes) {
            $('#inv-submitted-notes').text(invoice.notes).removeClass('d-none');
        } else {
            $('#inv-submitted-notes').addClass('d-none');
        }

        $('#invoice-submitted').removeClass('d-none');
        $('#invoice-claim-form').addClass('d-none');
    }

    /**
     * Hide both claim form and submitted state (called before recalculate).
     */
    function hideInvoiceStates() {
        $('#invoice-claim-form').addClass('d-none');
        $('#invoice-submitted').addClass('d-none');
        $('#invoice-summary').addClass('d-none');
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /**
     * Show or hide the loading spinner.
     *
     * @param {boolean} show
     */
    function showInvoiceLoading(show) {
        if (show) {
            $('#invoice-loading').removeClass('d-none');
        } else {
            $('#invoice-loading').addClass('d-none');
        }
    }

    /**
     * Display an alert in the invoice alert container.
     *
     * @param {string} msg    Message text.
     * @param {string} type   Bootstrap alert type: 'warning', 'danger', etc.
     */
    function showInvoiceAlert(msg, type) {
        $('#invoice-alert').html(
            '<div class="alert alert-subtle-' + type + ' alert-dismissible fade show" role="alert">'
            + escapeHtml(msg)
            + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            + '</div>'
        );
    }

    /**
     * Clear the invoice alert container.
     */
    function clearInvoiceAlert() {
        $('#invoice-alert').html('');
    }

    /**
     * Map invoice status to a Phoenix badge CSS class.
     *
     * @param {string} status
     * @returns {string}
     */
    function statusBadgeClass(status) {
        switch (status) {
            case 'approved':  return 'badge-phoenix-success';
            case 'disputed':  return 'badge-phoenix-danger';
            case 'submitted': return 'badge-phoenix-info';
            default:          return 'badge-phoenix-secondary';
        }
    }

    /**
     * Format a numeric hours value for display.
     *
     * @param {*} value
     * @returns {string}
     */
    function formatHours(value) {
        const n = parseFloat(value);
        if (isNaN(n)) {
            return '—';
        }
        return n % 1 === 0 ? n.toFixed(0) : n.toFixed(2);
    }

    /**
     * Capitalise the first letter of a string.
     *
     * @param {string} str
     * @returns {string}
     */
    function capitalise(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Escape HTML special characters to prevent XSS in dynamic content.
     *
     * @param {string} str
     * @returns {string}
     */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

}(jQuery));
