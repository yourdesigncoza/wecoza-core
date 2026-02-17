/**
 * Lookup Table Manager
 *
 * Provides inline CRUD for Phoenix-styled lookup tables.
 * Communicates with the server via a single AJAX endpoint (wecoza_lookup_table)
 * using sub_action dispatch (list | create | update | delete).
 *
 * Globals (provided by wp_localize_script):
 *   WeCozaLookupTables.ajax_url — admin-ajax.php URL
 *   WeCozaLookupTables.nonce    — WordPress nonce
 *
 * @since 4.1.0
 */

(function ($) {
    'use strict';

    // ---------------------------------------------------------------------------
    // Initialization
    // ---------------------------------------------------------------------------

    $(document).ready(function () {
        // Find every table that has a data-table-key attribute
        $('[data-table-key]').each(function () {
            var tableKey = $(this).data('table-key');
            loadRows(tableKey);
        });
    });

    // ---------------------------------------------------------------------------
    // Data Loading
    // ---------------------------------------------------------------------------

    /**
     * Load all rows for a given table from the server and render them.
     *
     * @param {string} tableKey
     */
    function loadRows(tableKey) {
        showLoading(tableKey, true);

        $.ajax({
            url: WeCozaLookupTables.ajax_url,
            method: 'POST',
            data: {
                action:     'wecoza_lookup_table',
                sub_action: 'list',
                table_key:  tableKey,
                nonce:      WeCozaLookupTables.nonce
            },
            success: function (response) {
                showLoading(tableKey, false);
                if (response.success) {
                    var config = getConfig(tableKey);
                    renderRows(tableKey, response.data.items, config);
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to load records.';
                    showAlert(tableKey, 'danger', msg);
                }
            },
            error: function () {
                showLoading(tableKey, false);
                showAlert(tableKey, 'danger', 'An error occurred while loading records.');
            }
        });
    }

    // ---------------------------------------------------------------------------
    // Row Rendering
    // ---------------------------------------------------------------------------

    /**
     * Clear existing data rows (preserving the add-row) and render fresh rows.
     *
     * @param {string} tableKey
     * @param {Array}  items    Array of row objects from the server
     * @param {Object} config   Table config { pk, columns, labels }
     */
    function renderRows(tableKey, items, config) {
        var $tbody   = $('#lookup-rows-' + tableKey);
        var $addRow  = $('#lookup-add-row-' + tableKey);

        // Remove all rows except the add-row
        $tbody.find('tr:not(.lookup-add-row)').remove();

        if (!items || items.length === 0) {
            var colSpan = config.columns.length + 2;
            var $empty  = $(
                '<tr class="lookup-data-row">' +
                '<td colspan="' + colSpan + '" class="text-center text-muted ps-3 py-3">No records found.</td>' +
                '</tr>'
            );
            $addRow.after($empty);
            return;
        }

        // Build and insert rows after the add-row (in reverse so order is preserved)
        var rows = [];
        $.each(items, function (index, item) {
            var pkValue = item[config.pk];
            var rowNum  = index + 1;

            var $tr = $('<tr class="lookup-data-row"></tr>');

            // Row number cell
            $tr.append('<td class="align-middle ps-3">' + rowNum + '</td>');

            // Data cells
            $.each(config.columns, function (i, col) {
                var value = item[col] !== null && item[col] !== undefined ? item[col] : '';
                var $td   = $('<td class="align-middle"></td>')
                    .attr('data-column', col)
                    .attr('data-id', pkValue)
                    .text(value);
                $tr.append($td);
            });

            // Actions cell
            var $actions = $(
                '<td class="align-middle text-end pe-3">' +
                '<button type="button" class="btn btn-sm btn-phoenix-primary lookup-btn-edit" ' +
                    'data-id="' + pkValue + '" data-table-key="' + tableKey + '" title="Edit">' +
                    '<span class="fas fa-pencil-alt"></span>' +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-phoenix-danger lookup-btn-delete ms-1" ' +
                    'data-id="' + pkValue + '" data-table-key="' + tableKey + '" title="Delete">' +
                    '<span class="fas fa-trash-alt"></span>' +
                '</button>' +
                '</td>'
            );
            $tr.append($actions);

            rows.push($tr);
        });

        // Append rows after the add-row
        $addRow.after(rows);
    }

    // ---------------------------------------------------------------------------
    // Add Handler
    // ---------------------------------------------------------------------------

    $(document).on('click', '.lookup-btn-add', function () {
        var tableKey = $(this).data('table-key');
        var $addRow  = $('#lookup-add-row-' + tableKey);
        var data     = {};
        var hasValue = false;

        $addRow.find('.lookup-add-input').each(function () {
            var col = $(this).data('column');
            var val = $.trim($(this).val());
            data[col] = val;
            if (val !== '') {
                hasValue = true;
            }
        });

        if (!hasValue) {
            showAlert(tableKey, 'danger', 'Please enter at least one value before adding.');
            return;
        }

        showLoading(tableKey, true);

        var postData = $.extend({
            action:     'wecoza_lookup_table',
            sub_action: 'create',
            table_key:  tableKey,
            nonce:      WeCozaLookupTables.nonce
        }, data);

        $.ajax({
            url:    WeCozaLookupTables.ajax_url,
            method: 'POST',
            data:   postData,
            success: function (response) {
                showLoading(tableKey, false);
                if (response.success) {
                    // Clear add-row inputs
                    $addRow.find('.lookup-add-input').val('');
                    loadRows(tableKey);
                    showAlert(tableKey, 'success', 'Record added successfully.');
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to add record.';
                    showAlert(tableKey, 'danger', msg);
                }
            },
            error: function () {
                showLoading(tableKey, false);
                showAlert(tableKey, 'danger', 'An error occurred while adding the record.');
            }
        });
    });

    // ---------------------------------------------------------------------------
    // Edit Handler — switches row cells to inputs
    // ---------------------------------------------------------------------------

    $(document).on('click', '.lookup-btn-edit', function () {
        var $btn     = $(this);
        var tableKey = $btn.data('table-key');
        var id       = $btn.data('id');
        var $row     = $btn.closest('tr');

        // Replace each data cell text with an input
        $row.find('td[data-column]').each(function () {
            var col      = $(this).data('column');
            var current  = $(this).text();
            var $input   = $('<input type="text" class="form-control form-control-sm lookup-edit-input">')
                .attr('data-column', col)
                .val(current);
            $(this).empty().append($input);
        });

        // Swap edit button to save (checkmark)
        $btn
            .removeClass('btn-phoenix-primary lookup-btn-edit')
            .addClass('btn-phoenix-success lookup-btn-save')
            .attr('title', 'Save')
            .html('<span class="fas fa-check"></span>');

        // Swap delete button to cancel (times)
        $row.find('.lookup-btn-delete')
            .removeClass('btn-phoenix-danger lookup-btn-delete')
            .addClass('btn-phoenix-secondary lookup-btn-cancel')
            .attr('title', 'Cancel')
            .html('<span class="fas fa-times"></span>');
    });

    // ---------------------------------------------------------------------------
    // Save Handler
    // ---------------------------------------------------------------------------

    $(document).on('click', '.lookup-btn-save', function () {
        var $btn     = $(this);
        var tableKey = $btn.data('table-key');
        var id       = $btn.data('id');
        var $row     = $btn.closest('tr');
        var data     = {};

        $row.find('.lookup-edit-input').each(function () {
            var col    = $(this).data('column');
            data[col]  = $.trim($(this).val());
        });

        showLoading(tableKey, true);

        var postData = $.extend({
            action:     'wecoza_lookup_table',
            sub_action: 'update',
            table_key:  tableKey,
            id:         id,
            nonce:      WeCozaLookupTables.nonce
        }, data);

        $.ajax({
            url:    WeCozaLookupTables.ajax_url,
            method: 'POST',
            data:   postData,
            success: function (response) {
                showLoading(tableKey, false);
                if (response.success) {
                    loadRows(tableKey);
                    showAlert(tableKey, 'success', 'Record updated successfully.');
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to update record.';
                    showAlert(tableKey, 'danger', msg);
                }
            },
            error: function () {
                showLoading(tableKey, false);
                showAlert(tableKey, 'danger', 'An error occurred while updating the record.');
            }
        });
    });

    // ---------------------------------------------------------------------------
    // Cancel Handler
    // ---------------------------------------------------------------------------

    $(document).on('click', '.lookup-btn-cancel', function () {
        var $btn     = $(this);
        var tableKey = $btn.data('table-key');
        // Reload rows to restore display state
        loadRows(tableKey);
    });

    // ---------------------------------------------------------------------------
    // Delete Handler
    // ---------------------------------------------------------------------------

    $(document).on('click', '.lookup-btn-delete', function () {
        var $btn     = $(this);
        var tableKey = $btn.data('table-key');
        var id       = $btn.data('id');

        if (!confirm('Are you sure you want to delete this item?')) {
            return;
        }

        showLoading(tableKey, true);

        $.ajax({
            url:    WeCozaLookupTables.ajax_url,
            method: 'POST',
            data: {
                action:     'wecoza_lookup_table',
                sub_action: 'delete',
                table_key:  tableKey,
                id:         id,
                nonce:      WeCozaLookupTables.nonce
            },
            success: function (response) {
                showLoading(tableKey, false);
                if (response.success) {
                    loadRows(tableKey);
                    showAlert(tableKey, 'success', 'Record deleted successfully.');
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to delete record.';
                    showAlert(tableKey, 'danger', msg);
                }
            },
            error: function () {
                showLoading(tableKey, false);
                showAlert(tableKey, 'danger', 'An error occurred while deleting the record.');
            }
        });
    });

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Read table config from the hidden JSON script tag embedded by the view.
     *
     * @param  {string} tableKey
     * @return {Object} Parsed config object { tableKey, pk, columns, labels }
     */
    function getConfig(tableKey) {
        var raw = $('#lookup-config-' + tableKey).text();
        try {
            return JSON.parse(raw);
        } catch (e) {
            return { pk: 'id', columns: [], labels: [] };
        }
    }

    /**
     * Show or hide the loading spinner for a given table.
     *
     * @param {string}  tableKey
     * @param {boolean} show
     */
    function showLoading(tableKey, show) {
        var $spinner = $('#lookup-loading-' + tableKey);
        if (show) {
            $spinner.show();
        } else {
            $spinner.hide();
        }
    }

    /**
     * Display a Phoenix-styled dismissible alert in the table's alert container.
     * Auto-dismisses after 5 seconds.
     *
     * @param {string} tableKey
     * @param {string} type     'success' or 'danger'
     * @param {string} message  Alert message text
     */
    function showAlert(tableKey, type, message) {
        var $container = $('#lookup-alert-' + tableKey);

        var $alert = $(
            '<div class="alert alert-subtle-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>'
        );

        $container.empty().append($alert).show();

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            $alert.alert('close');
        }, 5000);
    }

})(jQuery);
