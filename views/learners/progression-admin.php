<?php
/**
 * Progression Admin View
 *
 * Full-featured admin shell for learner progression management.
 * JS (progression-admin.js) populates table rows, filter dropdowns, and wires all interactions.
 *
 * @package WeCoza\Learners
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="progression-admin-container">

    <!-- Alert Container -->
    <div id="progression-admin-alert" class="alert-container"></div>

    <!-- Loading Spinner -->
    <div id="progression-admin-loading" class="d-flex justify-content-center align-items-center py-4">
        <div class="spinner-border text-primary me-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="text-muted">Loading progressions...</span>
    </div>

    <!-- Main Content (hidden until JS loads data) -->
    <div id="progression-admin-content" class="d-none">

        <!-- Progression Management Card -->
        <div class="card shadow-none border" data-component-card="data-component-card">
            <div class="card-header p-3 border-bottom">
                <div class="row align-items-center mb-2">
                    <div class="col">
                        <h4 class="mb-0">Progression Management <i class="bi bi-mortarboard ms-1"></i></h4>
                    </div>
                    <div class="col-auto">
                        <?php echo wecoza_component('form-help-button', ['offcanvas_id' => 'progressionAdminHelp']); ?>
                        <button id="btn-start-new-lp" class="btn btn-phoenix-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Start New LP
                        </button>
                    </div>
                </div>
                <form id="progression-filter-form" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fs-9 mb-1">Client</label>
                        <select id="filter-client" class="form-select form-select-sm">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-9 mb-1">Class</label>
                        <select id="filter-class" class="form-select form-select-sm">
                            <option value="">All Classes</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-9 mb-1">Learning Programme</label>
                        <select id="filter-subject" class="form-select form-select-sm">
                            <option value="">All LPs</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-9 mb-1">Status</label>
                        <select id="filter-status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-phoenix-secondary btn-sm w-100">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bulk Action Bar (hidden until checkboxes selected) -->
            <div id="bulk-action-bar" class="d-none d-flex align-items-center justify-content-between px-4 py-2 bg-info-subtle border-bottom">
                <span><strong id="selected-count">0</strong> progression(s) selected</span>
                <button id="btn-bulk-complete" class="btn btn-phoenix-success btn-sm">
                    <i class="bi bi-check2-all me-1"></i> Bulk Complete
                </button>
            </div>

            <div class="card-body p-4 py-2">
                <div class="table-responsive">
                    <table id="progression-admin-table" class="table table-hover table-sm fs-9 mb-0">
                        <thead class="border-bottom">
                            <tr>
                                <th class="border-0 ps-3" style="width: 40px;">
                                    <input type="checkbox" id="select-all-progressions" class="form-check-input">
                                </th>
                                <th class="border-0">Learner</th>
                                <th class="border-0">Learning Programme</th>
                                <th class="border-0">Class</th>
                                <th class="border-0">Status</th>
                                <th class="border-0">Progress</th>
                                <th class="border-0">Start Date</th>
                                <th class="border-0 text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="progression-admin-tbody">
                            <!-- JS populates rows -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="progression-pagination" class="d-flex justify-content-between align-items-center p-3 border-top">
                    <span id="pagination-info" class="text-muted fs-9"></span>
                    <nav>
                        <ul id="pagination-controls" class="pagination pagination-sm mb-0"></ul>
                    </nav>
                </div>
            </div>
        </div>

    </div><!-- /progression-admin-content -->

    <!-- =====================================================================
         Start New LP Modal
         ===================================================================== -->
    <div class="modal fade" id="startNewLPModal" tabindex="-1" aria-labelledby="startNewLPModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="startNewLPModalLabel">Start New Learning Programme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="start-lp-alert"></div>
                    <form id="start-lp-form">
                        <div class="mb-3">
                            <label class="form-label" for="start-lp-learner">
                                Learner <span class="text-danger">*</span>
                            </label>
                            <select id="start-lp-learner" class="form-select" required>
                                <option value="">Select Learner...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="start-lp-subject">
                                Learning Programme <span class="text-danger">*</span>
                            </label>
                            <select id="start-lp-subject" class="form-select" required>
                                <option value="">Select LP...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="start-lp-class">Class (optional)</label>
                            <select id="start-lp-class" class="form-select">
                                <option value="">No class</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="start-lp-notes">Notes (optional)</label>
                            <textarea id="start-lp-notes" class="form-control" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-phoenix-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-submit-start-lp" class="btn btn-phoenix-primary btn-sm">
                        <i class="bi bi-play-circle me-1"></i> Start LP
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         Hours Log Modal
         ===================================================================== -->
    <div class="modal fade" id="hoursLogModal" tabindex="-1" aria-labelledby="hoursLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hoursLogModalLabel">Hours Audit Trail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="hours-log-summary" class="mb-3">
                        <!-- JS fills: learner name, LP name, status badge, total hours -->
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm fs-9">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Hours Trained</th>
                                    <th>Hours Present</th>
                                    <th>Source</th>
                                    <th>Captured By</th>
                                    <th>Session</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="hours-log-tbody">
                                <!-- JS populates -->
                            </tbody>
                        </table>
                    </div>
                    <div id="hours-log-empty" class="text-center text-muted py-3 d-none">
                        No hours logged yet for this progression.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         Bulk Complete Confirmation Modal
         ===================================================================== -->
    <div class="modal fade" id="bulkCompleteModal" tabindex="-1" aria-labelledby="bulkCompleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkCompleteModalLabel">Confirm Bulk Complete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to mark <strong id="bulk-complete-count">0</strong> progression(s) as completed.</p>
                    <p class="text-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Note: Bulk complete does not require portfolio uploads. Individual completions with portfolios should use the learner view.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-phoenix-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btn-confirm-bulk-complete" class="btn btn-phoenix-success btn-sm">
                        <i class="bi bi-check2-all me-1"></i> Confirm Complete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php echo wecoza_component('form-help-panel', ['form_key' => 'progression-admin', 'offcanvas_id' => 'progressionAdminHelp']); ?>

</div><!-- /progression-admin-container -->
