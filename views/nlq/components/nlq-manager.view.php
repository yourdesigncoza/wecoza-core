<?php
/**
 * NLQ Query Manager View
 *
 * Frontend admin interface for creating, editing, previewing, and managing saved queries.
 * Uses Phoenix design patterns consistent with Classes/Agents/Clients displays.
 *
 * Available Variables:
 *   - $queries:    Array of all saved queries
 *   - $categories: Array of distinct category strings
 *   - $nonce:      Nonce value for AJAX calls
 *
 * @package WeCoza\NLQ
 */

defined('ABSPATH') || exit;

$queries    = $queries ?? [];
$categories = $categories ?? [];
$nonce      = $nonce ?? '';

// Compute summary stats
$total_queries  = count($queries);
$active_queries = count(array_filter($queries, fn($q) => ($q['is_active'] ?? true)));
$total_executions = array_sum(array_column($queries, 'execution_count'));
$unique_categories = count(array_unique(array_filter(array_column($queries, 'category'))));
?>

<div class="wecoza-nlq-manager" id="nlq-manager">

    <!-- ─── Tabs ───────────────────────────────────────────── -->
    <ul class="nav nav-underline mb-4" id="nlq-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-queries" data-bs-toggle="tab" data-bs-target="#panel-queries" type="button" role="tab">
                <i class="bi bi-list-ul me-1"></i> Saved Queries
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-create" data-bs-toggle="tab" data-bs-target="#panel-create" type="button" role="tab">
                <i class="bi bi-plus-circle me-1"></i> Create Query
            </button>
        </li>
    </ul>

    <div class="tab-content" id="nlq-tab-content">

        <!-- ════════════════════════════════════════════════════ -->
        <!-- TAB 1: Saved Queries List                           -->
        <!-- ════════════════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="panel-queries" role="tabpanel">

            <?php if (empty($queries)): ?>
                <div class="alert alert-subtle-info d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                    <div>
                        <h6 class="alert-heading mb-1">No Saved Queries</h6>
                        <p class="mb-0">Create your first query using the "Create Query" tab above.</p>
                    </div>
                </div>
            <?php else: ?>

            <div class="card shadow-none border" data-component-card="data-component-card">
                <!-- Card Header -->
                <div class="card-header p-3 border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <h5 class="text-body mb-0 text-nowrap" data-anchor="data-anchor">
                            Saved Queries
                            <i class="bi bi-database ms-2"></i>
                        </h5>
                        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                            <div class="search-box">
                                <form class="position-relative">
                                    <input class="form-control search-input search form-control-sm" type="search"
                                           placeholder="Search" aria-label="Search" id="nlq-search">
                                    <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg>
                                </form>
                            </div>
                            <select id="nlq-filter-category" class="form-select form-select-sm" style="width: 160px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                                Refresh
                                <i class="bi bi-arrow-clockwise ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Summary Strip -->
                    <div class="col-12">
                        <div class="scrollbar">
                            <div class="row g-0 flex-nowrap">
                                <div class="col-auto border-end pe-4">
                                    <h6 class="text-body-tertiary">Total Queries : <?php echo esc_html((string) $total_queries); ?></h6>
                                </div>
                                <div class="col-auto px-4 border-end">
                                    <h6 class="text-body-tertiary">Active : <?php echo esc_html((string) $active_queries); ?></h6>
                                </div>
                                <div class="col-auto px-4 border-end">
                                    <h6 class="text-body-tertiary">Total Executions : <?php echo esc_html((string) $total_executions); ?></h6>
                                </div>
                                <div class="col-auto px-4">
                                    <h6 class="text-body-tertiary">Categories : <?php echo esc_html((string) $unique_categories); ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Body / Table -->
                <div class="card-body p-4 py-2">
                    <div class="table-responsive">
                        <table id="nlq-queries-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                            <thead class="border-bottom">
                                <tr>
                                    <th scope="col" class="border-0 ps-4">
                                        ID <i class="bi bi-hash ms-1"></i>
                                    </th>
                                    <th scope="col" class="border-0">
                                        Query <i class="bi bi-tag ms-1"></i>
                                    </th>
                                    <th scope="col" class="border-0">
                                        Category <i class="bi bi-folder ms-1"></i>
                                    </th>
                                    <th scope="col" class="border-0">
                                        Description <i class="bi bi-text-left ms-1"></i>
                                    </th>
                                    <th scope="col" class="border-0">
                                        Runs <i class="bi bi-play-circle ms-1"></i>
                                    </th>
                                    <th scope="col" class="border-0">
                                        Last Executed <i class="bi bi-clock ms-1"></i>
                                    </th>
                                    <th scope="col" class="border-0">
                                        Shortcode <i class="bi bi-code-slash ms-1"></i>
                                    </th>
                                    <th scope="col" class="border-0 pe-4">
                                        Actions <i class="bi bi-gear ms-1"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queries as $q): 
                                    $isActive = ($q['is_active'] ?? true);
                                ?>
                                <tr data-query-id="<?php echo esc_attr((string) $q['id']); ?>"
                                    data-category="<?php echo esc_attr($q['category'] ?? ''); ?>"
                                    class="<?php echo !$isActive ? 'opacity-50' : ''; ?>">

                                    <!-- ID Badge -->
                                    <td class="py-2 align-middle text-center fs-8 white-space-nowrap ps-4">
                                        <span class="badge fs-10 badge-phoenix badge-phoenix-secondary">
                                            #<?php echo esc_html((string) $q['id']); ?>
                                        </span>
                                    </td>

                                    <!-- Name -->
                                    <td class="py-2 align-middle">
                                        <span class="fw-medium"><?php echo esc_html($q['query_name']); ?></span>
                                        <?php if (!$isActive): ?>
                                            <span class="badge badge-phoenix badge-phoenix-warning ms-1 fs-10">Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Category -->
                                    <td class="py-2 align-middle fs-8 white-space-nowrap">
                                        <?php if (!empty($q['category'])): ?>
                                            <span class="badge badge-phoenix badge-phoenix-info"><?php echo esc_html($q['category']); ?></span>
                                        <?php else: ?>
                                            <span class="text-body-tertiary">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Description -->
                                    <td class="py-2 align-middle">
                                        <small class="text-body-tertiary"><?php echo esc_html(mb_strimwidth($q['description'] ?? '', 0, 80, '…')); ?></small>
                                    </td>

                                    <!-- Execution Count -->
                                    <td class="py-2 align-middle text-center">
                                        <?php $execCount = (int) ($q['execution_count'] ?? 0); ?>
                                        <?php if ($execCount > 0): ?>
                                            <span class="badge badge-phoenix badge-phoenix-success fs-10"><?php echo esc_html((string) $execCount); ?></span>
                                        <?php else: ?>
                                            <span class="text-body-tertiary">0</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Last Executed -->
                                    <td class="py-2 align-middle">
                                        <?php if (!empty($q['last_executed'])): ?>
                                            <span class="text-nowrap"><?php echo esc_html(wp_date('M j, Y g:i A', strtotime($q['last_executed']))); ?></span>
                                        <?php else: ?>
                                            <span class="text-body-tertiary">Never</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Shortcode -->
                                    <td class="py-2 align-middle">
                                        <code class="nlq-shortcode-copy user-select-all" role="button" title="Click to copy"
                                              data-shortcode='[wecoza_nlq_table query_id="<?php echo esc_attr((string) $q['id']); ?>"]'
                                              style="font-size: 0.75rem; cursor: pointer;">
                                            [wecoza_nlq_table query_id="<?php echo esc_html((string) $q['id']); ?>"]
                                        </code>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-2 align-middle text-center pe-4">
                                        <div class="d-flex justify-content-center gap-2" role="group">
                                            <button class="btn btn-sm btn-outline-secondary border-0 nlq-preview-btn"
                                                    data-id="<?php echo esc_attr((string) $q['id']); ?>" title="Preview Results">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary border-0 nlq-edit-btn"
                                                    data-id="<?php echo esc_attr((string) $q['id']); ?>" title="Edit Query">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary border-0 nlq-delete-btn"
                                                    data-id="<?php echo esc_attr((string) $q['id']); ?>" title="Deactivate">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════════════════════ -->
        <!-- TAB 2: Create / Edit Query                          -->
        <!-- ════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="panel-create" role="tabpanel">

            <div class="card shadow-none border" data-component-card="data-component-card">
                <div class="card-header p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="text-body mb-0" id="nlq-form-title">
                        Create New Query
                        <i class="bi bi-plus-circle ms-2"></i>
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" id="nlq-form-reset" style="display:none;">
                        Cancel Edit <i class="bi bi-x-circle ms-1"></i>
                    </button>
                </div>

                <div class="card-body p-4">
                    <form id="nlq-query-form">
                        <input type="hidden" id="nlq-query-id" value="">

                        <div class="row mb-3 g-3">
                            <div class="col-md-8">
                                <label for="nlq-query-name" class="form-label fw-medium">
                                    Query Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="nlq-query-name"
                                       placeholder="e.g. Active Agents Today" required>
                            </div>
                            <div class="col-md-4">
                                <label for="nlq-query-category" class="form-label fw-medium">Category</label>
                                <input type="text" class="form-control" id="nlq-query-category"
                                       placeholder="e.g. Agents, Learners" list="nlq-category-list">
                                <datalist id="nlq-category-list">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nlq-query-description" class="form-label fw-medium">Description</label>
                            <textarea class="form-control" id="nlq-query-description" rows="2"
                                      placeholder="What does this query show?"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="nlq-natural-language" class="form-label fw-medium">
                                Natural Language Request
                                <small class="text-body-tertiary fw-normal ms-1">(what the user originally asked)</small>
                            </label>
                            <textarea class="form-control" id="nlq-natural-language" rows="2"
                                      placeholder="e.g. Show me all agents who are active today"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="nlq-sql-query" class="form-label fw-medium">
                                SQL Query <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control font-monospace" id="nlq-sql-query" rows="6"
                                      placeholder="SELECT * FROM agents WHERE ..." required
                                      style="font-size: 0.85rem;"></textarea>
                            <div class="form-text">
                                <i class="bi bi-shield-check me-1"></i>
                                Only SELECT queries are allowed. The system enforces read-only access.
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="nlq-preview-sql">
                                <i class="bi bi-play-circle me-1"></i> Preview Results
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm" id="nlq-save-btn">
                                <i class="bi bi-save me-1"></i> Save Query
                            </button>
                        </div>
                    </form>

                    <!-- Preview Results Area -->
                    <div id="nlq-preview-area" class="mt-4" style="display: none;">
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Preview Results</h6>
                            <span id="nlq-preview-info"></span>
                        </div>
                        <div class="table-responsive">
                            <table id="nlq-preview-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                                <thead class="border-bottom"><tr></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Success Message -->
            <div id="nlq-save-result" class="alert alert-subtle-success mt-3 d-flex align-items-center d-none">
                <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                <div>
                    <strong>Query saved!</strong> Use this shortcode to display it:
                    <code id="nlq-save-shortcode" class="ms-2"></code>
                    <button class="btn btn-sm btn-outline-secondary ms-2 nlq-copy-shortcode">
                        <i class="bi bi-clipboard me-1"></i> Copy
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- ─── Preview Modal ──────────────────────────────────── -->
    <div class="modal fade" id="nlq-preview-modal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title" id="nlq-modal-title">
                        Query Preview
                        <i class="bi bi-eye ms-2"></i>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Loading -->
                    <div id="nlq-modal-loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-body-tertiary mt-2">Executing query…</p>
                    </div>
                    <!-- Content -->
                    <div id="nlq-modal-content" style="display: none;">
                        <div id="nlq-modal-info" class="mb-3"></div>
                        <div class="table-responsive">
                            <table id="nlq-modal-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                                <thead class="border-bottom"><tr></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Error -->
                    <div id="nlq-modal-error" class="alert alert-subtle-danger d-flex align-items-center d-none">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div id="nlq-modal-error-text"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
