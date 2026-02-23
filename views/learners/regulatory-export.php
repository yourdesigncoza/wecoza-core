<?php
/**
 * Regulatory Export View
 *
 * Date-range filtered compliance report for Umalusi/DHET submissions.
 * Displays all required regulatory columns with CSV download support.
 * JS (regulatory-export.js) populates the filter dropdowns and table rows.
 *
 * @package WeCoza\Learners
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="regulatory-export-container">

    <!-- Alert Container -->
    <div id="reg-alert" class="alert-container"></div>

    <!-- Loading Spinner -->
    <div id="reg-loading" class="d-flex justify-content-center align-items-center py-4">
        <div class="spinner-border text-primary me-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="text-muted">Loading report...</span>
    </div>

    <!-- Main Content (hidden until JS loads data) -->
    <div id="reg-content" class="d-none">

        <!-- Regulatory Export Card -->
        <div class="card shadow-none border" data-component-card="data-component-card">
            <div class="card-header p-3 border-bottom">
                <!-- Row 1: Title + Export action -->
                <div class="row align-items-center mb-2">
                    <div class="col">
                        <h4 class="mb-0">Regulatory Progressions Export <i class="bi bi-file-earmark-spreadsheet ms-1"></i></h4>
                    </div>
                    <div class="col-auto d-flex align-items-center gap-2">
                        <span id="reg-record-count" class="text-muted fs-9">0 records</span>
                        <button id="btn-reg-export-csv" class="btn btn-phoenix-secondary btn-sm" disabled>
                            <i class="bi bi-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Row 2: Filters -->
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fs-9 mb-1" for="reg-date-from">Date From</label>
                        <input type="date" id="reg-date-from" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-9 mb-1" for="reg-date-to">Date To</label>
                        <input type="date" id="reg-date-to" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-9 mb-1" for="reg-client-filter">Client</label>
                        <select id="reg-client-filter" class="form-select form-select-sm">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-9 mb-1" for="reg-status-filter">Status</label>
                        <select id="reg-status-filter" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button id="btn-reg-generate" class="btn btn-phoenix-secondary btn-sm w-100">
                            <i class="bi bi-funnel me-1"></i> Go
                        </button>
                    </div>
                </div>
            </div>

            <!-- Card Body: Table + Empty State -->
            <div class="card-body p-4 py-2">
                <div id="reg-table-card" class="table-responsive scrollbar">
                    <table class="table table-sm fs-9 mb-0 overflow-hidden" id="reg-table">
                        <thead class="text-body">
                            <tr>
                                <th class="sort pe-1 align-middle white-space-nowrap">First Name</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Surname</th>
                                <th class="sort pe-1 align-middle white-space-nowrap" style="min-width:8rem">SA ID</th>
                                <th class="sort pe-1 align-middle white-space-nowrap" style="min-width:10rem">Programme</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Status</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Class Code</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Client</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Employer</th>
                                <th class="no-sort pe-1 align-middle" style="width:2rem"></th>
                            </tr>
                        </thead>
                        <tbody id="reg-table-body">
                            <!-- JS populates main + detail rows -->
                        </tbody>
                    </table>
                </div>

                <div id="reg-empty" class="d-none text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                    <span class="text-muted">No progressions found for the selected date range.</span>
                </div>
            </div>

        </div><!-- /card -->

    </div><!-- /reg-content -->

</div><!-- /regulatory-export-container -->
