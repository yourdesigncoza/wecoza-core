<?php
/**
 * Exam Task Integration Verification Script
 *
 * WordPress-bootstrapped script covering all S02 integration checks:
 * - Section 1: ExamTaskProvider unit checks
 * - Section 2: ExamTaskProvider DB checks
 * - Section 3: TaskManager integration (placeholder — T02)
 * - Section 4: ClassTaskPresenter checks (placeholder — T03)
 *
 * Run: php tests/exam/verify-exam-task-integration.php
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
require_once $pluginDir . 'src/Events/Models/Task.php';
require_once $pluginDir . 'src/Events/Models/TaskCollection.php';
require_once $pluginDir . 'src/Events/Services/ExamTaskProvider.php';

use WeCoza\Events\Models\Task;
use WeCoza\Events\Models\TaskCollection;
use WeCoza\Events\Services\ExamTaskProvider;
use WeCoza\Learners\Enums\ExamStep;
use WeCoza\Learners\Repositories\ExamRepository;

$pass = 0;
$fail = 0;
$skip = 0;

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

function skip(string $label, string $reason): void
{
    global $skip;
    echo "  ⚠ SKIP: {$label} — {$reason}\n";
    $skip++;
}

echo "=== Exam Task Integration Verification (S02) ===\n\n";

// ══════════════════════════════════════════════════════
// SECTION 1: ExamTaskProvider Unit Checks
// ══════════════════════════════════════════════════════
echo "--- Section 1: ExamTaskProvider Unit Checks ---\n";

// 1.1 Instantiation
$provider = new ExamTaskProvider();
check($provider instanceof ExamTaskProvider, 'ExamTaskProvider instantiates without errors');

// 1.2 parseExamTaskId — valid IDs
$parsed = ExamTaskProvider::parseExamTaskId('exam-42-mock_1');
check($parsed !== null, 'parseExamTaskId parses valid ID (exam-42-mock_1)');
check($parsed['tracking_id'] === 42, 'parseExamTaskId extracts tracking_id=42');
check($parsed['step'] === ExamStep::MOCK_1, 'parseExamTaskId extracts step=MOCK_1');

$parsed2 = ExamTaskProvider::parseExamTaskId('exam-100-final');
check($parsed2 !== null, 'parseExamTaskId parses exam-100-final');
check($parsed2['tracking_id'] === 100, 'parseExamTaskId extracts tracking_id=100');
check($parsed2['step'] === ExamStep::FINAL, 'parseExamTaskId extracts step=FINAL');

$parsed3 = ExamTaskProvider::parseExamTaskId('exam-7-sba');
check($parsed3 !== null, 'parseExamTaskId parses exam-7-sba');
check($parsed3['step'] === ExamStep::SBA, 'parseExamTaskId extracts step=SBA');

// 1.3 parseExamTaskId — invalid IDs return null
check(ExamTaskProvider::parseExamTaskId('event-0') === null, 'parseExamTaskId returns null for event-0');
check(ExamTaskProvider::parseExamTaskId('agent-order') === null, 'parseExamTaskId returns null for agent-order');
check(ExamTaskProvider::parseExamTaskId('exam-0-mock_1') === null, 'parseExamTaskId returns null for tracking_id=0');
check(ExamTaskProvider::parseExamTaskId('exam-abc-mock_1') === null, 'parseExamTaskId returns null for non-numeric tracking_id');
check(ExamTaskProvider::parseExamTaskId('exam-42-invalid_step') === null, 'parseExamTaskId returns null for invalid step');
check(ExamTaskProvider::parseExamTaskId('') === null, 'parseExamTaskId returns null for empty string');
check(ExamTaskProvider::parseExamTaskId('exam-') === null, 'parseExamTaskId returns null for partial ID');
check(ExamTaskProvider::parseExamTaskId('exam--mock_1') === null, 'parseExamTaskId returns null for missing tracking_id');

// 1.4 isExamTaskId
check(ExamTaskProvider::isExamTaskId('exam-42-mock_1') === true, 'isExamTaskId true for valid exam task');
check(ExamTaskProvider::isExamTaskId('event-0') === false, 'isExamTaskId false for event task');
check(ExamTaskProvider::isExamTaskId('agent-order') === false, 'isExamTaskId false for agent-order');

// 1.5 Empty input handling
$emptyResult = $provider->getExamTasksForClasses([]);
check($emptyResult === [], 'getExamTasksForClasses returns empty array for empty input');

// 1.6 Cache behavior — getExamTasksForClass without preload returns empty
$emptyCollection = $provider->getExamTasksForClass(99999);
check($emptyCollection instanceof TaskCollection, 'getExamTasksForClass returns TaskCollection');
check($emptyCollection->isEmpty(), 'getExamTasksForClass returns empty for non-preloaded class');

// 1.7 preloadForClasses with empty array doesn't error
$provider->preloadForClasses([]);
check(true, 'preloadForClasses with empty array completes without error');


// ══════════════════════════════════════════════════════
// SECTION 2: ExamTaskProvider DB Checks
// ══════════════════════════════════════════════════════
echo "\n--- Section 2: ExamTaskProvider DB Checks ---\n";

$tablesExist = false;
try {
    $db = wecoza_db();
    $stmt = $db->query("SELECT 1 FROM information_schema.tables WHERE table_name IN ('learner_lp_tracking', 'learner_exam_results', 'learners') GROUP BY 1 HAVING COUNT(*) >= 1");
    $tablesExist = ($stmt->fetchColumn() !== false);
} catch (\Exception $e) {
    // DB not available
}

if (!$tablesExist) {
    skip('DB checks', 'Required tables not available');
} else {
    // Find an exam-type class with learners in learner_lp_tracking
    $examClassId = null;
    $nonExamClassId = null;

    try {
        // Find a class_id that has learners in tracking
        $stmt = $db->query("
            SELECT t.class_id, COUNT(DISTINCT t.tracking_id) as learner_count
            FROM learner_lp_tracking t
            GROUP BY t.class_id
            HAVING COUNT(DISTINCT t.tracking_id) > 0
            ORDER BY learner_count DESC
            LIMIT 5
        ");
        $classRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($classRows)) {
            $examClassId = (int) $classRows[0]['class_id'];
            // Use a different class for non-exam test, or a non-existent one
            $nonExamClassId = 999999; // Guaranteed no learners
        }
    } catch (\Exception $e) {
        // ignore
    }

    if ($examClassId === null) {
        skip('DB checks', 'No class with learners found in learner_lp_tracking');
    } else {
        echo "  Using class_id={$examClassId} for exam class test\n";

        // 2.1 Generate tasks for a class with learners
        $provider2 = new ExamTaskProvider();
        $collections = $provider2->getExamTasksForClasses([$examClassId]);

        check(is_array($collections), 'getExamTasksForClasses returns array');
        check(isset($collections[$examClassId]), 'Result keyed by requested class_id');
        check($collections[$examClassId] instanceof TaskCollection, 'Value is TaskCollection');

        $tasks = $collections[$examClassId]->all();
        check(count($tasks) > 0, 'Tasks generated for class with learners (count=' . count($tasks) . ')');

        // 2.2 Verify task count is multiple of 5 (5 steps per learner)
        check(count($tasks) % 5 === 0, 'Task count is multiple of 5 (one per ExamStep per learner)');

        // 2.3 Verify task ID format
        if (count($tasks) > 0) {
            $firstTask = $tasks[0];
            check(str_starts_with($firstTask->getId(), 'exam-'), 'Task ID starts with exam-');
            $parsedId = ExamTaskProvider::parseExamTaskId($firstTask->getId());
            check($parsedId !== null, 'Task ID is parseable by parseExamTaskId');
        }

        // 2.4 Verify task labels contain learner name
        if (count($tasks) > 0) {
            $label = $tasks[0]->getLabel();
            // Should contain a colon separator and some name text
            check(str_contains($label, ':'), 'Task label contains colon separator');
            // Check it starts with one of the known exam step labels
            $stepLabels = array_map(fn(ExamStep $s) => $s->label(), ExamStep::cases());
            $startsWithStep = false;
            foreach ($stepLabels as $sl) {
                if (str_starts_with($label, $sl)) {
                    $startsWithStep = true;
                    break;
                }
            }
            check($startsWithStep, 'Task label starts with exam step label');
        }

        // 2.5 Verify task statuses are valid
        $statuses = array_map(fn(Task $t) => $t->getStatus(), $tasks);
        $validStatuses = [Task::STATUS_OPEN, Task::STATUS_COMPLETED];
        $allValid = true;
        foreach ($statuses as $s) {
            if (!in_array($s, $validStatuses, true)) {
                $allValid = false;
                break;
            }
        }
        check($allValid, 'All task statuses are valid (open or completed)');

        // 2.6 Completed tasks have completedBy and completedAt
        $completedTasks = $collections[$examClassId]->completed();
        if (count($completedTasks) > 0) {
            $ct = $completedTasks[0];
            check($ct->getCompletedAt() !== null, 'Completed task has completedAt');
            // completedBy may be null if recorded_by was null in old data
            echo "  ℹ Completed tasks found: " . count($completedTasks) . "\n";
        } else {
            echo "  ℹ No completed exam tasks found (all steps open) — this is OK for classes without exam results\n";
        }

        // 2.7 Empty result for non-existent class
        $emptyCollections = $provider2->getExamTasksForClasses([$nonExamClassId]);
        check(isset($emptyCollections[$nonExamClassId]), 'Non-exam class key exists in result');
        check($emptyCollections[$nonExamClassId]->isEmpty(), 'Non-exam class returns empty TaskCollection');

        // 2.8 Batch loading works — multiple classes in one call
        $batchCollections = $provider2->getExamTasksForClasses([$examClassId, $nonExamClassId]);
        check(count($batchCollections) === 2, 'Batch query returns collections for both class IDs');
        check(!$batchCollections[$examClassId]->isEmpty(), 'Batch: exam class has tasks');
        check($batchCollections[$nonExamClassId]->isEmpty(), 'Batch: non-exam class is empty');

        // 2.9 Preload + single-class retrieval
        $provider3 = new ExamTaskProvider();
        $provider3->preloadForClasses([$examClassId]);
        $preloadedCollection = $provider3->getExamTasksForClass($examClassId);
        check(!$preloadedCollection->isEmpty(), 'preloadForClasses + getExamTasksForClass returns tasks');
        check(
            count($preloadedCollection->all()) === count($tasks),
            'Preloaded collection has same task count as direct query'
        );

        // 2.10 No ID collisions with event/agent task ID formats
        $examIds = array_map(fn(Task $t) => $t->getId(), $tasks);
        $noEventCollision = true;
        $noAgentCollision = true;
        foreach ($examIds as $eid) {
            if (str_starts_with($eid, 'event-')) { $noEventCollision = false; }
            if ($eid === 'agent-order') { $noAgentCollision = false; }
        }
        check($noEventCollision, 'No exam task ID collides with event-{N} format');
        check($noAgentCollision, 'No exam task ID collides with agent-order format');
    }
}


// ══════════════════════════════════════════════════════
// SECTION 3: TaskManager Integration (T02)
// ══════════════════════════════════════════════════════
echo "\n--- Section 3: TaskManager Integration ---\n";

// Additional requires for T02
require_once $pluginDir . 'src/Events/Repositories/ClassTaskRepository.php';
require_once $pluginDir . 'src/Events/Services/TaskManager.php';
require_once $pluginDir . 'src/Events/Services/ClassTaskService.php';

use WeCoza\Events\Services\TaskManager;
use WeCoza\Events\Services\ClassTaskService;
use WeCoza\Learners\Services\ExamService;

// 3.1 TaskManager constructor accepts optional ExamTaskProvider and ExamService
$examProvider = new ExamTaskProvider();
$examService = new ExamService();
$tm = new TaskManager($examProvider, $examService);
check($tm instanceof TaskManager, 'TaskManager instantiates with ExamTaskProvider and ExamService');

// 3.2 getExamTaskProvider returns the injected provider
check($tm->getExamTaskProvider() === $examProvider, 'getExamTaskProvider returns injected provider');

// 3.3 TaskManager without exam dependencies still works (null-coalescing pattern)
$tmPlain = new TaskManager();
check($tmPlain instanceof TaskManager, 'TaskManager instantiates without exam dependencies');
check($tmPlain->getExamTaskProvider() === null, 'getExamTaskProvider returns null when not injected');

// 3.4 buildTasksFromEvents — non-exam class excludes exam tasks
$nonExamClass = [
    'class_id' => 1,
    'order_nr' => null,
    'order_nr_metadata' => null,
    'event_dates' => json_encode([
        ['type' => 'Test Event', 'description' => 'desc', 'date' => '2026-01-01', 'status' => 'Pending']
    ]),
    'class_status' => 'active',
    'exam_class' => false,
];
$nonExamTasks = $tm->buildTasksFromEvents($nonExamClass);
$nonExamIds = array_map(fn(Task $t) => $t->getId(), $nonExamTasks->all());
$hasExamInNonExam = false;
foreach ($nonExamIds as $id) {
    if (str_starts_with($id, 'exam-')) { $hasExamInNonExam = true; break; }
}
check(!$hasExamInNonExam, 'buildTasksFromEvents excludes exam tasks for non-exam classes');
check(in_array('agent-order', $nonExamIds, true), 'Non-exam class still has agent-order task');
check(in_array('event-0', $nonExamIds, true), 'Non-exam class still has event tasks');

// 3.5 buildTasksFromEvents — exam class includes exam tasks (DB-dependent)
if ($tablesExist && $examClassId !== null) {
    // Preload exam data for the test class
    $provider4 = new ExamTaskProvider();
    $provider4->preloadForClasses([$examClassId]);
    $tm2 = new TaskManager($provider4, $examService);

    // Build a minimal exam class row
    $examClass = [
        'class_id' => $examClassId,
        'order_nr' => null,
        'order_nr_metadata' => null,
        'event_dates' => null,
        'class_status' => 'active',
        'exam_class' => true,
    ];
    $examTasks = $tm2->buildTasksFromEvents($examClass);
    $examTaskIds = array_map(fn(Task $t) => $t->getId(), $examTasks->all());
    $hasExamTasks = false;
    foreach ($examTaskIds as $id) {
        if (str_starts_with($id, 'exam-')) { $hasExamTasks = true; break; }
    }
    check($hasExamTasks, 'buildTasksFromEvents includes exam tasks for exam classes (class_id=' . $examClassId . ', total tasks=' . count($examTaskIds) . ')');
    check(in_array('agent-order', $examTaskIds, true), 'Exam class still has agent-order task alongside exam tasks');
} else {
    skip('buildTasksFromEvents includes exam tasks for exam classes', 'No DB or exam class available');
    skip('Exam class still has agent-order task alongside exam tasks', 'No DB or exam class available');
}

// 3.6 isExamTask routing — markTaskCompleted throws for invalid exam IDs
$caughtInvalidExam = false;
try {
    $tm->markTaskCompleted(1, 'exam-invalid', 1, '2026-01-01 00:00:00');
} catch (RuntimeException $e) {
    $caughtInvalidExam = str_contains($e->getMessage(), 'Invalid exam task ID format');
}
check($caughtInvalidExam, 'markTaskCompleted throws RuntimeException for invalid exam task ID');

// 3.7 reopenTask throws for invalid exam IDs
$caughtInvalidReopen = false;
try {
    $tm->reopenTask(1, 'exam-bad-format');
} catch (RuntimeException $e) {
    $caughtInvalidReopen = str_contains($e->getMessage(), 'Invalid exam task ID format');
}
check($caughtInvalidReopen, 'reopenTask throws RuntimeException for invalid exam task ID');


// ══════════════════════════════════════════════════════
// SECTION 4: ClassTaskPresenter Checks (T03)
// ══════════════════════════════════════════════════════
echo "\n--- Section 4: ClassTaskPresenter Checks ---\n";

require_once $pluginDir . 'src/Events/Views/Presenters/ClassTaskPresenter.php';

use WeCoza\Events\Views\Presenters\ClassTaskPresenter;

$presenter = new ClassTaskPresenter();

// 4.1 Exam open task — no note fields, has complete_label
$examOpenTask = new Task(
    id: 'exam-42-mock_1',
    label: 'Mock Exam 1: John Doe',
    status: Task::STATUS_OPEN
);
$examOpenCollection = new TaskCollection([$examOpenTask]);
$examOpenResult = $presenter->presentTasks($examOpenCollection);

check(count($examOpenResult['open']) === 1, 'Exam open task appears in open list');
check(count($examOpenResult['completed']) === 0, 'Exam open task not in completed list');

$examOpenPayload = $examOpenResult['open'][0];
check($examOpenPayload['id'] === 'exam-42-mock_1', 'Exam open task has correct id');
check($examOpenPayload['label'] === 'Mock Exam 1: John Doe', 'Exam open task has correct label with learner name');
check(isset($examOpenPayload['complete_label']), 'Exam open task has complete_label');
check($examOpenPayload['note_required'] === false, 'Exam open task has note_required=false');
check(!empty($examOpenPayload['hide_note']), 'Exam open task has hide_note=true');
check(!isset($examOpenPayload['note_label']), 'Exam open task does NOT have note_label');
check(!isset($examOpenPayload['note_placeholder']), 'Exam open task does NOT have note_placeholder');
check(!isset($examOpenPayload['note_required_message']), 'Exam open task does NOT have note_required_message');

// 4.2 Exam completed task — has reopen_label, completed_by, completed_at
$examCompletedTask = new Task(
    id: 'exam-42-final',
    label: 'Final Exam: John Doe',
    status: Task::STATUS_COMPLETED,
    completedBy: 1,
    completedAt: '2026-03-10 12:00:00',
    note: null
);
$examCompletedCollection = new TaskCollection([$examCompletedTask]);
$examCompletedResult = $presenter->presentTasks($examCompletedCollection);

check(count($examCompletedResult['completed']) === 1, 'Exam completed task appears in completed list');
$examCompletedPayload = $examCompletedResult['completed'][0];
check($examCompletedPayload['id'] === 'exam-42-final', 'Exam completed task has correct id');
check($examCompletedPayload['label'] === 'Final Exam: John Doe', 'Exam completed task has correct label');
check(isset($examCompletedPayload['reopen_label']), 'Exam completed task has reopen_label');
check(isset($examCompletedPayload['completed_by']), 'Exam completed task has completed_by');
check(isset($examCompletedPayload['completed_at']), 'Exam completed task has completed_at');
check(array_key_exists('note', $examCompletedPayload), 'Exam completed task has note key');

// 4.3 Non-exam open task still has note fields (no regression)
$eventOpenTask = new Task(
    id: 'event-0',
    label: 'Test Event',
    status: Task::STATUS_OPEN
);
$eventOpenCollection = new TaskCollection([$eventOpenTask]);
$eventOpenResult = $presenter->presentTasks($eventOpenCollection);

$eventOpenPayload = $eventOpenResult['open'][0];
check(isset($eventOpenPayload['note_label']), 'Non-exam open task still has note_label');
check(isset($eventOpenPayload['note_placeholder']), 'Non-exam open task still has note_placeholder');
check($eventOpenPayload['note_required'] === false, 'Non-exam open task has note_required=false');
check(!isset($eventOpenPayload['hide_note']), 'Non-exam open task does NOT have hide_note');

// 4.4 Agent-order task still has required note (no regression)
$agentOrderTask = new Task(
    id: 'agent-order',
    label: 'Agent Order',
    status: Task::STATUS_OPEN
);
$agentOrderCollection = new TaskCollection([$agentOrderTask]);
$agentOrderResult = $presenter->presentTasks($agentOrderCollection);

$agentOrderPayload = $agentOrderResult['open'][0];
check($agentOrderPayload['note_required'] === true, 'Agent-order task still has note_required=true');
check(isset($agentOrderPayload['note_required_message']), 'Agent-order task still has note_required_message');
check(isset($agentOrderPayload['note_label']), 'Agent-order task still has note_label');

// 4.5 Mixed collection — event + exam tasks coexist correctly
$mixedTasks = new TaskCollection([
    new Task(id: 'agent-order', label: 'Agent Order', status: Task::STATUS_OPEN),
    new Task(id: 'event-0', label: 'Test Event', status: Task::STATUS_OPEN),
    new Task(id: 'exam-10-mock_1', label: 'Mock Exam 1: Jane Smith', status: Task::STATUS_OPEN),
    new Task(id: 'exam-10-sba', label: 'SBA: Jane Smith', status: Task::STATUS_COMPLETED, completedBy: 1, completedAt: '2026-03-10 10:00:00'),
    new Task(id: 'event-1', label: 'Another Event', status: Task::STATUS_COMPLETED, completedBy: 1, completedAt: '2026-03-09 09:00:00'),
]);
$mixedResult = $presenter->presentTasks($mixedTasks);

check(count($mixedResult['open']) === 3, 'Mixed collection: 3 open tasks (agent-order + event + exam)');
check(count($mixedResult['completed']) === 2, 'Mixed collection: 2 completed tasks (exam + event)');

// Verify exam open has hide_note, event open does not
$mixedOpenIds = array_column($mixedResult['open'], 'id');
$mixedExamOpen = null;
$mixedEventOpen = null;
foreach ($mixedResult['open'] as $t) {
    if (str_starts_with($t['id'], 'exam-')) { $mixedExamOpen = $t; }
    if (str_starts_with($t['id'], 'event-')) { $mixedEventOpen = $t; }
}
check($mixedExamOpen !== null && !empty($mixedExamOpen['hide_note']), 'Mixed: exam open task has hide_note');
check($mixedEventOpen !== null && !isset($mixedEventOpen['hide_note']), 'Mixed: event open task has no hide_note');

// Verify no ID collisions in output
$allIds = array_merge(
    array_column($mixedResult['open'], 'id'),
    array_column($mixedResult['completed'], 'id')
);
check(count($allIds) === count(array_unique($allIds)), 'Mixed: no ID collisions in presenter output');


// ══════════════════════════════════════════════════════
// Summary
// ══════════════════════════════════════════════════════
echo "\n=== Results: {$pass} passed, {$fail} failed, {$skip} skipped ===\n";
exit($fail > 0 ? 1 : 0);
