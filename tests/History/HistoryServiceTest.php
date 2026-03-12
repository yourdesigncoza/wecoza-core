<?php
/**
 * History Service Verification Script
 *
 * WordPress-bootstrapped script that exercises the History data layer:
 * HistoryRepository and HistoryService (when available).
 *
 * Run: php tests/History/HistoryServiceTest.php
 * Requires: WordPress environment
 */

// Try WordPress bootstrap first, fall back to standalone
$wpLoadPath = __DIR__ . '/../../../../../wp-load.php';
$pluginDir = __DIR__ . '/../../';

if (file_exists($wpLoadPath)) {
    // Suppress MySQL errors in output — we only need PG
    ob_start();
    $wpLoaded = @include_once $wpLoadPath;
    $wpOutput = ob_get_clean();

    // If WP loaded successfully (no fatal DB error), use it
    if ($wpLoaded && function_exists('get_option')) {
        // WP is available
    } else {
        // WP failed (MySQL down) — use standalone bootstrap
        require_once __DIR__ . '/bootstrap.php';
    }
} else {
    require_once __DIR__ . '/bootstrap.php';
}

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

echo "=== History Service Verification ===\n\n";

// ──────────────────────────────────────────────
// 1. HistoryRepository — Instantiation
// ──────────────────────────────────────────────
echo "--- HistoryRepository ---\n";

require_once $pluginDir . 'src/Classes/Repositories/HistoryRepository.php';
use WeCoza\Classes\Repositories\HistoryRepository;

$repo = new HistoryRepository();
check($repo instanceof HistoryRepository, 'HistoryRepository instantiates');

// ──────────────────────────────────────────────
// 2. HistoryRepository — Method Existence
// ──────────────────────────────────────────────
echo "\n--- Method Existence ---\n";

$requiredMethods = [
    'getClassHistory',
    'getLearnerHistory',
    'getAgentHistory',
    'getClientHistory',
    'getAgentClassHistory',
    'getAgentClassHistoryByAgent',
];

foreach ($requiredMethods as $method) {
    check(method_exists($repo, $method), "HistoryRepository has method {$method}()");
}

// ──────────────────────────────────────────────
// 3. HistoryRepository — getClassHistory Shape
// ──────────────────────────────────────────────
echo "\n--- getClassHistory (non-existent ID) ---\n";

$classHistory = $repo->getClassHistory(999999);
check(is_array($classHistory), 'getClassHistory returns array');
check(array_key_exists('agent_assignments', $classHistory), 'Has agent_assignments key');
check(array_key_exists('learner_assignments', $classHistory), 'Has learner_assignments key');
check(array_key_exists('status_changes', $classHistory), 'Has status_changes key');
check(array_key_exists('stop_restart_dates', $classHistory), 'Has stop_restart_dates key');
check($classHistory['agent_assignments'] === [], 'Empty agent_assignments for non-existent class');
check($classHistory['learner_assignments'] === [], 'Empty learner_assignments for non-existent class');
check($classHistory['status_changes'] === [], 'Empty status_changes for non-existent class');
check($classHistory['stop_restart_dates'] === [], 'Empty stop_restart_dates for non-existent class');

// ──────────────────────────────────────────────
// 4. HistoryRepository — getLearnerHistory Shape
// ──────────────────────────────────────────────
echo "\n--- getLearnerHistory (non-existent ID) ---\n";

$learnerHistory = $repo->getLearnerHistory(999999);
check(is_array($learnerHistory), 'getLearnerHistory returns array');
check(array_key_exists('class_enrollments', $learnerHistory), 'Has class_enrollments key');
check(array_key_exists('hours_logged', $learnerHistory), 'Has hours_logged key');
check($learnerHistory['class_enrollments'] === [], 'Empty class_enrollments for non-existent learner');
check($learnerHistory['hours_logged'] === [], 'Empty hours_logged for non-existent learner');

// ──────────────────────────────────────────────
// 5. HistoryRepository — getAgentHistory Shape
// ──────────────────────────────────────────────
echo "\n--- getAgentHistory (non-existent ID) ---\n";

$agentHistory = $repo->getAgentHistory(999999);
check(is_array($agentHistory), 'getAgentHistory returns array');
check(array_key_exists('primary_classes', $agentHistory), 'Has primary_classes key');
check(array_key_exists('backup_classes', $agentHistory), 'Has backup_classes key');
check(array_key_exists('notes', $agentHistory), 'Has notes key');
check(array_key_exists('absences', $agentHistory), 'Has absences key');
check($agentHistory['primary_classes'] === [], 'Empty primary_classes for non-existent agent');
check($agentHistory['backup_classes'] === [], 'Empty backup_classes for non-existent agent');

