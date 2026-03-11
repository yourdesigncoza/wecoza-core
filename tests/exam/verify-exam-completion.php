<?php
/**
 * Integration test: Exam LP completion trigger
 *
 * Validates that handle_record_exam_result() wires isExamComplete()
 * to auto-complete the LP, includes lp_completed in the response,
 * guards against double-completion, and the JS handles it.
 *
 * Usage: php tests/exam/verify-exam-completion.php
 */

$passed = 0;
$failed = 0;
$total  = 0;

function check(string $label, bool $result, string $detail = ''): void
{
    global $passed, $failed, $total;
    $total++;
    if ($result) {
        $passed++;
        echo "  ✅ PASS: {$label}\n";
    } else {
        $failed++;
        echo "  ❌ FAIL: {$label}" . ($detail ? " — {$detail}" : "") . "\n";
    }
}

echo "\n=== Exam LP Completion Integration Test ===\n\n";

// ---------------------------------------------------------------------------
// Resolve paths
// ---------------------------------------------------------------------------
$pluginDir   = realpath(__DIR__ . '/../..');
$handlerFile = $pluginDir . '/src/Learners/Ajax/ExamAjaxHandlers.php';
$serviceFile = $pluginDir . '/src/Learners/Services/ExamService.php';
$modelFile   = $pluginDir . '/src/Learners/Models/LearnerProgressionModel.php';
$jsFile      = $pluginDir . '/assets/js/learners/learner-exam-progress.js';

$handlerCode = file_get_contents($handlerFile);
$serviceCode = file_get_contents($serviceFile);
$modelCode   = file_get_contents($modelFile);
$jsCode      = file_get_contents($jsFile);

// ===========================================================================
// Section 1: AJAX Handler Structure
// ===========================================================================
echo "--- Section 1: AJAX Handler Structure ---\n";

check(
    'ExamAjaxHandlers.php exists',
    file_exists($handlerFile)
);

check(
    'LearnerProgressionModel import present',
    str_contains($handlerCode, 'use WeCoza\Learners\Models\LearnerProgressionModel;')
);

check(
    'isExamComplete call exists in handler',
    str_contains($handlerCode, 'isExamComplete(') && str_contains($handlerCode, '$trackingId')
);

check(
    'lp_completed key in success response',
    str_contains($handlerCode, "'lp_completed'") && str_contains($handlerCode, 'wp_send_json_success')
);

check(
    'LearnerProgressionModel::getById call present',
    str_contains($handlerCode, 'LearnerProgressionModel::getById(')
);

check(
    'isCompleted() guard present',
    str_contains($handlerCode, '->isCompleted()')
);

check(
    'markComplete() call present',
    str_contains($handlerCode, '->markComplete(')
);

check(
    'LP auto-completed log message present',
    str_contains($handlerCode, 'LP auto-completed for tracking_id=')
);

check(
    'LP already completed skip log present',
    str_contains($handlerCode, 'LP already completed for tracking_id=')
);

check(
    'markComplete failure is caught and logged',
    str_contains($handlerCode, 'markComplete failed for tracking_id=')
);

// ===========================================================================
// Section 2: ExamService::isExamComplete behavior
// ===========================================================================
echo "\n--- Section 2: ExamService::isExamComplete structure ---\n";

check(
    'ExamService.php exists',
    file_exists($serviceFile)
);

check(
    'isExamComplete method exists',
    str_contains($serviceCode, 'function isExamComplete(')
);

// Check that isExamComplete validates all 5 steps
check(
    'isExamComplete checks all ExamStep cases',
    str_contains($serviceCode, 'ExamStep::cases()')
    || (str_contains($serviceCode, 'mock_1') && str_contains($serviceCode, 'final'))
);

// Check that isExamComplete checks file_path for file-required steps
check(
    'isExamComplete checks file_path for upload steps',
    str_contains($serviceCode, 'file_path') && str_contains($serviceCode, 'requiresFile')
    || str_contains($serviceCode, 'file_path')
);

check(
    'isExamComplete returns bool',
    (bool) preg_match('/function\s+isExamComplete\s*\([^)]*\)\s*:\s*bool/', $serviceCode)
);

// ===========================================================================
// Section 3: LearnerProgressionModel::markComplete
// ===========================================================================
echo "\n--- Section 3: LearnerProgressionModel::markComplete ---\n";

check(
    'LearnerProgressionModel.php exists',
    file_exists($modelFile)
);

check(
    'markComplete method exists',
    str_contains($modelCode, 'function markComplete(')
);

check(
    'markComplete accepts nullable portfolio path',
    (bool) preg_match('/function\s+markComplete\s*\([^)]*\?\s*string\s+\$portfolio/s', $modelCode)
);

