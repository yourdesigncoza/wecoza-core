<?php
declare(strict_types=1);

namespace WeCoza\Learners\Enums;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Learner LP Progression status values
 *
 * Constraint: Only ONE 'in_progress' LP per learner at a time.
 *
 * @since 1.1.0
 */
enum ProgressionStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';

    /**
     * Human-readable label for display
     */
    public function label(): string
    {
        return match($this) {
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::ON_HOLD => 'On Hold',
        };
    }

    /**
     * CSS class for badge styling
     */
    public function badgeClass(): string
    {
        return match($this) {
            self::IN_PROGRESS => 'badge-primary',
            self::COMPLETED => 'badge-success',
            self::ON_HOLD => 'badge-warning',
        };
    }

    /**
     * Check if learner is actively learning
     */
    public function isActive(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if LP allows hour logging
     */
    public function canLogHours(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Safe conversion from string with fallback
     */
    public static function tryFromString(?string $value, self $default = self::IN_PROGRESS): self
    {
        if ($value === null) {
            return $default;
        }
        return self::tryFrom($value) ?? $default;
    }
}
