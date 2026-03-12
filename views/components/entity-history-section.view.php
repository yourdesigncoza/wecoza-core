<?php
/**
 * Entity History Section Component
 *
 * Renders a collapsible accordion card containing the entity's relationship history.
 * Data is loaded via AJAX (lazy-loaded on accordion expand).
 *
 * Available variables:
 *  - $entity_type: 'class', 'agent', 'learner', or 'client'
 *  - $entity_id: Integer entity ID
 *
 * @package WeCoza\Views\Components
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

$entity_type = $entity_type ?? '';
$entity_id = $entity_id ?? 0;

if (empty($entity_type) || $entity_id <= 0) {
    return;
}

$labels = [
    'class' => 'Class Relationship History',
    'agent' => 'Agent Relationship History',
    'learner' => 'Learner Relationship History',
    'client' => 'Client Relationship History',
];
$label = $labels[$entity_type] ?? 'Relationship History';
?>

<!-- Entity History Section (M002) -->
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">
            <button class="btn btn-link btn-sm text-decoration-none p-0 collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#entityHistoryCollapse"
                    aria-expanded="false" aria-controls="entityHistoryCollapse">
                <i class="bi bi-clock-history me-1"></i><?php echo esc_html($label); ?>
            </button>
        </h5>
    </div>
    <div id="entityHistoryCollapse" class="collapse">
        <div class="card-body">
            <div id="entity-history-content">
                <p class="text-body-tertiary mb-0">
                    <i class="bi bi-arrow-up-circle me-1"></i>
                    Click to load relationship history...
                </p>
            </div>
        </div>
    </div>
</div>
