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
// Recount from unfiltered - we only have filtered items, so use the filtered count
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
                <table class="table table-hover mb-0 fs-9">
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?>
                                <th class="text-center" style="width: 50px;">Done</th>
                            <?php endif; ?>
                            <th>Title</th>
                            <th style="width: 100px;">Category</th>
                            <th style="width: 80px;">Priority</th>
                            <th style="width: 140px;">Page</th>
                            <th style="width: 130px;">User</th>
                            <th style="width: 60px;">Img</th>
                            <th style="width: 120px;">Date</th>
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
                        ?>
                            <tr class="<?= $isResolved ? 'opacity-50' : '' ?>" id="feedback-row-<?= (int) $item['id'] ?>">
                                <?php if ($isAdmin): ?>
                                    <td class="text-center align-middle">
                                        <button type="button"
                                                class="btn btn-sm p-0 border-0 wecoza-feedback-resolve-btn"
                                                data-feedback-id="<?= (int) $item['id'] ?>"
                                                title="<?= $isResolved ? 'Mark as open' : 'Mark as resolved' ?>">
                                            <?php if ($isResolved): ?>
                                                <span class="fas fa-check-circle text-success fs-7"></span>
                                            <?php else: ?>
                                                <span class="far fa-circle text-body-tertiary fs-7"></span>
                                            <?php endif; ?>
                                        </button>
                                    </td>
                                <?php endif; ?>
                                <td class="align-middle">
                                    <strong class="<?= $isResolved ? 'text-decoration-line-through' : '' ?>">
                                        <?= esc_html($item['ai_generated_title'] ?: substr($item['feedback_text'], 0, 60)) ?>
                                    </strong>
                                    <?php if ($isResolved && !empty($item['resolved_at'])): ?>
                                        <br><small class="text-body-tertiary">
                                            Resolved <?= esc_html(date('M j', strtotime($item['resolved_at']))) ?>
                                        </small>
                                    <?php endif; ?>
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
                                <td class="align-middle">
                                    <small class="text-truncate d-block" style="max-width: 140px;" title="<?= esc_attr($item['page_url'] ?? '') ?>">
                                        <?= esc_html($item['page_title'] ?: 'N/A') ?>
                                    </small>
                                </td>
                                <td class="align-middle">
                                    <small><?= esc_html(explode('@', $item['user_email'])[0]) ?></small>
                                </td>
                                <td class="align-middle text-center">
                                    <?php if ($screenshotUrl): ?>
                                        <a href="<?= esc_url($screenshotUrl) ?>" target="_blank" title="View screenshot">
                                            <img src="<?= esc_url($screenshotUrl) ?>" alt="Screenshot"
                                                 class="rounded" style="width: 40px; height: 28px; object-fit: cover;">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-body-tertiary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <small><?= esc_html(date('M j, H:i', strtotime($item['created_at']))) ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
