<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

use Exception;
use PDO;
use WeCoza\Core\Database\PostgresConnection;
use WeCoza\Events\Models\Task;
use WeCoza\Events\Models\TaskCollection;
use WeCoza\Learners\Enums\ExamStep;
use WeCoza\Learners\Repositories\ExamRepository;

/**
 * Generates virtual Task objects from learner exam data.
 *
 * For each learner-LP-tracking in an exam class, generates up to 5 Task objects
 * (one per ExamStep). Uses a single batch query for all class IDs to avoid N+1.
 *
 * Task ID format: exam-{tracking_id}-{step_value}
 * Task label format: "{ExamStep.label()}: {first_name} {surname}"
 *
 * @package WeCoza\Events\Services
 * @since 1.3.0
 */
final class ExamTaskProvider
{
    private PostgresConnection $db;
    private ExamRepository $examRepository;

    /** @var array<int, TaskCollection> Cached results keyed by class_id */
    private array $cache = [];

    /** @var bool Whether preload has been called */
    private bool $preloaded = false;

    public function __construct(?ExamRepository $examRepository = null)
    {
        $this->db = PostgresConnection::getInstance();
        $this->examRepository = $examRepository ?? new ExamRepository();
    }

    /**
     * Generate exam Task objects for multiple classes in a single batch query.
     *
     * Returns an array of TaskCollections keyed by class_id. Each collection
     * contains up to 5 tasks per learner (one per ExamStep). Completed steps
     * have status=completed with completedBy/completedAt from the result row.
     *
     * @param array<int> $classIds Class IDs to generate tasks for (caller pre-filters to exam classes)
     * @return array<int, TaskCollection> Keyed by class_id
     */
    public function getExamTasksForClasses(array $classIds): array
    {
        if (empty($classIds)) {
            return [];
        }

        // Sanitize to integers
        $classIds = array_values(array_unique(array_map('intval', $classIds)));

        try {
            $rows = $this->fetchBatchData($classIds);
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamTaskProvider::getExamTasksForClasses - Query failed for class_ids=[" . implode(',', $classIds) . "]: " . $e->getMessage());
            // Return empty collections for all requested IDs
            $result = [];
            foreach ($classIds as $cid) {
                $result[$cid] = new TaskCollection();
            }
            return $result;
        }

        return $this->buildTaskCollections($classIds, $rows);
    }

    /**
     * Preload exam tasks for a set of class IDs into the internal cache.
     *
     * Call this once with all displayed class IDs, then use getExamTasksForClass()
     * to retrieve per-class results without additional queries.
     *
     * @param array<int> $classIds Class IDs to preload
     */
    public function preloadForClasses(array $classIds): void
    {
        if (empty($classIds)) {
            $this->preloaded = true;
            return;
        }

        $collections = $this->getExamTasksForClasses($classIds);
        foreach ($collections as $classId => $collection) {
            $this->cache[$classId] = $collection;
        }
        $this->preloaded = true;
    }

    /**
     * Get exam tasks for a single class from cache.
     *
     * Returns an empty TaskCollection if the class was not preloaded or has no exam learners.
     *
     * @param int $classId
     * @return TaskCollection
     */
    public function getExamTasksForClass(int $classId): TaskCollection
    {
        return $this->cache[$classId] ?? new TaskCollection();
    }

    /**
     * Parse an exam task ID into its components.
     *
     * @param string $taskId e.g. "exam-42-mock_1"
     * @return array{tracking_id: int, step: ExamStep}|null Null if format doesn't match
     */
    public static function parseExamTaskId(string $taskId): ?array
    {
        // Format: exam-{tracking_id}-{step_value}
        // tracking_id is a positive integer, step_value is one of ExamStep values
        if (!preg_match('/^exam-(\d+)-(.+)$/', $taskId, $matches)) {
            return null;
        }

        $trackingId = (int) $matches[1];
        if ($trackingId <= 0) {
            return null;
        }

        $step = ExamStep::tryFrom($matches[2]);
        if ($step === null) {
            return null;
        }

        return [
            'tracking_id' => $trackingId,
            'step' => $step,
        ];
    }

