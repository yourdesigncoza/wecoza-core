<?php
/**
 * WeCoza Core - Agents Integration Feature Parity Tests
 *
 * Verifies that the integrated Agents module in wecoza-core provides
 * all functionality previously in the standalone wecoza-agents-plugin:
 * - 3 shortcodes registered
 * - 2 AJAX endpoints registered (no nopriv handlers - Bug #12 fix)
 * - 7 classes in WeCoza\Agents namespace
 * - 4 database tables queryable
 * - 6 view templates present
 * - 5 JS assets present
 * - Statistics calculation works
 * - WorkingAreasService works
 * - Metadata CRUD operations work (agent_meta, agent_notes, agent_absences)
 * - No standalone plugin dependency references
 *
 * Run with: php tests/integration/agents-feature-parity.php
 * Or via WP-CLI: wp eval-file tests/integration/agents-feature-parity.php
 *
 * @package WeCoza\Tests
 * @since 3.0.0
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

class AgentsParityTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "\n=== WeCoza Core - Agents Feature Parity Tests ===\n\n";

        $this->testShortcodeRegistration();
        $this->testAjaxEndpointRegistration();
        $this->testNamespaceClasses();
        $this->testDatabaseConnectivity();
        $this->testViewTemplateExistence();
        $this->testJsAssetExistence();
        $this->testStatisticsCalculation();
        $this->testWorkingAreasService();
        $this->testAgentMetadataCRUD();
        $this->testNoStandalonePluginDependency();

        $this->printResults();
    }

    /**
     * Test 1: All 3 shortcodes registered
     */
    private function testShortcodeRegistration(): void
    {
        echo "--- Shortcode Registration ---\n";

        $shortcodes = [
            'wecoza_capture_agents',
            'wecoza_display_agents',
            'wecoza_single_agent',
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
     * Test 2: All 2 AJAX endpoints registered (no nopriv - Bug #12 fix)
     */
    private function testAjaxEndpointRegistration(): void
    {
        echo "--- AJAX Endpoint Registration ---\n";

        global $wp_filter;

        $endpoints = [
            'wp_ajax_wecoza_agents_paginate',
            'wp_ajax_wecoza_agents_delete',
        ];

        foreach ($endpoints as $hook) {
            if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
                $this->pass("AJAX endpoint [{$hook}] registered");
            } else {
                $this->fail("AJAX endpoint [{$hook}] NOT registered");
            }
        }

        // Verify NO nopriv handlers exist (Bug #12 fix)
        $nopriv_endpoints = [
            'wp_ajax_nopriv_wecoza_agents_paginate',
            'wp_ajax_nopriv_wecoza_agents_delete',
        ];

        foreach ($nopriv_endpoints as $hook) {
            if (!isset($wp_filter[$hook]) || empty($wp_filter[$hook]->callbacks)) {
                $this->pass("NO nopriv handler for [{$hook}] (Bug #12 fix)");
            } else {
                $this->fail("FOUND nopriv handler for [{$hook}] (should not exist)");
            }
        }

        echo "\n";
    }

    /**
     * Test 3: All 7 namespace classes exist
     */
    private function testNamespaceClasses(): void
    {
        echo "--- Namespace Class Verification ---\n";

        $classes = [
            '\\WeCoza\\Agents\\Controllers\\AgentsController',
            '\\WeCoza\\Agents\\Ajax\\AgentsAjaxHandlers',
            '\\WeCoza\\Agents\\Models\\AgentModel',
            '\\WeCoza\\Agents\\Repositories\\AgentRepository',
            '\\WeCoza\\Agents\\Services\\WorkingAreasService',
            '\\WeCoza\\Agents\\Helpers\\FormHelpers',
            '\\WeCoza\\Agents\\Helpers\\ValidationHelper',
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

        $tables = ['agents', 'agent_meta', 'agent_notes', 'agent_absences'];

        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $count = $row['count'];
                $this->pass("Table [{$table}] exists and queryable (count: {$count})");
            } catch (\Exception $e) {
                // agent_meta table doesn't exist yet (FEAT-02 not implemented)
                if ($table === 'agent_meta') {
                    $this->fail("Table [{$table}] does not exist (FEAT-02 not implemented yet)");
                } else {
                    $this->fail("Table [{$table}] query failed: " . $e->getMessage());
                }
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
            'views/agents/components/agent-capture-form.view.php',
            'views/agents/components/agent-fields.view.php',
            'views/agents/display/agent-display-table.view.php',
            'views/agents/display/agent-display-table-rows.view.php',
            'views/agents/display/agent-pagination.view.php',
            'views/agents/display/agent-single-display.view.php',
        ];

        foreach ($views as $view) {
            $fullPath = $pluginPath . '/' . $view;
            if (file_exists($fullPath)) {
                $this->pass("View [{$view}] exists");
            } else {
                $this->fail("View [{$view}] NOT found at {$fullPath}");
            }
        }

        echo "\n";
    }

    /**
     * Test 6: JS asset files exist
     */
    private function testJsAssetExistence(): void
    {
        echo "--- JS Asset Existence ---\n";

        $pluginPath = dirname(__FILE__, 3);

        $jsFiles = [
            'assets/js/agents/agents-app.js',
            'assets/js/agents/agent-form-validation.js',
            'assets/js/agents/agents-ajax-pagination.js',
            'assets/js/agents/agents-table-search.js',
            'assets/js/agents/agent-delete.js',
        ];

        foreach ($jsFiles as $jsFile) {
            $fullPath = $pluginPath . '/' . $jsFile;
            if (file_exists($fullPath)) {
                $this->pass("JS file [{$jsFile}] exists");
            } else {
                $this->fail("JS file [{$jsFile}] NOT found at {$fullPath}");
            }
        }

        echo "\n";
    }

    /**
     * Test 7: Statistics calculation works
     */
    private function testStatisticsCalculation(): void
    {
        echo "--- Statistics Calculation ---\n";

        try {
            $db = wecoza_db();

            // Total agents (not deleted)
            $stmt = $db->query("SELECT COUNT(*) as count FROM agents WHERE status != 'deleted'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $total = $row['count'];
            $this->pass("Total agents (not deleted): {$total}");

            // Active agents
            $stmt = $db->query("SELECT COUNT(*) as count FROM agents WHERE status = 'active'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $active = $row['count'];
            $this->pass("Active agents: {$active}");

            // SACE registered
            $stmt = $db->query("SELECT COUNT(*) as count FROM agents WHERE sace_number IS NOT NULL AND sace_number != '' AND status != 'deleted'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $sace = $row['count'];
            $this->pass("SACE registered agents: {$sace}");

            // Quantum assessed
            $stmt = $db->query("SELECT COUNT(*) as count FROM agents WHERE (quantum_maths_score > 0 OR quantum_science_score > 0) AND status != 'deleted'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $quantum = $row['count'];
            $this->pass("Quantum assessed agents: {$quantum}");

        } catch (\Exception $e) {
            $this->fail("Statistics calculation failed: " . $e->getMessage());
        }

        echo "\n";
    }

    /**
     * Test 8: WorkingAreasService works correctly
     */
    private function testWorkingAreasService(): void
    {
        echo "--- WorkingAreasService ---\n";

        try {
            // Test get_working_areas returns exactly 14 areas
            $areas = \WeCoza\Agents\Services\WorkingAreasService::get_working_areas();
            if (count($areas) === 14) {
                $this->pass("get_working_areas() returns exactly 14 areas");
            } else {
                $this->fail("get_working_areas() returns " . count($areas) . " areas (expected 14)");
            }

            // Test get_working_area_by_id('1') contains 'Sandton'
            $area1 = \WeCoza\Agents\Services\WorkingAreasService::get_working_area_by_id('1');
            if ($area1 && strpos($area1, 'Sandton') !== false) {
                $this->pass("get_working_area_by_id('1') contains 'Sandton'");
            } else {
                $this->fail("get_working_area_by_id('1') does not contain 'Sandton' (got: {$area1})");
            }

            // Test get_working_area_by_id('14') contains 'East London'
            $area14 = \WeCoza\Agents\Services\WorkingAreasService::get_working_area_by_id('14');
            if ($area14 && strpos($area14, 'East London') !== false) {
                $this->pass("get_working_area_by_id('14') contains 'East London'");
            } else {
                $this->fail("get_working_area_by_id('14') does not contain 'East London' (got: {$area14})");
            }

            // Test get_working_area_by_id('999') returns null
            $area999 = \WeCoza\Agents\Services\WorkingAreasService::get_working_area_by_id('999');
            if ($area999 === null) {
                $this->pass("get_working_area_by_id('999') returns null");
            } else {
                $this->fail("get_working_area_by_id('999') should return null (got: {$area999})");
            }

        } catch (\Exception $e) {
            $this->fail("WorkingAreasService test failed: " . $e->getMessage());
        }

        echo "\n";
    }

    /**
     * Test 9: Agent metadata CRUD operations (agent_meta, agent_notes, agent_absences)
     */
    private function testAgentMetadataCRUD(): void
    {
        echo "--- Agent Metadata CRUD ---\n";

        $repository = new \WeCoza\Agents\Repositories\AgentRepository();
        $testAgentId = null;
        $createdTestAgent = false;

        try {
            // Get a real agent ID for testing
            $db = wecoza_db();
            $stmt = $db->query("SELECT agent_id FROM agents WHERE status != 'deleted' LIMIT 1");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $testAgentId = (int) $row['agent_id'];
                $this->pass("Using existing agent ID {$testAgentId} for metadata tests");
            } else {
                // No agents exist, create temporary test agent
                $testAgentId = $repository->createAgent([
                    'first_name' => '__TEST__',
                    'surname' => 'Parity',
                    'email_address' => 'parity-test-' . time() . '@test.invalid',
                    'status' => 'active'
                ]);

                if ($testAgentId) {
                    $createdTestAgent = true;
                    $this->pass("Created temporary test agent ID {$testAgentId}");
                } else {
                    $this->fail("Failed to create temporary test agent");
                    return;
                }
            }

            // Test agent_meta CRUD (skip if table doesn't exist - FEAT-02 not implemented yet)
            echo "  --- Agent Meta CRUD ---\n";

            if ($db->tableExists('agent_meta')) {
                // Add meta
                $addResult = $repository->addAgentMeta($testAgentId, '_parity_test_key', 'test_value');
                if ($addResult) {
                    $this->pass("addAgentMeta() succeeded");
                } else {
                    $this->fail("addAgentMeta() failed");
                }

                // Get meta
                $getValue = $repository->getAgentMeta($testAgentId, '_parity_test_key', true);
                if ($getValue === 'test_value') {
                    $this->pass("getAgentMeta() returned 'test_value'");
                } else {
                    $this->fail("getAgentMeta() returned '{$getValue}' (expected 'test_value')");
                }

                // Update meta
                $updateResult = $repository->updateAgentMeta($testAgentId, '_parity_test_key', 'updated_value');
                if ($updateResult) {
                    $this->pass("updateAgentMeta() succeeded");
                } else {
                    $this->fail("updateAgentMeta() failed");
                }

                // Get updated meta
                $updatedValue = $repository->getAgentMeta($testAgentId, '_parity_test_key', true);
                if ($updatedValue === 'updated_value') {
                    $this->pass("getAgentMeta() returned 'updated_value' after update");
                } else {
                    $this->fail("getAgentMeta() returned '{$updatedValue}' (expected 'updated_value')");
                }

                // Delete meta
                $deleteResult = $repository->deleteAgentMeta($testAgentId, '_parity_test_key');
                if ($deleteResult) {
                    $this->pass("deleteAgentMeta() succeeded");
                } else {
                    $this->fail("deleteAgentMeta() failed");
                }

                // Get deleted meta (should be null)
                $deletedValue = $repository->getAgentMeta($testAgentId, '_parity_test_key', true);
                if ($deletedValue === null) {
                    $this->pass("getAgentMeta() returned null after deletion");
                } else {
                    $this->fail("getAgentMeta() returned '{$deletedValue}' (expected null)");
                }
            } else {
                $this->fail("Table [agent_meta] does not exist (FEAT-02 not implemented yet) - skipping meta tests");
            }

            // Test agent_notes CRUD
            echo "  --- Agent Notes CRUD ---\n";

            // Add note
            $noteId = $repository->addAgentNote($testAgentId, 'Parity test note', 'general');
            if ($noteId) {
                $this->pass("addAgentNote() succeeded (ID: {$noteId})");
            } else {
                $this->fail("addAgentNote() failed");
            }

            // Get notes
            $notes = $repository->getAgentNotes($testAgentId);
            $foundNote = false;
            foreach ($notes as $note) {
                if (strpos($note['note'], 'Parity test note') !== false) {
                    $foundNote = true;
                    break;
                }
            }
            if ($foundNote) {
                $this->pass("getAgentNotes() returned note containing 'Parity test note'");
            } else {
                $this->fail("getAgentNotes() did not return 'Parity test note'");
            }

            // Clean up test note
            $db->query(
                "DELETE FROM agent_notes WHERE agent_id = :agent_id AND note = 'Parity test note'",
                [':agent_id' => $testAgentId]
            );

            // Test agent_absences CRUD
            echo "  --- Agent Absences CRUD ---\n";

            // Add absence
            $absenceId = $repository->addAgentAbsence($testAgentId, '2099-01-01', 'Parity test absence');
            if ($absenceId) {
                $this->pass("addAgentAbsence() succeeded (ID: {$absenceId})");
            } else {
                $this->fail("addAgentAbsence() failed");
            }

            // Get absences
            $absences = $repository->getAgentAbsences($testAgentId);
            $foundAbsence = false;
            foreach ($absences as $absence) {
                if ($absence['absence_date'] === '2099-01-01' && strpos($absence['reason'], 'Parity test absence') !== false) {
                    $foundAbsence = true;
                    break;
                }
            }
            if ($foundAbsence) {
                $this->pass("getAgentAbsences() returned absence with date '2099-01-01'");
            } else {
                $this->fail("getAgentAbsences() did not return absence with date '2099-01-01'");
            }

            // Clean up test absence
            $db->query(
                "DELETE FROM agent_absences WHERE agent_id = :agent_id AND reason = 'Parity test absence'",
                [':agent_id' => $testAgentId]
            );

            // Clean up test agent if created
            if ($createdTestAgent && $testAgentId) {
                $repository->deleteAgentPermanently($testAgentId);
                $this->pass("Cleaned up temporary test agent ID {$testAgentId}");
            }

        } catch (\Exception $e) {
            $this->fail("Metadata CRUD test failed: " . $e->getMessage());

            // Clean up test agent on error
            if ($createdTestAgent && $testAgentId) {
                try {
                    $repository->deleteAgentPermanently($testAgentId);
                } catch (\Exception $cleanupError) {
                    // Ignore cleanup errors
                }
            }
        }

        echo "\n";
    }

    /**
     * Test 10: No standalone plugin dependency references in src/Agents/
     */
    private function testNoStandalonePluginDependency(): void
    {
        echo "--- No Standalone Plugin Dependency ---\n";

        $pluginPath = dirname(__FILE__, 3);
        $srcDir = $pluginPath . '/src/Agents';

        if (!is_dir($srcDir)) {
            $this->fail("src/Agents/ directory not found");
            return;
        }

        $patterns = [
            'WeCozaAgents' => 'WeCozaAgents namespace reference',
            'WECOZA_AGENTS_PLUGIN_' => 'WECOZA_AGENTS_PLUGIN_ constant reference',
            'DatabaseService' => 'DatabaseService reference',
            'wecoza_agents_log' => 'wecoza_agents_log reference',
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
                $this->pass("No {$description} in src/Agents/");
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

$runner = new AgentsParityTest();
$runner->run();
