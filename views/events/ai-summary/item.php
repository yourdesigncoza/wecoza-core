<?php
/**
 * Notification Item Template
 *
 * Individual notification item for standalone use or list iteration.
 * This component can be included in timeline or card layouts.
 *
 * @var array $item Notification data from presenter
 *
 * Expected $item keys:
 * - event_id (int)
 * - event_type (string)
 * - is_read (bool)
 * - is_acknowledged (bool)
 * - search_index (string)
 * - class_code (string)
 * - class_subject (string)
 * - operation_label (string)
 * - operation_badge_class (string)
 * - created_at_formatted (string)
 * - sent_at_formatted (string|null)
 * - has_summary (bool)
 * - summary_html (string)
 * - read_state_class (string)
 *
 * @since 1.2.0
 */

if (!defined("ABSPATH")) {
    exit();
}

$eventId = esc_attr($item["event_id"] ?? 0);
$eventType = esc_attr($item["event_type"] ?? "");
$operation = esc_attr($item["operation"] ?? "");
$isRead = !empty($item["is_read"]);
$isAcknowledged = !empty($item["is_acknowledged"]);
$searchIndex = esc_attr($item["search_index"] ?? "");
$readStateClass =
    $item["read_state_class"] ??
    ($isRead ? "notification-read" : "notification-unread");
?>

<div class="notification-item card mb-2 <?php echo esc_attr(
    $readStateClass,
); ?> <?php echo !$isRead ? "border-start border-3" : ""; ?>"
     data-role="summary-item"
     data-event-id="<?php echo $eventId; ?>"
     data-operation="<?php echo $operation; ?>"
     data-search-index="<?php echo $searchIndex; ?>"
     data-is-read="<?php echo $isRead ? "1" : "0"; ?>">

    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start">
            <div class="notification-content flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span data-role="status-badge" data-event-id="<?php echo $eventId; ?>">
                        <?php if ($isAcknowledged): ?>
                            <span class="badge badge-phoenix badge-phoenix-success fs-10">Read</span>
                        <?php elseif (!$isRead): ?>
                            <span class="badge badge-phoenix badge-phoenix-primary fs-10"><?php echo esc_html__("NEW", "wecoza-events"); ?></span>
                        <?php else: ?>
                            <span class="badge badge-phoenix badge-phoenix-info fs-10">Read</span>
                        <?php endif; ?>
                    </span>

                    <span class="badge <?php echo esc_attr(
                        $item["operation_badge_class"] ??
                            "badge-phoenix badge-phoenix-secondary",
                    ); ?> text-uppercase fs-10">
                        <?php echo esc_html(
                            $item["operation_label"] ?? $eventType,
                        ); ?>
                    </span>
                </div>

                <h6 class="mb-0 fw-bold text-body">
                    <?php echo esc_html(
                        $item["class_code"] ?? __("Unknown", "wecoza-events"),
                    ); ?>
                </h6>
                <p class="text-body-secondary mb-0 fs-9">
                    <?php echo esc_html($item["class_subject"] ?? ""); ?>
                </p>
                <?php if (!empty($item["agent_name"])): ?>
                <p class="mb-0 fs-10 text-body-tertiary">
                    <i class="bi bi-person"></i> <?php echo esc_html($item["agent_name"]); ?>
                </p>
                <?php endif; ?>

                <small class="text-body-tertiary d-block mt-1 fs-10">
                    <i class="bi bi-clock me-1"></i>
                    <?php echo esc_html($item["created_at_formatted"] ?? ""); ?>
                    <?php if (!empty($item["sent_at_formatted"])): ?>
                    &middot; <i class="bi bi-send me-1"></i> <?php echo esc_html__(
                        "Sent:",
                        "wecoza-events",
                    ); ?> <?php echo esc_html($item["sent_at_formatted"]); ?>
                    <?php endif; ?>
                </small>

                <?php if (
                    !empty($item["has_summary"]) &&
                    !empty($item["summary_html"])
                ): ?>
                <div class="ai-summary mt-2 p-2 bg-body-tertiary rounded fs-9">
                    <?php echo wp_kses_post($item["summary_html"]); ?>
                </div>
                <?php elseif (empty($item["has_summary"])): ?>
                <p class="text-body-secondary fs-9 fst-italic mt-2 mb-0">
                    <?php echo esc_html__(
                        "No summary available for this change.",
                        "wecoza-events",
                    ); ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="notification-actions ms-3 d-flex flex-column gap-2">
                <?php if (!$isRead): ?>
                <button type="button"
                    class="btn btn-sm btn-outline-secondary py-0 px-2 fs-10"
                    data-role="mark-read-btn"
                    data-event-id="<?php echo $eventId; ?>">
                    <i class="bi bi-check me-1"></i><?php echo esc_html__(
                        "Mark Read",
                        "wecoza-events",
                    ); ?>
                </button>
                <?php endif; ?>

                <?php if (!$isAcknowledged): ?>
                <button type="button"
                    class="btn btn-sm btn-outline-success py-0 px-2 fs-10"
                    data-role="acknowledge-btn"
                    data-event-id="<?php echo $eventId; ?>">
                    <i class="bi bi-check2-circle me-1"></i><?php echo esc_html__(
                        "Acknowledge",
                        "wecoza-events",
                    ); ?>
                </button>
                <?php else: ?>
                <span class="badge badge-phoenix badge-phoenix-success fs-10">
                    <i class="bi bi-check-circle me-1"></i> <?php echo esc_html__(
                        "Acknowledged",
                        "wecoza-events",
                    ); ?>
                </span>
                <?php endif; ?>
                <?php if ($eventId): ?>
                <button type="button"
                    class="btn btn-sm btn-outline-danger py-0 px-2 fs-10"
                    data-role="delete-btn"
                    data-event-id="<?php echo $eventId; ?>"
                    title="<?php echo esc_attr__('Delete notification', 'wecoza-events'); ?>">
                    <i class="bi bi-trash"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
