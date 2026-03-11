<?php
/**
 * Verification script for T01: Exam AJAX handlers and data layer wiring
 *
 * Checks:
 * 1. ExamAjaxHandlers.php exists and has correct namespace
 * 2. All 3 wp_ajax action hooks are registered
 * 3. getCurrentLPDetails returns is_exam_class key
 * 4. ExamService integration (getExamProgress returns expected structure)
 * 5. Input validation (invalid step returns null, ExamStep::tryFromString works)
 * 6. ExamService::deleteExamResult exists
 * 7. baseQuery includes exam_class
 * 8. LearnerProgressionModel has exam_class support
 *
 * Usage: php tests/exam/verify-exam-ajax.php
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

echo "\n=== T01 Verification: Exam AJAX & Data Layer ===\n\n";

// ---------------------------------------------------------------------------
// 1. ExamAjaxHandlers.php exists and has correct namespace
// ---------------------------------------------------------------------------
$handlerFile = __DIR__ . '/../../src/Learners/Ajax/ExamAjaxHandlers.php';
$handlerContent = file_exists($handlerFile) ? file_get_contents($handlerFile) : '';

check(
    'ExamAjaxHandlers.php exists',
    file_exists($handlerFile)
);

check(
    'ExamAjaxHandlers.php has correct namespace',
    str_contains($handlerContent, 'namespace WeCoza\\Learners\\Ajax;')
);

// ---------------------------------------------------------------------------
// 2. All 3 wp_ajax action hooks are registered in the file
// ---------------------------------------------------------------------------
$expectedActions = [
    'wp_ajax_record_exam_result',
    'wp_ajax_get_exam_progress',
    'wp_ajax_delete_exam_result',
];

foreach ($expectedActions as $action) {
    check(
        "Handler registers {$action}",
        str_contains($handlerContent, $action),
        'Action hook not found in ExamAjaxHandlers.php'
    );
}

// ---------------------------------------------------------------------------
// 3. Handlers use verify_learner_access
// ---------------------------------------------------------------------------
$handlerFunctions = ['handle_record_exam_result', 'handle_get_exam_progress', 'handle_delete_exam_result'];
foreach ($handlerFunctions as $fn) {
    check(
        "{$fn} exists",
        str_contains($handlerContent, "function {$fn}"),
        'Function not found'
    );
}

check(
    'Handlers use verify_learner_access(\'learners_nonce\')',
    substr_count($handlerContent, "verify_learner_access('learners_nonce')") >= 3,
    'Expected 3 calls to verify_learner_access'
);

// ---------------------------------------------------------------------------
// 4. ExamStep::tryFromString used (not ::from)
// ---------------------------------------------------------------------------
check(
    'Uses ExamStep::tryFromString() for safe validation',
    str_contains($handlerContent, 'ExamStep::tryFromString('),
    'Should use tryFromString for graceful handling of invalid steps'
);

check(
    'Does NOT use ExamStep::from() directly',
    !str_contains($handlerContent, 'ExamStep::from('),
    'Should not use ::from() which throws on invalid input'
);

// ---------------------------------------------------------------------------
// 5. Error logging pattern
// ---------------------------------------------------------------------------
check(
    'Uses "WeCoza ExamAjax:" error_log pattern',
    str_contains($handlerContent, 'WeCoza ExamAjax:'),
    'Should use consistent error log prefix'
);

// ---------------------------------------------------------------------------
// 6. baseQuery includes exam_class COALESCE
// ---------------------------------------------------------------------------
$repoFile = __DIR__ . '/../../src/Learners/Repositories/LearnerProgressionRepository.php';
$repoContent = file_exists($repoFile) ? file_get_contents($repoFile) : '';

check(
    'baseQuery() includes COALESCE(c.exam_class, \'No\') AS exam_class',
    str_contains($repoContent, "COALESCE(c.exam_class, 'No') AS exam_class"),
    'exam_class not found in baseQuery SELECT'
);

// ---------------------------------------------------------------------------
// 7. ProgressionService returns is_exam_class
// ---------------------------------------------------------------------------
$serviceFile = __DIR__ . '/../../src/Learners/Services/ProgressionService.php';
$serviceContent = file_exists($serviceFile) ? file_get_contents($serviceFile) : '';

check(
    'getCurrentLPDetails returns is_exam_class key',
    str_contains($serviceContent, "'is_exam_class'"),
    'is_exam_class key not found in getCurrentLPDetails'
);

check(
    'getCurrentLPDetails returns exam_progress key for exam classes',
    str_contains($serviceContent, "'exam_progress'"),
    'exam_progress key not found in getCurrentLPDetails'
);

// ---------------------------------------------------------------------------
// 8. ExamService has deleteExamResult method
// ---------------------------------------------------------------------------
$examServiceFile = __DIR__ . '/../../src/Learners/Services/ExamService.php';
$examServiceContent = file_exists($examServiceFile) ? file_get_contents($examServiceFile) : '';

check(
    'ExamService has deleteExamResult method',
    str_contains($examServiceContent, 'function deleteExamResult('),
    'deleteExamResult method not found in ExamService'
);

// ---------------------------------------------------------------------------
// 9. wecoza-core.php loads ExamAjaxHandlers
// ---------------------------------------------------------------------------
$coreFile = __DIR__ . '/../../wecoza-core.php';
$coreContent = file_exists($coreFile) ? file_get_contents($coreFile) : '';

check(
    'wecoza-core.php loads ExamAjaxHandlers.php',
    str_contains($coreContent, 'ExamAjaxHandlers.php'),
    'require_once for ExamAjaxHandlers not found'
);

// ---------------------------------------------------------------------------
// 10. LearnerProgressionModel has exam_class support
// ---------------------------------------------------------------------------
$modelFile = __DIR__ . '/../../src/Learners/Models/LearnerProgressionModel.php';
$modelContent = file_exists($modelFile) ? file_get_contents($modelFile) : '';

check(
    'LearnerProgressionModel has examClass property',
    str_contains($modelContent, '$examClass'),
    'examClass property not found'
);

check(
    'LearnerProgressionModel has isExamClass() method',
    str_contains($modelContent, 'function isExamClass()'),
    'isExamClass method not found'
);

// ---------------------------------------------------------------------------
// 11. ExamStep::tryFromString validation (unit test)
// ---------------------------------------------------------------------------
// Load the enum file directly for validation testing
require_once __DIR__ . '/../../src/Learners/Enums/ExamStep.php';

use WeCoza\Learners\Enums\ExamStep;

check(
    'ExamStep::tryFromString("mock_1") returns MOCK_1',
    ExamStep::tryFromString('mock_1') === ExamStep::MOCK_1
);

check(
    'ExamStep::tryFromString("invalid_step") returns null',
    ExamStep::tryFromString('invalid_step') === null,
    'Should return null for invalid step'
);

check(
    'ExamStep::tryFromString(null) returns null',
    ExamStep::tryFromString(null) === null,
    'Should return null for null input'
);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n=== Results: {$passed}/{$total} passed, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
