/**
 * Progression Report JavaScript Module
 *
 * Wires all report interactivity for the learner progression report page:
 * AJAX fetch with search/employer/status filters, summary card population,
 * employer-grouped accordion rendering, learner timeline rows, and
 * client-side status pill filtering from cached data.
 *
 * @package WeCoza_Learners
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const config = window.progressionReportAjax || {};

    // Last server response cached for client-side status pill filtering
    let currentData = null;

    // Active status pill value ('' = all)
    let currentStatusFilter = '';

    // Guard: employer dropdown is populated only once from the initial load
    let employerDropdownPopulated = false;

    // =========================================================
    // DOM READY
    // =========================================================

    $(document).ready(function() {
        bindEvents();
        // Initial fetch populates summary cards, employer dropdown, and all results
        fetchReport();
    });

    // =========================================================
    // SECTION 1: AJAX FETCH
    // =========================================================

    /**
     * Fetch report data from the server using current filter values.
     * Caches response, updates summary cards, populates employer dropdown
     * on first call, and renders accordion results.
     */
    function fetchReport() {
        const search     = $('#report-search').val().trim();
        const employerId = $('#report-employer-filter').val();

        showLoading();

        $.ajax({
            url:  config.ajaxurl,
            type: 'GET',
            data: {
                action:      'get_progression_report',
                nonce:       config.nonce,
                search:      search      || '',
                employer_id: employerId  || '',
                status:      currentStatusFilter || '',
            },
            success: function(response) {
                if (response.success && response.data) {
                    currentData = response.data;

                    updateSummaryCards(response.data.summary);

                    // Populate employer dropdown only once on initial full load
                    if (!employerDropdownPopulated) {
                        populateEmployerDropdown(response.data.groups);
                        employerDropdownPopulated = true;
                    }

                    renderResults();
                    hideLoading();
                } else {
                    hideLoading();
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to load report data.';
                    showToast(msg, 'danger');
                }
            },
            error: function() {
                hideLoading();
                showToast('Failed to load report data', 'danger');
            }
        });
    }

    /**
     * Show loading spinner, hide report content.
     */
    function showLoading() {
        $('#report-loading').removeClass('d-none');
        $('#report-content').addClass('d-none');
    }

    /**
     * Hide loading spinner, reveal report content.
     */
    function hideLoading() {
        $('#report-loading').addClass('d-none');
        $('#report-content').removeClass('d-none');
    }

    // =========================================================
    // SECTION 2: SUMMARY CARDS
    // =========================================================

    /**
     * Populate the four summary stat cards with server response values.
     *
     * @param {Object} summary  summary object from get_progression_report response
     */
    function updateSummaryCards(summary) {
        if (!summary) {
            return;
        }
        $('#stat-total-learners').text(summary.total_learners  || 0);
        $('#stat-completion-rate').text(Math.round(summary.completion_rate || 0));
        $('#stat-avg-progress').text(Math.round(summary.avg_progress    || 0));
        $('#stat-active-lps').text(summary.in_progress_count || 0);

        $('#stat-completion-badge').text('of all progressions');
        $('#stat-progress-badge').text('hours completed');
    }

    // =========================================================
    // SECTION 3: EMPLOYER DROPDOWN
    // =========================================================

    /**
     * Populate the employer filter dropdown from the groups array.
     * Extracts unique employer_id / employer_name pairs, sorts alphabetically,
     * and appends options to #report-employer-filter.
     * Skips if already populated.
     *
     * @param {Array} groups
     */
    function populateEmployerDropdown(groups) {
        const $select = $('#report-employer-filter');

        // Guard: skip if already has options beyond the default "All Employers"
        if ($select.find('option').length > 1) {
            return;
        }

        if (!groups || groups.length === 0) {
            return;
        }

        // Collect unique employers
        const seen      = {};
        const employers = [];

        groups.forEach(function(group) {
            if (group.employer_id && group.employer_name && !seen[group.employer_id]) {
                seen[group.employer_id] = true;
                employers.push({ id: group.employer_id, name: group.employer_name });
            }
        });

        // Sort alphabetically by name
        employers.sort(function(a, b) {
            return a.name.localeCompare(b.name);
        });

        employers.forEach(function(emp) {
            $select.append(
                $('<option>').val(emp.id).text(emp.name)
            );
        });
    }

    // =========================================================
    // SECTION 4: RENDER RESULTS
    // =========================================================

    /**
     * Render employer-grouped accordion from cached data.
     * Applies client-side status filter if currentStatusFilter is set.
     */
    function renderResults() {
        if (!currentData || !currentData.groups) {
            $('#report-results').empty();
            $('#report-empty').removeClass('d-none');
            return;
        }

        // Deep-filter by status if a pill is active
        let groups = currentData.groups;

        if (currentStatusFilter) {
            groups = groups.map(function(group) {
                const filteredLearners = (group.learners || []).map(function(learner) {
                    const filteredProgressions = (learner.progressions || []).filter(function(prog) {
                        return prog.status === currentStatusFilter;
                    });
                    return $.extend({}, learner, { progressions: filteredProgressions });
                }).filter(function(learner) {
                    return learner.progressions.length > 0;
                });

                return $.extend({}, group, { learners: filteredLearners });
            }).filter(function(group) {
                return group.learners.length > 0;
            });
        }

        const $results = $('#report-results').empty();

        if (!groups || groups.length === 0) {
            $('#report-empty').removeClass('d-none');
            return;
        }

        $('#report-empty').addClass('d-none');

        groups.forEach(function(group) {
            const learnerCount = (group.learners || []).length;
            const groupId      = 'employer-' + (group.employer_id || 'unknown');

            // Group header row (flat section, not a card)
            const $groupHeader = $('<div>')
                .addClass('d-flex justify-content-between align-items-center p-2 px-3 bg-body-tertiary border-bottom cursor-pointer')
                .attr('data-bs-toggle', 'collapse')
                .attr('data-bs-target', '#' + groupId);

            $('<h6>').addClass('mb-0 fs-9')
                .append($('<i>').addClass('bi bi-building me-2'))
                .append(document.createTextNode(group.employer_name || 'Unknown Employer'))
                .appendTo($groupHeader);

            const $right = $('<span>').addClass('d-flex align-items-center gap-2');
            $('<span>').addClass('badge badge-phoenix badge-phoenix-secondary fs-10')
                .text(learnerCount + ' learner(s)')
                .appendTo($right);
            $('<i>').addClass('bi bi-chevron-down fs-9 transition-transform')
                .appendTo($right);
            $right.appendTo($groupHeader);

            // Collapsible learner rows — collapsed by default
            const $collapseDiv = $('<div>').attr('id', groupId).addClass('collapse');

            (group.learners || []).forEach(function(learner) {
                $collapseDiv.append(renderLearnerRow(learner));
            });

            $results.append($groupHeader).append($collapseDiv);
        });
    }

    /**
     * Build a single learner row with a collapsible timeline panel.
     *
     * @param {Object} learner
     * @returns {jQuery}
     */
    function renderLearnerRow(learner) {
        const learnerId       = learner.learner_id || 0;
        const learnerName     = learner.learner_name || 'Unknown';
        const progressionCount = (learner.progressions || []).length;
        const timelineId      = 'learner-timeline-' + learnerId;

        const $wrapper = $('<div>').addClass('border-bottom');

        // Toggle row
        const $toggleRow = $('<div>')
            .addClass('p-2 px-3 d-flex justify-content-between align-items-center cursor-pointer')
            .attr('data-bs-toggle', 'collapse')
            .attr('data-bs-target', '#' + timelineId);

        const $left = $('<span>').addClass('d-flex align-items-center gap-2');
        $('<i>').addClass('bi bi-chevron-right fs-10 text-muted transition-transform').appendTo($left);
        $('<span>').addClass('fw-semibold').text(learnerName).appendTo($left);
        $left.appendTo($toggleRow);

        $('<span>').addClass('text-muted fs-9')
            .html('ID: ' + learnerId + ' &middot; ' + progressionCount + ' LP(s)')
            .appendTo($toggleRow);

        // Collapsible timeline panel
        const $collapse = $('<div>').attr('id', timelineId).addClass('collapse');
        const $panel    = $('<div>').addClass('px-3 pb-3');

        $panel.append(renderTimeline(learner.progressions || []));
        $collapse.append($panel);

        $wrapper.append($toggleRow).append($collapse);

        return $wrapper;
    }

    /**
     * Build a Phoenix-style timeline from an array of progression objects.
     * Sorted chronologically descending (most recent first).
     *
     * @param {Array} progressions
     * @returns {jQuery}
     */
    function renderTimeline(progressions) {
        const $timeline = $('<div>').addClass('timeline-basic');

        if (!progressions || progressions.length === 0) {
            $('<p>').addClass('text-muted fs-9 mt-2 mb-0').text('No progressions found.')
                .appendTo($timeline);
            return $timeline;
        }

        // Sort descending by start_date
        const sorted = progressions.slice().sort(function(a, b) {
            return (b.start_date || '').localeCompare(a.start_date || '');
        });

        sorted.forEach(function(prog) {
            const status      = prog.status || 'in_progress';
            const duration    = parseFloat(prog.subject_duration) || 0;
            const hoursTrained = parseFloat(prog.hours_trained)    || 0;
            const progressPct = duration > 0
                ? Math.min(100, Math.round((hoursTrained / duration) * 100))
                : 0;

            const dateRange = prog.start_date
                ? prog.start_date + (prog.completion_date ? ' - ' + prog.completion_date : ' - present')
                : 'No date';

            const $item = $('<div>').addClass('timeline-item');

            const $row    = $('<div>').addClass('row g-2');
            const $colBar = $('<div>').addClass('col-auto');
            const $bar    = $('<div>').addClass('timeline-item-bar position-relative');

            $('<div>').addClass('icon-item icon-item-sm rounded-7 border border-translucent')
                .append(
                    $('<span>').addClass('bi ' + statusIcon(status) + ' ' + statusColor(status) + ' fs-9')
                )
                .appendTo($bar);

            $('<span>').addClass('timeline-bar border-end border-dashed').appendTo($bar);
            $colBar.append($bar);

            // Content column
            const $colContent = $('<div>').addClass('col');

            const $titleRow = $('<div>').addClass('d-flex justify-content-between mb-1');
            $('<h6>').addClass('fs-9 mb-0').text(prog.subject_name || 'Unknown LP').appendTo($titleRow);
            $('<span>').addClass('badge badge-phoenix badge-phoenix-' + statusBadgeClass(status) + ' fs-10')
                .text(statusLabel(status)).appendTo($titleRow);

            const $meta = $('<div>').addClass('fs-10 text-muted');
            $meta.append($('<span>').text('Class: ' + (prog.class_code || 'N/A')));
            $meta.append($('<span>').addClass('mx-1').html('&middot;'));
            $meta.append($('<span>').text(dateRange));
            $meta.append($('<span>').addClass('mx-1').html('&middot;'));
            $meta.append($('<span>').text('Hours: ' + hoursTrained + '/' + (prog.subject_duration || '?')));

            $colContent.append($titleRow).append($meta);

            // Progress bar (not shown for completed LPs)
            if (status !== 'completed') {
                const $progressOuter = $('<div>').addClass('progress mt-1').css('height', '4px');
                $('<div>').addClass('progress-bar bg-primary')
                    .attr('role', 'progressbar')
                    .attr('aria-valuenow', progressPct)
                    .attr('aria-valuemin', 0)
                    .attr('aria-valuemax', 100)
                    .css('width', progressPct + '%')
                    .appendTo($progressOuter);
                $colContent.append($progressOuter);
            }

            $row.append($colBar).append($colContent);
            $item.append($row);
            $timeline.append($item);
        });

        return $timeline;
    }

    // =========================================================
    // SECTION 5: EVENT BINDING
    // =========================================================

    /**
     * Bind all event listeners.
     */
    function bindEvents() {
        // Enter key in search field
        $('#report-search').on('keypress', function(e) {
            if (e.which === 13) {
                fetchReport();
            }
        });

        // Employer dropdown change triggers fetch
        $('#report-employer-filter').on('change', function() {
            fetchReport();
        });

        // Status pill click — client-side filter from cache, no server round-trip
        $('#report-status-pills').on('click', 'button', function() {
            currentStatusFilter = $(this).data('status') || '';

            // Toggle active state on pills
            $('#report-status-pills button').removeClass('active');
            $(this).addClass('active');

            // Re-render from cached data
            if (currentData) {
                renderResults();
            }
        });
    }

    // =========================================================
    // UTILITY FUNCTIONS
    // =========================================================

    /**
     * Return the Phoenix badge CSS class suffix for a progression status.
     *
     * @param {string} status
     * @returns {string}
     */
    function statusBadgeClass(status) {
        const map = {
            in_progress: 'primary',
            completed:   'success',
            on_hold:     'warning',
        };
        return map[status] || 'secondary';
    }

    /**
     * Return the human-readable label for a progression status.
     *
     * @param {string} status
     * @returns {string}
     */
    function statusLabel(status) {
        const map = {
            in_progress: 'In Progress',
            completed:   'Completed',
            on_hold:     'On Hold',
        };
        return map[status] || status;
    }

    /**
     * Return the Bootstrap Icons class for a progression status icon.
     *
     * @param {string} status
     * @returns {string}
     */
    function statusIcon(status) {
        const map = {
            in_progress: 'bi-play-circle',
            completed:   'bi-check-circle',
            on_hold:     'bi-pause-circle',
        };
        return map[status] || 'bi-circle';
    }

    /**
     * Return the text colour class for a progression status icon.
     *
     * @param {string} status
     * @returns {string}
     */
    function statusColor(status) {
        const map = {
            in_progress: 'text-primary',
            completed:   'text-success',
            on_hold:     'text-warning',
        };
        return map[status] || 'text-muted';
    }

    /**
     * Show a temporary fixed-position toast notification.
     * Auto-dismisses after 4 seconds.
     *
     * @param {string} message  Notification text
     * @param {string} type     success|danger|warning|info
     */
    function showToast(message, type) {
        const bgClass = type === 'success' ? 'bg-success'
            : type === 'danger'  ? 'bg-danger'
            : type === 'warning' ? 'bg-warning'
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

})(jQuery);
