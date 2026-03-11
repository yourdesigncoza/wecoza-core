<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - Natural Language Input Shortcode
 *
 * Provides the AI-powered query builder interface:
 *   1. User types a question in natural language
 *   2. AI generates SQL and returns explanation
 *   3. User previews results
 *   4. User can refine, then save the query
 *
 * Usage:
 *   [wecoza_nlq_input]
 *
 * @package WeCoza\NLQ\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Shortcodes;

use WeCoza\NLQ\Repositories\SavedQueryRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class NLQInputShortcode
{
    private const NONCE_ACTION = 'wecoza_nlq_nonce';

    public static function register(): void
    {
        add_shortcode('wecoza_nlq_input', [new self(), 'render']);
    }

    public function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="alert alert-subtle-warning d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div>Please log in to use the query builder.</div>
                    </div>';
        }

        if (!current_user_can('manage_options')) {
            return '<div class="alert alert-subtle-danger d-flex align-items-center">
                        <i class="bi bi-shield-exclamation me-3 fs-4"></i>
                        <div>You do not have permission to use the query builder.</div>
                    </div>';
        }

        $this->enqueueAssets();

        $repository = new SavedQueryRepository();
        $categories = $repository->getCategories();

        return wecoza_view('nlq/components/nlq-input', [
            'categories' => $categories,
            'nonce'      => wp_create_nonce(self::NONCE_ACTION),
        ], true);
    }

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

        // NLQ Input JS
        wp_enqueue_script(
            'wecoza-nlq-input',
            wecoza_js_url('nlq/nlq-input.js'),
            ['jquery', 'datatables-js'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-nlq-input', 'wecozaNLQ', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }
}
