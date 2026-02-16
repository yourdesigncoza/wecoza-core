<?php
/**
 * Application Constants
 *
 * Shared constants for pagination, timeouts, and bounds across all modules.
 * Eliminates magic numbers from business logic by providing named, discoverable constants.
 *
 * @package WeCoza\Core\Abstract
 * @since 4.0.0
 */

declare(strict_types=1);

namespace WeCoza\Core\Abstract;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AppConstants
 *
 * Provides shared application-wide constants for pagination limits, timeout values,
 * and progress/score bounds. All constants use SCREAMING_SNAKE_CASE naming.
 *
 * @package WeCoza\Core\Abstract
 */
class AppConstants
{
    // ============================================================
    // Pagination defaults (CONST-01)
    // ============================================================

    /**
     * Default page size for list views (learners, events, classes)
     */
    public const DEFAULT_PAGE_SIZE = 50;

    /**
     * Default limit for search/autocomplete dropdowns
     */
    public const SEARCH_RESULT_LIMIT = 10;

    /**
     * Default limit for shortcode pagination
     */
    public const SHORTCODE_DEFAULT_LIMIT = 20;

    /**
     * Upper bound for per_page parameter
     */
    public const MAX_PAGE_SIZE = 100;

    // ============================================================
    // Timeout values (CONST-02)
    // ============================================================

    /**
     * External API call timeout (seconds)
     */
    public const API_TIMEOUT_SECONDS = 30;

    /**
     * Process lock time-to-live (seconds)
     */
    public const LOCK_TTL_SECONDS = 120;

    // ============================================================
    // Progress/score limits (CONST-03)
    // ============================================================

    /**
     * Maximum progress percentage
     */
    public const PROGRESS_MAX_PERCENT = 100;

    // ============================================================
    // Pagination bounds
    // ============================================================

    /**
     * Minimum page number
     */
    public const MIN_PAGE = 1;

    /**
     * Minimum per_page value
     */
    public const MIN_PAGE_SIZE = 1;
}
