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

// Attendance lock gate:
// - draft: always locked
// - stopped: allow if a valid stop date exists; gate by stop date in JS
// - active: always open
// Note: class_status = 'stopped' means class deactivation (access control).
//       stop_restart_dates = schedule pauses (schedule exclusion). These are distinct concepts.
$classStatus = wecoza_resolve_class_status($class);

// Draft is always locked
if ($classStatus === 'draft') {
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3"><?= esc_html__('Attendance', 'wecoza-core'); ?></h5>
            <div class="alert alert-subtle-warning d-flex align-items-center mb-0">
                <i class="bi bi-lock-fill me-3 fs-4"></i>
                <div><?= esc_html__('This class is in draft status. Attendance capture is not available until the class is activated.', 'wecoza-core'); ?></div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Stopped class: allow attendance only up to the effective stop date
$stopDate = null;
if ($classStatus === 'stopped') {
    $scheduleData = $class['schedule_data'] ?? [];
    if (is_string($scheduleData)) {
        $scheduleData = json_decode($scheduleData, true) ?: [];
    }
    $stopDate = wecoza_get_effective_stop_date($scheduleData);

    if ($stopDate === null) {
        // No valid stop date found — lock the section
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3"><?= esc_html__('Attendance', 'wecoza-core'); ?></h5>
                <div class="alert alert-subtle-warning d-flex align-items-center mb-0">
                    <i class="bi bi-lock-fill me-3 fs-4"></i>
                    <div><?= esc_html__('This class has been stopped. Attendance capture is locked.', 'wecoza-core'); ?></div>
                </div>
            </div>
        </div>
        <?php
        return;
    }
}
?>

<!-- Attendance Section -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">
            <i class="bi bi-clipboard-check me-2"></i>Attendance
        </h5>

        <!-- Summary Stats Bar -->
        <div class="scrollbar mb-4" id="attendance-summary-cards">
            <div class="row g-0 flex-nowrap">
                <div class="col-auto border-end pe-4">
                    <h6 class="text-body-tertiary">Scheduled : <span id="att-total-sessions">...</span></h6>
                </div>
                <div class="col-auto px-4 border-end">
                    <h6 class="text-body-tertiary">Completed : <span id="att-captured-count">...</span></h6>
                </div>
                <div class="col-auto px-4">
                    <h6 class="text-body-tertiary">Remaining : <span id="att-pending-count">...</span></h6>
                </div>
            </div>
        </div>

        <!-- Monthly Calendar View -->
        <div class="mb-3" id="att-calendar-wrapper">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <button type="button" class="btn btn-sm btn-subtle-secondary" id="att-cal-prev">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <h6 class="text-body-tertiary mb-0" id="att-cal-title">...</h6>
                <button type="button" class="btn btn-sm btn-subtle-secondary" id="att-cal-next">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            <div id="att-calendar-grid"></div>
            <div class="d-flex gap-3 mt-2 flex-wrap">
                <small class="text-body-tertiary"><span class="att-cal-legend att-cal-captured"></span> Captured</small>
                <small class="text-body-tertiary"><span class="att-cal-legend att-cal-pending"></span> Pending</small>
                <small class="text-body-tertiary"><span class="att-cal-legend att-cal-exception"></span> Exception</small>
                <small class="text-body-tertiary"><span class="att-cal-legend att-cal-blocked"></span> Blocked</small>
                <small class="text-body-tertiary"><span class="att-cal-legend att-cal-future"></span> Upcoming</small>
            </div>
        </div>

        <!-- Month Filter -->
        <div class="mb-3 d-flex align-items-center gap-2">
            <label for="attendance-month-select" class="text-body-tertiary text-nowrap mb-0">Month :</label>
            <select class="form-select form-select-sm" id="attendance-month-select" style="max-width: 200px;">
                <option value="all">All months</option>
            </select>
        </div>

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
                <tfoot id="attendance-sessions-tfoot" class="border-top"></tfoot>
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
                                <th class="border-top border-translucent text-center">
                                    Hours Present
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="btn-copy-hours-to-all"
                                        title="Copy first learner's hours to all" style="display:none; font-size: 0.75rem;">
                                        <i class="bi bi-clipboard-check me-1"></i>Copy to all
                                    </button>
                                </th>
                                <th class="border-top border-translucent text-center">Hours Absent</th>
                                <th class="border-top border-translucent text-center">Last Completed Page</th>
                            </tr>
                        </thead>
                        <tbody id="capture-learners-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-phoenix-danger btn-sm" id="btn-admin-delete-from-capture" style="display:none;">
                        <i class="bi bi-trash me-1"></i>Delete &amp; Reverse Hours
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-subtle-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-phoenix-primary" id="btn-submit-capture">
                        <i class="bi bi-check-lg me-1"></i>Submit Attendance
                    </button>
                </div>
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
                                <th class="border-top border-translucent text-center">Last Completed Page</th>
                            </tr>
                        </thead>
                        <tbody id="detail-learners-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-subtle-secondary" data-bs-dismiss="modal">Close</button>
                <?php if (is_user_logged_in()): ?>
                <button type="button" class="btn btn-subtle-danger" id="btn-admin-delete-session">
                    <i class="bi bi-trash me-1"></i>Delete &amp; Reverse Hours
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div><!-- /#attendanceDetailModal -->

<?php if ($stopDate !== null): ?>
<script>
// Inject stop date for stopped classes — gates capture actions after stop date in attendance-capture.js
if (window.WeCozaSingleClass) {
    window.WeCozaSingleClass.stopDate = '<?= esc_js($stopDate) ?>';
}
</script>
<?php endif; ?>

<!-- Mark Exception Modal -->
<div class="modal fade" id="attendanceExceptionModal" tabindex="-1" aria-labelledby="attendanceExceptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="attendanceExceptionModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>Mark Exception</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p id="exception-session-info" class="text-muted mb-3"></p>
                <div id="exception-alert"></div>
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