// ──────────────────────────────────────────────
// 6. HistoryRepository — getClientHistory Shape
// ──────────────────────────────────────────────
echo "\n--- getClientHistory (non-existent ID) ---\n";

$clientHistory = $repo->getClientHistory(999999);
check(is_array($clientHistory), 'getClientHistory returns array');
check(array_key_exists('classes', $clientHistory), 'Has classes key');
check(array_key_exists('locations', $clientHistory), 'Has locations key');
check($clientHistory['classes'] === [], 'Empty classes for non-existent client');
check($clientHistory['locations'] === [], 'Empty locations for non-existent client');

// ──────────────────────────────────────────────
// 7. HistoryRepository — Live Data (if available)
// ──────────────────────────────────────────────
echo "\n--- Live Data Queries ---\n";

// Find a real class ID to test with
$db = \WeCoza\Core\Database\PostgresConnection::getInstance();
$sampleClass = $db->getRow("SELECT class_id FROM classes LIMIT 1");

if ($sampleClass) {
    $classId = (int) $sampleClass['class_id'];
    $liveClassHistory = $repo->getClassHistory($classId);
    check(is_array($liveClassHistory), "getClassHistory({$classId}) returns array on real data");
    check(
        isset($liveClassHistory['agent_assignments']) && is_array($liveClassHistory['agent_assignments']),
        'agent_assignments is array on real data'
    );
    check(
        isset($liveClassHistory['learner_assignments']) && is_array($liveClassHistory['learner_assignments']),
        'learner_assignments is array on real data'
    );
    // Verify agent assignment shape (if any)
    if (!empty($liveClassHistory['agent_assignments'])) {
        $firstAgent = $liveClassHistory['agent_assignments'][0];
        check(isset($firstAgent['agent_id']), 'Agent assignment has agent_id field');
        check(isset($firstAgent['assignment_type']), 'Agent assignment has assignment_type field');
        check(isset($firstAgent['class_id']), 'Agent assignment has class_id field');
    }
    // Verify learner assignment shape (if any)
    if (!empty($liveClassHistory['learner_assignments'])) {
        $firstLearner = $liveClassHistory['learner_assignments'][0];
        check(isset($firstLearner['learner_id']), 'Learner assignment has learner_id field');
        check(isset($firstLearner['class_id']), 'Learner assignment has class_id field');
        check(array_key_exists('level', $firstLearner), 'Learner assignment has level field');
        check(array_key_exists('status', $firstLearner), 'Learner assignment has status field');
    }
    echo "  ℹ️  Class {$classId}: " .
         count($liveClassHistory['agent_assignments']) . " agent assignments, " .
         count($liveClassHistory['learner_assignments']) . " learner assignments, " .
         count($liveClassHistory['status_changes']) . " status changes\n";
} else {
    echo "  ⚠️  No classes in DB — skipping live data tests\n";
}

// Find a real agent ID
$sampleAgent = $db->getRow("SELECT agent_id FROM agents LIMIT 1");
if ($sampleAgent) {
    $agentId = (int) $sampleAgent['agent_id'];
    $liveAgentHistory = $repo->getAgentHistory($agentId);
    check(is_array($liveAgentHistory), "getAgentHistory({$agentId}) returns array on real data");
    echo "  ℹ️  Agent {$agentId}: " .
         count($liveAgentHistory['primary_classes']) . " primary classes, " .
         count($liveAgentHistory['backup_classes']) . " backup classes\n";
}

// Find a real client ID
$sampleClient = $db->getRow("SELECT client_id FROM clients LIMIT 1");
if ($sampleClient) {
    $clientId = (int) $sampleClient['client_id'];
    $liveClientHistory = $repo->getClientHistory($clientId);
    check(is_array($liveClientHistory), "getClientHistory({$clientId}) returns array on real data");
    echo "  ℹ️  Client {$clientId}: " .
         count($liveClientHistory['classes']) . " classes, " .
         count($liveClientHistory['locations']) . " locations\n";
}

// Find a real learner ID (PK is 'id' in learners table)
$sampleLearner = $db->getRow("SELECT id AS learner_id FROM learners LIMIT 1");
if ($sampleLearner) {
    $learnerId = (int) $sampleLearner['learner_id'];
    $liveLearnerHistory = $repo->getLearnerHistory($learnerId);
    check(is_array($liveLearnerHistory), "getLearnerHistory({$learnerId}) returns array on real data");
    echo "  ℹ️  Learner {$learnerId}: " .
         count($liveLearnerHistory['class_enrollments']) . " enrollments, " .
         count($liveLearnerHistory['hours_logged']) . " hours log entries\n";
}

