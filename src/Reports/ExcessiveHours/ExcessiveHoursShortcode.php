<?php
declare(strict_types=1);

/**
 * WeCoza Core - Excessive Hours Report Shortcode
 *
 * Renders the excessive training hours dashboard.
 * Shortcode: [wecoza_excessive_hours_report]
 *
 * @package WeCoza\Reports\ExcessiveHours
 * @since 1.0.0
 */

namespace WeCoza\Reports\ExcessiveHours;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_excessive_hours_report_shortcode(): string
{
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view this content.</p>';
    }

    // Get initial counts for summary cards (lightweight query)
    $service = new ExcessiveHoursService();
    $openCount = 0;

    try {
        $openCount = $service->countOpen();
        // If demo mode and no real data, show demo count
        if ($openCount === 0 && ExcessiveHoursService::DEMO_MODE) {
            $openCount = 4; // 4 open demo items
        }
    } catch (\Throwable $e) {
        wecoza_log('Excessive hours shortcode count error: ' . $e->getMessage(), 'error');
    }

    // Get client list for filter dropdown
    $clients = [];
    try {
        $pdo = wecoza_db()->getPdo();
        $stmt = $pdo->query("
            SELECT DISTINCT cl.client_id, cl.client_name
            FROM clients cl
            INNER JOIN classes c ON c.client_id = cl.client_id
            INNER JOIN class_types ct ON c.class_type = ct.class_type_code
            WHERE ct.class_type_code IN ('AET','REALLL','GETC','BA2','BA3','BA4','ASC')
            ORDER BY cl.client_name
        ");
        $clients = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        wecoza_log('Excessive hours client list error: ' . $e->getMessage(), 'error');
    }

    // Enqueue DataTables — CDN with Bootstrap 5 styling (compatible with Phoenix theme)
    // NOTE: The old wecoza_3/assets/DataTables/datatables.min.css breaks the Phoenix flex layout.
    // Always use the Bootstrap 5 variant from CDN which is theme-compatible.
    wp_enqueue_style('datatables-bs5-css', 'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css', [], '1.13.6');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
    wp_enqueue_script('datatables-bs5-js', 'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js', ['datatables-js'], '1.13.6', true);

    // Enqueue dashboard script
    wp_enqueue_script(
        'excessive-hours-dashboard',
        WECOZA_CORE_URL . 'assets/js/reports/excessive-hours-dashboard.js',
        ['jquery', 'datatables-js'],
        WECOZA_CORE_VERSION,
        true
    );

    wp_localize_script('excessive-hours-dashboard', 'excessiveHoursAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('excessive_hours_nonce'),
        'actions' => ExcessiveHoursService::ACTION_LABELS,
        'classTypes' => ExcessiveHoursService::APPLICABLE_CLASS_TYPES,
    ]);

    return wecoza_view('reports/excessive-hours/dashboard', [
        'openCount' => $openCount,
        'clients'   => $clients,
        'classTypes' => ExcessiveHoursService::APPLICABLE_CLASS_TYPES,
        'actionLabels' => ExcessiveHoursService::ACTION_LABELS,
    ]);
}

add_shortcode('wecoza_excessive_hours_report', __NAMESPACE__ . '\wecoza_excessive_hours_report_shortcode');
