<?php
/**
 * Agents Controller
 *
 * Handles shortcodes, asset enqueuing, and frontend rendering for the Agents module.
 *
 * @package WeCoza\Agents
 * @since 3.0.0
 */

namespace WeCoza\Agents\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Agents\Repositories\AgentRepository;
use WeCoza\Agents\Models\AgentModel;
use WeCoza\Agents\Services\WorkingAreasService;
use WeCoza\Agents\Helpers\FormHelpers;
use WeCoza\Agents\Helpers\ValidationHelper;
use WeCoza\Agents\Services\AgentDisplayService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agents Controller
 *
 * @since 3.0.0
 */
class AgentsController extends BaseController
{
    /**
     * Repository instance (lazily loaded)
     *
     * @var AgentRepository|null
     */
    private ?AgentRepository $repository = null;

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Get repository instance on-demand
     *
     * @return AgentRepository
     */
    protected function getRepository(): AgentRepository
    {
        if ($this->repository === null) {
            $this->repository = new AgentRepository();
        }
        return $this->repository;
    }

    /**
     * Register shortcodes
     *
     * @return void
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_capture_agents', [$this, 'renderCaptureForm']);
        add_shortcode('wecoza_display_agents', [$this, 'renderAgentsList']);
        add_shortcode('wecoza_single_agent', [$this, 'renderSingleAgent']);
    }

    /**
     * Render agent capture/edit form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function renderCaptureForm($atts): string
    {
        // Check permissions - editors and above
        if (!current_user_can('edit_others_posts')) {
            return '<div class="alert alert-subtle-danger">' . __('You do not have permission to manage agents.', 'wecoza-core') . '</div>';
        }

        // Parse attributes
        $atts = shortcode_atts([
            'mode' => 'add',
            'agent_id' => 0,
            'redirect_after_save' => '',
        ], $atts);

        // Detect agent ID from URL parameters
        $agent_id = $this->detectAgentIdFromUrl($atts);
        $mode = $this->determineFormMode($agent_id, $atts);

        $agent = null;
        $errors = [];
        $current_agent = null;

        // Load agent data if editing
        if ($mode === 'edit' && $agent_id > 0) {
            $current_agent = $this->getRepository()->getAgent($agent_id);
            if (!$current_agent) {
                return '<div class="alert alert-subtle-danger">' . sprintf(__('Agent with ID %d not found.', 'wecoza-core'), $agent_id) . '</div>';
            }
            $agent = $current_agent;
        }

        // Handle form submission (non-AJAX fallback)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wecoza_agents_form_nonce']) && !wp_doing_ajax()) {
            if (!wp_verify_nonce($_POST['wecoza_agents_form_nonce'], 'submit_agent_form')) {
                $errors['general'] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                // Collect form data
                $data = $this->collectFormData();

                // Validate form data
                $validation_errors = $this->validateFormData($data, $current_agent);
                if (!empty($validation_errors)) {
                    $errors = $validation_errors;
                    $agent = $data; // Preserve submitted data
                } else {
                    // Save agent
                    if ($current_agent) {
                        $success = $this->getRepository()->updateAgent($agent_id, $data);
                        $saved_agent_id = $success ? $agent_id : false;
                    } else {
                        $saved_agent_id = $this->getRepository()->createAgent($data);
                    }

                    if ($saved_agent_id) {
                        // Handle file uploads
                        $file_data = $this->handleFileUploads($saved_agent_id, $current_agent);
                        if (!empty($file_data)) {
                            $this->getRepository()->updateAgent($saved_agent_id, $file_data);
                        }

                        // Show success message
                        if (!empty($atts['redirect_after_save'])) {
                            wp_safe_redirect($atts['redirect_after_save']);
                            exit;
                        }

                        // Reload agent data
                        $agent = $this->getRepository()->getAgent($saved_agent_id);
                        $current_agent = $agent;
                    } else {
                        $errors['general'] = __('Failed to save agent. Please try again.', 'wecoza-core');
                        $agent = $data; // Preserve submitted data
                    }
                }
            }
        }

        // Get working areas for dropdown
        $working_areas = WorkingAreasService::get_working_areas();

        // Render view
        return $this->render('agents/components/agent-capture-form', [
            'agent' => $agent,
            'errors' => $errors,
            'mode' => $mode,
            'atts' => $atts,
            'working_areas' => $working_areas,
        ], true);
    }

    /**
     * Render agents list table shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function renderAgentsList($atts): string
    {
        // Parse attributes
        $atts = shortcode_atts([
            'per_page' => 10,
            'show_search' => true,
            'show_filters' => true,
            'show_pagination' => true,
            'show_actions' => true,
            'columns' => '',
        ], $atts);

        // Get query parameters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : (int) $atts['per_page'];
        $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $sort_column = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_name';
        $sort_order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';

        // Validate sort order
        if (!in_array($sort_order, ['ASC', 'DESC'])) {
            $sort_order = 'ASC';
        }

        // Map frontend column to database column
        $sort_column = AgentDisplayService::mapSortColumn($sort_column);

        // Build query args
        $args = [
            'status' => 'all',
            'orderby' => $sort_column,
            'order' => $sort_order,
            'limit' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'search' => $search_query,
        ];

        // Get agents
        $agents_raw = $this->getRepository()->getAgents($args);
        $agents = [];
        foreach ($agents_raw as $agent) {
            $agents[] = AgentDisplayService::mapAgentFields($agent);
        }

        // Get total count
        $total_agents = $this->getRepository()->countAgents(['status' => 'all', 'search' => $search_query]);

        // Calculate pagination
        $total_pages = ceil($total_agents / $per_page);
        $start_index = ($current_page - 1) * $per_page + 1;
        $end_index = min($start_index + $per_page - 1, $total_agents);

        // Get statistics
        $statistics = AgentDisplayService::getAgentStatistics();

        // Determine display columns
        $columns = AgentDisplayService::getDisplayColumns($atts['columns']);

        // Render view
        return $this->render('agents/display/agent-display-table', [
            'agents' => $agents,
            'total_agents' => $total_agents,
            'current_page' => $current_page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'start_index' => $start_index,
            'end_index' => $end_index,
            'search_query' => $search_query,
            'sort_column' => $sort_column,
            'sort_order' => $sort_order,
            'columns' => $columns,
            'atts' => $atts,
            'can_manage' => current_user_can('edit_others_posts'),
            'statistics' => $statistics,
        ], true);
    }

    /**
     * Render single agent display shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function renderSingleAgent($atts): string
    {
        // Parse attributes
        $atts = shortcode_atts([
            'agent_id' => 0,
        ], $atts);

        // Get agent ID from shortcode or URL
        $agent_id = $atts['agent_id'];
        if (empty($agent_id)) {
            $agent_id = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
        }
        $agent_id = intval($agent_id);

        $agent = false;
        $error = false;

        // Validate agent ID
        if ($agent_id <= 0) {
            $error = __('Invalid agent ID provided.', 'wecoza-core');
        } else {
            // Load agent data
            $agent_data = $this->getRepository()->getAgent($agent_id);
            if ($agent_data) {
                // Transform database fields to form fields
                $agent = FormHelpers::map_database_to_form($agent_data);
            } else {
                $error = __('Agent not found.', 'wecoza-core');
            }
        }

        // Render view
        return $this->render('agents/display/agent-single-display', [
            'agent_id' => $agent_id,
            'agent' => $agent,
            'error' => $error,
            'loading' => false,
            'back_url' => $this->getBackUrl(),
            'edit_url' => $agent ? $this->getEditUrl($agent_id) : '',
            'can_manage' => current_user_can('edit_others_posts'),
            'date_format' => get_option('date_format'),
        ], true);
    }

    /**
     * Enqueue assets conditionally
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        // Only enqueue if shortcode is present
        if (!$this->shouldEnqueueAssets()) {
            return;
        }

        // Enqueue Google Maps API if key is available
        $google_maps_api_key = get_option('wecoza_google_maps_api_key');
        if ($google_maps_api_key) {
            wp_enqueue_script(
                'google-maps-api',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_maps_api_key) . '&libraries=places&loading=async&v=weekly',
                [],
                WECOZA_CORE_VERSION,
                true
            );
        }

        // Enqueue agents app (base script)
        wp_enqueue_script(
            'wecoza-agents-app',
            WECOZA_CORE_URL . 'assets/js/agents/agents-app.js',
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue agent form validation
        wp_enqueue_script(
            'wecoza-agent-form-validation',
            WECOZA_CORE_URL . 'assets/js/agents/agent-form-validation.js',
            ['jquery', 'google-maps-api'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue AJAX pagination
        wp_enqueue_script(
            'wecoza-agents-ajax-pagination',
            WECOZA_CORE_URL . 'assets/js/agents/agents-ajax-pagination.js',
            ['jquery', 'wecoza-agents-app'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue table search
        wp_enqueue_script(
            'wecoza-agents-table-search',
            WECOZA_CORE_URL . 'assets/js/agents/agents-table-search.js',
            ['jquery', 'wecoza-agents-app'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue delete functionality
        wp_enqueue_script(
            'wecoza-agents-delete',
            WECOZA_CORE_URL . 'assets/js/agents/agent-delete.js',
            ['jquery', 'wecoza-agents-app'],
            WECOZA_CORE_VERSION,
            true
        );

        // Unified localization object (Bug #3 fix)
        wp_localize_script('wecoza-agents-app', 'wecozaAgents', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('agents_nonce_action'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'loadingText' => __('Loading...', 'wecoza-core'),
            'errorText' => __('Error loading agents. Please try again.', 'wecoza-core'),
            'confirmDeleteText' => __('Are you sure you want to delete this agent? This action cannot be undone.', 'wecoza-core'),
            'deleteSuccessText' => __('Agent deleted successfully.', 'wecoza-core'),
            'deleteErrorText' => __('Error deleting agent. Please try again.', 'wecoza-core'),
            'urls' => [
                'displayAgents' => home_url('/app/agents/'),
                'viewAgent' => home_url('/app/agent-view/'),
                'captureAgent' => home_url('/new-agents/'),
            ],
        ]);
    }

    /**
     * Check if assets should be enqueued
     *
     * @return bool
     */
    protected function shouldEnqueueAssets(): bool
    {
        global $post;
        if (!$post) {
            return false;
        }

        $shortcodes = ['wecoza_capture_agents', 'wecoza_display_agents', 'wecoza_single_agent'];
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helper Methods (Stubs - to be filled in Task 1b)
    |--------------------------------------------------------------------------
    */

    /**
     * Collect form data from POST
     *
     * @return array
     */
    private function collectFormData(): array
    {
        $data = [
            // Personal Information
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'second_name' => $this->processTextField($_POST['second_name'] ?? ''),
            'surname' => sanitize_text_field($_POST['surname'] ?? ''),
            'initials' => sanitize_text_field($_POST['initials'] ?? ''),
            'gender' => sanitize_text_field($_POST['gender'] ?? ''),
            'race' => sanitize_text_field($_POST['race'] ?? ''),

            // Identification
            'id_type' => sanitize_text_field($_POST['id_type'] ?? 'sa_id'),
            'sa_id_no' => preg_replace('/[^0-9]/', '', $_POST['sa_id_no'] ?? ''),
            'passport_number' => sanitize_text_field($_POST['passport_number'] ?? ''),

            // Contact Information
            'tel_number' => preg_replace('/[^0-9+\-\(\)\s]/', '', $_POST['tel_number'] ?? ''),
            'email_address' => sanitize_email($_POST['email_address'] ?? ''),

            // Address Information
            'residential_address_line' => sanitize_text_field($_POST['address_line_1'] ?? ''),
            'address_line_2' => sanitize_text_field($_POST['address_line_2'] ?? ''),
            'residential_suburb' => sanitize_text_field($_POST['residential_suburb'] ?? ''),
            'city' => sanitize_text_field($_POST['city_town'] ?? ''),
            'province' => sanitize_text_field($_POST['province_region'] ?? ''),
            'residential_postal_code' => preg_replace('/[^0-9]/', '', $_POST['postal_code'] ?? ''),

            // Working Areas
            'preferred_working_area_1' => absint($_POST['preferred_working_area_1'] ?? 0),
            'preferred_working_area_2' => absint($_POST['preferred_working_area_2'] ?? 0),
            'preferred_working_area_3' => absint($_POST['preferred_working_area_3'] ?? 0),

            // SACE Registration
            'sace_number' => sanitize_text_field($_POST['sace_number'] ?? ''),
            'sace_registration_date' => $this->processDateField($_POST['sace_registration_date'] ?? ''),
            'sace_expiry_date' => $this->processDateField($_POST['sace_expiry_date'] ?? ''),
            'phase_registered' => sanitize_text_field($_POST['phase_registered'] ?? ''),
            'subjects_registered' => sanitize_textarea_field($_POST['subjects_registered'] ?? ''),

            // Qualifications
            'highest_qualification' => sanitize_text_field($_POST['highest_qualification'] ?? ''),

            // Quantum Tests
            'quantum_maths_score' => $this->processNumericField($_POST['quantum_maths_score'] ?? ''),
            'quantum_science_score' => $this->processNumericField($_POST['quantum_science_score'] ?? ''),
            'quantum_assessment' => $this->processNumericField($_POST['quantum_assessment'] ?? ''),

            // Training
            'agent_training_date' => $this->processDateField($_POST['agent_training_date'] ?? ''),

            // Criminal Record
            'criminal_record_date' => $this->processDateField($_POST['criminal_record_date'] ?? ''),

            // Agreement
            'signed_agreement_date' => $this->processDateField($_POST['signed_agreement_date'] ?? ''),

            // Banking Details
            'bank_name' => sanitize_text_field($_POST['bank_name'] ?? ''),
            'account_holder' => sanitize_text_field($_POST['account_holder'] ?? ''),
            'bank_account_number' => preg_replace('/[^0-9]/', '', $_POST['account_number'] ?? ''),
            'bank_branch_code' => preg_replace('/[^0-9]/', '', $_POST['branch_code'] ?? ''),
            'account_type' => sanitize_text_field($_POST['account_type'] ?? ''),
        ];

        // Clear unused field based on ID type
        if ($data['id_type'] === 'passport') {
            $data['sa_id_no'] = '';
        } else {
            $data['passport_number'] = '';
        }

        return $data;
    }

    /**
     * Process text field (return null for empty values)
     *
     * @param string $value Text value from form
     * @return string|null
     */
    private function processTextField(string $value): ?string
    {
        $value = sanitize_text_field($value);
        $value = trim($value);
        return empty($value) ? null : $value;
    }

    /**
     * Process date field value
     *
     * @param string $date_value Date value from form
     * @return string|null Processed date or null if empty
     */
    private function processDateField(string $date_value): ?string
    {
        $date_value = trim($date_value);

        // Return null for empty dates
        if (empty($date_value)) {
            return null;
        }

        // Validate HTML5 date format and return as-is if valid
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
            return $date_value;
        }

        // Try to parse with strtotime
        $timestamp = strtotime($date_value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Process numeric field values
     *
     * @param string $value Numeric value from form
     * @return int|null Processed numeric value or null if empty
     */
    private function processNumericField(string $value): ?int
    {
        $value = trim($value);

        // Return null for empty values
        if (empty($value)) {
            return null;
        }

        // Return integer value for numeric values
        if (is_numeric($value)) {
            return intval($value);
        }

        return null;
    }

    /**
     * Validate form data
     *
     * @param array $data Form data
     * @param array|null $current_agent Current agent (for edit mode)
     * @return array Validation errors
     */
    private function validateFormData(array $data, ?array $current_agent): array
    {
        $errors = [];

        // Required fields
        if (empty($data['first_name'])) {
            $errors['first_name'] = __('First name is required.', 'wecoza-core');
        }

        if (empty($data['surname'])) {
            $errors['surname'] = __('Surname is required.', 'wecoza-core');
        }

        if (empty($data['tel_number'])) {
            $errors['tel_number'] = __('Contact number is required.', 'wecoza-core');
        }

        if (empty($data['email_address'])) {
            $errors['email_address'] = __('Email address is required.', 'wecoza-core');
        } elseif (!is_email($data['email_address'])) {
            $errors['email_address'] = __('Please enter a valid email address.', 'wecoza-core');
        }

        if (empty($data['gender'])) {
            $errors['gender'] = __('Gender is required.', 'wecoza-core');
        }

        if (empty($data['race'])) {
            $errors['race'] = __('Race is required.', 'wecoza-core');
        }

        if (empty($data['residential_address_line'])) {
            $errors['residential_address_line'] = __('Address is required.', 'wecoza-core');
        }

        if (empty($data['city'])) {
            $errors['city'] = __('City is required.', 'wecoza-core');
        }

        if (empty($data['province'])) {
            $errors['province'] = __('Province is required.', 'wecoza-core');
        }

        if (empty($data['residential_postal_code'])) {
            $errors['residential_postal_code'] = __('Postal code is required.', 'wecoza-core');
        }

        if (empty($data['preferred_working_area_1'])) {
            $errors['preferred_working_area_1'] = __('At least one preferred working area is required.', 'wecoza-core');
        }

        if (empty($data['title'])) {
            $errors['title'] = __('Title is required.', 'wecoza-core');
        }

        if (empty($data['residential_suburb'])) {
            $errors['residential_suburb'] = __('Suburb is required.', 'wecoza-core');
        }

        if (empty($data['subjects_registered'])) {
            $errors['subjects_registered'] = __('Subjects registered is required.', 'wecoza-core');
        }

        if (empty($data['highest_qualification'])) {
            $errors['highest_qualification'] = __('Highest qualification is required.', 'wecoza-core');
        }

        if (empty($data['agent_training_date'])) {
            $errors['agent_training_date'] = __('Agent training date is required.', 'wecoza-core');
        }

        if (!isset($data['quantum_assessment']) || $data['quantum_assessment'] === '') {
            $errors['quantum_assessment'] = __('Quantum assessment is required.', 'wecoza-core');
        }

        if (!isset($data['quantum_maths_score']) || $data['quantum_maths_score'] === '') {
            $errors['quantum_maths_score'] = __('Quantum maths score is required.', 'wecoza-core');
        }

        if (!isset($data['quantum_science_score']) || $data['quantum_science_score'] === '') {
            $errors['quantum_science_score'] = __('Quantum science score is required.', 'wecoza-core');
        }

        if (empty($data['signed_agreement_date'])) {
            $errors['signed_agreement_date'] = __('Signed agreement date is required.', 'wecoza-core');
        }

        if (empty($data['bank_name'])) {
            $errors['bank_name'] = __('Bank name is required.', 'wecoza-core');
        }

        if (empty($data['account_holder'])) {
            $errors['account_holder'] = __('Account holder is required.', 'wecoza-core');
        }

        if (empty($data['bank_account_number'])) {
            $errors['bank_account_number'] = __('Account number is required.', 'wecoza-core');
        }

        if (empty($data['bank_branch_code'])) {
            $errors['bank_branch_code'] = __('Branch code is required.', 'wecoza-core');
        }

        if (empty($data['account_type'])) {
            $errors['account_type'] = __('Account type is required.', 'wecoza-core');
        }

        // Validate ID based on type
        if ($data['id_type'] === 'sa_id') {
            if (empty($data['sa_id_no'])) {
                $errors['sa_id_no'] = __('SA ID number is required.', 'wecoza-core');
            } else {
                // Validate SA ID format and checksum
                $validation = ValidationHelper::validate_sa_id($data['sa_id_no']);
                if (is_array($validation) && !$validation['valid']) {
                    $errors['sa_id_no'] = $validation['message'];
                } elseif (is_bool($validation) && !$validation) {
                    $errors['sa_id_no'] = __('SA ID number is invalid.', 'wecoza-core');
                }
            }
        } else {
            if (empty($data['passport_number'])) {
                $errors['passport_number'] = __('Passport number is required.', 'wecoza-core');
            } else {
                $validation = ValidationHelper::validate_passport($data['passport_number']);
                if (is_array($validation) && !$validation['valid']) {
                    $errors['passport_number'] = $validation['message'];
                } elseif (is_bool($validation) && !$validation) {
                    $errors['passport_number'] = __('Passport number is invalid.', 'wecoza-core');
                }
            }
        }

        // Check for duplicate email (excluding current agent if editing)
        if (!empty($data['email_address'])) {
            $existing = $this->getRepository()->getAgentByEmail($data['email_address']);
            if ($existing && (!$current_agent || $existing['agent_id'] != $current_agent['agent_id'])) {
                $errors['email_address'] = __('This email address is already registered.', 'wecoza-core');
            }
        }

        // Check for duplicate ID number
        if (!empty($data['sa_id_no'])) {
            $existing = $this->getRepository()->getAgentByIdNumber($data['sa_id_no']);
            if ($existing && (!$current_agent || $existing['agent_id'] != $current_agent['agent_id'])) {
                $errors['sa_id_no'] = __('This ID number is already registered.', 'wecoza-core');
            }
        }

        return $errors;
    }

    /**
     * Handle file uploads
     *
     * @param int $agent_id Agent ID
     * @param array|null $current_agent Current agent data
     * @return array File paths to save
     */
    private function handleFileUploads(int $agent_id, ?array $current_agent): array
    {
        $uploaded_files = [];

        // Handle signed agreement file
        if (!empty($_FILES['signed_agreement_file']['name'])) {
            $file_path = $this->uploadFile('signed_agreement_file', $agent_id);
            if ($file_path) {
                $uploaded_files['signed_agreement_file'] = $file_path;
            }
        }

        // Handle criminal record file
        if (!empty($_FILES['criminal_record_file']['name'])) {
            $file_path = $this->uploadFile('criminal_record_file', $agent_id);
            if ($file_path) {
                $uploaded_files['criminal_record_file'] = $file_path;
            }
        }

        return $uploaded_files;
    }

    /**
     * Upload a single file
     *
     * @param string $field_name File field name
     * @param int $agent_id Agent ID
     * @return string|null File path or null on failure
     */
    private function uploadFile(string $field_name, int $agent_id): ?string
    {
        if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$field_name];

        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            return null;
        }

        // Require WordPress file handling functions
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        // Use WordPress file upload handler
        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            // Return relative path from uploads directory
            $upload_dir = wp_upload_dir();
            return str_replace($upload_dir['basedir'], '', $movefile['file']);
        }