// ──────────────────────────────────────────────
// 8. Extended Methods — Existence
// ──────────────────────────────────────────────
echo "\n--- Extended Method Existence ---\n";

$extendedMethods = [
    'getClassQAVisits',
    'getClassEvents',
    'getClassNotes',
    'getLearnerPortfolios',
    'getLearnerProgressionDates',
    'getAgentQAVisits',
    'getAgentSubjects',
];

foreach ($extendedMethods as $method) {
    check(method_exists($repo, $method), "HistoryRepository has method {$method}()");
}

// ──────────────────────────────────────────────
// 9. Extended Methods — Empty Results for Non-Existent IDs
// ──────────────────────────────────────────────
echo "\n--- Extended Methods (non-existent IDs) ---\n";

$qaVisits = $repo->getClassQAVisits(999999);
check(is_array($qaVisits) && $qaVisits === [], 'getClassQAVisits returns empty array');

$events = $repo->getClassEvents(999999);
check(is_array($events) && $events === [], 'getClassEvents returns empty array');

$notes = $repo->getClassNotes(999999);
check(is_array($notes) && $notes === [], 'getClassNotes returns empty array');

$portfolios = $repo->getLearnerPortfolios(999999);
check(is_array($portfolios) && $portfolios === [], 'getLearnerPortfolios returns empty array');

$progDates = $repo->getLearnerProgressionDates(999999);
check(is_array($progDates) && $progDates === [], 'getLearnerProgressionDates returns empty array');

$agentQA = $repo->getAgentQAVisits(999999);
check(is_array($agentQA) && $agentQA === [], 'getAgentQAVisits returns empty array');

$agentSubjects = $repo->getAgentSubjects(999999);
check(is_array($agentSubjects) && $agentSubjects === [], 'getAgentSubjects returns empty array');

// ──────────────────────────────────────────────
// 10. Extended Methods — Live Data
// ──────────────────────────────────────────────
echo "\n--- Extended Methods (live data) ---\n";

// Class events from events_log
if ($sampleClass) {
    $classId = (int) $sampleClass['class_id'];
    $liveEvents = $repo->getClassEvents($classId);
    check(is_array($liveEvents), "getClassEvents({$classId}) returns array");
    echo "  ℹ️  Class {$classId}: " . count($liveEvents) . " events in events_log\n";

    $liveNotes = $repo->getClassNotes($classId);
    check(is_array($liveNotes), "getClassNotes({$classId}) returns array");
    echo "  ℹ️  Class {$classId}: " . count($liveNotes) . " notes entries\n";
}

// Agent subjects
if ($sampleAgent) {
    $agentId = (int) $sampleAgent['agent_id'];
    $liveSubjects = $repo->getAgentSubjects($agentId);
    check(is_array($liveSubjects), "getAgentSubjects({$agentId}) returns array");
    if (!empty($liveSubjects)) {
        $first = $liveSubjects[0];
        check(isset($first['class_type']), 'Agent subject has class_type');
        check(isset($first['class_count']), 'Agent subject has class_count');
        check(isset($first['first_facilitated']), 'Agent subject has first_facilitated');
        check(isset($first['last_facilitated']), 'Agent subject has last_facilitated');
    }
    echo "  ℹ️  Agent {$agentId}: " . count($liveSubjects) . " distinct subjects\n";
}

// Learner progression dates
if ($sampleLearner) {
    $learnerId = (int) $sampleLearner['learner_id'];
    $liveDates = $repo->getLearnerProgressionDates($learnerId);
    check(is_array($liveDates), "getLearnerProgressionDates({$learnerId}) returns array");
    if (!empty($liveDates)) {
        $first = $liveDates[0];
        check(array_key_exists('start_date', $first), 'Progression has start_date');
        check(array_key_exists('completion_date', $first), 'Progression has completion_date');
        check(isset($first['class_type']), 'Progression has class_type');
        check(isset($first['status']), 'Progression has status');
    }
    echo "  ℹ️  Learner {$learnerId}: " . count($liveDates) . " progression entries\n";

    $livePortfolios = $repo->getLearnerPortfolios($learnerId);
    check(is_array($livePortfolios), "getLearnerPortfolios({$learnerId}) returns array");
    echo "  ℹ️  Learner {$learnerId}: " . count($livePortfolios) . " portfolio files\n";
}

// ──────────────────────────────────────────────
// 11. HistoryService — Facade
// ──────────────────────────────────────────────
echo "\n--- HistoryService ---\n";

