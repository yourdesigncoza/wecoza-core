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
        $('#report-loading').removeClass('d-none').addClass('d-flex');
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
                $('#report-loading').removeClass('d-flex').addClass('d-none');
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
                $('#report-loading').removeClass('d-flex').addClass('d-none');
                $('#btn-generate-report').prop('disabled', false);
                showAlert('An error occurred while generating the report.', 'danger');
            }
        });
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

        // Header info section
        var $headerSection = $('<div class="p-3 border-bottom bg-body-tertiary">');
        var $dl = $('<dl class="row mb-0 fs-9">');

        var headerFields = [
            { label: 'Client', value: header.client_name },
            { label: 'Site', value: header.site_name },
            { label: 'Class Type & Subject', value: (header.class_type_name || '') + ' - ' + (header.subject_name || '') },
            { label: 'Month', value: meta.month_label },
            { label: 'Class Days', value: header.class_days },
            { label: 'Class Times', value: header.class_times },
            { label: 'Facilitator', value: header.facilitator }
        ];

        $.each(headerFields, function (i, field) {
            $dl.append(
                $('<dt class="col-sm-3 text-body-secondary">').text(field.label),
                $('<dd class="col-sm-9 mb-1">').text(field.value || '-')
            );
        });

        $headerSection.append($dl);
        $preview.append($headerSection);

        // No learners
        if (learners.length === 0) {
            showAlert('No learner data found for this class and month.', 'info');
            return;
        }

        // Update learner count in header
        $('#clr-learner-count').text(learners.length + ' learner(s)');

        // Learner table
        var $tableWrapper = $('<div class="table-responsive scrollbar">');
        var $table = $('<table class="table table-sm table-striped table-hover fs-9 mb-0">');

        var columns = [
            'Surname', 'Initials', 'Level/Module', 'Start Date',
            'Race', 'Gender', 'Month Trained', 'Month Present',
            'Total Trained', 'Total Present', 'Hours %', 'Page %'
        ];
        var $thead = $('<thead>');
        var $headerRow = $('<tr>');
        $.each(columns, function (i, col) {
            $headerRow.append($('<th class="text-nowrap">').text(col));
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
            $row.append($('<td class="text-end">').text(formatNumber(learner.month_hours_trained)));
            $row.append($('<td class="text-end">').text(formatNumber(learner.month_hours_present)));
            $row.append($('<td class="text-end">').text(formatNumber(learner.hours_trained)));
            $row.append($('<td class="text-end">').text(formatNumber(learner.hours_present)));
            $row.append($('<td class="text-end">').text(formatPercentage(learner.hours_progress_pct)));
            $row.append($('<td class="text-end">').text(formatPercentage(learner.page_progress_pct)));

            $tbody.append($row);
        });
        $table.append($tbody);
        $tableWrapper.append($table);
        $preview.append($tableWrapper);
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
