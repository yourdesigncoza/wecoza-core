<?php
/**
 * Validation Helper
 *
 * Provides validation methods for agent data.
 *
 * @package WeCoza\Agents
 * @since 1.0.0
 */

namespace WeCoza\Agents\Helpers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validation Helper class
 *
 * @since 1.0.0
 */
class ValidationHelper {

    /**
     * Validation rules
     *
     * @var array
     */
    protected static $rules = array();

    /**
     * Error messages
     *
     * @var array
     */
    protected static $error_messages = array();

    /**
     * Initialize validation rules
     *
     * @since 1.0.0
     */
    public static function init() {
        self::setup_rules();
        self::setup_error_messages();
    }

    /**
     * Setup validation rules
     *
     * @since 1.0.0
     */
    protected static function setup_rules() {
        self::$rules = array(
            'required' => array(__CLASS__, 'validate_required'),
            'email' => array(__CLASS__, 'validate_email'),
            'phone' => array(__CLASS__, 'validate_phone'),
            'sa_id' => array(__CLASS__, 'validate_sa_id'),
            'passport' => array(__CLASS__, 'validate_passport'),
            'numeric' => array(__CLASS__, 'validate_numeric'),
            'date' => array(__CLASS__, 'validate_date'),
            'min_length' => array(__CLASS__, 'validate_min_length'),
            'max_length' => array(__CLASS__, 'validate_max_length'),
            'in' => array(__CLASS__, 'validate_in'),
            'regex' => array(__CLASS__, 'validate_regex'),
            'url' => array(__CLASS__, 'validate_url'),
            'alpha' => array(__CLASS__, 'validate_alpha'),
            'alpha_numeric' => array(__CLASS__, 'validate_alpha_numeric'),
            'postal_code' => array(__CLASS__, 'validate_postal_code'),
            'bank_account' => array(__CLASS__, 'validate_bank_account'),
            'branch_code' => array(__CLASS__, 'validate_branch_code'),
        );
    }

    /**
     * Setup error messages
     *
     * @since 1.0.0
     */
    protected static function setup_error_messages() {
        self::$error_messages = array(
            'required' => __('%s is required.', 'wecoza-core'),
            'email' => __('%s must be a valid email address.', 'wecoza-core'),
            'phone' => __('%s must be a valid phone number.', 'wecoza-core'),
            'sa_id' => __('%s must be a valid SA ID number.', 'wecoza-core'),
            'passport' => __('%s must be a valid passport number.', 'wecoza-core'),
            'numeric' => __('%s must be numeric.', 'wecoza-core'),
            'date' => __('%s must be a valid date.', 'wecoza-core'),
            'min_length' => __('%s must be at least %d characters long.', 'wecoza-core'),
            'max_length' => __('%s must not exceed %d characters.', 'wecoza-core'),
            'in' => __('%s must be one of: %s.', 'wecoza-core'),
            'regex' => __('%s has an invalid format.', 'wecoza-core'),
            'url' => __('%s must be a valid URL.', 'wecoza-core'),
            'alpha' => __('%s must contain only letters.', 'wecoza-core'),
            'alpha_numeric' => __('%s must contain only letters and numbers.', 'wecoza-core'),
            'postal_code' => __('%s must be a valid postal code.', 'wecoza-core'),
            'bank_account' => __('%s must be a valid bank account number.', 'wecoza-core'),
            'branch_code' => __('%s must be a valid branch code.', 'wecoza-core'),
        );
    }

    /**
     * Validate a single field
     *
     * @since 1.0.0
     * @param mixed $value Field value
     * @param string|array $rules Validation rules
     * @param string $field_name Field name for error messages
     * @return array Validation result with 'valid' and 'message' keys
     */
    public static function validate_field($value, $rules, $field_name = '') {
        if (empty(self::$rules)) {
            self::init();
        }
        
        // Convert string rules to array
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        $result = array(
            'valid' => true,
            'message' => '',
        );
        
        foreach ($rules as $rule) {
            $rule_parts = explode(':', $rule, 2);
            $rule_name = $rule_parts[0];
            $rule_params = isset($rule_parts[1]) ? explode(',', $rule_parts[1]) : array();
            
            // Skip if rule doesn't exist
            if (!isset(self::$rules[$rule_name])) {
                continue;
            }
            
            // Call validation method
            $is_valid = call_user_func_array(
                self::$rules[$rule_name],
                array_merge(array($value), $rule_params)
            );
            
            if (!$is_valid) {
                $result['valid'] = false;
                $result['message'] = self::get_error_message($rule_name, $field_name, $rule_params);
                break; // Stop on first error
            }
        }
        
        return $result;
    }

