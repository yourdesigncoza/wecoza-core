<?php
/**
 * Class Learner Report View
 *
 * Displays class selector, month picker, generate button,
 * and a preview area with CSV download for class learner reports.
 *
 * @package WeCoza\Views\Classes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="class-learner-report-container">

    <!-- Alert Container -->
    <div id="clr-alert" class="alert-container"></div>

    <!-- Class Learner Report Card -->
    <div class="card shadow-none border" data-component-card="data-component-card">
        <div class="card-header p-3 border-bottom">
            <!-- Row 1: Title + Download action -->
            <div class="row align-items-center mb-2">
                <div class="col">
                    <h4 class="mb-0">Class Learner Report <i class="bi bi-file-earmark-spreadsheet ms-1"></i></h4>
                </div>
                <div class="col-auto d-flex align-items-center gap-2">
                    <span id="clr-learner-count" class="text-muted fs-9"></span>
                    <button type="button" id="btn-download-csv" class="btn btn-phoenix-secondary btn-sm" disabled style="display:none;">
                        <i class="bi bi-download me-1"></i> Download CSV
                    </button>
                </div>
            </div>

            <!-- Row 2: Filters -->
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fs-9 mb-1" for="report-class-select">Class</label>
                    <select id="report-class-select" class="form-select form-select-sm">
                        <option value="">-- Select a class --</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-9 mb-1" for="report-month">Month</label>
                    <input type="month" id="report-month" class="form-control form-control-sm" />
                </div>
                <div class="col-auto">
                    <button type="button" id="btn-generate-report" class="btn btn-phoenix-primary btn-sm" disabled>
                        <i class="bi bi-bar-chart me-1"></i> Generate
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Loading Spinner -->
            <div id="report-loading" class="justify-content-center align-items-center py-4 d-none">
                <div class="spinner-border text-primary me-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="text-muted">Generating report...</span>
            </div>

            <!-- Report Preview Area -->
            <div id="report-preview"></div>
        </div>
    </div>
</div>
