<?php
declare(strict_types=1);

/**
 * WeCoza Core - Global Helper Functions
 *
 * Provides view rendering, configuration loading, and utility functions.
 * These functions are available globally with wecoza_ prefix.
 *
 * @package WeCoza\Core\Helpers
 * @since 1.0.0
 */

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/*
|--------------------------------------------------------------------------
| Configuration Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_config')) {
    /**
     * Load configuration file
     *
     * @param string $name Config file name (without .php extension)
     * @return array Configuration array
     */
    function wecoza_config(string $name): array {
        static $cache = [];

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $file = WECOZA_CORE_PATH . 'config/' . $name . '.php';

        if (file_exists($file)) {
            $cache[$name] = require $file;
            return $cache[$name];
        }

        return [];
    }
}

/*
|--------------------------------------------------------------------------
| View Rendering Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_view')) {
    /**
     * Render a view file
     *
     * Supports both .view.php (Classes style) and .php (Learners style) extensions.
     * Data is extracted to local scope using EXTR_SKIP for security.
     *
     * WARNING: Data array keys become local variables via extract().
     * Avoid using these reserved keys in $data as they may conflict:
     * - 'file', 'basePath', 'return', 'data', 'view'
     * - Any PHP superglobals ($_GET, $_POST, etc.)
     *
     * @param string $view View path relative to views directory (without extension)
     * @param array $data Data to pass to the view.
     * @param bool $return Whether to return output (true) or echo it (false)
     * @return string|void HTML output if $return is true
     */
    function wecoza_view(string $view, array $data = [], bool $return = true) {
        // Allow plugins to override the views path
        $basePath = apply_filters('wecoza_views_path', WECOZA_CORE_PATH . 'views/');
        $file = $basePath . ltrim($view, '/');

        // Add extension if not present
        if (substr($file, -4) !== '.php') {
            // Try .view.php first (Classes style), then .php (Learners style)
            if (file_exists($file . '.view.php')) {
                $file .= '.view.php';
            } else {
                $file .= '.php';
            }
        }

        if (!file_exists($file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WeCoza Core: View not found: {$file}");
            }
            return $return ? '' : null;
        }

        // Extract data to local scope (EXTR_SKIP prevents overwriting existing vars)
        extract($data, EXTR_SKIP);

        if ($return) {
            ob_start();
            include $file;
            return ob_get_clean();
        }

        include $file;
    }
}

if (!function_exists('wecoza_component')) {
    /**
     * Render a component (partial view)
     *
     * Components are stored in views/components/ directory.
     *
     * @param string $component Component path relative to views/components/
     * @param array $data Data to pass to the component
     * @param bool $return Whether to return output (true) or echo it (false)
     * @return string|void HTML output if $return is true
     */
    function wecoza_component(string $component, array $data = [], bool $return = true) {
        return wecoza_view('components/' . $component, $data, $return);
    }
}

/*
|--------------------------------------------------------------------------
| Asset URL Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_asset_url')) {
    /**
     * Get URL to an asset file
     *
     * @param string $asset Asset path relative to assets/ directory
     * @return string Full URL to the asset
     */
    function wecoza_asset_url(string $asset): string {
        return WECOZA_CORE_URL . 'assets/' . ltrim($asset, '/');
    }
}

if (!function_exists('wecoza_css_url')) {
    /**
     * Get URL to a CSS file
     *
     * @param string $file CSS filename (without path)
     * @return string Full URL to the CSS file
     */
    function wecoza_css_url(string $file): string {
        return wecoza_asset_url('css/' . $file);
    }
}

if (!function_exists('wecoza_js_url')) {
    /**
     * Get URL to a JavaScript file
     *
     * @param string $file JS filename (without path)
     * @return string Full URL to the JS file
     */
    function wecoza_js_url(string $file): string {
        return wecoza_asset_url('js/' . $file);
    }
}

/*
|--------------------------------------------------------------------------
| Path Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_plugin_path')) {
    /**
     * Get full server path to plugin directory or file
     *
     * @param string $path Path relative to plugin root (optional)
     * @return string Full server path
     */
    function wecoza_plugin_path(string $path = ''): string {
        return WECOZA_CORE_PATH . ltrim($path, '/');
    }
}

if (!function_exists('wecoza_core_path')) {
    /**
     * Get full server path to core directory or file
     *
     * @param string $path Path relative to core/ directory (optional)
     * @return string Full server path
     */
    function wecoza_core_path(string $path = ''): string {
        return WECOZA_CORE_PATH . 'core/' . ltrim($path, '/');
    }
}

/*
|--------------------------------------------------------------------------
| Environment Detection Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_is_admin_area')) {
    /**
     * Check if in WordPress admin area (excluding AJAX requests)
     *
     * @return bool
     */
    function wecoza_is_admin_area(): bool {
        return is_admin() && !wp_doing_ajax();
    }
}

