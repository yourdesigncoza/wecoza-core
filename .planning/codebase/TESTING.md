# Testing Patterns

**Analysis Date:** 2026-02-02

## Test Framework

**Runner:**
- Custom test runner: `tests/security-test.php`
- Execution method: `php tests/security-test.php` from CLI or `wp eval-file tests/security-test.php` via WP-CLI
- No PHPUnit, Jest, or Vitest configured

**Run Commands:**
```bash
# Run all security tests
php tests/security-test.php

# Run via WordPress CLI
wp eval-file tests/security-test.php
```

**Assertion Library:**
- Custom assertions in `SecurityTestRunner` class: `$this->pass()`, `$this->fail()` methods
- No external assertion library (e.g., PHPUnit, Jest)

## Test File Organization

**Location:**
- Tests stored in `tests/` directory at plugin root
- Separate from production code in `core/`, `src/`
- One test file currently: `tests/security-test.php`

**Naming:**
- Pattern: `[test-name]-test.php` (e.g., `security-test.php`)
- Not co-located with source files

**Structure:**
```
tests/
├── security-test.php   # Security regression tests
```

## Test Structure

**Suite Organization:**

Test runner class in `tests/security-test.php`:
```php
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
}

// Instantiate and run
$runner = new SecurityTestRunner();
$runner->run();
```

**Setup/Teardown:**
- WordPress environment loaded via `wp-load.php` (lines 24-31 in `security-test.php`)
- CLI-only execution guard: `php_sapi_name() !== 'cli'` check
- No test setup/teardown hooks (stateless tests)

**Assertion Patterns:**

Success assertion:
```php
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
```

Failure assertion:
```php
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
```

## Test Types

**Security Tests (All tests in current suite):**
- **Scope:** Verify security controls are in place
- **Approach:** Use try-catch to test malicious inputs, reflection to access protected methods
- **Location:** `tests/security-test.php`

**Test 1: SQL Injection in ORDER BY (`testSqlInjectionInOrderBy`)**
- Verifies: `LearnerRepository.findAll()` rejects malicious ORDER BY column names
- Input: `'id; DROP TABLE learners; --'`
- Assertion: Malicious input is sanitized via `validateOrderColumn()` whitelist
- Pattern:
```php
$maliciousOrderBy = 'id; DROP TABLE learners; --';
$result = $repo->findAll(10, 0, $maliciousOrderBy, 'DESC');
// If query executes safely, malicious input was filtered
$this->pass($testName, 'Malicious ORDER BY was sanitized');
```

**Test 2: SQL Injection in Filter Columns (`testSqlInjectionInFilterColumns`)**
- Verifies: `LearnerRepository.findBy()` rejects malicious column names in WHERE clause
- Input: `['id' => 1, 'id; DROP TABLE learners; --' => 'malicious']`
- Assertion: Non-whitelisted column keys are filtered out
- Pattern: Same as Test 1 - if query executes, malicious key was removed

**Test 3: SQL Injection in INSERT Columns (`testSqlInjectionInInsertColumns`)**
- Verifies: `BaseRepository.insert()` filters non-whitelisted columns
- Approach: Use Reflection to access protected `getAllowedInsertColumns()` method
- Input: `['first_name' => 'Test', 'malicious_col; DROP TABLE--' => 'injected']`
- Assertion: Array filter removes malicious key before database operation
- Pattern:
```php
$reflection = new \ReflectionMethod($repo, 'getAllowedInsertColumns');
$reflection->setAccessible(true);
$allowedColumns = $reflection->invoke($repo);

$filteredData = array_filter($maliciousData,
    fn($key) => in_array($key, $allowedColumns, true),
    ARRAY_FILTER_USE_KEY
);

if (!isset($filteredData['malicious_col; DROP TABLE--'])) {
    $this->pass($testName, 'Malicious INSERT column was filtered out');
}
```

**Test 4: SQL Injection in UPDATE Columns (`testSqlInjectionInUpdateColumns`)**
- Verifies: UPDATE whitelist contains only safe column names
- Approach: Check `getAllowedUpdateColumns()` for dangerous patterns
- Dangerous patterns checked: `;`, `--`, `DROP`, `DELETE`, `INSERT`, `UPDATE`
- Assertion:
```php
$dangerousPatterns = [';', '--', 'DROP', 'DELETE', 'INSERT', 'UPDATE'];
foreach ($allowedColumns as $col) {
    foreach ($dangerousPatterns as $pattern) {
        if (stripos($col, $pattern) !== false) {
            $this->fail($testName, 'Dangerous pattern found');
        }
    }
}
$this->pass($testName, 'Update column whitelist contains only safe column names');
```

**Test 5: Custom Capability Registration (`testCapabilityRegistration`)**
- Verifies: `manage_learners` capability is registered for administrators
- Pattern:
```php
$admin = get_role('administrator');
if ($admin && $admin->has_cap('manage_learners')) {
    $this->pass($testName, 'Administrator role has manage_learners capability');
} else {
    $this->fail($testName, 'Administrator role MISSING manage_learners capability');
}
```

