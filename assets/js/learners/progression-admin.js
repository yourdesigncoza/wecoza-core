/**
 * Progression Admin JavaScript Module
 *
 * Wires all admin management interactions for the learner progression
 * admin page: table loading with filters, pagination, bulk complete,
 * hours log modal, start new LP modal, and hold/resume toggle.
 *
 * @package WeCoza_Learners
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const config = window.progressionAdminAjax || {};
    let currentPage    = 1;
    let currentFilters = {};
    const selectedIds  = new Set();

    // Cache of full dataset from first load used to populate filter dropdowns
    let filterOptionsCache = {
        clients:  [],
        classes:  [],
        subjects: [],
        learners: [],
    };

    // =========================================================
    // DOM READY
    // =========================================================

    $(document).ready(function() {
        loadProgressions();
        bindEvents();
    });

    // =========================================================
    // SECTION 1: TABLE LOADING
    // =========================================================

    /**
     * Fetch progressions from the server and render the table + pagination.
     */
    function loadProgressions() {
        showLoading();

        $.ajax({
            url:      config.ajaxurl,
            type:     'GET',
            data:     $.extend({}, currentFilters, {
                action: 'get_admin_progressions',
                nonce:  config.nonce,
                page:   currentPage,
            }),
            success: function(response) {
                if (response.success && response.data) {
                    const meta = response.data;
                    renderTable(meta.data || []);
                    renderPagination(meta);

                    // Populate filter dropdowns from first full load
                    if (currentPage === 1 && !currentFilters.client_id &&
                        !currentFilters.class_id && !currentFilters.class_type_subject_id &&
                        !currentFilters.status) {
                        buildFilterOptionsFromData(meta.data || []);
                        populateFilterDropdowns();
                    }
                } else {
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to load progressions.';
                    showContainerAlert('#progression-admin-alert', msg, 'danger');
                }
            },
            error: function() {
                showContainerAlert('#progression-admin-alert', 'Server error. Please try again.', 'danger');
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    /**
     * Show loading spinner, hide content area.
     */
    function showLoading() {
        $('#progression-admin-loading').removeClass('d-none');
        $('#progression-admin-content').addClass('d-none');
    }

    /**
     * Hide loading spinner, reveal content area.
     */
    function hideLoading() {
        $('#progression-admin-loading').addClass('d-none');
        $('#progression-admin-content').removeClass('d-none');
    }

    /**
     * Render table rows from server data.
     * Uses jQuery DOM construction — no innerHTML for XSS safety.
     *
     * @param {Array} rows
     */
    function renderTable(rows) {
        const $tbody = $('#progression-admin-tbody');
        $tbody.empty();

        if (!rows || rows.length === 0) {
            const $empty = $('<tr>').append(
                $('<td>').attr('colspan', 8)
                    .addClass('text-center text-muted py-4')
                    .text('No progressions found.')
            );
            $tbody.append($empty);
            updateBulkBar();
            return;
        }

        rows.forEach(function(row) {
            const $tr = $('<tr>').attr('data-tracking-id', row.tracking_id);

            // Col 1: Checkbox (skip for completed)
            const $checkCell = $('<td>').addClass('ps-3');
            if (row.status !== 'completed') {
                const $cb = $('<input>')
                    .attr('type', 'checkbox')
                    .addClass('form-check-input row-checkbox')
                    .val(row.tracking_id);
                if (selectedIds.has(String(row.tracking_id))) {
                    $cb.prop('checked', true);
                }
                $checkCell.append($cb);
            }
            $tr.append($checkCell);

            // Col 2: Learner name
            $tr.append($('<td>').text(row.learner_name || ''));

            // Col 3: LP name
            $tr.append($('<td>').text(row.subject_name || ''));

            // Col 4: Class code
            $tr.append($('<td>').text(row.class_code || '\u2014'));

            // Col 5: Status badge
            const $statusTd = $('<td>');
            const $badge = $('<span>')
                .addClass('badge fs-10 badge-phoenix ' + statusBadgeClass(row.status))
                .text(statusLabel(row.status));
            $statusTd.append($badge);
            $tr.append($statusTd);

            // Col 6: Progress bar
            const duration   = parseFloat(row.subject_duration) || 0;
            const trained    = parseFloat(row.hours_trained) || 0;
            const pct        = duration > 0 ? Math.min(100, Math.round((trained / duration) * 100)) : 0;
            const $progressTd = $('<td>').css('min-width', '120px');
            const $outer = $('<div>').addClass('progress progress-sm').css({ height: '8px', width: '80px', display: 'inline-block' });
            const $bar   = $('<div>').addClass('progress-bar bg-primary').css('width', pct + '%')
                .attr('role', 'progressbar')
                .attr('aria-valuenow', pct)
                .attr('aria-valuemin', 0)
                .attr('aria-valuemax', 100);
            $outer.append($bar);
            $progressTd.append($outer);
            $progressTd.append($('<span>').addClass('ms-2 fs-9 text-muted').text(pct + '%'));
            $tr.append($progressTd);

            // Col 7: Start date
            $tr.append($('<td>').text(row.start_date || ''));

            // Col 8: Actions dropdown
            const $actionsTd = $('<td>').addClass('text-end pe-3');
            const $group     = $('<div>').addClass('btn-group');

            const $toggle = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-subtle-secondary btn-sm dropdown-toggle')
                .attr('data-bs-toggle', 'dropdown')
                .attr('aria-expanded', 'false')
                .html('<i class="bi bi-three-dots"></i>');

            const $menu = $('<ul>').addClass('dropdown-menu dropdown-menu-end');

            // Hours log
            const $logItem = $('<li>').append(
                $('<a>').addClass('dropdown-item btn-hours-log')
                    .attr('href', '#')
                    .attr('data-tracking-id', row.tracking_id)
                    .html('<i class="bi bi-clock-history me-2"></i>Hours Log')
            );
            $menu.append($logItem);

            // Hold / Resume (not for completed)
            if (row.status === 'in_progress') {
                const $holdItem = $('<li>').append(
                    $('<a>').addClass('dropdown-item btn-toggle-hold')
                        .attr('href', '#')
                        .attr('data-tracking-id', row.tracking_id)
                        .attr('data-action', 'hold')
                        .html('<i class="bi bi-pause-circle me-2"></i>Put on Hold')
                );
                $menu.append($holdItem);
            } else if (row.status === 'on_hold') {
                const $resumeItem = $('<li>').append(
                    $('<a>').addClass('dropdown-item btn-toggle-hold')
                        .attr('href', '#')
                        .attr('data-tracking-id', row.tracking_id)
                        .attr('data-action', 'resume')
                        .html('<i class="bi bi-play-circle me-2"></i>Resume')
                );
                $menu.append($resumeItem);
            }

            // Mark complete (not for completed)
            if (row.status !== 'completed') {
                const $completeItem = $('<li>').append(
                    $('<a>').addClass('dropdown-item btn-mark-single-complete')
                        .attr('href', '#')
                        .attr('data-tracking-id', row.tracking_id)
                        .html('<i class="bi bi-check-circle me-2"></i>Mark Complete')
                );
                $menu.append($completeItem);
            }

            $group.append($toggle).append($menu);
            $actionsTd.append($group);
            $tr.append($actionsTd);

            $tbody.append($tr);
        });

        updateBulkBar();
    }

    /**
     * Render pagination controls and info text.
     *
     * @param {Object} meta  Response object with total, page, page_size, pages fields
     */
    function renderPagination(meta) {
        const total     = parseInt(meta.total)        || 0;
        const page      = parseInt(meta.current_page) || currentPage;
        const pageSize  = parseInt(meta.page_size)    || 25;
        const pages     = parseInt(meta.pages)        || 1;
        const from      = total === 0 ? 0 : (page - 1) * pageSize + 1;
        const to        = Math.min(page * pageSize, total);

        $('#pagination-info').text('Showing ' + from + '\u2013' + to + ' of ' + total);

        const $ul = $('#pagination-controls').empty();

        if (pages <= 1) {
            return;
        }

        // Previous button
        const $prev = $('<li>').addClass('page-item' + (page <= 1 ? ' disabled' : ''));
        $('<a>').addClass('page-link').attr('href', '#').html('&laquo;')
            .on('click', function(e) {
                e.preventDefault();
                if (page > 1) {
                    currentPage = page - 1;
                    loadProgressions();
                }
            })
            .appendTo($prev);
        $ul.append($prev);

        // Page number buttons (max 5 visible, centred around current)
        const maxVisible = 5;
        let startPage = Math.max(1, page - Math.floor(maxVisible / 2));
        let endPage   = Math.min(pages, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        for (let p = startPage; p <= endPage; p++) {
            const $li = $('<li>').addClass('page-item' + (p === page ? ' active' : ''));
            const pageNum = p;
            $('<a>').addClass('page-link').attr('href', '#').text(p)
                .on('click', function(e) {
                    e.preventDefault();
                    currentPage = pageNum;
                    loadProgressions();
                })
                .appendTo($li);
            $ul.append($li);
        }

        // Next button
        const $next = $('<li>').addClass('page-item' + (page >= pages ? ' disabled' : ''));
        $('<a>').addClass('page-link').attr('href', '#').html('&raquo;')
            .on('click', function(e) {
                e.preventDefault();
                if (page < pages) {
                    currentPage = page + 1;
                    loadProgressions();
                }
            })
            .appendTo($next);
        $ul.append($next);
    }

    // =========================================================
    // SECTION 2: FILTER HANDLING
    // =========================================================

    /**
     * Extract unique filter option values from the first full data load.
     * Stored in filterOptionsCache for populating dropdowns once.
     *
     * NOTE: This approach populates from the current page of data.
     * For large datasets (>25 rows), a dedicated filter endpoint would
     * provide more comprehensive options.
     *
     * @param {Array} rows
     */
    function buildFilterOptionsFromData(rows) {
        const clients  = {};
        const classes  = {};
        const subjects = {};
        const learners = {};

        rows.forEach(function(row) {
            if (row.client_id && row.client_name && !clients[row.client_id]) {
                clients[row.client_id] = row.client_name;
            }
            if (row.class_id && row.class_code && !classes[row.class_id]) {
                classes[row.class_id] = row.class_code;
            }
            if (row.class_type_subject_id && row.subject_name && !subjects[row.class_type_subject_id]) {
                subjects[row.class_type_subject_id] = row.subject_name;
            }
            if (row.learner_id && row.learner_name && !learners[row.learner_id]) {
                learners[row.learner_id] = row.learner_name;
            }
        });

        filterOptionsCache.clients  = Object.entries(clients).map(function([id, name]) { return { id: id, name: name }; });
        filterOptionsCache.classes  = Object.entries(classes).map(function([id, name]) { return { id: id, name: name }; });
        filterOptionsCache.subjects = Object.entries(subjects).map(function([id, name]) { return { id: id, name: name }; });
        filterOptionsCache.learners = Object.entries(learners).map(function([id, name]) { return { id: id, name: name }; });
    }

    /**
     * Populate filter dropdowns from cached option data.
     */
    function populateFilterDropdowns() {
        populateSelect('#filter-client',  filterOptionsCache.clients,  'All Clients');
        populateSelect('#filter-class',   filterOptionsCache.classes,   'All Classes');
        populateSelect('#filter-subject', filterOptionsCache.subjects,  'All LPs');
    }

    /**
     * Build <option> elements inside a select from an array of {id, name} objects.
     *
     * @param {string} selector   CSS selector of the <select>
     * @param {Array}  items      Array of {id, name}
     * @param {string} placeholder Text for the blank "all" option
     */
    function populateSelect(selector, items, placeholder) {
        const $select = $(selector);
        const currentVal = $select.val();
        $select.empty().append(
            $('<option>').val('').text(placeholder)
        );
        items.sort(function(a, b) {
            return a.name.localeCompare(b.name);
        }).forEach(function(item) {
            $select.append(
                $('<option>').val(item.id).text(item.name)
            );
        });
        $select.val(currentVal);
    }

    /**
     * Populate Start LP modal selects (learners, subjects, classes).
     * Uses cache from first load; falls back to AJAX if cache is empty.
     */
    function populateStartLPModal() {
        // Learners select
        populateSelect('#start-lp-learner', filterOptionsCache.learners, 'Select Learner...');

        // Subjects select
        populateSelect('#start-lp-subject', filterOptionsCache.subjects, 'Select LP...');

        // Classes select
        populateSelect('#start-lp-class', filterOptionsCache.classes, 'No class');

        // If cache was empty, attempt AJAX for learners
        if (filterOptionsCache.learners.length === 0) {
            $.ajax({
                url:  config.ajaxurl,
                type: 'GET',
                data: {
                    action: 'get_admin_progressions',
                    nonce:  config.nonce,
                    page:   1,
                    per_page: 100,
                },
                success: function(response) {
                    if (response.success && response.data && response.data.data) {
                        buildFilterOptionsFromData(response.data.data);
                        populateSelect('#start-lp-learner', filterOptionsCache.learners, 'Select Learner...');
                        populateSelect('#start-lp-subject', filterOptionsCache.subjects, 'Select LP...');
                        populateSelect('#start-lp-class',   filterOptionsCache.classes,  'No class');
                    }
                }
            });
        }
    }

    /**
     * Handle filter form submission.
     *
     * @param {Event} e
     */
    function handleFilterSubmit(e) {
        e.preventDefault();
        currentFilters = {
            client_id:  $('#filter-client').val()  || '',
            class_id:   $('#filter-class').val()   || '',
            class_type_subject_id: $('#filter-subject').val() || '',
            status:     $('#filter-status').val()  || '',
        };
        // Strip empty keys to keep request clean
        Object.keys(currentFilters).forEach(function(k) {
            if (!currentFilters[k]) {
                delete currentFilters[k];
            }
        });
        currentPage = 1;
        selectedIds.clear();
        loadProgressions();
    }

    // =========================================================
    // SECTION 3: CHECKBOX + BULK BAR
    // =========================================================

    /**
     * Toggle all row checkboxes when select-all changes.
     */
    function handleSelectAll() {
        const checked = $(this).is(':checked');
        $('#progression-admin-tbody .row-checkbox').each(function() {
            $(this).prop('checked', checked);
            const id = String($(this).val());
            if (checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });
        updateBulkBar();
    }

    /**
     * Update bulk action bar visibility and selected count.
     */
    function updateBulkBar() {
        const pageChecked = $('#progression-admin-tbody .row-checkbox:checked').length;
        const pageTotal   = $('#progression-admin-tbody .row-checkbox').length;

        // Sync select-all checkbox with current page state
        if (pageTotal > 0) {
            $('#select-all-progressions').prop('indeterminate', pageChecked > 0 && pageChecked < pageTotal);
            $('#select-all-progressions').prop('checked', pageChecked === pageTotal && pageTotal > 0);
        } else {
            $('#select-all-progressions').prop('checked', false);
            $('#select-all-progressions').prop('indeterminate', false);
        }

        // Bar visibility based on total cross-page selection
        const totalSelected = selectedIds.size;
        if (totalSelected > 0) {
            $('#bulk-action-bar').removeClass('d-none');
            $('#selected-count').text(totalSelected);
        } else {
            $('#bulk-action-bar').addClass('d-none');
        }
    }

    /**
     * Collect checked tracking IDs from the table.
     *
     * @returns {Array<string>}
     */
    function getCheckedIds() {
        return Array.from(selectedIds);
    }

    // =========================================================
    // SECTION 4: BULK COMPLETE
    // =========================================================

    /**
     * Open the bulk complete confirmation modal.
     */
    function handleBulkCompleteClick() {
        const ids = getCheckedIds();
        if (ids.length === 0) {
            return;
        }
        $('#bulk-complete-count').text(ids.length);
        const modal = new bootstrap.Modal(document.getElementById('bulkCompleteModal'));
        modal.show();
    }

    /**
     * Execute bulk complete after user confirms.
     */
    function handleBulkCompleteConfirm() {
        const ids = getCheckedIds();
        if (ids.length === 0) {
            return;
        }

        const $btn = $('#btn-confirm-bulk-complete');
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span> Processing...'
        );

        $.ajax({
            url:  config.ajaxurl,
            type: 'POST',
            data: {
                action:       'bulk_complete_progressions',
                nonce:        config.nonce,
                tracking_ids: ids,
            },
            success: function(response) {
                const modalEl = document.getElementById('bulkCompleteModal');
                const modal   = bootstrap.Modal.getInstance(modalEl);
                if (modal) { modal.hide(); }

                if (response.success && response.data) {
                    const completed = response.data.completed || ids.length;
                    const failed    = response.data.failed    || 0;
                    const msg = completed + ' progression(s) completed.' +
                        (failed > 0 ? ' ' + failed + ' failed.' : '');
                    showToast(msg, 'success');
                } else {
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Bulk complete failed.';
                    showToast(msg, 'danger');
                }

                $btn.prop('disabled', false).html(
                    '<i class="bi bi-check2-all me-1"></i> Confirm Complete'
                );
                selectedIds.clear();
                loadProgressions();
            },
            error: function() {
                showAlert('#bulkCompleteModal .modal-body', 'Server error. Please try again.', 'danger');
                $btn.prop('disabled', false).html(
                    '<i class="bi bi-check2-all me-1"></i> Confirm Complete'
                );
            }
        });
    }

    // =========================================================
    // SECTION 5: HOURS LOG
    // =========================================================

    /**
     * Open hours log modal and load audit trail for a tracking ID.
     *
     * @param {Event} e
     */
    function handleHoursLogClick(e) {
        e.preventDefault();

        const trackingId = $(e.currentTarget).data('tracking-id');
        const $modalEl   = $('#hoursLogModal');

        // Show modal with loading state
        $('#hours-log-summary').html(
            '<div class="text-center py-2"><span class="spinner-border spinner-border-sm"></span> Loading...</div>'
        );
        $('#hours-log-tbody').empty();
        $('#hours-log-empty').addClass('d-none');
        $modalEl.find('table').show();

        const modal = new bootstrap.Modal($modalEl[0]);
        modal.show();

        $.ajax({
            url:  config.ajaxurl,
            type: 'GET',
            data: {
                action:      'get_progression_hours_log',
                nonce:       config.nonce,
                tracking_id: trackingId,
            },
            success: function(response) {
                if (!response.success || !response.data) {
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to load hours log.';
                    $('#hours-log-summary').html(
                        '<div class="alert alert-danger mb-0">' + $('<span>').text(msg).html() + '</div>'
                    );
                    return;
                }

                const data = response.data;
                renderHoursLogSummary(data);
                renderHoursLogTable(data.hours_log || []);
            },
            error: function() {
                $('#hours-log-summary').html(
                    '<div class="alert alert-danger mb-0">Server error. Please try again.</div>'
                );
            }
        });
    }

    /**
     * Fill the hours log modal summary section.
     *
     * @param {Object} data  Response data from get_progression_hours_log
     */
    function renderHoursLogSummary(data) {
        const $summary = $('#hours-log-summary').empty();

        const $row = $('<div>').addClass('d-flex align-items-center flex-wrap gap-2');

        $('<strong>').text(data.learner_name || '').appendTo($row);

        $('<span>').addClass('badge fs-10 badge-phoenix badge-phoenix-info ms-2')
            .text(data.subject_name || '').appendTo($row);

        $('<span>').addClass('badge fs-10 badge-phoenix ' + statusBadgeClass(data.status))
            .text(statusLabel(data.status)).appendTo($row);

        $('<span>').addClass('text-muted ms-2 fs-9')
            .text('Hours: ' + (data.hours_trained || 0) + ' / ' + (data.subject_duration || 0) + ' trained')
            .appendTo($row);

        $summary.append($row);
    }

    /**
     * Fill the hours log table.
     *
     * @param {Array} log  Array of log entry objects
     */
    function renderHoursLogTable(log) {
        const $tbody = $('#hours-log-tbody').empty();
        const $table = $tbody.closest('table');

        if (!log || log.length === 0) {
            $table.hide();
            $('#hours-log-empty').removeClass('d-none');
            return;
        }

        $table.show();
        $('#hours-log-empty').addClass('d-none');

        log.forEach(function(entry) {
            const notes     = (entry.notes || '').toString();
            const notesTrunc = notes.length > 50 ? notes.substring(0, 50) + '\u2026' : notes;
            const source    = entry.source || 'manual';

            const $tr = $('<tr>');
            $tr.append($('<td>').text(entry.log_date || ''));
            $tr.append($('<td>').text(entry.hours_trained || 0));
            $tr.append($('<td>').text(entry.hours_present || 0));

            const $sourceTd = $('<td>');
            $('<span>').addClass('badge fs-10 badge-phoenix badge-phoenix-secondary')
                .text(source).appendTo($sourceTd);
            $tr.append($sourceTd);

            const $notesTd = $('<td>');
            if (notes.length > 50) {
                $('<span>').text(notesTrunc).attr('title', notes).appendTo($notesTd);
            } else {
                $notesTd.text(notes);
            }
            $tr.append($notesTd);

            $tbody.append($tr);
        });
    }

    // =========================================================
    // SECTION 6: START NEW LP
    // =========================================================

    /**
     * Open the Start New LP modal and populate form dropdowns.
     */
    function handleStartNewLPClick() {
        clearAlert('#start-lp-alert');
        $('#start-lp-form')[0].reset();
        populateStartLPModal();
        const modal = new bootstrap.Modal(document.getElementById('startNewLPModal'));
        modal.show();
    }

    /**
     * Submit start LP form via AJAX.
     */
    function handleStartNewLPSubmit() {
        const learnerId = $('#start-lp-learner').val();
        const classTypeSubjectId = $('#start-lp-subject').val();

        if (!learnerId) {
            showAlert('#start-lp-alert', 'Please select a learner.', 'danger');
            return;
        }
        if (!classTypeSubjectId) {
            showAlert('#start-lp-alert', 'Please select a Learning Programme.', 'danger');
            return;
        }

        const $btn = $('#btn-submit-start-lp');
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span> Starting...'
        );
        clearAlert('#start-lp-alert');

        $.ajax({
            url:  config.ajaxurl,
            type: 'POST',
            data: {
                action:     'start_learner_progression',
                nonce:      config.nonce,
                learner_id: learnerId,
                class_type_subject_id: classTypeSubjectId,
                class_id:   $('#start-lp-class').val()  || '',
                notes:      $('#start-lp-notes').val()  || '',
            },
            success: function(response) {
                if (response.success) {
                    const modalEl = document.getElementById('startNewLPModal');
                    const modal   = bootstrap.Modal.getInstance(modalEl);
                    if (modal) { modal.hide(); }

                    showToast('Learning Programme started successfully.', 'success');
                    $('#start-lp-form')[0].reset();
                    loadProgressions();
                } else {
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to start Learning Programme.';
                    showAlert('#start-lp-alert', msg, 'danger');
                }
            },
            error: function() {
                showAlert('#start-lp-alert', 'Server error. Please try again.', 'danger');
            },
            complete: function() {
                $btn.prop('disabled', false).html(
                    '<i class="bi bi-play-circle me-1"></i> Start LP'
                );
            }
        });
    }

    // =========================================================
    // SECTION 7: HOLD / RESUME TOGGLE
    // =========================================================

    /**
     * Toggle hold or resume status for a progression row.
     *
     * @param {Event} e
     */
    function handleToggleHold(e) {
        e.preventDefault();

        const $link      = $(e.currentTarget);
        const trackingId = $link.data('tracking-id');
        const action     = $link.data('action'); // 'hold' or 'resume'

        $.ajax({
            url:  config.ajaxurl,
            type: 'POST',
            data: {
                action:      'toggle_progression_hold',
                nonce:       config.nonce,
                tracking_id: trackingId,
                toggle:      action,
            },
            success: function(response) {
                if (response.success) {
                    const newStatus = action === 'hold' ? 'on_hold' : 'in_progress';
                    updateRowStatus(trackingId, newStatus);
                    const msg = action === 'hold'
                        ? 'Progression put on hold.'
                        : 'Progression resumed.';
                    showToast(msg, 'success');
                } else {
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to update status.';
                    showToast(msg, 'danger');
                }
            },
            error: function() {
                showToast('Server error. Please try again.', 'danger');
            }
        });
    }

    /**
     * Update a table row's status badge and actions dropdown in place.
     *
     * @param {string} trackingId
     * @param {string} newStatus   'in_progress' or 'on_hold'
     */
    function updateRowStatus(trackingId, newStatus) {
        const $tr = $('#progression-admin-tbody tr[data-tracking-id="' + trackingId + '"]');
        if (!$tr.length) {
            return;
        }

        // Update badge
        $tr.find('.badge-phoenix').first()
            .removeClass('badge-phoenix-primary badge-phoenix-success badge-phoenix-warning')
            .addClass(statusBadgeClass(newStatus))
            .text(statusLabel(newStatus));

        // Update hold/resume dropdown item
        const $toggleLink = $tr.find('.btn-toggle-hold');
        if ($toggleLink.length) {
            if (newStatus === 'on_hold') {
                $toggleLink.attr('data-action', 'resume')
                    .html('<i class="bi bi-play-circle me-2"></i>Resume');
            } else {
                $toggleLink.attr('data-action', 'hold')
                    .html('<i class="bi bi-pause-circle me-2"></i>Put on Hold');
            }
        }
    }

    /**
     * Handle single-row mark complete from the actions dropdown.
     *
     * @param {Event} e
     */
    function handleMarkSingleComplete(e) {
        e.preventDefault();

        const trackingId = $(e.currentTarget).data('tracking-id');
        // Native confirm — simple and adequate for an admin action
        if (!confirm('Mark this progression as complete? (No portfolio required for admin single/bulk complete)')) {
            return;
        }

        $.ajax({
            url:  config.ajaxurl,
            type: 'POST',
            data: {
                action:       'bulk_complete_progressions',
                nonce:        config.nonce,
                tracking_ids: [trackingId],
            },
            success: function(response) {
                if (response.success) {
                    showToast('Progression marked as complete.', 'success');
                    loadProgressions();
                } else {
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Failed to mark progression as complete.';
                    showToast(msg, 'danger');
                }
            },
            error: function() {
                showToast('Server error. Please try again.', 'danger');
            }
        });
    }

    // =========================================================
    // EVENT BINDING
    // =========================================================

    /**
     * Bind all event listeners.
     */
    function bindEvents() {
        $('#progression-filter-form').on('submit', handleFilterSubmit);
        $('#select-all-progressions').on('change', handleSelectAll);
        $('#progression-admin-tbody').on('change', '.row-checkbox', function() {
            const id = String($(this).val());
            if ($(this).is(':checked')) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            updateBulkBar();
        });
        $('#btn-bulk-complete').on('click', handleBulkCompleteClick);
        $('#btn-confirm-bulk-complete').on('click', handleBulkCompleteConfirm);
        $('#progression-admin-tbody').on('click', '.btn-hours-log', handleHoursLogClick);
        $('#progression-admin-tbody').on('click', '.btn-toggle-hold', handleToggleHold);
        $('#progression-admin-tbody').on('click', '.btn-mark-single-complete', handleMarkSingleComplete);
        $('#btn-start-new-lp').on('click', handleStartNewLPClick);
        $('#btn-submit-start-lp').on('click', handleStartNewLPSubmit);
    }

    // =========================================================
    // UTILITY FUNCTIONS
    // =========================================================

    /**
     * Return the Phoenix badge CSS class for a given progression status.
     *
     * @param {string} status
     * @returns {string}
     */
    function statusBadgeClass(status) {
        const map = {
            in_progress: 'badge-phoenix-primary',
            completed:   'badge-phoenix-success',
            on_hold:     'badge-phoenix-warning',
        };
        return map[status] || 'badge-phoenix-secondary';
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
     * Show a Bootstrap alert inside a container element.
     * Uses jQuery DOM construction — no innerHTML for XSS safety.
     *
     * @param {string} containerSelector  CSS selector of container
     * @param {string} message            Alert body text
     * @param {string} type               Bootstrap type: success|danger|warning|info
     */
    function showAlert(containerSelector, message, type) {
        const $container = $(containerSelector);
        const iconClass  = type === 'success' ? 'check-circle' : 'exclamation-triangle';

        const $alert = $('<div>')
            .addClass('alert alert-' + type + ' alert-dismissible fade show mt-2')
            .attr('role', 'alert');

        $('<i>').addClass('bi bi-' + iconClass + ' me-2').appendTo($alert);
        $alert.append(document.createTextNode(message));

        $('<button>').attr('type', 'button')
            .addClass('btn-close')
            .attr('data-bs-dismiss', 'alert')
            .attr('aria-label', 'Close')
            .appendTo($alert);

        $container.empty().append($alert);
    }

    /**
     * Clear an alert container.
     *
     * @param {string} containerSelector
     */
    function clearAlert(containerSelector) {
        $(containerSelector).empty();
    }

    /**
     * Show an alert inside the progression-admin-alert container.
     *
     * @param {string} containerSelector
     * @param {string} message
     * @param {string} type
     */
    function showContainerAlert(containerSelector, message, type) {
        showAlert(containerSelector, message, type);
    }

    /**
     * Show a temporary fixed-position toast notification.
     * Auto-dismisses after 3 seconds.
     * Uses Phoenix badge colour conventions.
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
                position:  'fixed',
                top:       '20px',
                right:     '20px',
                zIndex:    9999,
                minWidth:  '260px',
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
        }, 3000);
    }

})(jQuery);