if (!function_exists('wecoza_is_ajax')) {
    /**
     * Check if processing an AJAX request
     *
     * @return bool
     */
    function wecoza_is_ajax(): bool {
        return wp_doing_ajax();
    }
}

if (!function_exists('wecoza_is_rest')) {
    /**
     * Check if processing a REST API request
     *
     * @return bool
     */
    function wecoza_is_rest(): bool {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
}

/*
|--------------------------------------------------------------------------
| Debug/Logging Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_log')) {
    /**
     * Log a message (only when WP_DEBUG is enabled)
     *
     * Automatically appends caller file:line for warning/error levels.
     * Pass a Throwable as $context to log its message and trace.
     *
     * @param string          $message Message to log
     * @param string          $level   Log level (info, warning, error)
     * @param \Throwable|null $context Optional exception/error for stack trace
     * @return void
     */
    function wecoza_log(string $message, string $level = 'info', ?\Throwable $context = null): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $prefix = sprintf('[WeCoza][%s]', strtoupper($level));

        // Append caller location for warning/error so we know WHERE the log came from
        if (in_array($level, ['warning', 'error'], true)) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $frame  = $caller[1] ?? $caller[0] ?? null;
            if ($frame) {
                $file = basename($frame['file'] ?? 'unknown');
                $line = $frame['line'] ?? '?';
                $message .= " ({$file}:{$line})";
            }
        }

        error_log("{$prefix} {$message}");

        // Log exception details when provided
        if ($context !== null) {
            error_log("{$prefix} Exception: " . $context->getMessage());
            error_log("{$prefix} Trace: " . $context->getTraceAsString());
        }
    }
}

/*
|--------------------------------------------------------------------------
| Database Helper Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_db')) {
    /**
     * Get the PostgreSQL database connection instance
     *
     * @return \WeCoza\Core\Database\PostgresConnection
     */
    function wecoza_db(): \WeCoza\Core\Database\PostgresConnection {
        return \WeCoza\Core\Database\PostgresConnection::getInstance();
    }
}

/*
|--------------------------------------------------------------------------
| Array/Data Helper Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_array_get')) {
    /**
     * Get a value from a nested array using dot notation
     *
     * @param array $array Source array
     * @param string $key Key in dot notation (e.g., 'database.host')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function wecoza_array_get(array $array, string $key, $default = null) {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('wecoza_snake_to_camel')) {
    /**
     * Convert snake_case string to camelCase
     *
     * @param string $value Input string
     * @return string camelCase string
     */
    function wecoza_snake_to_camel(string $value): string {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }
}

if (!function_exists('wecoza_camel_to_snake')) {
    /**
     * Convert camelCase string to snake_case
     *
     * @param string $value Input string
     * @return string snake_case string
     */
    function wecoza_camel_to_snake(string $value): string {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}

/*
|--------------------------------------------------------------------------
| Exception/Error Sanitization Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_sanitize_exception')) {
    /**
     * Sanitize exception message for logging
     *
     * Removes sensitive information from exception messages before logging:
     * - Database schema/table names patterns
     * - Column names in error messages
     * - Connection strings
     * - SQL query fragments
     *
     * @param string $message Original exception message
     * @param string $context Optional context prefix (e.g., "LearnerRepository")
     * @return string Sanitized message safe for logging
     */
    function wecoza_sanitize_exception(string $message, string $context = ''): string
    {
        // Patterns that might expose schema details
        $patterns = [
            // Remove table.column references
            '/\b[a-z_]+\.[a-z_]+/i' => '[table.column]',
            // Remove "column X" patterns
            '/column\s+["\']?[a-z_]+["\']?/i' => 'column [redacted]',
            // Remove "table X" patterns
            '/table\s+["\']?[a-z_]+["\']?/i' => 'table [redacted]',
            // Remove SQL fragments
            '/\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN)\b.*$/i' => '[SQL redacted]',
            // Remove constraint names
            '/constraint\s+["\']?[a-z_]+["\']?/i' => 'constraint [redacted]',
            // Remove index names
            '/index\s+["\']?[a-z_]+["\']?/i' => 'index [redacted]',
        ];

        $sanitized = $message;
        foreach ($patterns as $pattern => $replacement) {
            $sanitized = preg_replace($pattern, $replacement, $sanitized);
        }

        // Truncate if too long (prevent log flooding)
        if (strlen($sanitized) > 200) {
            $sanitized = substr($sanitized, 0, 200) . '...';
        }

        // Add context prefix if provided
        $prefix = $context ? "WeCoza Core [{$context}]: " : "WeCoza Core: ";

        return $prefix . $sanitized;
    }
}

