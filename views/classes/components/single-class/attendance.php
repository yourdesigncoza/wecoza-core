<?php
/**
 * Single Class Display - Attendance Section Component
 *
 * Renders the Attendance section on the single class display page.
 * Includes:
 * - Summary cards (Total Sessions, Captured, Pending)
 * - Month filter tabs
 * - Session table (Date, Day, Time, Hours, Status, Action)
 * - Capture modal with per-learner hours inputs
 * - View-detail modal (read-only learner hours breakdown)
 * - Exception modal for marking client cancelled / agent absent
 *
 * JS in assets/js/classes/attendance-capture.js populates all dynamic content.
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables:
 *   - $class: Array of class data from the database
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];

// Return early if no class data
if (empty($class)) {
    return;
}
?>

<!-- Attendance Section -->
<div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0">
            <i class="bi bi-clipboard-check me-2"></i>Attendance
        </h4>
    </div>
    <div class="card-body">

        <!-- Summary Cards Row -->
        <div class="row g-3 mb-4" id="attendance-summary-cards">

            <!-- Total Sessions Card -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi bi-calendar-range fs-3 text-primary"></i>
                        <div>
                            <h4 class="mb-0" id="att-total-sessions">...</h4>
                            <span class="fs-9 text-body-tertiary">Scheduled</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Captured Sessions Card -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi bi-check-circle fs-3 text-success"></i>
                        <div>
                            <h4 class="mb-0" id="att-captured-count">...</h4>
                            <span class="fs-9 text-body-tertiary">Completed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Sessions Card -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi bi-clock fs-3 text-warning"></i>
                        <div>
                            <h4 class="mb-0" id="att-pending-count">...</h4>
                            <span class="fs-9 text-body-tertiary">Remaining</span>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /#attendance-summary-cards -->

        <!-- Month Filter Tabs -->
        <ul class="nav nav-underline mb-3" id="attendance-month-tabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-month="all">All</button>
            </li>
        </ul>

        <!-- Alert Container -->
        <div id="attendance-alert"></div>

        <!-- Session Table -->
        <div class="table-responsive">
            <table class="table table-sm fs-9 mb-0" id="attendance-sessions-table">
                <thead>
                    <tr class="bg-body-highlight">
                        <th class="border-top border-translucent ps-3">Date</th>
                        <th class="border-top border-translucent">Day</th>
                        <th class="border-top border-translucent">Time</th>
                        <th class="border-top border-translucent">Hours</th>
                        <th class="border-top border-translucent">Status</th>
                        <th class="border-top border-translucent text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody id="attendance-sessions-tbody"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Loading sessions...</td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div><!-- /.card-body -->
</div><!-- /.card -->

<!-- Capture Attendance Modal -->
<div class="modal fade" id="attendanceCaptureModal" tabindex="-1" aria-labelledby="attendanceCaptureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="attendanceCaptureModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Capture Attendance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span id="capture-session-info" class="text-muted"></span>
                    <span id="capture-hours-info" class="badge badge-phoenix badge-phoenix-info"></span>
                </div>
                <div id="capture-alert"></div>
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                        <thead>
                            <tr class="bg-body-highlight">
                                <th class="border-top border-translucent ps-3">Learner</th>
                                <th class="border-top border-translucent text-center">Hours Trained</th>
                                <th class="border-top border-translucent text-center">Hours Present</th>
                                <th class="border-top border-translucent text-center">Hours Absent</th>
                            </tr>
                        </thead>
                        <tbody id="capture-learners-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-subtle-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-phoenix-primary" id="btn-submit-capture">
                    <i class="bi bi-check-lg me-1"></i>Submit Attendance
                </button>
            </div>
        </div>
    </div>
</div><!-- /#attendanceCaptureModal -->

<!-- View Session Detail Modal -->
<div class="modal fade" id="attendanceDetailModal" tabindex="-1" aria-labelledby="attendanceDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="attendanceDetailModalLabel">
                    <i class="bi bi-eye me-2"></i>Session Detail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span id="detail-session-info" class="text-muted"></span>
                    <span id="detail-status-badge"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                        <thead>
                            <tr class="bg-body-highlight">
                                <th class="border-top border-translucent ps-3">Learner</th>
                                <th class="border-top border-translucent text-center">Hours Trained</th>
                                <th class="border-top border-translucent text-center">Hours Present</th>
                                <th class="border-top border-translucent text-center">Hours Absent</th>
                            </tr>
                        </thead>
                        <tbody id="detail-learners-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-subtle-secondary" data-bs-dismiss="modal">Close</button>
                <?php if (current_user_can('manage_options')): ?>
                <button type="button" class="btn btn-subtle-danger" id="btn-admin-delete-session">
                    <i class="bi bi-trash me-1"></i>Delete &amp; Reverse Hours
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div><!-- /#attendanceDetailModal -->

<!-- Mark Exception Modal -->
<div class="modal fade" id="attendanceExceptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Mark Exception</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p id="exception-session-info" class="text-muted mb-3"></p>
                <div class="mb-3">
                    <label class="form-label">Exception Type</label>
                    <select class="form-select" id="exception-type-select">
                        <option value="">Select...</option>
                        <option value="client_cancelled">Client Cancelled</option>
                        <option value="agent_absent">Agent Absent</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes (optional)</label>
                    <textarea class="form-control" id="exception-notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-subtle-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-phoenix-warning" id="btn-submit-exception">
                    <i class="bi bi-check-lg me-1"></i>Mark Exception
                </button>
            </div>
        </div>
    </div>
</div><!-- /#attendanceExceptionModal -->
