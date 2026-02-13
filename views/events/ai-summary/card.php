<?php
/**
 * Notification Card Layout Template
 *
 * @var array<int, array<string, mixed>> $summaries Notification data from presenter
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="timeline-scroll-wrapper" style="max-height: 600px; overflow-y: auto; overflow-x: hidden; padding-right: 10px;">
    <div class="row g-3">
        <?php foreach ($summaries as $summary): ?>
        <?php
        $eventId = $summary['event_id'] ?? 0;
        $isRead = (bool) ($summary['is_read'] ?? false);
        $isAcknowledged = (bool) ($summary['is_acknowledged'] ?? false);
        $searchIndex = $summary['search_index'] ?? '';
        $readStateClass = $summary['read_state_class'] ?? ($isRead ? 'notification-read' : 'notification-unread');
        ?>
        <div
            class="col-12 col-md-6 col-lg-4 <?php echo esc_attr($readStateClass); ?>"
            data-role="summary-item"
            data-event-id="<?php echo esc_attr($eventId); ?>"
            data-search-index="<?php echo esc_attr($searchIndex); ?>"
            data-operation="<?php echo esc_attr($summary['operation'] ?? ''); ?>"
            data-is-read="<?php echo $isRead ? '1' : '0'; ?>"
        >
            <div class="card h-100 shadow-sm <?php echo !$isRead ? 'border border-1' : ''; ?>">
                <div class="card-header bg-body-tertiary border-bottom">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if (!$isRead): ?>
                                <span class="badge badge-phoenix badge-phoenix-primary fs-10"><?php echo esc_html__('NEW', 'wecoza-events'); ?></span>
                                <?php endif; ?>
                                <h6 class="mb-0 fw-bold text-body">
                                    <?php echo $summary['class_code'] ?: esc_html__('N/A', 'wecoza-events'); ?>
                                </h6>
                            </div>
                            <p class="mb-0 fs-9 text-body-secondary">
                                <?php echo $summary['class_subject'] ?: esc_html__('No subject', 'wecoza-events'); ?>
                            </p>
                        </div>
                        <span class="badge <?php echo esc_attr($summary['operation_badge_class'] ?? 'badge-phoenix-secondary'); ?> text-uppercase fs-10">
                            <?php echo esc_html($summary['operation_label'] ?? 'Unknown'); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto; overflow-x: hidden;">
                    <?php if ($summary['has_summary']): ?>
                        <div class="fs-9 text-body">
                            <?php echo wp_kses_post($summary['summary_html']); ?>
                        </div>
                    <?php else: ?>
                        <p class="text-body-secondary fs-9 fst-italic mb-0">
                            <?php echo esc_html__('No AI summary available for this change.', 'wecoza-events'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-body-tertiary border-top">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fs-10 text-body-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-clock"></i>
                            <span><?php echo esc_html($summary['created_at_formatted'] ?? $summary['changed_at_formatted'] ?? ''); ?></span>
                        </div>
                        <?php if (!empty($summary['summary_model'])): ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-cpu"></i>
                                <span>AI-Generated</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?php echo esc_attr($summary['summary_status_badge_class'] ?? 'badge-phoenix-secondary'); ?> fs-10">
                                <?php echo esc_html(strtoupper($summary['summary_status'] ?? 'unknown')); ?>
                            </span>
                            <?php if (!empty($summary['tokens_used'])): ?>
                                <span class="fs-10 text-body-secondary">
                                    <i class="bi bi-coin"></i> <?php echo esc_html(number_format($summary['tokens_used'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-2">
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
</div>
