<?php
/**
 * Exam Schema Verification Script
 *
 * Validates that schema/learner_exam_results.sql has the expected
 * table structure, columns, constraints, and references.
 *
 * Run: php tests/exam/verify-exam-schema.php
 */

$schemaFile = __DIR__ . '/../../schema/learner_exam_results.sql';

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

echo "=== Exam Schema Verification ===\n\n";

// 1. File exists
check(file_exists($schemaFile), 'Schema file exists at schema/learner_exam_results.sql');

if (!file_exists($schemaFile)) {
    echo "\n⚠ Cannot continue without schema file.\n";
    exit(1);
}

$sql = file_get_contents($schemaFile);

// 2. CREATE TABLE present
check(
    str_contains($sql, 'CREATE TABLE public.learner_exam_results'),
    'CREATE TABLE public.learner_exam_results statement present'
);

// 3. Expected columns
$expectedColumns = [
    'result_id'   => 'SERIAL PRIMARY KEY',
    'tracking_id' => 'INTEGER NOT NULL',
    'exam_step'   => 'VARCHAR(10) NOT NULL',
    'percentage'  => 'NUMERIC(5,2)',
    'file_path'   => 'VARCHAR(500)',
    'file_name'   => 'VARCHAR(255)',
    'recorded_by' => 'INTEGER',
    'recorded_at' => 'TIMESTAMP',
    'updated_at'  => 'TIMESTAMP',
];

echo "\nColumn checks:\n";
foreach ($expectedColumns as $column => $type) {
    check(
        str_contains($sql, $column),
        "Column '{$column}' present"
    );
}

// 4. CHECK constraint for exam_step values
echo "\nConstraint checks:\n";
$examStepValues = ['mock_1', 'mock_2', 'mock_3', 'sba', 'final'];
foreach ($examStepValues as $stepVal) {
    check(
        str_contains($sql, "'{$stepVal}'"),
        "CHECK constraint includes '{$stepVal}'"
    );
}

// 5. CHECK constraint for percentage range
check(
    str_contains($sql, 'percentage >= 0') && str_contains($sql, 'percentage <= 100'),
    'CHECK constraint for percentage 0-100'
);

// 6. UNIQUE constraint on (tracking_id, exam_step)
check(
    str_contains($sql, 'UNIQUE (tracking_id, exam_step)') || str_contains($sql, 'UNIQUE(tracking_id, exam_step)'),
    'UNIQUE constraint on (tracking_id, exam_step)'
);

// 7. Foreign key reference to learner_lp_tracking
check(
    str_contains($sql, 'REFERENCES public.learner_lp_tracking(tracking_id)'),
    'FK reference to learner_lp_tracking(tracking_id)'
);

// 8. ON DELETE CASCADE
check(
    str_contains($sql, 'ON DELETE CASCADE'),
    'ON DELETE CASCADE on FK'
);

// Summary
echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
