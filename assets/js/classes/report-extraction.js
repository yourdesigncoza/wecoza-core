/**
 * Report Extraction JS
 *
 * Handles class selection, AJAX report generation preview,
 * and CSV download trigger for the report extraction shortcode.
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
        // Enable/disable generate button based on class selection
        $('#report-class-select').on('change', function () {
            var hasClass = $(this).val() !== '';
            $('#btn-generate-report').prop('disabled', !hasClass);
        });

        // Generate report
        $('#btn-generate-report').on('click', function () {
            generateReport();
        });

        // Download CSV
        $('#btn-download-csv').on('click', function () {
            downloadCsv();
        });
    }

    /**
     * Generate report via AJAX
     */
    function generateReport() {
        var classId = $('#report-class-select').val();
        var monthVal = $('#report-month').val(); // YYYY-MM

        if (!classId || !monthVal) {
            return;
        }

        var parts = monthVal.split('-');
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);

        // Show loading, hide preview and download button
        $('#report-loading').show();
        $('#report-preview').empty();
        $('#btn-download-csv').hide().prop('disabled', true);
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
                $('#report-loading').hide();
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
                    $('#report-preview').html(
                        '<div class="alert alert-warning">' + $('<span>').text(msg).html() + '</div>'
                    );
                }
            },
            error: function () {
                $('#report-loading').hide();
                $('#btn-generate-report').prop('disabled', false);
                $('#report-preview').html(
                    '<div class="alert alert-danger">An error occurred while generating the report.</div>'
                );
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
            $preview.html(
                '<div class="alert alert-warning">Class not found or no data available.</div>'
            );
            return;
        }

        // Header info section
        var $headerSection = $('<div class="mb-4">');
        var $dl = $('<dl class="row mb-0">');

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
                $('<dt class="col-sm-3">').text(field.label),
                $('<dd class="col-sm-9">').text(field.value || '-')
            );
        });

        $headerSection.append($dl);
        $preview.append($headerSection);

        // Learner table
        if (learners.length === 0) {
            $preview.append(
                '<div class="alert alert-info">No learner data found for this class and month.</div>'
            );
            return;
        }

        var $tableWrapper = $('<div class="table-responsive">');
        var $table = $('<table class="table table-sm table-hover">');

        // Table header
        var columns = [
            'Surname', 'Initials', 'Level/Module', 'Start Date',
            'Race', 'Gender', 'Month Trained', 'Month Present',
            'Total Trained', 'Total Present', 'Hours %', 'Page %'
        ];
        var $thead = $('<thead class="table-light">');
        var $headerRow = $('<tr>');
        $.each(columns, function (i, col) {
            $headerRow.append($('<th>').text(col));
        });
        $thead.append($headerRow);
        $table.append($thead);

        // Table body
        var $tbody = $('<tbody>');
        $.each(learners, function (i, learner) {
            var $row = $('<tr>');

            $row.append($('<td>').text(learner.surname || '-'));
            $row.append($('<td>').text(learner.initials || '-'));
            $row.append($('<td>').text(learner.subject_name || '-'));
            $row.append($('<td>').text(formatDate(learner.start_date)));
            $row.append($('<td>').text(learner.race || '-'));
            $row.append($('<td>').text(learner.gender || '-'));
            $row.append($('<td>').text(formatNumber(learner.month_hours_trained)));
            $row.append($('<td>').text(formatNumber(learner.month_hours_present)));
            $row.append($('<td>').text(formatNumber(learner.hours_trained)));
            $row.append($('<td>').text(formatNumber(learner.hours_present)));
            $row.append($('<td>').text(formatPercentage(learner.hours_progress_pct)));
            $row.append($('<td>').text(formatPercentage(learner.page_progress_pct)));

            $tbody.append($row);
        });
        $table.append($tbody);
        $tableWrapper.append($table);
        $preview.append($tableWrapper);

        // Summary count
        $preview.append(
            $('<p class="text-body-secondary mt-2">').text(learners.length + ' learner(s) found')
        );
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
