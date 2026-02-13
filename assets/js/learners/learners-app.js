(function($) {
    'use strict';

    // console.log('learners-app.js loaded');

    /*------------------YDCOZA-----------------------*/
    /* Global Initials Generation Function           */
    /* Auto-generates initials from first & second   */
    /* name fields, excluding surname                */
    /* OPTIMIZED: Cached DOM, single event, no poll  */
    /*-----------------------------------------------*/

    // Cache DOM elements once (not inside functions)
    var $firstName, $secondName, $initials;

    function generateInitials() {
        if (!$firstName || !$secondName || !$initials) return;

        var first = $firstName.val().trim();
        var second = $secondName.val().trim();
        var result = '';

        if (first.length > 0) {
            result += first.charAt(0).toUpperCase();
        }
        if (second.length > 0) {
            result += second.charAt(0).toUpperCase();
        }

        // Only update DOM if value changed (prevents unnecessary reflows)
        if ($initials.val() !== result) {
            $initials.val(result);
        }
    }

    // Initialize initials generation for any form with name fields
    function initializeInitialsGeneration() {
        // Cache DOM elements
        $firstName = $('#first_name');
        $secondName = $('#second_name');
        $initials = $('#initials');

        // Use namespaced events to safely unbind only our listeners
        $firstName.add($secondName).off('input.wecoza');

        // Single 'input' event handles typing, paste, autofill
        // No need for keyup/blur/change or polling
        $firstName.add($secondName).on('input.wecoza', function() {
            generateInitials();
        });

        // Generate initials once on init
        generateInitials();
    }

    /*------------------YDCOZA-----------------------*/
    /* Document ready function                       */
    /* Initializes table and modal when DOM is ready */
    /*-----------------------------------------------*/
    $(document).ready(function() {
        // Initialize initials generation if name fields exist on the page
        if ($('#first_name').length && $('#second_name').length) {
            initializeInitialsGeneration();
        }
    });


    /*------------------YDCOZA-----------------------*/
    /* Client-side form validation using Bootstrap 5  */
    /* with visual feedback for learners-form only.   */
    /* Prevents form submission if validation fails   */
    /* and shows custom Bootstrap feedback styles.    */
    /*-----------------------------------------------*/

    const form = $('#learners-form'); // Target the specific learners form

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

    /*------------------YDCOZA-----------------------*/
    /* Generalized toggle function for dynamic fields */
    /* Toggles visibility and the required attribute  */
    /* based on another field's value.                */
    /*-----------------------------------------------*/

    function toggleFieldVisibility(triggerElement, targetElement, triggerValue, isRequired = false) {
        if (triggerElement.val() === triggerValue) {
            targetElement.removeClass('d-none'); // Show the field
            if (isRequired) {
                targetElement.find('input, select').attr('required', 'required'); // Add required attribute
            }
        } else {
            targetElement.addClass('d-none'); // Hide the field
            targetElement.find('input, select').removeAttr('required'); // Remove required attribute
            targetElement.find('input, select').val(''); // Clear the field value
        }
    }

    /*------------------YDCOZA-----------------------*/
    /* Dynamically show/hide Placement Date based on  */
    /* the Assessment Status selection. If the status */
    /* is "Not Assessed", hide the date field and     */
    /* remove its required attribute.                 */
    /*-----------------------------------------------*/
        // Toggle visibility of placement fields based on assessment status
        const assessmentStatus = $('#assessment_status');
        const placementDateDiv = $('#placement_assessment_date').closest('.placement_date_outerdiv');
        const placementLevelDiv = $('#numeracy_level').closest('.placement_date_outerdiv');
        const placementLevelComm = $('#communication_level').closest('.initial_communication_level');
        const placementDateInput = $('#placement_assessment_date');
        const placementLevelSelect = $('#numeracy_level');
        const form_update = $('#learners-update-form');

        function togglePlacementFields() { 
            if (assessmentStatus.val() === 'Assessed') {
                placementDateDiv.removeClass('d-none');
                placementLevelDiv.removeClass('d-none');
                placementLevelComm.removeClass('d-none');
            } else {
                placementDateDiv.addClass('d-none');
                placementLevelDiv.addClass('d-none');
                placementLevelComm.addClass('d-none');
            }
        }

        // Initial load check
        $(document).ready(function() {
            togglePlacementFields();
        });

        // Event handler for assessment status change
        assessmentStatus.change(togglePlacementFields);

        // Clear values on form submit if status is "Not Assessed"
        form_update.submit(function(e) {
            if (assessmentStatus.val() === 'Not Assessed') {
                placementDateInput.val('');
                placementLevelSelect.val('');
            }
        });

    /*------------------YDCOZA-----------------------*/
    /* Toggle Employer Field Based on Employment Status */
    /*-----------------------------------------------*/
    // Store initial values
        var initialEmployer = $('#employer_field').val();

        function toggleFieldVisibility(statusElement, fieldElement, employedValue, isRequired) {
            if (statusElement.val() === employedValue) {
                fieldElement.removeClass('d-none');
                fieldElement.prop('required', isRequired);

                // Only clear if the employer field has changed
                if (fieldElement.val() !== initialEmployer) {
                    fieldElement.val('').removeClass('is-valid is-invalid');
                }
            } else {
                fieldElement.addClass('d-none');
                fieldElement.prop('required', false);
            }
        }

        const employmentStatus = $('#employment_status');
        const employerField = $('#employer_field');

        employmentStatus.change(function() {
            toggleFieldVisibility(employmentStatus, employerField, '1', true); // Assuming 1 is "Employed"
        });

        // Initial load check
        toggleFieldVisibility(employmentStatus, employerField, '1', true);

    /*------------------YDCOZA-----------------------*/
    /* Toggle SA ID and Passport Fields Based on Radio*/
    /*-----------------------------------------------*/

        // IMPORTANT!  Reference Helper Functions in app.js
        const SA_ID_PATTERN = /^([0-9]{2})((?:[0][1-9])|(?:[1][0-2]))((?:[0-2][0-9])|(?:[3][0-1]))(?:[0-9]{7})$/;
        const PASSPORT_PATTERN = /^[A-Z0-9]{6,12}$/i;

        function validateSaId(idNumber) {
            if (!SA_ID_PATTERN.test(idNumber)) {
                return {
                    valid: false,
                    message: 'ID number must be 13 digits in format: YYMMDD + 7 digits'
                };
            }
            const year = parseInt(idNumber.substring(0, 2));
            const month = parseInt(idNumber.substring(2, 4));
            const day = parseInt(idNumber.substring(4, 6));
            const fullYear = year + (year < 50 ? 2000 : 1900);
            const date = new Date(fullYear, month - 1, day);
            if (date.getDate() !== day || date.getMonth() !== month - 1 || date.getFullYear() !== fullYear) {
                return {
                    valid: false,
                    message: 'Invalid date in ID number'
                };
            }
            let sum = 0;
            let isSecond = false;
            for (let i = idNumber.length - 1; i >= 0; i--) {
                let digit = parseInt(idNumber.charAt(i));
                if (isSecond) {
                    digit *= 2;
                    if (digit > 9) {
                        digit -= 9;
                    }
                }
                sum += digit;
                isSecond = !isSecond;
            }
            if (sum % 10 !== 0) {
                return {
                    valid: false,
                    message: 'Invalid ID number checksum'
                };
            }
            return { valid: true };
        }

        function validatePassport(passportNumber) {
            if (!PASSPORT_PATTERN.test(passportNumber)) {
                return {
                    valid: false,
                    message: 'Passport number must be 6-12 characters (letters and numbers only)'
                };
            }
            return { valid: true };
        }

        function showValidationFeedback(input, validationResult) {
            if (!validationResult.valid) {
                input.addClass('is-invalid').removeClass('is-valid');
                input.siblings('.invalid-feedback').text(validationResult.message);
            } else {
                input.addClass('is-valid').removeClass('is-invalid');
                input.siblings('.valid-feedback').text('Valid!');
            }
        }
        $(document).ready(function() {
            const $form = $('#learners-form');
            const saIdOption = $form.find('#sa_id_option');
            const passportOption = $form.find('#passport_option');
            const saIdField = $form.find('#sa_id_field');
            const passportField = $form.find('#passport_field');
            const saIdInput = $form.find('#sa_id_no');
            const passportInput = $form.find('#passport_number');

            var initialSaId = saIdInput.val();
            var initialPassportNumber = passportInput.val();

            function toggleIdFields(selectedType) {
                const idFieldsContainer = $form.find('#id_fields_container');
                
                if (selectedType === 'sa_id') {
                    idFieldsContainer.removeClass('d-none'); // Show the container
                    saIdField.removeClass('d-none');
                    passportField.addClass('d-none');
                    saIdInput.prop('required', true);
                    passportInput.prop('required', false);
                    if (passportInput.val() !== initialPassportNumber) {
                        passportInput.val('').removeClass('is-valid is-invalid');
                    }
                } else if (selectedType === 'passport') {
                    idFieldsContainer.removeClass('d-none'); // Show the container
                    passportField.removeClass('d-none');
                    saIdField.addClass('d-none');
                    passportInput.prop('required', true);
                    saIdInput.prop('required', false);
                    if (saIdInput.val() !== initialSaId) {
                        saIdInput.val('').removeClass('is-valid is-invalid');
                    }
                }
            }

            // Event listener for radio buttons
            $form.find('input[name="id_type"]').change(function() {
                toggleIdFields($(this).val());
            });

            // Real-time SA ID validation
            saIdInput.on('input', function() {
                const idNumber = $(this).val().trim();
                if (idNumber) {
                    const validationResult = validateSaId(idNumber);
                    showValidationFeedback($(this), validationResult);
                } else {
                    $(this).removeClass('is-valid is-invalid');
                }
            });

            // Real-time passport validation
            passportInput.on('input', function() {
                const passportNumber = $(this).val().trim();
                if (passportNumber) {
                    const validationResult = validatePassport(passportNumber);
                    showValidationFeedback($(this), validationResult);
                } else {
                    $(this).removeClass('is-valid is-invalid');
                }
            });

            // Form submit validation
            $form.on('submit', function(e) {
                const selectedType = $form.find('input[name="id_type"]:checked').val();
                let isValid = true;

                if (selectedType === 'sa_id') {
                    const idNumber = saIdInput.val().trim();
                    const validationResult = validateSaId(idNumber);
                    if (!validationResult.valid) {
                        isValid = false;
                        showValidationFeedback(saIdInput, validationResult);
                    }
                } else if (selectedType === 'passport') {
                    const passportNumber = passportInput.val().trim();
                    const validationResult = validatePassport(passportNumber);
                    if (!validationResult.valid) {
                        isValid = false;
                        showValidationFeedback(passportInput, validationResult);
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });
        });


})(jQuery);


// Add this to your existing JavaScrip
jQuery(document).ready(function($) {
    // Handle portfolio deletion
    $('.delete-portfolio').on('click', function(e) {
        e.preventDefault();
        
        const portfolioId = $(this).data('portfolio-id');
        const learnerId = $(this).data('learner-id');
        const $portfolioItem = $(this).closest('.portfolio-item');
        
        if (confirm('Are you sure you want to delete this portfolio file?')) {
            $.ajax({
                url: WeCozaLearners.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_learner_portfolio',
                    nonce: WeCozaLearners.nonce,
                    portfolio_id: portfolioId,
                    learner_id: learnerId
                },
                success: function(response) {
                    if (response.success) {
                        $portfolioItem.fadeOut(300, function() {
                            $(this).remove();
                            // Show success message
                            const alert = `
                                <div class="alert alert-subtle-success alert-dismissible fade show" role="alert">
                                    Portfolio file deleted successfully.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>`;
                            $('#alert-container').html(alert);
                        });
                    } else {
                        // Show error message (use text() for dynamic content to prevent XSS)
                        const $alert = $('<div class="alert alert-subtle-danger alert-dismissible fade show" role="alert"></div>');
                        $alert.text('Failed to delete portfolio file: ' + response.data);
                        $alert.append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
                        $('#alert-container').html($alert);
                    }
                },
                error: function() {
                    // Show error message
                    const alert = `
                        <div class="alert alert-subtle-danger alert-dismissible fade show" role="alert">
                            An error occurred while deleting the file.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`;
                    $('#alert-container').html(alert);
                }
            });
        }
    });

    /*------------------YDCOZA-----------------------*/
    /* Portfolio PDF Validation                       */
    /* Client-side validation for immediate UX        */
    /*-----------------------------------------------*/

    function validatePdfFiles(fileInput) {
        const files = fileInput.files;
        const errorContainer = fileInput.parentElement.querySelector('.portfolio-upload-error')
            || createErrorContainer(fileInput);

        errorContainer.textContent = '';
        errorContainer.classList.add('d-none');

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            // Check both extension and MIME type
            const ext = file.name.split('.').pop().toLowerCase();
            const validMime = file.type === 'application/pdf';
            const validExt = ext === 'pdf';

            if (!validExt || !validMime) {
                errorContainer.textContent = 'Invalid file type. Please upload a PDF document.';
                errorContainer.classList.remove('d-none');
                fileInput.value = ''; // Clear the invalid selection
                return false;
            }
        }
        return true;
    }

    function createErrorContainer(fileInput) {
        const container = document.createElement('div');
        container.className = 'portfolio-upload-error text-danger small mt-1 d-none';
        fileInput.parentElement.appendChild(container);
        return container;
    }

    // Attach validation to portfolio file inputs
    $(document).on('change', 'input[type="file"][name="portfolio_file"], input[type="file"][id="portfolio_file"]', function() {
        validatePdfFiles(this);
    });
});

    /*------------------YDCOZA-----------------------*/
    /* Tabs                      */
    /*-----------------------------------------------*/
