/**
 * WeCoza NLQ - Table Display Script
 *
 * Initializes DataTables on [wecoza_nlq_table] shortcode instances.
 * Wires up the Phoenix-style search box in the card header.
 *
 * @package WeCoza\NLQ
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize all NLQ DataTables on the page
        $('.wecoza-nlq-datatable').each(function () {
            var $table = $(this);
            var pageSize = parseInt($table.data('page-size'), 10) || 25;
            var tableId = $table.attr('id');

            if ($.fn.DataTable.isDataTable($table)) {
                return;
            }

            var dt = $table.DataTable({
                pageLength: pageSize,
                scrollX: true,
                order: [[0, 'asc']],
                dom: '<"d-none"f>rt<"d-flex justify-content-between align-items-center mt-3"<"text-body-tertiary fs-9"i><"d-flex align-items-center gap-2"lp>>',
                language: {
                    emptyTable: 'No data available',
                    info: 'Showing _START_ to _END_ of _TOTAL_ records',
                    infoEmpty: 'No records',
                    lengthMenu: 'Show _MENU_',
                    paginate: {
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                    },
                },
            });

            // Wire up Phoenix search box in card header
            var $searchInput = $('#' + tableId + '-search');
            if ($searchInput.length) {
                $searchInput.on('keyup', function () {
                    dt.search(this.value).draw();
                });
            }
        });

        // CSV export button (card header)
        $(document).on('click', '.nlq-export-csv-btn', function () {
            var tableId = $(this).data('table');
            var $table = $('#' + tableId);
            if (!$table.length || !$.fn.DataTable.isDataTable($table)) return;

            var dt = $table.DataTable();
            var data = dt.rows({ search: 'applied' }).data().toArray();
            var columns = [];

            $table.find('thead th').each(function () {
                columns.push('"' + $(this).text().trim().replace(/"/g, '""') + '"');
            });

            var csv = columns.join(',') + '\n';
            data.forEach(function (row) {
                var rowData = [];
                for (var i = 0; i < row.length; i++) {
                    var cell = String(row[i] || '').replace(/"/g, '""');
                    rowData.push('"' + cell + '"');
                }
                csv += rowData.join(',') + '\n';
            });

            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'export-' + tableId + '.csv';
            link.click();
        });
    });

})(jQuery);
