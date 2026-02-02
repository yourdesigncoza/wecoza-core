<?php
declare(strict_types=1);

namespace WeCoza\Events\Enums;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Class Task completion status values
 *
 * @since 1.1.0
 */
enum TaskStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';

    /**
     * Human-readable label for display
     */
    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * CSS class for badge styling
     */
    public function badgeClass(): string
    {
        return match($this) {
            self::OPEN => 'badge-warning',
            self::COMPLETED => 'badge-success',
        };
    }

    /**
     * Icon for display
     */
    public function icon(): string
    {
        return match($this) {
            self::OPEN => 'circle',
            self::COMPLETED => 'check-circle',
        };
    }

    /**
     * Safe conversion from string with fallback
     */
    public static function tryFromString(?string $value, self $default = self::OPEN): self
    {
        if ($value === null) {
            return $default;
        }
        return self::tryFrom($value) ?? $default;
    }
}
