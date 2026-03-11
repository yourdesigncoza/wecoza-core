<?php
/**
 * Exam Service Verification Script
 *
 * WordPress-bootstrapped script that exercises the entire S01 data layer:
 * ExamStep enum, ExamRepository, ExamUploadService, and ExamService.
 *
 * Run: php tests/exam/verify-exam-service.php
 * Requires: WordPress environment + learner_exam_results table deployed
 */

// Bootstrap WordPress
$wpLoadPath = __DIR__ . '/../../../../../wp-load.php';
if (!file_exists($wpLoadPath)) {
    echo "❌ Cannot find wp-load.php at {$wpLoadPath}\n";
    exit(1);
}
require_once $wpLoadPath;

// Autoload plugin classes
$pluginDir = __DIR__ . '/../../';
require_once $pluginDir . 'src/Learners/Enums/ExamStep.php';
require_once $pluginDir . 'src/Learners/Repositories/ExamRepository.php';
require_once $pluginDir . 'src/Learners/Services/ExamUploadService.php';
require_once $pluginDir . 'src/Learners/Services/ExamService.php';

use WeCoza\Learners\Enums\ExamStep;
use WeCoza\Learners\Repositories\ExamRepository;
use WeCoza\Learners\Services\ExamUploadService;
use WeCoza\Learners\Services\ExamService;

$pass = 0;
$fail = 0;

function check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        echo "  ✅ PASS: {$label}\n";
        $pass++;
    } else {
        echo "  ❌ FAIL: {$label}\n";
        $fail++;
    }
}

echo "=== Exam Service Verification ===\n\n";

// ──────────────────────────────────────────────
// 1. ExamStep Enum
// ──────────────────────────────────────────────
echo "--- ExamStep Enum ---\n";

$cases = ExamStep::cases();
check(count($cases) === 5, 'ExamStep has exactly 5 cases');

$expectedValues = ['mock_1', 'mock_2', 'mock_3', 'sba', 'final'];
$actualValues   = array_map(fn($c) => $c->value, $cases);
check($expectedValues === $actualValues, 'ExamStep values match expected order');

check(ExamStep::SBA->requiresFile() === true, 'SBA step requires file');
check(ExamStep::FINAL->requiresFile() === true, 'FINAL step requires file');
check(ExamStep::MOCK_1->requiresFile() === false, 'MOCK_1 step does not require file');

check(ExamStep::tryFromString('mock_1') === ExamStep::MOCK_1, 'tryFromString resolves mock_1');
check(ExamStep::tryFromString('invalid') === null, 'tryFromString returns null for invalid');
check(ExamStep::tryFromString(null) === null, 'tryFromString returns null for null');

// ──────────────────────────────────────────────
// 2. ExamRepository
// ──────────────────────────────────────────────
echo "\n--- ExamRepository ---\n";

$repo = new ExamRepository();
check($repo instanceof ExamRepository, 'ExamRepository instantiates');

$repoMethods = ['findByTrackingId', 'findByTrackingAndStep', 'upsert', 'getProgressForTracking'];
foreach ($repoMethods as $method) {
    check(method_exists($repo, $method), "ExamRepository has method {$method}()");
}

// ──────────────────────────────────────────────
// 3. ExamUploadService
// ──────────────────────────────────────────────
echo "\n--- ExamUploadService ---\n";

$uploadService = new ExamUploadService();
check($uploadService instanceof ExamUploadService, 'ExamUploadService instantiates');
check(method_exists($uploadService, 'upload'), 'ExamUploadService has method upload()');
check(method_exists($uploadService, 'getUploadDir'), 'ExamUploadService has method getUploadDir()');

// ──────────────────────────────────────────────
// 4. ExamService
// ──────────────────────────────────────────────
echo "\n--- ExamService ---\n";

$service = new ExamService();
check($service instanceof ExamService, 'ExamService instantiates');

$serviceMethods = ['recordExamResult', 'getExamProgress', 'isExamComplete', 'getExamResultsForTracking'];
foreach ($serviceMethods as $method) {
    check(method_exists($service, $method), "ExamService has method {$method}()");
}

// ──────────────────────────────────────────────
// 5. Validation: invalid percentage
// ──────────────────────────────────────────────
echo "\n--- Validation ---\n";

$resultOver = $service->recordExamResult(1, ExamStep::MOCK_1, 101.0);
check($resultOver['success'] === false, 'Percentage > 100 rejected');
check(!empty($resultOver['error']), 'Error message present for percentage > 100');
check(is_array($resultOver['data']), 'Data key is array even on failure');

$resultUnder = $service->recordExamResult(1, ExamStep::MOCK_1, -1.0);
check($resultUnder['success'] === false, 'Percentage < 0 rejected');
check(!empty($resultUnder['error']), 'Error message present for percentage < 0');

