<?php
/**
 * Excessive Hours Report Dashboard
 *
 * Standalone dashboard showing learners with excessive training hours.
 * Data loads via AJAX DataTable. Resolution workflow is inline.
 *
 * @package WeCoza\Reports\ExcessiveHours
 * @var int $openCount Current open flag count
 * @var array $clients Client list for filter dropdown
 * @var array $classTypes Applicable class type codes
 * @var array $actionLabels Resolution action labels
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="excessive-hours-container">

    <!-- Alert Container -->
    <div id="eh-alert" class="alert-container"></div>

    <!-- Loading Spinner (shown until DataTable inits) -->
    <div id="eh-loading" class="d-flex justify-content-center align-items-center py-4">
        <div class="spinner-border text-primary me-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="text-muted">Loading excessive hours data...</span>
    </div>

    <!-- Main Content -->
    <div id="eh-content" class="d-none">

        <div class="card shadow-none border my-3" data-component-card="data-component-card">

            <!-- Card Header -->
            <div class="card-header p-3 border-bottom">

                <!-- Row 1: Title + Filters -->
                <div class="row g-3 justify-content-between align-items-center mb-3">
                    <div class="col-12 col-md">
                        <h5 class="text-body mb-0">
                            Excessive Training Hours <i class="bi bi-exclamation-triangle-fill text-warning ms-2"></i>
                        </h5>
                        <small class="text-muted">Learners exceeding allocated programme hours</small>
                    </div>
                    <div class="col-auto">
                        <input type="text" id="eh-search"
                               class="form-control form-control-sm"
                               placeholder="Search learner, class, client..."
                               style="min-width:200px;">
                    </div>
                    <div class="col-auto">
                        <select id="eh-filter-client" class="form-select form-select-sm" style="min-width:180px;">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo esc_attr($client['client_id']); ?>">
                                    <?php echo esc_html($client['client_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select id="eh-filter-class-type" class="form-select form-select-sm" style="min-width:160px;">
                            <option value="">All Programmes</option>
                            <?php
                            $typeLabels = [
                                'AET' => 'AET', 'REALLL' => 'REALLL', 'GETC' => 'GETC AET',
                                'BA2' => 'Business Admin NQF 2', 'BA3' => 'Business Admin NQF 3',
                                'BA4' => 'Business Admin NQF 4', 'ASC' => 'Adult Matric',
                            ];
                            foreach ($classTypes as $code):
                            ?>
                                <option value="<?php echo esc_attr($code); ?>">
                                    <?php echo esc_html($typeLabels[$code] ?? $code); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Summary Stats -->
                <div class="col-12">
                    <div class="scrollbar">
                        <div class="row g-0 flex-nowrap">
                            <div class="col-auto border-end pe-4">
                                <h6 class="text-body-tertiary mb-0">
                                    Open Flags: <span id="eh-stat-open" class="fw-bold text-danger"><?php echo esc_html($openCount); ?></span>
                                </h6>
                            </div>
                            <div class="col-auto px-4 border-end">
                                <h6 class="text-body-tertiary mb-0">
                                    Resolved (30d): <span id="eh-stat-resolved" class="fw-bold text-success">0</span>
                                </h6>
                            </div>
                            <div class="col-auto px-4">
                                <h6 class="text-body-tertiary mb-0">
                                    Total Flagged: <span id="eh-stat-total" class="fw-bold">0</span>
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Body -->
            <div class="card-body p-4 py-2">

                <!-- Status Pills -->
                <div id="eh-status-pills" class="mb-3 mt-2">
                    <button class="btn btn-sm btn-phoenix-secondary me-1 active" data-status="open">
                        Open <span class="badge bg-danger ms-1" id="eh-pill-open"><?php echo esc_html($openCount); ?></span>
                    </button>
                    <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="resolved">
                        Resolved <span class="badge bg-success ms-1" id="eh-pill-resolved">0</span>
                    </button>
                    <button class="btn btn-sm btn-phoenix-secondary me-1" data-status="all">All</button>
                </div>

                <!-- DataTable -->
                <div class="table-responsive">
                    <table id="eh-table" class="table table-sm table-hover fs-9 mb-0" style="width:100%">
                        <thead>
                            <tr class="bg-body-highlight">
                                <th>Learner</th>
                                <th>Class</th>
                                <th>Programme</th>
                                <th>Client</th>
                                <th class="text-end">Trained</th>
                                <th class="text-end">Allocated</th>
                                <th class="text-end">Overage</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div id="eh-empty" class="d-none text-center py-5">
                    <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                    <span class="text-muted">No excessive hours flagged. All learners are within allocated hours.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Resolve Modal -->
    <div class="modal fade" id="eh-resolve-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Excessive Hours Flag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <strong>Learner:</strong> <span id="eh-resolve-learner"></span><br>
                        <strong>Class:</strong> <span id="eh-resolve-class"></span><br>
                        <strong>Overage:</strong> <span id="eh-resolve-overage" class="text-danger fw-bold"></span> hours
                    </p>

                    <input type="hidden" id="eh-resolve-tracking-id">

                    <div class="mb-3">
                        <label for="eh-resolve-action" class="form-label">Action Taken <span class="text-danger">*</span></label>
                        <select id="eh-resolve-action" class="form-select" required>
                            <option value="">Select action...</option>
                            <?php foreach ($actionLabels as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>">
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="eh-resolve-notes" class="form-label">Notes</label>
                        <textarea id="eh-resolve-notes" class="form-control" rows="3"
                                  placeholder="Optional — describe what was done or decided..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="eh-resolve-submit">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="eh-resolve-spinner"></span>
                        Resolve Flag
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="eh-history-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resolution History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="eh-history-content"></div>
                </div>
            </div>
        </div>
    </div>
</div>
