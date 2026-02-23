<?php
/**
 * LP Collision Audit Trail Template
 *
 * @var array<int, array<string, mixed>> $records Presented collision events
 */

if (!defined('ABSPATH')) {
    exit;
}

$totalCount = count($records);
?>
<div class="wecoza-lp-collision-audit" id="lp-collision-audit">

    <div class="card shadow-none border" data-component-card="data-component-card">
        <!-- Card Header -->
        <div class="card-header p-3 border-bottom">
            <div class="row align-items-center">
                <div class="col">
                    <h4 class="mb-0">LP Collision Audit Trail <i class="bi bi-shield-exclamation ms-1"></i></h4>
                    <p class="text-body-tertiary fs-9 mb-0 mt-1">
                        Record of LP collisions acknowledged during learner assignment
                    </p>
                </div>
                <div class="col-auto d-flex align-items-center gap-2">
                    <span class="text-muted fs-9">
                        <span id="lp-collision-visible"><?php echo $totalCount; ?></span>
                        of <?php echo $totalCount; ?> records
                    </span>
                    <?php if (!empty($records)): ?>
                        <div class="search-box">
                            <form class="position-relative" onsubmit="return false;">
                                <input
                                    type="search"
                                    class="form-control search-input search form-control-sm"
                                    id="lp-collision-search"
                                    placeholder="Search..."
                                    aria-label="Search">
                                <i class="bi bi-search position-absolute top-50 translate-middle-y" style="left: 10px; font-size: 0.75rem; color: var(--phoenix-quaternary-color);"></i>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card Body -->
        <div class="card-body p-4 py-2">
            <?php if (empty($records)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                    <span class="text-body-tertiary">No LP collision acknowledgements recorded yet.</span>
                </div>
            <?php else: ?>
                <div class="table-responsive scrollbar" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-hover table-sm fs-9 mb-0 overflow-hidden" id="lp-collision-table">
                        <thead class="text-body">
                            <tr>
                                <th class="sort pe-1 align-middle white-space-nowrap ps-1" style="min-width: 140px;">Date</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Acknowledged By</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Class</th>
                                <th class="sort pe-1 align-middle white-space-nowrap">Affected Learners</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr data-search-index="<?php echo $record['search_index']; ?>">
                                    <td class="py-2 align-middle white-space-nowrap ps-1">
                                        <span class="fw-semibold"><?php echo $record['date']; ?></span>
                                    </td>
                                    <td class="py-2 align-middle white-space-nowrap">
                                        <?php echo $record['acknowledged_by']; ?>
                                    </td>
                                    <td class="py-2 align-middle white-space-nowrap">
                                        <span class="fw-semibold"><?php echo $record['class_code']; ?></span>
                                        <?php if ($record['class_type']): ?>
                                            <br><span class="text-body-tertiary fs-10"><?php echo $record['class_type']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 align-middle">
                                        <?php if ($record['learner_count'] <= 3): ?>
                                            <?php foreach ($record['learners'] as $learner): ?>
                                                <div class="mb-1">
                                                    <?php echo $learner['name']; ?>
                                                    <?php if ($learner['subject_name']): ?>
                                                        <span class="badge badge-phoenix badge-phoenix-warning fs-10 ms-1"><?php echo $learner['subject_name']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="lp-collision-learners-summary">
                                                <a href="#" class="text-primary fs-9 lp-collision-toggle" data-event-id="<?php echo (int) $record['event_id']; ?>">
                                                    <?php echo $record['learner_count']; ?> learners
                                                    <i class="bi bi-chevron-down ms-1 fs-10"></i>
                                                </a>
                                                <div class="lp-collision-learners-detail mt-1" id="collision-detail-<?php echo (int) $record['event_id']; ?>" hidden>
                                                    <?php foreach ($record['learners'] as $learner): ?>
                                                        <div class="mb-1">
                                                            <?php echo $learner['name']; ?>
                                                            <?php if ($learner['subject_name']): ?>
                                                                <span class="badge badge-phoenix badge-phoenix-warning fs-10 ms-1"><?php echo $learner['subject_name']; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- No-results message (hidden by default) -->
                <div id="lp-collision-no-results" class="text-center py-4" hidden>
                    <p class="text-body-tertiary mb-0">No records match your search.</p>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /card -->

</div>

<script>
(function() {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    ready(function() {
        var container = document.getElementById('lp-collision-audit');
        if (!container) return;

        var searchInput = document.getElementById('lp-collision-search');
        var table = document.getElementById('lp-collision-table');
        var noResults = document.getElementById('lp-collision-no-results');
        var visibleSpan = document.getElementById('lp-collision-visible');

        // Client-side search filter
        if (searchInput && table) {
            searchInput.addEventListener('input', function() {
                var term = this.value.toLowerCase().trim();
                var rows = table.querySelectorAll('tbody tr');
                var visible = 0;

                rows.forEach(function(row) {
                    var index = row.getAttribute('data-search-index') || '';
                    var match = term === '' || index.indexOf(term) !== -1;
                    row.style.display = match ? '' : 'none';
                    if (match) visible++;
                });

                if (visibleSpan) {
                    visibleSpan.textContent = visible;
                }

                if (noResults) {
                    noResults.hidden = visible > 0 || rows.length === 0;
                }
            });
        }

        // Expand/collapse learner details
        container.addEventListener('click', function(e) {
            var toggle = e.target.closest('.lp-collision-toggle');
            if (!toggle) return;
            e.preventDefault();

            var eventId = toggle.getAttribute('data-event-id');
            var detail = document.getElementById('collision-detail-' + eventId);
            if (!detail) return;

            var isHidden = detail.hidden;
            detail.hidden = !isHidden;

            var icon = toggle.querySelector('i');
            if (icon) {
                icon.className = isHidden
                    ? 'bi bi-chevron-up ms-1 fs-10'
                    : 'bi bi-chevron-down ms-1 fs-10';
            }

            toggle.childNodes.forEach(function(node) {
                if (node.nodeType === 3 && node.textContent.trim()) {
                    var count = node.textContent.trim().replace(/\D/g, '');
                    node.textContent = count + ' learners ';
                }
            });
        });
    });
})();
</script>
