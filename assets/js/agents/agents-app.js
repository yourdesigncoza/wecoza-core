/**
 * Agents Application Main JavaScript
 *
 * Main application file with form validation triggers, ID toggle, and loader management.
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

    // Bootstrap form validation for agents-form
    const form = $('#agents-form');

    if (form.length) {
        form.on('submit', function(event) {
            // Check if form is valid
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Add Bootstrap's 'was-validated' class to trigger validation styles
            $(this).addClass('was-validated');
        });
    }

    /**
     * Toggle SA ID and Passport Fields Based on Radio Selection
     */
    const $form = $('#agents-form');
    const saIdOption = $form.find('#sa_id_option');
    const passportOption = $form.find('#passport_option');
    const saIdField = $form.find('#sa_id_field');
    const passportField = $form.find('#passport_field');
    const saIdInput = $form.find('#sa_id_no');
    const passportInput = $form.find('#passport_number');

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
