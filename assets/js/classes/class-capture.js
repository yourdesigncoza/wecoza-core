/**
 * Class Capture JavaScript for WeCoza Classes Plugin
 *
 * Handles the client-side functionality for the class capture form
 * Extracted from WeCoza theme for standalone plugin
 */

/**
 * Date/Time utility aliases - delegate to WeCozaUtils for DRY code
 * These maintain backward compatibility for any code calling these global functions
 */
var getDayIndex = window.WeCozaUtils ? window.WeCozaUtils.getDayIndex : function(dayName) {
    return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].indexOf(dayName);
};

var getDayOfWeek = window.WeCozaUtils ? window.WeCozaUtils.getDayOfWeek : function(date) {
    return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()];
};

var formatDate = window.WeCozaUtils ? window.WeCozaUtils.formatDate : function(date) {
    var d = new Date(date);
    var month = '' + (d.getMonth() + 1);
    var day = '' + d.getDate();
    var year = d.getFullYear();
    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;
    return [year, month, day].join('-');
};

var formatTime = window.WeCozaUtils ? window.WeCozaUtils.formatTime : function(dateOrHour, minute) {
    var hours = dateOrHour instanceof Date ? dateOrHour.getHours() : dateOrHour;
    var minutes = dateOrHour instanceof Date ? dateOrHour.getMinutes() : minute;
    var ampm = hours >= 12 ? 'PM' : 'AM';
    var hour12 = hours % 12 || 12;
    var minuteStr = minutes < 10 ? '0' + minutes : minutes;
    return hour12 + ':' + minuteStr + ' ' + ampm;
};

/**
 * Show a custom alert dialog instead of using the browser's native alert
 * @param {string} message - The message to display
 */
