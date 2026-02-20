<?php
/**
 * Notification Card Layout Template
 *
 * @var array<int, array<string, mixed>> $summaries Notification data from presenter
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<div class="timeline-scroll-wrapper" style="max-height: 640px; overflow-y: auto; overflow-x: hidden; padding-right: 10px;">
    <div class="row g-3">
        <?php foreach ($summaries as $summary): ?>
        <?php
        $eventId = $summary["event_id"] ?? 0;
        $isRead = (bool) ($summary["is_read"] ?? false);
        $isAcknowledged = (bool) ($summary["is_acknowledged"] ?? false);
        $searchIndex = $summary["search_index"] ?? "";
        $readStateClass =
            $summary["read_state_class"] ??
            ($isRead ? "notification-read" : "notification-unread");
        ?>
        <div
            class="col-12 col-md-6 col-lg-4 <?php echo esc_attr(
                $readStateClass,
            ); ?>"
            data-role="summary-item"
            data-event-id="<?php echo esc_attr($eventId); ?>"
            data-search-index="<?php echo esc_attr($searchIndex); ?>"
            data-operation="<?php echo esc_attr(
                $summary["operation"] ?? "",
            ); ?>"
            data-is-read="<?php echo $isRead ? "1" : "0"; ?>"
        >
            <div class="card h-100 shadow-sm <?php echo !$isRead
                ? "border border-1"
                : ""; ?>">
                <div class="card-header bg-body-tertiary border-bottom">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span data-role="status-badge" data-event-id="<?php echo esc_attr(
                                    $eventId,
                                ); ?>">
                                    <?php if ($isAcknowledged): ?>
                                        <span class="badge badge-phoenix badge-phoenix-success fs-10">Read</span>
                                    <?php elseif (!$isRead): ?>
                                        <span class="badge badge-phoenix badge-phoenix-primary fs-10"><?php echo esc_html__(
                                            "NEW",
                                            "wecoza-events",
                                        ); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-phoenix badge-phoenix-info fs-10">Read</span>
                                    <?php endif; ?>
                                </span>
                                <h6 class="mb-0 fw-bold text-body">
                                    <?php echo $summary["class_code"] ?:
                                        esc_html__("N/A", "wecoza-events"); ?>
                                </h6>
                            </div>
                            <p class="mb-0 fs-9 text-body-secondary">
                                <?php echo $summary["class_subject"] ?:
                                    esc_html__(
                                        "No subject",
                                        "wecoza-events",
                                    ); ?>
                            </p>
                            <p class="mb-0 fs-10 text-body-tertiary">
                                <?php if (!empty($summary["client_name"])): ?>
                                <i class="bi bi-building me-1"></i><?php echo esc_html($summary["client_name"]); ?>
                                <?php else: ?>&nbsp;<?php endif; ?>
                            </p>
                            <p class="mb-0 fs-10 text-body-tertiary">
                                <?php if (!empty($summary["site_name"])): ?>
                                <i class="bi bi-geo-alt me-1"></i><?php echo esc_html($summary["site_name"]); if (!empty($summary["site_address"])): ?> &mdash; <?php echo esc_html($summary["site_address"]); endif; ?>
                                <?php else: ?>&nbsp;<?php endif; ?>
                            </p>
                            <p class="mb-0 fs-10 text-body-tertiary">
                                <?php if (!empty($summary["agent_name"])): ?>
                                <i class="bi bi-person me-1"></i><?php echo esc_html($summary["agent_name"]); ?>
                                <?php else: ?>&nbsp;<?php endif; ?>
                            </p>
                        </div>
                        <span class="badge <?php echo esc_attr(
                            $summary["operation_badge_class"] ??
                                "badge-phoenix-secondary",
                        ); ?> text-uppercase fs-10">
                            <?php echo esc_html(
                                $summary["operation_label"] ?? "Unknown",
                            ); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body" style="max-height: 110px; overflow-y: auto; overflow-x: hidden;">
                    <?php if ($summary["has_summary"]): ?>
                        <div class="fs-9 text-body">
                            <?php echo wp_kses_post(
                                $summary["summary_html"],
                            ); ?>
                        </div>
                    <?php else: ?>
                        <?php $detailRows = array_filter([
                            "Class Type" => $summary["class_type"] ?? "",
                            "Start Date" => $summary["start_date"] ?? "",
                            "End Date" => $summary["end_date"] ?? "",
                            "Schedule" => $summary["schedule_pattern"] ?? "",
                            "Learners" =>
                                ($summary["learner_count"] ?? 0) > 0
                                    ? (string) $summary["learner_count"]
                                    : "",
                        ]); ?>
                        <?php if (!empty($detailRows)): ?>
                        <div class="fs-9 text-body">
                            <ul class="list-unstyled mb-0">
                                <?php foreach (
                                    $detailRows
                                    as $label => $value
                                ): ?>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong><?php echo esc_html(
                                    $label,
                                ); ?>:</strong> <?php echo esc_html(
    $value,
); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                        <p class="text-body-secondary fs-9 fst-italic mb-0">
                            <?php echo esc_html__(
                                "No summary available for this change.",
                                "wecoza-events",
                            ); ?>
                        </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-body-tertiary border-top py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fs-10 text-body-secondary">
                            <i class="bi bi-clock me-1"></i><?php echo esc_html(
                                $summary["created_at_formatted"] ??
                                    ($summary["changed_at_formatted"] ?? ""),
                            ); ?>
                            <?php if (!empty($summary["summary_model"])): ?>
                                <span class="ms-2"><i class="bi bi-cpu me-1"></i>AI</span>
                            <?php endif; ?>
                        </span>
                        <div class="btn-group" role="group">
                            <?php if ($isAcknowledged): ?>
                                <span class="btn btn-sm btn-subtle-warning" style="pointer-events:none;">
                                    <i class="bi bi-check-circle me-1"></i><?php echo esc_html__(
                                        "Acknowledged",
                                        "wecoza-events",
                                    ); ?>
                                </span>
                            <?php elseif ($eventId): ?>
                                <button type="button"
                                    class="btn btn-sm btn-subtle-success"
                                    data-role="acknowledge-btn"
                                    data-event-id="<?php echo esc_attr(
                                        $eventId,
                                    ); ?>">
                                    <?php echo esc_html__(
                                        "Acknowledge",
                                        "wecoza-events",
                                    ); ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($eventId): ?>
                                <button type="button"
                                    class="btn btn-sm btn-subtle-danger"
                                    data-role="delete-btn"
                                    data-event-id="<?php echo esc_attr(
                                        $eventId,
                                    ); ?>"
                                    title="<?php echo esc_attr__(
                                        "Delete notification",
                                        "wecoza-events",
                                    ); ?>">
                                    <i class="bi bi-trash"></i>
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
