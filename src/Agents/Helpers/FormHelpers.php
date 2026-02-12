<?php

namespace WeCoza\Agents\Helpers;

/**
 * FormHelpers
 * 
 * Centralized form helper methods for template rendering
 */
class FormHelpers {
    
    /**
     * Form field to database column mapping
     * Maps form field names to actual database column names
     * 
     * @var array
     */
    private static $field_mapping = [
        // Address fields
        'address_line_1' => 'residential_address_line',
        'city_town' => 'city',
        'province_region' => 'province',
        'postal_code' => 'residential_postal_code',
        // Database field names that should map to themselves for templates
        'city' => 'city',
        'province' => 'province',
        
        // Banking fields
        'account_number' => 'bank_account_number',
        'branch_code' => 'bank_branch_code',
        'bank_branch_code' => 'bank_branch_code',
        
        // Contact fields
        'tel_number' => 'tel_number',
        'email_address' => 'email_address',
        
        // Identification fields
        'sa_id_no' => 'sa_id_no',
        'id_number' => 'sa_id_no',  // Form helper sometimes uses id_number
        
        // Name fields
        'last_name' => 'surname',
        // Remove duplicate 'surname' => 'surname' mapping
        
        // Other fields that might need mapping
        'street_address' => 'residential_address_line',
        'phone' => 'tel_number',
        'email' => 'email_address',
        'notes' => 'agent_notes',
        
        // File upload fields
        'agreement_file_path' => 'signed_agreement_file',
        'signed_agreement_file' => 'signed_agreement_file',
        'criminal_record_file' => 'criminal_record_file',
        
        // Primary key mapping
        'id' => 'agent_id',
        'agent_id' => 'agent_id',
        
        // Quantum score fields
        'quantum_maths_score' => 'quantum_maths_score',
        'quantum_science_score' => 'quantum_science_score',
        
        // Additional personal fields
        'title' => 'title',
        'second_name' => 'second_name',
        'initials' => 'initials',
        
        // Additional address fields
        'address_line_2' => 'address_line_2',
        'residential_suburb' => 'residential_suburb',
        
        // Educational/Professional fields
        'phase_registered' => 'phase_registered',
        'subjects_registered' => 'subjects_registered',
        'agent_training_date' => 'agent_training_date',
        
        // SACE date fields
        'sace_registration_date' => 'sace_registration_date',
        'sace_expiry_date' => 'sace_expiry_date',
        
        // Additional banking fields
        'account_holder' => 'account_holder',
        
        // Compliance/Document fields
        'criminal_record_date' => 'criminal_record_date',
        'signed_agreement_date' => 'signed_agreement_date',
        
        // Working preference fields
        'preferred_working_area_1' => 'preferred_working_area_1',
        'preferred_working_area_2' => 'preferred_working_area_2',
        'preferred_working_area_3' => 'preferred_working_area_3',
    ];

    /**
     * Get field value from agent data with fallback to default
     * Handles form field name to database column name mapping
     * 
     * @param array $agent Agent data array
     * @param string $field Field name (form field name)
     * @param string $default Default value if field not found
     * @return string Field value or default
     */
    public static function get_field_value(?array $agent, string $field, string $default = ''): string {
        if ($agent === null) {
            return $default;
        }

        // Get the database column name for this form field
        $db_field = self::get_database_field_name($field);

        // Check if the field exists in the agent data
        if (isset($agent[$db_field])) {
            return esc_attr($agent[$db_field]);
        }
        
        // Fallback: check if the original field name exists (for backward compatibility)
        if (isset($agent[$field])) {
            return esc_attr($agent[$field]);
        }
        
        return $default;
    }
    
    /**
     * Get database column name for a form field
     * 
     * @param string $form_field Form field name
     * @return string Database column name
     */
    public static function get_database_field_name(string $form_field): string {
        return self::$field_mapping[$form_field] ?? $form_field;
    }
    
    /**
     * Get form field name for a database column
     * 
     * @param string $db_field Database column name
     * @return string Form field name
     */
    public static function get_form_field_name(string $db_field): string {
        $flipped = array_flip(self::$field_mapping);
        return $flipped[$db_field] ?? $db_field;
    }
    
    /**
     * Convert form data to database format
     * Maps form field names to database column names
     * 
     * @param array $form_data Form data with form field names
     * @return array Database data with database column names
     */
    public static function map_form_to_database(array $form_data): array {
        $db_data = [];
        
        foreach ($form_data as $form_field => $value) {
            $db_field = self::get_database_field_name($form_field);
            $db_data[$db_field] = $value;
        }
        
        return $db_data;
    }
    
    /**
     * Convert database data to form format
     * Maps database column names to form field names
     * 
     * @param array $db_data Database data with database column names
     * @return array Form data with form field names
     */
    public static function map_database_to_form(array $db_data): array {
        $form_data = [];
        
        foreach ($db_data as $db_field => $value) {
            $form_field = self::get_form_field_name($db_field);
            $form_data[$form_field] = $value;
        }
        
        return $form_data;
    }
    
    /**
     * Get error CSS class for field
     * 
     * @param array $errors Errors array
     * @param string $field Field name
     * @return string CSS class for error state
     */
    public static function get_error_class(array $errors, string $field): string {
        return isset($errors[$field]) ? 'is-invalid' : '';
    }
    
    /**
     * Display field error message
     * 
     * @param array $errors Errors array
     * @param string $field Field name
     * @return void
     */
    public static function display_field_error(array $errors, string $field): void {
        if (isset($errors[$field])) {
            echo '<div class="invalid-feedback">' . esc_html($errors[$field]) . '</div>';
        }
    }
}