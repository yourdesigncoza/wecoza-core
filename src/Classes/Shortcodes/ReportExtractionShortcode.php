<?php
declare(strict_types=1);

/**
 * WeCoza Report Extraction Shortcode
 *
 * Displays report extraction UI with class selector, month picker,
 * report preview, and CSV download functionality.
 *
 * Shortcode: [wecoza_class_learner_report]
 *
 * @package WeCoza\Classes\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Classes\Shortcodes;

use WeCoza\Classes\Repositories\ClassRepository;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_class_learner_report_shortcode(): string {
    // Enqueue report extraction script
    wp_enqueue_script(
        'report-extraction-script',
        WECOZA_CORE_URL . 'assets/js/classes/report-extraction.js',
        ['jquery'],
        WECOZA_CORE_VERSION,
        true
    );

    // Get classes for dropdown
    $classes = ClassRepository::getAllClasses(['limit' => 9999, 'order_by' => 'class_id', 'order' => 'DESC']);

    // Build simplified class list for JS
    $classList = [];
    foreach ($classes as $class) {
        $classList[] = [
            'id'          => (int) ($class['class_id'] ?? 0),
            'class_code'  => $class['class_code'] ?? '',
            'client_name' => $class['client_name'] ?? '',
        ];
    }

    // Localize script with AJAX URL, nonce, and class list
    wp_localize_script('report-extraction-script', 'reportExtractionAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('class_learner_report_nonce'),
        'classes' => $classList,
    ]);

    return wecoza_view('classes/report-extraction', []);
}

// Register shortcode
add_shortcode('wecoza_class_learner_report', __NAMESPACE__ . '\wecoza_class_learner_report_shortcode');
