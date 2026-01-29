/**
 * Class Types & Durations JavaScript for WeCoza Classes Plugin
 *
 * Handles the two-level selection system for class types and subjects,
 * and implements automatic duration calculation.
 * Extracted from WeCoza theme for standalone plugin
 */

/**
 * Global variable to store the AJAX URL
 * This will be set by WordPress via wp_localize_script
 */
var wecozaClass = wecozaClass || {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    debug: true
};

/**
 * Auto-populate learner level selects based on selected class subject
 * This function is globally accessible for use by other scripts
 *
 * @param {string} subjectId The selected subject ID
 */
function classes_populate_learner_levels(subjectId) {
    // Find all learner level select elements in the class learners table
    const learnerLevelSelects = document.querySelectorAll('#class-learners-table .learner-level-select');

    if (learnerLevelSelects.length === 0) {
        return;
    }

    // The subject ID is already the level ID we want to use
    if (subjectId && subjectId.trim() !== '') {
        learnerLevelSelects.forEach(function(select) {
            // Set the value to the subject ID
            select.value = subjectId;

            // Trigger change event using jQuery to ensure compatibility with jQuery event handlers
            if (typeof $ !== 'undefined') {
                $(select).trigger('change');
            } else {
                // Fallback to native event if jQuery is not available
                select.dispatchEvent(new Event('change'));
            }
        });
    } else {
        // If no subject selected, reset all selects
        learnerLevelSelects.forEach(function(select) {
            select.value = '';
            // Trigger change event using jQuery to ensure compatibility with jQuery event handlers
            if (typeof $ !== 'undefined') {
                $(select).trigger('change');
            } else {
                // Fallback to native event if jQuery is not available
                select.dispatchEvent(new Event('change'));
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const classTypeSelect = document.getElementById('class_type');
    const classSubjectSelect = document.getElementById('class_subject');
    const classDurationInput = document.getElementById('class_duration');
    const classCodeInput = document.getElementById('class_code');

    // Get subject wrapper for show/hide
    const classSubjectWrapper = classSubjectSelect ? classSubjectSelect.closest('.col-md-3') : null;

    // Progression types don't need subject selection
    const progressionTypes = ['GETC', 'BA2', 'BA3', 'BA4'];

    // Class subjects data (will be populated via AJAX)
    let classSubjectsData = {};

    // Event listener for client change
    const clientSelect = document.getElementById('client_id');
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            // Regenerate class code when client changes
            regenerateClassCode();
        });
    }

    // Event listener for class type change
    if (classTypeSelect) {
        classTypeSelect.addEventListener('change', function() {
            const selectedClassType = this.value;
            const isProgressionType = progressionTypes.includes(selectedClassType);

            // Hide subject field for progression types (GETC, BA2, BA3, BA4)
            if (classSubjectWrapper) {
                classSubjectWrapper.style.display = isProgressionType ? 'none' : '';
                classSubjectSelect.required = !isProgressionType;
            }

            // Reset duration and code
            classDurationInput.value = '';
            classCodeInput.value = '';

            if (isProgressionType) {
                // Set placeholder value for progression types
                classSubjectSelect.innerHTML = '<option value="LP" selected>Learner Progression</option>';
                classSubjectSelect.value = 'LP';
                classSubjectSelect.disabled = false;
                // Generate class code
                classCodeInput.value = generateClassCode();
                // Fetch and set duration for progression type
                fetchProgressionDuration(selectedClassType);
            } else {
                // Reset subject dropdown
                classSubjectSelect.innerHTML = '<option value="">Select Subject</option>';
                classSubjectSelect.disabled = !selectedClassType;

                if (selectedClassType) {
                    // Fetch subjects for the selected class type
                    fetchClassSubjects(selectedClassType);
                }
            }
        });
    }

    // Event listener for class subject change
    if (classSubjectSelect) {
        classSubjectSelect.addEventListener('change', function() {
            const selectedClassType = classTypeSelect.value;
            const selectedSubject = this.value;
            const selectedClientId = document.getElementById('client_id')?.value;

            // Auto-population only happens on button clicks, not subject changes

            if (selectedClassType && selectedSubject) {
                // Find the selected subject in the data
                const subjectData = classSubjectsData[selectedClassType].find(
                    subject => subject.id === selectedSubject
                );

                if (subjectData) {
                    // Set duration
                    classDurationInput.value = subjectData.duration;

                    // Generate class code (function now gets client from DOM)
                    classCodeInput.value = generateClassCode();
                }
            } else {
                // Reset duration and code
                classDurationInput.value = '';
                classCodeInput.value = '';
            }
        });
    }

    /**
     * Fetch class subjects for the selected class type
     *
     * @param {string} classType The selected class type
     */
    function fetchClassSubjects(classType) {
        // Show loading indicator
        classSubjectSelect.innerHTML = '<option value="">Loading...</option>';

        // Get the AJAX URL from WordPress
        const ajaxUrl = (typeof wecozaClass !== 'undefined' && wecozaClass.ajaxUrl)
            ? wecozaClass.ajaxUrl
            : '/wp-admin/admin-ajax.php';

        // Make AJAX request to get subjects
        const requestUrl = `${ajaxUrl}?action=get_class_subjects&class_type=${classType}`;

        fetch(requestUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Extract the subjects array from the response
                    let subjectsArray = [];

                    // Check if data.data exists and is an array
                    if (data.data && Array.isArray(data.data)) {
                        subjectsArray = data.data;
                    }
                    // Try to convert object to array if needed
                    else if (data.data) {
                        console.log('Data is not in expected format, attempting to convert:', data.data);
                        if (typeof data.data === 'object') {
                            subjectsArray = Object.values(data.data);
                            console.log('Converted object to array:', subjectsArray);
                        }
                    }

                    // Store subjects data for later use
                    classSubjectsData[classType] = subjectsArray;

                    // Reset dropdown
                    classSubjectSelect.innerHTML = '<option value="">Select Subject</option>';

                    // Add options to dropdown
                    if (subjectsArray.length > 0) {
                        subjectsArray.forEach(subject => {
                            if (subject && subject.id && subject.name) {
                                const option = document.createElement('option');
                                option.value = subject.id;
                                option.textContent = subject.name;
                                classSubjectSelect.appendChild(option);
                            }
                        });
                    } else {
                        console.error('No valid subjects found in the response');
                        classSubjectSelect.innerHTML = '<option value="">No subjects available</option>';
                    }
                } else {
                    // Handle error
                    classSubjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                    console.error('Error loading class subjects:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                // Handle error
                classSubjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                console.error('Error loading class subjects:', error);

                // Show more detailed error in the dropdown for debugging
                if (typeof wecozaClass !== 'undefined' && wecozaClass.debug) {
                    // Secure fallback - fail closed, not open
                    const fallbackEsc = function(s) {
                        if (s === null || s === undefined) return '';
                        var div = document.createElement('div');
                        div.textContent = String(s);
                        return div.innerHTML;
                    };
                    const esc = window.WeCozaUtils ? window.WeCozaUtils.escapeHtml : fallbackEsc;
                    classSubjectSelect.innerHTML = `<option value="">Error: ${esc(error.message)}</option>`;
                }
            });
    }

    /**
     * Fetch and set duration for progression class types (GETC, BA2, BA3, BA4)
     *
     * @param {string} classType The selected progression class type
     */
    function fetchProgressionDuration(classType) {
        const ajaxUrl = (typeof wecozaClass !== 'undefined' && wecozaClass.ajaxUrl)
            ? wecozaClass.ajaxUrl
            : '/wp-admin/admin-ajax.php';

        fetch(`${ajaxUrl}?action=get_class_subjects&class_type=${classType}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data[0] && data.data[0].duration) {
                    classDurationInput.value = data.data[0].duration;
                }
            })
            .catch(error => console.error('Error fetching progression duration:', error));
    }

    /**
     * Helper function to regenerate class code when any required field changes
     */
    function regenerateClassCode() {
        const classCodeInput = document.getElementById('class_code');
        if (classCodeInput) {
            classCodeInput.value = generateClassCode();
        }
    }

    /**
     * Generate a simple 9-character class code from client name and timestamp
     *
     * Format: [ABC][MMDDHR] where ABC = first 3 letters of client name, MMDDHR = month-day-hour
     * Example: AGR102214 = AGR (from "AGR Limited") + 10 (Oct) + 22 (22nd) + 14 (2pm)
     *
     * @return {string} The generated class code (9 characters)
     */
    function generateClassCode() {
        // Get client name from dropdown
        const clientSelect = document.getElementById('client_id');
        if (!clientSelect || !clientSelect.value) {
            return '';
        }

        const selectedOption = clientSelect.options[clientSelect.selectedIndex];
        const clientName = selectedOption.text;

        // Extract first 3 uppercase letters from client name
        const prefix = clientName
            .replace(/[^a-zA-Z]/g, '') // Remove non-letter characters
            .substring(0, 3)
            .toUpperCase()
            .padEnd(3, 'X'); // Pad with X if less than 3 letters

        // Get current date/time components
        const now = new Date();
        const month = (now.getMonth() + 1).toString().padStart(2, '0');
        const day = now.getDate().toString().padStart(2, '0');
        const hour = now.getHours().toString().padStart(2, '0');

        // Format: [ABC][MMDDHR]
        return `${prefix}${month}${day}${hour}`;
    }



    // Initialize on page load if elements exist
    if (classTypeSelect && classSubjectSelect) {
        // Check if we have pre-selected values (for update mode)
        const preSelectedType = classTypeSelect.value;
        const preSelectedSubject = classSubjectSelect.value;
        const isProgressionType = progressionTypes.includes(preSelectedType);

        // Hide subject field on load if progression type
        if (isProgressionType && classSubjectWrapper) {
            classSubjectWrapper.style.display = 'none';
            classSubjectSelect.required = false;
            classSubjectSelect.innerHTML = '<option value="LP" selected>Learner Progression</option>';
            classSubjectSelect.value = 'LP';
            // Fetch and set duration for pre-selected progression type
            fetchProgressionDuration(preSelectedType);
        } else if (preSelectedType && preSelectedSubject) {
            // Fetch subjects for the pre-selected type
            fetchClassSubjects(preSelectedType);

            // After a short delay, set the pre-selected subject
            setTimeout(() => {
                classSubjectSelect.value = preSelectedSubject;
                // Trigger change event to update duration and code
                classSubjectSelect.dispatchEvent(new Event('change'));
                // Auto-population only happens on button clicks
            }, 500);
        }
    }

    // Auto-population only happens on button clicks, not automatically
});

// Also add a global function that can be called manually if needed
window.wecoza_auto_populate_learner_levels = classes_populate_learner_levels;
