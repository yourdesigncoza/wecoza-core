/**
 * Class Learner Report JS
 *
 * Handles class selection, AJAX report generation preview,
 * and CSV download trigger for the [wecoza_class_learner_report] shortcode.
 *
 * @package WeCoza
 */
(function ($) {
    'use strict';

    // State
    var currentReportData = null;

    $(document).ready(function () {
        initClassDropdown();
        initMonthPicker();
        bindEvents();
    });

    /**
     * Populate class dropdown from localized data
     */
    function initClassDropdown() {
        var $select = $('#report-class-select');
        var classes = reportExtractionAjax.classes || [];

        $.each(classes, function (i, cls) {
            var label = cls.class_code || ('Class #' + cls.id);
            if (cls.client_name) {
                label += ' (' + cls.client_name + ')';
            }
            $select.append(
                $('<option>').val(cls.id).text(label)
            );
        });
    }

    /**
     * Set month picker to current month
     */
    function initMonthPicker() {
        var now = new Date();
        var yyyy = now.getFullYear();
        var mm = String(now.getMonth() + 1).padStart(2, '0');
        $('#report-month').val(yyyy + '-' + mm);
    }

    /**
     * Bind UI events
     */
    function bindEvents() {
        $('#report-class-select').on('change', function () {
            var hasClass = $(this).val() !== '';
            $('#btn-generate-report').prop('disabled', !hasClass);
        });

        $('#btn-generate-report').on('click', function () {
            generateReport();
        });

        $('#btn-download-csv').on('click', function () {
            downloadCsv();
        });
    }

    /**
     * Show alert message in the alert container
     */
    function showAlert(message, type) {
        var $container = $('#clr-alert');
        $container.html(
            '<div class="alert alert-' + type + ' alert-dismissible fade show m-3" role="alert">' +
                $('<span>').text(message).html() +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>'
        );
    }

    /**
     * Generate report via AJAX
     */
    function generateReport() {
        var classId = $('#report-class-select').val();
        var monthVal = $('#report-month').val();

        if (!classId || !monthVal) {
            return;
        }

        var parts = monthVal.split('-');
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);

        // Show loading, hide preview and download button
        $('#clr-loading').removeClass('d-none').addClass('d-flex');
        $('#report-preview').empty();
        $('#clr-alert').empty();
        $('#btn-download-csv').hide().prop('disabled', true);
        $('#clr-learner-count').text('');
        $('#btn-generate-report').prop('disabled', true);

        $.ajax({
            url: reportExtractionAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_class_report',
                nonce: reportExtractionAjax.nonce,
                class_id: classId,
                year: year,
                month: month
            },
            success: function (response) {
                $('#clr-loading').removeClass('d-flex').addClass('d-none');
                $('#btn-generate-report').prop('disabled', false);

                if (response.success && response.data) {
                    currentReportData = {
                        class_id: classId,
                        year: year,
                        month: month
                    };
                    renderPreview(response.data);
                    $('#btn-download-csv').show().prop('disabled', false);
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Failed to generate report.';
                    showAlert(msg, 'warning');
                }
            },
            error: function () {
                $('#clr-loading').removeClass('d-flex').addClass('d-none');
                $('#btn-generate-report').prop('disabled', false);
                showAlert('An error occurred while generating the report.', 'danger');
            }
        });
    }

    /**
     * Build a details column (table with icon rows) matching single class view pattern
     */
    function buildDetailsColumn(fields, extraClasses) {
        var $col = $('<div class="col-sm-12 col-xxl-6 py-3 ' + (extraClasses || '') + '">');
        var $table = $('<table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">');
        var $tbody = $('<tbody>');

        $.each(fields, function (i, field) {
            var $tr = $('<tr>');

            // Label cell with icon
            var $labelTd = $('<td class="py-2 ydcoza-w-150">');
            var $labelWrap = $('<div class="d-inline-flex align-items-center">');
            var $iconCircle = $('<div class="d-flex rounded-circle flex-center me-3" style="width:24px; height:24px">')
                .addClass('bg-' + field.color + '-subtle');
            var $icon = $('<i style="font-size: 12px;">')
                .addClass('bi ' + field.icon + ' text-' + field.color);
            $iconCircle.append($icon);
            $labelWrap.append($iconCircle);
            $labelWrap.append($('<p class="fw-bold mb-0">').text(field.label + ' :'));
            $labelTd.append($labelWrap);

            // Value cell
            var $valueTd = $('<td class="py-2">');
            $valueTd.append($('<p class="fw-semibold mb-0">').text(field.value || '-'));

            $tr.append($labelTd, $valueTd);
            $tbody.append($tr);
        });

        $table.append($tbody);
        $col.append($table);
        return $col;
    }

    /**
     * Render report preview into the preview area
     */
    function renderPreview(data) {
        var $preview = $('#report-preview');
        $preview.empty();

        var header = data.header;
        var learners = data.learners || [];
        var meta = data.meta || {};

        // Error state
        if (!header) {
            showAlert('Class not found or no data available.', 'warning');
            return;
        }

        // Header info section - 2-column table layout matching single class details
        var leftFields = [
            { icon: 'bi-building',       color: 'primary', label: 'Client',             value: header.client_name },
            { icon: 'bi-geo-alt',        color: 'success', label: 'Site',               value: header.site_name },
            { icon: 'bi-layers',         color: 'primary', label: 'Class Type & Subject', value: (header.class_type_name || '') + ' - ' + (header.subject_name || '') },
            { icon: 'bi-calendar-event', color: 'info',    label: 'Month',              value: meta.month_label }
        ];
        var rightFields = [
            { icon: 'bi-calendar-week', color: 'info',    label: 'Class Days',   value: header.class_days },
            { icon: 'bi-clock',         color: 'warning', label: 'Class Times',  value: header.class_times },
            { icon: 'bi-person-badge',  color: 'success', label: 'Facilitator',  value: header.facilitator }
        ];

        var $headerSection = $('<div class="px-xl-4 border-bottom">');
        var $row = $('<div class="row mx-0">');

        $row.append(buildDetailsColumn(leftFields, 'border-end-xxl'));
        $row.append(buildDetailsColumn(rightFields, ''));

        $headerSection.append($row);
        $preview.append($headerSection);

        // No learners
        if (learners.length === 0) {
            showAlert('No learner data found for this class and month.', 'info');
            return;
        }

        // Update learner count in header
        $('#clr-learner-count').text(learners.length + ' learner(s)');

        // Learner table wrapped in card-body
        var $cardBody = $('<div class="card-body p-4 py-2">');
        var $tableWrapper = $('<div class="table-responsive scrollbar">');
        var $table = $('<table class="table table-sm table-hover fs-9 mb-0">');

        var columns = [
            'Surname', 'Initials', 'Level/Module', 'Start Date',
            'Race', 'Gender', 'Month Trained', 'Month Present',
            'Total Trained', 'Total Present', 'Hours %', 'Page %'
        ];
        var centeredCols = { 6: true, 7: true, 8: true, 9: true, 10: true, 11: true };
        var $thead = $('<thead class="border-bottom">');
        var $headerRow = $('<tr>');
        $.each(columns, function (i, col) {
            var cls = 'border-0 text-nowrap' + (centeredCols[i] ? ' text-center' : '');
            $headerRow.append($('<th class="' + cls + '">').text(col));
        });
        $thead.append($headerRow);
        $table.append($thead);

        var $tbody = $('<tbody>');
        $.each(learners, function (i, learner) {
            var $row = $('<tr>');

            $row.append($('<td>').text(learner.surname || '-'));
            $row.append($('<td>').text(learner.initials || '-'));
            $row.append($('<td>').text(learner.subject_name || '-'));
            $row.append($('<td class="text-nowrap">').text(formatDate(learner.start_date)));
            $row.append($('<td>').text(learner.race || '-'));
            $row.append($('<td>').text(learner.gender || '-'));
            $row.append($('<td class="text-center">').text(formatNumber(learner.month_hours_trained)));
            $row.append($('<td class="text-center">').text(formatNumber(learner.month_hours_present)));
            $row.append($('<td class="text-center">').text(formatNumber(learner.hours_trained)));
            $row.append($('<td class="text-center">').text(formatNumber(learner.hours_present)));
            $row.append($('<td class="text-center">').text(formatPercentage(learner.hours_progress_pct)));
            $row.append($('<td class="text-center">').text(formatPercentage(learner.page_progress_pct)));

            $tbody.append($row);
        });
        $table.append($tbody);
        $tableWrapper.append($table);
        $cardBody.append($tableWrapper);
        $preview.append($cardBody);
    }

    /**
     * Trigger CSV download via window.location
     */
    function downloadCsv() {
        if (!currentReportData) {
            return;
        }

        var url = reportExtractionAjax.ajaxurl +
            '?action=download_class_report_csv' +
            '&class_id=' + encodeURIComponent(currentReportData.class_id) +
            '&year=' + encodeURIComponent(currentReportData.year) +
            '&month=' + encodeURIComponent(currentReportData.month) +
            '&nonce=' + encodeURIComponent(reportExtractionAjax.nonce);

        window.location.href = url;
    }

    /**
     * Format a date string for display (DD/MM/YYYY)
     */
    function formatDate(dateStr) {
        if (!dateStr) {
            return '-';
        }
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) {
            return dateStr;
        }
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        return dd + '/' + mm + '/' + yyyy;
    }

    /**
     * Format a number for display
     */
    function formatNumber(val) {
        if (val === null || val === undefined || val === '') {
            return '0';
        }
        var num = parseFloat(val);
        if (isNaN(num)) {
            return '0';
        }
        return num === Math.floor(num) ? String(Math.floor(num)) : num.toFixed(1);
    }

    /**
     * Format a percentage for display
     */
    function formatPercentage(val) {
        if (val === null || val === undefined) {
            return '-';
        }
        var num = parseFloat(val);
        if (isNaN(num)) {
            return '-';
        }
        return num.toFixed(1) + '%';
    }

})(jQuery);
