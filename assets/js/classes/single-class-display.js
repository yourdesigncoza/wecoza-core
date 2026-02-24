/**
 * Single Class Display JavaScript
 *
 * Handles all interactive functionality for the single class display view.
 * Data is passed from PHP via wp_localize_script as window.WeCozaSingleClass
 *
 * @package WeCozaClasses
 */

(function($, window) {
    'use strict';

    // Get localized data from PHP
    const config = window.WeCozaSingleClass || {};

    /**
     * Main Application Object
     */
    const SingleClassApp = {
        /**
         * Initialize the application
         */
        init: function() {
            console.log('SingleClassApp initializing...', config);

            // Hide loading indicator and show content after a brief delay
            if (config.showLoading) {
                setTimeout(function() {
                    const loading = document.getElementById('single-class-loading');
                    const content = document.getElementById('single-class-content');

                    if (loading) loading.classList.add('d-none');
                    if (content) content.classList.remove('d-none');
                }, 500);
            }

            // Initialize FullCalendar if the calendar container exists
            if (document.getElementById('classCalendar')) {
                console.log('Calendar container found, initializing...');
                this.initializeClassCalendar();
            } else {
                console.log('Calendar container not found');
            }

            // Initialize view toggle functionality
            this.initializeViewToggle();

            // Initialize month breakdown toggle functionality
            this.initializeMonthBreakdowns();

            // Initialize notes filtering when models are ready
            this.initializeNotesWhenReady();

            // Initialize class status action handlers (activate, stop, reactivate, history)
            this.initializeStatusActions();
        },

        /**
         * Initialize FullCalendar for the class schedule
         */
        initializeClassCalendar: function() {
            console.log('initializeClassCalendar called');
            console.log('FullCalendar available:', typeof FullCalendar !== 'undefined');
            console.log('WeCozaCalendar available:', typeof window.WeCozaCalendar !== 'undefined');

            // Pass class data to the calendar
            const classData = {
                id: config.classId,
                code: config.classCode,
                subject: config.classSubject,
                startDate: config.startDate,
                deliveryDate: config.deliveryDate,
                duration: config.duration,
                scheduleData: config.scheduleData,
                ajaxUrl: config.ajaxUrl,
                nonce: config.calendarNonce
            };

            console.log('Class data:', classData);

            // Initialize the calendar with the class data
            if (typeof window.WeCozaCalendar !== 'undefined') {
                console.log('Initializing WeCoza Calendar...');
                window.WeCozaCalendar.init(classData);
            } else {
                console.warn('WeCoza Calendar library not loaded');
                this.showCalendarError('Calendar library not available');
            }
        },

        /**
         * Show calendar error message
         */
        showCalendarError: function(message) {
            const loadingEl = document.getElementById('calendar-loading');
            const errorEl = document.getElementById('calendar-error');
            const messageEl = document.getElementById('calendar-error-message');

            if (loadingEl) loadingEl.style.display = 'none';
            if (errorEl) {
                errorEl.classList.remove('d-none');
                if (messageEl) messageEl.textContent = message;
            }
        },

        /**
         * Initialize view toggle functionality
         * Handles switching between calendar and list views with state persistence
         */
        initializeViewToggle: function() {
            console.log('Initializing view toggle functionality...');

            const calendarTab = document.getElementById('calendar-view-tab');
            const listTab = document.getElementById('list-view-tab');

            if (!calendarTab || !listTab) {
                console.warn('View toggle tabs not found');
                return;
            }

            // Load saved view preference from localStorage
            const savedView = localStorage.getItem('wecoza_schedule_view_preference');
            if (savedView === 'list') {
                setTimeout(() => {
                    listTab.click();
                }, 100);
            }

            // Add event listeners for tab switching
            const self = this;
            calendarTab.addEventListener('shown.bs.tab', function(e) {
                console.log('Switched to calendar view');
                localStorage.setItem('wecoza_schedule_view_preference', 'calendar');

                if (typeof window.WeCozaCalendar !== 'undefined' && window.WeCozaCalendar.refreshEvents) {
                    window.WeCozaCalendar.refreshEvents();
                }
            });

            listTab.addEventListener('shown.bs.tab', function(e) {
                console.log('Switched to list view');
                localStorage.setItem('wecoza_schedule_view_preference', 'list');
                self.loadListViewData();
            });
        },

        /**
         * Load and display data for list view
         */
        loadListViewData: function() {
            console.log('Loading list view data...');

            const listContainer = document.getElementById('classScheduleList');
            const listLoading = document.getElementById('list-loading');
            const listError = document.getElementById('list-error');
            const listEmpty = document.getElementById('list-empty');

            if (!listContainer) {
                console.error('List container not found');
                return;
            }

            // Show loading state
            listLoading.classList.remove('d-none');
            listError.classList.add('d-none');
            listEmpty.classList.add('d-none');
            listContainer.innerHTML = '';

            const classData = {
                id: config.classId,
                code: config.classCode,
                subject: config.classSubject,
                ajaxUrl: config.ajaxUrl,
                nonce: config.calendarNonce
            };

            const self = this;

            // Fetch calendar events data
            Promise.all([
                fetch(classData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_calendar_events',
                        class_id: classData.id,
                        nonce: classData.nonce
                    })
                }),
                fetch(classData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_public_holidays',
                        year: new Date().getFullYear(),
                        nonce: classData.nonce
                    })
                })
            ])
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(([classEvents, holidays]) => {
                const allEvents = [...(classEvents || []), ...(holidays || [])];

                listLoading.classList.add('d-none');

                if (allEvents.length === 0) {
                    listEmpty.classList.remove('d-none');
                } else {
                    window.currentListViewEvents = allEvents;
                    self.renderListView(allEvents, classData);
                    self.initializeListViewFilters();
                }
            })
            .catch(error => {
                console.error('Error loading list view data:', error);
                listLoading.classList.add('d-none');
                listError.classList.remove('d-none');

                const errorMessage = document.getElementById('list-error-message');
                if (errorMessage) {
                    errorMessage.textContent = 'Failed to load schedule data: ' + error.message;
                }
            });
        },

        /**
         * Render list view with event data
         */
        renderListView: function(events, classData) {
            console.log('Rendering list view with', events.length, 'events');

            const listContainer = document.getElementById('classScheduleList');
            if (!listContainer) return;

            // Sort events chronologically
            const sortedEvents = events.sort((a, b) => {
                const dateA = new Date(a.start || a.date);
                const dateB = new Date(b.start || b.date);
                return dateA - dateB;
            });

            // Group events by type
            const groupedEvents = {
                class_session: [],
                public_holiday: [],
                exception: [],
                stop_date: [],
                restart_date: [],
                stop_period: []
            };

            sortedEvents.forEach(event => {
                const eventType = event.extendedProps?.type || 'class_session';
                if (groupedEvents[eventType]) {
                    groupedEvents[eventType].push(event);
                } else {
                    groupedEvents.class_session.push(event);
                }
            });

            // Create list view HTML
            let listHTML = '<div class="row g-3">';
            const self = this;

            Object.keys(groupedEvents).forEach(eventType => {
                const events = groupedEvents[eventType];
                if (events.length === 0) return;

                const groupInfo = self.getEventGroupInfo(eventType);

                listHTML += `
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light border-0 py-2">
                                <h6 class="mb-0 d-flex align-items-center">
                                    <i class="${groupInfo.icon} me-2 text-${groupInfo.color}"></i>
                                    ${groupInfo.title}
                                    <span class="badge bg-${groupInfo.color} ms-2">${events.length}</span>
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="border-0 ps-3">Date & Time</th>
                                                <th class="border-0">Details</th>
                                                <th class="border-0 text-end pe-3">Duration</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;

                events.forEach(event => {
                    listHTML += self.renderEventRow(event, eventType);
                });

                listHTML += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>`;
            });

            listHTML += '</div>';
            listContainer.innerHTML = listHTML;
        },

        /**
         * Get event group information for styling and display
         */
        getEventGroupInfo: function(eventType) {
            const groupInfo = {
                class_session: {
                    title: 'Class Sessions',
                    icon: 'bi-calendar-event',
                    color: 'primary'
                },
                public_holiday: {
                    title: 'Public Holidays',
                    icon: 'bi-calendar-x',
                    color: 'danger'
                },
                exception: {
                    title: 'Exception Dates',
                    icon: 'bi-exclamation-triangle',
                    color: 'warning'
                },
                stop_date: {
                    title: 'Stop Dates',
                    icon: 'bi-stop-circle',
                    color: 'danger'
                },
                restart_date: {
                    title: 'Restart Dates',
                    icon: 'bi-play-circle',
                    color: 'success'
                },
                stop_period: {
                    title: 'Stop Period Days',
                    icon: 'bi-pause-circle',
                    color: 'secondary'
                }
            };

            return groupInfo[eventType] || groupInfo.class_session;
        },

        /**
         * Render individual event row for list view
         */
        renderEventRow: function(event, eventType) {
            const startDate = new Date(event.start || event.date);
            const endDate = event.end ? new Date(event.end) : null;

            const dateStr = startDate.toLocaleDateString('en-ZA', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            const timeStr = event.allDay ? 'All Day' :
                startDate.toLocaleTimeString('en-ZA', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }) + (endDate ? ' - ' + endDate.toLocaleTimeString('en-ZA', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }) : '');

            const title = event.title || 'Untitled Event';
            const subject = event.extendedProps?.classSubject || '';
            const notes = event.extendedProps?.notes || '';
            const reason = event.extendedProps?.reason || '';

            let durationStr = '-';
            if (endDate && !event.allDay) {
                const durationMs = endDate - startDate;
                const hours = Math.floor(durationMs / (1000 * 60 * 60));
                const minutes = Math.floor((durationMs % (1000 * 60 * 60)) / (1000 * 60));
                durationStr = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
            }

            return `
                <tr>
                    <td class="align-middle ps-3">
                        <div class="d-flex flex-column">
                            <span class="fw-medium">${this.escapeHtml(dateStr)}</span>
                            <small class="text-muted">${this.escapeHtml(timeStr)}</small>
                        </div>
                    </td>
                    <td class="align-middle">
                        <div class="d-flex flex-column">
                            <span class="fw-medium">${this.escapeHtml(title)}</span>
                            ${subject ? `<small class="text-muted">${this.escapeHtml(subject)}</small>` : ''}
                            ${notes ? `<small class="text-body-secondary">${this.escapeHtml(notes)}</small>` : ''}
                            ${reason ? `<small class="text-warning">Reason: ${this.escapeHtml(reason)}</small>` : ''}
                        </div>
                    </td>
                    <td class="align-middle text-end pe-3">
                        <span class="badge bg-light text-dark">${this.escapeHtml(durationStr)}</span>
                    </td>
                </tr>`;
        },

        /**
         * Initialize list view filtering functionality
         */
        initializeListViewFilters: function() {
            console.log('Initializing list view filters...');

            const filtersContainer = document.getElementById('listViewFilters');
            const eventTypeFilter = document.getElementById('eventTypeFilter');
            const dateFromFilter = document.getElementById('dateFromFilter');
            const dateToFilter = document.getElementById('dateToFilter');
            const clearFiltersBtn = document.getElementById('clearFilters');

            if (!filtersContainer || !eventTypeFilter || !dateFromFilter || !dateToFilter || !clearFiltersBtn) {
                console.warn('Filter elements not found');
                return;
            }

            if (window.currentListViewEvents && window.currentListViewEvents.length > 0) {
                filtersContainer.classList.remove('d-none');
            }

            const self = this;
            eventTypeFilter.addEventListener('change', function() { self.applyListViewFilters(); });
            dateFromFilter.addEventListener('change', function() { self.applyListViewFilters(); });
            dateToFilter.addEventListener('change', function() { self.applyListViewFilters(); });
            clearFiltersBtn.addEventListener('click', function() { self.clearListViewFilters(); });
        },

        /**
         * Apply filters to list view
         */
        applyListViewFilters: function() {
            if (!window.currentListViewEvents) return;

            const eventTypeFilter = document.getElementById('eventTypeFilter').value;
            const dateFromFilter = document.getElementById('dateFromFilter').value;
            const dateToFilter = document.getElementById('dateToFilter').value;

            let filteredEvents = [...window.currentListViewEvents];

            if (eventTypeFilter) {
                filteredEvents = filteredEvents.filter(event => {
                    const eventType = event.extendedProps?.type || 'class_session';
                    return eventType === eventTypeFilter;
                });
            }

            if (dateFromFilter) {
                const fromDate = new Date(dateFromFilter);
                filteredEvents = filteredEvents.filter(event => {
                    const eventDate = new Date(event.start || event.date);
                    return eventDate >= fromDate;
                });
            }

            if (dateToFilter) {
                const toDate = new Date(dateToFilter);
                toDate.setHours(23, 59, 59, 999);
                filteredEvents = filteredEvents.filter(event => {
                    const eventDate = new Date(event.start || event.date);
                    return eventDate <= toDate;
                });
            }

            console.log(`Filtered ${filteredEvents.length} events from ${window.currentListViewEvents.length} total`);

            const listContainer = document.getElementById('classScheduleList');
            const listEmpty = document.getElementById('list-empty');

            if (filteredEvents.length === 0) {
                listContainer.innerHTML = '';
                listEmpty.classList.remove('d-none');

                const emptyTitle = listEmpty.querySelector('h5');
                const emptyText = listEmpty.querySelector('p');
                if (emptyTitle && emptyText) {
                    emptyTitle.textContent = 'No Events Match Your Filters';
                    emptyText.textContent = 'Try adjusting your filter criteria to see more events.';
                }
            } else {
                listEmpty.classList.add('d-none');
                this.renderListView(filteredEvents, {
                    id: config.classId,
                    code: config.classCode,
                    subject: config.classSubject
                });
            }
        },

        /**
         * Clear all list view filters
         */
        clearListViewFilters: function() {
            document.getElementById('eventTypeFilter').value = '';
            document.getElementById('dateFromFilter').value = '';
            document.getElementById('dateToFilter').value = '';

            const listEmpty = document.getElementById('list-empty');
            const emptyTitle = listEmpty.querySelector('h5');
            const emptyText = listEmpty.querySelector('p');
            if (emptyTitle && emptyText) {
                emptyTitle.textContent = 'No Schedule Events Found';
                emptyText.textContent = 'There are no scheduled events to display for this class.';
            }

            if (window.currentListViewEvents) {
                this.renderListView(window.currentListViewEvents, {
                    id: config.classId,
                    code: config.classCode,
                    subject: config.classSubject
                });
            }
        },

        /**
         * Initialize Month Breakdown Toggle Functionality
         */
        initializeMonthBreakdowns: function() {
            console.log('Initializing month breakdown functionality...');

            const breakdownToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');

            if (breakdownToggles.length === 0) {
                console.log('No month breakdown toggles found');
                return;
            }

            breakdownToggles.forEach(toggle => {
                const targetId = toggle.getAttribute('data-bs-target');
                const targetElement = document.querySelector(targetId);

                if (targetElement) {
                    targetElement.addEventListener('show.bs.collapse', function() {
                        console.log('Expanding breakdown for:', targetId);
                        toggle.setAttribute('aria-expanded', 'true');
                    });

                    targetElement.addEventListener('hide.bs.collapse', function() {
                        console.log('Collapsing breakdown for:', targetId);
                        toggle.setAttribute('aria-expanded', 'false');
                    });
                }

                toggle.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(0,0,0,0.05)';
                });

                toggle.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });

            console.log(`Initialized ${breakdownToggles.length} month breakdown toggles`);
        },

        /**
         * Initialize notes filtering when models are ready
         */
        initializeNotesWhenReady: function() {
            const self = this;

            function waitForModels() {
                if (typeof ClassNotesQAModels !== 'undefined') {
                    self.initializeNotesFiltering();
                } else {
                    setTimeout(waitForModels, 100);
                }
            }
            waitForModels();
        },

        /**
         * Initialize Notes Filtering and Sorting
         */
        initializeNotesFiltering: function() {
            // Initialize collection
            if (typeof ClassNotesQAModels !== 'undefined' && !window.classNotesCollection) {
                window.classNotesCollection = new ClassNotesQAModels.Collection(ClassNotesQAModels.Note);
            }

            // Populate collection with existing data
            if (config.notesData && window.classNotesCollection) {
                window.classNotesCollection.items = [];
                config.notesData.forEach(note => {
                    window.classNotesCollection.add(note);
                });
            }

            // Attach event handlers
            const self = this;
            if (window.classNotesCollection && $) {
                $('#notes-priority-filter').off('change.notesFilter').on('change.notesFilter', function() {
                    const priority = $(this).val();
                    window.classNotesCollection.setFilter('priority', priority);
                    self.refreshSingleClassNotesDisplay();
                });

                $('#notes-sort').off('change.notesSort').on('change.notesSort', function() {
                    const sortValue = $(this).val();
                    let field, order;

                    switch (sortValue) {
                        case 'newest':
                            field = 'created_at';
                            order = 'desc';
                            break;
                        case 'oldest':
                            field = 'created_at';
                            order = 'asc';
                            break;
                        default:
                            field = 'created_at';
                            order = 'desc';
                    }

                    window.classNotesCollection.setSort(field, order);
                    self.refreshSingleClassNotesDisplay();
                });

                $('#clear-notes-filters').off('click.notesFilter').on('click.notesFilter', function() {
                    $('#notes-priority-filter').val('');
                    $('#notes-sort').val('newest');

                    window.classNotesCollection.setFilter('priority', '');
                    window.classNotesCollection.setSort('created_at', 'desc');
                    self.refreshSingleClassNotesDisplay();
                });
            }
        },

        /**
         * Refresh notes display for single class view
         */
        refreshSingleClassNotesDisplay: function() {
            if (!window.classNotesCollection || !$) return;

            const filteredData = window.classNotesCollection.getFiltered();
            const totalCount = filteredData.length;
            const allNotesCount = window.classNotesCollection.items.length;

            $('#notes-count').text(`${totalCount} NOTE${totalCount !== 1 ? 'S' : ''}`);

            if (!window.originalNoteCards) {
                window.originalNoteCards = {};
                $('.note-card').each(function() {
                    const noteId = $(this).data('note-id');
                    if (noteId) {
                        window.originalNoteCards[noteId] = $(this).clone(true);
                    }
                });
            }

            const $notesGrid = $('.notes-grid');

            if (totalCount === 0 && allNotesCount > 0) {
                $('#notes-no-results').removeClass('d-none').show();
                $('#notes-empty').hide();
                $notesGrid.empty();
            } else if (totalCount === 0) {
                $('#notes-empty').removeClass('d-none').show();
                $('#notes-no-results').hide();
                $notesGrid.empty();
            } else {
                $('#notes-empty').hide();
                $('#notes-no-results').hide();

                $notesGrid.empty();

                filteredData.forEach(note => {
                    if (window.originalNoteCards[note.id]) {
                        $notesGrid.append(window.originalNoteCards[note.id].clone(true));
                    }
                });
            }
        },

        /**
         * Show Success Banner Function
         */
        showSuccessBanner: function(message) {
            const banner = document.createElement('div');
            banner.className = 'alert alert-subtle-success alert-dismissible fade show position-fixed';
            banner.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
            banner.innerHTML = `
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Success!</strong> ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            document.body.appendChild(banner);

            setTimeout(() => {
                if (banner.parentNode) {
                    banner.remove();
                }
            }, 5000);
        },

        /**
         * HTML escape utility
         */
        escapeHtml: function(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        },

        /**
         * Initialize class status action handlers (activate, stop, reactivate, history)
         *
         * Wires the status management UI from Plan 05 to the AJAX endpoints:
         * - wecoza_class_status_update  (activate, stop, reactivate)
         * - wecoza_class_status_history (lazy-loaded on collapse expand)
         *
         * Uses config (WeCozaSingleClass) for ajaxUrl, classNonce, classId.
         * showStatusToast defined locally — separate scope from attendance-capture.js.
         */
        initializeStatusActions: function() {
            if (!config.classId || !config.classNonce) return;

            // ---------------------------------------------------------------
            // Local toast utility — scoped here, independent of attendance-capture.js
            // ---------------------------------------------------------------
            function showStatusToast(message, type) {
                var bgClass = type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : 'bg-warning';
                var $toast = $('<div>').addClass('toast align-items-center text-white border-0 show ' + bgClass)
                    .css({ position: 'fixed', top: '20px', right: '20px', zIndex: 9999, minWidth: '260px' })
                    .html('<div class="d-flex"><div class="toast-body">' + $('<span>').text(message).html() + '</div>' +
                          '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>');
                $('body').append($toast);
                setTimeout(function() { $toast.fadeOut(400, function() { $(this).remove(); }); }, 4000);
            }

            // ---------------------------------------------------------------
            // Activate Class — collects order_nr, sends AJAX, reloads on success
            // ---------------------------------------------------------------
            $('#btn-confirm-activate').on('click', function() {
                var orderNr = $('#activate-order-nr').val().trim();
                if (!orderNr) {
                    showStatusToast('Please enter an order number.', 'danger');
                    return;
                }
                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

                $.post(config.ajaxUrl, {
                    action: 'wecoza_class_status_update',
                    nonce: config.classNonce,
                    class_id: config.classId,
                    new_status: 'active',
                    order_nr: orderNr
                }, function(response) {
                    if (response.success) {
                        showStatusToast('Class activated successfully.', 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showStatusToast((response.data && response.data.message) ? response.data.message : 'Activation failed.', 'danger');
                        $btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i>Activate');
                    }
                }).fail(function() {
                    showStatusToast('Network error. Please try again.', 'danger');
                    $btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i>Activate');
                });
            });

            // ---------------------------------------------------------------
            // Stop Class — collects reason (required) + notes (optional), reloads on success
            // ---------------------------------------------------------------
            $('#btn-confirm-stop').on('click', function() {
                var reason = $('#stop-reason').val();
                if (!reason) {
                    showStatusToast('Please select a stop reason.', 'danger');
                    return;
                }
                var notes = $('#stop-notes').val().trim();
                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

                $.post(config.ajaxUrl, {
                    action: 'wecoza_class_status_update',
                    nonce: config.classNonce,
                    class_id: config.classId,
                    new_status: 'stopped',
                    stop_reason: reason,
                    notes: notes
                }, function(response) {
                    if (response.success) {
                        showStatusToast('Class stopped.', 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showStatusToast((response.data && response.data.message) ? response.data.message : 'Stop failed.', 'danger');
                        $btn.prop('disabled', false).html('<i class="bi bi-stop-circle me-1"></i>Stop Class');
                    }
                }).fail(function() {
                    showStatusToast('Network error. Please try again.', 'danger');
                    $btn.prop('disabled', false).html('<i class="bi bi-stop-circle me-1"></i>Stop Class');
                });
            });

            // ---------------------------------------------------------------
            // Reactivate Class — native confirm dialog, no modal needed
            // ---------------------------------------------------------------
            $('#btn-reactivate-class').on('click', function() {
                if (!confirm('Are you sure you want to reactivate this class?')) return;
                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

                $.post(config.ajaxUrl, {
                    action: 'wecoza_class_status_update',
                    nonce: config.classNonce,
                    class_id: config.classId,
                    new_status: 'active'
                }, function(response) {
                    if (response.success) {
                        showStatusToast('Class reactivated.', 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showStatusToast((response.data && response.data.message) ? response.data.message : 'Reactivation failed.', 'danger');
                        $btn.prop('disabled', false).html('<i class="bi bi-arrow-counterclockwise me-1"></i>Reactivate Class');
                    }
                }).fail(function() {
                    showStatusToast('Network error. Please try again.', 'danger');
                    $btn.prop('disabled', false).html('<i class="bi bi-arrow-counterclockwise me-1"></i>Reactivate Class');
                });
            });

            // ---------------------------------------------------------------
            // Status History — lazy-loads on first Bootstrap collapse expand
            // ---------------------------------------------------------------
            var historyLoaded = false;
            $('#statusHistoryCollapse').on('show.bs.collapse', function() {
                if (historyLoaded) return;
                historyLoaded = true;

                $.post(config.ajaxUrl, {
                    action: 'wecoza_class_status_history',
                    nonce: config.classNonce,
                    class_id: config.classId
                }, function(response) {
                    if (response.success && response.data.history && response.data.history.length > 0) {
                        var html = '<table class="table table-sm fs-10"><thead><tr>' +
                            '<th>Date</th><th>From</th><th>To</th><th>Reason</th><th>Changed By</th><th>Notes</th></tr></thead><tbody>';
                        response.data.history.forEach(function(row) {
                            var oldBadge = row.old_status === 'active' ? 'success' : row.old_status === 'stopped' ? 'danger' : 'warning';
                            var newBadge = row.new_status === 'active' ? 'success' : row.new_status === 'stopped' ? 'danger' : 'warning';
                            html += '<tr>' +
                                '<td>' + $('<span>').text(row.changed_at).html() + '</td>' +
                                '<td><span class="badge badge-phoenix fs-10 badge-phoenix-' + oldBadge + '">' +
                                    $('<span>').text(row.old_status).html() + '</span></td>' +
                                '<td><span class="badge badge-phoenix fs-10 badge-phoenix-' + newBadge + '">' +
                                    $('<span>').text(row.new_status).html() + '</span></td>' +
                                '<td>' + $('<span>').text(row.reason || '-').html() + '</td>' +
                                '<td>' + $('<span>').text(row.changed_by_name || 'Unknown').html() + '</td>' +
                                '<td>' + $('<span>').text(row.notes || '-').html() + '</td>' +
                            '</tr>';
                        });
                        html += '</tbody></table>';
                        $('#status-history-content').html(html);
                    } else if (response.success) {
                        $('#status-history-content').html('<p class="text-body-tertiary mb-0">No status changes recorded.</p>');
                    } else {
                        $('#status-history-content').html('<p class="text-danger mb-0">Failed to load history.</p>');
                    }
                }).fail(function() {
                    $('#status-history-content').html('<p class="text-danger mb-0">Failed to load history.</p>');
                });
            });
        }
    };

    /**
     * Global Functions (exposed for onclick handlers in HTML)
     */
    window.backToClasses = function() {
        window.location.href = config.classesUrl;
    };

    window.editClass = function(classId) {
        if (!config.canEdit) {
            alert('You do not have permission to edit classes.');
            return;
        }
        window.location.href = config.editUrl;
    };

    window.deleteClass = function(classId) {
        if (!config.isAdmin) {
            alert('Only administrators can delete classes.');
            return;
        }

        if (confirm('Are you sure you want to delete this class? This action cannot be undone.')) {
            const deleteButton = document.querySelector(`[onclick="deleteClass(${classId})"]`);
            const originalText = deleteButton.innerHTML;
            deleteButton.innerHTML = '<i class="bi bi-spinner-border me-2"></i>Deleting...';
            deleteButton.disabled = true;

            fetch(config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'delete_class',
                    nonce: config.classNonce,
                    class_id: classId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const successUrl = new URL(config.classesUrl);
                    successUrl.searchParams.set('deleted', 'success');
                    successUrl.searchParams.set('class_subject', data.data.class_subject || 'Unknown Class');
                    successUrl.searchParams.set('class_code', data.data.class_code || '');
                    window.location.href = successUrl.toString();
                } else {
                    alert('Error deleting class: ' + (data.data || 'Unknown error'));
                    deleteButton.innerHTML = originalText;
                    deleteButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the class. Please try again.');
                deleteButton.innerHTML = originalText;
                deleteButton.disabled = false;
            });
        }
    };

    window.showSuccessBanner = function(message) {
        SingleClassApp.showSuccessBanner(message);
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        SingleClassApp.init();
    });

})(jQuery, window);
