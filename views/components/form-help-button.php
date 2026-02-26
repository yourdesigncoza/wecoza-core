<?php
/**
 * Contextual Help Trigger Button
 *
 * Renders a small button that opens the corresponding offcanvas help panel.
 *
 * Expected variables (via extract):
 *   string $offcanvas_id — Must match the offcanvas panel id
 *   string $label        — Button label (default: 'Help')
 *
 * @package WeCoza\Core
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$offcanvas_id = $offcanvas_id ?? 'formHelpPanel';
$label        = $label ?? 'Help';
?>
<button type="button"
        class="btn btn-sm btn-phoenix-secondary"
        data-bs-toggle="offcanvas"
        data-bs-target="#<?php echo esc_attr($offcanvas_id); ?>"
        aria-controls="<?php echo esc_attr($offcanvas_id); ?>">
    <i class="bi bi-question-circle me-1"></i><?php echo esc_html($label); ?>
</button>
