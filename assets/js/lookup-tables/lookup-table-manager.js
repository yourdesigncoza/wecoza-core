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
    // Column Type Helpers
    // ---------------------------------------------------------------------------

    /**
     * Get column type from config, defaulting to 'text'.
     */
    function getColumnType(config, col) {
        return (config.column_types && config.column_types[col]) || 'text';
    }

    /**
     * Escape HTML entities for safe display.
     */
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /**
     * Create a typed input element for edit mode.
     *
     * @param {string} col     Column name
     * @param {string} type    Column type (text|select|number|boolean)
     * @param {*}      value   Current value
     * @param {Object} config  Table config (for select_options)
     * @return {jQuery} Input element
     */
    function createInput(col, type, value, config) {
        if (type === 'select') {
            var $sel = $('<select class="form-select form-select-sm lookup-edit-input"></select>')
                .attr('data-column', col);
            var opts = (config.select_options && config.select_options[col]) || [];
            $.each(opts, function (_, opt) {
                var selected = (String(opt.value) === String(value)) ? ' selected' : '';
                $sel.append('<option value="' + escHtml(opt.value) + '"' + selected + '>' + escHtml(opt.label) + '</option>');
            });
            return $sel;
        }
        if (type === 'number') {
            return $('<input type="number" min="0" class="form-control form-control-sm lookup-edit-input">')
                .attr('data-column', col).val(value);
        }
        if (type === 'boolean') {
            var checked = (value === true || value === 't' || value === '1' || value === 'true' || value === 1);
            return $('<div class="form-check form-switch ms-2">')
                .append($('<input type="checkbox" class="form-check-input lookup-edit-input">')
                    .attr('data-column', col).prop('checked', checked));
        }
        return $('<input type="text" class="form-control form-control-sm lookup-edit-input">')
            .attr('data-column', col).val(value);
    }

    /**
     * Look up the display label for a select column value.
     */
    function getSelectLabel(config, col, value) {
        var opts = (config.select_options && config.select_options[col]) || [];
        for (var i = 0; i < opts.length; i++) {
            if (String(opts[i].value) === String(value)) {
                return opts[i].label;
            }
        }
        return value;
    }

    // ---------------------------------------------------------------------------
    // Row Rendering
    // ---------------------------------------------------------------------------

    /**
     * Clear existing data rows (preserving the add-row) and render fresh rows.
     *
     * @param {string} tableKey
     * @param {Array}  items    Array of row objects from the server
     * @param {Object} config   Table config { pk, columns, labels, column_types, select_options }
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

        // Build and insert rows after the add-row
        var rows = [];
        $.each(items, function (index, item) {
            var pkValue = item[config.pk];
            var rowNum  = index + 1;

            var $tr = $('<tr class="lookup-data-row"></tr>');

            // Row number cell
            $tr.append('<td class="align-middle ps-3">' + rowNum + '</td>');

            // Data cells — type-aware display
            $.each(config.columns, function (i, col) {
                var value = item[col] !== null && item[col] !== undefined ? item[col] : '';
                var type  = getColumnType(config, col);
                var $td   = $('<td class="align-middle"></td>')
                    .attr('data-column', col)
                    .attr('data-id', pkValue)
                    .attr('data-value', value);

                if (type === 'boolean') {
                    var isTrue = (value === true || value === 't' || value === '1' || value === 'true' || value === 1);
                    if (isTrue) {
                        $td.html('<span class="badge badge-phoenix badge-phoenix-success">Yes</span>');
                    } else {
                        $td.html('<span class="badge badge-phoenix badge-phoenix-secondary">No</span>');
                    }
                } else if (type === 'select') {
                    $td.text(getSelectLabel(config, col, value));
                } else {
                    $td.text(value);
                }

                $tr.append($td);
            });

            // Actions cell — btn-group with subtle buttons
            var $actions = $(
                '<td class="align-middle text-end pe-3">' +
                '<div class="btn-group" role="group">' +
                '<button type="button" class="btn btn-sm btn-subtle-primary lookup-btn-edit" ' +
                    'data-id="' + pkValue + '" data-table-key="' + tableKey + '" title="Edit">' +
                    '<span class="fas fa-pencil-alt"></span>' +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-subtle-danger lookup-btn-delete" ' +
                    'data-id="' + pkValue + '" data-table-key="' + tableKey + '" title="Delete">' +
                    '<span class="fas fa-trash-alt"></span>' +
                '</button>' +
                '</div>' +
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
        var config   = getConfig(tableKey);
        var $addRow  = $('#lookup-add-row-' + tableKey);
        var data     = {};
        var hasValue = false;

        $addRow.find('.lookup-add-input').each(function () {
            var $input = $(this);
            var col    = $input.data('column');
            var type   = getColumnType(config, col);
            var val;

            if (type === 'boolean') {
                val = $input.is(':checked') ? '1' : '0';
                hasValue = true;
            } else {
                val = $.trim($input.val());
                if (val !== '') {
                    hasValue = true;
                }
            }
            data[col] = val;
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
                    // Clear add-row inputs (reset selects to first option, checkboxes to checked)
                    $addRow.find('.lookup-add-input').each(function () {
                        var $el = $(this);
                        if ($el.is(':checkbox')) {
                            $el.prop('checked', true);
                        } else if ($el.is('select')) {
                            $el.prop('selectedIndex', 0);
                        } else {
                            $el.val('');
                        }
                    });
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
        var config   = getConfig(tableKey);
        var id       = $btn.data('id');
        var $row     = $btn.closest('tr');

        // Replace each data cell with a typed input
        $row.find('td[data-column]').each(function () {
            var $td     = $(this);
            var col     = $td.data('column');
            var type    = getColumnType(config, col);
            // Use data-value attribute for raw value (preserves original for selects/booleans)
            var current = $td.attr('data-value') !== undefined ? $td.attr('data-value') : $td.text();
            var $input  = createInput(col, type, current, config);
            $td.empty().append($input);
        });

        // Swap edit button to save (checkmark)
        $btn
            .removeClass('btn-subtle-primary lookup-btn-edit')
            .addClass('btn-subtle-success lookup-btn-save')
            .attr('title', 'Save')
            .html('<span class="fas fa-check"></span>');

        // Swap delete button to cancel (times)
        $row.find('.lookup-btn-delete')
            .removeClass('btn-subtle-danger lookup-btn-delete')
            .addClass('btn-subtle-secondary lookup-btn-cancel')
            .attr('title', 'Cancel')
            .html('<span class="fas fa-times"></span>');
    });

    // ---------------------------------------------------------------------------
    // Save Handler
    // ---------------------------------------------------------------------------

    $(document).on('click', '.lookup-btn-save', function () {
        var $btn     = $(this);
        var tableKey = $btn.data('table-key');
        var config   = getConfig(tableKey);
        var id       = $btn.data('id');
        var $row     = $btn.closest('tr');
        var data     = {};

        $row.find('.lookup-edit-input').each(function () {
            var $input = $(this);
            var col    = $input.data('column');
            var type   = getColumnType(config, col);

            if (type === 'boolean') {
                data[col] = $input.is(':checked') ? '1' : '0';
            } else {
                data[col] = $.trim($input.val());
            }
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
