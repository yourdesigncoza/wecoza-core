<?php
/**
 * Feedback Dashboard View
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
        <div>
            <ul class="nav nav-pills nav-pills-sm" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'open' ? 'active' : '' ?>"
                       href="?feedback_filter=open">Open</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'resolved' ? 'active' : '' ?>"
                       href="?feedback_filter=resolved">Resolved</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>"
                       href="?feedback_filter=all">All</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
            <div class="text-center py-6">
                <span class="fas fa-check-circle text-success fs-3 mb-3 d-block"></span>
                <p class="text-body-tertiary mb-0">No feedback items to show.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 fs-9 wecoza-feedback-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th style="width: 90px;">Category</th>
                            <th style="width: 70px;">Priority</th>
                            <th style="width: 50px;">Img</th>
                            <th style="width: 110px;">Date</th>
                            <?php if ($isAdmin): ?>
                                <th class="text-center" style="width: 45px;">Done</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
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
                        ?>
                            <!-- Summary Row -->
                            <tr class="<?= $isResolved ? 'opacity-50' : '' ?> wecoza-feedback-summary-row"
                                id="feedback-row-<?= $rowId ?>"
                                data-bs-toggle="collapse"
                                data-bs-target="#feedback-detail-<?= $rowId ?>"
                                aria-expanded="false"
                                role="button">
                                <td class="align-middle">
                                    <span class="fas fa-chevron-right fa-xs text-body-tertiary me-1 wecoza-feedback-chevron"></span>
                                    <strong class="<?= $isResolved ? 'text-decoration-line-through' : '' ?>">
                                        <?= esc_html($item['ai_generated_title'] ?: substr($item['feedback_text'], 0, 60)) ?>
                                    </strong>
                                    <br>
                                    <small class="text-body-tertiary ms-3">
                                        <?= esc_html(explode('@', $item['user_email'])[0]) ?>
                                        <?php if (!empty($item['shortcode'])): ?>
                                            &middot; <?= esc_html($item['shortcode']) ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td class="align-middle">
                                    <span class="badge badge-phoenix badge-phoenix-<?= $categoryBadge ?>">
                                        <?= esc_html($categoryLabel) ?>
                                    </span>
                                </td>
                                <td class="align-middle">
                                    <span class="badge badge-phoenix badge-phoenix-<?= $priorityBadge ?>">
                                        <?= esc_html($item['ai_suggested_priority'] ?? 'Medium') ?>
                                    </span>
                                </td>
                                <td class="align-middle text-center">
                                    <?php if ($screenshotUrl): ?>
                                        <img src="<?= esc_url($screenshotUrl) ?>" alt="Screenshot"
                                             class="rounded" style="width: 40px; height: 28px; object-fit: cover;">
                                    <?php else: ?>
                                        <span class="text-body-tertiary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <small><?= esc_html(date('M j, H:i', strtotime($item['created_at']))) ?></small>
                                </td>
                                <?php if ($isAdmin): ?>
                                    <td class="text-center align-middle" onclick="event.stopPropagation();">
                                        <button type="button"
                                                class="btn btn-sm p-0 border-0 wecoza-feedback-resolve-btn"
                                                data-feedback-id="<?= $rowId ?>"
                                                title="<?= $isResolved ? 'Mark as open' : 'Mark as resolved' ?>">
                                            <?php if ($isResolved): ?>
                                                <span class="fas fa-check-circle text-success fs-7"></span>
                                            <?php else: ?>
                                                <span class="far fa-circle text-body-tertiary fs-7"></span>
                                            <?php endif; ?>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <!-- Detail Row (collapsible) -->
                            <tr class="wecoza-feedback-detail-row">
                                <td colspan="<?= $isAdmin ? 6 : 5 ?>" class="p-0 border-0">
                                    <div class="collapse" id="feedback-detail-<?= $rowId ?>">
                                        <div class="px-4 py-3 bg-body-tertiary border-top">
                                            <div class="row g-3">
                                                <!-- Left: Feedback text + conversation -->
                                                <div class="col-md-<?= $screenshotUrl ? '8' : '12' ?>">
                                                    <h6 class="fs-9 fw-bold text-body-secondary mb-1">Feedback</h6>
                                                    <p class="mb-2 fs-9"><?= nl2br(esc_html($item['feedback_text'])) ?></p>

                                                    <?php if (!empty($conversation)): ?>
                                                        <h6 class="fs-9 fw-bold text-body-secondary mb-1 mt-3">AI Conversation</h6>
                                                        <?php foreach ($conversation as $round): ?>
                                                            <div class="mb-2 fs-9">
                                                                <div class="text-body-secondary">
                                                                    <span class="fas fa-robot fa-xs me-1"></span>
                                                                    <?= esc_html($round['question'] ?? '') ?>
                                                                </div>
                                                                <?php if (!empty($round['answer'])): ?>
                                                                    <div class="ms-3">
                                                                        <span class="fas fa-user fa-xs me-1 text-body-tertiary"></span>
                                                                        <?= esc_html($round['answer']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>

                                                    <!-- Metadata -->
                                                    <div class="mt-3 fs-10 text-body-tertiary">
                                                        <div class="row g-2">
                                                            <div class="col-sm-6">
                                                                <span class="fw-semibold">Page:</span>
                                                                <?= esc_html($item['page_title'] ?: 'N/A') ?>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <span class="fw-semibold">URL:</span>
                                                                <a href="<?= esc_url($item['page_url'] ?? '') ?>" target="_blank" class="text-body-tertiary">
                                                                    <?= esc_html($item['page_url'] ?? 'N/A') ?>
                                                                </a>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <span class="fw-semibold">Shortcode:</span>
                                                                <?= esc_html($item['shortcode'] ?: 'N/A') ?>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <span class="fw-semibold">User:</span>
                                                                <?= esc_html($item['user_email']) ?>
                                                            </div>
                                                            <?php if (!empty($urlParams)): ?>
                                                                <div class="col-sm-6">
                                                                    <span class="fw-semibold">URL Params:</span>
                                                                    <?= esc_html(json_encode($urlParams, JSON_UNESCAPED_SLASHES)) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="col-sm-6">
                                                                <span class="fw-semibold">Browser:</span>
                                                                <?= esc_html($item['browser_info'] ?? 'N/A') ?>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <span class="fw-semibold">Viewport:</span>
                                                                <?= esc_html($item['viewport'] ?? 'N/A') ?>
                                                            </div>
                                                            <?php if ($isResolved && !empty($item['resolved_at'])): ?>
                                                                <div class="col-sm-6">
                                                                    <span class="fw-semibold">Resolved:</span>
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
                                                        <h6 class="fs-9 fw-bold text-body-secondary mb-1">Screenshot</h6>
                                                        <a href="<?= esc_url($screenshotUrl) ?>" target="_blank">
                                                            <img src="<?= esc_url($screenshotUrl) ?>" alt="Screenshot"
                                                                 class="rounded border w-100" style="max-height: 300px; object-fit: contain;">
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
