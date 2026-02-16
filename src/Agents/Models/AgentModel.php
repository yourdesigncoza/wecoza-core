<?php
declare(strict_types=1);

/**
 * Agent Model
 *
 * Represents an agent entity with data structure and validation rules.
 * Standalone model (NOT extending BaseModel) with get/set/validate cycle.
 *
 * @package WeCoza\Agents
 * @since 3.0.0
 */

namespace WeCoza\Agents\Models;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Model class
 *
 * @since 3.0.0
 */
class AgentModel
{

    /**
     * Agent ID (maps to 'agent_id' in database)
     *
     * @var int
     */
    protected $id = 0;

    /**
     * Database primary key field name
     *
     * @var string
     */
    protected static $primary_key = 'agent_id';

    /**
     * Agent data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Modified fields
     *
     * @var array
     */
    protected $modified = [];

    /**
     * Validation errors
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Default agent data structure
     * Uses actual database column names
     *
     * @var array
     */
    protected static $defaults = [
        // Personal Information (matches database)
        'title' => '',
        'first_name' => '',
        'second_name' => '',
        'surname' => '',
        'initials' => '',
        'gender' => '',
        'race' => '',

        // Identification (matches database)
        'id_type' => 'sa_id', // sa_id or passport
        'sa_id_no' => '',
        'passport_number' => '',

        // Contact Information (matches database)
        'tel_number' => '',
        'email_address' => '',

        // Address Information (matches database)
        'residential_address_line' => '',
        'address_line_2' => '',
        'city' => '',
        'province' => '',
        'residential_postal_code' => '',

        // Working Areas (matches database)
        'preferred_working_area_1' => null,
        'preferred_working_area_2' => null,
        'preferred_working_area_3' => null,

        // SACE Registration (matches database)
        'sace_number' => '',
        'sace_registration_date' => '',
        'sace_expiry_date' => '',
        'phase_registered' => '',
        'subjects_registered' => '',

        // Qualifications (matches database)
        'highest_qualification' => '',

        // Quantum Tests (matches database)
        'quantum_maths_score' => 0,
        'quantum_science_score' => 0,
        'quantum_assessment' => '',

        // Criminal Record (matches database)
        'criminal_record_date' => '',
        'criminal_record_file' => '',

        // Agreement (matches database)
        'signed_agreement_date' => '',
        'signed_agreement_file' => '',

        // Banking Details (matches database)
        'bank_name' => '',
        'account_holder' => '',
        'bank_account_number' => '',
        'bank_branch_code' => '',
        'account_type' => '',

        // Training (matches database)
        'agent_training_date' => '',

        // Metadata (matches database)
        'agent_notes' => '',
        'created_at' => '',
        'updated_at' => '',
        'created_by' => 0,
        'updated_by' => 0,
        'status' => 'active',

        // Legacy fields for backward compatibility
        'residential_suburb' => '',
        'residential_town_id' => null,
    ];

    /**
     * Required fields (using database column names)
     *
     * @var array
     */
    protected static $required_fields = [
        'title',
        'first_name',
        'surname',
        'gender',
        'race',
        'tel_number',
        'email_address',
        'residential_address_line',
        'residential_suburb',
        'city',
        'province',
        'residential_postal_code',
        'preferred_working_area_1',
        'subjects_registered',
        'highest_qualification',
        'agent_training_date',
        'signed_agreement_date',
        'bank_name',
        'account_holder',
        'bank_account_number',
        'bank_branch_code',
        'account_type',
    ];

    /**
     * Validation rules (using database column names)
     *
     * @var array
     */
    protected static $validation_rules = [
        'email_address' => 'email',
        'tel_number' => 'phone',
        'sa_id_no' => 'sa_id',
        'passport_number' => 'passport',
        'residential_postal_code' => 'numeric',
        'bank_account_number' => 'numeric',
        'bank_branch_code' => 'numeric',
    ];

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param array|int $data Agent data or ID
     */
    public function __construct($data = [])
    {
        if (is_numeric($data)) {
            $this->load($data);
        } elseif (is_array($data)) {
            $this->set_data($data);
        }
    }

