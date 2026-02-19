<?php
/**
 * Feedback Dashboard View
 *
 * Uses Phoenix accordion pattern for expandable rows.
 *
 * @var array $items     Feedback records
 * @var string $filter   Current filter (all|open|resolved)
 * @var bool $isAdmin    Whether current user can resolve items
 */
if (!defined('ABSPATH')) {
    exit;
}

$uploadsDir = wp_upload_dir();
$counts = ['all' => 0, 'open' => 0, 'resolved' => 0];
foreach ($items as $item) {
    $counts['all']++;
    if ($item['is_resolved'] ?? false) {
        $counts['resolved']++;
    } else {
        $counts['open']++;
    }
}
$totalLabel = match ($filter) {
    'open'     => $counts['all'] . ' Open',
    'resolved' => $counts['all'] . ' Resolved',
    default    => $counts['all'] . ' Total',
};
?>

<div class="card" data-wecoza-shortcode="wecoza_feedback_dashboard">
    <div class="card-header d-flex flex-between-center">
        <h5 class="mb-0">
            <span class="fas fa-bug me-2"></span>Feedback Dashboard
            <span class="badge badge-phoenix badge-phoenix-secondary ms-2"><?= esc_html($totalLabel) ?></span>
        </h5>
        <div class="btn-group" role="group" aria-label="Feedback Filters">
            <a class="btn btn-subtle-<?= $filter === 'open' ? 'primary' : 'secondary' ?>" href="?feedback_filter=open">Open</a>
            <a class="btn btn-subtle-<?= $filter === 'resolved' ? 'success' : 'secondary' ?>" href="?feedback_filter=resolved">Resolved</a>
            <a class="btn btn-subtle-<?= $filter === 'all' ? 'primary' : 'secondary' ?>" href="?feedback_filter=all">All</a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
            <div class="text-center py-6">
                <span class="fas fa-check-circle text-success fs-3 mb-3 d-block"></span>
                <p class="text-body-tertiary mb-0">No feedback items to show.</p>
            </div>
        <?php else: ?>
            <!-- Column headers - pe-5 matches accordion-button right padding for +/- icon -->
            <div class="d-flex align-items-center px-3 pe-5 py-2 border-bottom fs-9 fw-bold text-body-secondary">
                <?php if ($isAdmin): ?>
                    <div style="width: 35px;" class="text-center flex-shrink-0">Done</div>
                <?php endif; ?>
                <div class="flex-grow-1">Title</div>
                <div style="width: 90px;" class="text-center">Category</div>
                <div style="width: 70px;" class="text-center">Priority</div>
                <div style="width: 110px;" class="text-center">Date</div>
            </div>

            <!-- Accordion -->
            <div class="accordion" id="feedbackAccordion">
                <?php foreach ($items as $index => $item):
                    $isResolved = (bool) ($item['is_resolved'] ?? false);
                    $categoryBadge = match ($item['category']) {
                        'bug_report'      => 'danger',
                        'feature_request' => 'info',
                        default           => 'secondary',
                    };
                    $categoryLabel = match ($item['category']) {
                        'bug_report'      => 'Bug',
                        'feature_request' => 'Feature',
                        default           => 'Comment',
                    };
                    $priorityBadge = match ($item['ai_suggested_priority'] ?? 'Medium') {
                        'Urgent' => 'danger',
                        'High'   => 'warning',
                        'Low'    => 'secondary',
                        default  => 'info',
                    };
                    $screenshotUrl = '';
                    if (!empty($item['screenshot_path'])) {
                        $screenshotUrl = str_replace(
                            $uploadsDir['basedir'],
                            $uploadsDir['baseurl'],
                            $item['screenshot_path']
                        );
                    }
                    $conversation = [];
                    if (!empty($item['ai_conversation'])) {
                        $decoded = is_string($item['ai_conversation'])
                            ? json_decode($item['ai_conversation'], true)
                            : $item['ai_conversation'];
                        if (is_array($decoded)) {
                            $conversation = $decoded;
                        }
                    }
                    $urlParams = [];
                    if (!empty($item['url_params'])) {
                        $decoded = is_string($item['url_params'])
                            ? json_decode($item['url_params'], true)
                            : $item['url_params'];
                        if (is_array($decoded)) {
                            $urlParams = $decoded;
                        }
                    }
                    $rowId = (int) $item['id'];
                    $collapseId = 'feedbackCollapse' . $rowId;
                    $headerId = 'feedbackHeader' . $rowId;
                ?>
                    <div class="accordion-item <?= $isResolved ? 'opacity-50' : '' ?>" id="feedback-row-<?= $rowId ?>">
                        <h2 class="accordion-header d-flex align-items-center" id="<?= $headerId ?>">
                            <?php if ($isAdmin): ?>
                                <span class="wecoza-feedback-resolve-btn flex-shrink-0 text-center px-2"
                                      role="button"
                                      data-feedback-id="<?= $rowId ?>"
                                      title="<?= $isResolved ? 'Mark as open' : 'Mark as resolved' ?>">
                                    <?php if ($isResolved): ?>
                                        <span class="fas fa-check-circle text-success fs-7"></span>
                                    <?php else: ?>
                                        <span class="far fa-circle text-body-tertiary fs-7"></span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            <button class="accordion-button collapsed py-2 px-3 flex-grow-1" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#<?= $collapseId ?>"
                                    aria-expanded="false"
                                    aria-controls="<?= $collapseId ?>">
                                <div class="d-flex align-items-center w-100">
                                    <div class="flex-grow-1 me-2">
                                        <strong class="fs-9 <?= $isResolved ? 'text-decoration-line-through' : '' ?>">
                                            <?= esc_html($item['ai_generated_title'] ?: substr($item['feedback_text'], 0, 60)) ?>
                                        </strong>
                                        <br>
                                        <small class="text-body-tertiary">
                                            <?= esc_html(explode('@', $item['user_email'])[0]) ?>
                                            <?php if (!empty($item['shortcode'])): ?>
                                                &middot; <?= esc_html($item['shortcode']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div style="width: 90px;" class="text-center flex-shrink-0">
                                        <span class="badge badge-phoenix badge-phoenix-<?= $categoryBadge ?>">
                                            <?= esc_html($categoryLabel) ?>
                                        </span>
                                    </div>
                                    <div style="width: 70px;" class="text-center flex-shrink-0">
                                        <span class="badge badge-phoenix badge-phoenix-<?= $priorityBadge ?>">
                                            <?= esc_html($item['ai_suggested_priority'] ?? 'Medium') ?>
                                        </span>
                                    </div>
                                    <div style="width: 110px;" class="text-center flex-shrink-0">
                                        <small><?= esc_html(date('M j, H:i', strtotime($item['created_at']))) ?></small>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="<?= $collapseId ?>" class="accordion-collapse collapse"
                             aria-labelledby="<?= $headerId ?>"
                             data-bs-parent="#feedbackAccordion">
                            <div class="accordion-body px-4 py-3">
                                <?php
                                    // Build lean report data for clipboard
                                    $firstLine = strtok($item['feedback_text'], "\n");
                                    $reportData = [
                                        'title'       => $item['ai_generated_title'] ?: substr($item['feedback_text'], 0, 60),
                                        'priority'    => $item['ai_suggested_priority'] ?? 'Medium',
                                        'category'    => $categoryLabel,
                                        'date'        => date('M j, Y H:i', strtotime($item['created_at'])),
                                        'reporter'    => $item['user_email'],
                                        'page'        => $item['page_title'] ?: 'N/A',
                                        'url'         => $item['page_url'] ?? '',
                                        'shortcode'   => $item['shortcode'] ?: '',
                                        'description' => trim($firstLine),
                                        'conversation' => $conversation,
                                        'screenshot'  => $screenshotUrl,
                                    ];
                                ?>
                                <div class="d-flex justify-content-end mb-2">
                                    <button type="button"
                                            class="btn btn-phoenix-secondary btn-sm wecoza-feedback-copy-btn"
                                            data-report="<?= esc_attr(json_encode($reportData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
                                            title="Copy report for IDE">
                                        <span class="fas fa-copy me-1"></span>Copy Report
                                    </button>
                                </div>
                                <div class="row g-3">
                                    <!-- Left: Feedback text + conversation -->
                                    <div class="col-md-<?= $screenshotUrl ? '8' : '12' ?>">
                                        <!-- Feedback text -->
                                        <div class="bg-body-highlight rounded-3 p-3 mb-3">
                                            <h6 class="fs-9 fw-bold text-body-secondary mb-2">
                                                <span class="fas fa-comment-alt fa-xs me-1"></span>Feedback
                                            </h6>
                                            <p class="mb-0 fs-9"><?= nl2br(esc_html($item['feedback_text'])) ?></p>
                                        </div>

                                        <!-- AI Conversation -->
                                        <?php if (!empty($conversation)): ?>
                                            <div class="bg-body-highlight rounded-3 p-3 mb-3">
                                                <h6 class="fs-9 fw-bold text-body-secondary mb-2">
                                                    <span class="fas fa-robot fa-xs me-1"></span>AI Conversation
                                                </h6>
                                                <?php foreach ($conversation as $round): ?>
                                                    <div class="mb-2 fs-9">
                                                        <div class="text-body-secondary">
                                                            <span class="fas fa-robot fa-xs me-1 text-primary"></span>
                                                            <?= esc_html($round['question'] ?? '') ?>
                                                        </div>
                                                        <?php if (!empty($round['answer'])): ?>
                                                            <div class="ms-3 mt-1">
                                                                <span class="fas fa-user fa-xs me-1 text-body-tertiary"></span>
                                                                <?= esc_html($round['answer']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Metadata -->
                                        <div class="border rounded-3 p-3">
                                            <h6 class="fs-9 fw-bold text-body-secondary mb-2">
                                                <span class="fas fa-info-circle fa-xs me-1"></span>Context
                                            </h6>
                                            <div class="row g-2 fs-10">
                                                <div class="col-sm-6">
                                                    <span class="fw-semibold text-body-secondary">Page:</span>
                                                    <?= esc_html($item['page_title'] ?: 'N/A') ?>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="fw-semibold text-body-secondary">URL:</span>
                                                    <a href="<?= esc_url($item['page_url'] ?? '') ?>" target="_blank" class="text-body-tertiary text-decoration-none">
                                                        <?= esc_html($item['page_url'] ?? 'N/A') ?>
                                                    </a>
                                                </div>
                                                <?php if (!empty($item['shortcode'])): ?>
                                                    <div class="col-sm-6">
                                                        <span class="fw-semibold text-body-secondary">Shortcode:</span>
                                                        <code class="fs-10"><?= esc_html($item['shortcode']) ?></code>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="col-sm-6">
                                                    <span class="fw-semibold text-body-secondary">User:</span>
                                                    <?= esc_html($item['user_email']) ?>
                                                </div>
                                                <?php if (!empty($urlParams)): ?>
                                                    <div class="col-sm-6">
                                                        <span class="fw-semibold text-body-secondary">URL Params:</span>
                                                        <code class="fs-10"><?= esc_html(json_encode($urlParams, JSON_UNESCAPED_SLASHES)) ?></code>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="col-sm-6">
                                                    <span class="fw-semibold text-body-secondary">Browser:</span>
                                                    <?= esc_html($item['browser_info'] ?? 'N/A') ?>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="fw-semibold text-body-secondary">Viewport:</span>
                                                    <?= esc_html($item['viewport'] ?? 'N/A') ?>
                                                </div>
                                                <?php if ($isResolved && !empty($item['resolved_at'])): ?>
                                                    <div class="col-sm-6">
                                                        <span class="fw-semibold text-body-secondary">Resolved:</span>
                                                        <?= esc_html(date('M j, Y H:i', strtotime($item['resolved_at']))) ?>
                                                        by <?= esc_html($item['resolved_by'] ?? '') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Right: Screenshot -->
                                    <?php if ($screenshotUrl): ?>
                                        <div class="col-md-4">
                                            <div class="border rounded-3 p-3 h-100">
                                                <h6 class="fs-9 fw-bold text-body-secondary mb-2">
                                                    <span class="fas fa-camera fa-xs me-1"></span>Screenshot
                                                </h6>
                                                <a href="<?= esc_url($screenshotUrl) ?>" target="_blank">
                                                    <img src="<?= esc_url($screenshotUrl) ?>" alt="Screenshot"
                                                         class="rounded border w-100" style="max-height: 300px; object-fit: contain;">
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
