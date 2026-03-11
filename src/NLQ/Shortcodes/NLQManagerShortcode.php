<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - Query Manager Shortcode
 *
 * Frontend admin interface for creating, editing, previewing, and managing saved queries.
 * Uses Phoenix design patterns with proper view separation.
 *
 * Usage:
 *   [wecoza_nlq_manager]
 *
 * @package WeCoza\NLQ\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Shortcodes;

use WeCoza\NLQ\Repositories\SavedQueryRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class NLQManagerShortcode
{
    private const NONCE_ACTION = 'wecoza_nlq_nonce';

    /**
     * Register the shortcode
     */
    public static function register(): void
    {
        add_shortcode('wecoza_nlq_manager', [new self(), 'render']);
    }

    /**
     * Render the query manager interface
     */
    public function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="alert alert-subtle-warning d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div>Please log in to manage queries.</div>
                    </div>';
        }

        if (!current_user_can('manage_options')) {
            return '<div class="alert alert-subtle-danger d-flex align-items-center">
                        <i class="bi bi-shield-exclamation me-3 fs-4"></i>
                        <div>You do not have permission to manage queries.</div>
                    </div>';
        }

        $this->enqueueAssets();

        $repository = new SavedQueryRepository();

        return wecoza_view('nlq/components/nlq-manager', [
            'queries'    => $repository->findAllForAdmin(),
            'categories' => $repository->getCategories(),
            'nonce'      => wp_create_nonce(self::NONCE_ACTION),
        ], true);
    }

    /**
     * Enqueue JS/CSS
     */
    private function enqueueAssets(): void
    {
        // DataTables
        if (!wp_style_is('datatables-css', 'enqueued')) {
            wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css', [], '1.13.7');
        }
        if (!wp_script_is('datatables-js', 'enqueued')) {
            wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', ['jquery'], '1.13.7', true);
            wp_enqueue_script('datatables-bs5-js', 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js', ['datatables-js'], '1.13.7', true);
        }

        // NLQ Manager JS
        wp_enqueue_script(
            'wecoza-nlq-manager',
            wecoza_js_url('nlq/nlq-manager.js'),
            ['jquery', 'datatables-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-nlq-manager', 'wecozaNLQ', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }
}
