/**
 * Class Schedule Form JavaScript for WeCoza Classes Plugin
 *
 * Handles the client-side functionality for the class schedule form.
 * Extracted from WeCoza theme for standalone plugin
 */
(function ($) {
    'use strict';

    // Initialize holidayOverrides object globally to prevent undefined errors
    if (typeof window.holidayOverrides !== 'object' || window.holidayOverrides === null) {
        window.holidayOverrides = {};
    }

    // Store completed events separately (not rendered in form, but shown in statistics)
    let completedEvents = [];

    /**
     * Initialize the class schedule form
     */
    function initClassScheduleForm() {
        // Don't set default values - let user choose

        // Don't set default pattern - let user choose

        // Initialize schedule pattern selection
        initSchedulePatternSelection();

        // Initialize time selection and duration calculation
        initTimeSelection();

        // Initialize time validation for existing data
        validateAllTimeSelections();

        // Initialize date fields
        initDateFields();

        // Initialize per-day time controls based on current selection
        updatePerDayTimeControls();

        // Initialize exception dates
        initExceptionDates();

        // Initialize event dates
        initEventDates();

        // Initialize date history (stop/restart dates)
        initDateHistory();

        // Initialize holiday overrides
        initHolidayOverrides();

        // Initialize schedule data updates
        initScheduleDataUpdates();

        // Initialize exam class toggle functionality
        initExamClassToggle();

        // Initialize learner selection functionality
        initLearnerSelection();

        // Initialize auto level population on subject change
        initSubjectChangeLevelPopulation();

        // Initialize backup agents functionality
        initBackupAgents();

        // Initialize manual end date calculation button
        initManualEndDateCalculation();

        // Load existing schedule data for editing (backward compatibility)
        loadExistingScheduleData();

        // Initial auto-population after all initialization is complete
        setTimeout(function () {
            // Auto-populate schedule start date if class start date exists but schedule start date is empty
            initAutoPopulateScheduleStartDate();

            // Don't auto-fill schedule start date - let user choose

            // Check for holidays on initial load
            const startDate = $('#schedule_start_date').val();
            const endDate = $('#schedule_end_date').val();
            if (startDate) {
                checkForHolidays(startDate, endDate);
            }

            // Auto-calculate end date if we have duration and start date
            const duration = $('#class_duration').val();
            if (startDate && duration && !endDate) {
                recalculateEndDate();
            }

            // Don't set default pattern - let user choose

            // Don't auto-select any days - let user choose

            // Ensure updateScheduleData runs after fields are populated
            setTimeout(function () {
                updateScheduleData();
            }, 200);
        }, 100);
    }

    /**
     * Initialize auto-population of schedule start date on page load
     */
    function initAutoPopulateScheduleStartDate() {


        // Don't auto-populate schedule start date - let user choose
        // User can manually copy from class start date if needed

        // Don't auto-update schedule_start_date when class_start_date changes
        // Let user manually set schedule start date
    }

    /**
     * Initialize manual end date calculation button
     */
    function initManualEndDateCalculation() {
        // Add click event handler for the manual end date calculation button
        $('#calculate_schedule_end_date-btn').on('click', function () {
            // Call the existing recalculateEndDate function when button is clicked
            recalculateEndDate();
        });
    }

    /**
     * Initialize schedule pattern selection
     */
    function initSchedulePatternSelection() {
        const $schedulePattern = $('#schedule_pattern');
        const $daySelection = $('#day-selection-container');
        const $dayOfMonth = $('#day-of-month-container');

        $schedulePattern.on('change', function () {
            const pattern = $(this).val();

            // Reset visibility
            $daySelection.addClass('d-none');
            $dayOfMonth.addClass('d-none');

            // Show appropriate fields based on pattern
            if (pattern === 'weekly' || pattern === 'biweekly') {
                $daySelection.removeClass('d-none');
                $('#schedule_day_of_month').removeAttr('required');

                // Update per-day time controls based on current day selection
                updatePerDayTimeControls();
            } else if (pattern === 'monthly') {
                $dayOfMonth.removeClass('d-none');
                $('#schedule_day_of_month').attr('required', 'required');

                // For monthly pattern, always show single time controls
                // since monthly scheduling doesn't use multiple days
                resetToSingleTimeControls();
            } else {
                // For custom or no pattern, reset to single time controls
                resetToSingleTimeControls();
            }

            // Update schedule data
            updateScheduleData();

            // Check for holidays that conflict with the new pattern
            const startDate = $('#schedule_start_date').val();
            const endDate = $('#schedule_end_date').val();
            if (startDate) {
                checkForHolidays(startDate, endDate);
            }
        });

        // Initialize day selection buttons
        $('#select-all-days').on('click', function () {
            $('.schedule-day-checkbox').prop('checked', true);
            validateDaySelection(); // Update required attribute only
            updatePerDayTimeControls(); // Add conditional display logic
            updateScheduleData();
            restrictStartDateBySelectedDays();
        });

        $('#clear-all-days').on('click', function () {
            $('.schedule-day-checkbox').prop('checked', false);
            validateDaySelection(); // Update required attribute only
            updatePerDayTimeControls(); // Add conditional display logic
            updateScheduleData();
        });

        // Handle day checkbox changes - using event delegation in case checkboxes are loaded dynamically
        $(document).on('change', '.schedule-day-checkbox', function () {
            validateDaySelection(); // Update required attribute only
            updatePerDayTimeControls(); // Add conditional display logic
            updateScheduleData();
            restrictStartDateBySelectedDays();

            // Check for holidays that conflict with the new day selection
            const startDate = $('#schedule_start_date').val();
            const endDate = $('#schedule_end_date').val();
            if (startDate) {
                checkForHolidays(startDate, endDate);
            }
        });

        // Handle day of month selection changes
        $('#schedule_day_of_month').on('change', function () {
            updateScheduleData();

            // Check for holidays that conflict with the new day selection
            const startDate = $('#schedule_start_date').val();
            const endDate = $('#schedule_end_date').val();
            if (startDate) {
                checkForHolidays(startDate, endDate);
            }
        });

        // Trigger initial change to set correct visibility based on current pattern
        $schedulePattern.trigger('change');
    }

    /**
     * Validate that at least one day is selected
     * Returns validation result without showing immediate feedback
     */
    function validateDaySelection() {
        const anyDaySelected = $('.schedule-day-checkbox:checked').length > 0;
        const $daySelectionContainer = $('#day-selection-container');

        // Manage required attribute on first checkbox for Bootstrap validation
        if (!$daySelectionContainer.hasClass('d-none')) {
            const $firstCheckbox = $('.schedule-day-checkbox').first();
            if ($firstCheckbox.length > 0) {
                if (anyDaySelected) {
                    // Remove required attribute when any day is selected
                    $firstCheckbox.removeAttr('required');
                    $firstCheckbox[0].setCustomValidity('');
                } else {
                    // Add required attribute when no days are selected
                    $firstCheckbox.attr('required', 'required');
                    $firstCheckbox[0].setCustomValidity('Please select at least one day.');
                }
            }
        }

        return anyDaySelected;
    }

    /**
     * Show validation feedback for day selection when form is validated
     */
    function showDaySelectionValidationFeedback() {
        const anyDaySelected = $('.schedule-day-checkbox:checked').length > 0;
        const $daySelectionContainer = $('#day-selection-container');

        if (!$daySelectionContainer.hasClass('d-none')) {
            const $invalidFeedback = $daySelectionContainer.find('.invalid-feedback');
            const $validFeedback = $daySelectionContainer.find('.valid-feedback');

            if (anyDaySelected) {
                // Show valid feedback, hide invalid feedback
                $invalidFeedback.addClass('d-none');
                $validFeedback.removeClass('d-none');
            } else {
                // Show invalid feedback, hide valid feedback
                $invalidFeedback.removeClass('d-none');
                $validFeedback.addClass('d-none');
            }
        }
    }

    /**
     * Get all selected days
     */
    function getSelectedDays() {
        const selectedDays = [];
        $('.schedule-day-checkbox:checked').each(function () {
            selectedDays.push($(this).val());
        });
        return selectedDays;
    }

    /**
     * Date utility aliases - delegate to WeCozaUtils for DRY code
     */
    var getDayName = window.WeCozaUtils ? window.WeCozaUtils.getDayName : function(dayIndex) {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][dayIndex];
    };

    var getDayIndex = window.WeCozaUtils ? window.WeCozaUtils.getDayIndex : function(dayName) {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].indexOf(dayName);
    };

    /**
     * Update per-day time controls based on selected days
     */
    function updatePerDayTimeControls() {
        const selectedDays = getSelectedDays();
        const $singleTimeControls = $('#single-time-controls');
        const $perDayTimeControls = $('#per-day-time-controls');
        const $perDaySectionsContainer = $('#per-day-sections-container');

        // Show/hide controls based on number of selected days
        if (selectedDays.length === 0) {
            // No days selected: hide both controls
            $singleTimeControls.addClass('d-none');
            $perDayTimeControls.addClass('d-none');

            // Clear per-day sections
            $perDaySectionsContainer.empty();
        } else {
            // Any days selected: show per-day time controls (even for single day)
            $singleTimeControls.addClass('d-none');
            $perDayTimeControls.removeClass('d-none');

            // Generate per-day sections
            generatePerDaySections(selectedDays);
        }
    }

    /**
     * Generate per-day time sections for selected days
     */
    function generatePerDaySections(selectedDays) {
        const $container = $('#per-day-sections-container');
        const $template = $('#day-time-section-template');

        // Clear existing sections
        $container.empty();

        // Create section for each selected day
        selectedDays.forEach(function (day, index) {
            const $section = $template.clone();
            $section.removeClass('d-none').removeAttr('id');
            $section.attr('data-day', day);

            // Update day name in header
            $section.find('.day-name').text(day);

            // Update data-day attributes for form elements
            $section.find('.day-start-time').attr('data-day', day).attr('name', 'day_start_time[' + day + ']');
            $section.find('.day-end-time').attr('data-day', day).attr('name', 'day_end_time[' + day + ']');

            // Don't set default times - let user choose

            // Show copy button only on first day
            if (index === 0) {
                $section.find('.copy-to-all-btn').show();
            } else {
                $section.find('.copy-to-all-btn').hide();
            }

            $container.append($section);
        });

        // Initialize event handlers for new sections
        initPerDayTimeHandlers();

        // Initialize time selection for each new section
        initPerDayTimeSections();

        // Initialize validation indicators
        setTimeout(() => {
            updateTimeValidationIndicators();
        }, 100);
    }

    /**
     * Initialize time selection for per-day sections
     * Ensures each section has proper time selection behavior
     */
    function initPerDayTimeSections() {
        $('.per-day-time-section').each(function () {
            const day = $(this).attr('data-day');
            const $startTime = $(this).find('.day-start-time');
            const $endTime = $(this).find('.day-end-time');
            const $durationContainer = $(this).find('.day-duration-display');

            // Initially hide duration display
            $durationContainer.addClass('d-none');

            // Calculate initial duration if times are already set
            if ($startTime.val() && $endTime.val()) {
                calculatePerDayDuration(day);
            }
        });
    }

    /**
     * Initialize event handlers for per-day time controls
     * Enhanced to use unified time validation and duration calculation
     */
    function initPerDayTimeHandlers() {
        // Handle time changes for validation and duration calculation
        $('.day-start-time, .day-end-time').off('change.perday').on('change.perday', function () {
            const day = $(this).attr('data-day');
            const $section = $('.per-day-time-section[data-day="' + day + '"]');
            const $startTime = $section.find('.day-start-time');
            const $endTime = $section.find('.day-end-time');

            // Validate individual time selection
            validateTimeSelection($startTime, $endTime);

            // Calculate duration using unified calculation
            calculatePerDayDuration(day);

            // Perform comprehensive validation across all days
            setTimeout(() => {
                validateAllTimeSelections();
                updateTimeValidationIndicators();
            }, 100); // Small delay to ensure all DOM updates are complete

            // Update schedule data
            updateScheduleData();
        });

        // Handle copy to all days functionality
        $('.copy-to-all-btn').off('click.perday').on('click.perday', function () {
            const $section = $(this).closest('.per-day-time-section');
            const startTime = $section.find('.day-start-time').val();
            const endTime = $section.find('.day-end-time').val();

            if (startTime && endTime) {
                // Validate source times first
                const $startTimeElement = $section.find('.day-start-time');
                const $endTimeElement = $section.find('.day-end-time');

                if (validateTimeSelection($startTimeElement, $endTimeElement)) {
                    // Copy times to all other day sections
                    $('.per-day-time-section').not($section).each(function () {
                        const $targetStartTime = $(this).find('.day-start-time');
                        const $targetEndTime = $(this).find('.day-end-time');

                        $targetStartTime.val(startTime);
                        $targetEndTime.val(endTime);

                        // Trigger change events to update validation and duration
                        $targetStartTime.trigger('change');
                        $targetEndTime.trigger('change');
                    });
                }
            }
        });
    }

    /**
     * Calculate duration for a specific day using unified calculation
     * Only shows duration display when both time fields are valid
     */
    function calculatePerDayDuration(day) {
        const $section = $('.per-day-time-section[data-day="' + day + '"]');
        const startTime = $section.find('.day-start-time').val();
        const endTime = $section.find('.day-end-time').val();
        const $durationDisplay = $section.find('.duration-value');
        const $durationContainer = $section.find('.day-duration-display');
        const $startTimeElement = $section.find('.day-start-time');
        const $endTimeElement = $section.find('.day-end-time');

        if (startTime && endTime) {
            // Validate the time selection first
            const isValid = validateTimeSelection($startTimeElement, $endTimeElement);

            if (isValid) {
                // Use unified duration calculation
                const duration = calculateTimeDuration(startTime, endTime);
                $durationDisplay.text(duration.toFixed(1));

                // Show duration display only when valid
                $durationContainer.removeClass('d-none');
            } else {
                // Hide duration display when invalid
                $durationContainer.addClass('d-none');
            }
        } else {
            // Hide duration display when fields are empty
            $durationContainer.addClass('d-none');
            $durationDisplay.text('-');
        }
    }

    /**
     * Reset to no time controls (for monthly/custom patterns)
     */
    function resetToSingleTimeControls() {
        const $singleTimeControls = $('#single-time-controls');
        const $perDayTimeControls = $('#per-day-time-controls');
        const $perDaySectionsContainer = $('#per-day-sections-container');

        // Hide both time control sections
        $singleTimeControls.addClass('d-none');
        $perDayTimeControls.addClass('d-none');

        // Clear per-day sections
        $perDaySectionsContainer.empty();
    }

    /**
     * Initialize time selection and duration calculation
     * Enhanced to work with both single-day and per-day time controls
     */
    function initTimeSelection() {
        const $startTime = $('#schedule_start_time');
        const $endTime = $('#schedule_end_time');
        const $duration = $('#schedule_duration');

        // Only initialize single time controls if the fields exist
        if ($startTime.length && $endTime.length) {
            // Initialize single time controls
            initSingleTimeControls($startTime, $endTime, $duration);

            // Calculate initial duration on page load for existing data
            if ($duration.length) {
                calculateSingleDuration($startTime, $endTime, $duration);
            }
        }

        // Initialize per-day time controls (will be called when sections are generated)
        // This is handled by initPerDayTimeHandlers() but we ensure consistency here
    }

    /**
     * Initialize single time controls with validation and duration calculation
     */
    function initSingleTimeControls($startTime, $endTime, $duration) {
        // Calculate duration when times change
        $startTime.add($endTime).on('change', function () {
            // Validate time selection
            validateTimeSelection($startTime, $endTime);

            // Calculate duration
            calculateSingleDuration($startTime, $endTime, $duration);

            // Update visual indicators
            setTimeout(() => {
                updateTimeValidationIndicators();
            }, 50);

            // Update schedule data
            updateScheduleData();
        });
    }

    /**
     * Calculate duration for single time controls
     */
    function calculateSingleDuration($startTime, $endTime, $duration) {
        const startTime = $startTime.val();
        const endTime = $endTime.val();

        if ($duration && $duration.length) {
            if (startTime && endTime) {
                const calculatedDuration = calculateTimeDuration(startTime, endTime);
                $duration.val(calculatedDuration.toFixed(1));
            } else {
                $duration.val('');
            }
        }
    }

    /**
     * Unified time duration calculation function
     * Used by both single and per-day time controls
     */
    function calculateTimeDuration(startTime, endTime) {
        if (!startTime || !endTime) {
            return 0;
        }

        // Parse times
        const [startHour, startMinute] = startTime.split(':').map(Number);
        const [endHour, endMinute] = endTime.split(':').map(Number);

        // Calculate duration in hours
        let durationHours = endHour - startHour;
        let durationMinutes = endMinute - startMinute;

        if (durationMinutes < 0) {
            durationHours--;
            durationMinutes += 60;
        }

        // Return duration as decimal hours
        return durationHours + (durationMinutes / 60);
    }

    /**
     * Validate time selection (start time must be before end time)
     * Enhanced with additional validation rules
     */
    function validateTimeSelection($startTimeElement, $endTimeElement) {
        const startTime = $startTimeElement.val();
        const endTime = $endTimeElement.val();

        // Clear any existing validation states
        $startTimeElement.removeClass('is-invalid is-valid');
        $endTimeElement.removeClass('is-invalid is-valid');

        if (startTime && endTime) {
            const [startHour, startMinute] = startTime.split(':').map(Number);
            const [endHour, endMinute] = endTime.split(':').map(Number);

            const startMinutes = startHour * 60 + startMinute;
            const endMinutes = endHour * 60 + endMinute;

            // Validation 1: Start time must be before end time
            if (startMinutes >= endMinutes) {
                $endTimeElement.addClass('is-invalid');
                $endTimeElement.siblings('.invalid-feedback').text('End time must be after start time.');
                return false;
            }

            // Validation 2: Minimum duration check (at least 30 minutes)
            const durationMinutes = endMinutes - startMinutes;
            if (durationMinutes < 30) {
                $endTimeElement.addClass('is-invalid');
                $endTimeElement.siblings('.invalid-feedback').text('Class duration must be at least 30 minutes.');
                return false;
            }

            // Validation 3: Maximum duration check (no more than 8 hours)
            if (durationMinutes > 480) { // 8 hours = 480 minutes
                $endTimeElement.addClass('is-invalid');
                $endTimeElement.siblings('.invalid-feedback').text('Class duration cannot exceed 8 hours.');
                return false;
            }

            // All validations passed
            $startTimeElement.addClass('is-valid');
            $endTimeElement.addClass('is-valid');
            $endTimeElement.siblings('.invalid-feedback').text('Please select an end time.');
            return true;
        }

        return true; // If either time is empty, don't show validation error yet
    }

    /**
     * Get all time data from the form (single or per-day)
     * Returns an object with the current time configuration
     */
    function getAllTimeData() {
        const selectedDays = getSelectedDays();

        if (selectedDays.length === 0) {
            // No days selected - return empty single mode
            return {
                mode: 'single',
                startTime: '',
                endTime: '',
                duration: ''
            };
        } else {
            // Any days selected: use per-day time mode (even for single day)
            const perDayTimes = {};
            $('.per-day-time-section').each(function () {
                const day = $(this).attr('data-day');
                const startTime = $(this).find('.day-start-time').val();
                const endTime = $(this).find('.day-end-time').val();

                if (startTime && endTime) {
                    perDayTimes[day] = {
                        startTime: startTime,
                        endTime: endTime,
                        duration: calculateTimeDuration(startTime, endTime)
                    };
                }
            });

            return {
                mode: 'per-day',
                perDayTimes: perDayTimes
            };
        }
    }

    /**
     * Validate all time selections (single or per-day)
     * Enhanced with overlap detection and comprehensive validation
     * Returns true if all times are valid, false otherwise
     */
    function validateAllTimeSelections() {
        const selectedDays = getSelectedDays();
        let allValid = true;

        if (selectedDays.length === 0) {
            // No days selected - no validation needed
            allValid = true;
        } else {
            // Any days selected: validate per-day time controls
            allValid = validatePerDayTimeSelections();
        }

        return allValid;
    }

    /**
     * Comprehensive validation for per-day time selections
     * Includes individual validation and overlap detection
     */
    function validatePerDayTimeSelections() {
        let allValid = true;
        const dayTimes = [];

        // First pass: validate each day individually and collect time data
        $('.per-day-time-section').each(function () {
            const day = $(this).attr('data-day');
            const $startTime = $(this).find('.day-start-time');
            const $endTime = $(this).find('.day-end-time');
            const startTime = $startTime.val();
            const endTime = $endTime.val();

            if (startTime && endTime) {
                // Validate individual day times
                if (!validateTimeSelection($startTime, $endTime)) {
                    allValid = false;
                }

                // Collect time data for overlap detection
                dayTimes.push({
                    day: day,
                    startTime: startTime,
                    endTime: endTime,
                    startMinutes: timeToMinutes(startTime),
                    endMinutes: timeToMinutes(endTime),
                    $section: $(this)
                });
            }
        });

        // Second pass: check for potential scheduling conflicts
        if (allValid && dayTimes.length > 1) {
            allValid = validateTimeConsistency(dayTimes);
        }

        return allValid;
    }

    /**
     * Convert time string to minutes since midnight
     */
    function timeToMinutes(timeStr) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    /**
     * Validate time consistency across multiple days
     * Checks for potential scheduling issues
     */
    function validateTimeConsistency(dayTimes) {
        let allValid = true;
        const warnings = [];

        // Check for extremely different durations that might indicate errors
        const durations = dayTimes.map(dt => dt.endMinutes - dt.startMinutes);
        const minDuration = Math.min(...durations);
        const maxDuration = Math.max(...durations);

        if (maxDuration - minDuration > 120) { // More than 2 hours difference
            warnings.push('Large duration differences between days detected. Please verify times are correct.');
        }

        // Check for very early or very late times that might be errors
        const veryEarly = dayTimes.some(dt => dt.startMinutes < 360); // Before 6:00 AM
        const veryLate = dayTimes.some(dt => dt.endMinutes > 1320); // After 10:00 PM

        if (veryEarly || veryLate) {
            warnings.push('Some classes are scheduled very early or very late. Please verify times are correct.');
        }

        // Display warnings if any
        if (warnings.length > 0) {
            displayTimeValidationWarnings(warnings);
        } else {
            clearTimeValidationWarnings();
        }

        return allValid;
    }

    /**
     * Display time validation warnings
     */
    function displayTimeValidationWarnings(warnings) {
        // Remove any existing warning
        $('#time-validation-warnings').remove();

        // Create warning container
        const warningHtml = `
            <div id="time-validation-warnings" class="alert alert-subtle-warning mt-4 alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Time Validation Warnings:</strong>
                <ul class="mb-0 mt-1">
                    ${warnings.map(warning => `<li>${warning}</li>`).join('')}
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        // Insert after per-day time controls
        $('#per-day-time-controls').after(warningHtml);
    }

    /**
     * Clear time validation warnings
     */
    function clearTimeValidationWarnings() {
        $('#time-validation-warnings').remove();
    }

    /**
     * Validate that all required time fields are filled
     * Returns validation result with details
     */
    function validateRequiredTimeFields() {
        const selectedDays = getSelectedDays();
        const result = {
            isValid: true,
            missingFields: [],
            message: ''
        };

        if (selectedDays.length === 0) {
            // No days selected - no validation needed
            result.isValid = true;
        } else {
            // Any days selected: check each selected day has times
            selectedDays.forEach(day => {
                const $section = $('.per-day-time-section[data-day="' + day + '"]');
                const startTime = $section.find('.day-start-time').val();
                const endTime = $section.find('.day-end-time').val();

                if (!startTime) {
                    result.missingFields.push(`${day} Start Time`);
                    $section.find('.day-start-time').addClass('is-invalid');
                }
                if (!endTime) {
                    result.missingFields.push(`${day} End Time`);
                    $section.find('.day-end-time').addClass('is-invalid');
                }
            });
        }

        if (result.missingFields.length > 0) {
            result.isValid = false;
            result.message = `Please fill in the following required fields: ${result.missingFields.join(', ')}`;
        }

        return result;
    }

    /**
     * Get validation summary for all time inputs
     * Useful for form submission validation
     */
    function getTimeValidationSummary() {
        const requiredFieldsResult = validateRequiredTimeFields();
        const allTimesValid = validateAllTimeSelections();

        return {
            isValid: requiredFieldsResult.isValid && allTimesValid,
            requiredFields: requiredFieldsResult,
            timeValidation: allTimesValid,
            canSubmit: requiredFieldsResult.isValid && allTimesValid
        };
    }

    /**
     * Update visual validation indicators for time controls
     */
    function updateTimeValidationIndicators() {
        const selectedDays = getSelectedDays();

        if (selectedDays.length === 0) {
            // No days selected - no indicators needed
            return;
        } else {
            // Any days selected: update per-day time control indicators
            updatePerDayTimeIndicators();
        }
    }

    /**
     * Update validation indicators for single time controls
     */
    function updateSingleTimeIndicators() {
        const $startTime = $('#schedule_start_time');
        const $endTime = $('#schedule_end_time');
        const $container = $('#single-time-controls');

        // Remove existing status indicators
        $container.find('.time-validation-status').remove();

        // Only show indicators if both fields exist and have values
        if ($startTime.length && $endTime.length && $startTime.val() && $endTime.val()) {
            const isValid = validateTimeSelection($startTime, $endTime);
            const statusClass = isValid ? 'text-success' : 'text-danger';
            const statusIcon = isValid ? 'bi-check-circle' : 'bi-exclamation-circle';
            const statusText = isValid ? 'Times are valid' : 'Please check time selection';

            const statusHtml = `
                <div class="time-validation-status ${statusClass} small mt-1">
                    <i class="bi ${statusIcon} me-1"></i>${statusText}
                </div>
            `;

            $container.append(statusHtml);
        }
    }

    /**
     * Update validation indicators for per-day time controls
     */
    function updatePerDayTimeIndicators() {
        $('.per-day-time-section').each(function () {
            const $section = $(this);
            const day = $section.attr('data-day');
            const $startTime = $section.find('.day-start-time');
            const $endTime = $section.find('.day-end-time');

            // Remove existing status indicators
            $section.find('.time-validation-status').remove();

            if ($startTime.val() && $endTime.val()) {
                const isValid = validateTimeSelection($startTime, $endTime);
                const statusClass = isValid ? 'text-success' : 'text-danger';
                const statusIcon = isValid ? 'bi-check-circle' : 'bi-exclamation-circle';
                const statusText = isValid ? 'Valid' : 'Invalid';

                const statusHtml = `
                    <div class="time-validation-status ${statusClass} small">
                        <i class="bi ${statusIcon} me-1"></i>${statusText}
                    </div>
                `;

                $section.find('.day-duration-display').after(statusHtml);
            }
        });
    }

    /**
     * Initialize date field handling
     */
    function initDateFields() {
        // Initialize date fields
        const $startDate = $('#schedule_start_date');
        const $classType = $('#class_type');
        const $classStartDate = $('#class_start_date');

        // Auto-populate schedule start date when class start date changes
        $classStartDate.on('change', function () {
            const classStartDate = $(this).val();
            if (classStartDate) {
                // Auto-populate schedule start date with the same value
                $startDate.val(classStartDate);

                // Clear any existing validation errors on schedule start date since it's now valid
                $startDate.removeClass('is-invalid');
                $startDate.siblings('.invalid-feedback').text('Please select a start date.');

                // Trigger change event on schedule start date to update dependent calculations
                $startDate.trigger('change');
            }
        });

        // Update end date when start date or class type changes
        $startDate.add($classType).on('change', function () {

            // If start date changed, validate against class original start date
            if ($(this).attr('id') === 'schedule_start_date') {
                const startDate = $(this).val();
                const originalStartDate = $('#class_start_date').val();

                // Validate schedule start date against original start date
                if (startDate && originalStartDate && startDate < originalStartDate) {
                    // Show validation error
                    $(this).addClass('is-invalid');
                    $(this).siblings('.invalid-feedback').text('Start date cannot be before the class original start date');
                } else {
                    // Clear validation error
                    $(this).removeClass('is-invalid');
                    $(this).siblings('.invalid-feedback').text('Please select a start date.');
                }
            }

            updateScheduleData();
        });
    }

    /**
     * Helper function to get class type hours
     */
    function getClassTypeHours(classTypeId) {
        // Use the actual value from class_duration field instead of hard-coded values
        const duration = $('#class_duration').val();


        // Return the parsed float value, or 0 if not set
        return duration ? parseFloat(duration) : 0;
    }

    /**
     * Calculate actual calendar months between two dates
     * More accurate than dividing days by 30
     */
    function calculateActualMonths(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);

        // Calculate the difference in months
        let months = (end.getFullYear() - start.getFullYear()) * 12;
        months += end.getMonth() - start.getMonth();

        // Add partial month if end date is after start date's day
        if (end.getDate() >= start.getDate()) {
            months += 1;
        }

        return Math.max(0, months); // Ensure non-negative result
    }

    /**
     * Initialize exception dates with event delegation
     */
    function initExceptionDates() {
        const $container = $('#exception-dates-container');
        const $template = $('#exception-date-row-template');
        const $addButton = $('#add-exception-date-btn');

        // Set up event delegation for remove buttons (works for all rows, including PHP-populated ones)
        $container.on('click', '.remove-exception-btn', function () {
            console.log('Exception date remove button clicked - event delegation working');
            $(this).closest('.exception-date-row').remove();
            updateScheduleData();
        });

        // Set up event delegation for input/select changes
        $container.on('change', '.exception-date-row input, .exception-date-row select', function () {
            const $row = $(this).closest('.exception-date-row');

            // Validate exception date against class start date
            const exceptionDate = $row.find('input[name="exception_dates[]"]').val();
            const startDate = $('#schedule_start_date').val();

            if (exceptionDate && startDate && exceptionDate < startDate) {
                // Show validation error
                $row.find('input[name="exception_dates[]"]').addClass('is-invalid');
                $row.find('.invalid-feedback').text('Exception date cannot be before the class start date');
            } else {
                // Clear validation error
                $row.find('input[name="exception_dates[]"]').removeClass('is-invalid');
                $row.find('.invalid-feedback').text('Please select a valid date.');
            }

            updateScheduleData();
        });

        // Add exception date row (simplified - no individual event binding needed)
        $addButton.on('click', function () {
            const $newRow = $template.clone();
            $newRow.removeClass('d-none').removeAttr('id');
            $container.append($newRow);

            // Event handlers are automatically available via delegation above
            // No need to manually attach them to new rows
        });
    }

    /**
     * Initialize event dates (deliveries, exams, QA visits, etc.) functionality
     */
    function initEventDates() {
        const $container = $('#event-dates-container');
        const $template = $('#event-date-row-template');
        const $addButton = $('#add-event-date-btn');

        // Check if event dates elements exist
        if ($container.length === 0 || $template.length === 0) {
            return;
        }

        // Set up event delegation for remove buttons
        $container.on('click', '.remove-event-btn', function () {
            $(this).closest('.event-date-row').remove();
            // Update statistics if visible
            updateEventDatesStatistics();
        });

        // Add event date row
        $addButton.on('click', function () {
            const $newRow = $template.clone();
            $newRow.removeClass('d-none').removeAttr('id');
            $container.append($newRow);
        });

        // Update statistics when event date fields change
        $container.on('change', 'select[name="event_types[]"], input[name="event_dates_input[]"], select[name="event_statuses[]"]', function() {
            updateEventDatesStatistics();
        });

        // Populate existing event dates (for update form)
        const existingEventsInput = document.getElementById('existing-event-dates');
        let hasDeliveryEvent = false;

        if (existingEventsInput && existingEventsInput.value) {
            try {
                // Clear previous completed events
                completedEvents = [];

                const existingEvents = JSON.parse(existingEventsInput.value);
                if (Array.isArray(existingEvents) && existingEvents.length > 0) {
                    existingEvents.forEach(function(event) {
                        // Store completed events for statistics display
                        if (event.status === 'Completed') {
                            completedEvents.push({
                                type: event.type || '',
                                description: event.description || '',
                                date: event.date || '',
                                status: 'Completed',
                                notes: event.notes || ''
                            });
                            return; // Skip rendering in form
                        }

                        const $newRow = $template.clone();
                        $newRow.removeClass('d-none').removeAttr('id');

                        // Set values
                        $newRow.find('select[name="event_types[]"]').val(event.type || '');
                        $newRow.find('input[name="event_descriptions[]"]').val(event.description || '');
                        $newRow.find('input[name="event_dates_input[]"]').val(event.date || '');
                        $newRow.find('select[name="event_statuses[]"]').val(event.status || 'Pending');
                        $newRow.find('input[name="event_notes[]"]').val(event.notes || '');

                        $container.append($newRow);

                        // Track if we have a Deliveries event
                        if (event.type === 'Deliveries') {
                            hasDeliveryEvent = true;
                        }
                    });
                }
            } catch (e) {
                console.error('Error parsing existing event dates:', e);
            }
        }

        // Pre-populate an empty Deliveries row if none exists (for both create and edit)
        if (!hasDeliveryEvent) {
            const $deliveryRow = $template.clone();
            $deliveryRow.removeClass('d-none').removeAttr('id');
            $deliveryRow.find('select[name="event_types[]"]').val('Deliveries');
            $deliveryRow.find('select[name="event_statuses[]"]').val('Pending');
            $container.append($deliveryRow);
        }
    }

    /**
     * Collect event dates from form for statistics display
     * @returns {Array} Array of event objects with type, description, date, and notes
     */
    function collectEventDatesForStats() {
        const events = [];
        // Collect from visible form rows
        $('.event-date-row:not(.d-none):not(#event-date-row-template)').each(function() {
            const $row = $(this);
            const type = $row.find('select[name="event_types[]"]').val();
            const description = $row.find('input[name="event_descriptions[]"]').val();
            const date = $row.find('input[name="event_dates_input[]"]').val();
            const status = $row.find('select[name="event_statuses[]"]').val();
            const notes = $row.find('input[name="event_notes[]"]').val();
            if (type && date) {
                events.push({
                    type: type,
                    description: description || '',
                    date: date,
                    status: status || 'Pending',
                    notes: notes || ''
                });
            }
        });
        // Add completed events that aren't shown in form
        events.push(...completedEvents);
        return events;
    }

    /**
     * Format date string from YYYY-MM-DD to DD/MM/YYYY
     * @param {string} dateStr - Date in YYYY-MM-DD format
     * @returns {string} Date in DD/MM/YYYY format
     */
    function formatDateDDMMYYYY(dateStr) {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return dateStr;
    }

    /**
     * Update the Event Dates section in Schedule Statistics
     * Displays individual event rows with Type, Description, Date, and Notes
     */
    function updateEventDatesStatistics() {
        const $statsSection = $('#schedule-statistics-section');
        const $emptyRow = $('#event-dates-stats-empty-row');

        // Only update if statistics section exists
        if ($statsSection.length === 0) {
            return;
        }

        // Remove any previously added dynamic event rows
        $('.event-dates-stat-row').remove();

        // Collect events (now includes description and notes)
        const events = collectEventDatesForStats();

        // Sort events chronologically by date (YYYY-MM-DD sorts as strings)
        events.sort(function(a, b) {
            return a.date.localeCompare(b.date);
        });

        if (events.length > 0) {
            // Hide empty row
            $emptyRow.hide();

            // Insert individual rows with XSS protection using jQuery's .text() method
            events.forEach(function(event, index) {
                const $newRow = $('<tr class="event-dates-stat-row"></tr>');

                if (index === 0) {
                    // First row has rowspan for "Events" category label
                    $newRow.append(
                        $('<td>').addClass('align-middle').attr('rowspan', events.length).text('Events')
                    );
                }

                // Add Type, Description, Date, Status columns
                const status = event.status || 'Pending';
                const $statusCell = $('<td>');

                // Add badge styling based on status
                if (status === 'Completed') {
                    $statusCell.html('<span class="wecoza-badge wecoza-badge-success">' + status.toUpperCase() + '</span>');
                } else if (status === 'Cancelled') {
                    $statusCell.html('<span class="wecoza-badge wecoza-badge-danger">' + status.toUpperCase() + '</span>');
                } else {
                    $statusCell.html('<span class="wecoza-badge wecoza-badge-secondary">' + status.toUpperCase() + '</span>');
                }

                $newRow.append(
                    $('<td>').text(event.type),
                    $('<td>').text(event.description),
                    $('<td>').text(formatDateDDMMYYYY(event.date)),
                    $statusCell
                );

                // Add Notes column (show '-' if empty)
                $newRow.append(
                    $('<td>').text(event.notes || '-')
                );

                $emptyRow.before($newRow);
            });
        } else {
            // Show empty row
            $emptyRow.show();
        }
    }

    /**
     * Initialize date history (stop/restart dates) functionality
     */
    function initDateHistory() {
        const $container = $('#date-history-container');
        const $template = $('#date-history-row-template');
        const $addButton = $('#add-date-history-btn');

        // Add date history row
        $addButton.on('click', function () {
            const $newRow = $template.clone();
            $newRow.removeClass('d-none').removeAttr('id');
            $container.append($newRow);

            // Initialize remove button
            $newRow.find('.date-remove-btn').on('click', function () {
                $newRow.remove();
                updateScheduleData();
            });

            // Update schedule data when dates change
            $newRow.find('input[name="stop_dates[]"], input[name="restart_dates[]"]').on('change', function () {
                updateScheduleData();
            });
        });
    }

    /**
     * Initialize exam class toggle functionality
     * Shows/hides the exam type container based on exam class selection
     */
    function initExamClassToggle() {
        const $examClass = $('#exam_class');
        const $examTypeContainer = $('#exam_type_container');
        const $examType = $('#exam_type');
        const $examLearnersContainer = $('#exam_learners_container');

        if (!$examClass.length || !$examTypeContainer.length) {
            return; // Elements not found, skip initialization
        }

        // Handle exam class selection change
        $examClass.on('change', function () {
            const examClassValue = $(this).val();

            if (examClassValue === 'Yes' || examClassValue === '1' || examClassValue === 1) {
                // Show exam type field and make it required
                $examTypeContainer.show();
                if ($examType.length) {
                    $examType.attr('required', 'required');
                }

                // Show exam learners container if it exists
                if ($examLearnersContainer.length) {
                    $examLearnersContainer.show();
                }
            } else {
                // Hide exam type field and remove required attribute YDCOZA
                $examTypeContainer.hide();
                if ($examType.length) {
                    $examType.removeAttr('required');
                    $examType.val(''); // Clear the value
                }

                // Hide exam learners container if it exists
                if ($examLearnersContainer.length) {
                    $examLearnersContainer.hide();
                }
            }
        });

        // Trigger change event on page load to set initial state
        $examClass.trigger('change');
    }

    /**
     * Initialize learner selection functionality
     * Handles adding selected learners from multi-select to the learners table
     */
    function initLearnerSelection() {
        const $addLearnerSelect = $('#add_learner');
        const $addSelectedLearnersBtn = $('#add-selected-learners-btn');
        const $classLearnersContainer = $('#class-learners-container');
        const $classLearnersTable = $('#class-learners-table');
        const $classLearnersTbody = $('#class-learners-tbody');
        const $noLearnersMessage = $('#no-learners-message');
        const $classLearnersData = $('#class_learners_data');

        // Array to store class learner data
        let classLearners = [];

        // Check if required elements exist
        if (!$addLearnerSelect.length || !$addSelectedLearnersBtn.length || !$classLearnersContainer.length) {
            return;
        }


        // Handle add selected learners button click
        $addSelectedLearnersBtn.on('click', function () {
            const selectedOptions = $addLearnerSelect.find('option:selected');

            if (selectedOptions.length === 0) {
                alert('Please select at least one learner to add.');
                return;
            }


            // Add each selected learner
            selectedOptions.each(function () {
                const learnerId = $(this).val();
                const learnerName = $(this).text();

                // Convert to string to ensure consistent comparison
                const learnerIdStr = String(learnerId);

                // Check if learner is already added - ensure both IDs are strings for comparison
                if (classLearners.some(learner => String(learner.id) === learnerIdStr)) {
                    return;
                }

                // Add learner to array (store as string for consistency)
                const learnerData = {
                    id: learnerIdStr,
                    name: learnerName,
                    level: '', // Default level (empty, will be auto-populated when subject is selected)
                    status: 'CIC - Currently in Class' // Default status
                };

                classLearners.push(learnerData);
            });

            // Update the display and data
            updateLearnersDisplay();
            updateLearnersData();

            // Auto-populate learner levels with current subject
            const classSubjectSelect = document.getElementById('class_subject');
            if (classSubjectSelect && classSubjectSelect.value) {
                const selectedSubject = classSubjectSelect.value;
                if (typeof classes_populate_learner_levels === 'function') {
                    classes_populate_learner_levels(selectedSubject);
                }
            }

            // Synchronize exam learner options if the function exists
            if (typeof window.classes_sync_exam_learner_options === 'function') {
                window.classes_sync_exam_learner_options();
            }

            // Clear the selection
            $addLearnerSelect.val([]);
        });

        // Function to update the learners table display
        function updateLearnersDisplay() {
            // Clear existing rows
            $classLearnersTbody.empty();

            if (classLearners.length === 0) {
                // Show no learners message and hide table
                $noLearnersMessage.removeClass('d-none');
                $classLearnersTable.addClass('d-none');
                return;
            }

            // Hide no learners message and show table
            $noLearnersMessage.addClass('d-none');
            $classLearnersTable.removeClass('d-none');

            // Add rows for each learner
            classLearners.forEach(function (learner) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${learner.name}</td>
                    <td>
                        ${classes_generate_learner_level_select_html(learner.id, learner.level)}
                    </td>
                    <td>
                        <select class="form-select form-select-sm learner-status-select" data-learner-id="${learner.id}">
                            <option value="CIC - Currently in Class" ${learner.status === 'CIC - Currently in Class' ? 'selected' : ''}>CIC - Currently in Class</option>
                            <option value="RBE - Removed by Employer" ${learner.status === 'RBE - Removed by Employer' ? 'selected' : ''}>RBE - Removed by Employer</option>
                            <option value="DRO - Drop Out" ${learner.status === 'DRO - Drop Out' ? 'selected' : ''}>DRO - Drop Out</option>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="btn btn-subtle-danger btn-sm remove-learner-btn" data-learner-id="${learner.id}">Remove</button>
                    </td>
                `;
                $classLearnersTbody.append(row);
            });


            // Debug: Check if remove buttons were created correctly
            const removeButtons = $classLearnersTbody.find('.remove-learner-btn');
            removeButtons.each(function (index) {
                const learnerId = $(this).data('learner-id');
            });

            // Auto-populate learner levels if a class subject is already selected
            const classSubjectSelect = document.getElementById('class_subject');
            if (classSubjectSelect && classSubjectSelect.value) {
                // Use setTimeout to ensure DOM is fully updated
                setTimeout(function () {
                    if (typeof classes_populate_learner_levels === 'function') {
                        classes_populate_learner_levels(classSubjectSelect.value);
                    } else if (typeof window.wecoza_auto_populate_learner_levels === 'function') {
                        window.wecoza_auto_populate_learner_levels(classSubjectSelect.value);
                    }
                }, 100); // Small delay to ensure DOM is ready
            }
        }

        // Function to update the hidden field with learner data
        function updateLearnersData() {
            const jsonData = JSON.stringify(classLearners);
            $classLearnersData.val(jsonData);

            // Trigger custom event for learner data change
            $(document).trigger('classLearnersChanged', [classLearners]);

        }

        // Handle level/status changes
        $(document).on('change', '.learner-level-select, .learner-status-select', function () {
            const learnerId = $(this).data('learner-id');
            const field = $(this).hasClass('learner-level-select') ? 'level' : 'status';
            const value = $(this).val();


            // Convert to string to ensure consistent comparison
            const learnerIdStr = String(learnerId);

            // Update the learner data - ensure both IDs are strings for comparison
            const learner = classLearners.find(l => String(l.id) === learnerIdStr);
            if (learner) {
                learner[field] = value;
                updateLearnersData();
            } else {
                console.warn('❌ Learner not found for ID:', learnerIdStr);
            }
        });

        // Handle remove learner
        $(document).on('click', '.remove-learner-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const learnerId = $(this).data('learner-id');

            if (!learnerId) {
                console.error('No learner ID found on remove button');
                return;
            }

            // Convert to string to ensure consistent comparison (since HTML data attributes are strings)
            const learnerIdStr = String(learnerId);

            // Debug: Log current classLearners array

            // Remove from array - ensure both IDs are strings for comparison
            const initialLength = classLearners.length;
            classLearners = classLearners.filter(learner => {
                const learnerIdInArray = String(learner.id);
                const shouldKeep = learnerIdInArray !== learnerIdStr;
                return shouldKeep;
            });

            if (classLearners.length === initialLength) {
                console.warn('Learner', learnerIdStr, 'was not found in classLearners array');
                return;
            }


            // Update display and data
            updateLearnersDisplay();
            updateLearnersData();

            // Remove from exam learners if they were selected for exams (cascading removal)
            if (typeof window.classes_remove_exam_learner === 'function') {
                window.classes_remove_exam_learner(learnerIdStr);
            }

            // Synchronize exam learner options if the function exists
            if (typeof window.classes_sync_exam_learner_options === 'function') {
                window.classes_sync_exam_learner_options();
            }

        });

        // Load existing learner data if available (for editing)
        const existingData = $classLearnersData.val();
        if (existingData) {
            try {
                classLearners = JSON.parse(existingData);
                updateLearnersDisplay();

                // Synchronize exam learner options after loading existing data
                setTimeout(function () {
                    if (typeof window.classes_sync_exam_learner_options === 'function') {
                        window.classes_sync_exam_learner_options();
                    }
                }, 200); // Delay to ensure exam learner functionality is initialized

                // Auto-populate learner levels if a class subject is already selected (for editing)
                const classSubjectSelect = document.getElementById('class_subject');
                if (classSubjectSelect && classSubjectSelect.value) {
                    // Use setTimeout to ensure DOM is fully updated
                    setTimeout(function () {
                        if (typeof classes_populate_learner_levels === 'function') {
                            classes_populate_learner_levels(classSubjectSelect.value);
                        } else if (typeof window.wecoza_auto_populate_learner_levels === 'function') {
                            window.wecoza_auto_populate_learner_levels(classSubjectSelect.value);
                        }
                    }, 100); // Small delay to ensure DOM is ready
                }
            } catch (e) {
                console.error('Error parsing existing learner data:', e);
            }
        }
    }

    /**
     * Initialize auto level population when class subject changes
     * This ensures learner levels are auto-populated when subject is selected
     */
    function initSubjectChangeLevelPopulation() {
        const classSubjectSelect = document.getElementById('class_subject');

        if (!classSubjectSelect) {
            return;
        }

        // Subject change no longer auto-populates levels - only button clicks do that
    }

    /**
     * Initialize backup agents functionality
     * Handles adding and removing backup agent rows
     */
    function initBackupAgents() {
        const $container = $('#backup-agents-container');
        const $template = $('#backup-agent-row-template');
        const $addButton = $('#add-backup-agent-btn');

        // Check if required elements exist
        if (!$container.length || !$template.length || !$addButton.length) {
            return;
        }


        // Handle add backup agent button click
        $addButton.on('click', function () {

            // Clone the template
            const $newRow = $template.clone();

            // Remove the d-none class and id to make it visible and unique
            $newRow.removeClass('d-none').removeAttr('id');

            // Append to container
            $container.append($newRow);

            // Initialize remove button for this row
            $newRow.find('.remove-backup-agent-btn, .date-remove-btn').on('click', function () {
                $newRow.remove();

                // Update any form data if needed
                updateScheduleData();
            });

            // Focus on the first input in the new row
            $newRow.find('select').first().focus();
        });

        // Handle remove buttons for any existing rows (in case of editing)
        // Use event delegation but be more specific to avoid conflicts with other .date-remove-btn handlers
        $(document).on('click', '.backup-agent-row .remove-backup-agent-btn, .backup-agent-row .date-remove-btn', function () {
            // Check if this is a backup agent row (should always be true due to selector)
            const $row = $(this).closest('.backup-agent-row');
            if ($row.length && !$row.is('#backup-agent-row-template')) {
                $row.remove();
                updateScheduleData();
            }
        });
    }

    /**
     * Initialize holiday override functionality
     */
    function initHolidayOverrides() {
        const $holidaysList = $('#holidays-list');
        const $overrideAllCheckbox = $('#override-all-holidays');
        const $skipAllBtn = $('#skip-all-holidays-btn');
        const $overrideAllBtn = $('#override-all-holidays-btn');
        const $holidayOverridesInput = $('#holiday_overrides');

        // Initialize holidayOverrides object if not already initialized
        if (typeof window.holidayOverrides !== 'object' || window.holidayOverrides === null) {
            window.holidayOverrides = {};
        }

        // Load existing overrides if available
        try {
            const existingOverrides = $holidayOverridesInput.val();

            if (existingOverrides) {
                const overrides = JSON.parse(existingOverrides);
                Object.assign(window.holidayOverrides, overrides);
            }
        } catch (e) {
            console.error('Error parsing existing holiday overrides:', e);
        }

        // Handle individual holiday override checkboxes
        $(document).on('change', '.holiday-override-checkbox', function () {
            const $checkbox = $(this);
            const date = $checkbox.data('date');
            const isChecked = $checkbox.is(':checked');
            const $row = $checkbox.closest('tr');

            if (isChecked) {
                window.holidayOverrides[date] = true;
            } else {
                delete window.holidayOverrides[date];
            }

            // Update status badges in the row
            $row.find('.holiday-skipped').toggleClass('d-none', isChecked);
            $row.find('.holiday-overridden').toggleClass('d-none', !isChecked);

            // Update hidden field
            $holidayOverridesInput.val(JSON.stringify(window.holidayOverrides));

            // Update "Override All" checkbox state
            updateOverrideAllCheckbox();
        });

        // Handle "Override All" checkbox
        if ($overrideAllCheckbox.length) {
            $overrideAllCheckbox.on('change', function () {
                const isChecked = $(this).is(':checked');
                $('.holiday-override-checkbox').prop('checked', isChecked).trigger('change');
            });
        }

        // Handle "Skip All" button
        if ($skipAllBtn.length) {
            $skipAllBtn.on('click', function () {
                $('.holiday-override-checkbox').each(function () {
                    $(this).prop('checked', false).trigger('change');
                });
            });
        }

        // Handle "Override All" button
        if ($overrideAllBtn.length) {
            $overrideAllBtn.on('click', function () {
                $('.holiday-override-checkbox').each(function () {
                    $(this).prop('checked', true).trigger('change');
                });
            });
        }

        // Check for holidays when start date changes
        $('#schedule_start_date').on('change', function () {
            const startDate = $(this).val();
            const endDate = $('#schedule_end_date').val();
            const pattern = $('#schedule_pattern').val();
            const selectedDays = getSelectedDays();

            if (startDate && pattern && selectedDays.length > 0) {
                checkForHolidays(startDate, endDate);
            }
        });

        // Check for holidays when end date changes
        $('#schedule_end_date').on('change', function () {
            const startDate = $('#schedule_start_date').val();
            const endDate = $(this).val();
            const pattern = $('#schedule_pattern').val();
            const selectedDays = getSelectedDays();

            if (startDate && pattern && selectedDays.length > 0) {
                checkForHolidays(startDate, endDate);
            }
        });

        // Update the "Override All" checkbox based on individual checkboxes
        function updateOverrideAllCheckbox() {
            const totalHolidays = $('.holiday-override-checkbox').length;
            const checkedHolidays = $('.holiday-override-checkbox:checked').length;

            if ($overrideAllCheckbox.length) {
                if (checkedHolidays === 0) {
                    $overrideAllCheckbox.prop('checked', false);
                    $overrideAllCheckbox.prop('indeterminate', false);
                } else if (checkedHolidays === totalHolidays) {
                    $overrideAllCheckbox.prop('checked', true);
                    $overrideAllCheckbox.prop('indeterminate', false);
                } else {
                    $overrideAllCheckbox.prop('checked', false);
                    $overrideAllCheckbox.prop('indeterminate', true);
                }
            }
        }
    }

    /**
     * Check for public holidays in date range and show only holidays that conflict with the schedule
     */
    function checkForHolidays(startDate, endDate) {
        // If no public holidays data, show no holidays message and return
        if (typeof window.wecozaPublicHolidays === 'undefined' || !window.wecozaPublicHolidays.events) {
            updateHolidaysDisplay([]);
            return;
        }

        // If no start date, show no holidays message and return
        if (!startDate) {
            updateHolidaysDisplay([]);
            return;
        }

        // If no end date, use 3 months from start date
        if (!endDate) {
            const date = new Date(startDate);
            date.setMonth(date.getMonth() + 3);
            endDate = date.toISOString().split('T')[0];
        }

        const pattern = $('#schedule_pattern').val();
        const selectedDays = getSelectedDays();

        // Only check for holidays if we have a weekly or biweekly pattern with selected days
        if ((pattern === 'weekly' || pattern === 'biweekly') && selectedDays.length === 0) {
            return;
        }

        // Convert dates to Date objects for comparison
        const startDateObj = new Date(startDate);
        const endDateObj = new Date(endDate);

        // Find holidays that conflict with the schedule
        const conflictingHolidays = [];

        window.wecozaPublicHolidays.events.forEach(holiday => {
            // Parse the date parts to ensure correct date (avoid timezone issues)
            const [year, month, day] = holiday.start.split('-').map(Number);
            const holidayDate = new Date(year, month - 1, day);

            if (holidayDate >= startDateObj && holidayDate <= endDateObj) {
                // Check if this holiday conflicts with the schedule
                let conflictsWithSchedule = false;

                if (pattern === 'weekly' || pattern === 'biweekly') {
                    const dayName = holidayDate.toLocaleDateString('en-US', { weekday: 'long' });
                    conflictsWithSchedule = selectedDays.includes(dayName);
                } else if (pattern === 'monthly') {
                    const dayOfMonth = $('#schedule_day_of_month').val();
                    if (dayOfMonth === 'last') {
                        // Check if it's the last day of the month
                        const lastDay = new Date(holidayDate.getFullYear(), holidayDate.getMonth() + 1, 0);
                        conflictsWithSchedule = holidayDate.getDate() === lastDay.getDate();
                    } else {
                        conflictsWithSchedule = holidayDate.getDate() === parseInt(dayOfMonth);
                    }
                }

                if (conflictsWithSchedule) {
                    conflictingHolidays.push({
                        date: holiday.start,
                        name: holiday.title,
                        dayName: holidayDate.toLocaleDateString('en-US', { weekday: 'long' })
                    });
                }
            }
        });

        // Update holidays display
        updateHolidaysDisplay(conflictingHolidays);
    }

    /**
     * Update holidays display with conflicting holidays
     */
    function updateHolidaysDisplay(conflictingHolidays) {
        const $holidaysList = $('#holidays-list');
        const $holidaysTableContainer = $('#holidays-table-container');
        const $noHolidaysMessage = $('#no-holidays-message');
        const $template = $('#holiday-row-template');

        if (!$holidaysList.length) {
            return; // No holidays display element found
        }

        // Clear existing holidays
        $holidaysList.empty();

        if (conflictingHolidays.length === 0) {
            // Hide holidays table container and show no holidays message
            if ($holidaysTableContainer.length) {
                $holidaysTableContainer.addClass('d-none');
            }
            if ($noHolidaysMessage.length) {
                $noHolidaysMessage.removeClass('d-none');
            }
            return;
        }

        // Show holidays table container and hide no holidays message
        if ($holidaysTableContainer.length) {
            $holidaysTableContainer.removeClass('d-none');
        }
        if ($noHolidaysMessage.length) {
            $noHolidaysMessage.addClass('d-none');
        }

        // Add each conflicting holiday using the template
        conflictingHolidays.forEach((holiday, index) => {
            // Get template content
            const templateHtml = $template.html();

            // Format the date for display
            const [year, month, day] = holiday.date.split('-').map(Number);
            const holidayDate = new Date(year, month - 1, day);
            const formattedDate = holidayDate.toLocaleDateString('en-ZA', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Replace placeholders in template
            let rowHtml = templateHtml
                .replace(/{id}/g, index)
                .replace(/{date}/g, holiday.date)
                .replace(/{formatted_date}/g, formattedDate)
                .replace(/{name}/g, holiday.name);

            // Add to holidays list
            $holidaysList.append(rowHtml);

            // Get the row we just added
            const $row = $holidaysList.find(`[data-date="${holiday.date}"]`).closest('tr');

            // Check if this holiday has an existing override
            let isOverridden = false;
            if (typeof window.holidayOverrides === 'object' && window.holidayOverrides !== null && window.holidayOverrides[holiday.date]) {
                const overrideValue = window.holidayOverrides[holiday.date];
                // Handle both direct boolean values and objects with override property
                isOverridden = typeof overrideValue === 'boolean' ? overrideValue : overrideValue.override;

                console.log(`Holiday ${holiday.date} (${holiday.name}) override value:`, overrideValue, 'resolved to:', isOverridden);

                // Update checkbox
                $row.find('.holiday-override-checkbox').prop('checked', isOverridden);
            }

            // Update status badges based on override status
            $row.find('.holiday-skipped').toggleClass('d-none', isOverridden);
            $row.find('.holiday-overridden').toggleClass('d-none', !isOverridden);
        });
    }

    /**
     * Initialize schedule data updates system
     * Sets up event handlers and initial data collection
     */
    function initScheduleDataUpdates() {
        // Initialize statistics toggle
        initScheduleStatisticsToggle();

        // Perform initial data update
        updateScheduleData();

        // Set up form submission handler
        initFormSubmissionHandler();
    }

    /**
     * Initialize schedule statistics toggle functionality
     */
    function initScheduleStatisticsToggle() {
        $('#toggle-statistics-btn').on('click', function () {
            const $section = $('#schedule-statistics-section');
            const $button = $(this);

            if ($section.hasClass('d-none')) {
                // Show statistics
                $section.removeClass('d-none');
                $button.html('<i class="bi bi-eye-slash me-1"></i> Hide Schedule Statistics');

                // Update statistics when shown
                const scheduleData = collectScheduleData();
                updateScheduleStatistics(scheduleData);
            } else {
                // Hide statistics
                $section.addClass('d-none');
                $button.html('<i class="bi bi-bar-chart-line me-1"></i> View Schedule Statistics');
            }
        });
    }

    /**
     * Initialize form submission handler with validation
     */
    function initFormSubmissionHandler() {
        // Find the form containing the schedule data
        const $form = $('#schedule_pattern').closest('form');

        if ($form.length > 0) {
            $form.on('submit', function (e) {
                // Validate day selection for custom validation
                validateDaySelection();

                // Check if form is valid using Bootstrap validation
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Add Bootstrap validation class to trigger styling
                    $form.addClass('was-validated');

                    // Show validation feedback for day selection
                    showDaySelectionValidationFeedback();

                    return false;
                }

                // Add Bootstrap validation class to trigger styling
                $form.addClass('was-validated');

                // Show validation feedback for day selection
                showDaySelectionValidationFeedback();

                // If form is valid, proceed with custom validation
                if (this.checkValidity()) {
                    // Get submission data with additional validation
                    const submissionData = getFormSubmissionData();

                    if (!submissionData.isValid) {
                        e.preventDefault();

                        // Display validation errors
                        displayFormValidationErrors(submissionData.errors);

                        return false;
                    }

                    // Final data update before submission
                    updateScheduleData();
                }
            });
        }
    }

    /**
     * Display form validation errors to the user
     */
    function displayFormValidationErrors(errors) {
        // Remove existing error display
        $('#schedule-validation-errors').remove();

        if (errors.length > 0) {
            const errorHtml = `
                <div id="schedule-validation-errors" class="alert alert-subtle-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        ${errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            // Insert after the schedule controls
            $('#per-day-time-controls').after(errorHtml);

            // Scroll to the error message
            $('#schedule-validation-errors')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /**
     * Backward Compatibility System
     * Handles loading and converting legacy schedule data formats
     */

    /**
     * Load and process existing schedule data for editing
     * Handles both legacy and new data formats
     */
    function loadExistingScheduleData() {
        // Check if we're in edit mode (has existing data)
        const existingData = getExistingScheduleDataFromForm();

        if (existingData) {
            // V2.0 format only - no legacy processing needed
            // Directly populate form with schedule data
            populateFormWithScheduleData(existingData);
        }
    }

    /**
     * Get existing schedule data from form/page context
     * This would typically come from server-side rendering or AJAX
     */
    function getExistingScheduleDataFromForm() {
        // Check for data in various possible locations

        // 1. Check for data in a hidden field (common pattern)
        const hiddenDataField = $('#existing_schedule_data').val();
        if (hiddenDataField) {
            try {
                return JSON.parse(hiddenDataField);
            } catch (e) {
                console.warn('Failed to parse hidden schedule data:', e);
            }
        }

        // 2. Check for data in window object (if localized by PHP)
        if (window.wecozaScheduleData) {
            return window.wecozaScheduleData;
        }

        // 2b. Check for existingScheduleData set by update form
        if (window.existingScheduleData) {
            return window.existingScheduleData;
        }

        // 3. Check for pre-filled form values (legacy approach)
        const $startTime = $('#schedule_start_time');
        const $endTime = $('#schedule_end_time');
        const pattern = $('#schedule_pattern').val();

        const startTime = $startTime.length ? $startTime.val() : '';
        const endTime = $endTime.length ? $endTime.val() : '';

        if (startTime && endTime && pattern) {
            // Construct basic legacy format
            return {
                format: 'legacy_form_values',
                pattern: pattern,
                start_time: startTime,
                end_time: endTime,
                start_date: $('#schedule_start_date').val(),
                end_date: $('#schedule_end_date').val()
            };
        }

        return null;
    }

    // Legacy format processing removed - V2.0 format only

    // Legacy conversion functions removed - V2.0 format only

    /**
     * Populate form with processed schedule data
     * Sets form values and triggers appropriate UI updates
     */
    function populateFormWithScheduleData(data) {
        try {

            // Set basic schedule fields
            if (data.pattern) {
                $('#schedule_pattern').val(data.pattern).trigger('change');
            }

            // Handle both startDate and start_date formats
            if (data.startDate || data.start_date) {
                $('#schedule_start_date').val(data.startDate || data.start_date);
            }

            // Handle both endDate and end_date formats
            if (data.endDate || data.end_date) {
                $('#schedule_end_date').val(data.endDate || data.end_date);
            }

            if (data.dayOfMonth) {
                $('#schedule_day_of_month').val(data.dayOfMonth);
            }

            // Set time data based on mode
            // First check if timeData exists, otherwise check for legacy format
            if (data.timeData) {
                if (data.timeData.mode === 'single') {
                    // Populate single time controls only if they exist
                    const $startTime = $('#schedule_start_time');
                    const $endTime = $('#schedule_end_time');

                    if (data.timeData.startTime && $startTime.length) {
                        $startTime.val(data.timeData.startTime);
                    }
                    if (data.timeData.endTime && $endTime.length) {
                        $endTime.val(data.timeData.endTime);
                    }
                    if (data.timeData.duration) {
                        const $duration = $('#schedule_duration');
                        if ($duration.length) {
                            $duration.val(data.timeData.duration);
                        }
                    }
                } else if (data.timeData.mode === 'per-day' && data.timeData.perDayTimes) {
                    // Handle per-day times - this will be set after day selection
                    // Store for later use when per-day sections are created
                    window.pendingPerDayTimes = data.timeData.perDayTimes;
                }
            } else if (data.per_day_times || data.perDayTimes) {
                // Handle legacy format where per_day_times is at root level
                window.pendingPerDayTimes = data.per_day_times || data.perDayTimes;
            }

            // Set selected days for weekly/biweekly patterns (handle both selectedDays and days)
            const days = data.selectedDays || data.days || [];

            if (days.length > 0) {
                days.forEach(day => {
                    const checkbox = $(`.schedule-day-checkbox[value="${day}"]`);
                    if (checkbox.length > 0) {
                        checkbox.prop('checked', true);
                    } else {
                        console.warn(`Checkbox not found for day: ${day}`);
                    }
                });

                // Trigger day selection update
                setTimeout(() => {
                    updatePerDayTimeControls();

                    // Apply pending per-day times if available
                    if (window.pendingPerDayTimes) {
                        applyPerDayTimes(window.pendingPerDayTimes);
                        delete window.pendingPerDayTimes;
                    }
                }, 100);
            } else {
            }

            // Set exception dates (with duplicate prevention)
            if (data.exceptionDates && data.exceptionDates.length > 0) {
                const exceptionDatesContainer = $('#exception-dates-container');
                const isAlreadyPopulated = exceptionDatesContainer.attr('data-populated');

                if (isAlreadyPopulated === 'php') {
                    console.log('JS: Exception dates already populated by PHP, skipping JavaScript population');
                } else {
                    console.log('JS: Populating exception dates from JavaScript');
                    data.exceptionDates.forEach(exception => {
                        addExceptionDateRow(exception.date, exception.reason);
                    });
                    // Mark as populated by JavaScript
                    exceptionDatesContainer.attr('data-populated', 'javascript');
                }
            }

            // Set holiday overrides (handle both holidayOverrides and holiday_overrides)
            const holidayOverrides = data.holidayOverrides || data.holiday_overrides;

            if (holidayOverrides) {
                Object.keys(holidayOverrides).forEach(date => {
                    const $checkbox = $(`.holiday-override-checkbox[data-date="${date}"]`);
                    if ($checkbox.length > 0) {
                        $checkbox.prop('checked', true);
                        console.log(`Set checkbox for ${date} to checked`);
                    } else {
                        console.log(`Checkbox for ${date} not found in DOM yet`);
                    }
                });

                // Store holiday overrides in global object for later application
                if (typeof window.holidayOverrides !== 'object' || window.holidayOverrides === null) {
                    window.holidayOverrides = {};
                }
                Object.assign(window.holidayOverrides, holidayOverrides);
            }

            // Trigger validation and updates
            setTimeout(() => {
                validateAllTimeSelections();
                updateTimeValidationIndicators();
                updateScheduleData();
            }, 200);

        } catch (error) {
            console.error('Error populating form with schedule data:', error);
        }
    }

    /**
     * Apply per-day times to the generated sections
     */
    function applyPerDayTimes(perDayTimes) {

        if (!perDayTimes || typeof perDayTimes !== 'object') {
            console.warn('Invalid perDayTimes data:', perDayTimes);
            return;
        }

        Object.keys(perDayTimes).forEach(day => {
            const dayData = perDayTimes[day];
            const $section = $(`.per-day-time-section[data-day="${day}"]`);


            if ($section.length > 0) {
                const $startTime = $section.find('.day-start-time');
                const $endTime = $section.find('.day-end-time');

                if ($startTime.length && dayData.startTime) {
                    $startTime.val(dayData.startTime);
                }

                if ($endTime.length && dayData.endTime) {
                    $endTime.val(dayData.endTime);
                }

                // Trigger change events to update duration and validation
                $startTime.trigger('change');
                $endTime.trigger('change');
            } else {
                console.warn(`No time section found for day: ${day}`);
            }
        });
    }

    /**
     * Add an exception date row with pre-filled data
     * Includes duplicate prevention to avoid adding the same date twice
     */
    function addExceptionDateRow(date, reason) {
        // Check if this date already exists to prevent duplicates
        const existingDates = [];
        $('#exception-dates-container input[name="exception_dates[]"]').each(function () {
            if ($(this).val()) {
                existingDates.push($(this).val());
            }
        });

        if (existingDates.includes(date)) {
            console.log(`JS: Exception date ${date} already exists, skipping duplicate`);
            return;
        }

        // Trigger the add button to create a new row
        $('#add-exception-date-btn').trigger('click');

        // Fill the newly created row
        setTimeout(() => {
            const $lastRow = $('#exception-dates-container .exception-date-row').last();
            $lastRow.find('input[name="exception_dates[]"]').val(date);
            $lastRow.find('select[name="exception_reasons[]"]').val(reason);
        }, 50);
    }

    // Legacy compatibility function removed - V2.0 format only

    // Legacy compatibility functions removed - V2.0 format only

    /**
     * Global function to get schedule data in current format
     * Provides access to the full new data structure
     */
    window.getScheduleDataCurrent = function () {
        return collectScheduleData();
    };

    /**
     * Update schedule data - collect and format all schedule information
     * Creates hidden form fields with properly structured data for backend processing
     */
    function updateScheduleData() {
        try {
            // Collect all schedule data
            const scheduleData = collectScheduleData();

            // Update hidden form fields
            updateHiddenFormFields(scheduleData);

            // Update schedule statistics if visible
            updateScheduleStatistics(scheduleData);

        } catch (error) {
            console.error('Error updating schedule data:', error);
        }
    }

    /**
     * Collect comprehensive schedule data from the form
     * Returns structured data object ready for backend processing
     */
    function collectScheduleData() {
        // Ensure we have a start date before collecting
        let startDate = $('#schedule_start_date').val() || $('#class_start_date').val();
        if (!startDate) {
            startDate = new Date().toISOString().split('T')[0];
            $('#schedule_start_date').val(startDate);
        }

        const data = {
            // Basic schedule information with fallbacks
            pattern: $('#schedule_pattern').val() || 'weekly',
            startDate: startDate,
            endDate: $('#schedule_end_date').val() || '',
            dayOfMonth: $('#schedule_day_of_month').val() || null,

            // Time data (single or per-day)
            timeData: getAllTimeData(),

            // Selected days for weekly/biweekly patterns
            selectedDays: getSelectedDays(),

            // Exception dates
            exceptionDates: collectExceptionDates(),

            // Holiday overrides
            holidayOverrides: collectHolidayOverrides(),

            // Metadata
            lastUpdated: new Date().toISOString(),
            version: '2.0', // Version for backward compatibility tracking

            // Add validation metadata
            metadata: {
                lastUpdated: new Date().toISOString(),
                validatedAt: new Date().toISOString()
            }
        };

        // Debug logging

        return data;
    }

    /**
     * Collect exception dates from the form
     */
    function collectExceptionDates() {
        const exceptions = [];

        $('#exception-dates-container .exception-date-row').each(function () {
            const date = $(this).find('input[name="exception_dates[]"]').val();
            const reason = $(this).find('select[name="exception_reasons[]"]').val();

            if (date) {
                exceptions.push({
                    date: date,
                    reason: reason || 'No reason specified'
                });
            }
        });

        return exceptions;
    }

    /**
     * Collect holiday override data
     */
    function collectHolidayOverrides() {
        const overrides = {};

        $('.holiday-override-checkbox:checked').each(function () {
            const date = $(this).attr('data-date');
            if (date) {
                overrides[date] = true;
            }
        });

        return overrides;
    }

    /**
     * Update hidden form fields with collected schedule data
     * Creates the proper structure expected by the backend
     */
    function updateHiddenFormFields(scheduleData) {
        const $container = $('#schedule-data-container');


        // Clear existing hidden fields
        $container.empty();

        // Create hidden fields for different data types
        createHiddenField($container, 'schedule_data[pattern]', scheduleData.pattern);
        createHiddenField($container, 'schedule_data[start_date]', scheduleData.startDate);
        createHiddenField($container, 'schedule_data[end_date]', scheduleData.endDate);
        createHiddenField($container, 'schedule_data[day_of_month]', scheduleData.dayOfMonth);
        createHiddenField($container, 'schedule_data[version]', scheduleData.version);
        createHiddenField($container, 'schedule_data[last_updated]', scheduleData.lastUpdated);

        // Handle time data based on mode
        if (scheduleData.timeData.mode === 'single') {
            // Single time mode - backward compatible format
            createHiddenField($container, 'schedule_data[time_mode]', 'single');
            createHiddenField($container, 'schedule_data[start_time]', scheduleData.timeData.startTime);
            createHiddenField($container, 'schedule_data[end_time]', scheduleData.timeData.endTime);
            createHiddenField($container, 'schedule_data[duration]', scheduleData.timeData.duration);
        } else {
            // Per-day time mode - new format
            createHiddenField($container, 'schedule_data[time_mode]', 'per_day');

            // Create fields for each day's times
            Object.keys(scheduleData.timeData.perDayTimes).forEach(day => {
                const dayData = scheduleData.timeData.perDayTimes[day];
                createHiddenField($container, `schedule_data[per_day_times][${day}][start_time]`, dayData.startTime);
                createHiddenField($container, `schedule_data[per_day_times][${day}][end_time]`, dayData.endTime);
                createHiddenField($container, `schedule_data[per_day_times][${day}][duration]`, dayData.duration.toFixed(2));
            });
        }

        // Selected days for weekly/biweekly patterns
        if (scheduleData.selectedDays.length > 0) {
            scheduleData.selectedDays.forEach((day, index) => {
                createHiddenField($container, `schedule_data[selected_days][${index}]`, day);
            });
        }

        // Exception dates
        if (scheduleData.exceptionDates.length > 0) {
            scheduleData.exceptionDates.forEach((exception, index) => {
                createHiddenField($container, `schedule_data[exception_dates][${index}][date]`, exception.date);
                createHiddenField($container, `schedule_data[exception_dates][${index}][reason]`, exception.reason);
            });
        }

        // Holiday overrides
        Object.keys(scheduleData.holidayOverrides).forEach(date => {
            createHiddenField($container, `schedule_data[holiday_overrides][${date}]`, '1');
        });

        // Update the main holiday overrides field for backward compatibility
        $('#holiday_overrides').val(JSON.stringify(scheduleData.holidayOverrides));

        // Legacy compatibility fields removed - V2.0 format only

        // Log final hidden field count
        const hiddenFieldCount = $container.find('input[type="hidden"]').length;

        // Verify critical fields exist and show status
        const criticalFields = ['schedule_data[pattern]', 'schedule_data[start_date]'];
        let allFieldsValid = true;
        criticalFields.forEach(fieldName => {
            const fieldExists = $container.find(`input[name="${fieldName}"]`).length > 0;
            if (!fieldExists) {
                // Only log as warning during initialization, not error
                console.warn(`Field not yet created: ${fieldName}`);
                allFieldsValid = false;
            }
        });
    }

    /**
     * Create a hidden form field
     */
    function createHiddenField($container, name, value) {
        if (value !== null && value !== undefined && value !== '') {
            const $field = $('<input>', {
                type: 'hidden',
                name: name,
                value: value
            });
            $container.append($field);
        }
        // else {
        //             console.warn(`Skipped empty hidden field: ${name}`);
        //         }
    }

    /**
     * Update schedule statistics display
     */
    function updateScheduleStatistics(scheduleData) {
        // Only update if statistics section is visible
        if (!$('#schedule-statistics-section').hasClass('d-none')) {
            calculateAndDisplayStatistics(scheduleData);
        }
    }

    /**
     * Calculate and display schedule statistics
     */
    function calculateAndDisplayStatistics(scheduleData) {
        try {
            const stats = calculateScheduleStatistics(scheduleData);

            // Update statistics display
            $('#stat-total-days').text(stats.totalDays || '-');
            $('#stat-total-weeks').text(stats.totalWeeks || '-');
            $('#stat-total-months').text(stats.totalMonths || '-');
            $('#stat-total-classes').text(stats.totalClasses || '-');
            $('#stat-total-hours').text(stats.totalHours || '-');
            $('#stat-avg-hours-month').text(stats.avgHoursPerMonth || '-');
            $('#stat-holidays-affecting').text((stats.holidaysAffecting || 0) + ' holidays');
            $('#stat-exception-dates').text(stats.exceptionDates || '-');
            $('#stat-actual-days').text(stats.actualTrainingDays || '-');

            // Update Event Dates statistics section
            updateEventDatesStatistics();

        } catch (error) {
            console.error('Error calculating schedule statistics:', error);
        }
    }

    /**
     * Calculate schedule statistics from schedule data
     */
    function calculateScheduleStatistics(scheduleData) {
        const stats = {
            totalDays: 0,
            totalWeeks: 0,
            totalMonths: 0,
            totalClasses: 0,
            totalHours: 0,
            avgHoursPerMonth: 0,
            holidaysAffecting: 0,
            exceptionDates: scheduleData.exceptionDates.length,
            actualTrainingDays: 0
        };

        if (scheduleData.startDate && scheduleData.endDate) {
            const startDate = new Date(scheduleData.startDate);
            const endDate = new Date(scheduleData.endDate);
            const timeDiff = endDate.getTime() - startDate.getTime();

            stats.totalDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
            stats.totalWeeks = Math.ceil(stats.totalDays / 7);
            // Use accurate calendar-based month calculation instead of dividing by 30
            stats.totalMonths = calculateActualMonths(scheduleData.startDate, scheduleData.endDate);

            // Calculate total hours based on time mode
            if (scheduleData.timeData.mode === 'single') {
                const duration = parseFloat(scheduleData.timeData.duration) || 0;
                const daysPerWeek = scheduleData.selectedDays.length || 1;

                if (scheduleData.pattern === 'weekly') {
                    stats.totalClasses = stats.totalWeeks * daysPerWeek;
                } else if (scheduleData.pattern === 'biweekly') {
                    stats.totalClasses = Math.ceil(stats.totalWeeks / 2) * daysPerWeek;
                } else if (scheduleData.pattern === 'monthly') {
                    stats.totalClasses = stats.totalMonths;
                }

                stats.totalHours = stats.totalClasses * duration;
            } else {
                // Per-day mode calculation
                const perDayTimes = scheduleData.timeData.perDayTimes;
                let totalWeeklyHours = 0;

                Object.values(perDayTimes).forEach(dayData => {
                    totalWeeklyHours += dayData.duration;
                });

                if (scheduleData.pattern === 'weekly') {
                    stats.totalHours = stats.totalWeeks * totalWeeklyHours;
                    stats.totalClasses = stats.totalWeeks * Object.keys(perDayTimes).length;
                } else if (scheduleData.pattern === 'biweekly') {
                    stats.totalHours = Math.ceil(stats.totalWeeks / 2) * totalWeeklyHours;
                    stats.totalClasses = Math.ceil(stats.totalWeeks / 2) * Object.keys(perDayTimes).length;
                }
            }

            stats.avgHoursPerMonth = stats.totalMonths > 0 ? (stats.totalHours / stats.totalMonths).toFixed(1) : 0;
            stats.actualTrainingDays = stats.totalClasses - stats.exceptionDates;

            // Calculate holidays affecting classes
            stats.holidaysAffecting = countHolidaysAffectingClasses(scheduleData);
        }

        return stats;
    }

    /**
     * Count holidays that affect class schedule
     */
    function countHolidaysAffectingClasses(scheduleData) {
        // Check if we have holidays data and selected days
        if (!scheduleData.selectedDays || scheduleData.selectedDays.length === 0) {
            return 0;
        }

        // Check if we have public holidays data
        if (typeof window.wecozaPublicHolidays === 'undefined' || !window.wecozaPublicHolidays.events) {
            return 0;
        }

        // Get holidays within the schedule date range
        if (!scheduleData.startDate || !scheduleData.endDate) {
            return 0;
        }

        const startDate = new Date(scheduleData.startDate);
        const endDate = new Date(scheduleData.endDate);

        // Filter holidays to only include those within the date range
        const holidaysInRange = window.wecozaPublicHolidays.events.filter(holiday => {
            const [year, month, day] = holiday.start.split('-').map(Number);
            const holidayDate = new Date(year, month - 1, day);
            return holidayDate >= startDate && holidayDate <= endDate;
        });

        let count = 0;
        const selectedDays = scheduleData.selectedDays;
        const dayIndices = selectedDays.map(day => getDayIndex(day));

        // Loop through each holiday in range
        holidaysInRange.forEach(holiday => {
            // Parse the date parts to ensure correct date (avoid timezone issues)
            const [year, month, day] = holiday.start.split('-').map(Number);
            const holidayDate = new Date(year, month - 1, day);
            const dayOfWeek = holidayDate.getDay();

            // Check if this holiday falls on a selected day and is not overridden
            const dateStr = holiday.start;
            let isOverridden = false;

            // Check if this holiday has been overridden
            if (typeof window.holidayOverrides === 'object' && window.holidayOverrides[dateStr]) {
                const overrideValue = window.holidayOverrides[dateStr];
                isOverridden = typeof overrideValue === 'boolean' ? overrideValue : overrideValue.override === true;
            }

            if (dayIndices.includes(dayOfWeek) && !isOverridden) {
                count++;
            }
        });

        return count;
    }

    /**
     * Get form data ready for submission
     * Includes validation and final data preparation
     */
    function getFormSubmissionData() {
        // Validate all time selections
        const timeValidation = getTimeValidationSummary();

        if (!timeValidation.canSubmit) {
            return {
                isValid: false,
                errors: ['Please fix time validation errors before submitting.'],
                data: null
            };
        }

        // Collect final schedule data
        const scheduleData = collectScheduleData();

        // Validate required fields
        const requiredFieldsValid = validateRequiredScheduleFields(scheduleData);

        if (!requiredFieldsValid.isValid) {
            return {
                isValid: false,
                errors: requiredFieldsValid.errors,
                data: null
            };
        }

        return {
            isValid: true,
            errors: [],
            data: scheduleData
        };
    }

    /**
     * Validate required schedule fields for submission
     */
    function validateRequiredScheduleFields(scheduleData) {
        const errors = [];

        if (!scheduleData.pattern) {
            errors.push('Schedule pattern is required.');
        }

        if (!scheduleData.startDate) {
            errors.push('Start date is required.');
        }

        if (scheduleData.pattern === 'monthly' && !scheduleData.dayOfMonth) {
            errors.push('Day of month is required for monthly pattern.');
        }

        if ((scheduleData.pattern === 'weekly' || scheduleData.pattern === 'biweekly') && scheduleData.selectedDays.length === 0) {
            errors.push('At least one day must be selected for weekly/biweekly patterns.');
        }

        // Validate time data
        if (scheduleData.timeData.mode === 'single') {
            if (!scheduleData.timeData.startTime || !scheduleData.timeData.endTime) {
                errors.push('Start time and end time are required.');
            }
        } else {
            if (Object.keys(scheduleData.timeData.perDayTimes).length === 0) {
                errors.push('Time data is required for all selected days.');
            }
        }

        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }



    /**
     * Check if a date falls within any stop/restart period (class is stopped)
     */
    function isDateInStopPeriod(dateStr, stopRestartPeriods) {
        for (const period of stopRestartPeriods) {
            const stopDate = new Date(period.stopDate);
            const restartDate = new Date(period.restartDate);
            const checkDate = new Date(dateStr);

            // Check if the date falls between stop date (inclusive) and restart date (exclusive)
            // Note: restart date is the day classes resume, so it should NOT be in the stop period
            if (checkDate >= stopDate && checkDate < restartDate) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recalculate end date based on class type, start date, selected days, and per-day durations
     */
    function recalculateEndDate() {

        const startDate = $('#schedule_start_date').val();
        const classType = $('#class_type').val();
        const pattern = $('#schedule_pattern').val();


        // Get session duration from per-day time data or fallback
        let sessionDuration = 0;
        let hoursPerWeek = 0;
        let useWeeklyCalculation = false;
        const timeData = getAllTimeData();

        if (timeData.mode === 'per-day' && timeData.perDayTimes && (pattern === 'weekly' || pattern === 'biweekly')) {
            // For weekly/biweekly patterns with per-day times, calculate total hours per week
            const selectedDays = getSelectedDays();
            selectedDays.forEach(day => {
                if (timeData.perDayTimes[day] && timeData.perDayTimes[day].duration) {
                    hoursPerWeek += parseFloat(timeData.perDayTimes[day].duration);
                }
            });

            // For biweekly, this represents hours every two weeks
            if (pattern === 'biweekly') {
                hoursPerWeek = hoursPerWeek; // Keep as is - we'll handle biweekly in the main loop
            }

            useWeeklyCalculation = true;
        } else if (timeData.mode === 'per-day' && timeData.perDayTimes) {
            // For other patterns, use average duration
            const durations = Object.values(timeData.perDayTimes).map(day => day.duration);
            if (durations.length > 0) {
                sessionDuration = durations.reduce((sum, duration) => sum + duration, 0) / durations.length;
            }
        } else if (timeData.duration) {
            sessionDuration = parseFloat(timeData.duration);
        }


        if (startDate && classType && pattern && (sessionDuration > 0 || hoursPerWeek > 0)) {
            // Get total hours for this class type
            const classHours = getClassTypeHours(classType);
            $('#schedule_total_hours').val(classHours);


            if (classHours > 0) {
                // Get exception dates
                const exceptionDates = [];
                const $exceptionRows = $('#exception-dates-container .exception-date-row');


                $exceptionRows.each(function (index) {
                    const $row = $(this);
                    // Skip template row (has id)
                    if ($row.attr('id') === 'exception-date-row-template') {
                        return;
                    }

                    const date = $row.find('input[name="exception_dates[]"]').val();
                    const reason = $row.find('select[name="exception_reasons[]"]').val();
                    if (date) {
                        exceptionDates.push(date);
                    } else {
                    }
                });


                // Get stop/restart dates
                const stopRestartPeriods = [];
                $('#date-history-container .date-history-row:not(.d-none)').each(function () {
                    const stopDate = $(this).find('input[name="stop_dates[]"]').val();
                    const restartDate = $(this).find('input[name="restart_dates[]"]').val();

                    if (stopDate && restartDate) {
                        stopRestartPeriods.push({
                            stopDate: stopDate,
                            restartDate: restartDate
                        });
                    }
                });


                // Calculate number of sessions/weeks needed based on calculation method
                let unitsNeeded = 0;
                let unitType = 'sessions';

                if (useWeeklyCalculation && hoursPerWeek > 0) {
                    // Calculate weeks needed
                    unitsNeeded = Math.ceil(classHours / hoursPerWeek);
                    unitType = 'weeks';
                } else {
                    // Calculate sessions needed
                    unitsNeeded = Math.ceil(classHours / sessionDuration);
                    unitType = 'sessions';
                }

                // Create session tracking array for debugging
                const sessionLog = [];

                // Calculate end date based on schedule pattern and exception dates
                if (pattern && startDate) {
                    let date = new Date(startDate);
                    let unitsScheduled = 0;

                    // Weekly pattern
                    if (pattern === 'weekly') {
                        const selectedDays = getSelectedDays();

                        if (selectedDays.length === 0) {
                            return; // Can't calculate without selected days
                        }

                        // Convert selected days to day indices
                        const dayIndices = selectedDays.map(day => getDayIndex(day));

                        // Enhanced debug logging

                        // Set start date to the first occurrence of any selected day
                        const currentDayIndex = date.getDay();
                        if (!dayIndices.includes(currentDayIndex)) {
                            // Find the next occurrence of any selected day
                            let daysToAdd = 1;
                            let nextDate = new Date(date);
                            nextDate.setDate(nextDate.getDate() + daysToAdd);

                            while (!dayIndices.includes(nextDate.getDay())) {
                                daysToAdd++;
                                nextDate = new Date(date);
                                nextDate.setDate(nextDate.getDate() + daysToAdd);
                            }

                            date = nextDate;
                        }

                        // Track weeks for weekly calculation
                        let currentWeekDays = [];
                        let lastWeekStart = null;

                        // Add days until we have enough units (sessions or weeks)
                        while (unitsScheduled < unitsNeeded) {
                            const dateStr = date.toISOString().split('T')[0];
                            const currentDayIndex = date.getDay();

                            // Check if this date is a public holiday
                            let isPublicHoliday = false;
                            let isHolidayOverridden = false;

                            if (typeof window.wecozaPublicHolidays !== 'undefined' && window.wecozaPublicHolidays.events) {
                                const matchingHoliday = window.wecozaPublicHolidays.events.find(holiday => {
                                    return holiday.start === dateStr;
                                });

                                if (matchingHoliday) {
                                    isPublicHoliday = true;
                                    // Check if this holiday has been overridden
                                    const overrideValue = window.holidayOverrides[dateStr];
                                    if (typeof window.holidayOverrides === 'object' && overrideValue && (typeof overrideValue === 'boolean' ? overrideValue : overrideValue.override === true)) {
                                        isHolidayOverridden = true;
                                    }
                                }
                            }

                            // Skip exception dates, stop periods, and public holidays (unless overridden)
                            // Only count days that are in our selected days list
                            const isExceptionDate = exceptionDates.includes(dateStr);
                            const isInStopPeriod = isDateInStopPeriod(dateStr, stopRestartPeriods);

                            if (isExceptionDate) {
                            }

                            if (dayIndices.includes(currentDayIndex) &&
                                !isExceptionDate &&
                                !isInStopPeriod &&
                                (!isPublicHoliday || isHolidayOverridden)) {

                                if (useWeeklyCalculation) {
                                    // For weekly calculation, track which days of the week we've scheduled
                                    const weekStart = new Date(date);
                                    weekStart.setDate(date.getDate() - date.getDay()); // Get Sunday of this week
                                    const weekStartStr = weekStart.toISOString().split('T')[0];

                                    // Check if this is a new week
                                    if (weekStartStr !== lastWeekStart) {
                                        // New week - check if we completed the previous week
                                        if (currentWeekDays.length > 0) {
                                            unitsScheduled++;
                                            if (unitsScheduled <= 5 || unitsScheduled >= unitsNeeded - 2) {
                                            }

                                            // Check if we've reached our target weeks
                                            if (unitsScheduled >= unitsNeeded) {
                                                // We've scheduled enough weeks - use the last scheduled date
                                                const lastScheduledDate = currentWeekDays[currentWeekDays.length - 1];
                                                date = new Date(lastScheduledDate);
                                                break;
                                            }
                                        }
                                        currentWeekDays = [];
                                        lastWeekStart = weekStartStr;
                                    }

                                    // Add this day to the current week
                                    currentWeekDays.push(dateStr);

                                    // Add to session log
                                    sessionLog.push({
                                        sessionNumber: `Week ${unitsScheduled + 1} - Day ${currentWeekDays.length}`,
                                        date: dateStr,
                                        dayName: getDayName(currentDayIndex),
                                        isHoliday: isPublicHoliday,
                                        isHolidayOverridden: isHolidayOverridden,
                                        isException: false,
                                        isInStopPeriod: false,
                                        status: 'scheduled'
                                    });

                                    if (unitsScheduled < 3 || (currentWeekDays.length === 1 && unitsScheduled >= unitsNeeded - 2)) {
                                    }
                                } else {
                                    // Regular session-based calculation
                                    unitsScheduled++;

                                    // Add to session log
                                    sessionLog.push({
                                        sessionNumber: unitsScheduled,
                                        date: dateStr,
                                        dayName: getDayName(currentDayIndex),
                                        isHoliday: isPublicHoliday,
                                        isHolidayOverridden: isHolidayOverridden,
                                        isException: false,
                                        isInStopPeriod: false,
                                        status: 'scheduled'
                                    });

                                    if (unitsScheduled <= 5 || unitsScheduled >= unitsNeeded - 2) {
                                    }

                                    // Safety check - break if we've reached our target
                                    if (unitsScheduled >= unitsNeeded) {
                                        break;
                                    }
                                }
                            } else {
                                // Debug why this day was skipped
                                const dayName = getDayName(currentDayIndex);
                                const isSelectedDay = dayIndices.includes(currentDayIndex);

                                if (isSelectedDay) {
                                    sessionLog.push({
                                        sessionNumber: 'skipped',
                                        date: dateStr,
                                        dayName: dayName,
                                        isHoliday: isPublicHoliday,
                                        isException: isExceptionDate,
                                        isInStopPeriod: isInStopPeriod,
                                        status: 'skipped',
                                        reason: isExceptionDate ? 'exception' : isInStopPeriod ? 'stop_period' : isPublicHoliday ? 'holiday' : 'unknown'
                                    });

                                    // Log important skips
                                    if (isPublicHoliday) {
                                    }
                                    if (isExceptionDate || isInStopPeriod) {
                                    }
                                }

                            }

                            // Move to next day
                            date.setDate(date.getDate() + 1);

                            // Emergency break to prevent infinite loops
                            if (unitsScheduled > unitsNeeded + 100) {
                                console.error('🚨 EMERGENCY BREAK: Too many units scheduled!', unitsScheduled, 'vs needed:', unitsNeeded);
                                break;
                            }
                        }

                        // For weekly calculation, check if we need to count the last partial week
                        if (useWeeklyCalculation && currentWeekDays.length > 0 && unitsScheduled < unitsNeeded) {
                            unitsScheduled++;

                            // Use the last scheduled date from the current week
                            if (currentWeekDays.length > 0) {
                                const lastScheduledDate = currentWeekDays[currentWeekDays.length - 1];
                                date = new Date(lastScheduledDate);
                            }
                        }
                    }
                    // Bi-weekly pattern
                    else if (pattern === 'biweekly') {
                        const selectedDays = getSelectedDays();

                        if (selectedDays.length === 0) {
                            return; // Can't calculate without selected days
                        }

                        // Convert selected days to day indices
                        const dayIndices = selectedDays.map(day => getDayIndex(day));

                        // Debug logging for multiple day selection

                        // Set start date to the first occurrence of any selected day
                        const currentDayIndex = date.getDay();
                        if (!dayIndices.includes(currentDayIndex)) {
                            // Find the next occurrence of any selected day
                            let daysToAdd = 1;
                            let nextDate = new Date(date);
                            nextDate.setDate(nextDate.getDate() + daysToAdd);

                            while (!dayIndices.includes(nextDate.getDay())) {
                                daysToAdd++;
                                nextDate = new Date(date);
                                nextDate.setDate(nextDate.getDate() + daysToAdd);
                            }

                            date = nextDate;
                        }

                        // Track which week we're in (0 = first week, 1 = second week)
                        let weekCounter = 0;

                        // Add days until we have enough sessions
                        while (unitsScheduled < unitsNeeded) {
                            const dateStr = date.toISOString().split('T')[0];
                            const currentDayIndex = date.getDay();

                            // Check if this date is a public holiday
                            let isPublicHoliday = false;
                            let isHolidayOverridden = false;

                            if (typeof window.wecozaPublicHolidays !== 'undefined' && window.wecozaPublicHolidays.events) {
                                const matchingHoliday = window.wecozaPublicHolidays.events.find(holiday => {
                                    return holiday.start === dateStr;
                                });

                                if (matchingHoliday) {
                                    isPublicHoliday = true;
                                    // Check if this holiday has been overridden
                                    const overrideValue = window.holidayOverrides[dateStr];
                                    if (typeof window.holidayOverrides === 'object' && overrideValue && (typeof overrideValue === 'boolean' ? overrideValue : overrideValue.override === true)) {
                                        isHolidayOverridden = true;
                                    }
                                }
                            }

                            // Skip exception dates, stop periods, and public holidays (unless overridden)
                            // Only count days that are in our selected days list and in the first week of the biweek
                            if (dayIndices.includes(currentDayIndex) &&
                                weekCounter === 0 &&
                                !exceptionDates.includes(dateStr) &&
                                !isDateInStopPeriod(dateStr, stopRestartPeriods) &&
                                (!isPublicHoliday || isHolidayOverridden)) {
                                unitsScheduled++;
                            } else {
                            }

                            // Move to next day
                            date.setDate(date.getDate() + 1);

                            // Update week counter (0 = first week, 1 = second week)
                            if (date.getDay() === 0) { // If it's Sunday
                                weekCounter = (weekCounter + 1) % 2;
                            }
                        }
                    }
                    // Monthly pattern
                    else if (pattern === 'monthly') {
                        const dayOfMonth = $('#schedule_day_of_month').val();

                        // Add months until we have enough sessions
                        while (unitsScheduled < unitsNeeded) {
                            let dateToUse = new Date(date);

                            if (dayOfMonth === 'last') {
                                // Set to last day of the month
                                dateToUse = new Date(date.getFullYear(), date.getMonth() + 1, 0);
                            } else {
                                // Set to specific day of month
                                dateToUse.setDate(parseInt(dayOfMonth));

                                // If day is beyond the end of the month, move to next month
                                if (dateToUse.getMonth() !== date.getMonth()) {
                                    date.setMonth(date.getMonth() + 1);
                                    date.setDate(1);
                                    continue;
                                }
                            }

                            const dateStr = dateToUse.toISOString().split('T')[0];

                            // Check if this date is a public holiday
                            let isPublicHoliday = false;
                            let isHolidayOverridden = false;

                            if (typeof window.wecozaPublicHolidays !== 'undefined' && window.wecozaPublicHolidays.events) {
                                const matchingHoliday = window.wecozaPublicHolidays.events.find(holiday => {
                                    return holiday.start === dateStr;
                                });

                                if (matchingHoliday) {
                                    isPublicHoliday = true;
                                    // Check if this holiday has been overridden
                                    const overrideValue = window.holidayOverrides[dateStr];
                                    if (typeof window.holidayOverrides === 'object' && overrideValue && (typeof overrideValue === 'boolean' ? overrideValue : overrideValue.override === true)) {
                                        isHolidayOverridden = true;
                                    }
                                }
                            }

                            // Skip exception dates, stop periods, and public holidays (unless overridden)
                            if (!exceptionDates.includes(dateStr) &&
                                !isDateInStopPeriod(dateStr, stopRestartPeriods) &&
                                (!isPublicHoliday || isHolidayOverridden)) {
                                unitsScheduled++;
                            }

                            // Move to next month
                            date.setMonth(date.getMonth() + 1);
                            date.setDate(1);
                        }
                    }

                    // The date variable is now one day past the last scheduled session
                    // We need to move back to the actual last session date
                    let finalEndDate = date;

                    // Find the last scheduled session from our log
                    const lastScheduledSession = sessionLog.filter(s => s.status === 'scheduled').pop();
                    if (lastScheduledSession) {
                        finalEndDate = new Date(lastScheduledSession.date);
                    } else {
                        // If no sessions in log, move back one day as we've advanced past the end
                        finalEndDate.setDate(finalEndDate.getDate() - 1);
                    }

                    // Format date as YYYY-MM-DD
                    const endDate = finalEndDate.toISOString().split('T')[0];
                    $('#schedule_end_date').val(endDate);


                    // Validate that calculated hours match expected hours
                    let calculatedHours = 0;
                    if (useWeeklyCalculation) {
                        calculatedHours = unitsScheduled * hoursPerWeek;
                    } else {
                        calculatedHours = unitsScheduled * sessionDuration;
                    }
                    const expectedHours = parseFloat($('#class_duration').val()) || 0;
                    const hoursDifference = Math.abs(calculatedHours - expectedHours);


                    // Show warning if there's a significant mismatch (more than 0.1 hours difference)
                    if (hoursDifference > 0.1) {
                        console.warn('⚠️ Hours mismatch detected!');
                        console.warn('Expected hours:', expectedHours);
                        console.warn('Calculated hours:', calculatedHours);
                        console.warn('Difference:', hoursDifference.toFixed(2), 'hours');

                        // You could also show a visual warning to the user here if needed
                        // For example: $('#hours-warning').show().text('Warning: Calculated hours (' + calculatedHours + ') do not match expected hours (' + expectedHours + ')');
                    }


                    // Update schedule tables
                    updateScheduleTables();
                }
            }
        }
    }

    /**
     * Update schedule tables with current data
     */
    function updateScheduleTables() {
        // Get schedule data
        const pattern = $('#schedule_pattern').val();
        const startDate = $('#schedule_start_date').val();
        const endDate = $('#schedule_end_date').val();

        // Get time data from current mode
        const timeData = getAllTimeData();
        let startTime = '';
        let endTime = '';

        if (timeData.mode === 'single') {
            startTime = timeData.startTime || '';
            endTime = timeData.endTime || '';
        } else if (timeData.mode === 'per-day' && timeData.perDayTimes) {
            // Use first day's times for display
            const firstDay = Object.keys(timeData.perDayTimes)[0];
            if (firstDay) {
                startTime = timeData.perDayTimes[firstDay].startTime || '';
                endTime = timeData.perDayTimes[firstDay].endTime || '';
            }
        }

        // Format pattern for display
        let patternDisplay = '';
        switch (pattern) {
            case 'weekly':
                patternDisplay = 'Weekly';
                break;
            case 'biweekly':
                patternDisplay = 'Bi-weekly';
                break;
            case 'monthly':
                patternDisplay = 'Monthly';
                break;
            case 'custom':
                patternDisplay = 'Custom';
                break;
            default:
                patternDisplay = pattern;
        }

        // Format dates for display
        const formatDate = function (dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-ZA', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        // Update schedule summary table if it exists
        $('#schedule-summary-pattern').text(patternDisplay);
        $('#schedule-summary-start-date').text(formatDate(startDate));
        $('#schedule-summary-end-date').text(formatDate(endDate));
        $('#schedule-summary-class-time').text(startTime && endTime ? `${startTime} - ${endTime}` : '');

        // Get selected days
        const selectedDays = getSelectedDays();
        $('#schedule-summary-days').text(selectedDays.join(', '));

        // Update exception dates table if function exists
        if (typeof updateExceptionDatesTable === 'function') {
            updateExceptionDatesTable();
        }

        // Update holidays table if function exists
        if (typeof updateHolidaysTable === 'function') {
            updateHolidaysTable(startDate, endDate);
        }
    }

    /**
     * Update exception dates table
     */
    function updateExceptionDatesTable() {
        const $exceptionDatesTable = $('#exception-dates-table');
        const $noExceptionDatesRow = $('#no-exception-dates-row');

        if (!$exceptionDatesTable.length) {
            return; // No exception dates table found
        }

        // Get all exception date rows
        const exceptionDates = [];
        $('#exception-dates-container .exception-date-row:not(.d-none)').each(function () {
            const $row = $(this);
            const date = $row.find('input[name="exception_dates[]"]').val();
            const reason = $row.find('select[name="exception_reasons[]"]').val();

            if (date) {
                exceptionDates.push({
                    date: date,
                    reason: reason || ''
                });
            }
        });

        // Clear existing rows except the "no exception dates" row
        $exceptionDatesTable.find('tr:not(#no-exception-dates-row)').remove();

        // Show/hide "no exception dates" row
        if (exceptionDates.length === 0) {
            $noExceptionDatesRow.show();
            return;
        } else {
            $noExceptionDatesRow.hide();
        }

        // Format dates for display
        const formatDate = function (dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-ZA', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        // Add a row for each exception date
        exceptionDates.forEach(function (exceptionDate) {
            const formattedDate = formatDate(exceptionDate.date);
            const reasonText = exceptionDate.reason || 'No reason specified';

            const $row = $('<tr></tr>');
            $row.append('<td>' + formattedDate + '</td>');
            $row.append('<td>' + reasonText + '</td>');

            $exceptionDatesTable.append($row);
        });
    }

    /**
     * Update holidays table
     */
    function updateHolidaysTable(startDate, endDate) {
        const $holidaysTable = $('#holidays-table');
        const $noHolidaysRow = $('#no-holidays-row');
        const selectedDays = getSelectedDays();
        const pattern = $('#schedule_pattern').val();

        if (!$holidaysTable.length) {
            return; // No holidays table found
        }

        // If no public holidays data, show "no holidays" row and return
        if (typeof window.wecozaPublicHolidays === 'undefined' || !window.wecozaPublicHolidays.events) {
            $holidaysTable.find('tr:not(#no-holidays-row)').remove();
            $noHolidaysRow.show();
            return;
        }

        // If no start date or end date, show "no holidays" row and return
        if (!startDate || !endDate) {
            $holidaysTable.find('tr:not(#no-holidays-row)').remove();
            $noHolidaysRow.show();
            return;
        }

        // Convert dates to Date objects for comparison
        const startDateObj = new Date(startDate);
        const endDateObj = new Date(endDate);

        // Filter holidays to only include those within the date range
        const holidaysInRange = window.wecozaPublicHolidays.events.filter(holiday => {
            // Parse the date parts to ensure correct date (avoid timezone issues)
            const [year, month, day] = holiday.start.split('-').map(Number);
            const holidayDate = new Date(year, month - 1, day);
            return holidayDate >= startDateObj && holidayDate <= endDateObj;
        });

        // Filter to only include holidays that conflict with the schedule
        const conflictingHolidays = holidaysInRange.filter(holiday => {
            // Parse the date parts to ensure correct date (avoid timezone issues)
            const [year, month, day] = holiday.start.split('-').map(Number);
            const holidayDate = new Date(year, month - 1, day);
            const dayName = holidayDate.toLocaleDateString('en-US', { weekday: 'long' });

            // For weekly/biweekly patterns, check if the holiday falls on a selected day
            return (pattern === 'weekly' || pattern === 'biweekly') && selectedDays.includes(dayName);
        });

        // Clear existing rows except the "no holidays" row
        $holidaysTable.find('tr:not(#no-holidays-row)').remove();

        // Show/hide "no holidays" row
        if (conflictingHolidays.length === 0) {
            $noHolidaysRow.show();
            return;
        } else {
            $noHolidaysRow.hide();
        }

        // Format dates for display
        const formatDate = function (dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-ZA', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        // Add a row for each holiday
        conflictingHolidays.forEach(function (holiday) {
            const formattedDate = formatDate(holiday.start);
            const holidayName = holiday.title;

            // Check if this holiday has been overridden
            let isOverridden = false;
            if (typeof window.holidayOverrides === 'object' && window.holidayOverrides[holiday.start]) {
                const overrideValue = window.holidayOverrides[holiday.start];
                isOverridden = typeof overrideValue === 'boolean' ? overrideValue : overrideValue.override;
            }

            const $row = $('<tr></tr>');
            $row.append('<td>' + formattedDate + '</td>');
            $row.append('<td>' + holidayName + '</td>');

            // Add status badge
            if (isOverridden) {
                $row.append('<td><span class="badge bg-warning text-dark">Included</span></td>');
            } else {
                $row.append('<td><span class="badge bg-danger">Skipped</span></td>');
            }

            $holidaysTable.append($row);
        });
    }

    function restrictStartDateBySelectedDays() {
        // Placeholder for start date restriction functionality
    }

    // Initialize when document is ready
    $(document).ready(function () {
        // Check if we're on a page with the class schedule form
        if ($('#schedule_pattern').length > 0) {
            initClassScheduleForm();
        } else {
            // Try again after a short delay in case of timing issues
            setTimeout(function () {
                if ($('#schedule_pattern').length > 0) {
                    initClassScheduleForm();
                }
            }, 1000);
        }
    });

})(jQuery);