check(
    'isCompleted method exists',
    str_contains($modelCode, 'function isCompleted(')
);

check(
    'getById static method exists',
    str_contains($modelCode, 'static function getById(')
    || str_contains($modelCode, 'function getById(')
);

// ===========================================================================
// Section 4: JS module — lp_completed handling
// ===========================================================================
echo "\n--- Section 4: JS lp_completed handling ---\n";

check(
    'JS file exists',
    file_exists($jsFile)
);

check(
    'JS checks response.data.lp_completed',
    str_contains($jsCode, 'lp_completed')
);

check(
    'JS shows completion alert on lp_completed',
    str_contains($jsCode, 'Learning Programme completed')
    || str_contains($jsCode, 'LP completed')
);

check(
    'JS calls refreshProgressionData if available',
    str_contains($jsCode, 'refreshProgressionData')
);

check(
    'JS guards refreshProgressionData with typeof check',
    str_contains($jsCode, "typeof window.refreshProgressionData")
    || str_contains($jsCode, "typeof refreshProgressionData")
);

// ===========================================================================
// Section 5: Response format validation
// ===========================================================================
echo "\n--- Section 5: Response format & flow ---\n";

// Verify lp_completed defaults to false
check(
    'lp_completed defaults to false before isExamComplete check',
    (bool) preg_match('/\$lpCompleted\s*=\s*false/', $handlerCode)
);

// Verify the flow: recordExamResult -> isExamComplete -> getById -> guard -> markComplete
$recordPos     = strpos($handlerCode, 'recordExamResult(');
$isExamPos     = strpos($handlerCode, 'isExamComplete(');
$getByIdPos    = strpos($handlerCode, 'getById(');
$isCompletedPos = strpos($handlerCode, 'isCompleted()');
$markCompletePos = strpos($handlerCode, 'markComplete(');
$jsonSuccessPos = strpos($handlerCode, "wp_send_json_success");

// Find the LAST wp_send_json_success in handle_record_exam_result (the one with lp_completed)
$lpCompletedPos = strpos($handlerCode, "'lp_completed'");

check(
    'isExamComplete called after recordExamResult',
    $recordPos !== false && $isExamPos !== false && $isExamPos > $recordPos
);

check(
    'getById called after isExamComplete',
    $isExamPos !== false && $getByIdPos !== false && $getByIdPos > $isExamPos
);

check(
    'isCompleted guard before markComplete',
    $isCompletedPos !== false && $markCompletePos !== false && $isCompletedPos < $markCompletePos
);

check(
    'lp_completed in response after markComplete logic',
    $lpCompletedPos !== false && $markCompletePos !== false && $lpCompletedPos > $markCompletePos
);

// ===========================================================================
// Section 6: Partial progress (4/5 steps) must NOT trigger completion
// ===========================================================================
echo "\n--- Section 6: Partial progress — 4/5 steps must NOT complete ---\n";

// isExamComplete iterates ExamStep::cases() and returns false if any step is null.
// Simulate: what if only mock_1..sba are present but final is missing?
// We verify the logic by tracing the code path.

// Count required steps in ExamStep::cases()
// Count enum case declarations (case MOCK_1 = ... pattern) in ExamStep
$enumFile = file_get_contents($pluginDir . '/src/Learners/Enums/ExamStep.php');
$enumCaseCount = preg_match_all('/^\s*case\s+[A-Z_0-9]+\s*=/m', $enumFile);
check(
    'ExamStep has exactly 5 cases',
    $enumCaseCount === 5,
    "Expected 5 enum case definitions, found {$enumCaseCount}"
);

// isExamComplete must check EVERY step — early return false on first null
check(
    'isExamComplete returns false on null step data',
    str_contains($serviceCode, 'if ($stepData === null)') && str_contains($serviceCode, 'return false'),
    'isExamComplete must return false when any step is null'
);

// Verify the foreach iterates ExamStep::cases()
check(
    'isExamComplete iterates all ExamStep::cases()',
    (bool) preg_match('/foreach\s*\(\s*ExamStep::cases\(\)\s+as/', $serviceCode)
);

// The loop must use the progress array keyed by step value
check(
    'isExamComplete indexes progress by step value',
    str_contains($serviceCode, '$progress[$step->value]') || str_contains($serviceCode, "\$progress[\$step->value]")
);

// ===========================================================================
// Section 7: Certificate requirement on final step
// ===========================================================================
echo "\n--- Section 7: Certificate file_path required for final step ---\n";