function showCustomAlert(message) {
    // Use jQuery instead of $ in the global scope
    // Create the modal HTML if it doesn't exist
    if (jQuery('#custom-alert-modal').length === 0) {
        const modalHTML = `
            <div class="modal fade" id="custom-alert-modal" tabindex="-1" aria-labelledby="custom-alert-modal-label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="custom-alert-modal-label">WeCoza Classes</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="custom-alert-message">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        jQuery('body').append(modalHTML);
    }

    // Set the message and show the modal
    jQuery('#custom-alert-message').text(message);
    const modal = new bootstrap.Modal(document.getElementById('custom-alert-modal'));
    modal.show();
}

(function ($) {
    'use strict';

    // Global variables for holiday overrides
    var holidayOverrides = {};

    /**
     * Initialize the class capture form
     */
    window.initClassCaptureForm = function () {
        // Initialize the client-site relationship
        initializeClientSiteRelationship();

        // Initialize the site address lookup
        initializeSiteAddressLookup();

        // Initialize day of week restriction for start date
        initializeDayOfWeekRestriction();

        // Initialize the exam type toggle
        initializeExamTypeToggle();

        // Initialize the SETA field toggle
        initializeSetaToggle();

        // Set up synchronization listeners for exam learner options
        classes_setup_synchronization_listeners();

        // Initialize form submission
        initializeFormSubmission();

        // Initialize QA Visit functionality
        initializeQAVisitHandlers();

        // Add listener for class_start_date changes to trigger schedule updates
        $('#class_start_date').on('change', function () {
            const newDate = $(this).val();
            if (newDate) {
                const today = new Date().toISOString().split('T')[0];

                // Update schedule start date if it's empty or still has default value
                const $scheduleStartDate = $('#schedule_start_date');
                const currentScheduleDate = $scheduleStartDate.val();

                if (!currentScheduleDate || currentScheduleDate === today) {
                    $scheduleStartDate.val(newDate);

                    // Trigger schedule data update
                    if (typeof updateScheduleData === 'function') {
                        setTimeout(updateScheduleData, 100);
                    }
                }

                // Auto-populate initial agent start date
                const $initialAgentStartDate = $('#initial_agent_start_date');
                const currentInitialAgentDate = $initialAgentStartDate.val();

                if (!currentInitialAgentDate || currentInitialAgentDate === today) {
                    $initialAgentStartDate.val(newDate);
                }

                // Auto-populate delivery date
                const $deliveryDate = $('#delivery_date');
                const currentDeliveryDate = $deliveryDate.val();

                if (!currentDeliveryDate || currentDeliveryDate === today) {
                    $deliveryDate.val(newDate);
                }

                // Auto-populate backup agent dates
                $('input[name="backup_agent_dates[]"]').each(function () {
                    const $backupDate = $(this);
                    const currentBackupDate = $backupDate.val();

                    if (!currentBackupDate || currentBackupDate === today) {
                        $backupDate.val(newDate);
                    }
                });

                // Auto-populate exception dates
                $('input[name="exception_dates[]"]').each(function () {
                    const $exceptionDate = $(this);
                    const currentExceptionDate = $exceptionDate.val();

                    if (!currentExceptionDate || currentExceptionDate === today) {
                        $exceptionDate.val(newDate);
                    }
                });
            }
        });
    }

    /**
     * Initialize day of week restriction for start date
     */
    function initializeDayOfWeekRestriction() {
        const $scheduleDay = $('#schedule_day');
        const $startDate = $('#schedule_start_date');

        // Function to restrict start date based on selected day
        function restrictStartDateByDay() {
            const selectedDay = $scheduleDay.val();

            if (selectedDay && $scheduleDay.is(':visible')) {
                // Get the current date value
                const currentDate = $startDate.val();

                if (currentDate) {
                    const date = new Date(currentDate);
                    const dayIndex = getDayIndex(selectedDay);

                    // If the current date is not the selected day, find the next occurrence
                    if (date.getDay() !== dayIndex) {
                        // Find the next occurrence of the selected day
                        while (date.getDay() !== dayIndex) {
                            date.setDate(date.getDate() + 1);
                        }

                        // Update the start date
                        $startDate.val(date.toISOString().split('T')[0]);
                    }
                }
            }
        }

        // Apply restriction when day changes
        $scheduleDay.on('change', function () {
            restrictStartDateByDay();
        });

        // Apply restriction when date changes
        $startDate.on('change', function () {
            const selectedDay = $scheduleDay.val();

            if (selectedDay && $scheduleDay.is(':visible')) {
                const date = new Date($(this).val());
                const dayIndex = getDayIndex(selectedDay);

                if (date.getDay() !== dayIndex) {
                    // Find the next occurrence of the selected day
                    while (date.getDay() !== dayIndex) {
                        date.setDate(date.getDate() + 1);
                    }

                    // Update the start date
                    $(this).val(date.toISOString().split('T')[0]);
                }
            }
        });

        // Initial check
        restrictStartDateByDay();
    }

    /**
     * Initialize the client-site relationship
     * Filters the site_id dropdown based on the selected client_id
     */
    function initializeClientSiteRelationship() {
        // Get the client and site dropdowns
        const $clientDropdown = $("#client_id");
        const $siteDropdown = $("#site_id");

        // Add event listener to client dropdown
        $clientDropdown.on("change", function () {
            const selectedClientId = $(this).val();
            const selectedClientName = $(this).find("option:selected").text();

            // Reset site selection
            $siteDropdown.val("");

            // Show all optgroups and options initially
            $siteDropdown.find("optgroup").show();
            $siteDropdown.find("option").prop("disabled", false);

            // If a client is selected, hide other optgroups and disable their options
            if (selectedClientId) {
                $siteDropdown.find("optgroup").each(function () {
                    if ($(this).attr("label") !== selectedClientName) {
                        $(this).hide();
                        $(this).find("option").prop("disabled", true);
                    }
                });
            }

            // Trigger change event on site dropdown to update any dependent fields
            $siteDropdown.trigger("change");
        });

        // Initial filtering on page load if a client is already selected
        if ($clientDropdown.val()) {
            $clientDropdown.trigger("change");
        }
    }

    /**
     * Initialize the site address lookup
     */
    function initializeSiteAddressLookup() {
        // Get site addresses from localized script
        const siteAddresses = wecozaClass.siteAddresses || {};

        // On change of the select, look up the address and show/hide accordingly
        $("#site_id").on("change", function () {
            var selectedValue = $(this).val();
            var $addressWrapper = $("#address-wrapper");
            var $addressInput = $("#site_address");

            if (selectedValue) {
                // Site selected - always show address field
                $addressWrapper.show();

                // Populate if address data exists, otherwise show empty
                if (siteAddresses[selectedValue]) {
                    $addressInput.val(siteAddresses[selectedValue]);
                } else {
                    $addressInput.val("");
                }
            } else {
                // No site selected - hide address field
                $addressInput.val("");
                $addressWrapper.hide();
            }
        });
    }

    /**
     * Initialize the SETA field toggle
     */
    function initializeSetaToggle() {
        // Handle SETA Funded selection change
        const setaFundedElement = document.getElementById('seta_funded');
        if (setaFundedElement) {
            setaFundedElement.addEventListener('change', function () {
                var setaContainer = document.getElementById('seta_container');
                var setaSelect = document.getElementById('seta_id');

                if (this.value === 'Yes') {
                    // Show SETA field and make it required
                    setaContainer.style.display = 'block';
                    setaSelect.setAttribute('required', 'required');
                } else {
                    // Hide SETA field and remove required attribute
                    setaContainer.style.display = 'none';
                    setaSelect.removeAttribute('required');
                    // Reset the SETA selection
                    setaSelect.value = '';
                }
            });
        }

        // Check initial state on page load
        var setaFunded = document.getElementById('seta_funded');
        if (setaFunded && setaFunded.value === 'Yes') {
            var setaContainer = document.getElementById('seta_container');
            var setaSelect = document.getElementById('seta_id');
            if (setaContainer && setaSelect) {
                setaContainer.style.display = 'block';
                setaSelect.setAttribute('required', 'required');
            }
        }
    }

    /**
     * Initialize the exam type toggle and exam learners functionality
     */
    function initializeExamTypeToggle() {
        // References to DOM elements
        const $examTypeContainer = $('#exam_type_container');
        const $examLearnersContainer = $('#exam_learners_container');
        const $examLearnerSelect = $('#exam_learner_select');
        const $addSelectedExamLearnersBtn = $('#add-selected-exam-learners-btn');
        const $examLearnersTable = $('#exam-learners-table');
        const $examLearnersTbody = $('#exam-learners-tbody');
        const $noExamLearnersMessage = $('#no-exam-learners-message');
        const $examLearnersData = $('#exam_learners');

        // Array to store exam learner data
        let examLearners = [];

        // Handle exam class selection change
        const examClassElement = document.getElementById('exam_class');
        if (examClassElement) {
            examClassElement.addEventListener('change', function () {
                if (this.value === 'Yes') {
                    // Show exam type field and make it required
                    $examTypeContainer.show();
                    document.getElementById('exam_type').setAttribute('required', 'required');

                    // Show exam learners container
                    $examLearnersContainer.show();

                    // Update the exam learner select options based on class learners
                    updateExamLearnerOptions();

                    // Also trigger global synchronization to ensure consistency
                    setTimeout(function () {
                        if (typeof window.classes_sync_exam_learner_options === 'function') {
                            window.classes_sync_exam_learner_options();
                        }
                    }, 100);

                    // Validate exam learners immediately
                    if (typeof validateExamLearners === 'function') {
                        validateExamLearners();
                    }
                } else {
                    // Hide exam type field and remove required attribute
                    $examTypeContainer.hide();
                    document.getElementById('exam_type').removeAttribute('required');

                    // Hide exam learners container
                    $examLearnersContainer.hide();

                    // Clear exam learners data
                    examLearners = [];
                    updateExamLearnersData();

                    // Remove any validation styling
                    $('#exam_learners_container').removeClass('border-danger');
                    $('#no-exam-learners-message').removeClass('alert-subtle-danger').addClass('alert-subtle-info');
                }
            });
        }

        // Function to update the exam learner select options based on class learners
        function updateExamLearnerOptions() {
            // Get the current class learners
            const classLearnersData = JSON.parse($('#class_learners_data').val() || '[]');

            // Clear the current options
            $examLearnerSelect.empty();

            // Add options for each class learner
            classLearnersData.forEach(function (learner) {
                // Skip learners that are already in the exam learners list - ensure both IDs are strings for comparison
                if (!examLearners.some(el => String(el.id) === String(learner.id))) {
                    $examLearnerSelect.append(`<option value="${learner.id}">${learner.name}</option>`);
                }
            });
        }

        // Function to update the hidden field with exam learner data
        function updateExamLearnersData() {
            // Stringify the exam learners array and store in hidden field
            const jsonData = JSON.stringify(examLearners);
            $examLearnersData.val(jsonData);

            // Update the exam learner count hidden input if it exists
            if ($('#exam_learner_count').length) {
                $('#exam_learner_count').val(examLearners.length);
            } else {
                // Create a hidden input for exam learner count if it doesn't exist
                $('<input>').attr({
                    type: 'hidden',
                    id: 'exam_learner_count',
                    name: 'exam_learner_count',
                    value: examLearners.length
                }).appendTo('#classes-form');
            }
        }

        // Function to update the exam learners table display
        function updateExamLearnersDisplay() {
            // Clear existing rows
            $examLearnersTbody.empty();

            if (examLearners.length === 0) {
                // Show no exam learners message and hide table
                $noExamLearnersMessage.removeClass('d-none');
                $examLearnersTable.addClass('d-none');
                return;
            }

            // Hide no exam learners message and show table
            $noExamLearnersMessage.addClass('d-none');
            $examLearnersTable.removeClass('d-none');

            // Add each exam learner to the table (level/status inherited from class learners)
            examLearners.forEach(function (learner) {
                const row = `
                    <tr>
                        <td>${learner.name}</td>
                        <td>
                            <button type="button" class="btn btn-subtle-danger btn-sm remove-exam-learner-btn" data-learner-id="${learner.id}">
                                <i data-feather="trash-2" style="height:12.8px;width:12.8px;"></i>
                                Remove
                            </button>
                        </td>
                    </tr>
                `;
                $examLearnersTbody.append(row);
            });

            // Re-initialize feather icons for new buttons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }

        }

        // Handle add selected exam learners button click
        $addSelectedExamLearnersBtn.on('click', function () {
            const selectedOptions = $examLearnerSelect.find('option:selected');

            if (selectedOptions.length === 0) {
                alert('Please select at least one learner to add for exams.');
                return;
            }


            // Add each selected learner
            selectedOptions.each(function () {
                const learnerId = $(this).val();
                const learnerName = $(this).text();

                // Convert to string to ensure consistent comparison
                const learnerIdStr = String(learnerId);

                // Check if learner is already added to exam learners - ensure both IDs are strings for comparison
                if (examLearners.some(learner => String(learner.id) === learnerIdStr)) {
                    return;
                }

                // Add learner to exam learners array (level/status inherited from class learners)
                const examLearnerData = {
                    id: learnerIdStr,
                    name: learnerName
                };

                examLearners.push(examLearnerData);
            });

            // Update the display and data
            updateExamLearnersDisplay();
            updateExamLearnersData();
            updateExamLearnerOptions(); // Refresh dropdown to remove selected learners

            // Clear the selection
            $examLearnerSelect.val([]);
        });

        // Shared removal logic for exam learners (used by both UI and cascading removal)
        function removeExamLearner(learnerId) {
            const learnerIdStr = String(learnerId);
            const initialLength = examLearners.length;
            examLearners = examLearners.filter(learner => String(learner.id) !== learnerIdStr);

            if (examLearners.length < initialLength) {
                updateExamLearnersDisplay();
                updateExamLearnersData();
                updateExamLearnerOptions();
            }
        }

        // Handle remove exam learner button click
        $(document).on('click', '.remove-exam-learner-btn', function () {
            removeExamLearner($(this).data('learner-id'));
        });

        // Expose removal globally for cascading removal from class learners
        window.classes_remove_exam_learner = removeExamLearner;

        // Load existing exam learner data if available (for editing)
        const existingExamData = $examLearnersData.val();
        if (existingExamData) {
            try {
                examLearners = JSON.parse(existingExamData);
                updateExamLearnersDisplay();
            } catch (e) {
                console.error('Error parsing existing exam learner data:', e);
            }
        }
    }


    /**
     * Validate schedule data before submission
     */
    function validateScheduleData() {
        const errors = [];
        const result = { isValid: true, errors: errors };

        // Check if schedule start date exists
        const scheduleStartDate = $('#schedule_start_date').val();
        if (!scheduleStartDate) {
            // Try to use class start date as fallback
            const classStartDate = $('#class_start_date').val();
            if (classStartDate) {
                $('#schedule_start_date').val(classStartDate);
                console.log('Used class start date as fallback for schedule start date');
            } else {
                // If we're still missing the date, set today's date as default
                const today = new Date().toISOString().split('T')[0];
                $('#schedule_start_date').val(today);
                console.log('Used today\'s date as fallback for schedule start date:', today);
            }
        }

        // Check if schedule pattern is selected
        const pattern = $('#schedule_pattern').val();
        if (!pattern) {
            errors.push('Schedule pattern must be selected');
        }

        // For weekly/biweekly patterns, ensure at least one day is selected
        if ((pattern === 'weekly' || pattern === 'biweekly')) {
            const selectedDays = $('.schedule-day-checkbox:checked').length;
            if (selectedDays === 0) {
                errors.push('At least one day must be selected for weekly/biweekly schedules');
            }
        }

        // For monthly pattern, ensure day of month is selected
        if (pattern === 'monthly') {
            const dayOfMonth = $('#schedule_day_of_month').val();
            if (!dayOfMonth) {
                errors.push('Day of month must be selected for monthly schedules');
            }
        }

        // Check if class duration exists
        const duration = $('#class_duration').val();
        if (!duration || duration <= 0) {
            errors.push('Class duration is required and must be greater than 0');
        }

        result.isValid = errors.length === 0;

        if (!result.isValid) {
            console.error('Schedule validation errors:', errors);
        }

        return result;
    }

    /**
     * Check if at least one Deliveries event exists in Event Dates
     * @returns {boolean} True if at least one delivery event exists
     */
    function checkForDeliveryEvent() {
        let hasDelivery = false;
        $('.event-date-row:not(.d-none):not(#event-date-row-template)').each(function() {
            const eventType = $(this).find('select[name="event_types[]"]').val();
            const eventDate = $(this).find('input[name="event_dates_input[]"]').val();
            if (eventType === 'Deliveries' && eventDate) {
                hasDelivery = true;
                return false; // Break out of each loop
            }
        });
        return hasDelivery;
    }

    /**
     * Helper function to format file sizes
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Initialize QA Visit functionality
     * Handles dynamic addition and removal of QA visit date/report rows
     */
    function initializeQAVisitHandlers() {
        const $container = $('#qa-visits-container');
        const $template = $('#qa-visit-row-template');
        const $addButton = $('#add-qa-visit-btn');

        if (!$container.length || !$template.length || !$addButton.length) {
            console.log('QA Visit elements not found, skipping initialization');
            return;
        }

        // Add new QA visit row
        $addButton.on('click', function (e) {
            e.preventDefault();

            // Clone the template row
            const $newRow = $template.clone();
            $newRow.removeClass('d-none');
            $newRow.removeAttr('id');

            // Generate unique names for form inputs to avoid conflicts
            const timestamp = Date.now();
            $newRow.find('input[name="qa_visit_dates[]"]').attr('id', 'qa_visit_date_' + timestamp);
            $newRow.find('select[name="qa_visit_types[]"]').attr('id', 'qa_visit_type_' + timestamp);
            $newRow.find('input[name="qa_officers[]"]').attr('id', 'qa_officer_' + timestamp);
            $newRow.find('input[name="qa_reports[]"]').attr('id', 'qa_report_' + timestamp);

            // Append to container
            $container.append($newRow);

            // Initialize remove button handler for this row
            initializeRemoveButton($newRow);

            // Focus on the date input
            $newRow.find('input[name="qa_visit_dates[]"]').focus();

            console.log('Added new QA visit row');
        });

        // Initialize remove buttons for existing rows (if any from pre-population)
        $container.find('.qa-visit-row').each(function () {
            initializeRemoveButton($(this));
        });

        // Add event handlers for QA visit form changes
        $(document).on('change', 'input[name="qa_visit_dates[]"], select[name="qa_visit_types[]"], input[name="qa_officers[]"]', function () {
            updateQAVisitsData();
        });

        // Add file change handler to update metadata and show preview
        $(document).on('change', 'input[name="qa_reports[]"]', function () {
            const $fileInput = $(this);
            const $row = $fileInput.closest('.qa-visit-row');

            // Remove any existing file display and replace button
            $row.find('.qa-report-file-display').remove();
            $row.find('.qa-new-file-preview').remove();

            // If a file is selected, show a preview
            if (this.files && this.files[0]) {
                const file = this.files[0];
                // Use global utility with secure fallback (local escapeHtml - fail closed)
                const esc = window.WeCozaUtils ? window.WeCozaUtils.escapeHtml : escapeHtml;
                const $preview = $('<div class="qa-new-file-preview small mt-1 text-success"></div>');
                $preview.html('<i class="bi bi-file-earmark-plus"></i> New file: ' + esc(file.name) + ' (' + formatFileSize(file.size) + ')');
                $fileInput.after($preview);
            }

            updateQAVisitsData();
        });

        /**
         * Update QA visits data - simplified version
         */
        function updateQAVisitsData() {
            const visitData = [];

            $container.find('.qa-visit-row:visible').each(function (index) {
                const $row = $(this);
                const visitDate = $row.find('input[name="qa_visit_dates[]"]').val();
                const visitType = $row.find('select[name="qa_visit_types[]"]').val();
                const officerName = $row.find('input[name="qa_officers[]"]').val();
                const $fileInput = $row.find('input[name="qa_reports[]"]');

                // Only include rows with at least a date
                if (visitDate) {
                    const visit = {
                        date: visitDate,
                        type: visitType || 'Initial QA Visit',
                        officer: officerName || '',
                        hasNewFile: $fileInput.val() ? true : false
                    };

                    // Check if there's existing file data
                    const $existingFile = $row.find('.qa-report-file-display');
                    if ($existingFile.length > 0 && !$fileInput.val()) {
                        const existingData = $existingFile.data('document-info');
                        if (existingData) {
                            visit.existingDocument = existingData;
                        }
                    }

                    visitData.push(visit);
                }
            });

            // Store the complete visit data
            $('#qa_visits_data').val(JSON.stringify(visitData));
        }

        /**
         * Initialize remove button for a specific row
         */
        function initializeRemoveButton($row) {
            $row.find('.remove-qa-visit-btn').on('click', function (e) {
                e.preventDefault();

                // Check if this is the last row
                const rowCount = $container.find('.qa-visit-row').length;

                if (rowCount <= 1) {
                    showCustomAlert('At least one QA visit row must remain.');
                    return;
                }

                // Confirm removal if there's data
                const dateValue = $row.find('input[name="qa_visit_dates[]"]').val();
                const typeValue = $row.find('select[name="qa_visit_types[]"]').val();
                const officerValue = $row.find('input[name="qa_officers[]"]').val();
                const fileValue = $row.find('input[name="qa_reports[]"]').val();

                if (dateValue || typeValue || officerValue || fileValue) {
                    if (!confirm('Are you sure you want to remove this QA visit? Any unsaved data will be lost.')) {
                        return;
                    }
                }

                // Remove the row with animation
                $row.fadeOut(300, function () {
                    $(this).remove();
                    updateQAVisitsData();
                    console.log('Removed QA visit row');
                });
            });
        }

        // Add validation function that can be called during form submission
        window.validateQAVisits = function () {
            let isValid = true;
            let hasAnyData = false;

            $container.find('.qa-visit-row:visible').each(function () {
                const $row = $(this);
                const $dateInput = $row.find('input[name="qa_visit_dates[]"]');
                const $fileInput = $row.find('input[name="qa_reports[]"]');

                // Remove previous validation classes
                $dateInput.removeClass('is-invalid is-valid');
                $fileInput.removeClass('is-invalid is-valid');

                // Check if row has any data
                const hasDate = $dateInput.val() ? true : false;
                const hasFile = $fileInput.val() ? true : false;

                if (hasDate || hasFile) {
                    hasAnyData = true;

                    // If there's a file but no date, it's invalid
                    if (hasFile && !hasDate) {
                        $dateInput.addClass('is-invalid');
                        isValid = false;
                    } else if (hasDate) {
                        $dateInput.addClass('is-valid');
                        if (hasFile) {
                            $fileInput.addClass('is-valid');
                        }
                    }
                }
            });

            return {
                isValid: isValid,
                hasData: hasAnyData,
                errorMessage: isValid ? '' : 'Please provide dates for all QA visit entries with files.'
            };
        };

        console.log('QA Visit handlers initialized');
    }

    /**
     * Data Models for Class Notes and QA
     */
    const ClassNotesQAModels = {

        // Note model
        Note: class {
            constructor(data = {}) {
                this.id = data.id || null;
                this.title = data.title || '';
                this.content = data.content || '';
                this.category = data.category || [];
                this.priority = data.priority || 'medium';
                this.created_at = data.created_at || new Date().toISOString();
                this.updated_at = data.updated_at || new Date().toISOString();
                this.author_id = data.author_id || wecozaClass.currentUserId;
                this.author_name = data.author_name || '';
                this.attachments = data.attachments || [];
                this.tags = data.tags || [];
            }

            validate() {
                const errors = [];
                if (!this.title || this.title.trim() === '') {
                    errors.push('Title is required');
                }
                if (!this.content || this.content.trim() === '') {
                    errors.push('Content is required');
                }
                return { isValid: errors.length === 0, errors };
            }

            toJSON() {
                return {
                    id: this.id,
                    title: this.title,
                    content: this.content,
                    category: this.category,
                    created_at: this.created_at,
                    updated_at: this.updated_at,
                    author_id: this.author_id,
                    author_name: this.author_name,
                    attachments: this.attachments,
                    tags: this.tags
                };
            }
        },

        // QA Visit model
        QAVisit: class {
            constructor(data = {}) {
                this.id = data.id || null;
                this.visit_date = data.visit_date || '';
                this.officer_name = data.officer_name || '';
                this.report_file = data.report_file || null;
                this.report_url = data.report_url || '';
                this.notes = data.notes || '';
                this.status = data.status || 'pending';
                this.created_at = data.created_at || new Date().toISOString();
            }

            validate() {
                const errors = [];
                if (!this.visit_date) {
                    errors.push('Visit date is required');
                }
                if (!this.report_file && !this.report_url) {
                    errors.push('Report file is required');
                }
                return { isValid: errors.length === 0, errors };
            }

            toJSON() {
                return {
                    id: this.id,
                    visit_date: this.visit_date,
                    officer_name: this.officer_name,
                    report_url: this.report_url,
                    notes: this.notes,
                    status: this.status,
                    created_at: this.created_at
                };
            }
        },

        // Collection manager for notes and QA visits
        Collection: class {
            constructor(itemClass) {
                this.items = [];
                this.itemClass = itemClass;
                this.currentPage = 1;
                this.itemsPerPage = 10;
                this.totalItems = 0;
                this.filters = {};
                this.sortBy = 'created_at';
                this.sortOrder = 'desc';
            }

            add(item) {
                if (!(item instanceof this.itemClass)) {
                    item = new this.itemClass(item);
                }
                this.items.push(item);
                this.totalItems++;
                return item;
            }

            remove(id) {
                const index = this.items.findIndex(item => item.id === id);
                if (index > -1) {
                    this.items.splice(index, 1);
                    this.totalItems--;
                    return true;
                }
                return false;
            }

            find(id) {
                return this.items.find(item => item.id === id);
            }

            update(id, data) {
                const item = this.find(id);
                if (item) {
                    Object.assign(item, data);
                    item.updated_at = new Date().toISOString();
                    return item;
                }
                return null;
            }

            setFilter(key, value) {
                if (value === null || value === undefined || value === '') {
                    delete this.filters[key];
                } else {
                    this.filters[key] = value;
                }
                this.currentPage = 1; // Reset to first page when filtering
            }

            setSearch(term) {
                this.searchTerm = (term || '').toLowerCase();
                this.currentPage = 1;
            }

            setSort(field, order = 'asc') {
                this.sortBy = field;
                this.sortOrder = order;
            }

            getFiltered() {
                let filtered = [...this.items];

                // Apply filters
                for (const [key, value] of Object.entries(this.filters)) {
                    filtered = filtered.filter(item => {
                        if (key === 'dateRange' && value.start && value.end) {
                            const itemDate = new Date(item.created_at);
                            return itemDate >= new Date(value.start) && itemDate <= new Date(value.end);
                        }
                        if (key === 'tags' && Array.isArray(value)) {
                            return value.some(tag => item.tags && item.tags.includes(tag));
                        }
                        return item[key] === value;
                    });
                }

                // Apply search
                if (this.searchTerm) {
                    filtered = filtered.filter(item => {
                        const searchableFields = ['title', 'content', 'notes', 'officer_name', 'author_name'];
                        return searchableFields.some(field => {
                            const fieldValue = item[field];
                            return fieldValue && fieldValue.toLowerCase().includes(this.searchTerm);
                        });
                    });
                }

                // Apply sorting
                filtered.sort((a, b) => {
                    let aVal = a[this.sortBy];
                    let bVal = b[this.sortBy];

                    // Handle date sorting
                    if (this.sortBy.includes('date') || this.sortBy.includes('_at')) {
                        aVal = new Date(aVal).getTime();
                        bVal = new Date(bVal).getTime();
                    }
                    // Handle priority sorting (high > medium > low)
                    else if (this.sortBy === 'priority') {
                        const priorityOrder = { high: 3, medium: 2, low: 1 };
                        aVal = priorityOrder[aVal] || 0;
                        bVal = priorityOrder[bVal] || 0;
                    }
                    // Handle string sorting (case-insensitive)
                    else if (typeof aVal === 'string' && typeof bVal === 'string') {
                        aVal = aVal.toLowerCase();
                        bVal = bVal.toLowerCase();
                    }

                    if (this.sortOrder === 'asc') {
                        return aVal > bVal ? 1 : -1;
                    } else {
                        return aVal < bVal ? 1 : -1;
                    }
                });

                return filtered;
            }

            getPaginated() {
                const filtered = this.getFiltered();
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;

                return {
                    items: filtered.slice(start, end),
                    totalItems: filtered.length,
                    totalPages: Math.ceil(filtered.length / this.itemsPerPage),
                    currentPage: this.currentPage,
                    itemsPerPage: this.itemsPerPage
                };
            }

            setPage(page) {
                const totalPages = Math.ceil(this.getFiltered().length / this.itemsPerPage);
                if (page >= 1 && page <= totalPages) {
                    this.currentPage = page;
                }
            }

            toJSON() {
                return this.items.map(item => item.toJSON());
            }
        }
    };

    // Initialize collections
    window.classNotesCollection = new ClassNotesQAModels.Collection(ClassNotesQAModels.Note);
    window.qaVisitsCollection = new ClassNotesQAModels.Collection(ClassNotesQAModels.QAVisit);

    /**
     * Initialize search and filter functionality
     */
    function initializeSearchFilter() {
        // Search input handler
        $(document).on('input', '#notes-search-input', function () {
            const searchTerm = $(this).val();
            window.classNotesCollection.setSearch(searchTerm);
            refreshNotesDisplay();
        });

        $(document).on('input', '#qa-search-input', function () {
            const searchTerm = $(this).val();
            window.qaVisitsCollection.setSearch(searchTerm);
            refreshQADisplay();
        });

        // Filter handlers

        $(document).on('change', '#qa-status-filter', function () {
            const status = $(this).val();
            window.qaVisitsCollection.setFilter('status', status);
            refreshQADisplay();
        });

        // Date range filter
        $(document).on('change', '#date-range-filter', function () {
            const value = $(this).val();
            let dateRange = null;

            if (value === 'today') {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);
                dateRange = { start: today, end: tomorrow };
            } else if (value === 'week') {
                const end = new Date();
                const start = new Date();
                start.setDate(start.getDate() - 7);
                dateRange = { start, end };
            } else if (value === 'month') {
                const end = new Date();
                const start = new Date();
                start.setMonth(start.getMonth() - 1);
                dateRange = { start, end };
            }

            window.classNotesCollection.setFilter('dateRange', dateRange);
            window.qaVisitsCollection.setFilter('dateRange', dateRange);
            refreshNotesDisplay();
            refreshQADisplay();
        });

        // Sort handlers
        $(document).on('click', '.sort-trigger', function () {
            const field = $(this).data('sort-field');
            const currentOrder = $(this).data('sort-order') || 'asc';
            const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';

            $(this).data('sort-order', newOrder);
            $(this).find('i').removeClass('bi-chevron-up bi-chevron-down')
                .addClass(newOrder === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down');

            if ($(this).closest('.notes-section').length) {
                window.classNotesCollection.setSort(field, newOrder);
                refreshNotesDisplay();
            } else {
                window.qaVisitsCollection.setSort(field, newOrder);
                refreshQADisplay();
            }
        });

        // Pagination handlers
        $(document).on('click', '.pagination-link', function (e) {
            e.preventDefault();
            const page = $(this).data('page');
            const target = $(this).data('target');

            if (target === 'notes') {
                window.classNotesCollection.setPage(page);
                refreshNotesDisplay();
            } else {
                window.qaVisitsCollection.setPage(page);
                refreshQADisplay();
            }
        });
    }

    /**
     * Refresh notes display with current filters and pagination
     */
    function refreshNotesDisplay() {
        if (!window.classNotesCollection) return;

        const paginatedData = window.classNotesCollection.getPaginated();

        // Update notes count with search indication
        const totalCount = window.classNotesCollection.getFiltered().length;

        let countText = `${totalCount} note${totalCount !== 1 ? 's' : ''}`;

        $('#notes-count').text(countText);

        // Notes controls are now always visible - no need to show/hide the container
        // The interface elements should remain visible even when there are no notes

        // Render notes in enhanced card grid layout
        renderNotesGrid(paginatedData.items);

        // Update pagination
        renderNotesPagination(paginatedData);

        // Show no results message if search/filter returned empty
        if (totalCount === 0 && window.classNotesCollection.items.length > 0) {
            $('#notes-no-results').show();
            $('#notes-empty').hide();

            // Update no results message
            const hasFilters = Object.keys(window.classNotesCollection.filters).length > 0;

            if (hasFilters) {
                $('#notes-no-results p').text('No notes found matching your filter criteria.');
            } else {
                $('#notes-no-results p').text('No notes found.');
            }
        } else if (totalCount === 0) {
            // Truly empty - no notes at all
            $('#notes-empty').show();
            $('#notes-no-results').hide();
        } else {
            // Has notes - hide empty states
            $('#notes-empty').hide();
            $('#notes-no-results').hide();
        }
    }

    /**
     * Refresh QA display with current filters and pagination
     */
    function refreshQADisplay() {
        const { items, totalPages, currentPage } = window.qaVisitsCollection.getPaginated();
        const $container = $('#qa-visits-list');

        if (!$container.length) return;

        $container.empty();

        if (items.length === 0) {
            $container.html('<div class="alert alert-subtle-info">No QA visits found matching your criteria.</div>');
            return;
        }

        // Render QA visits
        items.forEach(visit => {
            const statusClass = visit.status === 'completed' ? 'success' :
                visit.status === 'pending' ? 'warning' : 'danger';
            const visitHtml = `
                <div class="qa-visit-item card mb-2" data-visit-id="${visit.id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1">Visit Date: ${formatDate(visit.visit_date)}</h6>
                                <p class="card-text small mb-1">Officer: ${escapeHtml(visit.officer_name || 'Not assigned')}</p>
                                <span class="badge bg-${statusClass}">${visit.status}</span>
                            </div>
                            <div>
                                ${visit.report_url ?
                    `<a href="${visit.report_url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-pdf"></i> View Report
                                    </a>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $container.append(visitHtml);
        });

        // Update pagination
        updatePagination('qa', currentPage, totalPages);
    }

    /**
     * Update pagination controls
     */
    function updatePagination(target, currentPage, totalPages) {
        const $pagination = $(`#${target}-pagination`);
        if (!$pagination.length || totalPages <= 1) return;

        $pagination.empty();

        // Previous button
        $pagination.append(`
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link pagination-link" href="#" data-page="${currentPage - 1}" data-target="${target}">Previous</a>
            </li>
        `);

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                $pagination.append(`
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link pagination-link" href="#" data-page="${i}" data-target="${target}">${i}</a>
                    </li>
                `);
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                $pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }
        }

        // Next button
        $pagination.append(`
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link pagination-link" href="#" data-page="${currentPage + 1}" data-target="${target}">Next</a>
            </li>
        `);
    }

    /**
     * Utility function to escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Utility function to format date
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Initialize form submission
     */
    function initializeFormSubmission() {
        const $form = $('#classes-form');
        const $submitButton = $form.find('button[type="submit"]');

        $form.on('submit', function (e) {
            e.preventDefault();

            // Show loading state
            const originalButtonText = $submitButton.html();
            $submitButton.html('<i class="bi bi-spinner-border me-2"></i>Saving...').prop('disabled', true);

            // Clear previous messages
            $('#form-messages').empty();

            // Ensure schedule data is updated before submission
            if (typeof updateScheduleData === 'function') {
                updateScheduleData();
                console.log('Called updateScheduleData before form submission');
            }

            // Validate schedule data before submission
            const scheduleValidation = validateScheduleData();
            if (!scheduleValidation.isValid) {
                showErrorMessage('Schedule validation failed: ' + scheduleValidation.errors.join(', '));
                $submitButton.html(originalButtonText).prop('disabled', false);
                return false;
            }

            // Validate QA visits if the function exists
            if (typeof validateQAVisits === 'function') {
                const qaValidation = validateQAVisits();
                if (!qaValidation.isValid) {
                    showErrorMessage('QA Visit validation failed: ' + qaValidation.errorMessage);
                    $submitButton.html(originalButtonText).prop('disabled', false);
                    return false;
                }
            }

            // Check if at least one Deliveries event exists (warning only, not blocking)
            const hasDeliveryEvent = checkForDeliveryEvent();
            if (!hasDeliveryEvent) {
                const proceedWithoutDelivery = confirm(
                    'Warning: No delivery date has been added.\n\n' +
                    'It is recommended to add at least one "Deliveries" event in the Event Dates section.\n\n' +
                    'Do you want to continue saving without a delivery date?'
                );
                if (!proceedWithoutDelivery) {
                    $submitButton.html(originalButtonText).prop('disabled', false);
                    // Scroll to Event Dates section
                    const eventSection = document.getElementById('event-dates-container');
                    if (eventSection) {
                        eventSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
            }

            // Prepare form data
            const formData = new FormData(this);

            // Add AJAX action
            formData.append('action', 'save_class');

            // Add nonce for security
            formData.append('nonce', wecozaClass.nonce);

            // Debug logging
            if (wecozaClass.debug) {
                console.log('Form submission initiated');
                console.log('AJAX URL:', wecozaClass.ajaxUrl);
                console.log('Action:', 'save_class');
                console.log('Nonce:', wecozaClass.nonce);
                console.log('Form fields:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ':', pair[1]);
                }
            }

            // Submit via AJAX
            $.ajax({
                url: wecozaClass.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Show success message
                        showSuccessMessage(response.data.message || 'Class saved successfully!');

                        // Check for redirect URL from server response first, then form field
                        const redirectUrl = response.data.redirect_url || $('#redirect_url').val();

                        if (redirectUrl) {
                            // Log the redirect for debugging
                            console.log('Redirecting to:', redirectUrl);

                            setTimeout(function () {
                                window.location.href = redirectUrl;
                            }, 1500);
                        } else {
                            // Reset form for new entry
                            $form[0].reset();

                            // Optionally show the class ID
                            if (response.data.class_id) {
                                console.log('Class created with ID:', response.data.class_id);
                            }
                        }
                    } else {
                        // Show error message
                        showErrorMessage(response.data || 'An error occurred while saving the class.');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });

                    let errorMessage = 'A network error occurred. ';

                    if (xhr.status === 0) {
                        errorMessage += 'Please check your internet connection.';
                    } else if (xhr.status === 404) {
                        errorMessage += 'The requested endpoint was not found.';
                    } else if (xhr.status === 500) {
                        errorMessage += 'A server error occurred. Please check the server logs.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    } else if (xhr.responseText) {
                        // Try to extract meaningful error from response
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data) {
                                errorMessage = response.data;
                            }
                        } catch (e) {
                            errorMessage += 'Status: ' + xhr.status + '. Please try again.';
                        }
                    } else {
                        errorMessage += 'Status: ' + xhr.status + '. Please try again.';
                    }

                    showErrorMessage(errorMessage);
                },
                complete: function () {
                    // Restore button state
                    $submitButton.html(originalButtonText).prop('disabled', false);
                }
            });
        });
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        const alertHtml = `
            <div class="alert alert-subtle-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Success!</strong> ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#form-messages').html(alertHtml);

        // Scroll to message
        $('html, body').animate({
            scrollTop: $('#form-messages').offset().top - 100
        }, 500);
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        const alertHtml = `
            <div class="alert alert-subtle-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error!</strong> ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#form-messages').html(alertHtml);

        // Scroll to message
        $('html, body').animate({
            scrollTop: $('#form-messages').offset().top - 100
        }, 500);
    }

    /**
     * Global function to synchronize exam learner options with class learners
     * This function can be called from other scripts when class learners change
     */
    window.classes_sync_exam_learner_options = function () {
        // Check if exam learner functionality is initialized and visible
        const $examLearnersContainer = $('#exam_learners_container');
        const $examLearnerSelect = $('#exam_learner_select');

        if (!$examLearnersContainer.is(':visible') || !$examLearnerSelect.length) {
            return; // Exit if exam learners section is not visible or doesn't exist
        }

        // Get the current class learners data
        const classLearnersData = JSON.parse($('#class_learners_data').val() || '[]');

        // Get current exam learners data to exclude them from options
        const examLearnersData = JSON.parse($('#exam_learners').val() || '[]');

        // Clear current options
        $examLearnerSelect.empty();

        // Add options for each class learner that's not already in exam learners - ensure both IDs are strings for comparison
        classLearnersData.forEach(function (learner) {
            if (!examLearnersData.some(el => String(el.id) === String(learner.id))) {
                $examLearnerSelect.append(`<option value="${learner.id}">${learner.name}</option>`);
            }
        });

        // console.log('Synchronized exam learner options with', classLearnersData.length, 'class learners');
    };

    /**
     * Set up event listeners for automatic synchronization
     */
    function classes_setup_synchronization_listeners() {
        // Listen for custom event when class learners change
        $(document).on('classLearnersChanged', function (event, classLearners) {
            if (typeof window.classes_sync_exam_learner_options === 'function') {
                window.classes_sync_exam_learner_options();
            }
        });

        // Also listen for changes to the class_learners_data hidden field directly
        $(document).on('change', '#class_learners_data', function () {
            console.log('Class learners data field changed, synchronizing exam learner options');
            if (typeof window.classes_sync_exam_learner_options === 'function') {
                window.classes_sync_exam_learner_options();
            }
        });

        // Listen for custom event when exam learners change (for cascading removal display updates)
        $(document).on('examLearnersChanged', function (event, examLearners) {
            console.log('Exam learners changed event received, refreshing display');
            // Trigger a refresh of the exam learners display if the container is visible
            const $examLearnersContainer = $('#exam_learners_container');
            if ($examLearnersContainer.is(':visible')) {
                // Find and trigger the local updateExamLearnersDisplay function if available
                // This is a bit of a hack, but necessary since the function is scoped within initializeExamTypeToggle
                const $examLearnersTable = $('#exam-learners-table');
                const $examLearnersTbody = $('#exam-learners-tbody');
                const $noExamLearnersMessage = $('#no-exam-learners-message');

                if (examLearners.length === 0) {
                    // Show no exam learners message and hide table
                    $noExamLearnersMessage.removeClass('d-none');
                    $examLearnersTable.addClass('d-none');
                } else {
                    // Clear existing rows and rebuild
                    $examLearnersTbody.empty();

                    // Hide no exam learners message and show table
                    $noExamLearnersMessage.addClass('d-none');
                    $examLearnersTable.removeClass('d-none');

                    // Add each exam learner to the table
                    examLearners.forEach(function (learner) {
                        const levelSelectHtml = classes_generate_learner_level_select_html(learner.id, learner.level || '');
                        const statusOptions = [
                            { value: 'CIC - Currently in Class', text: 'CIC - Currently in Class' },
                            { value: 'RBE - Removed by Employer', text: 'RBE - Removed by Employer' },
                            { value: 'DRO - Drop Out', text: 'DRO - Drop Out' }
                        ];

                        const statusSelectHtml = `
                            <select class="form-select form-select-sm exam-learner-status-select" data-learner-id="${learner.id}">
                                ${statusOptions.map(opt =>
                            `<option value="${opt.value}" ${learner.status === opt.value ? 'selected' : ''}>${opt.text}</option>`
                        ).join('')}
                            </select>
                        `;

                        const row = `
                            <tr>
                                <td>${learner.name}</td>
                                <td>${levelSelectHtml}</td>
                                <td>${statusSelectHtml}</td>
                                <td>
                                    <button type="button" class="btn btn-subtle-danger btn-sm remove-exam-learner-btn" data-learner-id="${learner.id}">
                                        <i data-feather="trash-2" style="height:12.8px;width:12.8px;"></i>
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        `;
                        $examLearnersTbody.append(row);
                    });

                    // Re-initialize feather icons for new buttons
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                }
            }
        });
    }

    /**
     * Load class notes via AJAX
     */
    function loadClassNotes(classId) {
        if (!classId) return;

        const $loadingIndicator = $('#notes-loading');
        const $notesContainer = $('#class-notes-container');

        if (!$notesContainer.length) return;

        // Show loading state
        $loadingIndicator.removeClass('d-none');
        // Only hide no-results, let empty state be handled by the response
        $('#notes-no-results').addClass('d-none');

        $.ajax({
            url: wecozaClass.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_class_notes',
                nonce: wecozaClass.nonce,
                class_id: classId
            },
            success: function (response) {
                if (response.success && response.data.notes) {
                    // Clear collection and add loaded notes
                    if (window.classNotesCollection) {
                        window.classNotesCollection.items = [];
                        response.data.notes.forEach(note => {
                            window.classNotesCollection.add(note);
                        });

                        // Refresh display
                        refreshNotesDisplay();
                    }
                } else {
                    // No notes - ensure collection is empty and refresh display
                    if (window.classNotesCollection) {
                        window.classNotesCollection.items = [];
                        refreshNotesDisplay();
                    }
                }
            },
            error: function () {
                // Show error in empty state area
                $('#notes-empty').show().html(`
                    <div class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle display-4 mb-2"></i>
                        <p class="mb-0">Failed to load notes. Please try again.</p>
                    </div>
                `);
            },
            complete: function () {
                // Hide loading state
                $loadingIndicator.addClass('d-none');
            }
        });
    }

    /**
     * Load QA visits via AJAX
     */
    function loadQAVisits(classId) {
        if (!classId) return;

        const $container = $('#qa-visits-display-container');
        if (!$container.length) return;

        // Show loading state
        $container.html('<div class="text-center py-3"><i class="bi bi-spinner-border"></i> Loading QA visits...</div>');

        // Get QA visit dates and reports from form or database
        const qaVisitDates = [];
        $('#qa-visits-container .qa-visit-row:not(.d-none)').each(function () {
            const date = $(this).find('.qa-visit-date').val();
            if (date) qaVisitDates.push(date);
        });

        // For update mode, also load from database
        if (classId && classId !== 'new') {
            $.ajax({
                url: wecozaClass.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_class_qa_data',
                    nonce: wecozaClass.nonce,
                    class_id: classId
                },
                success: function (response) {
                    if (response.success && response.data) {
                        // Clear collection and add loaded visits
                        window.qaVisitsCollection.items = [];

                        // Process visit dates
                        if (response.data.qa_visit_dates) {
                            response.data.qa_visit_dates.forEach((date, index) => {
                                const visit = {
                                    id: `visit_${index}`,
                                    visit_date: date,
                                    status: 'scheduled'
                                };

                                // Check if there's a corresponding report
                                if (response.data.qa_reports && response.data.qa_reports[index]) {
                                    const report = response.data.qa_reports[index];
                                    visit.report_url = report.url;
                                    visit.officer_name = report.officer || '';
                                    visit.status = 'completed';
                                    visit.notes = report.notes || '';
                                }

                                window.qaVisitsCollection.add(visit);
                            });
                        }

                        // Initialize QA display UI
                        if (!$('#qa-search-input').length) {
                            const qaDisplayHtml = `
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <input type="text" id="qa-search-input" class="form-control" placeholder="Search QA visits...">
                                    </div>
                                    <div class="col-md-3">
                                        <select id="qa-status-filter" class="form-select form-select-sm">
                                            <option value="">All Status</option>
                                            <option value="scheduled">Scheduled</option>
                                            <option value="completed">Completed</option>
                                            <option value="pending">Pending Review</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-secondary w-100 sort-trigger" data-sort-field="visit_date">
                                            Sort by Date <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="qa-visits-list"></div>
                                <nav>
                                    <ul class="pagination justify-content-center" id="qa-pagination"></ul>
                                </nav>
                            `;
                            $container.html(qaDisplayHtml);
                        }

                        refreshQADisplay();
                    } else {
                        $container.html('<div class="alert alert-subtle-info">No QA visits scheduled for this class.</div>');
                    }
                },
                error: function () {
                    $container.html('<div class="alert alert-subtle-danger">Failed to load QA visits. Please try again.</div>');
                }
            });
        }
    }

    /**
     * Save class note via AJAX
     */
    function saveClassNote(noteData, classId) {
        return $.ajax({
            url: wecozaClass.ajaxUrl,
            type: 'POST',
            data: {
                action: 'save_class_note',
                nonce: wecozaClass.nonce,
                class_id: classId,
                note: noteData
            }
        });
    }

    /**
     * Delete QA report via AJAX
     */
    function deleteQAReport(reportId, classId) {
        return $.ajax({
            url: wecozaClass.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_qa_report',
                nonce: wecozaClass.nonce,
                class_id: classId,
                report_id: reportId
            }
        });
    }

    // Removed renderNotesCards function - using table view only

    /**
     * Render notes in enhanced card grid layout
     */
    function renderNotesGrid(notes) {
        const $notesList = $('#notes-list');
        $notesList.empty();

        if (!notes || notes.length === 0) {
            $('#notes-empty').show();
            $('#notes-no-results').hide();
            return;
        }

        $('#notes-empty').hide();
        $('#notes-no-results').hide();

        const $grid = $('<div class="notes-grid"></div>');

        notes.forEach(note => {
            const noteCard = createEnhancedNoteCard(note);
            $grid.append(noteCard);
        });

        $notesList.append($grid);
    }

    /**
     * Generate CSS class name from category text
     */
    function generateCategoryClass(category) {
        if (!category || category === 'general') {
            return 'note-category-general';
        }

        // Convert category text to CSS class name
        // e.g., "Client Cancelled" -> "note-category-client-cancelled"
        const className = category
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, '') // Remove special characters
            .replace(/\s+/g, '-')        // Replace spaces with hyphens
            .trim();

        return `note-category-${className}`;
    }

    /**
     * Generate badges HTML for categories (supports multiple categories)
     */
    function generateCategoryBadges(categories) {
        if (!categories) {
            return '<span class="note-category-badge note-category-general">general</span>';
        }

        // Handle both string and array categories
        const categoryArray = Array.isArray(categories) ? categories : [categories];

        return categoryArray.map(category => {
            const categoryText = category.trim();
            const cssClass = generateCategoryClass(categoryText);
            return `<span class="note-category-badge ${cssClass}">${categoryText}</span>`;
        }).join(' ');
    }

    /**
     * Create an enhanced note card with full feature display
     */
    function createEnhancedNoteCard(note) {
        const createdDate = new Date(note.created_at).toLocaleDateString();
        const createdTime = new Date(note.created_at).toLocaleTimeString();
        const relativeTime = getRelativeTime(note.created_at);

        // Check if note was updated
        const isUpdated = note.updated_at && note.updated_at !== note.created_at;
        const updatedTime = isUpdated ? getRelativeTime(note.updated_at) : null;


        // Generate category badges
        const categoryBadges = generateCategoryBadges(note.category);

        // Priority class for card border - old notes default to medium priority
        const priority = note.priority || 'medium';
        const priorityClass = `priority-${priority.toLowerCase()}`;

        // Attachments indicator with clickable dropdown
        let attachmentsIndicator = '';
        if (note.attachments && note.attachments.length > 0) {
            const count = Array.isArray(note.attachments) ? note.attachments.length : 1;
            const attachmentsList = note.attachments.map(attachment => `
                <li><a class="dropdown-item" href="${attachment.url}" target="_blank" download="${attachment.name}">
                    <i class="bi bi-download me-2"></i>${attachment.name}
                </a></li>
            `).join('');

            attachmentsIndicator = `
                <div class="dropdown note-attachments-dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle note-attachments-indicator" 
                            type="button" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false" 
                            title="${count} attachment(s)">
                        <i class="bi bi-paperclip"></i>
                        <span>${count}</span>
                    </button>
                    <ul class="dropdown-menu">
                        ${attachmentsList}
                    </ul>
                </div>
            `;
        }

        // Content with expand/collapse functionality
        const maxLength = 200;
        const needsExpansion = note.content.length > maxLength;
        const shortContent = needsExpansion ? note.content.substring(0, maxLength) + '...' : note.content;

        const contentHtml = needsExpansion ? `
            <div class="note-content-expandable">
                <div class="note-content-full note-content-collapsed" data-full-content="${escapeHtml(note.content)}">
                    ${escapeHtml(shortContent)}
                </div>
                <button type="button" class="note-expand-btn fs-10" onclick="toggleNoteContent(this)">
                    <i class="bi bi-chevron-down me-1"></i>Show More
                </button>
            </div>
        ` : `
            <div class="note-content-full">
                ${escapeHtml(note.content)}
            </div>
        `;

        return $(`
            <div class="note-card ${priorityClass}" data-note-id="${note.id}">
                <div class="note-card-header">
                    <div class="note-card-categories">
                        ${categoryBadges}
                    </div>
                    <div class="note-card-metadata">
                        ${attachmentsIndicator}
                    </div>
                </div>
                <div class="note-card-body">
                    ${contentHtml}
                </div>
                <div class="note-card-footer">
                    <div class="note-card-meta">
                        <span><i class="bi bi-person"></i> ${escapeHtml(note.author_name || 'Unknown')}</span>
                        <span title="${createdDate} ${createdTime}"><i class="bi bi-calendar"></i> ${relativeTime}</span>
                        ${updatedTime ? `<span class="note-updated-indicator" title="Updated ${updatedTime}"><i class="bi bi-pencil"></i> Updated</span>` : ''}
                    </div>
                    <div class="note-card-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary edit-note-btn" data-note-id="${note.id}" title="Edit note">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-note-btn" data-note-id="${note.id}" title="Delete note">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `);
    }

    /**
     * Toggle note content expand/collapse
     */
    function toggleNoteContent(button) {
        const $button = $(button);
        const $contentDiv = $button.siblings('.note-content-full');
        const $icon = $button.find('i');

        if ($contentDiv.hasClass('note-content-collapsed')) {
            // Expand
            const fullContent = $contentDiv.data('full-content');
            $contentDiv.html(escapeHtml(fullContent));
            $contentDiv.removeClass('note-content-collapsed');
            $icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
            $button.html('<i class="bi bi-chevron-up me-1"></i>Show Less');
        } else {
            // Collapse
            const fullContent = $contentDiv.text();
            const shortContent = fullContent.substring(0, 200) + '...';
            $contentDiv.html(escapeHtml(shortContent));
            $contentDiv.addClass('note-content-collapsed');
            $icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
            $button.html('<i class="bi bi-chevron-down me-1"></i>Show More');
        }
    }

    // Make toggleNoteContent globally available
    window.toggleNoteContent = toggleNoteContent;

    // Removed createNoteRow function - using enhanced card grid layout

    /**
     * Get relative time string (e.g., "2 hours ago", "3 days ago")
     */
    function getRelativeTime(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) {
            return 'Just now';
        } else if (diffMins < 60) {
            return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;
        } else if (diffHours < 24) {
            return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
        } else if (diffDays < 7) {
            return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
        } else if (diffDays < 30) {
            const weeks = Math.floor(diffDays / 7);
            return `${weeks} week${weeks !== 1 ? 's' : ''} ago`;
        } else if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            return `${months} month${months !== 1 ? 's' : ''} ago`;
        } else {
            const years = Math.floor(diffDays / 365);
            return `${years} year${years !== 1 ? 's' : ''} ago`;
        }
    }

    /**
     * Get file icon based on file type
     */
    // Removed getFileIcon function - was only used in card view

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


    // Removed filter state persistence - page loads with clean state

    /**
     * Initialize notes display functionality
     */
    function initializeNotesDisplay() {
        // View toggle functionality removed - using table view only




        // Category filter - removed duplicate handler (already handled by delegated event on line 1080)

        // Priority filter
        $('#notes-priority-filter').on('change', function () {
            const priority = $(this).val();
            if (window.classNotesCollection) {
                window.classNotesCollection.setFilter('priority', priority);
                refreshNotesDisplay();
            }
        });


        // Sort functionality
        $('#notes-sort').on('change', function () {
            const sortValue = $(this).val();
            if (window.classNotesCollection) {
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
                refreshNotesDisplay();
            }
        });

        // Clear filters
        $('#clear-notes-filters').on('click', function () {
            // Clear all form inputs
            $('#notes-priority-filter').val('');
            $('#notes-sort').val('newest');

            if (window.classNotesCollection) {
                // Clear all filters from collection
                window.classNotesCollection.setFilter('priority', '');
                window.classNotesCollection.setSort('created_at', 'desc');
                refreshNotesDisplay();

                // Filter persistence removed
            }
        });

        // Add new note functionality - clear files when opening modal for new note
        $(document).on('click', '#add-class-note-btn', function () {
            // Clear the file list for new notes
            const $fileList = $('#note-file-list');
            $fileList.empty();

            // Reset uploaded files array
            if (window.getUploadedFiles) {
                const uploadedFiles = window.getUploadedFiles();
                uploadedFiles.length = 0; // Clear the array
            }

            // Set modal title for new note
            $('#note-modal-title').text('Add Class Note');
        });

        // Edit note functionality
        $(document).on('click', '.edit-note-btn', function () {
            const noteId = $(this).data('note-id');
            const note = window.classNotesCollection ? window.classNotesCollection.find(noteId) : null;

            if (note) {
                // Populate the modal with note data
                $('#note_id').val(note.id);
                $('#note_content').val(note.content);
                // Handle multi-select class notes
                $('#class_notes').val(Array.isArray(note.category) ? note.category : [note.category]);
                $('#note_priority').val(note.priority || '');

                // Load existing attachments if any
                if (note.attachments && note.attachments.length > 0) {
                    const $fileList = $('#note-file-list');
                    $fileList.empty(); // Clear any existing files

                    note.attachments.forEach(attachment => {
                        const fileId = 'existing_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                        const fileHtml = `
                            <div class="uploaded-file" data-file-id="${fileId}">
                                <div class="file-info">
                                    <i class="bi bi-file-earmark me-2"></i>
                                    <span class="file-name">${attachment.name}</span>
                                    <small class="text-muted ms-2">(existing file)</small>
                                </div>
                                <div class="file-actions">
                                    <a href="${attachment.url}" target="_blank" class="btn btn-sm btn-outline-info me-1" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-file-btn" data-file-id="${fileId}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        $fileList.append(fileHtml);

                        // Add to uploadedFiles array to maintain consistency
                        if (window.getUploadedFiles) {
                            const uploadedFiles = window.getUploadedFiles();
                            uploadedFiles.push({
                                name: attachment.name,
                                url: attachment.url,
                                elementId: fileId,
                                isExisting: true
                            });
                        }
                    });
                }

                // Update modal title
                $('#note-modal-title').text('Edit Class Note');

                // Show modal
                $('#classNoteModal').modal('show');
            }
        });

        // Delete note functionality
        $(document).on('click', '.delete-note-btn', function () {
            const noteId = $(this).data('note-id');
            const classId = $('#note_class_id').val() || $('#class_id').val();

            console.log('Delete note - ID:', noteId, 'Class ID:', classId); // Debug log

            if (!classId) {
                alert('Error: Unable to determine class ID');
                return;
            }

            if (confirm('Are you sure you want to delete this note?')) {
                // Call delete endpoint
                $.ajax({
                    url: wecozaClass.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'delete_class_note',
                        nonce: wecozaClass.nonce,
                        class_id: classId,
                        note_id: noteId
                    },
                    success: function (response) {
                        if (response.success) {
                            // Remove from collection
                            if (window.classNotesCollection) {
                                window.classNotesCollection.remove(noteId);
                                refreshNotesDisplay();
                            }
                            showSuccessMessage('Note deleted successfully!');
                        } else {
                            showErrorMessage(response.data || 'Failed to delete note');
                        }
                    },
                    error: function () {
                        showErrorMessage('Failed to delete note. Please try again.');
                    }
                });
            }
        });

        // Initialize collections
        if (!window.classNotesCollection) {
            window.classNotesCollection = new ClassNotesQAModels.Collection(ClassNotesQAModels.Note);
        }

        // View preference removed - using table view only

        // Initialize pagination and sorting handlers
        initializePaginationHandlers();
        initializeSortingHandlers();

        // Filter persistence removed - page loads with clean state
    }


    /**
     * Render pagination for notes
     */
    function renderNotesPagination(paginatedData) {
        const $pagination = $('#notes-pagination');
        const $paginationNav = $('#notes-pagination-nav');

        // Hide pagination if only one page
        if (paginatedData.totalPages <= 1) {
            $paginationNav.hide();
            return;
        }

        $paginationNav.show();
        $pagination.empty();

        const currentPage = paginatedData.currentPage;
        const totalPages = paginatedData.totalPages;

        // Previous button
        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        $pagination.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        `);

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            $pagination.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="1">1</a>
                </li>
            `);
            if (startPage > 2) {
                $pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const active = i === currentPage ? 'active' : '';
            $pagination.append(`
                <li class="page-item ${active}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                $pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            $pagination.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a>
                </li>
            `);
        }

        // Next button
        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        $pagination.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        `);

        // Show pagination info
        const start = ((currentPage - 1) * paginatedData.itemsPerPage) + 1;
        const end = Math.min(currentPage * paginatedData.itemsPerPage, paginatedData.totalItems);
        const paginationInfo = `
            <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted">
                    Showing ${start}-${end} of ${paginatedData.totalItems} notes
                </small>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">Items per page:</small>
                    <select class="form-select form-select-sm" id="notes-per-page" style="width: auto;">
                        <option value="5" ${paginatedData.itemsPerPage === 5 ? 'selected' : ''}>5</option>
                        <option value="10" ${paginatedData.itemsPerPage === 10 ? 'selected' : ''}>10</option>
                        <option value="20" ${paginatedData.itemsPerPage === 20 ? 'selected' : ''}>20</option>
                        <option value="50" ${paginatedData.itemsPerPage === 50 ? 'selected' : ''}>50</option>
                    </select>
                </div>
            </div>
        `;

        $paginationNav.append(paginationInfo);
    }

    /**
     * Initialize pagination handlers
     */
    function initializePaginationHandlers() {
        // Page click handler
        $(document).on('click', '#notes-pagination .page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && !$(this).parent().hasClass('disabled')) {
                window.classNotesCollection.setPage(page);
                refreshNotesDisplay();
            }
        });

        // Items per page change handler
        $(document).on('change', '#notes-per-page', function () {
            const itemsPerPage = parseInt($(this).val());
            window.classNotesCollection.itemsPerPage = itemsPerPage;
            window.classNotesCollection.currentPage = 1; // Reset to first page
            refreshNotesDisplay();
        });
    }

    /**
     * Enhanced sorting functionality
     */
    function initializeSortingHandlers() {
        // Sort dropdown handler
        $('#notes-sort').on('change', function () {
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
            window.classNotesCollection.currentPage = 1; // Reset to first page
            refreshNotesDisplay();
        });

        // Quick sort buttons for date
        $(document).on('click', '.notes-sort-date', function () {
            const currentSort = window.classNotesCollection.sortBy;
            const currentOrder = window.classNotesCollection.sortOrder;

            let newOrder = 'desc';
            if (currentSort === 'created_at') {
                newOrder = currentOrder === 'desc' ? 'asc' : 'desc';
            }

            window.classNotesCollection.setSort('created_at', newOrder);
            refreshNotesDisplay();

            // Update button appearance
            const icon = newOrder === 'desc' ? 'bi-chevron-down' : 'bi-chevron-up';
            $(this).find('i').removeClass('bi-chevron-down bi-chevron-up').addClass(icon);
        });
    }

    /**
     * Debounce function for search input
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Initialize data loading based on context
     */
    function initializeDataLoading() {
        // Get class ID from hidden input or URL
        const classId = $('#class_id').val() || new URLSearchParams(window.location.search).get('class_id');

        if (classId && classId !== 'new') {
            // Load existing data
            loadClassNotes(classId);
            loadQAVisits(classId);
        } else {
            // No valid class ID - show empty state
            if (window.classNotesCollection) {
                refreshNotesDisplay();
            }
        }

        // Initialize search/filter functionality
        initializeSearchFilter();

        // Handle class selection change (if applicable)
        $('#class_id, #class-select').on('change', function () {
            const newClassId = $(this).val();
            if (newClassId) {
                loadClassNotes(newClassId);
                loadQAVisits(newClassId);
            }
        });
    }

    /**
     * Initialize form processing with validation and CSRF protection
     */
    function initializeFormProcessing() {
        // Initialize note form
        initializeNoteForm();

        // Initialize QA form
        initializeQAForm();

        // Initialize auto-save functionality
        initializeAutoSave();

        // Initialize file upload functionality
        initializeFileUpload();
    }

    /**
     * Initialize class note form with validation
     */
    function initializeNoteForm() {
        const $noteForm = $('#class-note-form');
        const $noteModal = $('#classNoteModal');
        const $saveBtn = $('#save-note-btn');
        const $errorAlert = $('#note-error-alert');
        const $errorMessage = $('#note-error-message');
        const $charCount = $('#note-char-count');
        const $noteContent = $('#note_content');

        // Character counter
        $noteContent.on('input', function () {
            $charCount.text($(this).val().length);
        });

        // Form submission
        $noteForm.on('submit', function (e) {
            e.preventDefault();

            // Reset validation states
            $noteForm.removeClass('was-validated');
            $errorAlert.addClass('d-none');

            // Validate form
            if (!this.checkValidity()) {
                e.stopPropagation();
                $noteForm.addClass('was-validated');
                return;
            }

            // Get form data
            const formData = {
                action: 'save_class_note',
                nonce: wecozaClass.nonce,
                class_id: $('#note_class_id').val(),
                note_id: $('#note_id').val(),
                content: $('#note_content').val().trim(),
                category: $('#class_notes').val() || [], // Multi-select array
                priority: $('#note_priority').val()
            };

            // Validate data
            const validation = validateNoteData(formData);
            if (!validation.isValid) {
                showFormError($errorAlert, $errorMessage, validation.errors.join(', '));
                return;
            }

            // Show loading state
            setButtonLoading($saveBtn, true);

            // Upload files first if any
            window.uploadNotesFiles().then(uploadedFiles => {
                // Add uploaded files to form data
                formData.attachments = uploadedFiles;

                // Submit via AJAX
                $.ajax({
                    url: wecozaClass.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'save_class_note',
                        nonce: wecozaClass.nonce,
                        class_id: formData.class_id,
                        note: {
                            id: formData.note_id,
                            content: formData.content,
                            category: formData.category,
                            priority: formData.priority,
                            attachments: uploadedFiles
                        }
                    },
                    success: function (response) {
                        if (response.success) {
                            // Add/update note in collection
                            const noteData = response.data.note;
                            if (formData.note_id) {
                                window.classNotesCollection.update(formData.note_id, noteData);
                            } else {
                                window.classNotesCollection.add(noteData);
                            }

                            // Refresh display
                            refreshNotesDisplay();

                            // Reset form and close modal
                            $noteForm[0].reset();
                            $noteForm.removeClass('was-validated');
                            $charCount.text('0');
                            bootstrap.Modal.getInstance($noteModal[0]).hide();

                            // Show success message
                            showSuccessMessage('Note saved successfully!');

                            // Clear auto-save draft
                            clearAutoSaveDraft('note');
                        } else {
                            showFormError($errorAlert, $errorMessage, response.data || 'Failed to save note');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Note save error:', error);
                        showFormError($errorAlert, $errorMessage, 'An error occurred while saving the note');
                    },
                    complete: function () {
                        setButtonLoading($saveBtn, false);
                    }
                });
            }).catch(error => {
                console.error('File upload error:', error);
                showFormError($errorAlert, $errorMessage, 'Failed to upload files. Please try again.');
                setButtonLoading($saveBtn, false);
            });
        });

        // Handle modal events
        $noteModal.on('show.bs.modal', function (e) {
            const $trigger = $(e.relatedTarget);
            const noteId = $trigger.data('note-id');

            if (noteId) {
                // Edit mode - load note data
                const note = window.classNotesCollection.find(noteId);
                if (note) {
                    $('#note-modal-title').text('Edit Class Note');
                    $('#note_id').val(note.id);
                    $('#note_content').val(note.content);
                    $('#class_notes').val(Array.isArray(note.category) ? note.category : [note.category]);
                    $('#note_priority').val(note.priority);
                    $charCount.text(note.content.length);
                }
            }
            // Form clearing is handled by hidden.bs.modal event
        });

        $noteModal.on('hidden.bs.modal', function () {
            // Reset form
            $noteForm[0].reset();
            $noteForm.removeClass('was-validated');
            $('#note_id').val('');
            $charCount.text('0');
            $errorAlert.addClass('d-none');

            // Fix accessibility: Make buttons non-focusable when modal is hidden
            $noteModal.find('button, input, select, textarea, [tabindex]:not([tabindex="-1"])').attr('tabindex', '-1');
        });

        $noteModal.on('show.bs.modal', function () {
            // Fix accessibility: Restore focusability when modal is shown
            $noteModal.find('[tabindex="-1"]').removeAttr('tabindex');
        });

        // Handle edit/delete buttons
        $(document).on('click', '.edit-note', function (e) {
            e.preventDefault();
            const noteId = $(this).data('note-id');
            $('#classNoteModal').modal('show');
            // The show.bs.modal event will handle loading the note data
        });

        $(document).on('click', '.delete-note', function (e) {
            e.preventDefault();
            const noteId = $(this).data('note-id');
            const note = window.classNotesCollection.find(noteId);

            if (note && confirm(`Are you sure you want to delete this note?`)) {
                deleteNote(noteId);
            }
        });
    }

    /**
     * Initialize QA form with validation
     */
    function initializeQAForm() {
        const $qaForm = $('#qa-form');
        const $qaModal = $('#qaFormModal');
        const $submitBtn = $('#submit-question-btn');
        const $errorAlert = $('#qa-error-alert');
        const $errorMessage = $('#qa-error-message');

        // Form submission
        $qaForm.on('submit', function (e) {
            e.preventDefault();

            // Reset validation states
            $qaForm.removeClass('was-validated');
            $errorAlert.addClass('d-none');

            // Validate form
            if (!this.checkValidity()) {
                e.stopPropagation();
                $qaForm.addClass('was-validated');
                return;
            }

            // Create FormData for file upload
            const formData = new FormData(this);
            formData.append('action', 'submit_qa_question');
            formData.append('nonce', wecozaClass.nonce);

            // Validate file size
            const fileInput = $('#qa_attachment')[0];
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                if (file.size > 5 * 1024 * 1024) { // 5MB
                    showFormError($errorAlert, $errorMessage, 'File size must be less than 5MB');
                    return;
                }
            }

            // Show loading state
            setButtonLoading($submitBtn, true);

            // Submit via AJAX
            $.ajax({
                url: wecozaClass.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Reset form and close modal
                        $qaForm[0].reset();
                        $qaForm.removeClass('was-validated');
                        bootstrap.Modal.getInstance($qaModal[0]).hide();

                        // Show success message
                        showSuccessMessage('Question submitted successfully!');

                        // Clear auto-save draft
                        clearAutoSaveDraft('qa');

                        // Optionally reload QA data
                        const classId = $('#qa_class_id').val();
                        if (classId) {
                            loadQAVisits(classId);
                        }
                    } else {
                        showFormError($errorAlert, $errorMessage, response.data || 'Failed to submit question');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('QA submit error:', error);
                    showFormError($errorAlert, $errorMessage, 'An error occurred while submitting the question');
                },
                complete: function () {
                    setButtonLoading($submitBtn, false);
                }
            });
        });

        // Handle modal events
        $qaModal.on('show.bs.modal', function () {
            // Restore draft if available
            restoreAutoSaveDraft('qa');
        });

        $qaModal.on('hidden.bs.modal', function () {
            // Reset form
            $qaForm[0].reset();
            $qaForm.removeClass('was-validated');
            $errorAlert.addClass('d-none');

            // Fix accessibility: Make buttons non-focusable when modal is hidden
            $qaModal.find('button, input, select, textarea, [tabindex]:not([tabindex="-1"])').attr('tabindex', '-1');
        });

        $qaModal.on('show.bs.modal', function () {
            // Fix accessibility: Restore focusability when modal is shown
            $qaModal.find('[tabindex="-1"]').removeAttr('tabindex');
        });
    }

    /**
     * Initialize auto-save functionality
     */
    function initializeAutoSave() {
        let autoSaveTimers = {};
        const autoSaveDelay = 3000; // 3 seconds

        // Auto-save for note form
        $('#note_content, #class_notes, #note_priority').on('input change', function () {
            clearTimeout(autoSaveTimers.note);
            autoSaveTimers.note = setTimeout(() => {
                saveFormDraft('note', {
                    content: $('#note_content').val(),
                    category: $('#class_notes').val(),
                    priority: $('#note_priority').val()
                });
            }, autoSaveDelay);
        });

        // Auto-save for QA form
        $('#qa_question, #qa_context').on('input', function () {
            clearTimeout(autoSaveTimers.qa);
            autoSaveTimers.qa = setTimeout(() => {
                saveFormDraft('qa', {
                    question: $('#qa_question').val(),
                    context: $('#qa_context').val()
                });
            }, autoSaveDelay);
        });
    }

    /**
     * Validate note data
     */
    function validateNoteData(data) {
        const errors = [];

        if (!data.content || data.content.length < 10) {
            errors.push('Content must be at least 10 characters long');
        }

        if (!data.category || (Array.isArray(data.category) && data.category.length === 0)) {
            errors.push('Please select at least one class note type');
        }

        if (!data.priority || data.priority === '') {
            errors.push('Please select a priority level');
        }

        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Delete a note
     */
    function deleteNote(noteId) {
        const classId = $('#note_class_id').val() || $('#class_id').val();

        $.ajax({
            url: wecozaClass.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_class_note',
                nonce: wecozaClass.nonce,
                class_id: classId,
                note_id: noteId
            },
            success: function (response) {
                if (response.success) {
                    // Remove from collection
                    window.classNotesCollection.remove(noteId);

                    // Refresh display
                    refreshNotesDisplay();

                    // Show success message
                    showSuccessMessage('Note deleted successfully!');
                } else {
                    showErrorMessage(response.data || 'Failed to delete note');
                }
            },
            error: function (xhr, status, error) {
                console.error('Note delete error:', error);
                showErrorMessage('An error occurred while deleting the note');
            }
        });
    }

    /**
     * Save form draft to localStorage
     */
    function saveFormDraft(formType, data) {
        try {
            const key = `wecoza_${formType}_draft`;
            localStorage.setItem(key, JSON.stringify({
                data: data,
                timestamp: new Date().toISOString()
            }));

            // Show auto-save indicator
            if (formType === 'note') {
                $('#auto-save-indicator').removeClass('d-none');
                $('#auto-save-message').text('Draft saved');
                setTimeout(() => {
                    $('#auto-save-indicator').addClass('d-none');
                }, 2000);
            }
        } catch (e) {
            console.error('Failed to save draft:', e);
        }
    }

    /**
     * Restore form draft from localStorage
     */
    function restoreAutoSaveDraft(formType) {
        try {
            const key = `wecoza_${formType}_draft`;
            const draft = localStorage.getItem(key);

            if (draft) {
                const { data, timestamp } = JSON.parse(draft);

                // Check if draft is less than 24 hours old
                const draftAge = new Date() - new Date(timestamp);
                if (draftAge < 24 * 60 * 60 * 1000) {
                    if (formType === 'note') {
                        $('#note_content').val(data.content || '');
                        $('#class_notes').val(data.category || []);
                        $('#note_priority').val(data.priority || '');
                        $('#note-char-count').text((data.content || '').length);

                        // Show indicator
                        $('#auto-save-indicator').removeClass('d-none');
                        $('#auto-save-message').text('Draft restored');
                        setTimeout(() => {
                            $('#auto-save-indicator').addClass('d-none');
                        }, 3000);
                    } else if (formType === 'qa') {
                        $('#qa_question').val(data.question || '');
                        $('#qa_context').val(data.context || '');
                    }
                }
            }
        } catch (e) {
            console.error('Failed to restore draft:', e);
        }
    }

    /**
     * Clear auto-save draft
     */
    function clearAutoSaveDraft(formType) {
        try {
            const key = `wecoza_${formType}_draft`;
            localStorage.removeItem(key);
        } catch (e) {
            console.error('Failed to clear draft:', e);
        }
    }

    /**
     * Show form error
     */
    function showFormError($alert, $message, error) {
        $message.text(error);
        $alert.removeClass('d-none');
    }

    /**
     * Set button loading state
     */
    function setButtonLoading($button, loading) {
        if (loading) {
            $button.prop('disabled', true);
            $button.find('.btn-text').addClass('d-none');
            $button.find('.spinner-border').removeClass('d-none');
        } else {
            $button.prop('disabled', false);
            $button.find('.btn-text').removeClass('d-none');
            $button.find('.spinner-border').addClass('d-none');
        }
    }

    /**
     * Initialize file upload functionality with drag-and-drop
     */
    function initializeFileUpload() {
        const $dropzone = $('#note-dropzone');
        const $fileInput = $('#note-file-input');
        const $browseBtn = $('#browse-files-btn');
        const $fileList = $('#note-file-list');
        const $uploadProgress = $('#upload-progress');
        const $progressBar = $uploadProgress.find('.progress-bar');
        const $uploadStatus = $('#upload-status');

        // File management
        let pendingFiles = [];
        let uploadedFiles = [];
        let isFileInputTriggering = false; // Flag to prevent recursive clicks
        const maxFileSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = ['application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg', 'image/png'];

        // Browse button click - unbind first to prevent duplicate handlers
        $browseBtn.off('click.fileupload').on('click.fileupload', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (!isFileInputTriggering) {
                isFileInputTriggering = true;
                setTimeout(() => {
                    $fileInput[0].click(); // Use native DOM click instead of jQuery
                    isFileInputTriggering = false;
                }, 0);
            }
        });

        // File input change - unbind first to prevent duplicate handlers
        $fileInput.off('change.fileupload').on('change.fileupload', function (e) {
            handleFiles(e.target.files);
            this.value = ''; // Reset input
        });

        // Drag and drop events - unbind first to prevent duplicate handlers
        $dropzone.off('dragover.fileupload dragenter.fileupload').on('dragover.fileupload dragenter.fileupload', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        $dropzone.off('dragleave.fileupload dragend.fileupload').on('dragleave.fileupload dragend.fileupload', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        $dropzone.off('drop.fileupload').on('drop.fileupload', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');

            const files = e.originalEvent.dataTransfer.files;
            handleFiles(files);
        });

        // Click to upload - unbind first to prevent duplicate handlers
        $dropzone.off('click.fileupload').on('click.fileupload', function (e) {
            if (!$(e.target).is('button') && !$(e.target).closest('button').length && !isFileInputTriggering) {
                e.preventDefault();
                e.stopImmediatePropagation();
                isFileInputTriggering = true;
                setTimeout(() => {
                    $fileInput[0].click(); // Use native DOM click instead of jQuery
                    isFileInputTriggering = false;
                }, 0);
            }
        });

        /**
         * Handle dropped or selected files
         */
        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (validateFile(file)) {
                    addFileToList(file);
                    pendingFiles.push(file);
                }
            });
        }

        /**
         * Validate file type and size
         */
        function validateFile(file) {
            // Check file type
            if (!allowedTypes.includes(file.type)) {
                showErrorMessage(`Invalid file type: ${file.name}. Please upload PDF, DOC, DOCX, XLS, XLSX, JPG, or PNG files.`);
                return false;
            }

            // Check file size
            if (file.size > maxFileSize) {
                showErrorMessage(`File too large: ${file.name}. Maximum file size is 10MB.`);
                return false;
            }

            // Check if file already added
            const exists = [...pendingFiles, ...uploadedFiles].some(f =>
                f.name === file.name && f.size === file.size
            );

            if (exists) {
                showErrorMessage(`File already added: ${file.name}`);
                return false;
            }

            return true;
        }

        /**
         * Add file to the display list
         */
        function addFileToList(file) {
            const fileId = 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const fileIcon = getFileIcon(file.type);
            const fileSize = formatFileSize(file.size);

            const fileHtml = `
                <div class="file-item" id="${fileId}" data-file-name="${file.name}">
                    <i class="${fileIcon} file-icon"></i>
                    <div class="file-info">
                        <div class="file-name">${escapeHtml(file.name)}</div>
                        <div class="file-size">${fileSize}</div>
                    </div>
                    <div class="file-progress d-none">
                        <div class="progress progress-sm">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-file-btn" data-file-id="${fileId}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;

            $fileList.append(fileHtml);

            // Store file reference
            file.elementId = fileId;
        }

        /**
         * Get file icon based on type
         */
        function getFileIcon(mimeType) {
            if (mimeType.startsWith('image/')) return 'bi bi-file-image';
            if (mimeType.includes('pdf')) return 'bi bi-file-pdf';
            if (mimeType.includes('word')) return 'bi bi-file-word';
            if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'bi bi-file-excel';
            return 'bi bi-file-earmark';
        }

        /**
         * Format file size
         */
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        /**
         * Remove file from list
         */
        $(document).on('click', '.remove-file-btn', function () {
            const fileId = $(this).data('file-id');
            const $fileItem = $('#' + fileId);

            // Remove from pending files
            pendingFiles = pendingFiles.filter(f => f.elementId !== fileId);

            // Remove from uploaded files
            uploadedFiles = uploadedFiles.filter(f => f.elementId !== fileId);

            // Remove from DOM
            $fileItem.fadeOut(300, function () {
                $(this).remove();
            });
        });

        /**
         * Upload files to WordPress media library
         */
        window.uploadNotesFiles = function () {
            // Get existing attachments from uploadedFiles array
            const existingAttachments = uploadedFiles.filter(file => file.isExisting);

            if (pendingFiles.length === 0) {
                // Return existing attachments if no new files to upload
                return Promise.resolve(existingAttachments);
            }

            return new Promise((resolve, reject) => {
                const totalFiles = pendingFiles.length;
                let uploadedCount = 0;
                const results = [];

                $uploadProgress.removeClass('d-none');
                $uploadStatus.text(`Uploading ${totalFiles} file(s)...`);

                // Upload files sequentially
                const uploadNext = () => {
                    if (pendingFiles.length === 0) {
                        $uploadProgress.addClass('d-none');
                        uploadedFiles.push(...results);
                        // Combine new uploads with existing attachments
                        const allAttachments = [...existingAttachments, ...results];
                        resolve(allAttachments);
                        return;
                    }

                    const file = pendingFiles.shift();
                    uploadFile(file)
                        .then(result => {
                            uploadedCount++;
                            results.push(result);

                            // Update progress
                            const progress = (uploadedCount / totalFiles) * 100;
                            $progressBar.css('width', progress + '%');
                            $uploadStatus.text(`Uploaded ${uploadedCount} of ${totalFiles} files`);

                            // Mark file as uploaded
                            $('#' + file.elementId).removeClass('uploading').find('.file-progress').addClass('d-none');

                            uploadNext();
                        })
                        .catch(error => {
                            console.error('File upload error:', error);
                            $('#' + file.elementId).addClass('error');
                            // Continue with other files
                            uploadNext();
                        });
                };

                uploadNext();
            });
        };

        /**
         * Upload single file to WordPress
         */
        function uploadFile(file) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'upload_attachment');
                formData.append('nonce', wecozaClass.nonce);
                formData.append('file', file);
                formData.append('context', 'class_notes');

                // Mark file as uploading
                const $fileItem = $('#' + file.elementId);
                $fileItem.addClass('uploading');
                $fileItem.find('.file-progress').removeClass('d-none');

                $.ajax({
                    url: wecozaClass.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function () {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (e) {
                            if (e.lengthComputable) {
                                const percentComplete = (e.loaded / e.total) * 100;
                                $fileItem.find('.progress-bar').css('width', percentComplete + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function (response) {
                        if (response.success) {
                            resolve({
                                id: response.data.id,
                                url: response.data.url,
                                name: file.name,
                                size: file.size,
                                type: file.type
                            });
                        } else {
                            reject(response.data || 'Upload failed');
                        }
                    },
                    error: function (xhr, status, error) {
                        reject(error);
                    }
                });
            });
        }

        // Reset files when modal is closed
        $('#classNoteModal').on('hidden.bs.modal', function () {
            pendingFiles = [];
            uploadedFiles = [];
            $fileList.empty();
            $uploadProgress.addClass('d-none');
            $progressBar.css('width', '0%');
        });

        // Make uploadedFiles accessible for form submission
        window.getUploadedFiles = function () {
            return uploadedFiles;
        };
    }

    // Initialize when document is ready
    $(document).ready(function () {
        // Check if we're on a page with the class capture form
        if ($('#classes-form').length > 0) {
            initClassCaptureForm();
            initializeDataLoading();
            initializeFormProcessing();
            initializeNotesDisplay();

            // Ensure proper initial display state
            setTimeout(function () {
                if (window.classNotesCollection) {
                    refreshNotesDisplay();
                }
            }, 100);
        }
    });

})(jQuery);

