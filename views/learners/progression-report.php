<?php
/**
 * Progression Report View
 *
 * Read-only learner progression report shell with summary stats, search/filter
 * controls, and a results container. JS (progression-report.js) populates the
 * summary cards, employer dropdown, and accordion-grouped learner timeline rows.
 *
 * @package WeCoza\Learners
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="progression-report-container">

    <!-- Alert Container -->
    <div id="report-alert" class="alert-container"></div>

    <!-- Loading Spinner -->
    <div id="report-loading" class="d-flex justify-content-center align-items-center py-4">
        <div class="spinner-border text-primary me-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="text-muted">Loading report...</span>
    </div>

    <!-- Main Content (hidden until JS loads data) -->
    <div id="report-content" class="d-none">

        <div class="card shadow-none border my-3" data-component-card="data-component-card">

            <!-- Card Header: title row + stats bar -->
            <div class="card-header p-3 border-bottom">

                <!-- Row 1: Title, Search, Employer -->
                <div class="row g-3 justify-content-between align-items-center mb-3">
                    <div class="col-12 col-md">
                        <h4 class="text-body mb-0">
                            <i class="bi bi-graph-up me-2"></i>Learner Progression Report
                        </h4>
                    </div>
                    <div class="col-auto">
                        <input type="text" id="report-search"
                               class="form-control form-control-sm"
                               placeholder="Search learner..."
                               style="min-width:200px;">
                    </div>
                    <div class="col-auto">
                        <select id="report-employer-filter" class="form-select form-select-sm"
                                style="min-width:180px;">
                            <option value="">All Employers</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Stats Bar -->
                <div class="col-12">
                    <div class="scrollbar">
                        <div class="row g-0 flex-nowrap" id="progression-summary">
                            <div class="col-auto border-end pe-4">
                                <h6 class="text-body-tertiary">Total Learners : <span id="stat-total-learners">0</span></h6>
                            </div>
                            <div class="col-auto px-4 border-end">
                                <h6 class="text-body-tertiary">Completion Rate : <span id="stat-completion-rate">0</span>% <span id="stat-completion-badge" class="badge badge-phoenix fs-10 badge-phoenix-success"></span></h6>
                            </div>
                            <div class="col-auto px-4 border-end">
                                <h6 class="text-body-tertiary">Avg. Progress : <span id="stat-avg-progress">0</span>% <span id="stat-progress-badge" class="badge badge-phoenix fs-10 badge-phoenix-info"></span></h6>
                            </div>
                            <div class="col-auto px-4">
                                <h6 class="text-body-tertiary">Active LPs : <span id="stat-active-lps">0</span></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Body: pills + results -->
            <div class="card-body p-4 py-2">

                <!-- Status Pills -->
                <div id="report-status-pills" class="mb-3 mt-2">
                    <button class="btn btn-sm btn-phoenix-secondary me-1 active" data-status="">All</button>
                    <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="in_progress">In Progress</button>
                    <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="completed">Completed</button>
                    <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="on_hold">On Hold</button>
                </div>

                <!-- Results -->
                <div id="report-results"></div>

                <!-- Empty State -->
                <div id="report-empty" class="d-none text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                    <span class="text-muted">No progressions found matching your criteria.</span>
                </div>
            </div>

        </div><!-- /card -->

    </div><!-- /report-content -->

</div><!-- /progression-report-container -->
