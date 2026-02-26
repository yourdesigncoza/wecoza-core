<?php
/**
 * Contextual Help Offcanvas Panel
 *
 * Renders a Bootstrap 5 offcanvas panel with config-driven help content.
 * Silent no-op when the form key is not found in config/form-help.php.
 *
 * Expected variables (via extract):
 *   string $form_key    — Key in config/form-help.php (e.g. 'create-class')
 *   string $offcanvas_id — HTML id for the offcanvas element (default: 'formHelpPanel')
 *
 * @package WeCoza\Core
 * @since 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$offcanvas_id = $offcanvas_id ?? 'formHelpPanel';
$form_key     = $form_key ?? '';

$helpConfig = wecoza_config('form-help');
if (empty($helpConfig[$form_key])) {
    return;
}

$help     = $helpConfig[$form_key];
$title    = $help['title'] ?? 'Help';
$icon     = $help['icon'] ?? 'bi-question-circle';
$sections = $help['sections'] ?? [];
?>
<div class="offcanvas offcanvas-end wecoza-help-offcanvas" tabindex="-1"
     id="<?php echo esc_attr($offcanvas_id); ?>"
     aria-labelledby="<?php echo esc_attr($offcanvas_id); ?>Label">

    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="<?php echo esc_attr($offcanvas_id); ?>Label">
            <i class="bi <?php echo esc_attr($icon); ?> me-2"></i><?php echo esc_html($title); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        <?php foreach ($sections as $section):
            $heading = $section['heading'] ?? '';
            $type    = $section['type'] ?? 'checklist';
            $sIcon   = $section['icon'] ?? 'bi-info-circle';
            $items   = $section['items'] ?? [];
            if (empty($items)) {
                continue;
            }
        ?>
        <div class="mb-4">
            <h6 class="text-body-secondary mb-2">
                <i class="bi <?php echo esc_attr($sIcon); ?> me-1"></i><?php echo esc_html($heading); ?>
            </h6>

            <?php if ($type === 'checklist'): ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($items as $item): ?>
                        <li class="mb-1">
                            <i class="bi bi-check-lg text-success me-1"></i><?php echo wp_kses_post($item); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php elseif ($type === 'ordered'): ?>
                <ol class="wecoza-help-ordered mb-0 ps-3">
                    <?php foreach ($items as $item): ?>
                        <li class="mb-1"><?php echo wp_kses_post($item); ?></li>
                    <?php endforeach; ?>
                </ol>

            <?php elseif ($type === 'tips'): ?>
                <dl class="mb-0">
                    <?php foreach ($items as $field => $tip): ?>
                        <dt class="fw-semibold small"><?php echo esc_html($field); ?></dt>
                        <dd class="wecoza-help-tip ms-0 mb-2 ps-3 small text-body-secondary">
                            <?php echo wp_kses_post($tip); ?>
                        </dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <?php if (!empty($section['note'])): ?>
                <p class="small text-body-secondary fst-italic mt-2 mb-0">
                    <i class="bi bi-lightbulb me-1"></i><?php echo wp_kses_post($section['note']); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
