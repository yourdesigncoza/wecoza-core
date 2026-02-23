/**
 * Attendance Capture JavaScript Module
 *
 * Provides all interactive functionality for the attendance section on
 * the single class display page: session list loading, month filtering,
 * capture modal, view-detail modal, exception modal, and admin delete.
 *
 * Config passed via window.WeCozaSingleClass:
 *   - classId, ajaxUrl, attendanceNonce, learnerIds, isAdmin
 *
 * @package WeCoza_Classes
 * @since 1.0.0
 */
(function($) {
    'use strict';

    const config = window.WeCozaSingleClass || {};
    if (!config.classId) return; // Not on single class page

    let allSessions  = [];     // Full session list from server
    let currentMonth = 'all'; // Active month filter

    // Module-level state for open modals
    let captureDate      = '';
    let exceptionDate    = '';
    let detailSessionId  = 0;

    // =========================================================
    // DOM READY
    // =========================================================

    $(document).ready(function() {
        loadSessions();
        bindEvents();
    });

    // =========================================================
    // SECTION 1: SESSION LIST LOADING
    // =========================================================

    /**
     * Fetch session list from the server and render all UI components.
     */
    function loadSessions() {
        $('#attendance-sessions-tbody').html(
            '<tr><td colspan="6" class="text-center text-muted py-3">'
            + '<span class="spinner-border spinner-border-sm me-2"></span>Loading sessions...'
            + '</td></tr>'
        );

        $.ajax({
            url:  config.ajaxUrl,
            type: 'GET',
            data: {
                action:   'wecoza_attendance_get_sessions',
                nonce:    config.attendanceNonce,
                class_id: config.classId
            },
            success: function(response) {
                if (response.success && response.data && response.data.sessions) {
                    allSessions = response.data.sessions;
                    updateSummaryCards();
                    buildMonthTabs();
                    renderSessionTable();
                } else {
                    showAlert(
                        '#attendance-alert',
                        (response.data && response.data.message) || 'Failed to load sessions.',
                        'danger'
                    );
                    $('#attendance-sessions-tbody').html(
                        '<tr><td colspan="6" class="text-center text-muted py-3">Unable to load sessions.</td></tr>'
                    );
                }
            },
            error: function() {
                showAlert('#attendance-alert', 'Server error loading sessions.', 'danger');
                $('#attendance-sessions-tbody').html(
                    '<tr><td colspan="6" class="text-center text-muted py-3">Server error. Please refresh.</td></tr>'
                );
            }
        });
    }

    // =========================================================
    // SECTION 2: SUMMARY CARDS
    // =========================================================

    /**
     * Update summary card counts from the loaded session data.
     */
    function updateSummaryCards() {
        const total      = allSessions.length;
        const captured   = allSessions.filter(function(s) { return s.status === 'captured'; }).length;
        const exceptions = allSessions.filter(function(s) {
            return s.status === 'client_cancelled' || s.status === 'agent_absent';
        }).length;
        const pending    = allSessions.filter(function(s) { return s.status === 'pending'; }).length;

        $('#att-total-sessions').text(total);
        $('#att-captured-count').text(captured + exceptions);
        $('#att-pending-count').text(pending);
    }

    // =========================================================
    // SECTION 3: MONTH TABS
    // =========================================================

    /**
     * Build month filter tabs from unique YYYY-MM values in session dates.
     * Inserts tab buttons after the static "All" tab.
     */
    function buildMonthTabs() {
        const $tabs = $('#attendance-month-tabs');

        // Remove all tabs except the "All" tab (first)
        $tabs.find('li:not(:first)').remove();

        const months = new Map();
        allSessions.forEach(function(s) {
            if (!s.date) return;
            const ym = s.date.substring(0, 7); // "YYYY-MM"
            if (!months.has(ym)) {
                const d     = new Date(s.date + 'T00:00:00');
                const label = d.toLocaleString('en-US', { month: 'short', year: 'numeric' });
                months.set(ym, label);
            }
        });

        months.forEach(function(label, ym) {
            var activeClass = (ym === currentMonth) ? ' active' : '';
            $tabs.append(
                '<li class="nav-item">'
                + '<button class="nav-link' + activeClass + '" data-month="' + ym + '">' + label + '</button>'
                + '</li>'
            );
        });

        // Keep "All" tab active state in sync
        if (currentMonth !== 'all') {
            $tabs.find('[data-month="all"]').removeClass('active');
        }
    }

    // =========================================================
    // SECTION 4: RENDER SESSION TABLE
    // =========================================================

    /**
     * Render session rows filtered by the active month.
     */
    function renderSessionTable() {
        const filtered = currentMonth === 'all'
            ? allSessions
            : allSessions.filter(function(s) {
                return s.date && s.date.substring(0, 7) === currentMonth;
            });

        if (filtered.length === 0) {
            $('#attendance-sessions-tbody').html(
                '<tr><td colspan="6" class="text-center text-muted py-3">No sessions found for this period.</td></tr>'
            );
            return;
        }

        let html = '';
        filtered.forEach(function(s) {
            const statusBadge = getStatusBadge(s.status);
            const actionBtn   = getActionButton(s);
            const startTime   = s.start_time || '';
            const endTime     = s.end_time   || '';
            const timeRange   = startTime && endTime ? startTime + ' - ' + endTime : startTime || '—';
            const hours       = s.scheduled_hours ? parseFloat(s.scheduled_hours).toFixed(1) : '—';

            html += '<tr data-date="' + escAttr(s.date) + '" data-session-id="' + (s.session_id || '') + '">'
                + '<td class="align-middle ps-3">' + escHtml(s.date) + '</td>'
                + '<td class="align-middle">'      + escHtml(s.day || '') + '</td>'
                + '<td class="align-middle">'      + escHtml(timeRange) + '</td>'
                + '<td class="align-middle">'      + escHtml(hours) + '</td>'
                + '<td class="align-middle">'      + statusBadge + '</td>'
                + '<td class="align-middle text-end pe-3">' + actionBtn + '</td>'
                + '</tr>';
        });

        $('#attendance-sessions-tbody').html(html);
    }

    // =========================================================
    // SECTION 4a: STATUS BADGE HELPER
    // =========================================================

    /**
     * Return an HTML badge for the given session status.
     *
     * @param {string} status
     * @returns {string} HTML string
     */
    function getStatusBadge(status) {
        const map = {
            pending:          { cls: 'badge-phoenix-secondary', label: 'Pending' },
            captured:         { cls: 'badge-phoenix-success',   label: 'Captured' },
            client_cancelled: { cls: 'badge-phoenix-warning',   label: 'Client Cancelled' },
            agent_absent:     { cls: 'badge-phoenix-danger',    label: 'Agent Absent' },
        };
        const info = map[status] || { cls: 'badge-phoenix-secondary', label: status || 'Unknown' };
        return '<span class="badge badge-phoenix ' + info.cls + '">' + escHtml(info.label) + '</span>';
    }

    // =========================================================
    // SECTION 4b: ACTION BUTTON HELPER
    // =========================================================

    /**
     * Return the action button(s) HTML for a session row.
     *
     * Pending sessions: Capture + Exception triangle buttons.
     * Captured sessions: View button.
     * Exception sessions: dash (or View if admin and session_id exists).
     *
     * @param {Object} s  Session object
     * @returns {string} HTML string
     */
    function getActionButton(s) {
        if (s.status === 'pending') {
            return '<div class="btn-group btn-group-sm">'
                + '<button class="btn btn-sm btn-phoenix-primary btn-capture" data-date="' + escAttr(s.date) + '">Capture</button>'
                + '<button class="btn btn-sm btn-subtle-warning btn-exception" data-date="' + escAttr(s.date) + '" title="Mark Exception" aria-label="Mark Exception">'
                + '<i class="bi bi-exclamation-triangle"></i>'
                + '</button>'
                + '</div>';
        }

        if (s.status === 'captured' && s.session_id) {
            return '<button class="btn btn-sm btn-subtle-info btn-view-detail" data-session-id="' + escAttr(s.session_id) + '">View</button>';
        }

        // Exception statuses: show View for admin if session_id exists, otherwise dash
        if ((s.status === 'client_cancelled' || s.status === 'agent_absent') && s.session_id && config.isAdmin) {
            return '<button class="btn btn-sm btn-subtle-secondary btn-view-detail" data-session-id="' + escAttr(s.session_id) + '">View</button>';
        }

        return '<span class="text-muted">—</span>';
    }

    // =========================================================
    // SECTION 5: EVENT BINDING
    // =========================================================

    /**
     * Bind all event listeners for the attendance section.
     */
    function bindEvents() {
        // Month tab click
        $('#attendance-month-tabs').on('click', '.nav-link', function() {
            $('#attendance-month-tabs .nav-link').removeClass('active');
            $(this).addClass('active');
            currentMonth = $(this).data('month') || 'all';
            renderSessionTable();
        });

        // Capture button click (delegated — table re-renders on filter change)
        $('#attendance-sessions-tbody').on('click', '.btn-capture', function() {
            openCaptureModal($(this).data('date'));
        });

        // Exception button click
        $('#attendance-sessions-tbody').on('click', '.btn-exception', function() {
            openExceptionModal($(this).data('date'));
        });

        // View detail button click
        $('#attendance-sessions-tbody').on('click', '.btn-view-detail', function() {
            openDetailModal($(this).data('session-id'));
        });

        // Submit capture
        $('#btn-submit-capture').on('click', submitCapture);

        // Submit exception
        $('#btn-submit-exception').on('click', submitException);

        // Admin delete
        $('#btn-admin-delete-session').on('click', adminDeleteSession);

        // Hours present input change -> auto-calculate hours absent
        $('#capture-learners-tbody').on('input change', '.hours-present-input', function() {
            const $row   = $(this).closest('tr');
            const trained = parseFloat($row.find('.hours-trained-val').text()) || 0;
            const present = parseFloat($(this).val()) || 0;
            const absent  = Math.max(0, trained - present);
            $row.find('.hours-absent-val').text(absent.toFixed(1));
        });
    }

    // =========================================================
    // SECTION 6: CAPTURE MODAL
    // =========================================================

    /**
     * Open the capture modal for a given session date, pre-filling
     * the learner list with hours inputs at the scheduled default.
     *
     * @param {string} date  Session date in YYYY-MM-DD format
     */
    function openCaptureModal(date) {
        captureDate = date;

        // Find the session in the full list
        const session = allSessions.find(function(s) { return s.date === date; });
        if (!session) {
            showAlert('#attendance-alert', 'Session not found for date: ' + date, 'danger');
            return;
        }

        const scheduledHours = parseFloat(session.scheduled_hours) || 0;
        const dayLabel       = session.day || '';

        $('#capture-session-info').text(date + (dayLabel ? ' (' + dayLabel + ')' : ''));
        $('#capture-hours-info').text('Scheduled: ' + scheduledHours.toFixed(1) + ' hours');

        // Build learner rows
        const learnerIds = config.learnerIds || [];
        let html = '';

        if (learnerIds.length === 0) {
            html = '<tr><td colspan="4" class="text-center text-muted py-2">No enrolled learners found.</td></tr>';
            $('#btn-submit-capture').prop('disabled', true);
        } else {
            learnerIds.forEach(function(learner) {
                const id   = learner.id || learner.learner_id || 0;
                const name = learner.first_name
                    ? learner.first_name + ' ' + (learner.surname || learner.last_name || '')
                    : (learner.name || 'Unknown');

                html += '<tr data-learner-id="' + escAttr(id) + '">'
                    + '<td class="align-middle ps-3">' + escHtml(name.trim()) + '</td>'
                    + '<td class="align-middle text-center"><span class="hours-trained-val">' + scheduledHours.toFixed(1) + '</span></td>'
                    + '<td class="align-middle text-center">'
                    + '<input type="number" class="form-control form-control-sm hours-present-input"'
                    + ' value="' + scheduledHours.toFixed(1) + '"'
                    + ' min="0" max="' + scheduledHours.toFixed(1) + '"'
                    + ' step="0.5"'
                    + ' style="width: 80px; display: inline-block;">'
                    + '</td>'
                    + '<td class="align-middle text-center"><span class="hours-absent-val">0.0</span></td>'
                    + '</tr>';
            });
        }

        $('#capture-learners-tbody').html(html);
        clearAlert('#capture-alert');

        // Reset submit button state
        $('#btn-submit-capture').prop('disabled', false).html(
            '<i class="bi bi-check-lg me-1"></i>Submit Attendance'
        );

        showModal('attendanceCaptureModal');
    }

    /**
     * Collect learner hours from the capture modal and POST to the capture endpoint.
     */
    function submitCapture() {
        const $btn = $('#btn-submit-capture');
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...'
        );
        clearAlert('#capture-alert');

        // Collect and validate per-learner hours
        const learnerHours = [];
        let isValid = true;
        $('#capture-learners-tbody tr').each(function() {
            const learnerId = parseInt($(this).data('learner-id')) || 0;
            if (learnerId <= 0) return;

            const $input = $(this).find('.hours-present-input');
            const hoursPresent = parseFloat($input.val());
            const maxHours = parseFloat($input.attr('max'));

            if (isNaN(hoursPresent) || hoursPresent < 0 || hoursPresent > maxHours) {
                isValid = false;
                $input.addClass('is-invalid');
            } else {
                $input.removeClass('is-invalid');
                learnerHours.push({
                    learner_id:    learnerId,
                    hours_present: hoursPresent,
                });
            }
        });

        if (!isValid) {
            showAlert('#capture-alert', 'Please ensure all hours are between 0 and the scheduled maximum.', 'danger');
            $btn.prop('disabled', false).html(
                '<i class="bi bi-check-lg me-1"></i>Submit Attendance'
            );
            return;
        }

        $.ajax({
            url:  config.ajaxUrl,
            type: 'POST',
            data: {
                action:        'wecoza_attendance_capture',
                nonce:         config.attendanceNonce,
                class_id:      config.classId,
                session_date:  captureDate,
                learner_hours: learnerHours,
            },
            success: function(response) {
                if (response.success) {
                    hideModal('attendanceCaptureModal');
                    showToast('Attendance captured successfully.', 'success');
                    loadSessions();
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to capture attendance.';
                    showAlert('#capture-alert', msg, 'danger');
                    $btn.prop('disabled', false).html(
                        '<i class="bi bi-check-lg me-1"></i>Submit Attendance'
                    );
                }
            },
            error: function() {
                showAlert('#capture-alert', 'Server error. Please try again.', 'danger');
                $btn.prop('disabled', false).html(
                    '<i class="bi bi-check-lg me-1"></i>Submit Attendance'
                );
            }
        });
    }

    // =========================================================
    // SECTION 7: EXCEPTION MODAL
    // =========================================================

    /**
     * Open the exception modal for a given session date.
     *
     * @param {string} date  Session date in YYYY-MM-DD format
     */
    function openExceptionModal(date) {
        exceptionDate = date;
        $('#exception-session-info').text('Date: ' + date);
        $('#exception-type-select').val('');
        $('#exception-notes').val('');
        clearAlert('#exception-alert');

        // Reset submit button state (prevents stuck "Submitting..." after prior success)
        $('#btn-submit-exception').prop('disabled', false).html(
            '<i class="bi bi-check-lg me-1"></i>Mark Exception'
        );

        showModal('attendanceExceptionModal');
    }

    /**
     * Validate and POST the exception to the mark_exception endpoint.
     */
    function submitException() {
        const exceptionType = $('#exception-type-select').val();

        if (!exceptionType) {
            showAlert('#exception-alert', 'Please select an exception type.', 'danger');
            return;
        }

        const $btn = $('#btn-submit-exception');
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...'
        );

        $.ajax({
            url:  config.ajaxUrl,
            type: 'POST',
            data: {
                action:         'wecoza_attendance_mark_exception',
                nonce:          config.attendanceNonce,
                class_id:       config.classId,
                session_date:   exceptionDate,
                exception_type: exceptionType,
                notes:          $('#exception-notes').val(),
            },
            success: function(response) {
                if (response.success) {
                    hideModal('attendanceExceptionModal');
                    showToast('Exception marked successfully.', 'success');
                    loadSessions();
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to mark exception.';
                    showAlert('#exception-alert', msg, 'danger');
                    $btn.prop('disabled', false).html(
                        '<i class="bi bi-check-lg me-1"></i>Mark Exception'
                    );
                }
            },
            error: function() {
                showAlert('#exception-alert', 'Server error. Please try again.', 'danger');
                $btn.prop('disabled', false).html(
                    '<i class="bi bi-check-lg me-1"></i>Mark Exception'
                );
            }
        });
    }

    // =========================================================
    // SECTION 8: VIEW DETAIL MODAL
    // =========================================================

    /**
     * Fetch session detail from the server and display the read-only breakdown.
     *
     * @param {number|string} sessionId
     */
    function openDetailModal(sessionId) {
        detailSessionId = parseInt(sessionId) || 0;
        if (!detailSessionId) {
            showAlert('#attendance-alert', 'Invalid session ID.', 'danger');
            return;
        }

        // Show modal with loading state
        $('#detail-session-info').text('Loading...');
        $('#detail-status-badge').html('');
        $('#detail-learners-tbody').html(
            '<tr><td colspan="4" class="text-center text-muted py-2">'
            + '<span class="spinner-border spinner-border-sm me-2"></span>Loading detail...'
            + '</td></tr>'
        );

        // Reset delete button
        $('#btn-admin-delete-session').prop('disabled', false).html(
            '<i class="bi bi-trash me-1"></i>Delete &amp; Reverse Hours'
        );

        showModal('attendanceDetailModal');

        $.ajax({
            url:  config.ajaxUrl,
            type: 'GET',
            data: {
                action:     'wecoza_attendance_get_detail',
                nonce:      config.attendanceNonce,
                session_id: detailSessionId,
            },
            success: function(response) {
                if (response.success && response.data) {
                    renderDetailModal(response.data);
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to load session detail.';
                    $('#detail-learners-tbody').html(
                        '<tr><td colspan="4" class="text-center text-danger py-2">' + escHtml(msg) + '</td></tr>'
                    );
                }
            },
            error: function() {
                $('#detail-learners-tbody').html(
                    '<tr><td colspan="4" class="text-center text-danger py-2">Server error loading detail.</td></tr>'
                );
            }
        });
    }

    /**
     * Populate the detail modal with session info and per-learner rows.
     *
     * @param {Object} data  Response data from wecoza_attendance_get_detail
     */
    function renderDetailModal(data) {
        const session  = data.session || {};
        const learners = data.learners || [];

        // Session info line
        const sessionDate = session.session_date || '';
        const sessionDay  = session.day          || '';
        $('#detail-session-info').text(
            sessionDate + (sessionDay ? ' (' + sessionDay + ')' : '')
        );
        $('#detail-status-badge').html(getStatusBadge(session.status || 'captured'));

        // Learner rows (read-only)
        if (learners.length === 0) {
            $('#detail-learners-tbody').html(
                '<tr><td colspan="4" class="text-center text-muted py-2">No learner data available.</td></tr>'
            );
            return;
        }

        let html = '';
        learners.forEach(function(l) {
            const name          = escHtml(l.learner_name || l.name || 'Unknown');
            const hoursTrained  = parseFloat(l.hours_trained  || 0).toFixed(1);
            const hoursPresent  = parseFloat(l.hours_present  || 0).toFixed(1);
            const hoursAbsent   = parseFloat(l.hours_absent   || 0).toFixed(1);

            html += '<tr>'
                + '<td class="align-middle ps-3">' + name + '</td>'
                + '<td class="align-middle text-center">' + escHtml(hoursTrained) + '</td>'
                + '<td class="align-middle text-center">' + escHtml(hoursPresent) + '</td>'
                + '<td class="align-middle text-center">' + escHtml(hoursAbsent) + '</td>'
                + '</tr>';
        });

        $('#detail-learners-tbody').html(html);
    }

    // =========================================================
    // SECTION 9: ADMIN DELETE
    // =========================================================

    /**
     * Confirm and POST admin delete for the currently-open detail session.
     */
    function adminDeleteSession() {
        if (!detailSessionId) {
            return;
        }

        if (!window.confirm('Are you sure? This will reverse all hours logged for this session.')) {
            return;
        }

        const $btn = $('#btn-admin-delete-session');
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...'
        );

        $.ajax({
            url:  config.ajaxUrl,
            type: 'POST',
            data: {
                action:     'wecoza_attendance_admin_delete',
                nonce:      config.attendanceNonce,
                session_id: detailSessionId,
            },
            success: function(response) {
                if (response.success) {
                    hideModal('attendanceDetailModal');
                    showToast('Session deleted and hours reversed.', 'success');
                    loadSessions();
                } else {
                    const msg = (response.data && response.data.message) || 'Failed to delete session.';
                    showAlert('#attendance-alert', msg, 'danger');
                    $btn.prop('disabled', false).html(
                        '<i class="bi bi-trash me-1"></i>Delete &amp; Reverse Hours'
                    );
                }
            },
            error: function() {
                showAlert('#attendance-alert', 'Server error. Please try again.', 'danger');
                $btn.prop('disabled', false).html(
                    '<i class="bi bi-trash me-1"></i>Delete &amp; Reverse Hours'
                );
            }
        });
    }

    // =========================================================
    // SECTION 10: UTILITY HELPERS
    // =========================================================

    /**
     * Show a Bootstrap-style alert inside a container element.
     * Uses Phoenix alert-subtle variant for consistency.
     *
     * @param {string} selector  CSS selector of the container
     * @param {string} message   Alert body text
     * @param {string} type      success|danger|warning|info
     */
    function showAlert(selector, message, type) {
        const iconClass = type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';

        $(selector).html(
            '<div class="alert alert-subtle-' + type + ' d-flex align-items-center py-2 mt-2">'
            + '<i class="bi bi-' + iconClass + ' me-2"></i>'
            + '<span>' + escHtml(message) + '</span>'
            + '</div>'
        );

        // Auto-dismiss success after 5 seconds
        if (type === 'success') {
            setTimeout(function() { $(selector).html(''); }, 5000);
        }
    }

    /**
     * Clear an alert container.
     *
     * @param {string} selector  CSS selector of the container
     */
    function clearAlert(selector) {
        $(selector).html('');
    }

    /**
     * Show a temporary toast notification fixed at the top-right.
     * Auto-dismisses after 4 seconds.
     *
     * @param {string} message  Notification text
     * @param {string} type     success|danger|warning|info
     */
    function showToast(message, type) {
        const bgClass = type === 'success' ? 'bg-success'
            : type === 'danger'  ? 'bg-danger'
            : type === 'warning' ? 'bg-warning text-dark'
            : 'bg-info';

        const $toast = $('<div>')
            .addClass('toast align-items-center text-white border-0 show ' + bgClass)
            .attr('role', 'alert')
            .css({
                position: 'fixed',
                top:      '20px',
                right:    '20px',
                zIndex:   9999,
                minWidth: '260px',
            });

        const $body = $('<div>').addClass('d-flex');
        $('<div>').addClass('toast-body').text(message).appendTo($body);
        $('<button>').attr('type', 'button')
            .addClass('btn-close btn-close-white me-2 m-auto')
            .attr('aria-label', 'Close')
            .on('click', function() { $toast.remove(); })
            .appendTo($body);

        $toast.append($body);
        $('body').append($toast);

        setTimeout(function() {
            $toast.fadeOut(300, function() { $(this).remove(); });
        }, 4000);
    }

    /**
     * Show a Bootstrap 5 modal by ID.
     *
     * @param {string} modalId  Modal element ID (without #)
     */
    function showModal(modalId) {
        const el = document.getElementById(modalId);
        if (!el) return;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
    }

    /**
     * Hide a Bootstrap 5 modal by ID.
     *
     * @param {string} modalId  Modal element ID (without #)
     */
    function hideModal(modalId) {
        const el = document.getElementById(modalId);
        if (!el) return;
        const modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
    }

    /**
     * Escape a string for safe use as an HTML attribute value.
     *
     * @param {*} val
     * @returns {string}
     */
    function escAttr(val) {
        return String(val == null ? '' : val)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /**
     * Escape a string for safe use as HTML text content.
     *
     * @param {*} val
     * @returns {string}
     */
    function escHtml(val) {
        return String(val == null ? '' : val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

})(jQuery);
