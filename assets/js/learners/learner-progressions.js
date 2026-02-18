/**
 * Learner Progressions JavaScript Handler
 *
 * Handles mark complete flow, portfolio upload, and UI interactions
 * for the progressions tab in learner single display.
 *
 * Features:
 * - Confirmation modal before mark-complete (with LP details)
 * - Required portfolio upload with file validation
 * - Real-time upload progress bar
 * - In-place card updates on success (no page reload)
 * - Inline error alerts
 * - Skeleton loading on data refresh
 * - Auto-refresh of history timeline after completion
 * - Standalone portfolio upload (without mark-complete)
 *
 * @package WeCoza_Learners
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const learnerProgressions = {

        /**
         * Stores reference to the active mark-complete button
         * (set when modal opens so proceed button knows context)
         */
        activeMarkCompleteBtn: null,

        /**
         * Initialize the module
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            // Mark Complete button — open confirmation modal
            $(document).on('click', '.mark-complete-btn', this.openConfirmModal.bind(this));

            // Proceed to Upload button inside confirmation modal
            $(document).on('click', '#proceed-to-upload-btn', this.proceedToUpload.bind(this));

            // Cancel button in upload section (mark-complete flow)
            $(document).on('click', '.cancel-complete-btn', this.hideUploadSection.bind(this));

            // File input change — enable/disable confirm button
            $(document).on('change', '#portfolio_file', this.handleFileSelect.bind(this));

            // Form submission — mark LP complete
            $(document).on('submit', '#portfolio-upload-form', this.handleMarkComplete.bind(this));

            // Standalone "Upload Portfolio" button
            $(document).on('click', '.upload-portfolio-btn', this.showPortfolioOnlySection.bind(this));

            // Cancel standalone portfolio upload
            $(document).on('click', '.cancel-portfolio-only-btn', this.hidePortfolioOnlySection.bind(this));

            // Standalone portfolio file input change
            $(document).on('change', '#portfolio_only_file', this.handlePortfolioOnlyFileSelect.bind(this));

            // Standalone portfolio upload form submission
            $(document).on('submit', '#portfolio-only-upload-form', this.handlePortfolioOnlyUpload.bind(this));
        },

        // =========================================================
        // MARK COMPLETE FLOW
        // =========================================================

        /**
         * Open confirmation modal when Mark Complete is clicked.
         * Populates modal with LP details from data attributes.
         */
        openConfirmModal: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            this.activeMarkCompleteBtn = $btn;

            // Populate modal with LP details (XSS-safe via jQuery .text())
            const lpName      = $btn.data('product-name') || 'Unknown LP';
            const progressPct = $btn.data('progress-pct') || 0;
            const hoursPresent = $btn.data('hours-present') || 0;
            const productDuration = $btn.data('product-duration') || 0;

            $('#confirm-lp-name').text(lpName);
            $('#confirm-lp-progress').text(progressPct + '%');
            $('#confirm-lp-hours').text(hoursPresent + ' / ' + productDuration + ' hrs');

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('markCompleteConfirmModal'));
            modal.show();
        },

        /**
         * "Proceed to Upload" clicked in confirmation modal:
         * close modal, hide mark-complete button, reveal upload section.
         */
        proceedToUpload: function() {
            // Close the modal
            const modalEl = document.getElementById('markCompleteConfirmModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }

            // Hide the Mark Complete button, show upload section
            if (this.activeMarkCompleteBtn) {
                this.activeMarkCompleteBtn.addClass('d-none');
            }
            $('#upload-section').removeClass('d-none');

            // Reset progress bar
            this.resetProgressBar('#upload-progress');
        },

        /**
         * Hide the mark-complete upload section and restore the button
         */
        hideUploadSection: function(e) {
            e.preventDefault();
            $('#upload-section').addClass('d-none');
            $('.mark-complete-btn').removeClass('d-none');
            this.activeMarkCompleteBtn = null;

            // Reset form and button state
            $('#portfolio-upload-form')[0].reset();
            $('.confirm-complete-btn').prop('disabled', true);
            this.resetProgressBar('#upload-progress');
        },

        /**
         * Handle file selection for the mark-complete upload form.
         * Validates type/size and enables/disables confirm button.
         */
        handleFileSelect: function(e) {
            const $input = $(e.currentTarget);
            const $confirmBtn = $('.confirm-complete-btn');
            const file = $input[0].files[0];

            if (!file) {
                $confirmBtn.prop('disabled', true);
                return;
            }

            if (!this.validateFile(file)) {
                $input.val('');
                $confirmBtn.prop('disabled', true);
                return;
            }

            $confirmBtn.prop('disabled', false);
        },

        /**
         * Handle mark-complete form submission.
         * Sends FormData with portfolio file to wp_ajax_mark_progression_complete.
         * On success: in-place card update + auto-refresh history.
         * On error: inline alert.
         */
        handleMarkComplete: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $confirmBtn = $form.find('.confirm-complete-btn');
            const formData = new FormData($form[0]);

            formData.append('action', 'mark_progression_complete');
            formData.append('nonce', learnerSingleAjax.nonce);

            $confirmBtn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span> Processing...'
            );

            // Show progress bar at start
            this.showProgressBar('#upload-progress', 0);

            const self = this;

            $.ajax({
                url: learnerSingleAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const pct = Math.round((evt.loaded / evt.total) * 100);
                            self.showProgressBar('#upload-progress', pct);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    self.showProgressBar('#upload-progress', 100);
                    if (response.success) {
                        self.onMarkCompleteSuccess();
                    } else {
                        const msg = (response.data && response.data.message)
                            ? response.data.message
                            : (response.data || 'Failed to mark LP as complete.');
                        self.showAlert('danger', msg);
                        $confirmBtn.prop('disabled', false).html(
                            '<i class="bi bi-check-lg me-1"></i> Confirm Completion'
                        );
                        self.resetProgressBar('#upload-progress');
                    }
                },
                error: function() {
                    self.showAlert('danger', 'Server error. Please try again.');
                    $confirmBtn.prop('disabled', false).html(
                        '<i class="bi bi-check-lg me-1"></i> Confirm Completion'
                    );
                    self.resetProgressBar('#upload-progress');
                }
            });
        },

        /**
         * In-place card update after successful mark-complete.
         * Updates badge, fills progress bar, hides admin actions.
         * Triggers toast and auto-refreshes history after short delay.
         */
        onMarkCompleteSuccess: function() {
            // Update badge: "In Progress" → "Completed"
            $('#progression-current-lp .badge-phoenix-primary')
                .removeClass('badge-phoenix-primary')
                .addClass('badge-phoenix-success')
                .text('Completed');

            // Fill progress bar to 100%
            $('#progression-current-lp .progress-bar')
                .css('width', '100%')
                .attr('aria-valuenow', 100);

            // Hide admin actions section
            $('#progression-current-lp .admin-actions').hide();

            // Show success toast
            this.showAlert('success', 'LP marked as complete successfully!');

            // Auto-refresh history after short delay
            const self = this;
            setTimeout(function() {
                self.refreshProgressionData();
            }, 1000);
        },

        // =========================================================
        // STANDALONE PORTFOLIO UPLOAD FLOW (AJAX-02)
        // =========================================================

        /**
         * Show the standalone portfolio upload section
         */
        showPortfolioOnlySection: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            $btn.addClass('d-none');
            $('#portfolio-only-upload-section').removeClass('d-none');
            this.resetProgressBar('#portfolio-only-progress');
        },

        /**
         * Hide the standalone portfolio upload section
         */
        hidePortfolioOnlySection: function(e) {
            e.preventDefault();
            $('#portfolio-only-upload-section').addClass('d-none');
            $('.upload-portfolio-btn').removeClass('d-none');
            $('#portfolio-only-upload-form')[0].reset();
            $('#portfolio-only-confirm-btn').prop('disabled', true);
            this.resetProgressBar('#portfolio-only-progress');
        },

        /**
         * Handle file selection for standalone portfolio upload form
         */
        handlePortfolioOnlyFileSelect: function(e) {
            const $input = $(e.currentTarget);
            const $confirmBtn = $('#portfolio-only-confirm-btn');
            const file = $input[0].files[0];

            if (!file) {
                $confirmBtn.prop('disabled', true);
                return;
            }

            if (!this.validateFile(file)) {
                $input.val('');
                $confirmBtn.prop('disabled', true);
                return;
            }

            $confirmBtn.prop('disabled', false);
        },

        /**
         * Submit standalone portfolio upload via wp_ajax_upload_progression_portfolio.
         * On success: toast notification.
         * On error: inline alert.
         */
        handlePortfolioOnlyUpload: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $confirmBtn = $('#portfolio-only-confirm-btn');
            const formData = new FormData($form[0]);

            formData.append('action', 'upload_progression_portfolio');
            formData.append('nonce', learnerSingleAjax.nonce);

            $confirmBtn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...'
            );

            this.showProgressBar('#portfolio-only-progress', 0);

            const self = this;

            $.ajax({
                url: learnerSingleAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const pct = Math.round((evt.loaded / evt.total) * 100);
                            self.showProgressBar('#portfolio-only-progress', pct);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    self.showProgressBar('#portfolio-only-progress', 100);
                    if (response.success) {
                        self.hidePortfolioOnlySection({ preventDefault: function() {} });
                        self.showAlert('success', 'Portfolio uploaded successfully!');
                    } else {
                        const msg = (response.data && response.data.message)
                            ? response.data.message
                            : (response.data || 'Upload failed.');
                        self.showPortfolioOnlyAlert('danger', msg);
                        $confirmBtn.prop('disabled', false).html(
                            '<i class="bi bi-cloud-upload me-1"></i> Upload Portfolio'
                        );
                        self.resetProgressBar('#portfolio-only-progress');
                    }
                },
                error: function() {
                    self.showPortfolioOnlyAlert('danger', 'Server error. Please try again.');
                    $confirmBtn.prop('disabled', false).html(
                        '<i class="bi bi-cloud-upload me-1"></i> Upload Portfolio'
                    );
                    self.resetProgressBar('#portfolio-only-progress');
                }
            });
        },

        // =========================================================
        // PROGRESSION DATA REFRESH + SKELETON LOADING
        // =========================================================

        /**
         * Refresh progression data via wp_ajax_get_learner_progressions.
         * Shows skeleton while loading, then updates history timeline.
         */
        refreshProgressionData: function() {
            const learnerId = learnerSingleAjax.learnerId;
            if (!learnerId) {
                return;
            }

            this.showSkeletonCards();

            const self = this;

            $.ajax({
                url: learnerSingleAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'get_learner_progressions',
                    nonce: learnerSingleAjax.nonce,
                    learner_id: learnerId
                },
                success: function(response) {
                    self.hideSkeletonCards();
                    if (response.success && response.data) {
                        self.updateHistoryTimeline(response.data.history || []);
                    }
                },
                error: function() {
                    self.hideSkeletonCards();
                }
            });
        },

        /**
         * Show skeleton loading cards (hidden by default in PHP template)
         */
        showSkeletonCards: function() {
            $('#progression-skeleton').removeClass('d-none');
            $('#progression-history').addClass('d-none');
        },

        /**
         * Hide skeleton loading cards and restore history section
         */
        hideSkeletonCards: function() {
            $('#progression-skeleton').addClass('d-none');
            $('#progression-history').removeClass('d-none');
        },

        /**
         * Rebuild the history timeline from AJAX response data.
         * Uses jQuery DOM methods (not innerHTML) to prevent XSS.
         *
         * @param {Array} history - Array of completed LP objects
         */
        updateHistoryTimeline: function(history) {
            const $historyContainer = $('#progression-history');

            if (!history || history.length === 0) {
                return;
            }

            // Find or create the timeline inside the card body
            const $cardBody = $historyContainer.find('.timeline-basic');
            if (!$cardBody.length) {
                return;
            }

            $cardBody.empty();

            history.forEach(function(lp, index) {
                const isLast = index === history.length - 1;

                // Build timeline item using jQuery DOM (XSS-safe)
                const $item = $('<div>').addClass('d-flex mb-3' + (isLast ? '' : ' border-bottom pb-3'));

                const $icon = $('<div>')
                    .addClass('d-flex align-items-center justify-content-center bg-success rounded-circle me-3')
                    .css({ width: '36px', height: '36px', 'flex-shrink': '0' })
                    .append($('<i>').addClass('bi bi-check-lg text-white'));

                const $info = $('<div>').addClass('flex-grow-1');
                const $name = $('<h6>').addClass('mb-1').text(lp.product_name || '');

                const $meta = $('<div>').addClass('text-muted small');

                const $dates = $('<span>');
                $('<i>').addClass('bi bi-calendar3 me-1').appendTo($dates);
                $dates.append(document.createTextNode((lp.start_date_formatted || '') + ' - ' + (lp.completion_date_formatted || '')));

                const $hours = $('<span>').addClass('ms-3');
                $('<i>').addClass('bi bi-clock me-1').appendTo($hours);
                $hours.append(document.createTextNode((lp.hours_present || 0) + ' / ' + (lp.product_duration || 0) + ' hrs'));

                $meta.append($dates).append($hours);
                $info.append($name).append($meta);

                const $badge = $('<div>').append(
                    $('<span>').addClass('badge badge-phoenix badge-phoenix-success').text('Completed')
                );

                $item.append($icon).append($info).append($badge);
                $cardBody.append($item);
            });
        },

        // =========================================================
        // SHARED UTILITIES
        // =========================================================

        /**
         * Validate a file for type (PDF/DOC/DOCX) and size (10MB max).
         * Shows inline alert on failure.
         *
         * @param {File} file
         * @returns {boolean}
         */
        validateFile: function(file) {
            const allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            const allowedExtensions = ['.pdf', '.doc', '.docx'];
            const fileName = file.name.toLowerCase();
            const hasValidExtension = allowedExtensions.some(function(ext) {
                return fileName.endsWith(ext);
            });

            if (!hasValidExtension && !allowedTypes.includes(file.type)) {
                this.showAlert('danger', 'Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                return false;
            }

            const maxSize = 10 * 1024 * 1024; // 10MB
            if (file.size > maxSize) {
                this.showAlert('danger', 'File is too large. Maximum size is 10MB.');
                return false;
            }

            return true;
        },

        /**
         * Show the upload progress bar and update its percentage.
         *
         * @param {string} barSelector  CSS selector for the progress container
         * @param {number} pct          Percentage (0–100)
         */
        showProgressBar: function(barSelector, pct) {
            const $bar = $(barSelector);
            $bar.removeClass('d-none');
            $bar.find('.progress-bar').css('width', pct + '%').text(pct + '%');
        },

        /**
         * Reset and hide a progress bar.
         *
         * @param {string} barSelector  CSS selector for the progress container
         */
        resetProgressBar: function(barSelector) {
            const $bar = $(barSelector);
            $bar.find('.progress-bar').css('width', '0%').text('0%');
            $bar.addClass('d-none');
        },

        /**
         * Show an alert inside .admin-actions (mark-complete flow).
         * Uses jQuery DOM construction to prevent XSS.
         *
         * @param {string} type    Bootstrap alert type (success|danger|warning|info)
         * @param {string} message Alert text
         */
        showAlert: function(type, message) {
            const iconClass = type === 'success' ? 'check-circle' : 'exclamation-triangle';

            const $alert = $('<div>')
                .addClass('alert alert-' + type + ' alert-dismissible fade show')
                .attr('role', 'alert');

            const $icon = $('<i>').addClass('bi bi-' + iconClass + ' me-2');

            const $closeBtn = $('<button>')
                .attr('type', 'button')
                .addClass('btn-close')
                .attr('data-bs-dismiss', 'alert')
                .attr('aria-label', 'Close');

            $alert.append($icon).append(document.createTextNode(message)).append($closeBtn);

            $('.admin-actions .alert').remove();
            $('.admin-actions').prepend($alert);

            if (type === 'success') {
                setTimeout(function() {
                    $alert.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        /**
         * Show an alert inside the standalone portfolio upload section.
         * Uses jQuery DOM construction to prevent XSS.
         *
         * @param {string} type    Bootstrap alert type
         * @param {string} message Alert text
         */
        showPortfolioOnlyAlert: function(type, message) {
            const iconClass = type === 'success' ? 'check-circle' : 'exclamation-triangle';

            const $alert = $('<div>')
                .addClass('alert alert-' + type + ' alert-dismissible fade show mt-2')
                .attr('role', 'alert');

            const $icon = $('<i>').addClass('bi bi-' + iconClass + ' me-2');

            const $closeBtn = $('<button>')
                .attr('type', 'button')
                .addClass('btn-close')
                .attr('data-bs-dismiss', 'alert')
                .attr('aria-label', 'Close');

            $alert.append($icon).append(document.createTextNode(message)).append($closeBtn);

            $('#portfolio-only-upload-section .alert:not(.alert-info)').remove();
            $('#portfolio-only-upload-section').prepend($alert);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        learnerProgressions.init();
    });

})(jQuery);
