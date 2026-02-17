/**
 * Agents Application Main JavaScript
 *
 * Main application file with AJAX form submission, success/error feedback,
 * ID toggle, and loader management.
 * Bootstrap 5 compatible validation with jQuery.
 *
 * @package WeCozaCore
 * @since 3.0.0
 */

(function($) {
    'use strict';

    // Hide loader container after 2 seconds
    setTimeout(function() {
        $('#wecoza-agents-loader-container').hide();
    }, 2000);

    // -------------------------------------------------------------------------
    // AJAX Form Submission
    // -------------------------------------------------------------------------

    if (typeof window.wecozaAgents !== 'undefined') {
        var config = window.wecozaAgents;
        var form = $('#agents-form');

        if (form.length && typeof FormData !== 'undefined') {
            var container = form.closest('.wecoza-agents-form-container');
            var submitButton = form.find('button[type="submit"]');
            var feedback = container.find('.wecoza-agents-feedback');

            if (!feedback.length) {
                feedback = $('<div class="wecoza-agents-feedback mt-3"></div>');
                container.prepend(feedback);
            }

            var renderMessage = function (type, message) {
                var classes = 'alert alert-dismissible fade show';
                if (type === 'success') {
                    classes += ' alert-subtle-success';
                } else {
                    classes += ' alert-subtle-danger';
                }

                feedback.html(
                    '<div class="' + classes + '" role="alert">' +
                        '<div>' + message + '</div>' +
                        '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div>'
                );
            };

            var setSubmittingState = function (isSubmitting) {
                if (!submitButton.length) {
                    return;
                }

                if (isSubmitting) {
                    submitButton.data('original-text', submitButton.text());
                    submitButton.prop('disabled', true).text(
                        (config.messages && config.messages.form && config.messages.form.saving)
                            ? config.messages.form.saving
                            : 'Saving...'
                    );
                } else {
                    var original = submitButton.data('original-text');
                    if (original) {
                        submitButton.text(original);
                    }
                    submitButton.prop('disabled', false);
                }
            };

            var clearForm = function () {
                // Native reset
                form[0].reset();

                // Remove Bootstrap validation state
                form.removeClass('was-validated');

                // Remove hidden editing_agent_id field (switches back to create mode)
                form.find('input[name="editing_agent_id"]').remove();

                // Clear text/email/tel/number/date inputs explicitly
                form.find('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], input[type="date"]').val('');

                // Reset all selects to first option
                form.find('select').prop('selectedIndex', 0);

                // Reset ID type to SA ID (default state)
                var saIdOption = form.find('#sa_id_option');
                if (saIdOption.length) {
                    saIdOption.prop('checked', true).trigger('change');
                }

                // Clear initials field explicitly
                form.find('#initials').val('');

                // Remove validation classes from all inputs
                form.find('.is-valid, .is-invalid').removeClass('is-valid is-invalid');
            };

            var scrollToFeedback = function () {
                if (container.length) {
                    $('html, body').animate({ scrollTop: container.offset().top - 80 }, 400);
                }
            };

            var extractErrors = function (errors) {
                if (!errors) {
                    return (config.messages && config.messages.form && config.messages.form.error)
                        ? config.messages.form.error
                        : 'An error occurred. Please try again.';
                }

                if (errors.general) {
                    return errors.general;
                }

                var list = [];
                $.each(errors, function (field, message) {
                    if (message) {
                        list.push(message);
                    }
                });

                return list.length
                    ? list.join('<br>')
                    : ((config.messages && config.messages.form && config.messages.form.error)
                        ? config.messages.form.error
                        : 'An error occurred. Please try again.');
            };

            form.on('submit', function (event) {
                if (!form[0].checkValidity()) {
                    form.addClass('was-validated');
                    return;
                }

                event.preventDefault();
                form.addClass('was-validated');

                var formData = new FormData(form[0]);
                formData.append('action', config.saveAction);
                formData.append('nonce', config.nonce);

                setSubmittingState(true);

                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function (response) {
                    if (response && response.success) {
                        var data = response.data || {};
                        var message = data.message
                            || ((config.messages && config.messages.form && config.messages.form.saved)
                                ? config.messages.form.saved
                                : 'Agent saved successfully.');

                        renderMessage('success', message);
                        scrollToFeedback();
                        form.removeClass('was-validated');

                        if (data.is_new) {
                            // New agent: clear form and auto-dismiss banner after 5 seconds
                            clearForm();
                            setTimeout(function () {
                                feedback.fadeOut(300, function () {
                                    $(this).empty().show();
                                });
                            }, 5000);
                        } else {
                            // Update: reload page after brief delay to reflect saved data
                            setTimeout(function () {
                                window.location.reload();
                            }, 1500);
                        }
                    } else if (response && response.data && response.data.errors) {
                        renderMessage('error', extractErrors(response.data.errors));
                        scrollToFeedback();
                    } else {
                        renderMessage('error',
                            (config.messages && config.messages.form && config.messages.form.error)
                                ? config.messages.form.error
                                : 'An error occurred. Please try again.'
                        );
                        scrollToFeedback();
                    }
                }).fail(function () {
                    renderMessage('error',
                        (config.messages && config.messages.form && config.messages.form.error)
                            ? config.messages.form.error
                            : 'An error occurred. Please try again.'
                    );
                    scrollToFeedback();
                }).always(function () {
                    setSubmittingState(false);
                });
            });
        }
    }

    // -------------------------------------------------------------------------
    // Toggle SA ID and Passport Fields Based on Radio Selection
    // -------------------------------------------------------------------------

    var $form = $('#agents-form');
    var saIdOption = $form.find('#sa_id_option');
    var passportOption = $form.find('#passport_option');
    var saIdField = $form.find('#sa_id_field');
    var passportField = $form.find('#passport_field');
    var saIdInput = $form.find('#sa_id_no');
    var passportInput = $form.find('#passport_number');

    // Store initial values to preserve on edit mode
    var initialSaId = saIdInput.val();
    var initialPassportNumber = passportInput.val();

    /**
     * Toggle ID fields based on selected type
     *
     * @param {string} selectedType - Either 'sa_id' or 'passport'
     */
    function toggleIdFields(selectedType) {
        if (selectedType === 'sa_id') {
            saIdField.removeClass('d-none');
            passportField.addClass('d-none');
            saIdInput.prop('required', true);
            passportInput.prop('required', false);
            // Clear passport field unless it's the initial value (edit mode)
            if (passportInput.val() !== initialPassportNumber) {
                passportInput.val('').removeClass('is-valid is-invalid');
            }
        } else if (selectedType === 'passport') {
            passportField.removeClass('d-none');
            saIdField.addClass('d-none');
            passportInput.prop('required', true);
            saIdInput.prop('required', false);
            // Clear SA ID field unless it's the initial value (edit mode)
            if (saIdInput.val() !== initialSaId) {
                saIdInput.val('').removeClass('is-valid is-invalid');
            }
        }
    }

    // Event listener for radio button changes
    $form.find('input[name="id_type"]').change(function() {
        toggleIdFields($(this).val());
    });

})(jQuery);
