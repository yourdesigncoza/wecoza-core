/**
 * WeCoza NLQ - Query Manager Script
 *
 * Handles the [wecoza_nlq_manager] shortcode frontend:
 *   - Create/edit/delete saved queries
 *   - Preview SQL results before saving
 *   - Copy shortcodes to clipboard
 *   - Category filtering via Phoenix header search
 *
 * @package WeCoza\NLQ
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    var NLQ = window.wecozaNLQ || {};
    var queriesDataTable = null;

    $(document).ready(function () {
        initQueriesTable();
        bindEvents();
    });

    /* ─── DataTable for Queries List ──────────────────────── */

    function initQueriesTable() {
        var $table = $('#nlq-queries-table');
        if (!$table.length || $.fn.DataTable.isDataTable($table)) return;

        queriesDataTable = $table.DataTable({
            pageLength: 25,
            scrollX: true,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [6, 7] },
            ],
            dom: '<"d-none"f>rt<"d-flex justify-content-between align-items-center mt-3"<"text-body-tertiary fs-9"i><"d-flex align-items-center gap-2"lp>>',
            language: {
                emptyTable: 'No saved queries yet. Create one using the "Create Query" tab.',
                info: 'Showing _START_ to _END_ of _TOTAL_ queries',
                infoEmpty: 'No queries',
                lengthMenu: 'Show _MENU_',
                paginate: {
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>',
                },
            },
        });

        // Wire up Phoenix search box
        $('#nlq-search').on('keyup', function () {
            queriesDataTable.search(this.value).draw();
        });
    }

    /* ─── Event Bindings ──────────────────────────────────── */

    function bindEvents() {
        $('#nlq-preview-sql').on('click', handlePreviewSql);
        $('#nlq-query-form').on('submit', handleSaveQuery);
        $(document).on('click', '.nlq-edit-btn', handleEditQuery);
        $(document).on('click', '.nlq-preview-btn', handlePreviewQuery);
        $(document).on('click', '.nlq-delete-btn', handleDeleteQuery);
        $('#nlq-form-reset').on('click', resetForm);
        $(document).on('click', '.nlq-shortcode-copy, .nlq-copy-shortcode', handleCopyShortcode);

        // Category filter dropdown
        $('#nlq-filter-category').on('change', function () {
            var cat = $(this).val();
            if (queriesDataTable) {
                queriesDataTable.column(2).search(cat).draw();
            }
        });
    }

    /* ─── Preview SQL Results ─────────────────────────────── */

    function handlePreviewSql() {
        var sql = $('#nlq-sql-query').val().trim();
        if (!sql) {
            showToast('Please enter a SQL query.', 'warning');
            return;
        }

        var $btn = $(this);
        setBtnLoading($btn, 'Running…');

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_preview_sql',
                _ajax_nonce: NLQ.nonce,
                sql_query: sql,
            },
            success: function (response) {
                if (response.success) {
                    renderPreviewTable(response.data);
                    $('#nlq-preview-area').slideDown();
                } else {
                    showToast(response.data.message || 'Preview failed.', 'danger');
                    $('#nlq-preview-area').slideUp();
                }
            },
            error: function () {
                showToast('Network error. Please try again.', 'danger');
            },
            complete: function () {
                resetBtn($btn, '<i class="bi bi-play-circle me-1"></i> Preview Results');
            },
        });
    }

    /* ─── Render Preview Table ────────────────────────────── */

    function renderPreviewTable(data) {
        var $info = $('#nlq-preview-info');
        var $thead = $('#nlq-preview-table thead tr');
        var $tbody = $('#nlq-preview-table tbody');

        if ($.fn.DataTable.isDataTable('#nlq-preview-table')) {
            $('#nlq-preview-table').DataTable().destroy();
        }

        $info.html('<span class="badge badge-phoenix badge-phoenix-success fs-10">' + data.row_count + ' rows returned</span>');
        $thead.empty();
        $tbody.empty();

        if (data.columns && data.columns.length) {
            data.columns.forEach(function (col) {
                $thead.append('<th class="border-0">' + escapeHtml(formatColumnName(col)) + '</th>');
            });

            if (data.data && data.data.length) {
                data.data.forEach(function (row, idx) {
                    var tr = '<tr>';
                    data.columns.forEach(function (col, colIdx) {
                        var val = escapeHtml(String(row[col] || ''));
                        if (colIdx === 0 && /^\d+$/.test(val)) {
                            tr += '<td class="py-2 align-middle text-center"><span class="badge fs-10 badge-phoenix badge-phoenix-secondary">#' + val + '</span></td>';
                        } else {
                            tr += '<td class="py-2 align-middle">' + val + '</td>';
                        }
                    });
                    tr += '</tr>';
                    $tbody.append(tr);
                });
            }

            $('#nlq-preview-table').DataTable({
                pageLength: 10,
                scrollX: true,
                destroy: true,
                dom: 'rt<"d-flex justify-content-between align-items-center mt-2"<"text-body-tertiary fs-9"i>p>',
                language: {
                    paginate: {
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                    },
                },
            });
        }
    }

    /* ─── Save Query ──────────────────────────────────────── */

    function handleSaveQuery(e) {
        e.preventDefault();

        var queryId = $('#nlq-query-id').val();
        var isEdit = !!queryId;

        var payload = {
            action: isEdit ? 'wecoza_nlq_update_query' : 'wecoza_nlq_save_query',
            _ajax_nonce: NLQ.nonce,
            query_name: $('#nlq-query-name').val().trim(),
            description: $('#nlq-query-description').val().trim(),
            natural_language: $('#nlq-natural-language').val().trim(),
            sql_query: $('#nlq-sql-query').val().trim(),
            category: $('#nlq-query-category').val().trim(),
        };

        if (isEdit) payload.query_id = queryId;

        if (!payload.query_name || !payload.sql_query) {
            showToast('Query name and SQL are required.', 'warning');
            return;
        }

        var $btn = $('#nlq-save-btn');
        setBtnLoading($btn, 'Saving…');

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: payload,
            success: function (response) {
                if (response.success) {
                    if (!isEdit && response.data.id) {
                        var shortcode = '[wecoza_nlq_table query_id="' + response.data.id + '"]';
                        $('#nlq-save-shortcode').text(shortcode);
                        $('#nlq-save-result').removeClass('d-none');
                    }

                    showToast(isEdit ? 'Query updated!' : 'Query saved!', 'success');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    showToast(response.data.message || 'Save failed.', 'danger');
                }
            },
            error: function () {
                showToast('Network error. Please try again.', 'danger');
            },
            complete: function () {
                resetBtn($btn, '<i class="bi bi-save me-1"></i> Save Query');
            },
        });
    }

    /* ─── Edit Query ──────────────────────────────────────── */

    function handleEditQuery() {
        var queryId = $(this).data('id');

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_get_query',
                _ajax_nonce: NLQ.nonce,
                query_id: queryId,
            },
            success: function (response) {
                if (response.success) {
                    var q = response.data;
                    $('#nlq-query-id').val(q.id);
                    $('#nlq-query-name').val(q.query_name || '');
                    $('#nlq-query-category').val(q.category || '');
                    $('#nlq-query-description').val(q.description || '');
                    $('#nlq-natural-language').val(q.natural_language || '');
                    $('#nlq-sql-query').val(q.sql_query || '');

                    $('#nlq-form-title').html('Edit Query #' + q.id + ' <i class="bi bi-pencil ms-2"></i>');
                    $('#nlq-form-reset').show();
                    $('#nlq-save-btn').html('<i class="bi bi-save me-1"></i> Update Query');

                    // Switch to create tab
                    var tabEl = document.getElementById('tab-create');
                    if (tabEl) new bootstrap.Tab(tabEl).show();
                } else {
                    showToast('Failed to load query.', 'danger');
                }
            },
        });
    }

    /* ─── Preview Query (Modal) ───────────────────────────── */

    function handlePreviewQuery() {
        var queryId = $(this).data('id');

        $('#nlq-modal-loading').show();
        $('#nlq-modal-content').hide();
        $('#nlq-modal-error').addClass('d-none');

        var modal = new bootstrap.Modal(document.getElementById('nlq-preview-modal'));
        modal.show();

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_execute_query',
                _ajax_nonce: NLQ.nonce,
                query_id: queryId,
            },
            success: function (response) {
                $('#nlq-modal-loading').hide();

                if (response.success) {
                    $('#nlq-modal-title').html((response.data.query_name || 'Query #' + queryId) + ' <i class="bi bi-eye ms-2"></i>');
                    renderModalTable(response.data);
                    $('#nlq-modal-content').show();
                } else {
                    $('#nlq-modal-error-text').text(response.data.message || 'Execution failed.');
                    $('#nlq-modal-error').removeClass('d-none');
                }
            },
            error: function () {
                $('#nlq-modal-loading').hide();
                $('#nlq-modal-error-text').text('Network error.');
                $('#nlq-modal-error').removeClass('d-none');
            },
        });
    }

    function renderModalTable(data) {
        var $thead = $('#nlq-modal-table thead tr');
        var $tbody = $('#nlq-modal-table tbody');
        var $info = $('#nlq-modal-info');

        if ($.fn.DataTable.isDataTable('#nlq-modal-table')) {
            $('#nlq-modal-table').DataTable().destroy();
        }

        $info.html('<span class="badge badge-phoenix badge-phoenix-success fs-10">' + data.row_count + ' rows</span>');
        $thead.empty();
        $tbody.empty();

        if (data.columns && data.columns.length) {
            data.columns.forEach(function (col) {
                $thead.append('<th class="border-0">' + escapeHtml(formatColumnName(col)) + '</th>');
            });

            if (data.data && data.data.length) {
                data.data.forEach(function (row) {
                    var tr = '<tr>';
                    data.columns.forEach(function (col, colIdx) {
                        var val = escapeHtml(String(row[col] || ''));
                        if (colIdx === 0 && /^\d+$/.test(val)) {
                            tr += '<td class="py-2 align-middle text-center"><span class="badge fs-10 badge-phoenix badge-phoenix-secondary">#' + val + '</span></td>';
                        } else {
                            tr += '<td class="py-2 align-middle">' + val + '</td>';
                        }
                    });
                    tr += '</tr>';
                    $tbody.append(tr);
                });
            }

            $('#nlq-modal-table').DataTable({
                pageLength: 25,
                scrollX: true,
                destroy: true,
                dom: 'rt<"d-flex justify-content-between align-items-center mt-2"<"text-body-tertiary fs-9"i>p>',
                language: {
                    paginate: {
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                    },
                },
            });
        }
    }

    /* ─── Delete Query ────────────────────────────────────── */

    function handleDeleteQuery() {
        var queryId = $(this).data('id');

        if (!confirm('Are you sure you want to permanently delete this query? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_delete_query',
                _ajax_nonce: NLQ.nonce,
                query_id: queryId,
            },
            success: function (response) {
                if (response.success) {
                    showToast('Query deleted.', 'success');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    showToast(response.data.message || 'Failed to delete.', 'danger');
                }
            },
        });
    }

    /* ─── Reset Form ──────────────────────────────────────── */

    function resetForm() {
        $('#nlq-query-id').val('');
        $('#nlq-query-form')[0].reset();
        $('#nlq-form-title').html('Create New Query <i class="bi bi-plus-circle ms-2"></i>');
        $('#nlq-form-reset').hide();
        $('#nlq-save-btn').html('<i class="bi bi-save me-1"></i> Save Query');
        $('#nlq-preview-area').slideUp();
        $('#nlq-save-result').addClass('d-none');
    }

    /* ─── Copy Shortcode ──────────────────────────────────── */

    function handleCopyShortcode() {
        var shortcode = $(this).data('shortcode') || $(this).prev('code').text() || $('#nlq-save-shortcode').text();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(shortcode).then(function () {
                showToast('Shortcode copied to clipboard!', 'success', 2000);
            });
        } else {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(shortcode).select();
            document.execCommand('copy');
            $temp.remove();
            showToast('Shortcode copied!', 'success', 2000);
        }
    }

    /* ─── Utility Functions ───────────────────────────────── */

    function formatColumnName(col) {
        return col.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function setBtnLoading($btn, text) {
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> ' + text);
    }

    function resetBtn($btn, html) {
        $btn.prop('disabled', false).html(html);
    }

    /**
     * Phoenix-style toast notification (top-right, auto-dismiss)
     */
    function showToast(message, type, autoHide) {
        type = type || 'info';
        autoHide = autoHide || 4000;

        var iconMap = {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill',
        };

        var $alert = $(
            '<div class="alert alert-subtle-' + type + ' alert-dismissible fade show position-fixed d-flex align-items-center" ' +
            'style="top: 80px; right: 20px; z-index: 99999; min-width: 300px; max-width: 450px;" role="alert">' +
            '<i class="bi ' + (iconMap[type] || iconMap.info) + ' me-2 fs-5"></i>' +
            '<div>' + message + '</div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>'
        );

        $('body').append($alert);
        setTimeout(function () { $alert.alert('close'); }, autoHide);
    }

})(jQuery);
