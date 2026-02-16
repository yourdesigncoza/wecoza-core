<?php
declare(strict_types=1);

/**
 * Agent Service
 *
 * Business logic layer for agents. Orchestrates CRUD operations, form submission
 * workflow, data collection/sanitization, file uploads, and validation.
 *
 * Extracted from AgentsController and AgentsAjaxHandlers per SVC-02 refactor.
 *
 * @package WeCoza\Agents
 * @since 4.0.0
 */

namespace WeCoza\Agents\Services;

use WeCoza\Agents\Repositories\AgentRepository;
use WeCoza\Agents\Models\AgentModel;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Service
 *
 * @since 4.0.0
 */
class AgentService
{
    /**
     * Repository instance
     *
     * @var AgentRepository
     */
    private AgentRepository $repository;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->repository = new AgentRepository();
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Operations (Repository Delegation)
    |--------------------------------------------------------------------------
    */

    /**
     * Get agent by ID
     *
     * @param int $agentId Agent ID
     * @return array|null Agent data or null if not found
     */
    public function getAgent(int $agentId): ?array
    {
        return $this->repository->getAgent($agentId);
    }

    /**
     * Get agents with optional filtering and pagination
     *
     * @param array $args Query arguments (status, orderby, order, limit, offset, search)
     * @return array Array of agents with display-friendly field mapping
     */
    public function getAgents(array $args): array
    {
        $agents_raw = $this->repository->getAgents($args);
        $agents = [];
        foreach ($agents_raw as $agent) {
            $agents[] = AgentDisplayService::mapAgentFields($agent);
        }
        return $agents;
    }

    /**
     * Count agents with optional filtering
     *
     * @param array $args Query arguments (status, search)
     * @return int Agent count
     */
    public function countAgents(array $args): int
    {
        return $this->repository->countAgents($args);
    }

    /**
     * Delete agent (soft delete)
     *
     * @param int $agentId Agent ID
     * @return bool Success status
     */
    public function deleteAgent(int $agentId): bool
    {
        return $this->repository->deleteAgent($agentId);
    }

    /*
    |--------------------------------------------------------------------------
    | Form Submission Workflow
    |--------------------------------------------------------------------------
    */

