<?php
/**
 * Standalone test bootstrap for History tests.
 *
 * When WordPress MySQL is not available, this provides the minimal
 * environment needed to test against PostgreSQL directly.
 *
 * Usage: require_once __DIR__ . '/bootstrap.php';
 */

define('ABSPATH', '/opt/lampp/htdocs/wecoza/');
define('WPINC', 'wp-includes');

// Load config for DB defaults
$config = include __DIR__ . '/../../config/app.php';
$dbDefaults = $config['database']['defaults'] ?? [];

// Provide get_option() if WordPress isn't loaded
if (!function_exists('get_option')) {
    /**
     * Minimal get_option mock for standalone testing.
     * Reads PostgreSQL credentials from wp_options via a direct MySQL-less approach.
     */
    function get_option(string $key, $default = false)
    {
        static $opts = null;
        if ($opts === null) {
            $config = include __DIR__ . '/../../config/app.php';
            $d = $config['database']['defaults'] ?? [];

            // Try to read password from environment or a local secret file
            $password = getenv('WECOZA_PG_PASSWORD');
            if (!$password) {
                $secretFile = __DIR__ . '/../../.pg_password';
                if (file_exists($secretFile)) {
                    $password = trim(file_get_contents($secretFile));
                }
            }

            $opts = [
                'wecoza_postgres_host' => $d['host'] ?? '',
                'wecoza_postgres_port' => $d['port'] ?? '5432',
                'wecoza_postgres_dbname' => $d['dbname'] ?? '',
                'wecoza_postgres_user' => $d['user'] ?? '',
                'wecoza_postgres_password' => $password ?: '',
            ];
        }
        return $opts[$key] ?? $default;
    }
}

// Provide error_log if needed
if (!function_exists('wecoza_log')) {
    function wecoza_log(string $message, string $level = 'debug'): void
    {
        error_log("[WeCoza][{$level}] {$message}");
    }
}

// Load core classes
$pluginDir = __DIR__ . '/../../';
require_once $pluginDir . 'core/Abstract/AppConstants.php';
require_once $pluginDir . 'core/Database/PostgresConnection.php';
require_once $pluginDir . 'core/Abstract/BaseRepository.php';
