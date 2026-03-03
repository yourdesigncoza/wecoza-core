# Testing Patterns

**Analysis Date:** 2026-03-03

## Test Framework

**Runner:**
- WordPress CLI (`wp eval-file`) for PHP integration tests
- No formal test framework (PHPUnit, Pest, etc.)
- Tests written as standalone PHP scripts that bootstrap WordPress

**Assertion Library:**
- Custom `test_result()` function for tracking pass/fail
- No formal assertion library (manual boolean checks)

**Run Commands:**
```bash
# Email Notification Tests
wp eval-file tests/Events/EmailNotificationTest.php --path=/opt/lampp/htdocs/wecoza

# AI Summarization Tests
php wp-cli.phar eval-file wp-content/plugins/wecoza-core/tests/Events/AISummarizationTest.php --path=/opt/lampp/htdocs/wecoza

# PII Detection Tests
php tests/Events/PIIDetectorTest.php

# Architecture Verification
php tests/verify-architecture.php

# Security Tests
php tests/security-test.php

# Feature Parity Tests
php tests/integration/agents-feature-parity.php
php tests/integration/clients-feature-parity.php
```

## Test File Organization

**Location:**
- `tests/` directory at plugin root
- Subdirectories by domain: `tests/Events/`, `tests/integration/`, etc.
- Tests alongside source code (not co-located with implementations)

**Naming:**
- Pattern: `{FeatureName}Test.php`
- Examples: `EmailNotificationTest.php`, `PIIDetectorTest.php`, `AISummarizationTest.php`

**Structure:**
```
tests/
├── Events/
│   ├── EmailNotificationTest.php
│   ├── AISummarizationTest.php
│   ├── PIIDetectorTest.php
│   ├── MaterialTrackingTest.php
│   └── TaskManagementTest.php
├── integration/
│   ├── agents-feature-parity.php
│   ├── clients-feature-parity.php
│   └── ...
├── verify-architecture.php
├── security-test.php
└── ...
```

## Test Structure

**Suite Organization:**
Tests organized into logical sections within a single test file via comment headers and echo output:

```php
// ============================================================================
// SECTION NAME
// ============================================================================

echo "--- Section Title ---\n\n";

// Individual test calls
test_result('Test name 1', $condition1, $error_message);
test_result('Test name 2', $condition2, '');
```

**Patterns:**

1. **Global State Tracking:**
```php
$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];
```

2. **Test Runner Function:**
```php
function test_result(string $name, bool $passed, string $message = ''): void {
    global $results;

    $results['total']++;
    if ($passed) {
        $results['passed']++;
        echo "✓ PASS: {$name}\n";
    } else {
        $results['failed']++;
        echo "✗ FAIL: {$name}\n";
        if ($message) {
            echo "  Error: {$message}\n";
        }
    }

    $results['tests'][] = [
        'name' => $name,
        'passed' => $passed,
        'message' => $message
    ];
}
```

3. **Bootstrap Section:**
```php
// Bootstrap WordPress if not running via WP-CLI
if (!function_exists('get_option')) {
    require_once '/opt/lampp/htdocs/wecoza/wp-load.php';
}
```

4. **Setup Before Tests:**
- Configure cron: `wp_schedule_event(time(), 'hourly', 'wecoza_email_notifications_process')`
- Register settings: `\WeCoza\Events\Admin\SettingsPage::registerSettings()`
- Load test data via WordPress options: `update_option('wecoza_notification_class_created', 'test@example.com')`

5. **Cleanup After Tests:**
- Remove test options: `delete_option('wecoza_notification_class_created')`
- Reset state to avoid cross-test contamination

6. **Final Summary:**
```php
echo "====================================\n";
echo "TEST SUMMARY\n";
echo "====================================\n\n";

echo "Total tests: {$results['total']}\n";
echo "Passed: {$results['passed']}\n";
echo "Failed: {$results['failed']}\n";

$pass_rate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 2) : 0;
echo "Pass rate: {$pass_rate}%\n\n";

if ($results['failed'] > 0) {
    echo "FAILED TESTS:\n";
    foreach ($results['tests'] as $test) {
        if (!$test['passed']) {
            echo "  - {$test['name']}\n";
            if ($test['message']) {
                echo "    {$test['message']}\n";
            }
        }
    }
}

// Exit with code
exit($pass_rate === 100.0 ? 0 : 1);
```

## Mocking

**Framework:** No mocking framework used (Mockery, PHPUnit mocks, etc.)

**Patterns:**

