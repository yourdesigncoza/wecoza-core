<?php
declare(strict_types=1);

/**
 * WeCoza Core - AJAX Security Helper
 *
 * Provides centralized AJAX security utilities including nonce verification,
 * capability checks, input validation, and standardized response handling.
 *
 * Can be used statically or instantiated for fluent chaining.
 *
 * @package WeCoza\Core\Helpers
 * @since 1.0.0
 */

namespace WeCoza\Core\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class AjaxSecurity
{
    /*
    |--------------------------------------------------------------------------
    | Nonce Verification
    |--------------------------------------------------------------------------
    */

    /**
     * Verify AJAX nonce
     *
     * @param string $action Nonce action name
     * @param string $field Request field containing nonce (default: 'nonce')
     * @return bool True if valid
     */
    public static function verifyNonce(string $action, string $field = 'nonce'): bool
    {
        if (!function_exists('check_ajax_referer')) {
            return false;
        }
        return (bool) check_ajax_referer($action, $field, false);
    }

    /**
     * Require valid nonce (sends error and exits if invalid)
     *
     * @param string $action Nonce action name
     * @param string $field Request field containing nonce
     * @return void
     */
    public static function requireNonce(string $action, string $field = 'nonce'): void
    {
        if (!self::verifyNonce($action, $field)) {
            self::sendError('Invalid security token.', 403);
            exit;
        }
    }

    /**
     * Create a nonce for an action
     *
     * @param string $action Nonce action name
     * @return string Nonce string
     */
    public static function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    /*
    |--------------------------------------------------------------------------
    | Capability Checks
    |--------------------------------------------------------------------------
    */