    /**
     * Validate multiple fields
     *
     * @since 1.0.0
     * @param array $data Data to validate
     * @param array $rules Validation rules (field => rules)
     * @param array $field_names Custom field names for error messages
     * @return array Validation errors (field => error message)
     */
    public static function validate_fields($data, $rules, $field_names = array()) {
        $errors = array();
        
        foreach ($rules as $field => $field_rules) {
            $value = isset($data[$field]) ? $data[$field] : '';
            $field_name = isset($field_names[$field]) ? $field_names[$field] : ucfirst(str_replace('_', ' ', $field));
            
            $result = self::validate_field($value, $field_rules, $field_name);
            
            if (!$result['valid']) {
                $errors[$field] = $result['message'];
            }
        }
        
        return $errors;
    }

    /**
     * Get error message
     *
     * @since 1.0.0
     * @param string $rule_name Rule name
     * @param string $field_name Field name
     * @param array $params Rule parameters
     * @return string Error message
     */
    protected static function get_error_message($rule_name, $field_name, $params = array()) {
        if (!isset(self::$error_messages[$rule_name])) {
            return sprintf(__('%s is invalid.', 'wecoza-core'), $field_name);
        }
        
        $message = self::$error_messages[$rule_name];
        
        // Handle parameterized messages
        if (strpos($message, '%s') !== false || strpos($message, '%d') !== false) {
            $args = array_merge(array($field_name), $params);
            return vsprintf($message, $args);
        }
        
        return str_replace('%s', $field_name, $message);
    }

    /**
     * Validate required field
     *
     * @since 1.0.0
     * @param mixed $value Field value
     * @return bool Whether field is valid
     */
    public static function validate_required($value) {
        if (is_array($value)) {
            return !empty($value);
        }
        return $value !== '' && $value !== null;
    }

    /**
     * Validate email
     *
     * @since 1.0.0
     * @param string $value Email address
     * @return bool Whether email is valid
     */
    public static function validate_email($value) {
        if (empty($value)) {
            return true; // Allow empty if not required
        }
        return is_email($value);
    }

    /**
     * Validate phone number
     *
     * @since 1.0.0
     * @param string $value Phone number
     * @return bool Whether phone is valid
     */
    public static function validate_phone($value) {
        if (empty($value)) {
            return true;
        }
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $value);
        
        // Check length (10-15 digits)
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Validate SA ID number
     *
     * @since 1.0.0
     * @param string $value SA ID number
     * @return array|bool Validation result array or boolean for compatibility
     */
    public static function validate_sa_id($value) {
        if (empty($value)) {
            return array('valid' => true, 'message' => '');
        }
        
        // Check if global validation function exists
        if (function_exists('wecoza_agents_validate_sa_id')) {
            return wecoza_agents_validate_sa_id($value);
        }
        
        // Fallback validation - SA ID must be 13 digits
        if (!preg_match('/^[0-9]{13}$/', $value)) {
            return array(
                'valid' => false,
                'message' => __('SA ID number must be 13 digits.', 'wecoza-core')
            );
        }
        
        // Basic checksum validation for SA ID
        $checksum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = intval($value[$i]);
            if ($i % 2 === 0) {
                $checksum += $digit;
            } else {
                $doubled = $digit * 2;
                // Sum the digits of the doubled value (e.g., 14 becomes 1 + 4 = 5)
                $checksum += ($doubled >= 10) ? (1 + ($doubled % 10)) : $doubled;
            }
        }
        