1. **Dependency Injection for Testing:**
Services accept test dependencies via constructor:
```php
// Production
$service = new AISummaryService($config);

// Testing: Pass mock HTTP client
$service = new AISummaryService(
    $config,
    $mockHttpClient,  // Callable that returns test responses
    $maxAttempts
);
```

2. **Fake Implementations:**
Test classes implement required interfaces inline:
```php
class AITestRunner
{
    private $total = 0;
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function test(string $name, bool $passed, string $message = ''): void
    {
        // Custom test runner implementation
    }
}
```

3. **WordPress Helpers as Boundaries:**
Tests directly call WordPress functions (get_option, update_option, wp_schedule_event) which are assumed to work:
```php
update_option('wecoza_notification_class_created', 'test-insert@example.com');
$recipient = $settings->getRecipientForOperation('INSERT');
$correct_insert = $recipient === 'test-insert@example.com';
test_result('Settings option works', $correct_insert);
delete_option('wecoza_notification_class_created');
```

4. **Reflection for Private Method Testing:**
From `PIIDetectorTest.php`, test class exposes private methods:
```php
class PIIDetectorTestClass
{
    use \WeCoza\Events\Services\Traits\PIIDetector;

    // Expose private methods for testing
    public function testLooksLikeSouthAfricanID(string $value): bool
    {
        return $this->looksLikeSouthAfricanID($value);
    }
}
```

**What to Mock:**
- External APIs: HTTP calls (OpenAI, Stripe, etc.) - pass test callable to constructor
- WordPress functions: Do NOT mock; assume WordPress is loaded and works

**What NOT to Mock:**
- Database operations (PostgreSQL): Test against real database
- WordPress core functions (get_option, add_action, etc.): Assume these work correctly
- Service dependencies: Pass real implementations unless specifically testing error cases

## Fixtures and Factories

**Test Data:**
No formal fixture system. Data created inline using WordPress functions:

```php
// Create test option
update_option('wecoza_notification_class_created', 'test@example.com');

// Create test array (from EmailNotificationTest.php)
$test_context = [
    'operation' => 'INSERT',
    'row' => ['class_id' => '123', 'changed_at' => '2026-02-02T13:00:00Z'],
    'recipient' => 'test@example.com',
    'new_row' => ['class_code' => 'TEST-001', 'class_subject' => 'Test Subject'],
    'old_row' => [],
    'diff' => [],
    'summary' => ['status' => 'success', 'summary' => 'Test summary text'],
    'email_context' => ['alias_map' => [], 'obfuscated' => []],
];
```

**Location:**
- Inline within test files (no separate fixtures directory)
- Data pools for form fillers in development code: `src/Dev/FormFiller/data-pools.js`

## Coverage

**Requirements:** No coverage requirements enforced

**View Coverage:**
- No coverage reporting tool configured
- Manual inspection: Check test results pass/fail rate from final summary

## Test Types

**Unit Tests:**
- Scope: Individual class methods and functions
- Approach: Create instance, call method, assert return value
- Example from `PIIDetectorTest.php`:
  ```php
  $detector = new PIIDetectorTestClass();

  test_result(
      'SA ID: 13 digits detected',
      $detector->testLooksLikeSouthAfricanID('9001015800087')
  );
  ```

**Integration Tests:**
- Scope: Multiple classes working together with real WordPress/PostgreSQL
- Approach: Set up data, call service/controller methods, verify state changes and side effects
- Examples:
  - `tests/integration/agents-feature-parity.php`: Verify agents plugin functions work after migration
  - `tests/integration/clients-feature-parity.php`: Verify clients plugin functions work after migration
- Database: Tests use real PostgreSQL database (not mocked)

**Architecture Verification Tests:**
- Scope: Verify architectural requirements are met
- Approach: Static code analysis - check file existence, namespace declarations, method presence
- File: `tests/verify-architecture.php`
- Checks: Correct namespaces, required methods, file organization, inheritance chains

**Security Tests:**
- File: `tests/security-test.php`
- Scope: Verify CSRF protection, capability checks, input sanitization
- Approach: Check WordPress hooks, nonce registration, capability enforcement
- Example tests:
  - SQL injection in ORDER BY, WHERE, INSERT, UPDATE columns
  - Capability registration (manage_learners)
  - No unauthenticated AJAX handlers (`wp_ajax_nopriv_` hooks removed)

**Feature Parity Tests:**
- Scope: Verify migrated plugins work identically after integration into core
- Approach: Compare output/behavior before/after migration
- Files: `tests/integration/agents-feature-parity.php`, `tests/integration/clients-feature-parity.php`

