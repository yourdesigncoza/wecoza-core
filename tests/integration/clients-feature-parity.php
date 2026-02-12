<?php
/**
 * WeCoza Core - Clients Integration Feature Parity Tests
 *
 * Verifies that the integrated Clients module in wecoza-core provides
 * all functionality previously in the standalone wecoza-clients-plugin:
 * - 6 shortcodes registered
 * - 16 AJAX endpoints registered
 * - 8 classes in WeCoza\Clients namespace
 * - 3 database tables queryable
 * - View templates present
 * - No standalone plugin dependency references
 *
 * Run with: php tests/integration/clients-feature-parity.php
 * Or via WP-CLI: wp eval-file tests/integration/clients-feature-parity.php
 *
 * @package WeCoza\Tests
 * @since 2.0.0
 */

// Prevent web access - CLI only
if (php_sapi_name() !== 'cli' && !defined('WP_CLI')) {
    die('This script can only be run from command line.');
}

// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    $wp_load = dirname(__FILE__, 6) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die("Could not find wp-load.php. Run this script from the plugin directory.\n");
    }
}

class ClientsParityTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "\n=== WeCoza Core - Clients Feature Parity Tests ===\n\n";

        $this->testShortcodeRegistration();
        $this->testAjaxEndpointRegistration();
        $this->testNamespaceClasses();
        $this->testDatabaseConnectivity();
        $this->testViewTemplateExistence();
        $this->testNoStandalonePluginDependency();

        $this->printResults();
    }

    /**
     * Test 1: All 6 shortcodes registered
     */
    private function testShortcodeRegistration(): void
    {
        echo "--- Shortcode Registration ---\n";

        $shortcodes = [
            'wecoza_capture_clients',
            'wecoza_display_clients',
            'wecoza_update_clients',
            'wecoza_locations_capture',
            'wecoza_locations_list',
            'wecoza_locations_edit',
        ];

        foreach ($shortcodes as $shortcode) {
            if (shortcode_exists($shortcode)) {
                $this->pass("Shortcode [{$shortcode}] registered");
            } else {
                $this->fail("Shortcode [{$shortcode}] NOT registered");
            }
        }

        echo "\n";
    }

    /**
     * Test 2: All 16 AJAX endpoints registered
     */
    private function testAjaxEndpointRegistration(): void
    {
        echo "--- AJAX Endpoint Registration ---\n";

        global $wp_filter;

        $endpoints = [
            // Client endpoints (8)
            'wp_ajax_wecoza_save_client',
            'wp_ajax_wecoza_get_client',
            'wp_ajax_wecoza_get_client_details',
            'wp_ajax_wecoza_delete_client',
            'wp_ajax_wecoza_search_clients',
            'wp_ajax_wecoza_get_branch_clients',
            'wp_ajax_wecoza_export_clients',
            'wp_ajax_wecoza_get_main_clients',
            // Location endpoints (3)
            'wp_ajax_wecoza_get_locations',
            'wp_ajax_wecoza_save_location',
            'wp_ajax_wecoza_check_location_duplicates',
            // Site endpoints (5)
            'wp_ajax_wecoza_save_sub_site',
            'wp_ajax_wecoza_get_head_sites',
            'wp_ajax_wecoza_get_sub_sites',
            'wp_ajax_wecoza_delete_sub_site',
            'wp_ajax_wecoza_get_sites_hierarchy',
        ];

        foreach ($endpoints as $hook) {
            if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
                $this->pass("AJAX endpoint [{$hook}] registered");
            } else {
                $this->fail("AJAX endpoint [{$hook}] NOT registered");
            }
        }

        echo "\n";
    }

    /**
     * Test 3: All 8 namespace classes exist
     */
    private function testNamespaceClasses(): void
    {
        echo "--- Namespace Class Verification ---\n";

        $classes = [
            '\\WeCoza\\Clients\\Controllers\\ClientsController',
            '\\WeCoza\\Clients\\Controllers\\LocationsController',
            '\\WeCoza\\Clients\\Ajax\\ClientAjaxHandlers',
            '\\WeCoza\\Clients\\Models\\ClientsModel',
            '\\WeCoza\\Clients\\Models\\LocationsModel',
            '\\WeCoza\\Clients\\Models\\SitesModel',
            '\\WeCoza\\Clients\\Repositories\\ClientRepository',
            '\\WeCoza\\Clients\\Repositories\\LocationRepository',
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                $this->pass("Class {$class} exists");
            } else {
                $this->fail("Class {$class} NOT found");
            }
        }

        echo "\n";
    }

    /**
     * Test 4: Database connectivity and table existence
     */
    private function testDatabaseConnectivity(): void
    {
        echo "--- Database Connectivity ---\n";

        try {
            $db = wecoza_db();
            $this->pass("wecoza_db() connection established");
        } catch (\Exception $e) {
            $this->fail("wecoza_db() connection failed: " . $e->getMessage());
            return;
        }

        $tables = ['clients', 'locations', 'sites'];

        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT 1 FROM {$table} LIMIT 1");
                $this->pass("Table [{$table}] exists and is queryable");
            } catch (\Exception $e) {
                $this->fail("Table [{$table}] query failed: " . $e->getMessage());
            }
        }

        echo "\n";
    }

    /**
     * Test 5: View template files exist
     */
    private function testViewTemplateExistence(): void
    {
        echo "--- View Template Existence ---\n";

        $pluginPath = dirname(__FILE__, 3);

        $views = [
            'views/clients/components/client-capture-form.view.php',
            'views/clients/components/client-update-form.view.php',
            'views/clients/components/location-capture-form.view.php',
            'views/clients/display/clients-display.view.php',
            'views/clients/display/clients-table.view.php',
            'views/clients/display/locations-list.view.php',
        ];

        foreach ($views as $view) {
            $fullPath = $pluginPath . '/' . $view;
            if (file_exists($fullPath)) {
                $this->pass("View [{$view}] exists");
            } else {
                $this->fail("View [{$view}] NOT found at {$fullPath}");
            }
        }

        $dirs = [
            'views/clients/display',
            'views/clients/components',
        ];

        foreach ($dirs as $dir) {
            $fullPath = $pluginPath . '/' . $dir;
            if (is_dir($fullPath)) {
                $this->pass("Directory [{$dir}] exists");
            } else {
                $this->fail("Directory [{$dir}] NOT found");
            }
        }

        echo "\n";
    }

    /**
     * Test 6: No standalone plugin dependency references in src/Clients/
     */
    private function testNoStandalonePluginDependency(): void
    {
        echo "--- No Standalone Plugin Dependency ---\n";

        $pluginPath = dirname(__FILE__, 3);
        $srcDir = $pluginPath . '/src/Clients';

        if (!is_dir($srcDir)) {
            $this->fail("src/Clients/ directory not found");
            return;
        }

        $patterns = [
            'WeCozaClients' => 'WeCozaClients namespace reference',
            'WECOZA_CLIENTS_PLUGIN_' => 'WECOZA_CLIENTS_PLUGIN_ constant reference',
        ];

        $phpFiles = $this->findPhpFiles($srcDir);

        foreach ($patterns as $pattern => $description) {
            $found = false;
            $foundIn = '';

            foreach ($phpFiles as $file) {
                $content = file_get_contents($file);
                if (strpos($content, $pattern) !== false) {
                    $found = true;
                    $foundIn = str_replace($pluginPath . '/', '', $file);
                    break;
                }
            }

            if (!$found) {
                $this->pass("No {$description} in src/Clients/");
            } else {
                $this->fail("Found {$description} in {$foundIn}");
            }
        }

        echo "\n";
    }

    /**
     * Recursively find PHP files in a directory
     */
    private function findPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function pass(string $message): void
    {
        $this->passed++;
        echo "  ✓ PASS: {$message}\n";
    }

    private function fail(string $message): void
    {
        $this->failed++;
        echo "  ✗ FAIL: {$message}\n";
    }

    private function printResults(): void
    {
        echo "=================================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "=================================\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$runner = new ClientsParityTest();
$runner->run();
