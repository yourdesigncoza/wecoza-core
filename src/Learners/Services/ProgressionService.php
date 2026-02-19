<?php
declare(strict_types=1);

/**
 * WeCoza Core - Progression Service
 *
 * Business logic service for learner LP progression tracking.
 * Handles complex operations like LP assignment, completion, hours calculation.
 *
 * @package WeCoza\Learners\Services
 * @since 1.0.0
 */

namespace WeCoza\Learners\Services;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Learners\Models\LearnerProgressionModel;
use WeCoza\Learners\Repositories\LearnerProgressionRepository;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ProgressionService
{
    private LearnerProgressionRepository $repository;

    public function __construct()
    {
        $this->repository = new LearnerProgressionRepository();
    }

    /**
     * Start a new LP for a learner
     *
     * @param int $learnerId
     * @param int $classTypeSubjectId
     * @param int|null $classId
     * @param string|null $notes
     * @param bool $forceOverride If true, puts existing LP on hold instead of throwing
     * @return LearnerProgressionModel
     * @throws Exception if learner already has an in-progress LP and $forceOverride is false
     */
    public function startLearnerProgression(int $learnerId, int $classTypeSubjectId, ?int $classId = null, ?string $notes = null, bool $forceOverride = false): LearnerProgressionModel
    {
        $current = LearnerProgressionModel::getCurrentForLearner($learnerId);

        if ($current) {
            if (!$forceOverride) {
                throw new Exception("Learner already has an in-progress LP: " . $current->getSubjectName());
            }

            // Put current LP on hold
            $holdNotes = "Put on hold - learner assigned to new class. " . current_time('mysql');
            $current->putOnHold($holdNotes);
        }

        $progression = new LearnerProgressionModel([
            'learner_id' => $learnerId,
            'class_type_subject_id' => $classTypeSubjectId,
            'class_id' => $classId,
            'status' => 'in_progress',
            'start_date' => wp_date('Y-m-d'),
            'notes' => $notes,
        ]);

        if (!$progression->save()) {
            throw new Exception("Failed to create progression record");
        }

        return $progression;
    }

    /**
     * Check if learner has an active LP and return details
     *
     * Used for collision detection before assignment.
     *
     * @param int $learnerId
     * @return array|null Returns active LP info or null if none
     */
    public function checkForActiveLPCollision(int $learnerId): ?array
    {
        $current = LearnerProgressionModel::getCurrentForLearner($learnerId);

        if (!$current) {
            return null;
        }

        return [
            'has_collision' => true,
            'tracking_id' => $current->getTrackingId(),
            'class_type_subject_id' => $current->getClassTypeSubjectId(),
            'subject_name' => $current->getSubjectName(),
            'class_id' => $current->getClassId(),
            'class_code' => $current->getClassCode(),
            'progress_percentage' => $current->getProgressPercentage(),
            'hours_present' => $current->getHoursPresent(),
            'hours_trained' => $current->getHoursTrained(),
            'start_date' => $current->getStartDate(),
        ];
    }

    /**
     * Create LP for class assignment (handles collision gracefully)
     *
     * Returns a result array instead of throwing exceptions.
     *
     * @param int $learnerId
     * @param int $classTypeSubjectId
     * @param int $classId
     * @param bool $forceOverride
     * @return array Result with 'success', 'progression', 'warning', 'collision_data'
     */
    public function createLPForClassAssignment(int $learnerId, int $classTypeSubjectId, int $classId, bool $forceOverride = false): array
    {
        $collision = $this->checkForActiveLPCollision($learnerId);

        // If collision exists and no override, return warning
        if ($collision && !$forceOverride) {
            return [
                'success' => false,
                'warning' => true,
                'message' => "Learner has an active LP: " . $collision['subject_name'],
                'collision_data' => $collision,
                'progression' => null,
            ];
        }

        try {
            $notes = "Auto-created on class assignment. Class ID: {$classId}";
            if ($collision && $forceOverride) {
                $notes .= " (Previous LP put on hold: " . $collision['subject_name'] . ")";
            }

            $progression = $this->startLearnerProgression($learnerId, $classTypeSubjectId, $classId, $notes, $forceOverride);

            return [
                'success' => true,
                'warning' => false,
                'message' => 'LP created successfully',
                'collision_data' => $collision,
                'progression' => $progression,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'warning' => false,
                'message' => 'Failed to create LP: ' . $e->getMessage(),
                'collision_data' => $collision,
                'progression' => null,
            ];
        }
    }

    /**
     * Assign LP to late starter (manual assignment)
     */
    public function assignLPToLateStarter(int $learnerId, int $classTypeSubjectId, int $classId, ?string $notes = null): LearnerProgressionModel
    {
        $noteText = "Late starter - manually assigned. " . ($notes ?? '');
        return $this->startLearnerProgression($learnerId, $classTypeSubjectId, $classId, trim($noteText));
    }

    /**
     * Mark LP as complete with portfolio upload
     *
     * @throws Exception if LP not found or already completed
     */
    public function markLPComplete(int $trackingId, int $completedBy, ?array $portfolioFile = null): LearnerProgressionModel
    {
        $progression = LearnerProgressionModel::getById($trackingId);
        if (!$progression) {
            throw new Exception("Progression not found");
        }

        if ($progression->isCompleted()) {
            throw new Exception("LP is already marked as complete");
        }

        $portfolioPath = null;
        if ($portfolioFile && !empty($portfolioFile['tmp_name'])) {
            $uploadService = new PortfolioUploadService();
            $result = $uploadService->uploadProgressionPortfolio($trackingId, $portfolioFile, $completedBy);

            if (!$result['success']) {
                throw new Exception("Portfolio upload failed: " . $result['message']);
            }
            $portfolioPath = $result['file_path'];
        }

        if (!$progression->markComplete($completedBy, $portfolioPath)) {
            throw new Exception("Failed to mark LP as complete");
        }

        $this->recordProgression($progression);

        return $progression;
    }

    /**
     * Record progression in the legacy learner_progressions table
     */
    private function recordProgression(LearnerProgressionModel $progression): void
    {
        $db = wecoza_db();

        $sql = "
            INSERT INTO learner_progressions
            (learner_id, from_subject_id, to_subject_id, progression_date, notes)
            VALUES (:learner_id, :subject_id, :subject_id, :date, :notes)
        ";

        try {
            $db->query($sql, [
                'learner_id' => $progression->getLearnerId(),
                'subject_id' => $progression->getClassTypeSubjectId(),
                'date' => wp_date('Y-m-d'),
                'notes' => 'LP completed: ' . ($progression->getNotes() ?? '')
            ]);
        } catch (Exception $e) {
            error_log("WeCoza Core: Error recording progression: " . $e->getMessage());
        }
    }

    /**
     * Log hours for a learner's current LP
     */
    public function logHours(int $learnerId, float $hoursTrained, float $hoursPresent, string $source = 'manual', ?string $notes = null): bool
    {
        $progression = LearnerProgressionModel::getCurrentForLearner($learnerId);
        if (!$progression) {
            throw new Exception("Learner has no in-progress LP");
        }

        return $progression->addHours($hoursTrained, $hoursPresent, $source, $notes);
    }

    /**
     * Recalculate hours for a progression from logs
     */
    public function recalculateHours(int $trackingId): bool
    {
        $progression = LearnerProgressionModel::getById($trackingId);
        if (!$progression) {
            return false;
        }

        $logs = $this->repository->getHoursLog($trackingId);

        $totalTrained = 0;
        $totalPresent = 0;

        foreach ($logs as $log) {
            $totalTrained += (float) $log['hours_trained'];
            $totalPresent += (float) $log['hours_present'];
        }

        $progression->setHoursTrained($totalTrained);
        $progression->setHoursPresent($totalPresent);

        return $progression->update();
    }

    /**
     * Get learner's overall progress across all LPs
     */
    public function getLearnerOverallProgress(int $learnerId): array
    {
        $progressions = LearnerProgressionModel::getAllForLearner($learnerId);

        $totalEnrolledHours = 0;
        $totalCompletedHours = 0;
        $completedLPs = 0;
        $totalLPs = count($progressions);

        foreach ($progressions as $progression) {
            $duration = $progression->getSubjectDuration() ?? 0;
            $totalEnrolledHours += $duration;

            if ($progression->isCompleted()) {
                $totalCompletedHours += $duration;
                $completedLPs++;
            } else {
                $totalCompletedHours += $progression->getHoursPresent();
            }
        }

        $overallPercentage = $totalEnrolledHours > 0
            ? round(($totalCompletedHours / $totalEnrolledHours) * 100, 1)
            : 0;

        return [
            'total_lps' => $totalLPs,
            'completed_lps' => $completedLPs,
            'in_progress_lps' => $totalLPs - $completedLPs,
            'total_enrolled_hours' => $totalEnrolledHours,
            'total_completed_hours' => $totalCompletedHours,
            'overall_percentage' => $overallPercentage,
        ];
    }

    /**
     * Get current LP details for a learner (for display)
     */
    public function getCurrentLPDetails(int $learnerId): ?array
    {
        $progression = LearnerProgressionModel::getCurrentForLearner($learnerId);
        if (!$progression) {
            return null;
        }

        return [
            'tracking_id' => $progression->getTrackingId(),
            'subject_name' => $progression->getSubjectName(),
            'subject_duration' => $progression->getSubjectDuration(),
            'hours_trained' => $progression->getHoursTrained(),
            'hours_present' => $progression->getHoursPresent(),
            'hours_absent' => $progression->getHoursAbsent(),
            'progress_percentage' => $progression->getProgressPercentage(),
            'start_date' => $progression->getStartDate(),
            'class_code' => $progression->getClassCode(),
            'is_hours_complete' => $progression->isHoursComplete(),
        ];
    }

    /**
     * Get progression history for a learner
     */
    public function getProgressionHistory(int $learnerId): array
    {
        $progressions = LearnerProgressionModel::getHistoryForLearner($learnerId);

        return array_map(function($p) {
            return [
                'tracking_id' => $p->getTrackingId(),
                'subject_name' => $p->getSubjectName(),
                'start_date' => $p->getStartDate(),
                'completion_date' => $p->getCompletionDate(),
                'hours_present' => $p->getHoursPresent(),
                'subject_duration' => $p->getSubjectDuration(),
            ];
        }, $progressions);
    }

    /**
     * Get monthly progressions report
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        return $this->repository->getMonthlyProgressions($year, $month);
    }

    /**
     * Get progressions for admin panel with filters
     */
    public function getProgressionsForAdmin(array $filters = [], int $limit = AppConstants::DEFAULT_PAGE_SIZE, int $offset = 0): array
    {
        $data = $this->repository->findWithFilters($filters, $limit, $offset);
        $total = $this->repository->countWithFilters($filters);

        return [
            'data' => $data,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get summary stats for a class
     */
    public function getClassProgressionStats(int $classId): array
    {
        $progressions = LearnerProgressionModel::getByClass($classId);

        $stats = [
            'total_learners' => count($progressions),
            'in_progress' => 0,
            'completed' => 0,
            'on_hold' => 0,
            'average_progress' => 0,
        ];

        $totalProgress = 0;
        foreach ($progressions as $p) {
            switch ($p->getStatus()) {
                case 'in_progress':
                    $stats['in_progress']++;
                    break;
                case 'completed':
                    $stats['completed']++;
                    break;
                case 'on_hold':
                    $stats['on_hold']++;
                    break;
            }
            $totalProgress += $p->getProgressPercentage();
        }

        if ($stats['total_learners'] > 0) {
            $stats['average_progress'] = round($totalProgress / $stats['total_learners'], 1);
        }

        return $stats;
    }
}
