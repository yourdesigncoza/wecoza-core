<?php
declare(strict_types=1);

/**
 * WeCoza Regulatory Export Shortcode
 *
 * Displays a date-range filtered compliance report for Umalusi/DHET
 * submissions with all required regulatory columns and CSV export.
 *
 * Shortcode: [wecoza_regulatory_export]
 *
 * @package WeCoza\Learners\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Learners\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_regulatory_export_shortcode(): string {
    // Enqueue regulatory export script
    wp_enqueue_script(
        'regulatory-export-script',
        WECOZA_CORE_URL . 'assets/js/learners/regulatory-export.js',
        ['jquery'],
        WECOZA_CORE_VERSION,
        true
    );

    // Localize script with AJAX URL and nonce
    wp_localize_script('regulatory-export-script', 'regulatoryExportAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('learners_nonce'),
    ]);

    return wecoza_view('learners/regulatory-export', []);
}

// Register shortcode
add_shortcode('wecoza_regulatory_export', __NAMESPACE__ . '\wecoza_regulatory_export_shortcode');
