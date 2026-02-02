<?php
declare(strict_types=1);

namespace WeCoza\Events\Enums;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * AI Summary generation status values
 *
 * @since 1.1.0
 */
enum SummaryStatus: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    /**
     * Human-readable label for display
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Check if status indicates completion (success or failure)
     */
    public function isTerminal(): bool
    {
        return $this !== self::PENDING;
    }

    /**
     * Safe conversion from string with fallback
     *
     * @param string|null $value Database or user input value
     * @param self $default Fallback if value is invalid
     */
    public static function tryFromString(?string $value, self $default = self::PENDING): self
    {
        if ($value === null) {
            return $default;
        }
        return self::tryFrom($value) ?? $default;
    }
}
