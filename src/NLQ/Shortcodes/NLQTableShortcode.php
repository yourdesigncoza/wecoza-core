<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - Table Display Shortcode
 *
 * Renders a saved query result as a Phoenix-styled DataTable.
 *
 * Usage:
 *   [wecoza_nlq_table query_id="42"]
 *   [wecoza_nlq_table query_id="42" title="Active Agents" page_size="25"]
 *   [wecoza_nlq_table query_id="42" show_sql="false" export="true"]
 *
 * @package WeCoza\NLQ\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Shortcodes;

use WeCoza\NLQ\Services\NLQService;

if (!defined('ABSPATH')) {
    exit;
}

final class NLQTableShortcode
{
    /**
     * Register the shortcode
     */
    public static function register(): void
    {
        add_shortcode('wecoza_nlq_table', [new self(), 'render']);
    }

    /**
     * Render the shortcode
     */
    public function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="alert alert-subtle-warning d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div>Please log in to view this data.</div>
                    </div>';
        }

        $atts = shortcode_atts([
            'query_id'  => '',
            'title'     => '',
            'page_size' => '25',
            'show_sql'  => 'false',
            'export'    => 'true',
        ], $atts);

        $queryId = intval($atts['query_id']);
        if (!$queryId) {
            return '<div class="alert alert-subtle-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div>Invalid or missing query ID.</div>
                    </div>';
        }

        // Execute the query
        $service = new NLQService();
        $result = $service->executeById($queryId);

        if (!$result['success']) {
            return '<div class="alert alert-subtle-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div>' . esc_html($result['error']) . '</div>
                    </div>';
        }

        $uniqueId = 'nlq-table-' . $queryId . '-' . wp_rand(1000, 9999);

        // Enqueue assets
        $this->enqueueAssets();

        // Build display title: shortcode override > "Custom Query #ID"
        $displayTitle = !empty($atts['title'])
            ? $atts['title']
            : 'Custom Query #' . $queryId;

        // Build subtitle: description > natural_language question > query_name
        $subtitle = $result['description'] ?? '';
        if (empty($subtitle)) {
            $subtitle = $result['natural_language'] ?? '';
        }
        if (empty($subtitle)) {
            $subtitle = $result['query_name'] ?? '';
        }

        // Render view
        return wecoza_view('nlq/components/nlq-table-display', [
            'columns'     => $result['columns'] ?? [],
            'rows'        => $result['data'] ?? [],
            'row_count'   => $result['row_count'] ?? 0,
            'title'       => $displayTitle,
            'subtitle'    => $subtitle,
            'query_id'    => $queryId,
            'unique_id'   => $uniqueId,
            'show_sql'    => ($atts['show_sql'] === 'true'),
            'show_export' => ($atts['export'] === 'true'),
            'page_size'   => intval($atts['page_size']),
            'sql_query'   => $result['sql_query'] ?? '',
            'description' => $result['description'] ?? '',
            'category'    => $result['category'] ?? '',
        ], true);
    }

    /**
     * Enqueue required JS/CSS
     */
    private function enqueueAssets(): void
    {
        // DataTables CSS
        if (!wp_style_is('datatables-css', 'enqueued')) {
            wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css', [], '1.13.7');
        }

        // DataTables JS
        if (!wp_script_is('datatables-js', 'enqueued')) {
            wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', ['jquery'], '1.13.7', true);
            wp_enqueue_script('datatables-bs5-js', 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js', ['datatables-js'], '1.13.7', true);
        }

        // DataTables Buttons for export
        if (!wp_script_is('datatables-buttons-js', 'enqueued')) {
            wp_enqueue_script('datatables-buttons-js', 'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js', ['datatables-js'], '2.4.2', true);
            wp_enqueue_script('datatables-buttons-html5-js', 'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js', ['datatables-buttons-js'], '2.4.2', true);
            wp_enqueue_style('datatables-buttons-css', 'https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css', [], '2.4.2');
        }

        // NLQ Table init script
        wp_enqueue_script(
            'wecoza-nlq-table',
            wecoza_js_url('nlq/nlq-table.js'),
            ['jquery', 'datatables-js'],
            WECOZA_CORE_VERSION,
            true
        );
    }
}
