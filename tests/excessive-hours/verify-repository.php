<?php
/**
 * Verify ExcessiveHoursRepository queries work against real DB.
 *
 * Run: /opt/lampp/bin/php tests/excessive-hours/verify-repository.php
 * Requires: WordPress bootstrap (for DB connection via wecoza_db())
 */

// Bootstrap WordPress
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
define('ABSPATH', '/opt/lampp/htdocs/wecoza/');
define('WP_USE_THEMES', false);
require_once ABSPATH . 'wp-load.php';

use WeCoza\Reports\ExcessiveHours\ExcessiveHoursRepository;
use WeCoza\Reports\ExcessiveHours\ExcessiveHoursService;

echo "=== Excessive Hours Repository Verification ===\n\n";

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ {$label}\n";
        $passed++;
    } else {
        echo "  ❌ {$label}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
    }
}

// 1. Table exists
echo "1. Table exists\n";
try {
    $pdo = wecoza_db()->getPdo();
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'excessive_hours_resolutions'");
    check('excessive_hours_resolutions table exists', (int) $stmt->fetchColumn() === 1);
} catch (Throwable $e) {
    check('Table check', false, $e->getMessage());
}

// 2. Index exists
echo "\n2. Indexes exist\n";
try {
    $stmt = $pdo->query("SELECT indexname FROM pg_indexes WHERE tablename = 'excessive_hours_resolutions'");
    $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    check('Composite index (tracking_id, created_at)', in_array('idx_ehr_tracking_created', $indexes));
    check('Resolved_by index', in_array('idx_ehr_resolved_by', $indexes));
} catch (Throwable $e) {
    check('Index check', false, $e->getMessage());
}

// 3. Repository instantiation
echo "\n3. Repository\n";
try {
    $repo = new ExcessiveHoursRepository();
    check('Repository instantiates', true);
} catch (Throwable $e) {
    check('Repository instantiation', false, $e->getMessage());
    exit(1);
}

// 4. findFlagged returns correct structure
echo "\n4. findFlagged()\n";
try {
    $result = $repo->findFlagged();
    check('Returns array', is_array($result));
    check('Has data key', array_key_exists('data', $result));
    check('Has total key', array_key_exists('total', $result));
    check('Has open_count key', array_key_exists('open_count', $result));
    check('Has resolved_count key', array_key_exists('resolved_count', $result));
    check('data is array', is_array($result['data']));
    check('total is int', is_int($result['total']));

    echo "  → Found {$result['total']} flagged learners ({$result['open_count']} open, {$result['resolved_count']} resolved)\n";

    if (!empty($result['data'])) {
        $first = $result['data'][0];
        $requiredKeys = ['tracking_id', 'learner_id', 'hours_trained', 'subject_duration', 'overage_hours', 'learner_name', 'class_type_code', 'flag_status'];
        foreach ($requiredKeys as $key) {
            check("Row has '{$key}'", array_key_exists($key, $first));
        }
        check('overage_hours > 0', (float) $first['overage_hours'] > 0);
        check('hours_trained > subject_duration', (float) $first['hours_trained'] > (float) $first['subject_duration']);
        check('class_type in applicable list', in_array($first['class_type_code'], ['AET','REALLL','GETC','BA2','BA3','BA4','ASC']));
    }
} catch (Throwable $e) {
    check('findFlagged query', false, $e->getMessage());
}

// 5. countOpen
echo "\n5. countOpen()\n";
try {
    $count = $repo->countOpen();
    check('Returns int', is_int($count));
    check('Count >= 0', $count >= 0);
    echo "  → Open count: {$count}\n";
} catch (Throwable $e) {
    check('countOpen', false, $e->getMessage());
}

// 6. Service
echo "\n6. Service\n";
try {
    $service = new ExcessiveHoursService();
    check('Service instantiates', true);

    $result = $service->getFlaggedLearners(['status' => 'all']);
    check('getFlaggedLearners returns data', !empty($result['data']) || $result['total'] === 0);

    check('APPLICABLE_CLASS_TYPES defined', count(ExcessiveHoursService::APPLICABLE_CLASS_TYPES) === 7);
    check('ACTION_LABELS defined', count(ExcessiveHoursService::ACTION_LABELS) === 3);
} catch (Throwable $e) {
    check('Service', false, $e->getMessage());
}

// 7. Filters
echo "\n7. Filters\n";
try {
    $openResult = $repo->findFlagged(['status' => 'open']);
    check('Status=open filter works', is_array($openResult['data']));

    $resolvedResult = $repo->findFlagged(['status' => 'resolved']);
    check('Status=resolved filter works', is_array($resolvedResult['data']));
} catch (Throwable $e) {
    check('Filters', false, $e->getMessage());
}

// Summary
echo "\n=== Results: {$passed} passed, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
