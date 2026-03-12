<?php
/**
 * Audit Service Verification Script
 *
 * WordPress-bootstrapped script that exercises the AuditService
 * for writing, reading, and purging audit log entries.
 *
 * Run: php tests/History/AuditServiceTest.php
 * Requires: WordPress environment (or standalone bootstrap) + wecoza_events.audit_log table
 */

// Try WordPress bootstrap first, fall back to standalone
$wpLoadPath = __DIR__ . '/../../../../../wp-load.php';
$pluginDir = __DIR__ . '/../../';

if (file_exists($wpLoadPath)) {
    ob_start();
    $wpLoaded = @include_once $wpLoadPath;
    $wpOutput = ob_get_clean();
    if (!($wpLoaded && function_exists('get_option'))) {
        require_once __DIR__ . '/bootstrap.php';
    }
} else {
    require_once __DIR__ . '/bootstrap.php';
}

// Load AuditService
require_once $pluginDir . 'src/Classes/Services/AuditService.php';

use WeCoza\Classes\Services\AuditService;

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

echo "=== Audit Service Verification ===\n\n";

// ──────────────────────────────────────────────
// 1. Instantiation
// ──────────────────────────────────────────────
echo "--- Instantiation ---\n";

$audit = new AuditService();
check($audit instanceof AuditService, 'AuditService instantiates');

// ──────────────────────────────────────────────
// 2. Method Existence
// ──────────────────────────────────────────────
echo "\n--- Method Existence ---\n";

$requiredMethods = [
    'log',
    'getEntityLog',
    'getEntityLogCount',
    'getRecentLog',
    'purgeOlderThan',
    'isValidAction',
];

foreach ($requiredMethods as $method) {
    check(method_exists($audit, $method), "AuditService has method {$method}()");
}

// ──────────────────────────────────────────────
// 3. Action Code Validation
// ──────────────────────────────────────────────
echo "\n--- Action Code Validation ---\n";

check(AuditService::isValidAction('CLASS_STATUS_CHANGED'), 'CLASS_STATUS_CHANGED is valid');
check(AuditService::isValidAction('LEARNER_CREATED'), 'LEARNER_CREATED is valid');
check(AuditService::isValidAction('CLASS_AGENT_ASSIGNED'), 'CLASS_AGENT_ASSIGNED is valid');
check(AuditService::isValidAction('LEARNER_LP_COMPLETED'), 'LEARNER_LP_COMPLETED is valid');
check(AuditService::isValidAction('LEARNER_PORTFOLIO_UPLOADED'), 'LEARNER_PORTFOLIO_UPLOADED is valid');
check(!AuditService::isValidAction('INVALID_ACTION'), 'INVALID_ACTION is rejected');
check(!AuditService::isValidAction(''), 'Empty string is rejected');
check(count(AuditService::ACTION_CODES) >= 14, 'At least 14 action codes defined');

// ──────────────────────────────────────────────
// 4. Audit Log Table Exists
// ──────────────────────────────────────────────
echo "\n--- Audit Log Table ---\n";

$db = \WeCoza\Core\Database\PostgresConnection::getInstance();
if (!$db->isConnected()) {
    echo "  ⚠️  Database not connected — skipping live tests\n";
    echo "\n=== Summary: {$pass} passed, {$fail} failed (DB offline) ===\n";
    exit($fail > 0 ? 1 : 0);
}

$tableCheck = $db->getRow(
    "SELECT COUNT(*) as cnt FROM information_schema.tables
     WHERE table_schema = 'wecoza_events' AND table_name = 'audit_log'"
);
check(
    $tableCheck && (int)$tableCheck['cnt'] === 1,
    'wecoza_events.audit_log table exists'
);

// ──────────────────────────────────────────────
// 5. Write — log()
// ──────────────────────────────────────────────
echo "\n--- Write: log() ---\n";

// Use a unique marker so we can find our test entries
$testMarker = 'TEST_' . time() . '_' . mt_rand(1000, 9999);
$testEntityId = 999999;

$writeResult = $audit->log(
    'CLASS_STATUS_CHANGED',
    'class',
    $testEntityId,
    1, // user_id
    ['test_marker' => $testMarker]
);
check($writeResult === true, 'log() returns true on success');

// Write a second entry
$writeResult2 = $audit->log(
    'CLASS_AGENT_ASSIGNED',
    'class',
    $testEntityId,
    1,
    ['test_marker' => $testMarker]
);
check($writeResult2 === true, 'Second log() returns true');

// Write a learner entry to test entity type filtering
$writeResult3 = $audit->log(
    'LEARNER_CREATED',
    'learner',
    $testEntityId,
    1,
    ['test_marker' => $testMarker]
);
check($writeResult3 === true, 'Learner log() returns true');

// ──────────────────────────────────────────────
// 6. Read — getEntityLog()
// ──────────────────────────────────────────────
echo "\n--- Read: getEntityLog() ---\n";

$classEntries = $audit->getEntityLog('class', $testEntityId, 50, 0);
check(is_array($classEntries), 'getEntityLog returns array');
check(count($classEntries) >= 2, 'At least 2 class entries found');

