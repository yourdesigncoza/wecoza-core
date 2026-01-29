<?php
/**
 * WeCoza Core - Learner Progression Model
 *
 * Model for learner LP (Learning Programme) progression tracking.
 * Tracks current LP status, hours (trained/present/absent), and completion.
 *
 * Key constraint: Only ONE 'in_progress' LP per learner at a time.
 *
 * @package WeCoza\Learners\Models
 * @since 1.0.0
 */

namespace WeCoza\Learners\Models;

use WeCoza\Core\Abstract\BaseModel;
use WeCoza\Learners\Repositories\LearnerProgressionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LearnerProgressionModel extends BaseModel
{
    /**
     * Database table name
     */
    protected static string $table = 'learner_lp_tracking';

    /**
     * Primary key column
     */
    protected static string $primaryKey = 'tracking_id';

    /**
     * Type casting definitions
     */
    protected static array $casts = [
        'trackingId' => 'int',
        'learnerId' => 'int',
        'productId' => 'int',
        'classId' => 'int',
        'markedCompleteBy' => 'int',
        'productDuration' => 'int',
        'hoursTrained' => 'float',
        'hoursPresent' => 'float',
        'hoursAbsent' => 'float',
    ];

    // Core tracking fields
    protected ?int $trackingId = null;
    protected int $learnerId = 0;
    protected int $productId = 0;
    protected ?int $classId = null;

    // Hours tracking (three-way)
    protected float $hoursTrained = 0;
    protected float $hoursPresent = 0;
    protected float $hoursAbsent = 0;

    // Status: in_progress, completed, on_hold
    protected string $status = 'in_progress';

    // Date tracking
    protected ?string $startDate = null;
    protected ?string $completionDate = null;

    // Portfolio
    protected ?string $portfolioFilePath = null;
    protected ?string $portfolioUploadedAt = null;

    // Completion tracking
    protected ?int $markedCompleteBy = null;
    protected ?string $markedCompleteDate = null;

    protected ?string $notes = null;
    protected ?string $createdAt = null;
    protected ?string $updatedAt = null;

    // Joined fields (from products table)
    protected ?string $productName = null;
    protected ?int $productDuration = null;

    // Joined fields (from learners table)
    protected ?string $learnerName = null;

    // Joined fields (from classes table)
    protected ?string $classCode = null;

    /**
     * Repository instance (lazy loaded)
     */
    private static ?LearnerProgressionRepository $repository = null;

    /**
     * Get repository instance
     */
    private static function getRepository(): LearnerProgressionRepository
    {
        if (self::$repository === null) {
            self::$repository = new LearnerProgressionRepository();
        }
        return self::$repository;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Query Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get progression by ID
     */
    public static function getById(int $trackingId): ?static
    {
        $data = self::getRepository()->findById($trackingId);
        return $data ? new static($data) : null;
    }

    /**
     * Get current (in_progress) LP for a learner
     */
    public static function getCurrentForLearner(int $learnerId): ?static
    {
        $data = self::getRepository()->findCurrentForLearner($learnerId);
        return $data ? new static($data) : null;
    }

    /**
     * Get all progressions for a learner
     */
    public static function getAllForLearner(int $learnerId): array
    {
        $results = self::getRepository()->findAllForLearner($learnerId);
        return array_map(fn($row) => new static($row), $results);
    }

    /**
     * Get progression history for a learner (completed only)
     */
    public static function getHistoryForLearner(int $learnerId): array
    {
        $results = self::getRepository()->findHistoryForLearner($learnerId);
        return array_map(fn($row) => new static($row), $results);
    }

    /**
     * Get progressions by class
     */
    public static function getByClass(int $classId, ?string $status = null): array
    {
        $results = self::getRepository()->findByClass($classId, $status);
        return array_map(fn($row) => new static($row), $results);
    }

    /**
     * Get progressions by product
     */
    public static function getByProduct(int $productId, ?string $status = null): array
    {
        $results = self::getRepository()->findByProduct($productId, $status);
        return array_map(fn($row) => new static($row), $results);
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate progress percentage
     */
    public function getProgressPercentage(): float
    {
        if (!$this->productDuration || $this->productDuration <= 0) {
            return 0;
        }
        return min(100, round(($this->hoursPresent / $this->productDuration) * 100, 1));
    }

    /**
     * Calculate absent hours (trained - present)
     */
    public function calculateAbsentHours(): float
    {
        return max(0, $this->hoursTrained - $this->hoursPresent);
    }

    /**
     * Check if LP is complete (hours-wise)
     */
    public function isHoursComplete(): bool
    {
        return $this->productDuration && $this->hoursPresent >= $this->productDuration;
    }

    /**
     * Check if LP is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if LP is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if LP is on hold
     */
    public function isOnHold(): bool
    {
        return $this->status === 'on_hold';
    }

    /*
    |--------------------------------------------------------------------------
    | Persistence Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Save new progression
     */
    public function save(): bool
    {
        $this->hoursAbsent = $this->calculateAbsentHours();

        $data = $this->toDbArray();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        unset($data['tracking_id']);

        $newId = self::getRepository()->insert($data);

        if ($newId) {
            $this->trackingId = (int) $newId;
            return true;
        }

        return false;
    }

    /**
     * Update existing progression
     */
    public function update(): bool
    {
        if (!$this->trackingId) {
            return false;
        }

        $this->hoursAbsent = $this->calculateAbsentHours();

        $data = $this->toDbArray();
        $data['updated_at'] = date('Y-m-d H:i:s');

        return self::getRepository()->update($this->trackingId, $data);
    }

    /**
     * Mark LP as complete
     */
    public function markComplete(int $completedBy, ?string $portfolioPath = null): bool
    {
        $this->status = 'completed';
        $this->completionDate = date('Y-m-d');
        $this->markedCompleteBy = $completedBy;
        $this->markedCompleteDate = date('Y-m-d H:i:s');

        if ($portfolioPath) {
            $this->portfolioFilePath = $portfolioPath;
            $this->portfolioUploadedAt = date('Y-m-d H:i:s');
        }

        return $this->update();
    }

    /**
     * Put LP on hold
     */
    public function putOnHold(?string $notes = null): bool
    {
        $this->status = 'on_hold';
        if ($notes) {
            $this->notes = $notes;
        }
        return $this->update();
    }

    /**
     * Resume LP from hold
     */
    public function resume(): bool
    {
        if ($this->status !== 'on_hold') {
            return false;
        }
        $this->status = 'in_progress';
        return $this->update();
    }

    /**
     * Add hours to this progression
     */
    public function addHours(float $trained, float $present, string $source = 'manual', ?string $notes = null): bool
    {
        $this->hoursTrained += $trained;
        $this->hoursPresent += $present;
        $this->hoursAbsent = $this->calculateAbsentHours();

        self::getRepository()->logHours([
            'learner_id' => $this->learnerId,
            'product_id' => $this->productId,
            'class_id' => $this->classId,
            'tracking_id' => $this->trackingId,
            'log_date' => date('Y-m-d'),
            'hours_trained' => $trained,
            'hours_present' => $present,
            'source' => $source,
            'notes' => $notes,
        ]);

        return $this->update();
    }

    /**
     * Delete progression
     */
    public function delete(): bool
    {
        if (!$this->trackingId) {
            return false;
        }
        return self::getRepository()->delete($this->trackingId);
    }

    /*
    |--------------------------------------------------------------------------
    | Conversion Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Convert to database array (snake_case)
     */
    public function toDbArray(bool $includeNull = false): array
    {
        $data = [
            'tracking_id' => $this->trackingId,
            'learner_id' => $this->learnerId,
            'product_id' => $this->productId,
            'class_id' => $this->classId,
            'hours_trained' => $this->hoursTrained,
            'hours_present' => $this->hoursPresent,
            'hours_absent' => $this->hoursAbsent,
            'status' => $this->status,
            'start_date' => $this->startDate,
            'completion_date' => $this->completionDate,
            'portfolio_file_path' => $this->portfolioFilePath,
            'portfolio_uploaded_at' => $this->portfolioUploadedAt,
            'marked_complete_by' => $this->markedCompleteBy,
            'marked_complete_date' => $this->markedCompleteDate,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        if (!$includeNull) {
            $data = array_filter($data, fn($v) => $v !== null);
        }

        return $data;
    }

    /**
     * Convert to array (camelCase, includes computed fields)
     */
    public function toArray(): array
    {
        return [
            'trackingId' => $this->trackingId,
            'learnerId' => $this->learnerId,
            'productId' => $this->productId,
            'classId' => $this->classId,
            'hoursTrained' => $this->hoursTrained,
            'hoursPresent' => $this->hoursPresent,
            'hoursAbsent' => $this->hoursAbsent,
            'status' => $this->status,
            'startDate' => $this->startDate,
            'completionDate' => $this->completionDate,
            'portfolioFilePath' => $this->portfolioFilePath,
            'portfolioUploadedAt' => $this->portfolioUploadedAt,
            'markedCompleteBy' => $this->markedCompleteBy,
            'markedCompleteDate' => $this->markedCompleteDate,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            // Joined fields
            'productName' => $this->productName,
            'productDuration' => $this->productDuration,
            'learnerName' => $this->learnerName,
            'classCode' => $this->classCode,
            // Computed fields
            'progressPercentage' => $this->getProgressPercentage(),
            'isComplete' => $this->isCompleted(),
            'isHoursComplete' => $this->isHoursComplete(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getTrackingId(): ?int { return $this->trackingId; }
    public function getLearnerId(): int { return $this->learnerId; }
    public function getProductId(): int { return $this->productId; }
    public function getClassId(): ?int { return $this->classId; }
    public function getHoursTrained(): float { return $this->hoursTrained; }
    public function getHoursPresent(): float { return $this->hoursPresent; }
    public function getHoursAbsent(): float { return $this->hoursAbsent; }
    public function getStatus(): string { return $this->status; }
    public function getStartDate(): ?string { return $this->startDate; }
    public function getCompletionDate(): ?string { return $this->completionDate; }
    public function getPortfolioFilePath(): ?string { return $this->portfolioFilePath; }
    public function getPortfolioUploadedAt(): ?string { return $this->portfolioUploadedAt; }
    public function getMarkedCompleteBy(): ?int { return $this->markedCompleteBy; }
    public function getMarkedCompleteDate(): ?string { return $this->markedCompleteDate; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function getProductName(): ?string { return $this->productName; }
    public function getProductDuration(): ?int { return $this->productDuration; }
    public function getLearnerName(): ?string { return $this->learnerName; }
    public function getClassCode(): ?string { return $this->classCode; }

    /*
    |--------------------------------------------------------------------------
    | Setters (Fluent Interface)
    |--------------------------------------------------------------------------
    */

    public function setLearnerId(int $learnerId): static { $this->learnerId = $learnerId; return $this; }
    public function setProductId(int $productId): static { $this->productId = $productId; return $this; }
    public function setClassId(?int $classId): static { $this->classId = $classId; return $this; }
    public function setHoursTrained(float $hours): static { $this->hoursTrained = $hours; return $this; }
    public function setHoursPresent(float $hours): static { $this->hoursPresent = $hours; return $this; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function setStartDate(?string $date): static { $this->startDate = $date; return $this; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
}