**Test 6: No-priv AJAX Hooks Removed (`testNoPrivHooksRemoved`)**
- Verifies: Unauthenticated AJAX handlers (`wp_ajax_nopriv_`) are not registered
- Checked hooks:
  - `wp_ajax_nopriv_wecoza_get_learner`
  - `wp_ajax_nopriv_wecoza_get_learners`
  - `wp_ajax_nopriv_wecoza_update_learner`
  - `wp_ajax_nopriv_wecoza_delete_learner`
- Pattern:
```php
global $wp_filter;
foreach ($noprivHooks as $hook) {
    if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
        $foundHooks[] = $hook;
    }
}
if (empty($foundHooks)) {
    $this->pass($testName, 'All nopriv AJAX hooks have been removed');
}
```

## Coverage

**Requirements:** No explicit code coverage tool (no phpunit.xml, no jest config)

**Current Coverage:**
- Security controls: 6 test cases covering SQL injection, authentication, authorization
- Core Repository patterns tested indirectly through security tests
- No unit tests for Models, Controllers, Services

**Gap Areas:**
- No AJAX handler testing (integration level)
- No Model save/update/delete testing
- No form validation testing
- No service layer testing (e.g., `ProgressionService`)
- No JavaScript/frontend testing (no Jest, Vitest, or Cypress configured)

## Mocking

**Framework:** Not used

**Approach:** Tests use real database connection and repositories
- Example: `new LearnerRepository()` queries actual PostgreSQL database
- Exception handling used instead of mocks: `try-catch` verifies error handling

**Pattern to Add Mocks (if needed):**
Since no mocking framework exists, future tests should either:
1. Use test database with fixtures
2. Use Reflection to test private methods (as done in Tests 3-4)
3. Mock external dependencies manually (WordPress functions are real in tests)

## Fixtures and Test Data

**Test Data:**
- No fixtures or factories defined currently
- Security tests use hardcoded malicious input strings:
  - `'id; DROP TABLE learners; --'`
  - `'malicious_col; DROP TABLE--'`
- No factory pattern for creating test records

**Location:** None (tests are stateless and don't require fixtures)

**Pattern for Fixtures (if needed):**
```php
// Example fixture factory (not implemented)
class LearnerFactory {
    public static function create(array $overrides = []): int {
        $repo = new LearnerRepository();
        $data = array_merge([
            'first_name' => 'Test',
            'surname' => 'User',
            'email_address' => 'test@example.com',
        ], $overrides);
        return $repo->insert($data);
    }
}
```

## Common Patterns

**CLI Test Guard:**
```php
// Prevent web access - CLI only
if (php_sapi_name() !== 'cli' && !defined('WP_CLI')) {
    die('This script can only be run from command line.');
}
```

**WordPress Environment Loading:**
```php
// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    $wp_load = dirname(__FILE__, 6) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die("Could not find wp-load.php.\n");
    }
}
```

**Test Method Pattern:**
```php
private function testFeature(): void
{
    $testName = 'Feature Name';

    try {
        // Perform test action
        $result = /* ... */;

        // Assert result
        if ($result === expected) {
            $this->pass($testName, 'Reason for success');
        } else {
            $this->fail($testName, 'Reason for failure');
        }
    } catch (\Exception $e) {
        $this->fail($testName, 'Unexpected exception: ' . $e->getMessage());
    }
}
```

**Using Reflection to Test Protected Methods:**
```php
$reflection = new \ReflectionMethod($object, 'protectedMethodName');
$reflection->setAccessible(true);
$result = $reflection->invoke($object, ...args);
```

**Print Results:**
```php
private function printResults(): void
{
    echo "=================================\n";
    echo "Results: {$this->passed} passed, {$this->failed} failed\n";
    echo "=================================\n";

    if ($this->failed > 0) {
        exit(1);  // Exit with error code
    }
}
```

## Test Execution Requirements

**Dependencies:**
- PHP 8.0+ (match expressions, typed properties)
- WordPress 6.0+
- PostgreSQL with `pdo_pgsql` PHP extension
- `pdo` and `pdo_pgsql` PHP extensions must be installed

**Database:**
- Tests connect to real PostgreSQL database (from `wecoza_postgres_password` WP option)
- Must have tables: `learners`, `classes`, `qa_visits`, etc. (from schema)
- Tests don't modify data (read-only security validation)

**Run Context:**
- Must be run from WordPress environment (requires `wp-load.php`)
- Must be CLI execution (not web server)
- Requires WordPress to be fully initialized

## JavaScript Testing

**Current Status:** Not implemented

**Recommendation for Future Testing:**
- Framework: Jest or Vitest (lightweight)
- Target: `assets/js/` files
- Testing patterns for `WeCozaAjax`:
  - Mock jQuery/AJAX
  - Mock WordPress localization (`wecozaClass`, `qaAjax`, etc.)
  - Test request/response handling
  - Test loading indicator UI
  - Test error handling and retries

---

*Testing analysis: 2026-02-02*
