<?php
/**
 * Audit Log Table View
 *
 * Renders a filterable table of audit log entries using the Phoenix card + table pattern.
 * Used by the [wecoza_audit_log] shortcode.
 *
 * NOTE FOR FUTURE DEVELOPMENT:
 * ────────────────────────────
 * All WeCoza list/table views MUST follow the Phoenix card pattern from the classes display:
 *   1. Outer: <div class="card shadow-none border my-3" data-component-card="data-component-card">
 *   2. Card header with: title + search box + action buttons
 *   3. Summary strip: <div class="scrollbar"><div class="row g-0 flex-nowrap"> with border-end cols
 *   4. Card body: <div class="card-body p-4 py-2"> wrapping table-responsive
 *   5. Table: class="table table-hover table-sm fs-9 mb-0 overflow-hidden"
 *   6. Thead: class="border-bottom" with border-0 th cells
 *   7. Action badges use: badge-phoenix badge-phoenix-{color} pattern
 *   8. Status badges: badge-phoenix with badge-label span
 *   Reference: views/classes/components/classes-display.view.php
 *
 * Available variables:
 *  - $entries: Array of audit log rows
 *  - $filter_type: Current entity type filter (or null)
 *  - $page: Current page number
 *  - $limit: Entries per page
 *  - $has_more: Whether there are more entries
 *
 * @package WeCoza\Views\Components
 */

defined('ABSPATH') || exit;

$entries = $entries ?? [];
$filter_type = $filter_type ?? null;
$page = $page ?? 1;
$limit = $limit ?? 50;
$has_more = $has_more ?? false;

$entityTypes = ['class', 'learner', 'agent', 'client'];
$currentUrl = remove_query_arg(['audit_page']);
$totalEntries = count($entries);

// Map action codes to Phoenix badge colours
$actionBadgeMap = [
    'CLASS_CREATED' => 'success', 'CLASS_UPDATED' => 'info', 'CLASS_STATUS_CHANGED' => 'warning',
    'CLASS_AGENT_ASSIGNED' => 'primary', 'CLASS_AGENT_REMOVED' => 'secondary',
    'CLASS_LEARNER_ADDED' => 'success', 'CLASS_LEARNER_REMOVED' => 'secondary',
    'LEARNER_CREATED' => 'success', 'LEARNER_UPDATED' => 'info', 'LEARNER_DELETED' => 'danger',
    'LEARNER_LP_STARTED' => 'primary', 'LEARNER_LP_COMPLETED' => 'success',
    'LEARNER_LP_STATUS_CHANGED' => 'warning', 'LEARNER_EXAM_RECORDED' => 'info',
    'LEARNER_PORTFOLIO_UPLOADED' => 'primary',
    'AGENT_CREATED' => 'success', 'AGENT_UPDATED' => 'info',
    'AGENT_ASSIGNED' => 'primary', 'AGENT_REMOVED' => 'secondary',
    'CLIENT_CREATED' => 'success', 'CLIENT_UPDATED' => 'info', 'CLIENT_DELETED' => 'danger',
];
?>