    /**
     * Handle agent form submission (create or update)
     *
     * Orchestrates the complete form submission workflow:
     * - Collects and sanitizes form data
     * - Validates via AgentModel
     * - Saves to database via repository
     * - Handles file uploads
     *
     * @param array $postData POST data ($_POST)
     * @param array $filesData FILES data ($_FILES)
     * @param int|null $agentId Agent ID (null for create, int for update)
     * @param array|null $currentAgent Current agent data (for update mode)
     * @return array Result with keys: success (bool), agent_id (int|null), errors (array), agent (array|null), submitted_data (array|null)
     */
    public function handleAgentFormSubmission(array $postData, array $filesData, ?int $agentId = null, ?array $currentAgent = null): array
    {
        // Collect form data
        $data = $this->collectFormData($postData);

        // Validate via AgentModel (single source of truth)
        $agentModel = new AgentModel($data);
        $isValid = $agentModel->validate([
            'current_agent' => $currentAgent,
            'repository'    => $this->repository,
        ]);

        if (!$isValid) {
            return [
                'success' => false,
                'agent_id' => null,
                'errors' => $agentModel->get_errors(),
                'agent' => null,
                'submitted_data' => $data,
            ];
        }

        // Save agent
        if ($currentAgent) {
            $success = $this->repository->updateAgent($agentId, $data);
            $saved_agent_id = $success ? $agentId : false;
        } else {
            $saved_agent_id = $this->repository->createAgent($data);
        }

        if (!$saved_agent_id) {
            return [
                'success' => false,
                'agent_id' => null,
                'errors' => ['general' => __('Failed to save agent. Please try again.', 'wecoza-core')],
                'agent' => null,
                'submitted_data' => $data,
            ];
        }

        // Handle file uploads
        $file_data = $this->handleFileUploads($saved_agent_id, $filesData, $currentAgent);
        if (!empty($file_data)) {
            $this->repository->updateAgent($saved_agent_id, $file_data);
        }

        // Reload agent data
        $agent = $this->repository->getAgent($saved_agent_id);

        return [
            'success' => true,
            'agent_id' => $saved_agent_id,
            'errors' => [],
            'agent' => $agent,
            'submitted_data' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Form Data Collection and Sanitization
    |--------------------------------------------------------------------------
    */

    /**
     * Collect and sanitize form data from POST
     *
     * Extracts ~35 fields from POST array, applies field-specific sanitization,
     * and returns clean array ready for validation.
     *
     * @param array $postData POST data
     * @return array Sanitized form data
     */
    public function collectFormData(array $postData): array
    {
        $data = [
            // Personal Information
            'title' => sanitize_text_field($postData['title'] ?? ''),
            'first_name' => sanitize_text_field($postData['first_name'] ?? ''),
            'second_name' => $this->processTextField($postData['second_name'] ?? ''),
            'surname' => sanitize_text_field($postData['surname'] ?? ''),
            'initials' => sanitize_text_field($postData['initials'] ?? ''),
            'gender' => sanitize_text_field($postData['gender'] ?? ''),
            'race' => sanitize_text_field($postData['race'] ?? ''),

            // Identification
            'id_type' => sanitize_text_field($postData['id_type'] ?? 'sa_id'),
            'sa_id_no' => preg_replace('/[^0-9]/', '', $postData['sa_id_no'] ?? ''),
            'passport_number' => sanitize_text_field($postData['passport_number'] ?? ''),

            // Contact Information
            'tel_number' => preg_replace('/[^0-9+\-\(\)\s]/', '', $postData['tel_number'] ?? ''),
            'email_address' => sanitize_email($postData['email_address'] ?? ''),

            // Address Information
            'residential_address_line' => sanitize_text_field($postData['address_line_1'] ?? ''),
            'address_line_2' => sanitize_text_field($postData['address_line_2'] ?? ''),
            'residential_suburb' => sanitize_text_field($postData['residential_suburb'] ?? ''),
            'city' => sanitize_text_field($postData['city_town'] ?? ''),
            'province' => sanitize_text_field($postData['province_region'] ?? ''),
            'residential_postal_code' => preg_replace('/[^0-9]/', '', $postData['postal_code'] ?? ''),

            // Working Areas
            'preferred_working_area_1' => absint($postData['preferred_working_area_1'] ?? 0),
            'preferred_working_area_2' => absint($postData['preferred_working_area_2'] ?? 0),
            'preferred_working_area_3' => absint($postData['preferred_working_area_3'] ?? 0),

            // SACE Registration
            'sace_number' => sanitize_text_field($postData['sace_number'] ?? ''),
            'sace_registration_date' => $this->processDateField($postData['sace_registration_date'] ?? ''),
            'sace_expiry_date' => $this->processDateField($postData['sace_expiry_date'] ?? ''),
            'phase_registered' => sanitize_text_field($postData['phase_registered'] ?? ''),
            'subjects_registered' => sanitize_textarea_field($postData['subjects_registered'] ?? ''),

            // Qualifications
            'highest_qualification' => sanitize_text_field($postData['highest_qualification'] ?? ''),

            // Quantum Tests
            'quantum_maths_score' => $this->processNumericField($postData['quantum_maths_score'] ?? ''),
            'quantum_science_score' => $this->processNumericField($postData['quantum_science_score'] ?? ''),
            'quantum_assessment' => $this->processNumericField($postData['quantum_assessment'] ?? ''),

            // Training
            'agent_training_date' => $this->processDateField($postData['agent_training_date'] ?? ''),

            // Criminal Record
            'criminal_record_date' => $this->processDateField($postData['criminal_record_date'] ?? ''),

            // Agreement
            'signed_agreement_date' => $this->processDateField($postData['signed_agreement_date'] ?? ''),

            // Banking Details
            'bank_name' => sanitize_text_field($postData['bank_name'] ?? ''),
            'account_holder' => sanitize_text_field($postData['account_holder'] ?? ''),
            'bank_account_number' => preg_replace('/[^0-9]/', '', $postData['account_number'] ?? ''),
            'bank_branch_code' => preg_replace('/[^0-9]/', '', $postData['branch_code'] ?? ''),
            'account_type' => sanitize_text_field($postData['account_type'] ?? ''),
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
     * @param string $dateValue Date value from form
     * @return string|null Processed date or null if empty
     */
    private function processDateField(string $dateValue): ?string
    {
        $dateValue = trim($dateValue);

        // Return null for empty dates
        if (empty($dateValue)) {
            return null;
        }

        // Validate HTML5 date format and return as-is if valid
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            return $dateValue;
        }

        // Try to parse with strtotime
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return wp_date('Y-m-d', $timestamp);
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

    /*
    |--------------------------------------------------------------------------
    | File Upload Handling
    |--------------------------------------------------------------------------
    */

    /**
     * Handle file uploads for agent
     *
     * Processes signed_agreement_file and criminal_record_file uploads.
     *
     * @param int $agentId Agent ID
     * @param array $filesData FILES data ($_FILES)
     * @param array|null $currentAgent Current agent data
     * @return array File paths to save (keyed by field name)
     */
    public function handleFileUploads(int $agentId, array $filesData, ?array $currentAgent): array
    {
        $uploaded_files = [];

        // Handle signed agreement file
        if (!empty($filesData['signed_agreement_file']['name'])) {
            $file_path = $this->uploadFile('signed_agreement_file', $agentId, $filesData);
            if ($file_path) {
                $uploaded_files['signed_agreement_file'] = $file_path;
            }
        }

        // Handle criminal record file
        if (!empty($filesData['criminal_record_file']['name'])) {
            $file_path = $this->uploadFile('criminal_record_file', $agentId, $filesData);
            if ($file_path) {
                $uploaded_files['criminal_record_file'] = $file_path;
            }
        }

        return $uploaded_files;
    }

    /**
     * Upload a single file
     *
     * @param string $fieldName File field name
     * @param int $agentId Agent ID
     * @param array $filesData FILES data ($_FILES)
     * @return string|null File path or null on failure
     */
    private function uploadFile(string $fieldName, int $agentId, array $filesData): ?string
    {
        if (!isset($filesData[$fieldName]) || $filesData[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $filesData[$fieldName];

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

    /*
    |--------------------------------------------------------------------------
    | Pagination Response Assembly (for AJAX)
    |--------------------------------------------------------------------------
    */

    /**
     * Get paginated agents with statistics
     *
     * Assembles complete pagination response for AJAX handlers.
     * Used by AgentsAjaxHandlers::handlePagination().
     *
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param string $search Search term
     * @param string $orderby Sort column (frontend name)
     * @param string $order Sort order (ASC|DESC)
     * @return array Pagination data with keys: agents, total_agents, total_pages, start_index, end_index, statistics
     */
    public function getPaginatedAgents(int $page, int $perPage, string $search, string $orderby, string $order): array
    {
        // Map frontend column to database column
        $orderby = AgentDisplayService::mapSortColumn($orderby);

        // Sanitize inputs
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        // Build query args
        $args = [
            'status' => 'all',
            'orderby' => $orderby,
            'order' => $order,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'search' => $search,
        ];

        // Get agents
        $agents_raw = $this->repository->getAgents($args);
        $agents = [];
        foreach ($agents_raw as $agent) {
            $agents[] = AgentDisplayService::mapAgentFields($agent);
        }

        // Get total count
        $total_agents = $this->repository->countAgents(['status' => 'all', 'search' => $search]);

        // Calculate pagination
        $total_pages = ceil($total_agents / $perPage);
        $start_index = ($page - 1) * $perPage + 1;
        $end_index = min($start_index + $perPage - 1, $total_agents);

        // Get statistics
        $statistics = AgentDisplayService::getAgentStatistics();

        return [
            'agents' => $agents,
            'total_agents' => $total_agents,
            'total_pages' => $total_pages,
            'start_index' => $start_index,
            'end_index' => $end_index,
            'statistics' => $statistics,
        ];
    }
}
