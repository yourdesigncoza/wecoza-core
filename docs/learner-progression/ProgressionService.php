<?php
/**
 * ProgressionService.php
 *
 * Business logic service for learner LP progression tracking
 * Handles complex operations like LP assignment, completion, hours calculation
 *
 * @package WeCoza
 * @subpackage Services
 * @since 1.0.0
 */

namespace WeCoza\Services;

use WeCoza\Models\Learner\LearnerProgressionModel;
use WeCoza\Repositories\LearnerProgressionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ProgressionService {
    private LearnerProgressionRepository $repository;

    public function __construct() {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'repositories/LearnerProgressionRepository.php';
        $this->repository = new LearnerProgressionRepository();
    }

    /**
     * Start a new LP for a learner
     *
     * @throws \Exception if learner already has an in-progress LP
     */
    public function startLearnerProgression(int $learnerId, int $productId, ?int $classId = null, ?string $notes = null): LearnerProgressionModel {
        // Check if learner already has an in-progress LP
        $current = LearnerProgressionModel::getCurrentForLearner($learnerId);
        if ($current) {
            throw new \Exception("Learner already has an in-progress LP: " . $current->getProductName());
        }

        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

        $progression = new LearnerProgressionModel([
            'learner_id' => $learnerId,
            'product_id' => $productId,
            'class_id' => $classId,
            'status' => 'in_progress',
            'start_date' => date('Y-m-d'),
            'notes' => $notes,
        ]);

        if (!$progression->save()) {
            throw new \Exception("Failed to create progression record");
        }

        return $progression;
    }

    /**
     * Assign LP to late starter (manual assignment)
     */
    public function assignLPToLateStarter(int $learnerId, int $productId, int $classId, ?string $notes = null): LearnerProgressionModel {
        $noteText = "Late starter - manually assigned. " . ($notes ?? '');
        return $this->startLearnerProgression($learnerId, $productId, $classId, trim($noteText));
    }

    /**
     * Mark LP as complete with portfolio upload
     *
     * @throws \Exception if LP not found or already completed
     */
    public function markLPComplete(int $trackingId, int $completedBy, ?array $portfolioFile = null): LearnerProgressionModel {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

        $progression = LearnerProgressionModel::getById($trackingId);
        if (!$progression) {
            throw new \Exception("Progression not found");
        }

        if ($progression->isCompleted()) {
            throw new \Exception("LP is already marked as complete");
        }

        // Handle portfolio upload if provided
        $portfolioPath = null;
        if ($portfolioFile && !empty($portfolioFile['tmp_name'])) {
            require_once WECOZA_LEARNERS_PLUGIN_DIR . 'services/PortfolioUploadService.php';
            $uploadService = new PortfolioUploadService();
            $result = $uploadService->uploadProgressionPortfolio($trackingId, $portfolioFile, $completedBy);

            if (!$result['success']) {
                throw new \Exception("Portfolio upload failed: " . $result['message']);
            }
            $portfolioPath = $result['file_path'];
        }

        // Mark complete
        if (!$progression->markComplete($completedBy, $portfolioPath)) {
            throw new \Exception("Failed to mark LP as complete");
        }

        // Record in learner_progressions table for history
        $this->recordProgression($progression);

        return $progression;
    }

    /**
     * Record progression in the legacy learner_progressions table
     */
    private function recordProgression(LearnerProgressionModel $progression): void {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'database/WeCozaLearnersDB.php';
        $db = \WeCozaLearnersDB::getInstance();

        $sql = "
            INSERT INTO learner_progressions
            (learner_id, from_product_id, to_product_id, progression_date, notes)
            VALUES (:learner_id, :product_id, :product_id, :date, :notes)
        ";

        try {
            $db->query($sql, [
                'learner_id' => $progression->getLearnerId(),
                'product_id' => $progression->getProductId(),
                'date' => date('Y-m-d'),
                'notes' => 'LP completed: ' . ($progression->getNotes() ?? '')
            ]);
        } catch (\Exception $e) {
            error_log("Error recording progression: " . $e->getMessage());
        }
    }

    /**
     * Log hours for a learner's current LP
     */
    public function logHours(int $learnerId, float $hoursTrained, float $hoursPresent, string $source = 'manual', ?string $notes = null): bool {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

        $progression = LearnerProgressionModel::getCurrentForLearner($learnerId);
        if (!$progression) {
            throw new \Exception("Learner has no in-progress LP");
        }

        return $progression->addHours($hoursTrained, $hoursPresent, $source, $notes);
    }

    /**
     * Recalculate hours for a progression from logs
     */
    public function recalculateHours(int $trackingId): bool {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

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
    public function getLearnerOverallProgress(int $learnerId): array {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

        $progressions = LearnerProgressionModel::getAllForLearner($learnerId);

        $totalEnrolledHours = 0;
        $totalCompletedHours = 0;
        $completedLPs = 0;
        $totalLPs = count($progressions);

        foreach ($progressions as $progression) {
            $duration = $progression->getProductDuration() ?? 0;
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
    public function getCurrentLPDetails(int $learnerId): ?array {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

        $progression = LearnerProgressionModel::getCurrentForLearner($learnerId);
        if (!$progression) {
            return null;
        }

        return [
            'tracking_id' => $progression->getTrackingId(),
            'product_name' => $progression->getProductName(),
            'product_duration' => $progression->getProductDuration(),
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
    public function getProgressionHistory(int $learnerId): array {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

        $progressions = LearnerProgressionModel::getHistoryForLearner($learnerId);

        return array_map(function($p) {
            return [
                'tracking_id' => $p->getTrackingId(),
                'product_name' => $p->getProductName(),
                'start_date' => $p->getStartDate(),
                'completion_date' => $p->getCompletionDate(),
                'hours_present' => $p->getHoursPresent(),
                'product_duration' => $p->getProductDuration(),
            ];
        }, $progressions);
    }

    /**
     * Get monthly progressions report
     */
    public function getMonthlyReport(int $year, int $month): array {
        return $this->repository->getMonthlyProgressions($year, $month);
    }

    /**
     * Get progressions for admin panel with filters
     */
    public function getProgressionsForAdmin(array $filters = [], int $limit = 50, int $offset = 0): array {
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
     * Sync hours from schedule data
     * This method should be called when schedule data is updated
     */
    public function syncHoursFromSchedule(int $learnerId, int $productId): bool {
        // Get hours from schedule (implementation depends on schedule structure)
        $scheduledHours = $this->repository->calculateHoursFromSchedule($learnerId, $productId);

        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';
        $progression = LearnerProgressionModel::getCurrentForLearner($learnerId);

        if (!$progression || $progression->getProductId() !== $productId) {
            return false;
        }

        $progression->setHoursTrained($scheduledHours);
        return $progression->update();
    }

    /**
     * Sync hours from attendance data
     * This method should be called when attendance is captured
     */
    public function syncHoursFromAttendance(int $learnerId, int $productId): bool {
        // Get hours from attendance
        $attendanceHours = $this->repository->calculateHoursFromAttendance($learnerId, $productId);

        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';
        $progression = LearnerProgressionModel::getCurrentForLearner($learnerId);

        if (!$progression || $progression->getProductId() !== $productId) {
            return false;
        }

        $progression->setHoursPresent($attendanceHours);
        return $progression->update();
    }

    /**
     * Get summary stats for a class
     */
    public function getClassProgressionStats(int $classId): array {
        require_once WECOZA_LEARNERS_PLUGIN_DIR . 'models/LearnerProgressionModel.php';

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