        $calculatedChecksum = (10 - ($checksum % 10)) % 10;
        $actualChecksum = intval($value[12]);
        
        if ($calculatedChecksum !== $actualChecksum) {
            return array(
                'valid' => false,
                'message' => __('SA ID number checksum is invalid.', 'wecoza-core')
            );
        }
        
        return array('valid' => true, 'message' => '');
    }

    /**
     * Validate passport number
     *
     * @since 1.0.0
     * @param string $value Passport number
     * @return array|bool Validation result array or boolean for compatibility
     */
    public static function validate_passport($value) {
        if (empty($value)) {
            return array('valid' => true, 'message' => '');
        }
        
        // Check if global validation function exists
        if (function_exists('wecoza_agents_validate_passport')) {
            return wecoza_agents_validate_passport($value);
        }
        
        // Fallback validation - passport should be 6-12 alphanumeric characters
        if (!preg_match('/^[A-Z0-9]{6,12}$/', strtoupper($value))) {
            return array(
                'valid' => false,
                'message' => __('Passport number must be 6-12 alphanumeric characters.', 'wecoza-core')
            );
        }
        
        return array('valid' => true, 'message' => '');
    }

    /**
     * Validate numeric value
     *
     * @since 1.0.0
     * @param mixed $value Value to validate
     * @return bool Whether value is numeric
     */
    public static function validate_numeric($value) {
        if (empty($value)) {
            return true;
        }
        return is_numeric($value);
    }

    /**
     * Validate date
     *
     * @since 1.0.0
     * @param string $value Date value
     * @param string $format Date format (default: Y-m-d)
     * @return bool Whether date is valid
     */
    public static function validate_date($value, $format = 'Y-m-d') {
        if (empty($value) || $value === '0000-00-00') {
            return true;
        }
        
        $d = \DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }

    /**
     * Validate minimum length
     *
     * @since 1.0.0
     * @param string $value Value to validate
     * @param int $min Minimum length
     * @return bool Whether value meets minimum length
     */
    public static function validate_min_length($value, $min) {
        if (empty($value)) {
            return true;
        }
        return strlen($value) >= intval($min);
    }

    /**
     * Validate maximum length
     *
     * @since 1.0.0
     * @param string $value Value to validate
     * @param int $max Maximum length
     * @return bool Whether value is within maximum length
     */
    public static function validate_max_length($value, $max) {
        if (empty($value)) {
            return true;
        }
        return strlen($value) <= intval($max);
    }

    /**
     * Validate value is in list
     *
     * @since 1.0.0
     * @param mixed $value Value to validate
     * @param string ...$allowed Allowed values
     * @return bool Whether value is in list
     */
    public static function validate_in($value, ...$allowed) {
        if (empty($value)) {
            return true;
        }
        return in_array($value, $allowed, true);
    }

    /**
     * Validate against regex pattern
     *
     * @since 1.0.0
     * @param string $value Value to validate
     * @param string $pattern Regex pattern
     * @return bool Whether value matches pattern
     */
    public static function validate_regex($value, $pattern) {
        if (empty($value)) {
            return true;
        }
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Validate URL
     *
     * @since 1.0.0
     * @param string $value URL to validate
     * @return bool Whether URL is valid
     */
    public static function validate_url($value) {
        if (empty($value)) {
            return true;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate alphabetic characters only
     *
     * @since 1.0.0
     * @param string $value Value to validate
     * @return bool Whether value contains only letters
     */
    public static function validate_alpha($value) {
        if (empty($value)) {
            return true;
        }
        return preg_match('/^[a-zA-Z\s]+$/', $value) === 1;
    }

    /**
     * Validate alphanumeric characters only
     *
     * @since 1.0.0
     * @param string $value Value to validate
     * @return bool Whether value contains only letters and numbers
     */
    public static function validate_alpha_numeric($value) {
        if (empty($value)) {
            return true;
        }
        return preg_match('/^[a-zA-Z0-9\s]+$/', $value) === 1;
    }

    /**
     * Validate postal code
     *
     * @since 1.0.0
     * @param string $value Postal code
     * @return bool Whether postal code is valid
     */
    public static function validate_postal_code($value) {
        if (empty($value)) {
            return true;
        }
        // South African postal codes are 4 digits
        return preg_match('/^[0-9]{4}$/', $value) === 1;
    }

    /**
     * Validate bank account number
     *
     * @since 1.0.0
     * @param string $value Account number
     * @return bool Whether account number is valid
     */
    public static function validate_bank_account($value) {
        if (empty($value)) {
            return true;
        }
        // South African bank accounts are typically 9-11 digits
        $cleaned = preg_replace('/[^0-9]/', '', $value);
        return strlen($cleaned) >= 9 && strlen($cleaned) <= 11;
    }

    /**
     * Validate branch code
     *
     * @since 1.0.0
     * @param string $value Branch code
     * @return bool Whether branch code is valid
     */
    public static function validate_branch_code($value) {
        if (empty($value)) {
            return true;
        }
        // South African branch codes are 6 digits
        return preg_match('/^[0-9]{6}$/', $value) === 1;
    }

    /**
     * Sanitize phone number
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return string Sanitized phone number
     */
    public static function sanitize_phone($phone) {
        // Remove all non-numeric characters except + at the beginning
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        $phone = preg_replace('/\+(?!^)/', '', $phone);
        
        // Format South African numbers
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            // Local format: 0XX XXX XXXX
            return $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 2) === '27') {
            // International format without +: 27XX XXX XXXX
            return '+' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '+27') {
            // International format with +: +27XX XXX XXXX
            return $phone;
        }
        
        return $phone;
    }

    /**
     * Sanitize SA ID number
     *
     * @since 1.0.0
     * @param string $id_number ID number
     * @return string Sanitized ID number
     */
    public static function sanitize_sa_id($id_number) {
        // Remove all non-numeric characters
        return preg_replace('/[^0-9]/', '', $id_number);
    }

    /**
     * Sanitize passport number
     *
     * @since 1.0.0
     * @param string $passport Passport number
     * @return string Sanitized passport number
     */
    public static function sanitize_passport($passport) {
        // Remove spaces and convert to uppercase
        return strtoupper(preg_replace('/\s+/', '', $passport));
    }

    /**
     * Get validation rules for agent fields
     *
     * @since 1.0.0
     * @return array Validation rules
     */
    public static function get_agent_validation_rules() {
        return array(
            'first_name' => 'required|alpha|max_length:50',
            'last_name' => 'required|alpha|max_length:50',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'gender' => 'required|in:M,F',
            'race' => 'required|in:African,Coloured,Indian,White',
            'street_address' => 'required|max_length:255',
            'city' => 'required|max_length:100',
            'province' => 'required',
            'postal_code' => 'required|postal_code',
            'id_number' => 'sa_id',
            'passport_number' => 'passport',
            'bank_account_number' => 'bank_account',
            'branch_code' => 'branch_code',
            'sace_number' => 'alpha_numeric|max_length:20',
        );
    }

    /**
     * Get friendly field names
     *
     * @since 1.0.0
     * @return array Field names
     */
    public static function get_field_names() {
        return array(
            'first_name' => __('First name', 'wecoza-core'),
            'last_name' => __('Last name', 'wecoza-core'),
            'email' => __('Email address', 'wecoza-core'),
            'phone' => __('Phone number', 'wecoza-core'),
            'gender' => __('Gender', 'wecoza-core'),
            'race' => __('Race', 'wecoza-core'),
            'street_address' => __('Street address', 'wecoza-core'),
            'city' => __('City', 'wecoza-core'),
            'province' => __('Province', 'wecoza-core'),
            'postal_code' => __('Postal code', 'wecoza-core'),
            'id_number' => __('SA ID number', 'wecoza-core'),
            'passport_number' => __('Passport number', 'wecoza-core'),
            'bank_account_number' => __('Bank account number', 'wecoza-core'),
            'branch_code' => __('Branch code', 'wecoza-core'),
            'sace_number' => __('SACE registration number', 'wecoza-core'),
        );
    }
}