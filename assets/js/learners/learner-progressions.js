/**
 * Learner Progressions JavaScript Handler
 *
 * Handles mark complete flow, portfolio upload, and UI interactions
 * for the progressions tab in learner single display.
 *
 * @package WeCoza_Learners
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const learnerProgressions = {
        /**
         * Initialize the module
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Mark Complete button click
            $(document).on('click', '.mark-complete-btn', this.showUploadSection.bind(this));

            // Cancel button click
            $(document).on('click', '.cancel-complete-btn', this.hideUploadSection.bind(this));

            // File input change - enable/disable confirm button
            $(document).on('change', '#portfolio_file', this.handleFileSelect.bind(this));

            // Form submission
            $(document).on('submit', '#portfolio-upload-form', this.handleMarkComplete.bind(this));
        },

        /**
         * Show the upload section
         */
        showUploadSection: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            $btn.addClass('d-none');
            $('#upload-section').removeClass('d-none');
        },

        /**
         * Hide the upload section
         */
        hideUploadSection: function(e) {
            e.preventDefault();
            $('#upload-section').addClass('d-none');
            $('.mark-complete-btn').removeClass('d-none');
            // Reset form
            $('#portfolio-upload-form')[0].reset();
            $('.confirm-complete-btn').prop('disabled', true);
        },

        /**
         * Handle file selection - validate and enable confirm button
         */
        handleFileSelect: function(e) {
            const $input = $(e.currentTarget);
            const $confirmBtn = $('.confirm-complete-btn');
            const file = $input[0].files[0];

            if (!file) {
                $confirmBtn.prop('disabled', true);
                return;
            }

            // Validate file type
            const allowedTypes = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            const allowedExtensions = ['.pdf', '.doc', '.docx'];
            const fileName = file.name.toLowerCase();
            const hasValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));

            if (!hasValidExtension && !allowedTypes.includes(file.type)) {
                this.showAlert('danger', 'Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                $input.val('');
                $confirmBtn.prop('disabled', true);
                return;
            }

            // Validate file size (10MB max)
            const maxSize = 10 * 1024 * 1024; // 10MB in bytes
            if (file.size > maxSize) {
                this.showAlert('danger', 'File is too large. Maximum size is 10MB.');
                $input.val('');
                $confirmBtn.prop('disabled', true);
                return;
            }

            // File is valid - enable confirm button
            $confirmBtn.prop('disabled', false);
        },

        /**
         * Handle form submission - mark LP complete with portfolio
         */
        handleMarkComplete: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $confirmBtn = $form.find('.confirm-complete-btn');
            const formData = new FormData($form[0]);

            // Add action for WordPress AJAX
            formData.append('action', 'mark_progression_complete');

            // Disable button and show loading
            $confirmBtn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span> Processing...'
            );

            $.ajax({
                url: learnerSingleAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        learnerProgressions.showAlert('success', 'LP marked as complete successfully!');
                        // Reload the page to show updated state
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        learnerProgressions.showAlert('danger', response.data || 'Failed to mark LP as complete.');
                        $confirmBtn.prop('disabled', false).html(
                            '<i class="bi bi-check-lg me-1"></i> Confirm Completion'
                        );
                    }
                },
                error: function() {
                    learnerProgressions.showAlert('danger', 'Server error. Please try again.');
                    $confirmBtn.prop('disabled', false).html(
                        '<i class="bi bi-check-lg me-1"></i> Confirm Completion'
                    );
                }
            });
        },

        /**
         * Show alert message (XSS-safe using jQuery DOM methods)
         */
        showAlert: function(type, message) {
            // Create alert element safely to prevent XSS
            const iconClass = type === 'success' ? 'check-circle' : 'exclamation-triangle';

            const $alert = $('<div>')
                .addClass('alert alert-' + type + ' alert-dismissible fade show')
                .attr('role', 'alert');

            const $icon = $('<i>')
                .addClass('bi bi-' + iconClass + ' me-2');

            const $closeBtn = $('<button>')
                .attr('type', 'button')
                .addClass('btn-close')
                .attr('data-bs-dismiss', 'alert')
                .attr('aria-label', 'Close');

            // Use .text() to safely insert message (prevents XSS)
            $alert.append($icon).append(document.createTextNode(message)).append($closeBtn);

            // Remove any existing alerts
            $('.admin-actions .alert').remove();

            // Insert alert before the form/button
            $('.admin-actions').prepend($alert);

            // Auto-dismiss success alerts after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $alert.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        learnerProgressions.init();
    });

})(jQuery);
