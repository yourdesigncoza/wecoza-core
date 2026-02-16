<?php
/**
 * AJAX Handlers for Learners Management
 *
 * This file provides AJAX handlers using legacy action names for backward
 * compatibility with existing JavaScript files.
 *
 * @package WeCoza\Learners\Ajax
 * @since 1.0.0
 */

namespace WeCoza\Learners\Ajax;

use WeCoza\Learners\Controllers\LearnerController;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verify AJAX request has valid nonce and capability
 *
 * @param string $nonce_action Action-specific nonce action name
 * @param string $capability Required capability (default: manage_learners)
 * @return void Exits with JSON error if checks fail
 */
function verify_learner_access(string $nonce_action = 'learners_nonce', string $capability = 'manage_learners'): void {
    if (!check_ajax_referer($nonce_action, 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        exit;
    }
    if (!current_user_can($capability)) {
        wp_send_json_error(['message' => 'Unauthorized access']);
        exit;
    }
}

/**
 * Get LearnerController instance
 *
 * @return LearnerController
 */
function get_learner_controller(): LearnerController {
    return new LearnerController();
}

/**
 * Generate HTML table rows for learners data
 *
 * @param array $learners Array of learner objects
 * @return string HTML string of table rows
 */
function generate_learner_table_rows(array $learners): string {
    $rows = '';
    foreach ($learners as $learner) {
        $buttons = sprintf(
            '<div class="btn-group btn-group-sm" role="group">
                <a href="%s" class="btn bg-discovery-subtle">View</a>
                <a href="%s" class="btn bg-warning-subtle">Edit</a>
                <button class="btn btn-sm bg-danger-subtle delete-learner-btn" data-id="%s">Delete</button>
            </div>',
            esc_url(home_url('/app/view-learner/?learner_id=' . ($learner->id ?? ''))),
            esc_url(home_url('/app/update-learners/?learner_id=' . ($learner->id ?? ''))),
            esc_attr($learner->id ?? '')
        );

        // Create full name with title
        $title_with_period = !empty($learner->title) ? $learner->title . '. ' : '';
        $full_name = trim($title_with_period . ($learner->first_name ?? '') . ' ' . ($learner->surname ?? ''));

        $rows .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td class="text-nowrap text-center">%s</td>
            </tr>',
            esc_html($full_name),
            esc_html($learner->surname ?? ''),
            esc_html($learner->gender ?? ''),
            esc_html($learner->race ?? ''),
            esc_html($learner->tel_number ?? ''),
            esc_html($learner->email_address ?? ''),
            esc_html($learner->city_town_name ?? ''),
            esc_html($learner->employment_status ?? ''),
            $buttons
        );
    }
    return $rows;
}

/**
 * Update learner information
 *
 * AJAX action: update_learner
 */