$historyServiceFile = $pluginDir . 'src/Classes/Services/HistoryService.php';
if (!file_exists($historyServiceFile)) {
    echo "  ⏳ HistoryService not yet created\n";
} else {
    require_once $historyServiceFile;

    $svc = new \WeCoza\Classes\Services\HistoryService();
    check($svc instanceof \WeCoza\Classes\Services\HistoryService, 'HistoryService instantiates');

    // Method existence
    $svcMethods = ['getClassTimeline', 'getAgentTimeline', 'getLearnerTimeline', 'getClientTimeline'];
    foreach ($svcMethods as $m) {
        check(method_exists($svc, $m), "HistoryService has method {$m}()");
    }

    // --- getClassTimeline shape (non-existent) ---
    echo "\n--- getClassTimeline (non-existent ID) ---\n";
    $ct = $svc->getClassTimeline(999999);
    check(is_array($ct), 'getClassTimeline returns array');
    $ctKeys = ['agent_assignments', 'learner_assignments', 'status_changes', 'stop_restart_dates', 'qa_visits', 'events', 'notes'];
    foreach ($ctKeys as $k) {
        check(array_key_exists($k, $ct), "Class timeline has '{$k}' key");
    }

    // --- getAgentTimeline shape (non-existent) ---
    echo "\n--- getAgentTimeline (non-existent ID) ---\n";
    $at = $svc->getAgentTimeline(999999);
    check(is_array($at), 'getAgentTimeline returns array');
    $atKeys = ['primary_classes', 'backup_classes', 'notes', 'absences', 'qa_visits', 'subjects', 'clients'];
    foreach ($atKeys as $k) {
        check(array_key_exists($k, $at), "Agent timeline has '{$k}' key");
    }

    // --- getLearnerTimeline shape (non-existent) ---
    echo "\n--- getLearnerTimeline (non-existent ID) ---\n";
    $lt = $svc->getLearnerTimeline(999999);
    check(is_array($lt), 'getLearnerTimeline returns array');
    $ltKeys = ['class_enrollments', 'hours_logged', 'portfolios', 'progression_dates', 'clients'];
    foreach ($ltKeys as $k) {
        check(array_key_exists($k, $lt), "Learner timeline has '{$k}' key");
    }

    // --- getClientTimeline shape (non-existent) ---
    echo "\n--- getClientTimeline (non-existent ID) ---\n";
    $clt = $svc->getClientTimeline(999999);
    check(is_array($clt), 'getClientTimeline returns array');
    $cltKeys = ['classes', 'locations', 'agents', 'learners'];
    foreach ($cltKeys as $k) {
        check(array_key_exists($k, $clt), "Client timeline has '{$k}' key");
    }

    // --- Live data tests ---
    echo "\n--- HistoryService Live Data ---\n";

    if ($sampleClass) {
        $classId = (int) $sampleClass['class_id'];
        $liveCt = $svc->getClassTimeline($classId);
        check(is_array($liveCt) && count($liveCt) === 7, "getClassTimeline({$classId}) returns 7 keys");
        echo "  ℹ️  Class {$classId}: " .
             count($liveCt['agent_assignments']) . " agents, " .
             count($liveCt['learner_assignments']) . " learners, " .
             count($liveCt['events']) . " events, " .
             count($liveCt['notes']) . " notes\n";
    }

    if ($sampleAgent) {
        $agentId = (int) $sampleAgent['agent_id'];
        $liveAt = $svc->getAgentTimeline($agentId);
        check(is_array($liveAt) && count($liveAt) === 7, "getAgentTimeline({$agentId}) returns 7 keys");
        echo "  ℹ️  Agent {$agentId}: " .
             count($liveAt['primary_classes']) . " primary, " .
             count($liveAt['subjects']) . " subjects, " .
             count($liveAt['clients']) . " clients\n";
    }

    if ($sampleLearner) {
        $learnerId = (int) $sampleLearner['learner_id'];
        $liveLt = $svc->getLearnerTimeline($learnerId);
        check(is_array($liveLt) && count($liveLt) === 5, "getLearnerTimeline({$learnerId}) returns 5 keys");
        echo "  ℹ️  Learner {$learnerId}: " .
             count($liveLt['class_enrollments']) . " enrollments, " .
             count($liveLt['progression_dates']) . " progressions, " .
             count($liveLt['clients']) . " clients\n";
    }

    if ($sampleClient) {
        $clientId = (int) $sampleClient['client_id'];
        $liveClt = $svc->getClientTimeline($clientId);
        check(is_array($liveClt) && count($liveClt) === 4, "getClientTimeline({$clientId}) returns 4 keys");
        echo "  ℹ️  Client {$clientId}: " .
             count($liveClt['classes']) . " classes, " .
             count($liveClt['agents']) . " agents, " .
             count($liveClt['learners']) . " learners\n";
    }
}

// ──────────────────────────────────────────────
// Summary
// ──────────────────────────────────────────────
echo "\n=== Summary: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
