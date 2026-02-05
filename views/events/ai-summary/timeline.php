<?php
/**
 * Notification Timeline Layout Template
 *
 * @var array<int, array<string, mixed>> $summaries Notification data from presenter
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
    .wecoza-timeline {
        position: relative;
        padding-left: 30px;
    }
    .wecoza-timeline::before {
        content: '';
        position: absolute;
        left: 6px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--phoenix-border-color);
    }
    .wecoza-timeline-item {
        position: relative;
        padding-bottom: 2rem;
    }
    .wecoza-timeline-item:last-child {
        padding-bottom: 0;
    }
    .wecoza-timeline-marker {
        position: absolute;
        left: -24px;
        top: 6px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 2px solid var(--phoenix-border-color);
        background: var(--phoenix-body-bg);
        z-index: 1;
    }
    .wecoza-timeline-marker.marker-insert {
        background: var(--phoenix-success);
        border-color: var(--phoenix-success);
    }
    .wecoza-timeline-marker.marker-update {
        background: var(--phoenix-primary);
        border-color: var(--phoenix-primary);
    }
    .wecoza-timeline-marker.marker-delete {
        background: var(--phoenix-danger);
        border-color: var(--phoenix-danger);
    }
    .wecoza-timeline-marker.marker-unread {
        box-shadow: 0 0 0 3px rgba(var(--phoenix-primary-rgb), 0.3);
    }
    .wecoza-timeline-item.notification-unread .card {
        border-left: 3px solid var(--phoenix-primary);
    }
</style>

<div class="wecoza-timeline">
    <?php foreach ($summaries as $summary): ?>
        <?php
        $eventId = $summary['event_id'] ?? 0;
        $isRead = (bool) ($summary['is_read'] ?? false);
        $isAcknowledged = (bool) ($summary['is_acknowledged'] ?? false);
        $searchIndex = $summary['search_index'] ?? '';
        $readStateClass = $summary['read_state_class'] ?? ($isRead ? 'notification-read' : 'notification-unread');

        $markerClass = match ($summary['operation'] ?? '') {
            'INSERT' => 'marker-insert',
            'DELETE' => 'marker-delete',
            default => 'marker-update',
        };
        if (!$isRead) {
            $markerClass .= ' marker-unread';
        }
        ?>
        <div
            class="wecoza-timeline-item <?php echo esc_attr($readStateClass); ?>"
            data-role="summary-item"
            data-event-id="<?php echo esc_attr($eventId); ?>"
            data-search-index="<?php echo esc_attr($searchIndex); ?>"
            data-operation="<?php echo esc_attr($summary['operation'] ?? ''); ?>"
            data-is-read="<?php echo $isRead ? '1' : '0'; ?>"
        >
            <div class="wecoza-timeline-marker <?php echo esc_attr($markerClass); ?>"></div>
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if (!$isRead): ?>
                                <span class="badge badge-phoenix badge-phoenix-primary fs-10"><?php echo esc_html__('NEW', 'wecoza-events'); ?></span>
                                <?php endif; ?>
                                <h6 class="mb-0 fw-bold text-body">
                                    <?php echo $summary['class_code'] ?: esc_html__('N/A', 'wecoza-events'); ?>
                                </h6>
                                <span class="badge <?php echo esc_attr($summary['operation_badge_class'] ?? 'badge-phoenix-secondary'); ?> text-uppercase fs-10">
                                    <?php echo esc_html($summary['operation_label'] ?? 'Unknown'); ?>
                                </span>
                            </div>
                            <p class="mb-0 fs-9 text-body-secondary">
                                <?php echo $summary['class_subject'] ?: esc_html__('No subject', 'wecoza-events'); ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <div class="fs-10 text-body-secondary">
                                <i class="bi bi-clock"></i>
                                <?php echo esc_html($summary['created_at_formatted'] ?? $summary['changed_at_formatted'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                    <hr class="my-2">
                    <?php if ($summary['has_summary']): ?>
                        <div class="fs-9 text-body">
                            <?php echo $summary['summary_html']; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-body-secondary fs-9 fst-italic mb-0">
                            <?php echo esc_html__('No AI summary available for this change.', 'wecoza-events'); ?>
                        </p>
                    <?php endif; ?>
                    <hr class="my-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fs-10">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge <?php echo esc_attr($summary['summary_status_badge_class'] ?? 'badge-phoenix-secondary'); ?>">
                                <?php echo esc_html(strtoupper($summary['summary_status'] ?? 'unknown')); ?>
                            </span>
                            <?php if (!empty($summary['summary_model'])): ?>
                                <span class="text-body-secondary">
                                    <i class="bi bi-cpu"></i> AI-Generated
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($summary['tokens_used'])): ?>
                                <span class="text-body-secondary">
                                    <i class="bi bi-coin"></i> <?php echo esc_html(number_format($summary['tokens_used'])); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($isAcknowledged): ?>
                                <span class="badge badge-phoenix badge-phoenix-success fs-10">
                                    <i class="bi bi-check-circle"></i> <?php echo esc_html__('Acknowledged', 'wecoza-events'); ?>
                                </span>
                            <?php elseif ($eventId): ?>
                                <button type="button"
                                    class="btn btn-sm btn-outline-success py-0 px-2 fs-10"
                                    data-role="acknowledge-btn"
                                    data-event-id="<?php echo esc_attr($eventId); ?>">
                                    <?php echo esc_html__('Acknowledge', 'wecoza-events'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
