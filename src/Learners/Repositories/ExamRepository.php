<?php
declare(strict_types=1);

/**
 * WeCoza Core - Exam Repository
 *
 * Data access layer for learner exam results.
 * Handles all database operations on the learner_exam_results table.
 * Supports upsert (INSERT ... ON CONFLICT DO UPDATE) for corrections.
 *
 * @package WeCoza\Learners\Repositories
 * @since 1.2.0
 */

namespace WeCoza\Learners\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Learners\Enums\ExamStep;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ExamRepository extends BaseRepository
{
    // quoteIdentifier: all column names in this repository are hardcoded literals (safe)

    /**
     * Table name
     */
    protected static string $table = 'learner_exam_results';

    /**
     * Primary key column
     */
    protected static string $primaryKey = 'result_id';

    /*
    |--------------------------------------------------------------------------
    | Column Whitelisting (Security)
    |--------------------------------------------------------------------------
    */

    /**
     * Columns allowed for INSERT operations
     */
    protected function getAllowedInsertColumns(): array
    {
        return [
            'tracking_id',
            'exam_step',
            'percentage',
            'file_path',
            'file_name',
            'recorded_by',
            'recorded_at',
            'updated_at',
        ];
    }

    /**
     * Columns allowed for UPDATE operations
     */
    protected function getAllowedUpdateColumns(): array
    {
        return [
            'percentage',
            'file_path',
            'file_name',
            'recorded_by',
            'updated_at',
        ];
    }

    /**
     * Columns allowed for WHERE clause filtering
     */
    protected function getAllowedFilterColumns(): array
    {
        return [
            'result_id',
            'tracking_id',
            'exam_step',
            'recorded_by',
            'recorded_at',
            'updated_at',
        ];
    }

    /**
     * Columns allowed for ORDER BY clauses
     */
    protected function getAllowedOrderColumns(): array
    {
        return [
            'result_id',
            'tracking_id',
            'exam_step',
            'percentage',
            'recorded_at',
            'updated_at',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Query Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Find all exam results for a tracking ID
     *
     * @param int $trackingId LP tracking ID
     * @return array Exam result rows ordered by exam_step
     */
    public function findByTrackingId(int $trackingId): array
    {
        $sql = "SELECT * FROM learner_exam_results
                WHERE tracking_id = :tracking_id
                ORDER BY CASE exam_step
                    WHEN 'mock_1' THEN 1
                    WHEN 'mock_2' THEN 2
                    WHEN 'mock_3' THEN 3
                    WHEN 'sba'    THEN 4
                    WHEN 'final'  THEN 5
                END";

        try {
            $stmt = $this->db->query($sql, ['tracking_id' => $trackingId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamRepository::findByTrackingId - Error for tracking_id={$trackingId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a specific exam result by tracking ID and step
     *
     * @param int $trackingId LP tracking ID
     * @param ExamStep $step Exam step enum
     * @return array|null Result row or null if not found
     */
    public function findByTrackingAndStep(int $trackingId, ExamStep $step): ?array
    {
        $sql = "SELECT * FROM learner_exam_results
                WHERE tracking_id = :tracking_id AND exam_step = :exam_step
                LIMIT 1";

        try {
            $stmt = $this->db->query($sql, [
                'tracking_id' => $trackingId,
                'exam_step'   => $step->value,
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamRepository::findByTrackingAndStep - Error for tracking_id={$trackingId}, step={$step->value}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Insert or update an exam result.
     *
     * Uses PostgreSQL INSERT ... ON CONFLICT (tracking_id, exam_step) DO UPDATE
     * so office staff can correct previously recorded results.
     *
     * @param int $trackingId LP tracking ID
     * @param ExamStep $step Exam step enum
     * @param array $data Associative array with optional keys: percentage, file_path, file_name, recorded_by
     * @return int|null The result_id on success, null on failure
     */
    public function upsert(int $trackingId, ExamStep $step, array $data): ?int
    {
        // Whitelist the incoming data to updatable columns only
        $allowedData = $this->filterAllowedColumns($data, $this->getAllowedUpdateColumns());

        $now = current_time('mysql');

        // Build the full insert payload
        $insertData = array_merge($allowedData, [
            'tracking_id' => $trackingId,
            'exam_step'   => $step->value,
            'recorded_at' => $now,
            'updated_at'  => $now,
        ]);

        // Build column/placeholder lists for INSERT
        $columns      = array_keys($insertData);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        // Build SET clause for ON CONFLICT UPDATE (exclude tracking_id, exam_step, recorded_at)
        $updateExclude = ['tracking_id', 'exam_step', 'recorded_at'];
        $updateSets    = [];
        foreach ($columns as $col) {
            if (!in_array($col, $updateExclude, true)) {
                $updateSets[] = "{$col} = EXCLUDED.{$col}";
            }
        }

        $sql = sprintf(
            "INSERT INTO learner_exam_results (%s) VALUES (%s)
             ON CONFLICT (tracking_id, exam_step) DO UPDATE SET %s
             RETURNING result_id",
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updateSets)
        );

        try {
            $stmt = $this->db->query($sql, $insertData);
            $resultId = $stmt->fetchColumn();
            return $resultId !== false ? (int) $resultId : null;
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamRepository::upsert - Error for tracking_id={$trackingId}, step={$step->value}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get exam progress for a tracking ID.
     *
     * Returns an array keyed by exam_step value with the result row or null
     * for each of the 5 exam steps. Allows the caller to see which steps
     * have been completed and which are still pending.
     *
     * @param int $trackingId LP tracking ID
     * @return array<string, array|null> Keyed by exam_step value
     */
    public function getProgressForTracking(int $trackingId): array
    {
        // Initialize all steps as null
        $progress = [];
        foreach (ExamStep::cases() as $step) {
            $progress[$step->value] = null;
        }

        // Fetch actual results
        $results = $this->findByTrackingId($trackingId);

        // Overlay actual results onto the progress map
        foreach ($results as $row) {
            $stepValue = $row['exam_step'] ?? null;
            if ($stepValue !== null && array_key_exists($stepValue, $progress)) {
                $progress[$stepValue] = $row;
            }
        }

        return $progress;
    }

    /**
     * Delete a specific exam result by tracking ID and step.
     *
     * Used for "reopening" an exam task on the task dashboard.
     *
     * @param int $trackingId LP tracking ID
     * @param ExamStep $step Exam step enum
     * @return bool True if a row was deleted, false if no matching row
     */
    public function deleteByTrackingAndStep(int $trackingId, ExamStep $step): bool
    {
        $sql = "DELETE FROM learner_exam_results
                WHERE tracking_id = :tracking_id AND exam_step = :exam_step";

        try {
            $stmt = $this->db->query($sql, [
                'tracking_id' => $trackingId,
                'exam_step'   => $step->value,
            ]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamRepository::deleteByTrackingAndStep - Error for tracking_id={$trackingId}, step={$step->value}: " . $e->getMessage());
            throw $e;
        }
    }
}
