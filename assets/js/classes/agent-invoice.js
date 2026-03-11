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
 *   - wecoza_invoice_review   POST approve or dispute an invoice (admin only)
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
            loadReconciliationTable();
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
        $(document).on('click', '.btn-approve', function () {
            const invoiceId = parseInt($(this).data('invoice-id'), 10);
            if (invoiceId > 0) {
                handleReviewAction(invoiceId, 'approved');
            }
        });
        $(document).on('click', '.btn-dispute', function () {
            const invoiceId = parseInt($(this).data('invoice-id'), 10);
            if (invoiceId > 0) {
                handleReviewAction(invoiceId, 'disputed');
            }
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
                    if (config.isAdmin) {
                        loadReconciliationTable();
                    }
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
    // RECONCILIATION TABLE (admin only)
    // =========================================================

    /**
     * Load all invoices for the class/agent pair and populate the
     * reconciliation table. Called on page load and after submitInvoice().
     */
    function loadReconciliationTable() {
        $('#reconciliation-loading').removeClass('d-none');
        $('#reconciliation-table-wrapper').addClass('d-none');
        $('#reconciliation-empty').addClass('d-none');
        $('#reconciliation-alert').html('');

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
                $('#reconciliation-loading').addClass('d-none');

                if (response.success && response.data && Array.isArray(response.data.invoices)) {
                    const invoices = response.data.invoices;
                    if (invoices.length === 0) {
                        $('#reconciliation-empty').removeClass('d-none');
                    } else {
                        renderReconciliationRows(invoices);
                        $('#reconciliation-table-wrapper').removeClass('d-none');
                    }
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to load reconciliation data.';
                    $('#reconciliation-alert').html(
                        '<div class="alert alert-subtle-warning alert-dismissible fade show" role="alert">'
                        + escapeHtml(msg)
                        + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                        + '</div>'
                    );
                }
            },
            error: function () {
                $('#reconciliation-loading').addClass('d-none');
                $('#reconciliation-alert').html(
                    '<div class="alert alert-subtle-danger alert-dismissible fade show" role="alert">'
                    + 'Request failed. Please try again.'
                    + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                    + '</div>'
                );
            }
        });
    }

    /**
     * Render table rows into #reconciliation-tbody from the invoices array.
     *
     * Row highlighting:
     *   - discrepancy > 0 (overclaim): table-danger (red)
     *   - discrepancy === 0 and status !== 'draft': table-success (green)
     *
     * @param {Array} invoices  Array of invoice objects from wecoza_invoice_list.
     */
    function renderReconciliationRows(invoices) {
        let html = '';

        invoices.forEach(function (inv) {
            const month = (inv.invoice_month || '').substring(0, 7);
            const monthDisplay = month
                ? new Date(month + '-15').toLocaleDateString('en-ZA', { year: 'numeric', month: 'long' })
                : '—';

            const classHours  = formatHours(inv.class_hours_total);
            const claimed     = formatHours(inv.agent_claimed_hours);
            const payable     = formatHours(inv.calculated_payable_hours);
            const discrepancy = parseFloat(inv.discrepancy_hours) || 0;
            const status      = inv.status || 'draft';

            // Row highlight class
            let rowClass = '';
            if (discrepancy > 0) {
                rowClass = 'table-danger';
            } else if (discrepancy === 0 && status !== 'draft') {
                rowClass = 'table-success';
            }

            // Discrepancy cell content
            let discrepancyCell;
            if (status === 'draft' || inv.agent_claimed_hours === null || inv.agent_claimed_hours === undefined) {
                discrepancyCell = '—';
            } else if (discrepancy > 0) {
                discrepancyCell = '<span class="text-danger fw-bold">+' + discrepancy + ' hrs</span>';
            } else if (discrepancy < 0) {
                discrepancyCell = '<span class="text-success">' + discrepancy + ' hrs</span>';
            } else {
                discrepancyCell = '<span class="text-success"><i class="bi bi-check-circle"></i> 0</span>';
            }

            // Status badge
            const statusBadge = '<span class="badge badge-phoenix ' + statusBadgeClass(status) + '">'
                + escapeHtml(capitalise(status)) + '</span>';

            // Actions cell
            let actionsCell;
            if (status === 'submitted') {
                actionsCell = '<button class="btn btn-phoenix-success btn-sm me-1 btn-approve" data-invoice-id="' + inv.invoice_id + '">'
                    + '<i class="bi bi-check-lg"></i> Approve'
                    + '</button>'
                    + '<button class="btn btn-phoenix-danger btn-sm btn-dispute" data-invoice-id="' + inv.invoice_id + '">'
                    + '<i class="bi bi-x-lg"></i> Dispute'
                    + '</button>';
            } else if (status === 'approved') {
                actionsCell = '<span class="text-success small"><i class="bi bi-check-circle-fill"></i> Approved</span>';
            } else if (status === 'disputed') {
                actionsCell = '<span class="text-danger small"><i class="bi bi-exclamation-triangle-fill"></i> Disputed</span>';
            } else {
                actionsCell = '<span class="text-muted small">Pending submission</span>';
            }

            html += '<tr class="' + rowClass + '" data-invoice-id="' + inv.invoice_id + '">'
                + '<td>' + escapeHtml(monthDisplay) + '</td>'
                + '<td class="text-end">' + escapeHtml(classHours) + '</td>'
                + '<td class="text-end">' + escapeHtml(claimed) + '</td>'
                + '<td class="text-end">' + escapeHtml(payable) + '</td>'
                + '<td class="text-end">' + discrepancyCell + '</td>'
                + '<td>' + statusBadge + '</td>'
                + '<td>' + actionsCell + '</td>'
                + '</tr>';
        });

        $('#reconciliation-tbody').html(html);
    }

    /**
     * Send an approve or dispute action for a specific invoice.
     * Updates the affected table row in-place without reloading the table.
     *
     * @param {number} invoiceId  The invoice primary key.
     * @param {string} newStatus  'approved' or 'disputed'.
     */
    function handleReviewAction(invoiceId, newStatus) {
        const $row = $('tr[data-invoice-id="' + invoiceId + '"]');
        const $actionsCell = $row.find('td:last-child');

        // Show spinner while request is in flight
        const originalContent = $actionsCell.html();
        $actionsCell.html('<span class="spinner-border spinner-border-sm" role="status"></span>');

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_invoice_review',
                _ajax_nonce: config.ordersNonce,
                invoice_id: invoiceId,
                status: newStatus
            },
            success: function (response) {
                if (response.success) {
                    // Update status badge cell (second-to-last td)
                    const $statusCell = $row.find('td').eq(5);
                    $statusCell.html(
                        '<span class="badge badge-phoenix ' + statusBadgeClass(newStatus) + '">'
                        + escapeHtml(capitalise(newStatus)) + '</span>'
                    );

                    // Update actions cell
                    if (newStatus === 'approved') {
                        $actionsCell.html('<span class="text-success small"><i class="bi bi-check-circle-fill"></i> Approved</span>');
                        $row.removeClass('table-danger').addClass('table-success');
                    } else {
                        $actionsCell.html('<span class="text-danger small"><i class="bi bi-exclamation-triangle-fill"></i> Disputed</span>');
                    }
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to update invoice status.';
                    $actionsCell.html(originalContent);
                    $('#reconciliation-alert').html(
                        '<div class="alert alert-subtle-danger alert-dismissible fade show" role="alert">'
                        + escapeHtml(msg)
                        + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                        + '</div>'
                    );
                }
            },
            error: function () {
                $actionsCell.html(originalContent);
                $('#reconciliation-alert').html(
                    '<div class="alert alert-subtle-danger alert-dismissible fade show" role="alert">'
                    + 'Request failed. Please try again.'
                    + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                    + '</div>'
                );
            }
        });
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
