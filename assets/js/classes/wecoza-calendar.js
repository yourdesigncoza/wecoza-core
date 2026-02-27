/**
 * WeCoza FullCalendar Integration for WeCoza Classes Plugin
 * 
 * Handles FullCalendar initialization and functionality for class schedules
 * Following WordPress best practices and coding standards
 * Extracted from WeCoza theme for standalone plugin
 * 
 * @package WeCozaClasses
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Global calendar instance
    let calendar = null;
    let classData = null;

    /**
     * Load FullCalendar from fallback CDN if not available
     */
    function loadFullCalendarFallback() {
        return new Promise((resolve, reject) => {
            if (typeof FullCalendar !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = window.wecozaCalendar?.fallbackCdn || 'https://unpkg.com/fullcalendar/index.global.min.js';
            script.onload = () => {
                resolve();
            };
            script.onerror = () => {
                reject(new Error('FullCalendar failed to load'));
            };
            document.head.appendChild(script);
        });
    }

    /**
     * WeCoza Calendar namespace
     */
    window.WeCozaCalendar = {

        /**
         * Initialize the calendar with class data
         * @param {Object} data - Class data from PHP
         */
        init: function(data) {
            classData = data;

            // Validate required data
            if (!data || !data.id) {
                this.showError('Invalid class data provided');
                return;
            }

            // Check if FullCalendar is loaded, try fallback if not
            if (typeof FullCalendar === 'undefined') {
                loadFullCalendarFallback()
                    .then(() => {
                        this.initializeCalendar();
                    })
                    .catch(() => {
                        this.showError('FullCalendar library not available');
                    });
            } else {
                this.initializeCalendar();
            }
        },

        /**
         * Initialize FullCalendar instance
         */
        initializeCalendar: function() {
            const calendarEl = document.getElementById('classCalendar');

            if (!calendarEl) {
                console.error('Calendar container not found');
                return;
            }

            // Debug: Log the class data being used
            console.log('Class data for calendar:', classData);

            // FullCalendar configuration
            const calendarConfig = {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                height: 'auto',
                aspectRatio: 1.8,

                // Event sources - using AJAX to load real events
                eventSources: [
                    // Class events
                    {
                        url: classData.ajaxUrl,
                        method: 'POST',
                        extraParams: {
                            action: 'get_calendar_events',
                            class_id: classData.id,
                            nonce: classData.nonce
                        },
                        failure: function() {
                            WeCozaCalendar.showError('Failed to load calendar events');
                        }
                    },
                    // Public holidays
                    {
                        url: classData.ajaxUrl,
                        method: 'POST',
                        extraParams: {
                            action: 'get_public_holidays',
                            year: new Date().getFullYear(),
                            nonce: classData.nonce
                        },
                        failure: function() {
                            console.warn('Failed to load public holidays');
                        }
                    }
                ],

                // Event rendering
                eventDisplay: 'block',

                // View change handler to update public holidays for the new year
                datesSet: function(info) {
                    WeCozaCalendar.updatePublicHolidaysForYear(info.start.getFullYear());
                },

                // Loading state
                loading: function(isLoading) {
                    WeCozaCalendar.toggleLoading(isLoading);
                },

                // Error handling
                eventSourceFailure: function() {
                    WeCozaCalendar.showError('Failed to load calendar events');
                }
            };

            // Initialize calendar
            try {
                calendar = new FullCalendar.Calendar(calendarEl, calendarConfig);
                calendar.render();

                // Hide loading state after successful render
                this.hideLoading();

            } catch (error) {
                this.showError('Failed to initialize calendar: ' + error.message);
            }
        },

        /**
         * Toggle loading state
         * @param {boolean} isLoading - Loading state
         */
        toggleLoading: function(isLoading) {
            if (isLoading) {
                this.showLoading();
            } else {
                this.hideLoading();
            }
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) {
                loadingEl.style.display = 'block';
            }
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
        },

        /**
         * Show error message
         * @param {string} message - Error message
         */
        showError: function(message) {
            console.error('WeCoza Calendar Error:', message);
            
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
         * Refresh calendar events
         */
        refreshEvents: function() {
            if (calendar) {
                calendar.refetchEvents();
            }
        },

        /**
         * Update public holidays for a specific year
         * @param {number} year - The year to load holidays for
         */
        updatePublicHolidaysForYear: function(year) {
            if (!calendar || !classData) {
                return;
            }

            // Find the public holidays event source (index 1)
            const eventSources = calendar.getEventSources();
            if (eventSources.length > 1) {
                const holidaysSource = eventSources[1];

                // Remove the old holidays source
                holidaysSource.remove();

                // Add a new holidays source with the updated year
                calendar.addEventSource({
                    url: classData.ajaxUrl,
                    method: 'POST',
                    extraParams: {
                        action: 'get_public_holidays',
                        year: year,
                        nonce: classData.nonce
                    },
                    failure: function() {
                        console.warn('Failed to load public holidays for year ' + year);
                    }
                });
            }
        },

        /**
         * Force calendar to recalculate dimensions
         */
        updateSize: function() {
            if (calendar) {
                calendar.updateSize();
            }
        },

        /**
         * Get calendar instance
         * @returns {Object|null} FullCalendar instance
         */
        getCalendar: function() {
            return calendar;
        }
    };

})(jQuery);