        return null;
    }

    /**
     * Get edit URL for agent
     *
     * @param int $agent_id Agent ID
     * @return string Edit URL
     */
    private function getEditUrl(int $agent_id): string
    {
        return add_query_arg([
            'update' => '',
            'agent_id' => $agent_id
        ], home_url('/new-agents/'));
    }

    /**
     * Get view URL for agent
     *
     * @param int $agent_id Agent ID
     * @return string View URL
     */
    private function getViewUrl(int $agent_id): string
    {
        return add_query_arg('agent_id', $agent_id, home_url('/app/agent-view/'));
    }

    /**
     * Get back URL to agents list
     *
     * @return string Back URL
     */
    private function getBackUrl(): string
    {
        return home_url('/app/agents/');
    }

    /**
     * Detect agent ID from URL parameters
     *
     * @param array $atts Shortcode attributes
     * @return int Agent ID or 0
     */
    private function detectAgentIdFromUrl(array $atts): int
    {
        // Method 1: Check for "update" parameter with agent_id
        if (isset($_GET['update']) && isset($_GET['agent_id'])) {
            return absint($_GET['agent_id']);
        }

        // Method 2: Direct agent_id parameter
        if (isset($_GET['agent_id'])) {
            return absint($_GET['agent_id']);
        }

        // Method 3: Check shortcode attributes
        if (!empty($atts['agent_id'])) {
            return absint($atts['agent_id']);
        }

        return 0;
    }

    /**
     * Determine form mode based on agent ID
     *
     * @param int $agent_id Agent ID
     * @param array $atts Shortcode attributes
     * @return string Form mode ('add' or 'edit')
     */
    private function determineFormMode(int $agent_id, array $atts): string
    {
        if ($agent_id > 0) {
            return 'edit';
        }

        return !empty($atts['mode']) ? $atts['mode'] : 'add';
    }
}
