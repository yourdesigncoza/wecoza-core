/**
 * Excessive Hours Dashboard
 *
 * DataTable with AJAX loading, status filters, and inline resolve workflow.
 * Depends on: jQuery, DataTables, Bootstrap 5
 *
 * @package WeCoza\Reports\ExcessiveHours
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    const config = window.excessiveHoursAjax || {};
    let table;
    let currentStatus = 'open';

    /* -----------------------------------------------------------------------
     * Initialization
     * ----------------------------------------------------------------------- */

    $(document).ready(function () {
        initDataTable();
        bindEvents();
    });

    function initDataTable() {
        table = $('#eh-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: config.ajaxurl,
                type: 'POST',
                data: function (d) {
                    d.action = 'wecoza_get_excessive_hours';
                    d.nonce = config.nonce;
                    d.filter_status = currentStatus;
                    d.filter_client_id = $('#eh-filter-client').val();
                    d.filter_class_type = $('#eh-filter-class-type').val();
                    d.search_value = $('#eh-search').val();
                },
                dataSrc: function (json) {
                    // Update summary stats
                    updateStats(json);

                    // Show/hide empty state
                    if (json.data.length === 0 && json.recordsTotal === 0) {
                        $('#eh-empty').removeClass('d-none');
                        $('#eh-table').closest('.table-responsive').addClass('d-none');
                    } else {
                        $('#eh-empty').addClass('d-none');
                        $('#eh-table').closest('.table-responsive').removeClass('d-none');
                    }

                    // Hide loading, show content
                    $('#eh-loading').addClass('d-none');
                    $('#eh-content').removeClass('d-none');

                    return json.data;
                },
                error: function (xhr) {
                    $('#eh-loading').addClass('d-none');
                    $('#eh-content').removeClass('d-none');
                    showAlert('Failed to load data. Please refresh the page.', 'danger');
                }
            },
            columns: [
                {
                    data: 'learner_name',
                    render: function (data, type, row) {
                        return '<span class="fw-semibold">' + escHtml(data || '') + '</span>';
                    }
                },
                {
                    data: 'class_code',
                    render: function (data) {
                        return '<code class="fs-10">' + escHtml(data || '—') + '</code>';
                    }
                },
                {
                    data: 'class_type_name',
                    render: function (data, type, row) {
                        return escHtml(data || '') +
                            '<br><small class="text-muted">' + escHtml(row.subject_name || '') + '</small>';
                    }
                },
                { data: 'client_name' },
                {
                    data: 'hours_trained',
                    className: 'text-end',
                    render: function (data) { return formatHours(data); }
                },
                {
                    data: 'subject_duration',
                    className: 'text-end',
                    render: function (data) { return formatHours(data); }
                },
                {
                    data: 'overage_hours',
                    className: 'text-end',
                    render: function (data, type, row) {
                        const pct = row.overage_pct || 0;
                        const color = pct > 50 ? 'danger' : 'warning';
                        return '<span class="text-' + color + ' fw-bold">+' + formatHours(data) + '</span>' +
                            '<br><small class="text-muted">(' + pct + '%)</small>';
                    }
                },
                {
                    data: 'flag_status',
                    render: function (data, type, row) {
                        if (data === 'resolved') {
                            return '<span class="badge badge-phoenix fs-10 badge-phoenix-success">' +
                                '<i class="bi bi-check-circle me-1"></i>Resolved</span>' +
                                '<br><small class="text-muted">' + escHtml(row.resolved_at_display || '') + '</small>';
                        }
                        return '<span class="badge badge-phoenix fs-10 badge-phoenix-warning">' +
                            '<i class="bi bi-clock me-1"></i>Open</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let btns = '';
                        if (row.flag_status === 'open') {
                            btns += '<button class="btn btn-sm btn-phoenix-primary eh-resolve-btn me-1" ' +
                                'data-tracking-id="' + row.tracking_id + '" ' +
                                'data-learner="' + escAttr(row.learner_name) + '" ' +
                                'data-class="' + escAttr(row.class_code || '') + '" ' +
                                'data-overage="' + row.overage_hours + '" ' +
                                'title="Resolve">' +
                                '<i class="bi bi-check-lg"></i></button>';
                        } else {
                            btns += '<small class="text-muted">' +
                                escHtml(row.action_label || '') +
                                '</small>';
                        }
                        btns += '<button class="btn btn-sm btn-phoenix-secondary eh-history-btn ms-1" ' +
                            'data-tracking-id="' + row.tracking_id + '" ' +
                            'title="View history">' +
                            '<i class="bi bi-clock-history"></i></button>';
                        return btns;
                    }
                }
            ],
            order: [[6, 'desc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: {
                emptyTable: 'No excessive hours found',
                processing: '<div class="spinner-border spinner-border-sm text-primary"></div> Loading...',
                info: 'Showing _START_ to _END_ of _TOTAL_ flagged learners',
            },
            dom: '<"d-flex justify-content-between align-items-center mb-2"lp>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
            drawCallback: function () {
                // Re-bind action buttons after each draw
                bindActionButtons();
            }
        });
    }

    /* -----------------------------------------------------------------------
     * Event Bindings
     * ----------------------------------------------------------------------- */

    function bindEvents() {
        // Status pills
        $('#eh-status-pills').on('click', 'button', function () {
            $('#eh-status-pills button').removeClass('active');
            $(this).addClass('active');
            currentStatus = $(this).data('status');
            table.ajax.reload();
        });

        // Filter dropdowns
        $('#eh-filter-client, #eh-filter-class-type').on('change', function () {
            table.ajax.reload();
        });

        // Search with debounce
        let searchTimer;
        $('#eh-search').on('keyup', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                table.ajax.reload();
            }, 400);
        });

        // Resolve submit
        $('#eh-resolve-submit').on('click', submitResolve);
    }

    function bindActionButtons() {
        // Resolve button
        $('.eh-resolve-btn').off('click').on('click', function () {
            const $btn = $(this);
            $('#eh-resolve-tracking-id').val($btn.data('tracking-id'));
            $('#eh-resolve-learner').text($btn.data('learner'));
            $('#eh-resolve-class').text($btn.data('class'));
            $('#eh-resolve-overage').text($btn.data('overage'));
            $('#eh-resolve-action').val('');
            $('#eh-resolve-notes').val('');

            const modal = new bootstrap.Modal(document.getElementById('eh-resolve-modal'));
            modal.show();
        });

        // History button
        $('.eh-history-btn').off('click').on('click', function () {
            loadHistory($(this).data('tracking-id'));
        });
    }

    /* -----------------------------------------------------------------------
     * Resolve Workflow
     * ----------------------------------------------------------------------- */

    function submitResolve() {
        const trackingId = $('#eh-resolve-tracking-id').val();
        const actionTaken = $('#eh-resolve-action').val();
        const notes = $('#eh-resolve-notes').val();

        if (!actionTaken) {
            showAlert('Please select an action.', 'warning');
            return;
        }

        const $btn = $('#eh-resolve-submit');
        const $spinner = $('#eh-resolve-spinner');
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');

        $.post(config.ajaxurl, {
            action: 'wecoza_resolve_excessive_hours',
            nonce: config.nonce,
            tracking_id: trackingId,
            action_taken: actionTaken,
            resolution_notes: notes,
        })
        .done(function (resp) {
            if (resp.success) {
                bootstrap.Modal.getInstance(document.getElementById('eh-resolve-modal')).hide();
                showAlert('Flag resolved successfully by ' + escHtml(resp.data.resolved_by_name || 'you') + '.', 'success');
                table.ajax.reload(null, false);
            } else {
                showAlert(resp.data?.message || 'Failed to resolve flag.', 'danger');
            }
        })
        .fail(function () {
            showAlert('Network error. Please try again.', 'danger');
        })
        .always(function () {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        });
    }

    /* -----------------------------------------------------------------------
     * Resolution History
     * ----------------------------------------------------------------------- */

    function loadHistory(trackingId) {
        const $content = $('#eh-history-content');
        $content.html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>');

        const modal = new bootstrap.Modal(document.getElementById('eh-history-modal'));
        modal.show();

        $.post(config.ajaxurl, {
            action: 'wecoza_get_excessive_hours_history',
            nonce: config.nonce,
            tracking_id: trackingId,
        })
        .done(function (resp) {
            if (resp.success && resp.data.history.length > 0) {
                let html = '<div class="list-group list-group-flush">';
                resp.data.history.forEach(function (item) {
                    html += '<div class="list-group-item px-0">' +
                        '<div class="d-flex justify-content-between">' +
                        '<strong>' + escHtml(item.action_label) + '</strong>' +
                        '<small class="text-muted">' + escHtml(item.created_at_display) + '</small>' +
                        '</div>' +
                        '<small>By: ' + escHtml(item.resolved_by_name || 'Unknown') + '</small>';
                    if (item.resolution_notes) {
                        html += '<p class="mb-0 mt-1 text-muted small">' + escHtml(item.resolution_notes) + '</p>';
                    }
                    html += '</div>';
                });
                html += '</div>';
                $content.html(html);
            } else {
                $content.html('<p class="text-muted text-center py-3">No resolution history found.</p>');
            }
        })
        .fail(function () {
            $content.html('<p class="text-danger text-center py-3">Failed to load history.</p>');
        });
    }

    /* -----------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------------- */

    function updateStats(json) {
        $('#eh-stat-open').text(json.open_count || 0);
        $('#eh-stat-resolved').text(json.resolved_count || 0);
        $('#eh-stat-total').text(json.recordsTotal || 0);
        $('#eh-pill-open').text(json.open_count || 0);
        $('#eh-pill-resolved').text(json.resolved_count || 0);

        // Demo mode banner
        if (json.demo_mode) {
            if (!$('#eh-demo-banner').length) {
                $('#eh-alert').html(
                    '<div id="eh-demo-banner" class="alert alert-info d-flex align-items-center mb-3" role="alert">' +
                    '<i class="bi bi-info-circle-fill me-2 fs-5"></i>' +
                    '<div><strong>Demo Data</strong> — The data below is hardcoded for preview purposes. ' +
                    'Real data will appear automatically once learners exceed their allocated hours.</div>' +
                    '</div>'
                );
            }
        } else {
            $('#eh-demo-banner').remove();
        }
    }

    function formatHours(val) {
        const num = parseFloat(val) || 0;
        return num % 1 === 0 ? num.toString() : num.toFixed(1);
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function showAlert(msg, type) {
        const $container = $('#eh-alert');
        const html = '<div class="alert alert-' + type + ' alert-dismissible fade show mb-3" role="alert">' +
            msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        $container.html(html);
        setTimeout(function () { $container.find('.alert').alert('close'); }, 5000);
    }

})(jQuery);