    /**
     * Load agent by ID
     *
     * @since 3.0.0
     * @param int $id Agent ID
     * @return bool Success
     */
    public function load($id)
    {
        $id = absint($id);
        if (!$id) {
            return false;
        }

        $repository = new \WeCoza\Agents\Repositories\AgentRepository();
        $data = $repository->getAgent($id);

        if (!$data) {
            return false;
        }

        $this->id = $id;
        $this->set_data($data);
        $this->modified = [];

        return true;
    }

    /**
     * Save agent
     *
     * @since 3.0.0
     * @return bool|int Agent ID on success, false on failure
     */
    public function save()
    {
        $repository = new \WeCoza\Agents\Repositories\AgentRepository();

        // Load current agent data for uniqueness checks when editing
        $current_agent = $this->id ? $repository->getAgent($this->id) : null;

        // Validate before saving
        if (!$this->validate(['current_agent' => $current_agent, 'repository' => $repository])) {
            return false;
        }

        // Prepare data for saving
        $save_data = $this->get_save_data();

        if ($this->id) {
            // Update existing agent
            $success = $repository->updateAgent($this->id, $save_data);
            if ($success) {
                $this->modified = [];
                return $this->id;
            }
        } else {
            // Create new agent
            $id = $repository->createAgent($save_data);
            if ($id) {
                $this->id = $id;
                $this->modified = [];
                return $id;
            }
        }

        return false;
    }

    /**
     * Delete agent
     *
     * @since 3.0.0
     * @return bool Success
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        $repository = new \WeCoza\Agents\Repositories\AgentRepository();
        $success = $repository->deleteAgent($this->id);

        if ($success) {
            $this->id = 0;
            $this->data = [];
            $this->modified = [];
        }

        return $success;
    }

    /**
     * Set agent data
     *
     * @since 3.0.0
     * @param array $data Agent data
     */
    public function set_data($data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get agent data
     *
     * @since 3.0.0
     * @return array Agent data
     */
    public function get_data()
    {
        return array_merge(self::$defaults, $this->data);
    }

    /**
     * Get data for saving
     *
     * @since 3.0.0
     * @return array Data to save
     */
    protected function get_save_data()
    {
        $data = $this->get_data();

        // Remove fields that shouldn't be saved directly
        unset($data['id']);

        // Handle primary key mapping
        if ($this->id) {
            $data[self::$primary_key] = $this->id;
        }

        // Set timestamps
        if (!$this->id) {
            $data['created_at'] = current_time('mysql');
            $data['created_by'] = get_current_user_id();
        }
        $data['updated_at'] = current_time('mysql');
        $data['updated_by'] = get_current_user_id();

        // Convert boolean fields
        $boolean_fields = [];

        foreach ($boolean_fields as $field) {
            $data[$field] = $data[$field] ? 1 : 0;
        }

        return $data;
    }

    /**
     * Magic getter
     *
     * @since 3.0.0
     * @param string $key Property key
     * @return mixed Property value
     */
    public function __get($key)
    {
        if ($key === 'id') {
            return $this->id;
        }

        return $this->get($key);
    }

    /**
     * Magic setter
     *
     * @since 3.0.0
     * @param string $key Property key
     * @param mixed $value Property value
     */
    public function __set($key, $value)
    {
        if ($key === 'id') {
            $this->id = absint($value);
        } else {
            $this->set($key, $value);
        }
    }

    /**
     * Magic isset
     *
     * @since 3.0.0
     * @param string $key Property key
     * @return bool Whether property is set
     */
    public function __isset($key)
    {
        if ($key === 'id') {
            return isset($this->id);
        }

        return isset($this->data[$key]);
    }

    /**
     * Get property value
     *
     * @since 3.0.0
     * @param string $key Property key
     * @param mixed $default Default value
     * @return mixed Property value
     */
    public function get($key, $default = null)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        if (isset(self::$defaults[$key])) {
            return self::$defaults[$key];
        }

        return $default;
    }

