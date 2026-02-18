<?php
declare(strict_types=1);

/**
 * WeCoza Progression Admin Shortcode
 *
 * Displays a full-featured admin table for managing all learner progressions:
 * filterable table with checkboxes, bulk action bar, action dropdowns per row,
 * Start New LP modal, Hours Log modal, and Bulk Complete confirmation modal.
 *
 * Shortcode: [wecoza_progression_admin]
 *
 * @package WeCoza\Learners\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Learners\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_progression_admin_shortcode(): string {
    // Enqueue progression admin script
    wp_enqueue_script(
        'progression-admin-script',
        WECOZA_CORE_URL . 'assets/js/learners/progression-admin.js',
        ['jquery'],
        WECOZA_CORE_VERSION,
        true
    );

    // Localize script with AJAX URL and nonce
    wp_localize_script('progression-admin-script', 'progressionAdminAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('learners_nonce'),
    ]);

    ob_start();
    wecoza_view('learners/progression-admin', []);
    return ob_get_clean();
}

// Register shortcode
add_shortcode('wecoza_progression_admin', __NAMESPACE__ . '\wecoza_progression_admin_shortcode');
