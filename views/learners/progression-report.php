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

        <!-- Header Row -->
        <div class="row mb-3 align-items-center">
            <div class="col">
                <h4 class="mb-0">
                    <i class="bi bi-graph-up me-2"></i>Learner Progression Report
                </h4>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="row g-3 mb-3">

            <!-- Card 1: Total Learners -->
            <div class="col-md-3">
                <div class="card shadow-none border">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-people fs-3 float-start me-3 text-secondary"></i>
                            <div>
                                <h4 class="mb-0" id="stat-total-learners">0</h4>
                                <span class="text-muted fs-9">across all programmes</span>
                            </div>
                        </div>
                        <div class="mt-2 fs-9 text-muted fw-semibold">Total Learners</div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Completion Rate -->
            <div class="col-md-3">
                <div class="card shadow-none border">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle fs-3 float-start me-3 text-success"></i>
                            <div>
                                <h4 class="mb-0"><span id="stat-completion-rate">0</span>%</h4>
                                <span class="text-muted fs-9">of all progressions</span>
                            </div>
                        </div>
                        <div class="mt-2 fs-9 text-muted fw-semibold">Completion Rate</div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Avg. Progress -->
            <div class="col-md-3">
                <div class="card shadow-none border">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-speedometer2 fs-3 float-start me-3 text-primary"></i>
                            <div>
                                <h4 class="mb-0"><span id="stat-avg-progress">0</span>%</h4>
                                <span class="text-muted fs-9">hours completed</span>
                            </div>
                        </div>
                        <div class="mt-2 fs-9 text-muted fw-semibold">Avg. Progress</div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Active LPs -->
            <div class="col-md-3">
                <div class="card shadow-none border">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-book fs-3 float-start me-3 text-info"></i>
                            <div>
                                <h4 class="mb-0" id="stat-active-lps">0</h4>
                                <span class="text-muted fs-9">currently in progress</span>
                            </div>
                        </div>
                        <div class="mt-2 fs-9 text-muted fw-semibold">Active LPs</div>
                    </div>
                </div>
            </div>

        </div><!-- /summary cards -->

        <!-- Filter Card -->
        <div class="card shadow-none border mb-3">
            <div class="card-body p-3">
                <div class="row g-2 align-items-end">
                    <!-- Search -->
                    <div class="col-md-5">
                        <label class="form-label fs-9 mb-1">Search Learner</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="text"
                                id="report-search"
                                class="form-control"
                                placeholder="Search by learner name or ID..."
                            >
                        </div>
                    </div>
                    <!-- Employer Filter -->
                    <div class="col-md-4">
                        <label class="form-label fs-9 mb-1">Employer</label>
                        <select id="report-employer-filter" class="form-select form-select-sm">
                            <option value="">All Employers</option>
                            <!-- JS populates options from loaded data -->
                        </select>
                    </div>
                    <!-- Search Button -->
                    <div class="col-md-3">
                        <button id="btn-report-search" class="btn btn-phoenix-primary btn-sm w-100">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Filter Pills -->
        <div id="report-status-pills" class="mb-3">
            <button class="btn btn-sm btn-phoenix-secondary me-1 active" data-status="">All</button>
            <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="in_progress">In Progress</button>
            <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="completed">Completed</button>
            <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="on_hold">On Hold</button>
        </div>

        <!-- Results Container -->
        <div id="report-results">
            <!-- JS populates company-grouped accordion sections.
                 Each section: company name header with learner count badge,
                 expandable body with individual learner timeline rows. -->
        </div>

        <!-- Empty State -->
        <div id="report-empty" class="d-none text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
            <span class="text-muted">No progressions found matching your criteria.</span>
        </div>

    </div><!-- /report-content -->

</div><!-- /progression-report-container -->