jQuery(document).ready(function ($) {
    // Handle tab click events
    $(document).on("click", "[data-toggle='tab']", function () {
        var container = $(this).closest(".gtabs.ydcoza-tab"); // Find the closest `.gtabs.ydcoza-tab` container
        var tab = $(this).data("tab"); // Get the tab selector from the clicked button

        // Toggle active class for tabs within the closest container
        container.find(".gtab").removeClass("active");
        container.find(tab).addClass("active");

        // Toggle active class for buttons within the closest container
        container.find("[data-toggle='tab']").removeClass("active");
        $(this).addClass("active");

        // Adjust the container height
        //adjustContainerHeight(container);

    });

    // Initialize active classes on page load
        $(".gtabs.ydcoza-tab").each(function () {
            var container = $(this);
            // Adjust container height
            //adjustContainerHeight(container);

            // Ensure the correct button has the active class for the active tab on load
            var activeTab = container.find(".gtab.active");
            if (activeTab.length) {
                var activeTabClass = activeTab.attr("class").split(' ').filter(c => c.includes('tab-'))[0];
                container.find(`[data-tab=".${activeTabClass}"]`).addClass("active");
            }
        });






    /*------------------YDCOZA-----------------------*/
    /* Universal Delete Learner Handler               */
    /* Handles delete buttons across all contexts     */ 
    /* (single pages, tables, modals) with proper     */
    /* redirect logic based on context                */
    /*-----------------------------------------------*/
    $(document).on('click', '.delete-learner-btn', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const learnerId = $button.data('id');
        const isTableContext = $button.closest('#learners-table-body').length > 0;
        const isSinglePageContext = $button.closest('.wecoza-single-learner-display').length > 0;
        
        if (confirm('Are you sure you want to delete this learner? This action cannot be undone.')) {
            const originalButtonText = $button.html();
            
            $.ajax({
                url: WeCozaLearners.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_learner',
                    id: learnerId,
                    nonce: WeCozaLearners.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        if (isSinglePageContext) {
                            // Single page context - show success and redirect
                            showAlert('success', 'Learner deleted successfully. Redirecting...');
                            setTimeout(function() {
                                window.location.href = WeCozaLearners.display_learners_url;
                            }, 2000);
                        } else if (isTableContext) {
                            // Table context - remove row and update display
                            const row = $button.closest('tr');
                            row.fadeOut(300, function() {
                                $(this).remove();
                                showAlert('success', 'Learner deleted successfully');
                                // Trigger refresh if learnerTable exists
                                if (typeof window.learnerTable !== 'undefined' && window.learnerTable.fetchData) {
                                    setTimeout(() => window.learnerTable.fetchData(), 500);
                                }
                            });
                        } else {
                            // Generic context - show success and potentially refresh
                            showAlert('success', 'Learner deleted successfully');
                            setTimeout(() => location.reload(), 1500);
                        }
                    } else {
                        showAlert('error', response.data.message || 'Failed to delete learner');
                        $button.prop('disabled', false).html(originalButtonText);
                    }
                },
                error: function() {
                    showAlert('error', 'An error occurred while deleting the learner');
                    $button.prop('disabled', false).html(originalButtonText);
                }
            });
        }
    });

    // Universal alert function
    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-subtle-success' : 'alert-subtle-danger';
        const $alert = $('<div class="alert alert-dismissible fade show mb-3" role="alert"></div>')
            .addClass(alertClass)
            .text(message);
        $alert.append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');

        // Try different alert container locations
        if ($('#alert-container').length) {
            $('#alert-container').html('').append($alert);
        } else if ($('.wecoza-single-learner-display > .d-flex').length) {
            $('.wecoza-single-learner-display > .d-flex').after($alert);
        } else {
            $('body').prepend($('<div class="container mt-3"></div>').append($alert));
        }

        // Auto-dismiss after 5 seconds for error messages
        if (type !== 'success') {
            setTimeout(() => { $alert.fadeOut(); }, 5000);
        }
    }

}); // End jQuery(document).ready(function($)
