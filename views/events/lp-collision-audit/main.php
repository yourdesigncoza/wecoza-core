<?php
/**
 * LP Collision Audit Trail Template
 *
 * @var array<int, array<string, mixed>> $records Presented collision events
 */

if (!defined("ABSPATH")) {
    exit();
} ?>
<div class="wecoza-lp-collision-audit" id="lp-collision-audit">
    <div class="card h-100">
        <!-- Header -->
        <div class="card-header p-3 border-bottom">
            <div class="row g-3 justify-content-between align-items-center">
                <div class="col-12 col-md">
                    <h4 class="text-body-emphasis mb-0">
                        LP Collision Audit Trail
                        <i class="bi bi-shield-exclamation ms-2"></i>
                    </h4>
                    <p class="text-body-tertiary fs-9 mb-0 mt-1">
                        Record of Learner Program collisions acknowledged during learner assignment
                    </p>
                </div>
                <div class="search-box col-auto">
                    <form class="position-relative" onsubmit="return false;">
                        <input
                            type="search"
                            class="form-control search-input search form-control-sm"
                            id="lp-collision-search"
                            placeholder="Search by name, class code, subject..."
                            aria-label="Search">
                        <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                        </svg>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card-body p-0">
            <?php if (empty($records)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle fs-3 text-success"></i>
                    <p class="text-body-tertiary mt-2 mb-0">No LP collision acknowledgements recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive scrollbar" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-hover table-sm fs-9 mb-0 overflow-hidden" id="lp-collision-table">
                        <thead class="border-bottom bg-body">
                            <tr>
                                <th scope="col" class="border-0 ps-3" style="min-width: 140px;">Date</th>
                                <th scope="col" class="border-0">Acknowledged By</th>
                                <th scope="col" class="border-0">Class</th>
                                <th scope="col" class="border-0">Affected Learners</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr data-search-index="<?php echo $record[
                                    "search_index"
                                ]; ?>">
                                    <td class="ps-3 align-middle">
                                        <span class="fw-semibold"><?php echo $record[
                                            "date"
                                        ]; ?></span>
                                    </td>
                                    <td class="align-middle">
                                        <?php echo $record[
                                            "acknowledged_by"
                                        ]; ?>
                                    </td>
                                    <td class="align-middle">
                                        <span class="fw-semibold"><?php echo $record[
                                            "class_code"
                                        ]; ?></span>
                                        <?php if ($record["class_type"]): ?>
                                            <br><span class="text-body-tertiary fs-10"><?php echo $record[
                                                "class_type"
                                            ]; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <?php if (
                                            $record["learner_count"] <= 3
                                        ): ?>
                                            <?php foreach (
                                                $record["learners"]
                                                as $learner
                                            ): ?>
                                                <div class="mb-1">
                                                    <?php echo $learner[
                                                        "name"
                                                    ]; ?>
                                                    <?php if (
                                                        $learner["subject_name"]
                                                    ): ?>
                                                        <span class="badge badge-phoenix badge-phoenix-warning fs-10 ms-1"><?php echo $learner[
                                                            "subject_name"
                                                        ]; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="lp-collision-learners-summary">
                                                <a href="#" class="text-primary fs-9 lp-collision-toggle" data-event-id="<?php echo (int) $record[
                                                    "event_id"
                                                ]; ?>">
                                                    <?php echo $record[
                                                        "learner_count"
                                                    ]; ?> learners
                                                    <i class="bi bi-chevron-down ms-1 fs-10"></i>
                                                </a>
                                                <div class="lp-collision-learners-detail mt-1" id="collision-detail-<?php echo (int) $record[
                                                    "event_id"
                                                ]; ?>" hidden>
                                                    <?php foreach (
                                                        $record["learners"]
                                                        as $learner
                                                    ): ?>
                                                        <div class="mb-1">
                                                            <?php echo $learner[
                                                                "name"
                                                            ]; ?>
                                                            <?php if (
                                                                $learner[
                                                                    "subject_name"
                                                                ]
                                                            ): ?>
                                                                <span class="badge badge-phoenix badge-phoenix-warning fs-10 ms-1"><?php echo $learner[
                                                                    "subject_name"
                                                                ]; ?></span>
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

        <!-- Footer -->
        <?php if (!empty($records)): ?>
            <div class="card-footer border-top text-center py-2">
                <p class="mb-0 text-body-tertiary fs-10">
                    Showing <span id="lp-collision-visible"><?php echo count(
                        $records,
                    ); ?></span>
                    of <?php echo count($records); ?> records
                </p>
            </div>
        <?php endif; ?>
    </div>
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