/**
 * Class Notes and QA Data Models
 * Provides Collection class for managing notes data
 */
window.ClassNotesQAModels = (function () {
    'use strict';

    /**
     * Note data model
     */
    function Note(data) {
        this.id = data.id || null;
        this.content = data.content || '';
        this.category = data.category || [];
        this.priority = data.priority || '';
        this.attachments = data.attachments || [];
        this.created_at = data.created_at || new Date().toISOString();
        this.updated_at = data.updated_at || new Date().toISOString();
        this.class_id = data.class_id || null;
        this.user_id = data.user_id || null;
    }

    /**
     * Collection class for managing arrays of data with pagination, sorting, and filtering
     */
    function Collection(ModelClass) {
        this.ModelClass = ModelClass;
        this.items = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.sortBy = 'created_at';
        this.sortOrder = 'desc';
        this.filters = {};
    }

    /**
     * Add item to collection
     */
    Collection.prototype.add = function (data) {
        const item = new this.ModelClass(data);
        this.items.push(item);
        return item;
    };

    /**
     * Find item by ID
     */
    Collection.prototype.find = function (id) {
        return this.items.find(item => item.id == id);
    };

    /**
     * Remove item by ID
     */
    Collection.prototype.remove = function (id) {
        const index = this.items.findIndex(item => item.id == id);
        if (index > -1) {
            this.items.splice(index, 1);
            return true;
        }
        return false;
    };

    /**
     * Update item by ID
     */
    Collection.prototype.update = function (id, data) {
        const item = this.find(id);
        if (item) {
            Object.assign(item, data);
            return item;
        }
        return null;
    };


    /**
     * Set filter
     */
    Collection.prototype.setFilter = function (key, value) {
        if (value === '' || value === null || value === undefined) {
            delete this.filters[key];
        } else {
            this.filters[key] = value;
        }
        this.currentPage = 1; // Reset to first page when filter changes
    };

    /**
     * Set sort criteria
     */
    Collection.prototype.setSort = function (field, order) {
        this.sortBy = field;
        this.sortOrder = order;
        this.currentPage = 1; // Reset to first page when sort changes
    };

    /**
     * Set page number
     */
    Collection.prototype.setPage = function (page) {
        this.currentPage = page;
    };

    /**
     * Get filtered items based on search and filters
     */
    Collection.prototype.getFiltered = function () {
        let filtered = [...this.items];


        // Apply filters
        Object.keys(this.filters).forEach(key => {
            const filterValue = this.filters[key];

            if (key === 'priority') {
                filtered = filtered.filter(item => item.priority === filterValue);
            }
        });

        // Apply sorting
        filtered.sort((a, b) => {
            let aValue = a[this.sortBy];
            let bValue = b[this.sortBy];

            // Handle different data types for sorting
            if (this.sortBy === 'created_at' || this.sortBy === 'updated_at') {
                aValue = new Date(aValue);
                bValue = new Date(bValue);
            } else if (this.sortBy === 'priority') {
                // Priority sorting: high > medium > low
                const priorityOrder = { high: 3, medium: 2, low: 1 };
                aValue = priorityOrder[aValue] || 0;
                bValue = priorityOrder[bValue] || 0;
            } else if (typeof aValue === 'string') {
                aValue = aValue.toLowerCase();
                bValue = bValue.toLowerCase();
            }

            if (this.sortOrder === 'desc') {
                return bValue > aValue ? 1 : bValue < aValue ? -1 : 0;
            } else {
                return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
            }
        });

        return filtered;
    };

    /**
     * Get paginated data
     */
    Collection.prototype.getPaginated = function () {
        const filtered = this.getFiltered();
        const totalItems = filtered.length;
        const totalPages = Math.ceil(totalItems / this.itemsPerPage);

        // Ensure current page is within bounds
        if (this.currentPage > totalPages && totalPages > 0) {
            this.currentPage = totalPages;
        }
        if (this.currentPage < 1) {
            this.currentPage = 1;
        }

        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const items = filtered.slice(startIndex, endIndex);

        return {
            items: items,
            currentPage: this.currentPage,
            totalPages: totalPages,
            totalItems: totalItems,
            itemsPerPage: this.itemsPerPage,
            hasNext: this.currentPage < totalPages,
            hasPrev: this.currentPage > 1
        };
    };



    /**
     * Clear all data
     */
    Collection.prototype.clear = function () {
        this.items = [];
        this.currentPage = 1;
        this.filters = {};
    };

    // Return public API
    return {
        Note: Note,
        Collection: Collection
    };
})();