function handle_update_learner(): void {
    try {
        verify_learner_access('learners_nonce', 'manage_learners');

        $learner_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$learner_id) {
            throw new Exception('Learner ID is required');
        }

        // Field definitions with type info
        $fields = [
            'title', 'first_name', 'second_name', 'initials', 'surname', 'gender', 'race',
            'sa_id_no', 'passport_number', 'tel_number', 'alternative_tel_number',
            'email_address', 'address_line_1', 'address_line_2', 'city_town_id',
            'province_region_id', 'postal_code', 'highest_qualification',
            'assessment_status', 'placement_assessment_date', 'numeracy_level',
            'communication_level', 'employment_status', 'employer_id',
            'disability_status', 'scanned_portfolio'
        ];
        $intFields = ['city_town_id', 'province_region_id', 'highest_qualification',
                      'numeracy_level', 'communication_level', 'employer_id'];

        // Collect and sanitize data in single pass
        $data = [];
        foreach ($fields as $field) {
            if (!isset($_POST[$field])) {
                continue;
            }
            $value = sanitize_text_field($_POST[$field]);
            $data[$field] = (in_array($field, $intFields) && $value !== '')
                ? intval($value)
                : $value;
        }

        $result = get_learner_controller()->updateLearner($learner_id, $data);

        if ($result) {
            wp_send_json_success(['message' => 'Learner updated successfully']);
        } else {
            throw new Exception('Failed to update learner');
        }

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Delete learner
 *
 * AJAX action: delete_learner
 */
function handle_delete_learner(): void {
    try {
        verify_learner_access('learners_nonce', 'manage_learners');

        $learner_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$learner_id) {
            throw new Exception('Invalid learner ID');
        }

        $result = get_learner_controller()->deleteLearner($learner_id);

        if ($result) {
            wp_send_json_success(['message' => 'Learner deleted successfully']);
        } else {
            throw new Exception('Failed to delete learner');
        }

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Fetch learners data for display
 *
 * AJAX action: fetch_learners_data
 */
function handle_fetch_learners_data(): void {
    try {
        // Require manage_learners capability for PII data access
        verify_learner_access('learners_nonce', 'manage_learners');

        $learnerModels = get_learner_controller()->getLearnersWithMappings();

        if (empty($learnerModels)) {
            throw new Exception('No learners found.');
        }

        // Convert models to stdClass for backward compatibility
        // Pass true to include NULL values - prevents "Undefined property" warnings
        $learners = array_map(function($model) {
            $obj = (object) $model->toDbArray(true);
            $obj->city_town_name = $model->getCityTownName();
            return $obj;
        }, $learnerModels);

        $rows = generate_learner_table_rows($learners);
        wp_send_json_success($rows);

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Fetch dropdown data for forms
 *
 * AJAX action: fetch_learners_dropdown_data
 */
function handle_fetch_dropdown_data(): void {
    try {
        // Dropdown data (non-PII) requires only read capability
        verify_learner_access('learners_nonce', 'read');

        $dropdownData = get_learner_controller()->getDropdownData();

        // Transform data for frontend format
        $cities = array_map(function($city) {
            return ['id' => $city['location_id'], 'name' => $city['town']];
        }, $dropdownData['cities']);

        $provinces = array_map(function($province) {
            return ['id' => $province['location_id'], 'name' => $province['province']];
        }, $dropdownData['provinces']);

        $qualifications = array_map(function($qualification) {
            return ['id' => $qualification['id'], 'name' => $qualification['qualification']];
        }, $dropdownData['qualifications']);

        $employers = array_map(function($employer) {
            return ['id' => $employer['employer_id'], 'name' => $employer['employer_name']];
        }, $dropdownData['employers']);

        // Structure placement levels for frontend
        $placement_levels_data = [
            'numeracy_levels' => array_values(array_map(function($level) {
                return ['id' => $level['placement_level_id'], 'name' => $level['level']];
            }, $dropdownData['numeracy_levels'])),
            'communication_levels' => array_values(array_map(function($level) {
                return ['id' => $level['placement_level_id'], 'name' => $level['level']];
            }, $dropdownData['communication_levels']))
        ];

        wp_send_json_success([
            'cities' => $cities,
            'provinces' => $provinces,
            'qualifications' => $qualifications,
            'employers' => $employers,
            'placement_levels' => $placement_levels_data,
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Handle portfolio deletion
 *
 * AJAX action: delete_learner_portfolio
 */
function handle_portfolio_deletion(): void {
    try {
        verify_learner_access('learners_nonce', 'manage_learners');

        $portfolio_id = filter_input(INPUT_POST, 'portfolio_id', FILTER_VALIDATE_INT);
        $learner_id = filter_input(INPUT_POST, 'learner_id', FILTER_VALIDATE_INT);

        if (!$portfolio_id || !$learner_id) {
            throw new Exception('Invalid portfolio or learner ID');
        }

        if (get_learner_controller()->deletePortfolio($portfolio_id)) {
            wp_send_json_success('Portfolio deleted successfully');
        } else {
            throw new Exception('Failed to delete portfolio');
        }

    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Register all AJAX handlers
 * Uses legacy action names for backward compatibility with existing JS files
 */
function register_ajax_handlers(): void {
    // Data fetching - require authentication (site requires login)
    add_action('wp_ajax_fetch_learners_data', __NAMESPACE__ . '\handle_fetch_learners_data');
    add_action('wp_ajax_fetch_learners_dropdown_data', __NAMESPACE__ . '\handle_fetch_dropdown_data');

    // CRUD operations - require authentication
    add_action('wp_ajax_update_learner', __NAMESPACE__ . '\handle_update_learner');
    add_action('wp_ajax_delete_learner', __NAMESPACE__ . '\handle_delete_learner');
    add_action('wp_ajax_delete_learner_portfolio', __NAMESPACE__ . '\handle_portfolio_deletion');
}

// Register handlers on init
add_action('init', __NAMESPACE__ . '\register_ajax_handlers');
