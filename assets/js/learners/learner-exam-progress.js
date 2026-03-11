/**
 * Learner Exam Progress JavaScript Handler
 *
 * Handles exam result recording, file uploads, delete/re-record,
 * and in-place UI refresh for the 5-step exam progress card.
 *
 * Features:
 * - Record exam percentage for mock steps (plain POST)
 * - Record exam percentage + file upload for SBA/final (FormData)
 * - Real-time upload progress bar via xhr.upload.onprogress
 * - Delete/re-record with confirmation
 * - Client-side validation: percentage 0–100, file ≤10MB, accepted types
 * - In-place UI refresh after each AJAX action (no page reload)
 * - Double-submit prevention on all buttons
 * - Skeleton loading during data refresh
 *
 * Depends on: learnerSingleAjax (localized by learner-single-display)
 *
 * @package WeCoza_Learners
 * @since 1.2.0
 */

(function($) {
    'use strict';

    /**
     * Step metadata — mirrors ExamStep PHP enum.
     * Keys are step values, objects have label and requiresFile.
     */
    var EXAM_STEPS = {
        mock_1: { label: 'Mock Exam 1', requiresFile: false },
        mock_2: { label: 'Mock Exam 2', requiresFile: false },
        mock_3: { label: 'Mock Exam 3', requiresFile: false },
        sba:    { label: 'SBA',         requiresFile: true  },
        'final':{ label: 'Final Exam',  requiresFile: true  }
    };

    /** Ordered list so we render in correct sequence */
    var STEP_ORDER = ['mock_1', 'mock_2', 'mock_3', 'sba', 'final'];

    var ALLOWED_EXTENSIONS = ['.pdf', '.doc', '.docx', '.jpg', '.jpeg', '.png'];
    var ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png'
    ];
    var MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    var examProgress = {

        // ==========================================================
        // INIT & EVENT BINDING
        // ==========================================================

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Record exam result — form submit
            $(document).on('submit', '.exam-record-form', this.handleRecordSubmit.bind(this));

            // Delete / re-record button
            $(document).on('click', '.exam-delete-btn', this.handleDelete.bind(this));

            // Live validation — percentage input
            $(document).on('input', '.exam-percentage-input', this.validatePercentageInput.bind(this));

            // Live validation — file input
            $(document).on('change', '.exam-file-input', this.validateFileInput.bind(this));
        },

        // ==========================================================
        // RECORD EXAM RESULT
        // ==========================================================

        /**
         * Handle exam record form submission.
         * Uses FormData for file-upload steps, plain POST for mock steps.
         */
        handleRecordSubmit: function(e) {
            e.preventDefault();

            var $form      = $(e.currentTarget);
            var trackingId = $form.data('tracking-id');
            var examStep   = $form.data('exam-step');
            var $submitBtn = $form.find('.exam-submit-btn');
            var $pctInput  = $form.find('.exam-percentage-input');
            var $fileInput = $form.find('.exam-file-input');
            var stepMeta   = EXAM_STEPS[examStep];

            // --- Client-side validation ---
            var percentage = parseFloat($pctInput.val());
            if (isNaN(percentage) || percentage < 0 || percentage > 100) {
                this.showStepAlert($form, 'danger', 'Percentage must be between 0 and 100.');
                return;
            }

            if (stepMeta && stepMeta.requiresFile) {
                if (!$fileInput.length || !$fileInput[0].files.length) {
                    this.showStepAlert($form, 'danger', 'An evidence file is required for this step.');
                    return;
                }
                var file = $fileInput[0].files[0];
                if (!this.validateFile(file, $form)) {
                    return; // validateFile already shows alert
                }
            }

            // --- Build request ---
            var formData = new FormData();
            formData.append('action', 'record_exam_result');
            formData.append('nonce', learnerSingleAjax.nonce);
            formData.append('tracking_id', trackingId);
            formData.append('exam_step', examStep);
            formData.append('percentage', percentage);

            if (stepMeta && stepMeta.requiresFile && $fileInput.length && $fileInput[0].files.length) {
                formData.append('exam_file', $fileInput[0].files[0]);
            }

            // --- Double-submit prevention ---
            $submitBtn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span> Saving...'
            );

            var self = this;
            var $progressBar = $form.find('.exam-upload-progress');

            // Show progress bar for file uploads
            if (stepMeta && stepMeta.requiresFile && $fileInput.length && $fileInput[0].files.length) {
                this.showProgressBar($progressBar, 0);
            }

            $.ajax({
                url: learnerSingleAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            var pct = Math.round((evt.loaded / evt.total) * 100);
                            self.showProgressBar($progressBar, pct);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    self.showProgressBar($progressBar, 100);
                    if (response.success) {
                        // Check if LP was auto-completed
                        if (response.data && response.data.lp_completed === true) {
                            self.showSectionAlert('success',
                                '🎓 Learning Programme completed! All exam steps have been recorded.');
                            // Refresh overall progression card if available
                            if (typeof window.refreshProgressionData === 'function') {
                                try { window.refreshProgressionData(); } catch(err) {
                                    console.warn('Could not refresh progression data:', err);
                                }
                            }
                        }
                        self.refreshExamProgress(trackingId);
                    } else {
                        var msg = (response.data && response.data.message)
                            ? response.data.message
                            : 'Failed to record exam result.';
                        self.showStepAlert($form, 'danger', msg);
                        self.resetSubmitBtn($submitBtn);
                        self.resetProgressBar($progressBar);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Exam record AJAX error:', { status: status, error: error, response: xhr.responseText });
                    self.showStepAlert($form, 'danger', 'Server error. Please try again.');
                    self.resetSubmitBtn($submitBtn);
                    self.resetProgressBar($progressBar);
                }
            });
        },

        // ==========================================================
        // DELETE / RE-RECORD
        // ==========================================================

        handleDelete: function(e) {
            e.preventDefault();

            var $btn       = $(e.currentTarget);
            var trackingId = $btn.data('tracking-id');
            var examStep   = $btn.data('exam-step');
            var stepMeta   = EXAM_STEPS[examStep];
            var stepLabel  = stepMeta ? stepMeta.label : examStep;

            if (!confirm('Remove the recorded result for "' + stepLabel + '"?\n\nThis will clear the score' +
                (stepMeta && stepMeta.requiresFile ? ' and uploaded file' : '') +
                ' so you can re-record it.')) {
                return;
            }

            // Double-submit prevention
            $btn.prop('disabled', true);

            var self = this;

            $.ajax({
                url: learnerSingleAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_exam_result',
                    nonce: learnerSingleAjax.nonce,
                    tracking_id: trackingId,
                    exam_step: examStep
                },
                success: function(response) {
                    if (response.success) {
                        self.refreshExamProgress(trackingId);
                    } else {
                        var msg = (response.data && response.data.message)
                            ? response.data.message
                            : 'Failed to delete exam result.';
                        self.showSectionAlert('danger', msg);
                        $btn.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Exam delete AJAX error:', { status: status, error: error, response: xhr.responseText });
                    self.showSectionAlert('danger', 'Server error. Please try again.');
                    $btn.prop('disabled', false);
                }
            });
        },

        // ==========================================================
        // REFRESH EXAM PROGRESS (IN-PLACE)
        // ==========================================================

        /**
         * Fetch updated exam progress from server and rebuild the
         * step cards entirely from JSON data — no page reload.
         */
        refreshExamProgress: function(trackingId) {
            var $section = $('#exam-progress-section');
            if (!$section.length) {
                return;
            }

            // Show skeleton / loading state
            this.showSectionSkeleton($section);

            var self = this;

            $.ajax({
                url: learnerSingleAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'get_exam_progress',
                    nonce: learnerSingleAjax.nonce,
                    tracking_id: trackingId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderExamSection($section, response.data, trackingId);
                    } else {
                        var msg = (response.data && response.data.message)
                            ? response.data.message
                            : 'Failed to load exam progress.';
                        self.showSectionAlert('danger', msg);
                        self.hideSectionSkeleton($section);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Exam refresh AJAX error:', { status: status, error: error, response: xhr.responseText });
                    self.showSectionAlert('danger', 'Could not refresh exam progress. Please reload the page.');
                    self.hideSectionSkeleton($section);
                }
            });
        },

        // ==========================================================
        // CLIENT-SIDE RENDERING (mirrors PHP component output)
        // ==========================================================

        /**
         * Rebuild the entire #exam-progress-section inner HTML from
         * the JSON response of get_exam_progress.
         *
         * Produces visually identical output to the PHP component
         * (learner-exam-progress.php).
         */
        renderExamSection: function($section, data, trackingId) {
            var steps          = data.steps || {};
            var completedCount = data.completed_count || 0;
            var totalSteps     = data.total_steps || STEP_ORDER.length;

            $section.empty();

            // --- Section Header ---
            var badgeClass = completedCount === totalSteps ? 'success' : 'info';
            var $header = $('<div>').addClass('d-flex justify-content-between align-items-center mb-3');
            $header.append(
                $('<h6>').addClass('mb-0').append(
                    $('<i>').addClass('bi bi-mortarboard me-2 text-primary')
                ).append(document.createTextNode('Exam Progress'))
            );
            $header.append(
                $('<span>').addClass('badge badge-phoenix badge-phoenix-' + badgeClass)
                    .text(completedCount + '/' + totalSteps + ' steps')
            );
            $section.append($header);

            // --- Hidden nonce (for future form usage) ---
            $section.append(
                $('<input>').attr({ type: 'hidden', id: 'exam-nonce', value: learnerSingleAjax.nonce })
            );

            // --- Step Cards ---
            var self = this;
            $.each(STEP_ORDER, function(_, stepValue) {
                var stepData    = steps[stepValue] || null;
                var isCompleted = stepData !== null;
                var stepMeta    = EXAM_STEPS[stepValue];

                var $card = self.renderExamStepCard(stepValue, stepData, isCompleted, stepMeta, trackingId);
                $section.append($card);
            });
        },

        /**
         * Render a single exam step card — completed or pending.
         * Uses jQuery DOM methods exclusively (XSS-safe, no innerHTML).
         */
        renderExamStepCard: function(stepValue, stepData, isCompleted, stepMeta, trackingId) {
            var $card = $('<div>')
                .addClass('exam-step-card ' + (isCompleted ? 'completed' : 'pending') + ' mb-2')
                .attr('data-tracking-id', trackingId)
                .attr('data-exam-step', stepValue);

            // --- Top row: icon + label + badge, optional delete button ---
            var $topRow = $('<div>').addClass('d-flex justify-content-between align-items-start');

            var $labelGroup = $('<div>').addClass('d-flex align-items-center gap-2');
            if (isCompleted) {
                $labelGroup.append($('<i>').addClass('bi bi-check-circle-fill text-success'));
            } else {
                $labelGroup.append($('<i>').addClass('bi bi-circle text-secondary'));
            }
            $labelGroup.append($('<span>').addClass('fw-semibold').text(stepMeta.label));
            $labelGroup.append(
                $('<span>').addClass('badge badge-phoenix badge-phoenix-' + (isCompleted ? 'success' : 'secondary') + ' fs-10')
                    .text(isCompleted ? 'Completed' : 'Pending')
            );
            $topRow.append($labelGroup);

            if (isCompleted) {
                var $deleteBtn = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-link btn-sm text-danger p-0 exam-delete-btn')
                    .attr('data-tracking-id', trackingId)
                    .attr('data-exam-step', stepValue)
                    .attr('title', 'Remove result to re-record')
                    .append($('<i>').addClass('bi bi-arrow-counterclockwise'));
                $topRow.append($deleteBtn);
            }

            $card.append($topRow);

            // --- Body: completed details OR pending form ---
            if (isCompleted) {
                $card.append(this.renderCompletedDetails(stepData, stepMeta));
            } else {
                $card.append(this.renderPendingForm(stepValue, stepMeta, trackingId));
            }

            return $card;
        },

        /**
         * Render completed step details (percentage, date, user, file link).
         */
        renderCompletedDetails: function(stepData, stepMeta) {
            var $details = $('<div>').addClass('mt-2 ms-4');
            var $metaRow = $('<div>').addClass('d-flex flex-wrap gap-3 text-muted small');

            // Percentage
            var pctVal = stepData.percentage !== undefined ? parseFloat(stepData.percentage).toFixed(1) : '0.0';
            var $pctSpan = $('<span>');
            $pctSpan.append($('<i>').addClass('bi bi-percent me-1'));
            $pctSpan.append($('<strong>').addClass('exam-step-percentage').text(pctVal + '%'));
            $metaRow.append($pctSpan);

            // Recorded date
            if (stepData.recorded_at) {
                var $dateSpan = $('<span>');
                $dateSpan.append($('<i>').addClass('bi bi-calendar3 me-1'));
                $dateSpan.append(document.createTextNode(this.formatDate(stepData.recorded_at)));
                $metaRow.append($dateSpan);
            }

            // Recorded by
            if (stepData.recorded_by_name) {
                var $userSpan = $('<span>');
                $userSpan.append($('<i>').addClass('bi bi-person me-1'));
                $userSpan.append(document.createTextNode(stepData.recorded_by_name));
                $metaRow.append($userSpan);
            }

            $details.append($metaRow);

            // File link for SBA/final
            if (stepMeta.requiresFile && stepData.file_name) {
                var $fileRow = $('<div>').addClass('mt-1 small');
                $fileRow.append($('<i>').addClass('bi bi-file-earmark-text me-1 text-primary'));
                if (stepData.file_url) {
                    $fileRow.append(
                        $('<a>').attr({ href: stepData.file_url, target: '_blank' })
                            .addClass('text-decoration-none')
                            .text(stepData.file_name)
                    );
                } else {
                    $fileRow.append(document.createTextNode(stepData.file_name));
                }
                $details.append($fileRow);
            }

            return $details;
        },

        /**
         * Render pending step form (percentage input, optional file, submit btn).
         */
        renderPendingForm: function(stepValue, stepMeta, trackingId) {
            var $form = $('<form>')
                .addClass('exam-record-form mt-2 ms-4')
                .attr('data-tracking-id', trackingId)
                .attr('data-exam-step', stepValue)
                .attr('enctype', 'multipart/form-data');

            var $row = $('<div>').addClass('row g-2 align-items-end');

            // Percentage input
            var $pctCol = $('<div>').addClass('col-auto');
            $pctCol.append($('<label>').addClass('form-label small mb-1').text('Score (%)'));
            $pctCol.append(
                $('<input>').attr({
                    type: 'number',
                    min: 0,
                    max: 100,
                    step: '0.1',
                    placeholder: '0–100',
                    required: true
                }).addClass('form-control form-control-sm exam-percentage-input')
                  .css('width', '100px')
            );
            $row.append($pctCol);

            // File input (SBA/final only)
            if (stepMeta.requiresFile) {
                var $fileCol = $('<div>').addClass('col');
                $fileCol.append($('<label>').addClass('form-label small mb-1').text('Evidence file'));
                $fileCol.append(
                    $('<input>').attr({
                        type: 'file',
                        accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png',
                        required: true
                    }).addClass('form-control form-control-sm exam-file-input')
                );
                $fileCol.append($('<div>').addClass('form-text fs-10').text('PDF, DOC, DOCX, JPG, PNG (max 10MB)'));
                $row.append($fileCol);
            }

            // Submit button
            var $btnCol = $('<div>').addClass('col-auto');
            $btnCol.append(
                $('<button>').attr('type', 'submit')
                    .addClass('btn btn-sm btn-primary exam-submit-btn')
                    .append($('<i>').addClass('bi bi-save me-1'))
                    .append(document.createTextNode('Record'))
            );
            $row.append($btnCol);

            $form.append($row);

            // Upload progress bar (hidden by default)
            var $progress = $('<div>').addClass('progress mt-2 d-none exam-upload-progress').css('height', '6px');
            $progress.append(
                $('<div>').addClass('progress-bar progress-bar-striped progress-bar-animated')
                    .attr('role', 'progressbar').css('width', '0%')
            );
            $form.append($progress);

            return $form;
        },

        // ==========================================================
        // VALIDATION
        // ==========================================================

        /**
         * Live validation for percentage input.
         * Enables/disables submit based on validity.
         */
        validatePercentageInput: function(e) {
            var $input  = $(e.currentTarget);
            var $form   = $input.closest('.exam-record-form');
            var $submit = $form.find('.exam-submit-btn');
            var val     = parseFloat($input.val());

            if (isNaN(val) || val < 0 || val > 100) {
                $input.addClass('is-invalid');
            } else {
                $input.removeClass('is-invalid');
            }

            this.updateSubmitState($form);
        },

        /**
         * Live validation for file input.
         * Validates type and size, enables/disables submit.
         */
        validateFileInput: function(e) {
            var $input = $(e.currentTarget);
            var $form  = $input.closest('.exam-record-form');
            var file   = $input[0].files[0];

            if (file && !this.validateFile(file, $form)) {
                $input.val('');
            }

            this.updateSubmitState($form);
        },

        /**
         * Validate a file against allowed types and max size.
         * Shows alert on failure.
         *
         * @param {File} file
         * @param {jQuery} $form — form context for alert
         * @returns {boolean}
         */
        validateFile: function(file, $form) {
            var fileName = file.name.toLowerCase();
            var hasValidExt = ALLOWED_EXTENSIONS.some(function(ext) {
                return fileName.endsWith(ext);
            });

            if (!hasValidExt && !ALLOWED_MIME_TYPES.includes(file.type)) {
                this.showStepAlert($form, 'danger', 'Invalid file type. Accepted: PDF, DOC, DOCX, JPG, PNG.');
                console.warn('Exam file validation failed: invalid type', { name: file.name, type: file.type });
                return false;
            }

            if (file.size > MAX_FILE_SIZE) {
                this.showStepAlert($form, 'danger', 'File is too large. Maximum size is 10 MB.');
                console.warn('Exam file validation failed: too large', { name: file.name, size: file.size });
                return false;
            }

            return true;
        },

        /**
         * Enable submit only when percentage is valid and file is present (if required).
         */
        updateSubmitState: function($form) {
            var $submit  = $form.find('.exam-submit-btn');
            var $pct     = $form.find('.exam-percentage-input');
            var $file    = $form.find('.exam-file-input');
            var examStep = $form.data('exam-step');
            var stepMeta = EXAM_STEPS[examStep];

            var pctVal = parseFloat($pct.val());
            var pctOk  = !isNaN(pctVal) && pctVal >= 0 && pctVal <= 100;
            var fileOk = true;

            if (stepMeta && stepMeta.requiresFile) {
                fileOk = $file.length > 0 && $file[0].files.length > 0;
            }

            $submit.prop('disabled', !(pctOk && fileOk));
        },

        // ==========================================================
        // UI HELPERS
        // ==========================================================

        /**
         * Show loading skeleton in the exam section.
         */
        showSectionSkeleton: function($section) {
            $section.css('opacity', '0.5').css('pointer-events', 'none');
        },

        /**
         * Remove skeleton / loading overlay.
         */
        hideSectionSkeleton: function($section) {
            $section.css('opacity', '1').css('pointer-events', '');
        },

        /**
         * Show alert inside a specific step form.
         */
        showStepAlert: function($form, type, message) {
            $form.find('.exam-step-alert').remove();

            var iconClass = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            var $alert = $('<div>')
                .addClass('alert alert-' + type + ' alert-dismissible fade show mt-2 py-1 px-2 small exam-step-alert')
                .attr('role', 'alert');

            $alert.append($('<i>').addClass('bi bi-' + iconClass + ' me-1'));
            $alert.append(document.createTextNode(message));
            $alert.append(
                $('<button>').attr({ type: 'button', 'data-bs-dismiss': 'alert', 'aria-label': 'Close' })
                    .addClass('btn-close btn-close-sm')
                    .css('font-size', '0.65rem')
            );

            $form.append($alert);

            if (type === 'success') {
                setTimeout(function() { $alert.fadeOut(function() { $(this).remove(); }); }, 4000);
            }
        },

        /**
         * Show alert at the top of the exam progress section.
         */
        showSectionAlert: function(type, message) {
            var $section = $('#exam-progress-section');
            $section.find('.exam-section-alert').remove();

            var iconClass = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            var $alert = $('<div>')
                .addClass('alert alert-' + type + ' alert-dismissible fade show mt-2 exam-section-alert')
                .attr('role', 'alert');

            $alert.append($('<i>').addClass('bi bi-' + iconClass + ' me-2'));
            $alert.append(document.createTextNode(message));
            $alert.append(
                $('<button>').attr({ type: 'button', 'data-bs-dismiss': 'alert', 'aria-label': 'Close' })
                    .addClass('btn-close')
            );

            $section.prepend($alert);

            if (type === 'success') {
                setTimeout(function() { $alert.fadeOut(function() { $(this).remove(); }); }, 5000);
            }
        },

        /**
         * Show progress bar and update width.
         */
        showProgressBar: function($bar, pct) {
            if (!$bar || !$bar.length) return;
            $bar.removeClass('d-none');
            $bar.find('.progress-bar').css('width', pct + '%');
        },

        /**
         * Reset and hide progress bar.
         */
        resetProgressBar: function($bar) {
            if (!$bar || !$bar.length) return;
            $bar.find('.progress-bar').css('width', '0%');
            $bar.addClass('d-none');
        },

        /**
         * Reset a submit button to its default state.
         */
        resetSubmitBtn: function($btn) {
            $btn.prop('disabled', false).html(
                '<i class="bi bi-save me-1"></i>Record'
            );
        },

        /**
         * Format a date string (ISO/DB format) to "j M Y" style.
         * Simple formatter — no external dependency.
         */
        formatDate: function(dateStr) {
            if (!dateStr) return '';
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        examProgress.init();
    });

})(jQuery);