// Boundary values
$resultZero = $service->recordExamResult(1, ExamStep::MOCK_1, 0.0);
// 0 is valid — success depends on DB/table existence, but validation should not reject it
check($resultZero['error'] !== 'Percentage must be between 0 and 100', 'Percentage 0 passes validation');

$resultHundred = $service->recordExamResult(1, ExamStep::MOCK_1, 100.0);
check($resultHundred['error'] !== 'Percentage must be between 0 and 100', 'Percentage 100 passes validation');

// ──────────────────────────────────────────────
// 6. Return format consistency
// ──────────────────────────────────────────────
echo "\n--- Return Format ---\n";

// All recordExamResult returns must have these 3 keys
foreach ([$resultOver, $resultUnder] as $i => $result) {
    check(
        array_key_exists('success', $result) && array_key_exists('data', $result) && array_key_exists('error', $result),
        "recordExamResult return #{$i} has success, data, error keys"
    );
}

// ──────────────────────────────────────────────
// 7. DB-dependent tests (require schema deployed)
// ──────────────────────────────────────────────
echo "\n--- DB-Dependent Tests ---\n";

// Check if table exists before running DB tests
$tableExists = false;
try {
    $db = wecoza_db();
    $checkSql = "SELECT 1 FROM information_schema.tables WHERE table_name = 'learner_exam_results' LIMIT 1";
    $stmt = $db->query($checkSql);
    $tableExists = ($stmt->fetchColumn() !== false);
} catch (\Exception $e) {
    // DB not available
}

if (!$tableExists) {
    echo "  ⚠ SKIP: learner_exam_results table not deployed — skipping DB-dependent tests\n";
} else {
    // Find a real tracking_id to test against
    $trackingId = null;
    try {
        $stmt = $db->query("SELECT tracking_id FROM learner_lp_tracking ORDER BY tracking_id DESC LIMIT 1");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $trackingId = $row ? (int) $row['tracking_id'] : null;
    } catch (\Exception $e) {
        // ignore
    }

    if ($trackingId === null) {
        echo "  ⚠ SKIP: No tracking_id found in learner_lp_tracking — skipping DB write tests\n";
    } else {
        echo "  Using tracking_id={$trackingId} for tests\n";

        // Test recordExamResult with valid percentage
        $result = $service->recordExamResult($trackingId, ExamStep::MOCK_1, 75.5, null, 1);
        check($result['success'] === true, 'recordExamResult succeeds with valid percentage');
        check(!empty($result['data']['result_id']), 'recordExamResult returns result_id');
        check($result['error'] === '', 'recordExamResult error is empty on success');

        // Test upsert: record same step again with different percentage
        $result2 = $service->recordExamResult($trackingId, ExamStep::MOCK_1, 82.0, null, 1);
        check($result2['success'] === true, 'Upsert (second record of same step) succeeds');
        check(
            $result2['data']['result_id'] === $result['data']['result_id'],
            'Upsert returns same result_id (no duplicate)'
        );

        // Test getExamProgress
        $progress = $service->getExamProgress($trackingId);
        check(isset($progress['steps']), 'getExamProgress returns steps key');
        check(count($progress['steps']) === 5, 'getExamProgress returns all 5 steps');
        check(isset($progress['completion_percentage']), 'getExamProgress returns completion_percentage');
        check(isset($progress['completed_count']), 'getExamProgress returns completed_count');
        check(isset($progress['total_steps']), 'getExamProgress returns total_steps');
        check($progress['total_steps'] === 5, 'total_steps is 5');

        // The mock_1 step should be recorded
        check($progress['steps']['mock_1'] !== null, 'mock_1 step has data after recording');
        check((float) $progress['steps']['mock_1']['percentage'] === 82.0, 'mock_1 percentage is updated value (82.0)');

        // Test isExamComplete — should be false (only 1 of 5 steps)
        $isComplete = $service->isExamComplete($trackingId);
        check($isComplete === false, 'isExamComplete returns false when not all steps recorded');

        // Test getExamResultsForTracking
        $results = $service->getExamResultsForTracking($trackingId);
        check(is_array($results), 'getExamResultsForTracking returns array');
        check(count($results) >= 1, 'getExamResultsForTracking returns at least 1 result');

        // Clean up: delete the test data we inserted
        try {
            $db->query(
                "DELETE FROM learner_exam_results WHERE tracking_id = :tid AND exam_step = 'mock_1'",
                ['tid' => $trackingId]
            );
            echo "  🧹 Cleaned up test data\n";
        } catch (\Exception $e) {
            echo "  ⚠ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Summary
echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