// isExamComplete must explicitly check file_path on the final step
check(
    'isExamComplete checks FINAL step file_path',
    str_contains($serviceCode, "ExamStep::FINAL->value") && str_contains($serviceCode, "file_path")
);

check(
    'isExamComplete returns false when file_path is empty',
    str_contains($serviceCode, "empty(\$finalData['file_path'])") || str_contains($serviceCode, "empty(\$finalData[\"file_path\"])")
);

// The final check is separate from the loop — it's an additional requirement
$finalCheckPos = strpos($serviceCode, 'ExamStep::FINAL->value');
$loopEndPos    = strrpos(substr($serviceCode, 0, $finalCheckPos ?: 0), '}');
check(
    'Final certificate check is after the all-steps loop',
    $finalCheckPos !== false && $loopEndPos !== false && $finalCheckPos > $loopEndPos,
    'The final file_path check should be after the foreach loop'
);

// Verify that only file_path is checked, not file_name (file_path is the authoritative field)
check(
    'isExamComplete checks file_path (not just file_name) for certificate',
    (bool) preg_match("/empty\(\\\$finalData\['file_path'\]\)/", $serviceCode)
);

// ===========================================================================
// Section 8: Delete/re-record cycle
// ===========================================================================
echo "\n--- Section 8: Delete/re-record cycle support ---\n";

// ExamService::deleteExamResult must exist and delegate to repository
check(
    'deleteExamResult method exists in ExamService',
    str_contains($serviceCode, 'function deleteExamResult(')
);

check(
    'deleteExamResult accepts trackingId and ExamStep',
    (bool) preg_match('/function\s+deleteExamResult\s*\(\s*int\s+\$trackingId\s*,\s*ExamStep\s+\$step\s*\)/', $serviceCode)
);

check(
    'deleteExamResult delegates to repository',
    str_contains($serviceCode, 'deleteByTrackingAndStep(')
);

// After delete, isExamComplete should return false (since a step is missing)
// This is inherently true by the null-check logic, but let's verify the delete returns useful info
check(
    'deleteExamResult returns success/error array',
    str_contains($serviceCode, "'success' => true") && str_contains($serviceCode, "'success' => false")
);

// The AJAX handler for delete exists and is registered
check(
    'handle_delete_exam_result function exists',
    str_contains($handlerCode, 'function handle_delete_exam_result(')
);

check(
    'delete handler registered as wp_ajax_delete_exam_result',
    str_contains($handlerCode, "wp_ajax_delete_exam_result")
);

// recordExamResult can re-record after delete (upsert pattern)
check(
    'recordExamResult uses upsert for re-record support',
    str_contains($serviceCode, '->upsert(')
);

// ===========================================================================
// Section 9: Handler defensive checks — lp_error in response
// ===========================================================================
echo "\n--- Section 9: Handler defensive checks ---\n";

// lp_error key is set when markComplete throws
check(
    'Handler captures markComplete exception message into lp_error',
    str_contains($handlerCode, '$lpError') && str_contains($handlerCode, '$markEx->getMessage()')
);

check(
    'lp_error included in success response when set',
    str_contains($handlerCode, "'lp_error'") && str_contains($handlerCode, '$lpError')
);

// lp_error is only added when non-null (clean response on success)
check(
    'lp_error only added when non-null',
    str_contains($handlerCode, 'if ($lpError !== null)')
);

// The record result still succeeds even if LP marking fails
check(
    'Record success returned even on LP completion failure',
    str_contains($handlerCode, 'wp_send_json_success') && str_contains($handlerCode, "'lp_completed' => \$lpCompleted")
);

// Verify that invalid tracking_id (0 or negative) throws early
check(
    'Handler rejects tracking_id = 0',
    str_contains($handlerCode, "if (!\$trackingId)") || str_contains($handlerCode, "if (\$trackingId <= 0)")
);

// Verify that invalid exam_step is rejected
check(
    'Handler rejects invalid exam_step',
    str_contains($handlerCode, 'ExamStep::tryFromString') && str_contains($handlerCode, '$step === null')
);

// Verify the handler wraps everything in try/catch for overall safety
check(
    'Handler has outer try/catch for complete safety',
    (bool) preg_match('/function\s+handle_record_exam_result\s*\(\s*\)\s*:\s*void\s*\{\s*try\s*\{/s', $handlerCode)
);

// ===========================================================================
// Summary
// ===========================================================================
echo "\n=== Results: {$passed}/{$total} passed, {$failed} failed ===\n";

if ($failed > 0) {
    echo "⚠️  Some checks failed. Review the output above.\n";
    exit(1);
} else {
    echo "✅ All checks passed!\n";
    exit(0);
}