    /**
     * Check if current user has capability
     *
     * @param string $capability Capability name
     * @return bool
     */
    public static function checkCapability(string $capability): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }
        return current_user_can($capability);
    }

    /**
     * Require user capability (sends error and exits if not met)
     *
     * @param string $capability Required capability
     * @return void
     */
    public static function requireCapability(string $capability): void
    {
        if (!self::checkCapability($capability)) {
            self::sendError('Insufficient permissions.', 403);
            exit;
        }
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Require user to be logged in
     *
     * @return void
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            self::sendError('Authentication required.', 401);
            exit;
        }
    }

    /**
     * Combined security check (nonce + capability)
     *
     * @param string $nonceAction Nonce action name
     * @param string $capability Required capability
     * @param string $nonceField Nonce field name
     * @return void
     */
    public static function requireAuth(string $nonceAction, string $capability = 'manage_options', string $nonceField = 'nonce'): void
    {
        self::requireNonce($nonceAction, $nonceField);
        self::requireCapability($capability);
    }

    /*
    |--------------------------------------------------------------------------
    | Field Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Validate that required fields are present
     *
     * @param array $input Input array (typically $_POST)
     * @param array $required Array of required field names
     * @return array Array of missing field names (empty if all present)
     */
    public static function validateRequiredFields(array $input, array $required): array
    {
        $missing = [];

        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '' || $input[$field] === null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Require fields to be present (sends error and exits if missing)
     *
     * @param array $input Input array
     * @param array $required Required field names
     * @return void
     */
    public static function requireFields(array $input, array $required): void
    {
        $missing = self::validateRequiredFields($input, $required);

        if (!empty($missing)) {
            self::sendError(
                'Missing required fields: ' . implode(', ', $missing),
                400,
                ['missing_fields' => $missing]
            );
            exit;
        }
    }

    /**
     * Validate positive integer ID
     *
     * @param mixed $value Value to validate
     * @return bool
     */
    public static function isValidId($value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    /**
     * Require valid ID (sends error if invalid)
     *
     * @param mixed $value Value to validate
     * @param string $fieldName Field name for error message
     * @return int Validated ID
     */
    public static function requireValidId($value, string $fieldName = 'id'): int
    {
        if (!self::isValidId($value)) {
            self::sendError("Invalid {$fieldName}.", 400);
            exit;
        }
        return (int) $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize string input
     *
     * @param mixed $value Input value
     * @return string Sanitized string
     */
    public static function sanitizeString($value): string
    {
        return $value !== null ? sanitize_text_field((string) $value) : '';
    }

    /**
     * Sanitize textarea input
     *
     * @param mixed $value Input value
     * @return string Sanitized string
     */
    public static function sanitizeTextarea($value): string
    {
        return $value !== null ? sanitize_textarea_field((string) $value) : '';
    }

    /**
     * Sanitize integer input
     *
     * @param mixed $value Input value
     * @return int Sanitized integer
     */
    public static function sanitizeInt($value): int
    {
        return (int) $value;
    }

    /**
     * Sanitize float input
     *
     * @param mixed $value Input value
     * @return float Sanitized float
     */
    public static function sanitizeFloat($value): float
    {
        return (float) $value;
    }

    /**
     * Sanitize email input
     *
     * @param mixed $value Input value
     * @return string Sanitized email
     */
    public static function sanitizeEmail($value): string
    {
        return $value !== null ? sanitize_email((string) $value) : '';
    }

    /**
     * Sanitize URL input
     *
     * @param mixed $value Input value
     * @return string Sanitized URL
     */
    public static function sanitizeUrl($value): string
    {
        return $value !== null ? esc_url_raw((string) $value) : '';
    }

    /**
     * Sanitize boolean input
     *
     * @param mixed $value Input value
     * @return bool Sanitized boolean
     */
    public static function sanitizeBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Sanitize filename
     *
     * @param mixed $value Input value
     * @return string Sanitized filename
     */
    public static function sanitizeFilename($value): string
    {
        return $value !== null ? sanitize_file_name((string) $value) : '';
    }

    /**
     * Sanitize array of inputs based on schema
     *
     * @param array $input Input array
     * @param array $schema Schema defining field => type
     * @return array Sanitized array
     */
    public static function sanitizeArray(array $input, array $schema): array
    {
        $sanitized = [];

        foreach ($schema as $field => $type) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $sanitized[$field] = wecoza_sanitize_value($input[$field], $type);
        }

        return $sanitized;
    }

    /**
     * Get sanitized POST value
     *
     * @param string $key POST key
     * @param string $type Sanitization type
     * @param mixed $default Default value
     * @return mixed
     */
    public static function post(string $key, string $type = 'string', $default = null)
    {
        if (!isset($_POST[$key])) {
            return $default;
        }

        return wecoza_sanitize_value($_POST[$key], $type);
    }

    /**
     * Get sanitized GET value
     *
     * @param string $key GET key
     * @param string $type Sanitization type
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get(string $key, string $type = 'string', $default = null)
    {
        if (!isset($_GET[$key])) {
            return $default;
        }

        return wecoza_sanitize_value($_GET[$key], $type);
    }

    /*
    |--------------------------------------------------------------------------
    | Response Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Send JSON success response
     *
     * @param array $data Response data
     * @param string $message Optional success message
     * @return void
     */
    public static function sendSuccess(array $data = [], string $message = ''): void
    {
        $response = $data;
        if ($message) {
            $response['message'] = $message;
        }
        wp_send_json_success($response);
    }

    /**
     * Send JSON error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $data Additional data
     * @return void
     */
    public static function sendError(string $message, int $code = 400, array $data = []): void
    {
        $response = array_merge(['message' => $message], $data);
        wp_send_json_error($response, $code);
    }

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    */

    /**
     * Validate uploaded file
     *
     * @param array $file $_FILES array element
     * @param array $allowedMimes Allowed MIME types (e.g., ['application/pdf', 'image/jpeg'])
     * @param int $maxSize Maximum file size in bytes
     * @return array Validation result ['valid' => bool, 'error' => string|null]
     */
    public static function validateUploadedFile(array $file, array $allowedMimes = [], int $maxSize = 10485760): array
    {
        // Check for upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension.',
            ];
            $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            return ['valid' => false, 'error' => $errors[$errorCode] ?? 'Unknown upload error.'];
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            $maxMb = round($maxSize / 1048576, 1);
            return ['valid' => false, 'error' => "File exceeds maximum size of {$maxMb}MB."];
        }

        // Check MIME type if specified
        if (!empty($allowedMimes)) {
            $fileInfo = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            $actualMime = $fileInfo['type'] ?: mime_content_type($file['tmp_name']);

            if (!in_array($actualMime, $allowedMimes, true)) {
                return ['valid' => false, 'error' => 'File type not allowed.'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Get common allowed document MIME types
     *
     * @return array
     */
    public static function getAllowedDocumentMimes(): array
    {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * Get common allowed image MIME types
     *
     * @return array
     */
    public static function getAllowedImageMimes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
    }
}
