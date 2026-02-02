<?php
/**
 * PIIDetector Pattern Detection Tests
 *
 * Verifies heuristic PII detection patterns work correctly
 * Run with: php tests/Events/PIIDetectorTest.php
 */

declare(strict_types=1);

// Include the trait for testing
require_once dirname(__DIR__, 2) . '/src/Events/Services/Traits/PIIDetector.php';

/**
 * Test class that uses PIIDetector trait for testing.
 */
class PIIDetectorTestClass
{
    use \WeCoza\Events\Services\Traits\PIIDetector;

    // Expose private methods for testing
    public function testLooksLikeSouthAfricanID(string $value): bool
    {
        return $this->looksLikeSouthAfricanID($value);
    }

    public function testLooksLikePhoneNumber(string $value): bool
    {
        return $this->looksLikePhoneNumber($value);
    }

    public function testDetectPIIPattern(string $value): ?string
    {
        return $this->detectPIIPattern($value);
    }

    public function testMaskSouthAfricanID(string $value): string
    {
        return $this->maskSouthAfricanID($value);
    }

    public function testMaskPassport(string $value): string
    {
        return $this->maskPassport($value);
    }
}

// Test result tracking
$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];

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

echo "\n";
echo "====================================\n";
echo "PIIDETECTOR PATTERN DETECTION TESTS\n";
echo "====================================\n\n";

$detector = new PIIDetectorTestClass();

// Section 1: South African ID Detection
echo "--- South African ID Detection ---\n";

// Valid SA IDs (13 digits)
test_result('SA ID: 13 digits detected', $detector->testLooksLikeSouthAfricanID('9001015800087'));
test_result('SA ID: 13 digits with spaces detected', $detector->testLooksLikeSouthAfricanID('900101 5800 087'));
test_result('SA ID: 13 digits with dashes detected', $detector->testLooksLikeSouthAfricanID('900101-5800-087'));

// Invalid SA IDs
test_result('SA ID: 12 digits not detected', !$detector->testLooksLikeSouthAfricanID('123456789012'));
test_result('SA ID: 14 digits not detected', !$detector->testLooksLikeSouthAfricanID('12345678901234'));
test_result('SA ID: letters not detected', !$detector->testLooksLikeSouthAfricanID('900101ABC0087'));

echo "\n";

// Section 2: Phone Number Detection
echo "--- Phone Number Detection ---\n";

// Valid phone numbers (7-15 digits)
test_result('Phone: 10 digits detected', $detector->testLooksLikePhoneNumber('0821234567'));
test_result('Phone: with spaces detected', $detector->testLooksLikePhoneNumber('082 123 4567'));
test_result('Phone: international format detected', $detector->testLooksLikePhoneNumber('+27 82 123 4567'));
test_result('Phone: 7 digits detected', $detector->testLooksLikePhoneNumber('1234567'));

// Invalid phone numbers
test_result('Phone: 6 digits not detected', !$detector->testLooksLikePhoneNumber('123456'));
test_result('Phone: 16+ digits not detected', !$detector->testLooksLikePhoneNumber('1234567890123456'));

echo "\n";

// Section 3: Pattern Detection Priority
echo "--- PII Pattern Detection ---\n";

// SA ID takes priority over phone (more specific)
test_result('Pattern: SA ID detected (not phone)', $detector->testDetectPIIPattern('9001015800087') === 'sa_id');

// Phone detection
test_result('Pattern: Phone detected', $detector->testDetectPIIPattern('0821234567') === 'phone');

// Short values ignored
test_result('Pattern: Short value returns null', $detector->testDetectPIIPattern('12345') === null);

echo "\n";

// Section 4: Masking
echo "--- PII Masking ---\n";

test_result('Mask SA ID shows last 2 digits', $detector->testMaskSouthAfricanID('9001015800087') === 'ID-XXXXXXXXXXX87');
test_result('Mask passport shows last 2 chars', $detector->testMaskPassport('AB123456') === 'PASSPORT-XXXXXX56');

// Summary
echo "\n====================================\n";
echo "TEST SUMMARY\n";
echo "====================================\n";
echo "Total:  {$results['total']}\n";
echo "Passed: {$results['passed']}\n";
echo "Failed: {$results['failed']}\n";

if ($results['failed'] > 0) {
    echo "\n⚠️  Some tests failed!\n";
    exit(1);
} else {
    echo "\n✓ All tests passed!\n";
    exit(0);
}
