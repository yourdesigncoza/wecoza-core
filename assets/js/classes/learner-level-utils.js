/**
 * Learner Level Utilities for WeCoza Classes Plugin
 *
 * Provides utility functions for managing learner level options
 * and auto-population based on class subjects.
 */

/**
 * Get all available learner level options
 * 
 * @return {Array} Array of level objects with id and name
 */
function classes_get_learner_level_options() {
    return [
        { id: '', name: 'Select Level' },
        { id: 'COMM', name: 'COMM' },
        { id: 'NUM', name: 'NUM' },
        { id: 'COMM_NUM', name: 'COMM_NUM' },
        { id: 'CL4', name: 'CL4' },
        { id: 'NL4', name: 'NL4' },
        { id: 'LO4', name: 'LO4' },
        { id: 'HSS4', name: 'HSS4' },
        { id: 'EMS4', name: 'EMS4' },
        { id: 'NS4', name: 'NS4' },
        { id: 'SMME4', name: 'SMME4' },
        { id: 'RLC', name: 'RLC' },
        { id: 'RLN', name: 'RLN' },
        { id: 'RLF', name: 'RLF' },
        { id: 'BA2LP1', name: 'BA2LP1' },
        { id: 'BA2LP2', name: 'BA2LP2' },
        { id: 'BA2LP3', name: 'BA2LP3' },
        { id: 'BA2LP4', name: 'BA2LP4' },
        { id: 'BA2LP5', name: 'BA2LP5' },
        { id: 'BA2LP6', name: 'BA2LP6' },
        { id: 'BA2LP7', name: 'BA2LP7' },
        { id: 'BA2LP8', name: 'BA2LP8' },
        { id: 'BA2LP9', name: 'BA2LP9' },
        { id: 'BA2LP10', name: 'BA2LP10' },
        { id: 'BA3LP1', name: 'BA3LP1' },
        { id: 'BA3LP2', name: 'BA3LP2' },
        { id: 'BA3LP3', name: 'BA3LP3' },
        { id: 'BA3LP4', name: 'BA3LP4' },
        { id: 'BA3LP5', name: 'BA3LP5' },
        { id: 'BA3LP6', name: 'BA3LP6' },
        { id: 'BA3LP7', name: 'BA3LP7' },
        { id: 'BA3LP8', name: 'BA3LP8' },
        { id: 'BA3LP9', name: 'BA3LP9' },
        { id: 'BA3LP10', name: 'BA3LP10' },
        { id: 'BA3LP11', name: 'BA3LP11' },
        { id: 'BA4LP1', name: 'BA4LP1' },
        { id: 'BA4LP2', name: 'BA4LP2' },
        { id: 'BA4LP3', name: 'BA4LP3' },
        { id: 'BA4LP4', name: 'BA4LP4' },
        { id: 'BA4LP5', name: 'BA4LP5' },
        { id: 'BA4LP6', name: 'BA4LP6' },
        { id: 'BA4LP7', name: 'BA4LP7' },
        { id: 'WALK', name: 'WALK' },
        { id: 'HEXA', name: 'HEXA' },
        { id: 'RUN', name: 'RUN' },
        { id: 'IPC', name: 'IPC' },
        { id: 'EQ', name: 'EQ' },
        { id: 'TM', name: 'TM' },
        { id: 'SS', name: 'SS' },
        { id: 'EEPDL', name: 'EEPDL' },
        { id: 'EEPPF', name: 'EEPPF' },
        { id: 'EEPWI', name: 'EEPWI' },
        { id: 'EEPEI', name: 'EEPEI' },
        { id: 'EEPBI', name: 'EEPBI' }
    ];
}

/**
 * Generate HTML options for learner level select
 * 
 * @param {string} selectedValue The currently selected value
 * @return {string} HTML options string
 */
function classes_generate_learner_level_options_html(selectedValue = '') {
    const options = classes_get_learner_level_options();
    return options.map(option => {
        const selected = option.id === selectedValue ? 'selected' : '';
        return `<option value="${option.id}" ${selected}>${option.name}</option>`;
    }).join('');
}

/**
 * Generate learner level select HTML for dynamic table rows
 * 
 * @param {string} learnerId The learner ID for data attribute
 * @param {string} selectedLevel The currently selected level
 * @return {string} Complete select HTML
 */
function classes_generate_learner_level_select_html(learnerId, selectedLevel = '') {
    const optionsHtml = classes_generate_learner_level_options_html(selectedLevel);
    return `
        <select class="form-select form-select-sm learner-level-select" data-learner-id="${learnerId}">
            ${optionsHtml}
        </select>
    `;
}