<div class="wecoza-audit-log">
    <div class="card shadow-none border my-3" data-component-card="data-component-card">

        <!-- Card Header -->
        <div class="card-header p-3 border-bottom">
            <div class="row g-3 justify-content-between align-items-center mb-3">
                <div class="col-12 col-md">
                    <h5 class="text-body mb-0" data-anchor="data-anchor">
                        Audit Log
                        <i class="bi bi-shield-check ms-2"></i>
                    </h5>
                </div>
                <div class="col-auto">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="wecozaExportAuditLog()">
                            Export
                            <i class="bi bi-download ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Strip -->
            <div class="col-12">
                <div class="scrollbar">
                    <div class="row g-0 flex-nowrap">
                        <div class="col-auto border-end pe-4">
                            <h6 class="text-body-tertiary">Showing : <?php echo $totalEntries; ?></h6>
                        </div>
                        <div class="col-auto px-4">
                            <h6 class="text-body-tertiary">Page : <?php echo (int) $page; ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Body -->
        <div class="card-body p-4 py-2">
            <?php if (empty($entries)): ?>
                <div class="alert alert-subtle-info d-flex align-items-center my-3">
                    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                    <div>
                        <h6 class="alert-heading mb-1">No Audit Log Entries</h6>
                        <p class="mb-0">Entries are recorded when classes, learners, agents, or clients are created, updated, or have status changes.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                        <thead class="border-bottom">
                            <tr>
                                <th scope="col" class="border-0 ps-4" style="width: 160px;">
                                    Date
                                    <i class="bi bi-calendar3 ms-1"></i>
                                </th>
                                <th scope="col" class="border-0" style="width: 200px;">
                                    Action
                                    <i class="bi bi-lightning ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Details
                                    <i class="bi bi-card-text ms-1"></i>
                                </th>
                                <th scope="col" class="border-0" style="width: 120px;">
                                    User
                                    <i class="bi bi-person ms-1"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <?php
                                $context = $entry['context_parsed'] ?? [];
                                $entityType = $context['entity_type'] ?? null;
                                $entityId = $context['entity_id'] ?? null;
                                $message = $entry['message'] ?? '';
                                $action = $entry['action'] ?? '—';
                                $createdAt = $entry['created_at'] ?? '';

                                if ($createdAt) {
                                    $timestamp = strtotime($createdAt);
                                    $dateDisplay = $timestamp ? date('M d, Y H:i', $timestamp) : $createdAt;
                                } else {
                                    $dateDisplay = '—';
                                }

                                // Build details: prefer message, fall back to entity_type #entity_id
                                if (!empty($message)) {
                                    $details = $message;
                                } elseif ($entityType && $entityId) {
                                    $details = ucfirst($entityType) . ' #' . $entityId;
                                } else {
                                    $details = '—';
                                }

                                $badgeColor = $actionBadgeMap[$action] ?? 'secondary';
                                ?>
                                <tr>
                                    <td class="ps-4 text-body-tertiary align-middle"><?php echo esc_html($dateDisplay); ?></td>
                                    <td class="align-middle">
                                        <span class="badge badge-phoenix badge-phoenix-<?php echo esc_attr($badgeColor); ?>">
                                            <span class="badge-label"><?php echo esc_html($action); ?></span>
                                            <?php if (str_contains($action, 'CREATED')): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-plus-circle ms-1" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/></svg>
                                            <?php elseif (str_contains($action, 'DELETED')): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-x-circle ms-1" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/></svg>
                                            <?php elseif (str_contains($action, 'CHANGED') || str_contains($action, 'UPDATED')): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-pencil ms-1" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="align-middle fw-semibold"><?php echo esc_html($details); ?></td>
                                    <td class="align-middle text-body-tertiary"><?php echo esc_html($entry['user_display'] ?? 'Unknown'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <h6 class="text-body-tertiary mb-0">
                        Showing <?php echo $totalEntries; ?> entries (page <?php echo (int) $page; ?>)
                    </h6>
                    <div class="d-flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg('audit_page', $page - 1)); ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-chevron-left me-1"></i> Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($has_more): ?>
                            <a href="<?php echo esc_url(add_query_arg('audit_page', $page + 1)); ?>"
                               class="btn btn-sm btn-outline-secondary">
                                Next <i class="bi bi-chevron-right ms-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function wecozaExportAuditLog() {
    var table = document.querySelector('.wecoza-audit-log table');
    if (!table) { alert('No data to export.'); return; }

    var rows = [];
    // Header
    rows.push(['Date', 'Action', 'Details', 'User']);

    // Data rows
    var trs = table.querySelectorAll('tbody tr');
    trs.forEach(function(tr) {
        var cells = tr.querySelectorAll('td');
        if (cells.length >= 4) {
            rows.push([
                cells[0].textContent.trim(),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].textContent.trim()
            ]);
        }
    });

    // Build CSV
    var csv = rows.map(function(row) {
        return row.map(function(cell) {
            // Escape quotes and wrap in quotes
            return '"' + String(cell).replace(/"/g, '""') + '"';
        }).join(',');
    }).join('\r\n');

    // Download
    var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'audit-log-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
