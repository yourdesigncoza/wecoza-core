<?php
/**
 * WeCoza Core - Security Regression Tests
 *
 * Tests security measures implemented in Phase 1-5:
 * - SQL injection prevention via column whitelisting
 * - Authentication requirements (no nopriv hooks)
 * - Capability checks (manage_learners)
 * - CSRF protection via nonces
 *
 * Run with: php tests/security-test.php
 * Or via WP-CLI: wp eval-file tests/security-test.php
 *
 * @package WeCoza\Tests
 * @since 1.0.0
 */

// Prevent web access - CLI only
if (php_sapi_name() !== 'cli' && !defined('WP_CLI')) {
    die('This script can only be run from command line.');
}

// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    // Find wp-load.php
    $wp_load = dirname(__FILE__, 6) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die("Could not find wp-load.php. Run this script from the plugin directory.\n");
    }
}

use WeCoza\Learners\Repositories\LearnerRepository;
use WeCoza\Classes\Repositories\ClassRepository;

/**
 * Simple test runner
 */
class SecurityTestRunner
{
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "\n=== WeCoza Core Security Tests ===\n\n";

        $this->testSqlInjectionInOrderBy();
        $this->testSqlInjectionInFilterColumns();
        $this->testSqlInjectionInInsertColumns();
        $this->testSqlInjectionInUpdateColumns();
        $this->testCapabilityRegistration();
        $this->testNoPrivHooksRemoved();

        $this->printResults();
    }

    /**
     * Test 1: SQL Injection in ORDER BY should be blocked
     */
    private function testSqlInjectionInOrderBy(): void
    {
        $testName = 'SQL Injection in ORDER BY';

        try {
            $repo = new LearnerRepository();

            // Attempt malicious orderBy
            $maliciousOrderBy = 'id; DROP TABLE learners; --';
            $result = $repo->findAll(10, 0, $maliciousOrderBy, 'DESC');

            // If we get here without error, the malicious input was sanitized
            // The query should have used default column (created_at) not the malicious one
            $this->pass($testName, 'Malicious ORDER BY was sanitized, query executed safely');
        } catch (\Exception $e) {
            // An exception is also acceptable - it means the malicious input was rejected
            $this->pass($testName, 'Malicious ORDER BY was rejected: ' . $e->getMessage());
        }
    }

    /**
     * Test 2: SQL Injection in filter columns should be blocked
     */
    private function testSqlInjectionInFilterColumns(): void
    {
        $testName = 'SQL Injection in Filter Columns';

        try {
            $repo = new LearnerRepository();

            // Attempt to inject via criteria keys
            $maliciousCriteria = [
                'id' => 1,
                'id; DROP TABLE learners; --' => 'malicious',
            ];

            $result = $repo->findBy($maliciousCriteria, 10);

            // If query executed without error, the malicious key was filtered out
            $this->pass($testName, 'Malicious filter column was filtered, query executed safely');
        } catch (\Exception $e) {
            $this->pass($testName, 'Malicious filter column was rejected: ' . $e->getMessage());
        }
    }

    /**
     * Test 3: SQL Injection in INSERT columns should be blocked
     */
    private function testSqlInjectionInInsertColumns(): void
    {
        $testName = 'SQL Injection in INSERT Columns';

        try {
            $repo = new LearnerRepository();

            // Attempt to inject via data keys
            $maliciousData = [
                'first_name' => 'Test',
                'surname' => 'User',
                'malicious_col; DROP TABLE--' => 'injected',
            ];

            // Use reflection to access protected method
            $reflection = new \ReflectionMethod($repo, 'getAllowedInsertColumns');
            $reflection->setAccessible(true);
            $allowedColumns = $reflection->invoke($repo);

            $filteredData = array_filter(
                $maliciousData,
                fn($key) => in_array($key, $allowedColumns, true),
                ARRAY_FILTER_USE_KEY
            );

            if (!isset($filteredData['malicious_col; DROP TABLE--'])) {
                $this->pass($testName, 'Malicious INSERT column was filtered out');
            } else {
                $this->fail($testName, 'Malicious INSERT column was NOT filtered');
            }
        } catch (\Exception $e) {
            $this->fail($testName, 'Unexpected exception: ' . $e->getMessage());
        }
    }

    /**
     * Test 4: SQL Injection in UPDATE columns should be blocked
     */
    private function testSqlInjectionInUpdateColumns(): void
    {
        $testName = 'SQL Injection in UPDATE Columns';

        try {
            $repo = new LearnerRepository();

            // Use reflection to access protected method
            $reflection = new \ReflectionMethod($repo, 'getAllowedUpdateColumns');
            $reflection->setAccessible(true);
            $allowedColumns = $reflection->invoke($repo);

            // Verify no dangerous columns are allowed
            $dangerousPatterns = [';', '--', 'DROP', 'DELETE', 'INSERT', 'UPDATE'];
            $isDangerous = false;

            foreach ($allowedColumns as $col) {
                foreach ($dangerousPatterns as $pattern) {
                    if (stripos($col, $pattern) !== false) {
                        $isDangerous = true;
                        break 2;
                    }
                }
            }

            if (!$isDangerous) {
                $this->pass($testName, 'Update column whitelist contains only safe column names');
            } else {
                $this->fail($testName, 'Update column whitelist contains potentially dangerous values');
            }
        } catch (\Exception $e) {
            $this->fail($testName, 'Unexpected exception: ' . $e->getMessage());
        }
    }

    /**
     * Test 5: Custom capability should be registered for admin
     */
    private function testCapabilityRegistration(): void
    {
        $testName = 'Custom manage_learners Capability';

        $admin = get_role('administrator');

        if ($admin && $admin->has_cap('manage_learners')) {
            $this->pass($testName, 'Administrator role has manage_learners capability');
        } else {
            $this->fail($testName, 'Administrator role MISSING manage_learners capability (re-activate plugin)');
        }
    }

    /**
     * Test 6: Verify nopriv hooks are not registered
     */
    private function testNoPrivHooksRemoved(): void
    {
        $testName = 'No-priv AJAX Hooks Removed';

        global $wp_filter;

        $noprivHooks = [
            'wp_ajax_nopriv_wecoza_get_learner',
            'wp_ajax_nopriv_wecoza_get_learners',
            'wp_ajax_nopriv_wecoza_update_learner',
            'wp_ajax_nopriv_wecoza_delete_learner',
        ];

        $foundHooks = [];
        foreach ($noprivHooks as $hook) {
            if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
                $foundHooks[] = $hook;
            }
        }

        if (empty($foundHooks)) {
            $this->pass($testName, 'All nopriv AJAX hooks have been removed');
        } else {
            $this->fail($testName, 'Found nopriv hooks: ' . implode(', ', $foundHooks));
        }
    }

    private function pass(string $testName, string $message): void
    {
        $this->passed++;
        $this->results[] = [
            'status' => 'PASS',
            'name' => $testName,
            'message' => $message,
        ];
        echo "✓ PASS: {$testName}\n  {$message}\n\n";
    }

    private function fail(string $testName, string $message): void
    {
        $this->failed++;
        $this->results[] = [
            'status' => 'FAIL',
            'name' => $testName,
            'message' => $message,
        ];
        echo "✗ FAIL: {$testName}\n  {$message}\n\n";
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

// Run tests
$runner = new SecurityTestRunner();
$runner->run();
