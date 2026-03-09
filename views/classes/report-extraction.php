<?php
/**
 * Report Extraction View
 *
 * Displays class selector, month picker, generate/download buttons,
 * and a preview area for report data.
 *
 * @package WeCoza\Views\Classes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="container-fluid py-3">
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="card-title mb-0">Report Extraction</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end mb-4">
                <!-- Class Selector -->
                <div class="col-md-4">
                    <label for="report-class-select" class="form-label fw-semibold">Class</label>
                    <select id="report-class-select" class="form-select">
                        <option value="">-- Select a class --</option>
                    </select>
                </div>

                <!-- Month Picker -->
                <div class="col-md-3">
                    <label for="report-month" class="form-label fw-semibold">Month</label>
                    <input type="month" id="report-month" class="form-control" />
                </div>

                <!-- Generate Button -->
                <div class="col-md-2">
                    <button type="button" id="btn-generate-report" class="btn btn-phoenix-primary w-100" disabled>
                        <span class="fas fa-chart-bar me-1"></span> Generate
                    </button>
                </div>

                <!-- Download CSV Button -->
                <div class="col-md-3">
                    <button type="button" id="btn-download-csv" class="btn btn-phoenix-success w-100" disabled style="display:none;">
                        <span class="fas fa-file-csv me-1"></span> Download CSV
                    </button>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div id="report-loading" class="text-center py-4" style="display:none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-body-secondary">Generating report...</p>
            </div>

            <!-- Report Preview Area -->
            <div id="report-preview"></div>
        </div>
    </div>
</div>
