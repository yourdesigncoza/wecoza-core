/**
 * Regulatory Export JavaScript Module
 *
 * Wires all interactivity for the regulatory export page:
 * date-range and filter controls, AJAX data fetch, compliance table
 * rendering, client dropdown population, and CSV file download trigger.
 *
 * @package WeCoza_Learners
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const cfg = window.regulatoryExportAjax || {};

    // Cached server response rows
    let currentData = [];

    // Guard: client dropdown populated only once from the initial load
    let clientDropdownPopulated = false;

    // =========================================================
    // DOM READY
    // =========================================================

    $(initRegulatoryExport);

    // =========================================================
    // SECTION 1: INITIALISATION
    // =========================================================

    /**
     * Set default date range to previous month and kick off initial fetch.
     */
    function initRegulatoryExport() {
        setDefaultDateRange();
        bindEvents();
        fetchReport();
    }

    /**
     * Populate date inputs with first and last day of the previous month.
     */
    function setDefaultDateRange() {
        const today = new Date();
        const firstOfThisMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastOfPrevMonth  = new Date(firstOfThisMonth - 1);
        const firstOfPrevMonth = new Date(lastOfPrevMonth.getFullYear(), lastOfPrevMonth.getMonth(), 1);

        $('#reg-date-from').val(formatDate(firstOfPrevMonth));
        $('#reg-date-to').val(formatDate(lastOfPrevMonth));
    }

    /**
     * Format a Date object as YYYY-MM-DD.
     *
     * @param {Date} date
     * @returns {string}
     */
    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    // =========================================================
    // SECTION 2: EVENT BINDING
    // =========================================================

    /**
     * Attach all event listeners for filter controls.
     */
    function bindEvents() {
        // Generate button
        $('#btn-reg-generate').on('click', function() {
            fetchReport();
        });

        // Export CSV button
        $('#btn-reg-export-csv').on('click', function() {
            handleExportCsv();
        });

        // Enter key on date inputs triggers fetch
        $('#reg-date-from, #reg-date-to').on('keypress', function(e) {
            if (e.which === 13) {
                fetchReport();
            }
        });
    }

    // =========================================================
    // SECTION 3: AJAX FETCH
    // =========================================================

    /**
     * Fetch report data using current filter values.
     * Updates table, record count, and export button state.
     */
    function fetchReport() {
        const dateFrom = $('#reg-date-from').val();
        const dateTo   = $('#reg-date-to').val();

        if (!dateFrom || !dateTo) {
            showAlert('Please select both a start date and an end date.', 'warning');
            return;
        }

        showLoading();

        $.ajax({
            url:  cfg.ajaxurl,
            type: 'GET',
            data: {
                action:    'get_regulatory_report',
                nonce:     cfg.nonce,
                date_from: dateFrom,
                date_to:   dateTo,
                status:    $('#reg-status-filter').val() || '',
                client_id: $('#reg-client-filter').val() || '',
            },
            success: function(response) {
                if (response.success && response.data) {
                    currentData = response.data.rows || [];
                    const total = response.data.total || 0;

                    $('#reg-record-count').text(total + (total === 1 ? ' record' : ' records'));

                    // Enable/disable export button
                    if (total > 0) {
                        $('#btn-reg-export-csv').prop('disabled', false);
                    } else {
                        $('#btn-reg-export-csv').prop('disabled', true);
                    }

                    // Populate client dropdown once on first successful load
                    if (!clientDropdownPopulated && currentData.length > 0) {
                        populateClientDropdown(currentData);
                        clientDropdownPopulated = true;
                    }

                    renderTable(currentData);
                    hideLoading();
                } else {
                    hideLoading();
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to load report data.';
                    showAlert(msg, 'danger');
                }
            },
            error: function() {
                hideLoading();
                showAlert('Failed to load report data. Please try again.', 'danger');
            }
        });
    }

    // =========================================================
    // SECTION 4: TABLE RENDERING
    // =========================================================

    /**
     * Render the compliance table from a rows array.
     * Shows empty state when there are no rows.
     *
     * @param {Array} rows
     */
    function renderTable(rows) {
        const $tbody = $('#reg-table-body').empty();

        if (!rows || rows.length === 0) {
            $('#reg-table-card').addClass('d-none');
            $('#reg-empty').removeClass('d-none');
            return;
        }

        $('#reg-empty').addClass('d-none');
        $('#reg-table-card').removeClass('d-none');

        rows.forEach(function(row) {
            const saId = row.sa_id_no
                ? row.sa_id_no
                : (row.passport_number || '—');

            const programme = row.lp_name
                ? (row.lp_code ? row.lp_name + ' (' + row.lp_code + ')' : row.lp_name)
                : '—';

            const $tr = $('<tr>');
            $tr.append($('<td>').text(row.first_name || ''));
            $tr.append($('<td>').text(row.surname || ''));
            $tr.append($('<td>').text(saId));
            $tr.append($('<td>').text(programme));
            $tr.append($('<td>').text(row.class_code || '—'));
            $tr.append($('<td>').text(row.client_name || '—'));
            $tr.append($('<td>').text(row.employer_name || '—'));
            $tr.append($('<td>').text(row.start_date || '—'));
            $tr.append($('<td>').text(row.completion_date || '—'));
            $tr.append($('<td>').text(row.hours_trained || '0'));
            $tr.append($('<td>').text(row.hours_present || '0'));
            $tr.append($('<td>').text(row.hours_absent || '0'));
            $tr.append($('<td>').html(statusBadge(row.status)));
            $tr.append($('<td>').text(row.portfolio_submitted ? 'Yes' : 'No'));

            $tbody.append($tr);
        });
    }

    // =========================================================
    // SECTION 5: CLIENT DROPDOWN
    // =========================================================

    /**
     * Populate the client filter dropdown from rows data.
     * Extracts unique client names, sorts alphabetically, appends options.
     * Uses client_name as both value key and display text.
     *
     * @param {Array} rows
     */
    function populateClientDropdown(rows) {
        const $select = $('#reg-client-filter');

        // Guard: skip if already has client options beyond the default
        if ($select.find('option').length > 1) {
            return;
        }

        if (!rows || rows.length === 0) {
            return;
        }

        // Collect unique client names
        const seen    = {};
        const clients = [];

        rows.forEach(function(row) {
            if (row.client_name && !seen[row.client_name]) {
                seen[row.client_name] = true;
                clients.push(row.client_name);
            }
        });

        // Sort alphabetically
        clients.sort(function(a, b) {
            return a.localeCompare(b);
        });

        clients.forEach(function(name) {
            $select.append(
                $('<option>').val(name).text(name)
            );
        });
    }

    // =========================================================
    // SECTION 6: CSV EXPORT
    // =========================================================

    /**
     * Trigger CSV file download by redirecting to the export AJAX endpoint.
     * Browser handles the Content-Disposition: attachment response.
     */
    function handleExportCsv() {
        const dateFrom = $('#reg-date-from').val();
        const dateTo   = $('#reg-date-to').val();
        const status   = $('#reg-status-filter').val() || '';
        const clientId = $('#reg-client-filter').val() || '';

        const params = $.param({
            action:    'export_regulatory_csv',
            nonce:     cfg.nonce,
            date_from: dateFrom,
            date_to:   dateTo,
            status:    status,
            client_id: clientId,
        });

        window.location.href = cfg.ajaxurl + '?' + params;
    }

    // =========================================================
    // UTILITY FUNCTIONS
    // =========================================================

    /**
     * Return a Phoenix badge HTML string for a progression status.
     *
     * @param {string} status
     * @returns {string}
     */
    function statusBadge(status) {
        const map = {
            in_progress: ['badge-phoenix-info',      'In Progress'],
            completed:   ['badge-phoenix-success',   'Completed'],
            on_hold:     ['badge-phoenix-warning',   'On Hold'],
        };

        const entry = map[status];
        if (entry) {
            return '<span class="badge badge-phoenix ' + entry[0] + '">' + entry[1] + '</span>';
        }
        return '<span class="badge badge-phoenix badge-phoenix-secondary">' + (status || 'Unknown') + '</span>';
    }

    /**
     * Show loading spinner, hide main content.
     */
    function showLoading() {
        $('#reg-loading').removeClass('d-none');
        $('#reg-content').addClass('d-none');
    }

    /**
     * Hide loading spinner, reveal main content.
     */
    function hideLoading() {
        $('#reg-loading').addClass('d-none');
        $('#reg-content').removeClass('d-none');
    }

    /**
     * Insert a Bootstrap alert into #reg-alert, auto-dismiss after 5 seconds.
     *
     * @param {string} msg   Alert message text
     * @param {string} type  Bootstrap type: success|danger|warning|info
     */
    function showAlert(msg, type) {
        const $alert = $('<div>')
            .addClass('alert alert-' + (type || 'info') + ' alert-dismissible fade show')
            .attr('role', 'alert')
            .html(
                msg +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            );

        $('#reg-alert').empty().append($alert);

        setTimeout(function() {
            $alert.alert('close');
        }, 5000);
    }

})(jQuery);