    /**
     * Delete an exam result for a specific tracking and step.
     *
     * Delegates to ExamRepository. Used by TaskManager::reopenTask().
     *
     * @param int $trackingId LP tracking ID
     * @param ExamStep $step Exam step
     * @return bool True if a row was deleted, false otherwise
     */
    public function deleteExamResult(int $trackingId, ExamStep $step): bool
    {
        try {
            $result = $this->examRepository->deleteByTrackingAndStep($trackingId, $step);

            // Invalidate any cached collection containing this tracking's class
            // (simple approach: clear entire cache so next preload fetches fresh data)
            $this->cache = [];
            $this->preloaded = false;

            return $result;
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamTaskProvider::deleteExamResult - Failed for tracking_id={$trackingId}, step={$step->value}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a task ID is an exam task.
     *
     * @param string $taskId
     * @return bool
     */
    public static function isExamTaskId(string $taskId): bool
    {
        return str_starts_with($taskId, 'exam-') && self::parseExamTaskId($taskId) !== null;
    }

    // ──────────────────────────────────────────────
    // Private Methods
    // ──────────────────────────────────────────────

    /**
     * Run the single batch query joining tracking → learners → exam results.
     *
     * @param array<int> $classIds Already sanitized integer class IDs
     * @return array Raw rows from the query
     */
    private function fetchBatchData(array $classIds): array
    {
        // Build positional placeholders for the IN clause
        $placeholders = [];
        $params = [];
        foreach ($classIds as $i => $cid) {
            $key = ":cid_{$i}";
            $placeholders[] = $key;
            $params[$key] = $cid;
        }

        $inClause = implode(', ', $placeholders);

        // Single batch query:
        // - Get all learner-tracking rows for the requested classes
        // - LEFT JOIN exam results so we see completed AND open steps
        // - Join learners for name display
        $sql = "
            SELECT
                t.tracking_id,
                t.class_id,
                t.learner_id,
                l.first_name,
                l.surname,
                r.exam_step,
                r.percentage,
                r.recorded_by,
                r.recorded_at,
                r.result_id
            FROM learner_lp_tracking t
            INNER JOIN learners l ON l.id = t.learner_id
            LEFT JOIN learner_exam_results r ON r.tracking_id = t.tracking_id
            WHERE t.class_id IN ({$inClause})
            ORDER BY t.class_id, l.surname, l.first_name, t.tracking_id
        ";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Build TaskCollections from raw batch query rows.
     *
     * Groups by class_id → tracking_id, then generates up to 5 Task objects per tracking.
     *
     * @param array<int> $classIds All requested class IDs (to ensure empty collections for classes with no learners)
     * @param array $rows Raw query rows
     * @return array<int, TaskCollection>
     */
    private function buildTaskCollections(array $classIds, array $rows): array
    {
        // Initialize empty collections for all requested classes
        $result = [];
        foreach ($classIds as $cid) {
            $result[$cid] = new TaskCollection();
        }

        // Group rows by class_id → tracking_id
        // Each tracking may have 0-5 result rows (from the LEFT JOIN)
        $grouped = []; // [class_id][tracking_id] => ['first_name' => ..., 'surname' => ..., 'results' => [step => row]]
        foreach ($rows as $row) {
            $classId = (int) $row['class_id'];
            $trackingId = (int) $row['tracking_id'];

            if (!isset($grouped[$classId][$trackingId])) {
                $grouped[$classId][$trackingId] = [
                    'first_name' => $row['first_name'] ?? '',
                    'surname' => $row['surname'] ?? '',
                    'results' => [],
                ];
            }

            // If there's an exam result row (LEFT JOIN may produce null exam_step)
            if (!empty($row['exam_step'])) {
                $grouped[$classId][$trackingId]['results'][$row['exam_step']] = [
                    'recorded_by' => $row['recorded_by'] !== null ? (int) $row['recorded_by'] : null,
                    'recorded_at' => $row['recorded_at'],
                    'percentage' => $row['percentage'],
                    'result_id' => $row['result_id'],
                ];
            }
        }

        // Generate Task objects for each tracking
        foreach ($grouped as $classId => $trackings) {
            foreach ($trackings as $trackingId => $data) {
                $firstName = $data['first_name'];
                $surname = $data['surname'];
                $completedResults = $data['results'];

                foreach (ExamStep::cases() as $step) {
                    $taskId = "exam-{$trackingId}-{$step->value}";
                    $label = "{$step->label()}: {$firstName} {$surname}";

                    $resultRow = $completedResults[$step->value] ?? null;

                    if ($resultRow !== null) {
                        // Completed task
                        $task = new Task(
                            id: $taskId,
                            label: $label,
                            status: Task::STATUS_COMPLETED,
                            completedBy: $resultRow['recorded_by'],
                            completedAt: $resultRow['recorded_at'],
                        );
                    } else {
                        // Open task
                        $task = new Task(
                            id: $taskId,
                            label: $label,
                            status: Task::STATUS_OPEN,
                        );
                    }

                    $result[$classId]->add($task);
                }
            }
        }

        return $result;
    }
}