if (!function_exists('wecoza_admin_exception_details')) {
    /**
     * Get admin-safe exception details
     *
     * For administrators only - provides more detail than logs but still sanitized.
     * Use current_user_can('manage_options') before showing this to users.
     *
     * @param Exception $e The exception
     * @param string $context Context identifier
     * @return array Admin-safe error details
     */
    function wecoza_admin_exception_details(\Exception $e, string $context = ''): array
    {
        return [
            'context' => $context,
            'type' => get_class($e),
            'code' => $e->getCode(),
            'file' => basename($e->getFile()) . ':' . $e->getLine(),
            'message' => wecoza_sanitize_exception($e->getMessage()),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Input Sanitization Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_transform_dropdown')) {
    /**
     * Transform an array of associative arrays into a standardised dropdown format.
     *
     * Replaces repetitive array_map calls that convert varying key names
     * (e.g. 'location_id'/'town') into a consistent ['id' => …, 'name' => …] shape.
     *
     * @param array  $data      Source data rows.
     * @param string $idField   Key to use as the 'id' value.
     * @param string $nameField Key to use as the 'name' value.
     * @return array Transformed array with 'id' and 'name' keys.
     */
    function wecoza_transform_dropdown(array $data, string $idField, string $nameField): array
    {
        return array_map(fn($item) => [
            'id'   => $item[$idField],
            'name' => $item[$nameField],
        ], $data);
    }
}

/*
|--------------------------------------------------------------------------
| Class Status Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('wecoza_resolve_class_status')) {
    /**
     * Resolve the effective class status from a class data array.
     *
     * During the migration window, class_status may be NULL for rows that were
     * inserted before the column was added. This helper provides a safe fallback:
     * - If class_status is present and non-empty, use it directly.
     * - Otherwise, derive status from order_nr (non-empty = active, empty = draft).
     *
     * This function (CC1) must be used everywhere class status is read to ensure
     * backward compatibility during the migration period.
     *
     * @param array $class Class data row (from DB query or ClassModel::toArray())
     * @return string One of 'draft', 'active', or 'stopped'
     */
    function wecoza_resolve_class_status(array $class): string
    {
        return $class['class_status'] ?? (empty($class['order_nr']) ? 'draft' : 'active');
    }
}

if (!function_exists('wecoza_class_status_badge_svg')) {
    /**
     * Return a Feather SVG icon for a class status, matching Phoenix badge conventions.
     *
     * @param string $status One of 'active', 'stopped', or 'draft' (default).
     * @return string Inline SVG markup with ms-1 class for badge alignment.
     */
    function wecoza_class_status_badge_svg(string $status): string
    {
        $svgAttrs = 'xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="height:12.8px;width:12.8px;"';

        return match ($status) {
            'active'  => '<svg ' . $svgAttrs . ' class="feather feather-check ms-1"><polyline points="20 6 9 17 4 12"></polyline></svg>',
            'stopped' => '<svg ' . $svgAttrs . ' class="feather feather-x ms-1"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
            default   => '<svg ' . $svgAttrs . ' class="feather feather-alert-octagon ms-1"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
        };
    }
}

if (!function_exists('wecoza_sanitize_value')) {
    /**
     * Sanitize a value based on type
     *
     * Centralized sanitization used by BaseController, BaseModel, and AjaxSecurity.
     * Supports WordPress sanitization functions when available.
     *
     * @param mixed $value Value to sanitize
     * @param string $type Type to sanitize as (string, int, email, url, bool, array, json, raw)
     * @return mixed Sanitized value
     */
    function wecoza_sanitize_value(mixed $value, string $type): mixed {
        if ($value === null) {
            return match ($type) {
                'string', 'text', 'textarea', 'email', 'url' => '',
                'int', 'integer' => 0,
                'float', 'double' => 0.0,
                'bool', 'boolean' => false,
                'array', 'json' => [],
                default => null,
            };
        }

        return match ($type) {
            'string', 'text' => function_exists('sanitize_text_field')
                ? sanitize_text_field((string) $value)
                : htmlspecialchars(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'),
            'textarea' => function_exists('sanitize_textarea_field')
                ? sanitize_textarea_field((string) $value)
                : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'email' => function_exists('sanitize_email')
                ? sanitize_email((string) $value)
                : filter_var($value, FILTER_SANITIZE_EMAIL),
            'url' => function_exists('esc_url_raw')
                ? esc_url_raw((string) $value)
                : filter_var($value, FILTER_SANITIZE_URL),
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => is_array($value) ? $value : [],
            'json' => is_string($value) ? (json_decode($value, true) ?? []) : (is_array($value) ? $value : []),
            'filename' => function_exists('sanitize_file_name')
                ? sanitize_file_name((string) $value)
                : preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $value),
            'date' => is_string($value) ? (function_exists('sanitize_text_field')
                ? sanitize_text_field($value)
                : htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) : '',
            'datetime' => is_string($value) ? (function_exists('sanitize_text_field')
                ? sanitize_text_field($value)
                : htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) : '',
            'raw' => $value,
            default => function_exists('sanitize_text_field')
                ? sanitize_text_field((string) $value)
                : htmlspecialchars(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'),
        };
    }
}