    /**
     * Set property value
     *
     * @since 3.0.0
     * @param string $key Property key
     * @param mixed $value Property value
     */
    public function set($key, $value)
    {
        // Track modifications
        if (!isset($this->data[$key]) || $this->data[$key] !== $value) {
            $this->modified[$key] = true;
        }

        $this->data[$key] = $value;
    }

    /**
     * Check if field was modified
     *
     * @since 3.0.0
     * @param string $key Property key
     * @return bool Whether field was modified
     */
    public function is_modified($key = null)
    {
        if ($key === null) {
            return !empty($this->modified);
        }

        return isset($this->modified[$key]);
    }

    /**
     * Get modified fields
     *
     * @since 3.0.0
     * @return array Modified field keys
     */
    public function get_modified_fields()
    {
        return array_keys($this->modified);
    }

    /**
     * Validate agent data
     *
     * Centralised validation for the Agents module. Controllers and
     * repositories should delegate here instead of duplicating checks.
     *
     * @since 3.0.0
     * @param array|null $context Optional context for uniqueness checks.
     *   - 'current_agent' (array|null): Existing agent row when editing, used to
     *     exclude the current record from duplicate checks.
     *   - 'repository' (AgentRepository|null): Repository instance for DB lookups.
     * @return bool Whether data is valid
     */
    public function validate(?array $context = null)
    {
        $this->errors = [];
        $data = $this->get_data();

        $current_agent = $context['current_agent'] ?? null;
        $repository    = $context['repository'] ?? null;

        // ------------------------------------------------------------------
        // Required fields
        // ------------------------------------------------------------------
        foreach (self::$required_fields as $field) {
            if (empty($data[$field])) {
                $this->errors[$field] = sprintf(
                    __('%s is required.', 'wecoza-core'),
                    ucfirst(str_replace('_', ' ', $field))
                );
            }
        }

        // Quantum scores use a different emptiness check (0 is a valid score)
        $quantum_fields = [
            'quantum_assessment'   => __('Quantum assessment is required.', 'wecoza-core'),
            'quantum_maths_score'  => __('Quantum maths score is required.', 'wecoza-core'),
            'quantum_science_score' => __('Quantum science score is required.', 'wecoza-core'),
        ];
        foreach ($quantum_fields as $field => $message) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $this->errors[$field] = $message;
            }
        }

        // ------------------------------------------------------------------
        // Email format
        // ------------------------------------------------------------------
        if (!empty($data['email_address']) && !is_email($data['email_address'])) {
            $this->errors['email_address'] = __('Please enter a valid email address.', 'wecoza-core');
        }

        // ------------------------------------------------------------------
        // ID number / passport
        // ------------------------------------------------------------------
        if ($data['id_type'] === 'sa_id') {
            if (empty($data['sa_id_no'])) {
                $this->errors['sa_id_no'] = __('SA ID number is required.', 'wecoza-core');
            } else {
                $validation = \WeCoza\Agents\Helpers\ValidationHelper::validate_sa_id($data['sa_id_no']);
                if (is_array($validation) && !$validation['valid']) {
                    $this->errors['sa_id_no'] = $validation['message'];
                } elseif (is_bool($validation) && !$validation) {
                    $this->errors['sa_id_no'] = __('SA ID number is invalid.', 'wecoza-core');
                }
            }
        } else {
            if (empty($data['passport_number'])) {
                $this->errors['passport_number'] = __('Passport number is required.', 'wecoza-core');
            } else {
                $validation = \WeCoza\Agents\Helpers\ValidationHelper::validate_passport($data['passport_number']);
                if (is_array($validation) && !$validation['valid']) {
                    $this->errors['passport_number'] = $validation['message'];
                } elseif (is_bool($validation) && !$validation) {
                    $this->errors['passport_number'] = __('Passport number is invalid.', 'wecoza-core');
                }
            }
        }

        // ------------------------------------------------------------------
        // Phone number format
        // ------------------------------------------------------------------
        if (!empty($data['tel_number'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['tel_number']);
            if (strlen($phone) < 10) {
                $this->errors['tel_number'] = __('Please enter a valid phone number.', 'wecoza-core');
            }
        }

        // ------------------------------------------------------------------
        // Numeric fields
        // ------------------------------------------------------------------
        if (!empty($data['residential_postal_code']) && !is_numeric($data['residential_postal_code'])) {
            $this->errors['residential_postal_code'] = __('Postal code must be numeric.', 'wecoza-core');
        }

        if (!empty($data['bank_account_number']) && !is_numeric($data['bank_account_number'])) {
            $this->errors['bank_account_number'] = __('Account number must be numeric.', 'wecoza-core');
        }

        if (!empty($data['bank_branch_code']) && !is_numeric($data['bank_branch_code'])) {
            $this->errors['bank_branch_code'] = __('Branch code must be numeric.', 'wecoza-core');
        }

        // ------------------------------------------------------------------
        // Date fields
        // ------------------------------------------------------------------
        $date_fields = [
            'criminal_record_date',
            'signed_agreement_date',
            'agent_training_date',
        ];

        foreach ($date_fields as $field) {
            if (!empty($data[$field]) && !$this->is_valid_date($data[$field])) {
                $this->errors[$field] = sprintf(
                    __('%s must be a valid date.', 'wecoza-core'),
                    ucfirst(str_replace('_', ' ', $field))
                );
            }
        }

        // ------------------------------------------------------------------
        // Uniqueness checks (require a repository instance)
        // ------------------------------------------------------------------
        if ($repository) {
            // Duplicate email
            if (!empty($data['email_address']) && !isset($this->errors['email_address'])) {
                $existing = $repository->getAgentByEmail($data['email_address']);
                if ($existing && (!$current_agent || $existing['agent_id'] != $current_agent['agent_id'])) {
                    $this->errors['email_address'] = __('This email address is already registered.', 'wecoza-core');
                }
            }

            // Duplicate ID number
            if (!empty($data['sa_id_no']) && !isset($this->errors['sa_id_no'])) {
                $existing = $repository->getAgentByIdNumber($data['sa_id_no']);
                if ($existing && (!$current_agent || $existing['agent_id'] != $current_agent['agent_id'])) {
                    $this->errors['sa_id_no'] = __('This ID number is already registered.', 'wecoza-core');
                }
            }
        }

        // Allow filtering of validation
        $this->errors = apply_filters('wecoza_agents_validate_agent', $this->errors, $data, $this);

        return empty($this->errors);
    }

    /**
     * Get field value using FormHelpers mapping
     *
     * @since 3.0.0
     * @param string $form_field_name Form field name
     * @param mixed $default Default value
     * @return mixed Field value
     */
    public function get_form_field($form_field_name, $default = null)
    {
        // Use FormHelpers to map form field to database field
        if (class_exists('\WeCoza\Agents\Helpers\FormHelpers')) {
            $db_field = \WeCoza\Agents\Helpers\FormHelpers::get_database_field_name($form_field_name);
            return $this->get($db_field, $default);
        }

        return $this->get($form_field_name, $default);
    }

    /**
     * Set field value using FormHelpers mapping
     *
     * @since 3.0.0
     * @param string $form_field_name Form field name
     * @param mixed $value Field value
     */
    public function set_form_field($form_field_name, $value)
    {
        // Use FormHelpers to map form field to database field
        if (class_exists('\WeCoza\Agents\Helpers\FormHelpers')) {
            $db_field = \WeCoza\Agents\Helpers\FormHelpers::get_database_field_name($form_field_name);
            $this->set($db_field, $value);
        } else {
            $this->set($form_field_name, $value);
        }
    }

    /**
     * Set data from form submission
     *
     * @since 3.0.0
     * @param array $form_data Form data with form field names
     */
    public function set_form_data($form_data)
    {
        // Use FormHelpers to map form fields to database fields
        if (class_exists('\WeCoza\Agents\Helpers\FormHelpers')) {
            $db_data = \WeCoza\Agents\Helpers\FormHelpers::map_form_to_database($form_data);
            $this->set_data($db_data);
        } else {
            $this->set_data($form_data);
        }
    }

    /**
     * Get data in form format
     *
     * @since 3.0.0
     * @return array Data with form field names
     */
    public function get_form_data()
    {
        $data = $this->get_data();

        // Use FormHelpers to map database fields to form fields
        if (class_exists('\WeCoza\Agents\Helpers\FormHelpers')) {
            return \WeCoza\Agents\Helpers\FormHelpers::map_database_to_form($data);
        }

        return $data;
    }

    /**
     * Get validation errors
     *
     * @since 3.0.0
     * @return array Validation errors
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * Check if date is valid
     *
     * @since 3.0.0
     * @param string $date Date string
     * @return bool Whether date is valid
     */
    protected function is_valid_date($date)
    {
        if (empty($date) || $date === '0000-00-00') {
            return true; // Allow empty dates
        }

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Get display name
     *
     * @since 3.0.0
     * @return string Display name
     */
    public function get_display_name()
    {
        $parts = [];

        if ($this->get('title')) {
            $parts[] = $this->get('title');
        }

        if ($this->get('first_name')) {
            $parts[] = $this->get('first_name');
        }


        if ($this->get('surname')) {
            $parts[] = $this->get('surname');
        }

        return implode(' ', $parts);
    }

    /**
     * Get initials
     *
     * @since 3.0.0
     * @return string Initials
     */
    public function get_initials()
    {
        if ($this->get('initials')) {
            return $this->get('initials');
        }

        $initials = '';

        if ($this->get('first_name')) {
            $initials .= strtoupper(substr($this->get('first_name'), 0, 1)) . '.';
        }

        if ($this->get('surname')) {
            $initials .= ' ' . strtoupper(substr($this->get('surname'), 0, 1)) . '.';
        }

        return trim($initials);
    }

    /**
     * Get preferred areas as array
     *
     * @since 3.0.0
     * @return array Preferred areas
     */
    public function get_preferred_areas()
    {
        $areas = [];

        // Get from individual database columns
        if ($this->get('preferred_working_area_1')) {
            $areas[] = $this->get('preferred_working_area_1');
        }
        if ($this->get('preferred_working_area_2')) {
            $areas[] = $this->get('preferred_working_area_2');
        }
        if ($this->get('preferred_working_area_3')) {
            $areas[] = $this->get('preferred_working_area_3');
        }

        return $areas;
    }

    /**
     * Set preferred areas
     *
     * @since 3.0.0
     * @param array $areas Preferred areas
     */
    public function set_preferred_areas($areas)
    {
        if (is_array($areas)) {
            // Set individual database columns
            $this->set('preferred_working_area_1', isset($areas[0]) ? $areas[0] : null);
            $this->set('preferred_working_area_2', isset($areas[1]) ? $areas[1] : null);
            $this->set('preferred_working_area_3', isset($areas[2]) ? $areas[2] : null);
        }
    }

    /**
     * Check if agent has quantum qualification
     *
     * @since 3.0.0
     * @param string $type Quantum type (maths, science, or null for any)
     * @return bool Whether agent has qualification
     */
    public function has_quantum_qualification($type = null)
    {
        if ($type === 'maths') {
            return $this->get('quantum_maths_score') > 0;
        }

        if ($type === 'science') {
            return $this->get('quantum_science_score') > 0;
        }

        // Check if has any quantum qualification
        return $this->get('quantum_maths_score') > 0 || $this->get('quantum_science_score') > 0;
    }



    /**
     * Get status label
     *
     * @since 3.0.0
     * @return string Status label
     */
    public function get_status_label()
    {
        $status = $this->get('status', 'active');
        $labels = [
            'active' => __('Active', 'wecoza-core'),
            'inactive' => __('Inactive', 'wecoza-core'),
            'suspended' => __('Suspended', 'wecoza-core'),
        ];

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Convert to array
     *
     * @since 3.0.0
     * @return array Agent data array
     */
    public function to_array()
    {
        $data = $this->get_data();
        $data['id'] = $this->id;
        $data[self::$primary_key] = $this->id;
        return $data;
    }

    /**
     * Convert to JSON
     *
     * @since 3.0.0
     * @return string JSON representation
     */
    public function to_json()
    {
        return json_encode($this->to_array());
    }
}
