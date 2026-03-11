<?php
declare(strict_types=1);

/**
 * WeCoza Core - Exam Service
 *
 * Business logic layer for learner exam results.
 * Orchestrates ExamRepository and ExamUploadService to provide
 * validation, recording, progress tracking, and completion checking.
 *
 * @package WeCoza\Learners\Services
 * @since 1.2.0
 */

namespace WeCoza\Learners\Services;

use WeCoza\Learners\Enums\ExamStep;
use WeCoza\Learners\Repositories\ExamRepository;
use Exception;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

class ExamService
{
    private ExamRepository $repository;
    private ExamUploadService $uploadService;

    public function __construct(?ExamRepository $repository = null, ?ExamUploadService $uploadService = null)
    {
        $this->repository    = $repository ?? new ExamRepository();
        $this->uploadService = $uploadService ?? new ExamUploadService();
    }

    /**
     * Record or update an exam result for a learner LP tracking entry.
     *
     * Validates percentage, handles optional file upload for SBA/final steps,
     * and delegates persistence to the repository upsert.
     *
     * @param int       $trackingId  LP tracking ID
     * @param ExamStep  $step        Which exam step
     * @param float     $percentage  Score as percentage (0–100)
     * @param array|null $file       Optional $_FILES element for SBA/final evidence upload
     * @param int|null  $recordedBy  WP user ID of person recording the result
     * @return array{success: bool, data: array, error: string}
     */
    public function recordExamResult(
        int $trackingId,
        ExamStep $step,
        float $percentage,
        ?array $file = null,
        ?int $recordedBy = null
    ): array {
        // Validate percentage range
        if ($percentage < 0 || $percentage > 100) {
            error_log("WeCoza Exam: ExamService::recordExamResult - Invalid percentage {$percentage} for tracking_id={$trackingId}, step={$step->value}");
            return [
                'success' => false,
                'data'    => [],
                'error'   => 'Percentage must be between 0 and 100',
            ];
        }

        // Prepare data for upsert
        $data = [
            'percentage'  => $percentage,
            'recorded_by' => $recordedBy,
        ];

        // Handle file upload if provided and step supports it
        if ($file !== null && !empty($file['tmp_name'])) {
            if (!$step->requiresFile()) {
                // File provided for a step that doesn't need one — accept it anyway
                // but log it for awareness
                error_log("WeCoza Exam: ExamService::recordExamResult - File provided for non-file step {$step->value}, tracking_id={$trackingId}. Proceeding anyway.");
            }

            $uploadResult = $this->uploadService->upload($file, $trackingId, $step);

            if (!$uploadResult['success']) {
                error_log("WeCoza Exam: ExamService::recordExamResult - Upload failed for tracking_id={$trackingId}, step={$step->value}: {$uploadResult['error']}");
                return [
                    'success' => false,
                    'data'    => [],
                    'error'   => 'File upload failed: ' . $uploadResult['error'],
                ];
            }

            $data['file_path'] = $uploadResult['file_path'];
            $data['file_name'] = $uploadResult['file_name'];
        }

        // Persist via upsert
        try {
            $resultId = $this->repository->upsert($trackingId, $step, $data);

            if ($resultId === null) {
                error_log("WeCoza Exam: ExamService::recordExamResult - Upsert returned null for tracking_id={$trackingId}, step={$step->value}");
                return [
                    'success' => false,
                    'data'    => [],
                    'error'   => 'Failed to save exam result',
                ];
            }

            return [
                'success' => true,
                'data'    => [
                    'result_id'   => $resultId,
                    'tracking_id' => $trackingId,
                    'exam_step'   => $step->value,
                    'percentage'  => $percentage,
                ],
                'error'   => '',
            ];
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamService::recordExamResult - Exception for tracking_id={$trackingId}, step={$step->value}: " . $e->getMessage());
            return [
                'success' => false,
                'data'    => [],
                'error'   => 'Unexpected error recording exam result',
            ];
        }
    }

    /**
     * Get exam progress for a tracking ID.
     *
     * Returns all 5 exam steps with their result data (or null if not yet recorded),
     * plus an overall completion percentage.
     *
     * @param int $trackingId LP tracking ID
     * @return array{steps: array<string, array|null>, completion_percentage: float, completed_count: int, total_steps: int}
     */
    public function getExamProgress(int $trackingId): array
    {
        $progress = $this->repository->getProgressForTracking($trackingId);

        $completedCount = 0;
        foreach ($progress as $stepData) {
            if ($stepData !== null) {
                $completedCount++;
            }
        }

        $totalSteps = count(ExamStep::cases());

        return [
            'steps'                 => $progress,
            'completion_percentage' => $totalSteps > 0
                ? round(($completedCount / $totalSteps) * 100, 1)
                : 0.0,
            'completed_count'       => $completedCount,
            'total_steps'           => $totalSteps,
        ];
    }

    /**
     * Check if all exam steps are complete for a tracking ID.
     *
     * Complete means: all 5 steps have recorded results AND the final step
     * has an uploaded certificate file.
     *
     * @param int $trackingId LP tracking ID
     * @return bool
     */
    public function isExamComplete(int $trackingId): bool
    {
        $progress = $this->repository->getProgressForTracking($trackingId);

        foreach (ExamStep::cases() as $step) {
            $stepData = $progress[$step->value] ?? null;

            // Every step must have a recorded result
            if ($stepData === null) {
                return false;
            }
        }

        // Final step must have a certificate file
        $finalData = $progress[ExamStep::FINAL->value] ?? null;
        if ($finalData === null || empty($finalData['file_path'])) {
            return false;
        }

        return true;
    }

    /**
     * Delete an exam result for a tracking ID and step.
     *
     * Delegates to ExamRepository::deleteByTrackingAndStep().
     *
     * @param int      $trackingId LP tracking ID
     * @param ExamStep $step       Exam step enum
     * @return array{success: bool, error: string}
     */
    public function deleteExamResult(int $trackingId, ExamStep $step): array
    {
        try {
            $deleted = $this->repository->deleteByTrackingAndStep($trackingId, $step);

            if (!$deleted) {
                return [
                    'success' => false,
                    'error'   => 'No exam result found for this step',
                ];
            }

            return [
                'success' => true,
                'error'   => '',
            ];
        } catch (Exception $e) {
            error_log("WeCoza Exam: ExamService::deleteExamResult - Exception for tracking_id={$trackingId}, step={$step->value}: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'Failed to delete exam result',
            ];
        }
    }

    /**
     * Get raw exam results for a tracking ID from the repository.
     *
     * @param int $trackingId LP tracking ID
     * @return array Exam result rows ordered by step
     */
    public function getExamResultsForTracking(int $trackingId): array
    {
        return $this->repository->findByTrackingId($trackingId);
    }
}
