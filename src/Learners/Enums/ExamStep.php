<?php
declare(strict_types=1);

namespace WeCoza\Learners\Enums;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Exam step identifiers for learner LP exam results.
 *
 * Each learner LP tracking entry can have up to 5 exam steps:
 * 3 mock exams, 1 SBA (skills-based assessment), and 1 final exam.
 *
 * @since 1.2.0
 */
enum ExamStep: string
{
    case MOCK_1 = 'mock_1';
    case MOCK_2 = 'mock_2';
    case MOCK_3 = 'mock_3';
    case SBA    = 'sba';
    case FINAL  = 'final';

    /**
     * Human-readable label for display
     */
    public function label(): string
    {
        return match($this) {
            self::MOCK_1 => 'Mock Exam 1',
            self::MOCK_2 => 'Mock Exam 2',
            self::MOCK_3 => 'Mock Exam 3',
            self::SBA    => 'SBA',
            self::FINAL  => 'Final Exam',
        };
    }

    /**
     * CSS class for badge styling
     */
    public function badgeClass(): string
    {
        return match($this) {
            self::MOCK_1, self::MOCK_2, self::MOCK_3 => 'badge-info',
            self::SBA    => 'badge-warning',
            self::FINAL  => 'badge-success',
        };
    }

    /**
     * Whether this exam step requires a file upload (evidence document)
     */
    public function requiresFile(): bool
    {
        return match($this) {
            self::SBA, self::FINAL => true,
            default => false,
        };
    }

    /**
     * Safe conversion from string with fallback
     */
    public static function tryFromString(?string $value, ?self $default = null): ?self
    {
        if ($value === null) {
            return $default;
        }
        return self::tryFrom($value) ?? $default;
    }
}
