<?php
declare(strict_types=1);

namespace WeCoza\Events\Enums;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Event type classification for notification system
 *
 * Maps database operations to semantic event types for tracking,
 * notification routing, and AI enrichment.
 *
 * @since 1.2.0
 */
enum EventType: string
{
    case CLASS_INSERT = 'CLASS_INSERT';
    case CLASS_UPDATE = 'CLASS_UPDATE';
    case CLASS_DELETE = 'CLASS_DELETE';
    case LEARNER_ADD = 'LEARNER_ADD';
    case LEARNER_REMOVE = 'LEARNER_REMOVE';
    case LEARNER_UPDATE = 'LEARNER_UPDATE';
    case STATUS_CHANGE = 'STATUS_CHANGE';

    /**
     * Human-readable label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::CLASS_INSERT => 'Class Created',
            self::CLASS_UPDATE => 'Class Updated',
            self::CLASS_DELETE => 'Class Deleted',
            self::LEARNER_ADD => 'Learner Added',
            self::LEARNER_REMOVE => 'Learner Removed',
            self::LEARNER_UPDATE => 'Learner Updated',
            self::STATUS_CHANGE => 'Status Changed',
        };
    }

    /**
     * Get notification priority (1-5, higher = more urgent)
     */
    public function priority(): int
    {
        return match ($this) {
            self::CLASS_DELETE => 5,
            self::LEARNER_REMOVE => 4,
            self::STATUS_CHANGE => 4,
            self::CLASS_INSERT => 3,
            self::LEARNER_ADD => 3,
            self::CLASS_UPDATE => 2,
            self::LEARNER_UPDATE => 2,
        };
    }

    /**
     * Map entity type + operation to event type
     *
     * @param string $entityType Entity type: 'class' or 'learner'
     * @param string $operation Operation: 'INSERT', 'UPDATE', 'DELETE', 'ADD', 'REMOVE'
     * @return self
     * @throws \InvalidArgumentException If combination is invalid
     */
    public static function forEntity(string $entityType, string $operation): self
    {
        $entityType = strtolower($entityType);
        $operation = strtoupper($operation);

        if ($entityType === 'class') {
            return match ($operation) {
                'INSERT' => self::CLASS_INSERT,
                'UPDATE' => self::CLASS_UPDATE,
                'DELETE' => self::CLASS_DELETE,
                default => throw new \InvalidArgumentException("Invalid class operation: {$operation}"),
            };
        }

        if ($entityType === 'learner') {
            return match ($operation) {
                'ADD', 'INSERT' => self::LEARNER_ADD,
                'REMOVE', 'DELETE' => self::LEARNER_REMOVE,
                'UPDATE' => self::LEARNER_UPDATE,
                default => throw new \InvalidArgumentException("Invalid learner operation: {$operation}"),
            };
        }

        throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
    }

    /**
     * Check if this event affects a class
     */
    public function isClassEvent(): bool
    {
        return in_array($this, [self::CLASS_INSERT, self::CLASS_UPDATE, self::CLASS_DELETE], true);
    }

    /**
     * Check if this event affects a learner
     */
    public function isLearnerEvent(): bool
    {
        return in_array($this, [self::LEARNER_ADD, self::LEARNER_REMOVE, self::LEARNER_UPDATE], true);
    }

    /**
     * Check if event indicates a creation
     */
    public function isCreation(): bool
    {
        return in_array($this, [self::CLASS_INSERT, self::LEARNER_ADD], true);
    }

    /**
     * Check if event indicates a deletion/removal
     */
    public function isDeletion(): bool
    {
        return in_array($this, [self::CLASS_DELETE, self::LEARNER_REMOVE], true);
    }

    /**
     * Safe conversion from string with fallback
     *
     * @param string|null $value Database or user input value
     * @param self|null $default Fallback if value is invalid (null to throw)
     * @return self
     */
    public static function tryFromString(?string $value, ?self $default = null): ?self
    {
        if ($value === null) {
            return $default;
        }
        $result = self::tryFrom(strtoupper($value));
        if ($result === null && $default === null) {
            return null;
        }
        return $result ?? $default;
    }
}
