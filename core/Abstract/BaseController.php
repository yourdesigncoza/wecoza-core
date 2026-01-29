<?php
/**
 * WeCoza Core - Abstract Base Controller
 *
 * Provides common controller functionality including database access,
 * view rendering, AJAX helpers, and input sanitization.
 *
 * Child classes should:
 * - Override registerHooks() to register WordPress hooks
 * - Use render() and component() for view output
 * - Use AJAX helpers for secure endpoint handling
 *
 * @package WeCoza\Core\Abstract
 * @since 1.0.0
 */

namespace WeCoza\Core\Abstract;

use WeCoza\Core\Database\PostgresConnection;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseController
{
    /**
     * Database connection instance
     */
    protected ?PostgresConnection $db = null;

    /**
     * Constructor
     *
     * Calls registerHooks() to set up WordPress integration.
     */
    public function __construct()
    {
        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     *
     * Override in child classes to register actions, filters, shortcodes.
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        // Override in child class
    }

    /*
    |--------------------------------------------------------------------------
    | Database Access
    |--------------------------------------------------------------------------
    */

    /**
     * Get database connection
     *
     * @return PostgresConnection
     */
    protected function db(): PostgresConnection
    {
        if ($this->db === null) {
            $this->db = PostgresConnection::getInstance();
        }
        return $this->db;
    }

    /*
    |--------------------------------------------------------------------------
    | View Rendering
    |--------------------------------------------------------------------------
    */

    /**
     * Render a view
     *
     * @param string $view View path
     * @param array $data Data to pass to view
     * @param bool $return Return output instead of echoing
     * @return string|void
     */
    protected function render(string $view, array $data = [], bool $return = true)
    {
        return wecoza_view($view, $data, $return);
    }

    /**
     * Render a component
     *
     * @param string $component Component path
     * @param array $data Data to pass to component
     * @param bool $return Return output instead of echoing
     * @return string|void
     */
    protected function component(string $component, array $data = [], bool $return = true)
    {
        return wecoza_component($component, $data, $return);
    }

    /**
     * Get configuration value
     *
     * @param string|null $key Config key (dot notation supported)
     * @return mixed
     */
    protected function config(?string $key = null)
    {
        $config = wecoza_config('app');

        if ($key === null) {
            return $config;
        }

        return wecoza_array_get($config, $key);
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX Security Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Verify AJAX nonce
     *
     * @param string $action Nonce action name
     * @param string $field Request field containing nonce
     * @return bool
     */
    protected function verifyNonce(string $action, string $field = 'nonce'): bool
    {
        return check_ajax_referer($action, $field, false);
    }

    /**
     * Require valid nonce (sends error and exits if invalid)
     *
     * @param string $action Nonce action name
     * @param string $field Request field containing nonce
     * @return void
     */
    protected function requireNonce(string $action, string $field = 'nonce'): void
    {
        if (!$this->verifyNonce($action, $field)) {
            $this->sendError('Invalid security token.', 403);
            exit;
        }
    }

    /**
     * Check user capability
     *
     * @param string $capability Capability to check
     * @return bool
     */
    protected function checkCapability(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Require user capability (sends error and exits if not met)
     *
     * @param string $capability Capability required
     * @return void
     */
    protected function requireCapability(string $capability): void
    {
        if (!$this->checkCapability($capability)) {
            $this->sendError('Insufficient permissions.', 403);
            exit;
        }
    }

    /**
     * Combined security check (nonce + capability)
     *
     * @param string $nonceAction Nonce action
     * @param string $capability Required capability
     * @param string $nonceField Nonce field name
     * @return void
     */
    protected function requireAuth(string $nonceAction, string $capability = 'manage_options', string $nonceField = 'nonce'): void
    {
        $this->requireNonce($nonceAction, $nonceField);
        $this->requireCapability($capability);
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
     * @param string $message Optional message
     * @return void
     */
    protected function sendSuccess(array $data = [], string $message = ''): void
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
    protected function sendError(string $message, int $code = 400, array $data = []): void
    {
        $response = array_merge(['message' => $message], $data);
        wp_send_json_error($response, $code);
    }

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    */

    /**
     * Get sanitized POST value
     *
     * @param string $key POST key
     * @param string $type Type to sanitize as (string, int, email, url, bool)
     * @param mixed $default Default value if not set
     * @return mixed
     */
    protected function input(string $key, string $type = 'string', $default = null)
    {
        if (!isset($_POST[$key])) {
            return $default;
        }

        $value = $_POST[$key];

        return match ($type) {
            'string', 'text' => sanitize_text_field($value),
            'textarea' => sanitize_textarea_field($value),
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'email' => sanitize_email($value),
            'url' => esc_url_raw($value),
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => is_array($value) ? $value : [],
            'raw' => $value,
            default => sanitize_text_field($value),
        };
    }

    /**
     * Get sanitized GET value
     *
     * @param string $key GET key
     * @param string $type Type to sanitize as
     * @param mixed $default Default value
     * @return mixed
     */
    protected function query(string $key, string $type = 'string', $default = null)
    {
        if (!isset($_GET[$key])) {
            return $default;
        }

        $value = $_GET[$key];

        return match ($type) {
            'string', 'text' => sanitize_text_field($value),
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => sanitize_text_field($value),
        };
    }

    /**
     * Sanitize string input
     *
     * @param string|null $value Input value
     * @return string
     */
    protected function sanitizeString(?string $value): string
    {
        return $value ? sanitize_text_field($value) : '';
    }

    /**
     * Sanitize integer input
     *
     * @param mixed $value Input value
     * @return int
     */
    protected function sanitizeInt($value): int
    {
        return (int) $value;
    }

    /**
     * Sanitize email input
     *
     * @param string|null $value Input value
     * @return string
     */
    protected function sanitizeEmail(?string $value): string
    {
        return $value ? sanitize_email($value) : '';
    }

    /**
     * Sanitize URL input
     *
     * @param string|null $value Input value
     * @return string
     */
    protected function sanitizeUrl(?string $value): string
    {
        return $value ? esc_url_raw($value) : '';
    }

    /**
     * Sanitize array of inputs based on schema
     *
     * @param array $input Input array
     * @param array $schema Schema defining field => type
     * @return array Sanitized array
     */
    protected function sanitizeArray(array $input, array $schema): array
    {
        $sanitized = [];

        foreach ($schema as $field => $type) {
            if (!isset($input[$field])) {
                continue;
            }

            $sanitized[$field] = match ($type) {
                'string', 'text' => $this->sanitizeString($input[$field]),
                'textarea' => sanitize_textarea_field($input[$field]),
                'int', 'integer' => $this->sanitizeInt($input[$field]),
                'float', 'double' => (float) $input[$field],
                'email' => $this->sanitizeEmail($input[$field]),
                'url' => $this->sanitizeUrl($input[$field]),
                'bool', 'boolean' => filter_var($input[$field], FILTER_VALIDATE_BOOLEAN),
                'date' => $this->sanitizeString($input[$field]),
                'array' => is_array($input[$field]) ? $input[$field] : [],
                'json' => is_string($input[$field]) ? json_decode($input[$field], true) : $input[$field],
                default => $input[$field],
            };
        }

        return $sanitized;
    }

    /**
     * Validate required fields
     *
     * @param array $input Input array
     * @param array $required Required field names
     * @return array Missing field names (empty if all present)
     */
    protected function validateRequired(array $input, array $required): array
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
     * Require fields to be present (sends error if missing)
     *
     * @param array $input Input array
     * @param array $required Required field names
     * @return void
     */
    protected function requireFields(array $input, array $required): void
    {
        $missing = $this->validateRequired($input, $required);

        if (!empty($missing)) {
            $this->sendError(
                'Missing required fields: ' . implode(', ', $missing),
                400,
                ['missing_fields' => $missing]
            );
            exit;
        }
    }
}
