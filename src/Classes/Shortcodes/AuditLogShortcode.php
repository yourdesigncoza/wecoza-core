<?php
declare(strict_types=1);

/**
 * WeCoza Core - Audit Log Shortcode
 *
 * Renders a filterable audit log table for admin pages.
 * Shortcode: [wecoza_audit_log]
 *
 * Attributes:
 *  - entity_type: Filter by entity type (e.g. 'class', 'learner')
 *  - limit: Number of entries per page (default: 50)
 *
 * Design decisions:
 *  - D019: Admin-only access via page gatekeeping (not enforced in shortcode)
 *  - D017: High-level only, no PII
 *  - D018: Action codes only
 *
 * @package WeCoza\Classes\Shortcodes
 * @since 1.1.0
 */

namespace WeCoza\Classes\Shortcodes;

use WeCoza\Classes\Services\AuditService;

if (!defined('ABSPATH')) {
    exit;
}

class AuditLogShortcode
{
    private AuditService $auditService;

    public function __construct(?AuditService $auditService = null)
    {
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * Register the shortcode.
     */
    public static function register(?self $instance = null): void
    {
        $instance = $instance ?? new self();
        add_shortcode('wecoza_audit_log', [$instance, 'render']);
    }

    /**
     * Render the audit log table.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="alert alert-warning">You must be logged in to view this content.</div>';
        }

        $atts = shortcode_atts([
            'entity_type' => '',
            'limit' => 50,
        ], $atts, 'wecoza_audit_log');

        $limit = max(1, min(200, intval($atts['limit'])));
        $page = isset($_GET['audit_page']) ? max(1, intval($_GET['audit_page'])) : 1;
        $offset = ($page - 1) * $limit;

        $filterType = !empty($atts['entity_type']) ? sanitize_key($atts['entity_type']) : null;

        $entries = $this->auditService->getRecentLog($limit, $offset, $filterType);

        // Resolve user display names
        $entries = array_map(function ($entry) {
            $entry['user_display'] = $this->resolveUserName($entry['user_id'] ?? null);
            $entry['context_parsed'] = is_string($entry['context'] ?? null)
                ? json_decode($entry['context'], true)
                : ($entry['context'] ?? []);
            return $entry;
        }, $entries);

        ob_start();
        $data = [
            'entries' => $entries,
            'filter_type' => $filterType,
            'page' => $page,
            'limit' => $limit,
            'has_more' => count($entries) === $limit,
        ];

        // Render the view
        $viewPath = defined('WECOZA_CORE_PATH')
            ? WECOZA_CORE_PATH . 'views/components/audit-log-table.view.php'
            : dirname(__DIR__, 3) . '/views/components/audit-log-table.view.php';
        if (file_exists($viewPath)) {
            extract($data);
            include $viewPath;
        } else {
            echo '<div class="alert alert-danger">Audit log view template not found.</div>';
        }

        return ob_get_clean();
    }

    /**
     * Resolve WP user ID to display name.
     *
     * @param int|null $userId
     * @return string
     */
    private function resolveUserName(?int $userId): string
    {
        if ($userId === null || $userId === 0) {
            return 'System';
        }

        if (function_exists('get_userdata')) {
            $user = get_userdata($userId);
            if ($user) {
                return $user->display_name ?: $user->user_login;
            }
        }

        return "User #{$userId}";
    }
}