if (!empty($classEntries)) {
    $entry = $classEntries[0];
    check(isset($entry['id']), 'Entry has id field');
    check(isset($entry['action']), 'Entry has action field');
    check(isset($entry['message']), 'Entry has message field');
    check(isset($entry['context']), 'Entry has context field');
    check(isset($entry['created_at']), 'Entry has created_at field');

    // Verify action is one of our test entries
    $validActions = ['CLASS_STATUS_CHANGED', 'CLASS_AGENT_ASSIGNED'];
    check(in_array($entry['action'], $validActions), 'Entry action is a valid class action');

    // Verify message format: "ACTION: entity_type #entity_id"
    check(
        str_contains($entry['message'], "#{$testEntityId}"),
        'Message contains entity ID'
    );

    // Verify context JSONB
    $context = is_string($entry['context']) ? json_decode($entry['context'], true) : $entry['context'];
    check(
        is_array($context) && ($context['entity_type'] ?? '') === 'class',
        'Context JSONB has entity_type = class'
    );
    check(
        is_array($context) && ($context['entity_id'] ?? 0) == $testEntityId,
        'Context JSONB has correct entity_id'
    );
}

// ──────────────────────────────────────────────
// 7. Read — getEntityLogCount()
// ──────────────────────────────────────────────
echo "\n--- Read: getEntityLogCount() ---\n";

$classCount = $audit->getEntityLogCount('class', $testEntityId);
check($classCount >= 2, "getEntityLogCount returns >= 2 for class #{$testEntityId}");

$learnerCount = $audit->getEntityLogCount('learner', $testEntityId);
check($learnerCount >= 1, "getEntityLogCount returns >= 1 for learner #{$testEntityId}");

// ──────────────────────────────────────────────
// 8. Read — getRecentLog()
// ──────────────────────────────────────────────
echo "\n--- Read: getRecentLog() ---\n";

$recentAll = $audit->getRecentLog(10, 0);
check(is_array($recentAll), 'getRecentLog returns array');
check(count($recentAll) >= 3, 'At least 3 recent entries (our test entries)');

// Filter by entity type
$recentClass = $audit->getRecentLog(10, 0, 'class');
check(is_array($recentClass), 'getRecentLog with filter returns array');

// All returned entries should be class type
$allClass = true;
foreach ($recentClass as $entry) {
    $ctx = is_string($entry['context']) ? json_decode($entry['context'], true) : $entry['context'];
    if (($ctx['entity_type'] ?? '') !== 'class') {
        $allClass = false;
        break;
    }
}
check($allClass, 'Filtered results only contain class entity type');

// ──────────────────────────────────────────────
// 9. Read — Pagination
// ──────────────────────────────────────────────
echo "\n--- Pagination ---\n";

$page1 = $audit->getEntityLog('class', $testEntityId, 1, 0);
$page2 = $audit->getEntityLog('class', $testEntityId, 1, 1);
check(count($page1) === 1, 'Page 1 returns exactly 1 entry');
check(count($page2) === 1, 'Page 2 returns exactly 1 entry');
if (!empty($page1) && !empty($page2)) {
    check($page1[0]['id'] !== $page2[0]['id'], 'Pages return different entries');
}

// ──────────────────────────────────────────────
// 10. Write — Failure Suppression
// ──────────────────────────────────────────────
echo "\n--- Write Failure Suppression ---\n";

// Log with valid params should always succeed — we already tested this.
// The key design point is that log() returns false (not throws) on failure.
// We can't easily simulate a DB failure, but we verify the return type contract.
check(is_bool($writeResult), 'log() returns a boolean');

// ──────────────────────────────────────────────
// 11. Purge — purgeOlderThan()
// ──────────────────────────────────────────────
echo "\n--- Purge: purgeOlderThan() ---\n";

// Purge with a huge retention (9999 months) — should delete nothing recent
$purgedNone = $audit->purgeOlderThan(9999);
check($purgedNone === 0, 'purgeOlderThan(9999 months) deletes nothing');

// Verify our test entries survived the purge
$countAfterPurge = $audit->getEntityLogCount('class', $testEntityId);
check($countAfterPurge >= 2, 'Test entries survive non-matching purge');

// ──────────────────────────────────────────────
// 12. Cleanup — Remove test entries
// ──────────────────────────────────────────────
echo "\n--- Cleanup ---\n";

// Delete our test entries directly to keep the audit log clean
$cleanupSql = "DELETE FROM wecoza_events.audit_log
               WHERE context->>'entity_id' = :entity_id
                 AND context->>'test_marker' = :marker";
$db->query($cleanupSql, [
    'entity_id' => (string) $testEntityId,
    'marker' => $testMarker,
]);

$countAfterCleanup = $audit->getEntityLogCount('class', $testEntityId);
// Might be 0 or might have entries from prior test runs without markers
check($countAfterCleanup < $countAfterPurge, 'Test entries cleaned up');
echo "  ℹ️  Cleaned up test entries with marker {$testMarker}\n";

// ──────────────────────────────────────────────
// Summary
// ──────────────────────────────────────────────
echo "\n=== Summary: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
