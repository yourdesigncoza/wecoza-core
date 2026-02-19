<?php
declare(strict_types=1);

/**
 * WeCoza Progression Report Shortcode
 *
 * Displays a read-only learner progression report with summary stats,
 * search/filter controls, employer grouping, and status timeline rows.
 *
 * Shortcode: [wecoza_learner_progression_report]
 *
 * @package WeCoza\Learners\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Learners\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_progression_report_shortcode(): string {
    // Enqueue progression report script
    wp_enqueue_script(
        'progression-report-script',
        WECOZA_CORE_URL . 'assets/js/learners/progression-report.js',
        ['jquery'],
        WECOZA_CORE_VERSION,
        true
    );

    // Localize script with AJAX URL and nonce
    wp_localize_script('progression-report-script', 'progressionReportAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('learners_nonce'),
    ]);

    ob_start();
    wecoza_view('learners/progression-report', []);
    return ob_get_clean();
}

// Register shortcode
add_shortcode('wecoza_learner_progression_report', __NAMESPACE__ . '\wecoza_progression_report_shortcode');