## Common Patterns

**Async Testing:**
Not needed - WordPress is synchronous. Some tests use `wp_schedule_event()` to verify cron scheduling but don't wait for execution.

**Error Testing:**
Tests verify error conditions by checking exception messages and return values:

```php
// Test exception is thrown
try {
    $service->processInvalid();
    $error_occurred = false;
} catch (Exception $e) {
    $error_occurred = true;
    $correct_message = strpos($e->getMessage(), 'Expected error') !== false;
}
test_result('Service throws correct exception', $error_occurred && $correct_message);
```

**Conditional Testing:**
Tests skip sections if prerequisites aren't met:

```php
// From EmailNotificationTest.php - test only if admin functions available
if (function_exists('add_settings_section')) {
    $insert_field_registered = isset($wp_settings_fields[...]);
    test_result('Settings field registered', $insert_field_registered);
} else {
    // Test alternative method instead
    $has_register_method = method_exists('WeCoza\\Events\\Admin\\SettingsPage', 'registerSettings');
    test_result('SettingsPage has registerSettings() method', $has_register_method);
}
```

**WordPress Option Testing:**
Set options, test against them, then clean up:

```php
// Set
update_option('wecoza_notification_class_created', 'admin@example.com');

// Test
$retrieved = get_option('wecoza_notification_class_created');
$correct = $retrieved === 'admin@example.com';
test_result('Option is settable and retrievable', $correct);

// Clean
delete_option('wecoza_notification_class_created');
```

**Class Instantiation Testing:**
Verify classes can be instantiated and have expected methods:

```php
$processor_exists = class_exists('WeCoza\\Events\\Services\\NotificationProcessor');
test_result('NotificationProcessor class exists', $processor_exists);

if ($processor_exists) {
    $has_boot = method_exists('WeCoza\\Events\\Services\\NotificationProcessor', 'boot');
    test_result('NotificationProcessor has boot() static method', $has_boot);

    if ($has_boot) {
        try {
            $processor = \WeCoza\Events\Services\NotificationProcessor::boot();
            $returns_instance = $processor instanceof \WeCoza\Events\Services\NotificationProcessor;
            test_result('boot() returns valid instance', $returns_instance);
        } catch (Exception $e) {
            test_result('boot() execution', false, $e->getMessage());
        }
    }
}
```

**PII Detection Testing (from PIIDetectorTest.php):**
Tests validate pattern matching for sensitive data:
```php
// Valid SA IDs (13 digits)
test_result('SA ID: 13 digits detected', $detector->testLooksLikeSouthAfricanID('9001015800087'));
test_result('SA ID: 13 digits with spaces detected', $detector->testLooksLikeSouthAfricanID('900101 5800 087'));

// Invalid SA IDs
test_result('SA ID: 12 digits not detected', !$detector->testLooksLikeSouthAfricanID('123456789012'));
```

## Test Execution Flow

1. **Bootstrap phase:** Load WordPress via `wp-load.php`
2. **Setup phase:** Register hooks, create test data, schedule cron events
3. **Test execution phase:** Run test_result() calls to verify conditions
4. **Cleanup phase:** Remove test options, reset state
5. **Summary phase:** Print pass rate and failed tests
6. **Exit code:** 0 on all pass, 1 on any failure

Example from `EmailNotificationTest.php`:
```php
if ($pass_rate === 100.0) {
    echo "✓ ALL REQUIREMENTS VERIFIED\n";
    exit(0);
} else {
    echo "✗ SOME REQUIREMENTS NOT VERIFIED\n";
    echo "Please review failed tests above and fix any issues.\n";
    exit(1);
}
```

## Debug Workflow

**Primary:**
- Check `/opt/lampp/htdocs/wecoza/wp-content/debug.log` for PHP errors and `wecoza_log()` output
- All test output goes to stdout (run test file directly or via `wp eval-file`)

**Test-Specific Logging:**
- Tests echo progress to console: `✓ PASS:` and `✗ FAIL:` markers
- Use `var_export()` or `wp_json_encode()` in test assertions to debug values:
  ```php
  $has_body = isset($result['body']) && is_string($result['body']);
  $valid_structure = $has_subject && $has_body && $has_headers;
  test_result(
      'Structure is valid',
      $valid_structure,
      $valid_structure ? '' : 'Missing required keys in returned array'  // Show error message
  );
  ```

---

*Testing analysis: 2026-03-03*
