<?php
/**
 * NLQ AI Query Builder View
 *
 * The AI-powered natural language to SQL interface.
 * User types a question → AI generates SQL → preview results → save.
 *
 * Available Variables:
 *   - $categories: Array of existing category strings
 *   - $nonce:      Nonce for AJAX calls
 *
 * @package WeCoza\NLQ
 */

defined('ABSPATH') || exit;

$categories = $categories ?? [];
$nonce      = $nonce ?? '';
?>

<div class="wecoza-nlq-input" id="nlq-input">

    <!-- ─── Step 1: Ask a Question ─────────────────────────── -->
    <div class="card shadow-none border mb-4" data-component-card="data-component-card" id="nlq-step-ask">
        <div class="card-header p-3 border-bottom">
            <h4 class="text-body mb-0">
                Ask Your Data
                <i class="bi bi-chat-dots ms-2"></i>
            </h4>
        </div>
        <div class="card-body p-4">
            <p class="text-body-tertiary mb-3">
                Describe the data you want in plain language. The AI will generate a SQL query for you.
            </p>

            <div class="mb-3">
                <label for="nlq-question" class="form-label fw-medium">
                    What would you like to know?
                </label>
                <div class="input-group">
                    <textarea class="form-control" id="nlq-question" rows="3"
                              placeholder="e.g. Show me all agents who are active and registered in Gauteng&#10;e.g. How many learners completed their LP this month?&#10;e.g. List all classes starting next week with their client names"></textarea>
                    <button class="btn btn-primary" type="button" id="nlq-ask-btn" style="min-width: 120px;">
                        <i class="bi bi-stars me-1"></i> Generate
                    </button>
                </div>
                <div class="form-text">
                    <i class="bi bi-lightbulb me-1"></i>
                    Tip: Be specific about what columns you want, filters, and sorting.
                </div>
            </div>

            <!-- Module hint (optional) -->
            <div class="row">
                <div class="col-auto">
                    <select id="nlq-module-hint" class="form-select form-select-sm" style="width: 180px;">
                        <option value="">Auto-detect module</option>
                        <option value="agents">Agents</option>
                        <option value="learners">Learners</option>
                        <option value="classes">Classes</option>
                        <option value="clients">Clients</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Step 2: Generated SQL + Preview ────────────────── -->
    <div id="nlq-step-result" style="display: none;">

        <!-- AI Response Card -->
        <div class="card shadow-none border mb-4" data-component-card="data-component-card">
            <div class="card-header p-3 border-bottom d-flex justify-content-between align-items-center">
                <h4 class="text-body mb-0">
                    Generated Query
                    <i class="bi bi-robot ms-2"></i>
                </h4>
                <span class="badge badge-phoenix badge-phoenix-info" id="nlq-detected-module"></span>
            </div>
            <div class="card-body p-4">

                <!-- Explanation -->
                <div id="nlq-explanation" class="alert alert-subtle-info mb-3" style="display: none;">
                    <i class="bi bi-info-circle me-2"></i>
                    <span id="nlq-explanation-text"></span>
                </div>

                <!-- SQL Display -->
                <div class="mb-3">
                    <label class="form-label fw-medium">SQL Query</label>
                    <textarea class="form-control font-monospace" id="nlq-generated-sql" rows="5"
                              style="font-size: 0.85rem;"></textarea>
                    <div class="form-text">
                        <i class="bi bi-pencil me-1"></i>
                        You can edit the SQL directly before previewing or saving.
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="nlq-preview-btn">
                        <i class="bi bi-play-circle me-1"></i> Preview Results
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" id="nlq-refine-toggle">
                        <i class="bi bi-arrow-repeat me-1"></i> Refine with AI
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="nlq-new-query-btn">
                        <i class="bi bi-plus-circle me-1"></i> New Query
                    </button>
                </div>

                <!-- Refinement Input (hidden by default) -->
                <div id="nlq-refine-area" class="mt-3" style="display: none;">
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" id="nlq-refine-input"
                               placeholder="e.g. Add a filter for only active agents, sort by surname">
                        <button class="btn btn-info btn-sm" type="button" id="nlq-refine-btn">
                            <i class="bi bi-stars me-1"></i> Refine
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Results Card -->
        <div id="nlq-preview-card" class="card shadow-none border mb-4" style="display: none;">
            <div class="card-header p-3 border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h4 class="text-body mb-0 text-nowrap">
                        Preview Results
                        <i class="bi bi-table ms-2"></i>
                    </h4>
                    <span id="nlq-preview-count" class="badge badge-phoenix badge-phoenix-success fs-10"></span>
                </div>
            </div>
            <div class="card-body p-4 py-2">
                <div class="table-responsive">
                    <table id="nlq-ai-preview-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                        <thead class="border-bottom"><tr></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Save Card -->
        <div id="nlq-save-card" class="card shadow-none border mb-4" style="display: none;">
            <div class="card-header p-3 border-bottom">
                <h4 class="text-body mb-0">
                    Save Query
                    <i class="bi bi-save ms-2"></i>
                </h4>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label for="nlq-save-name" class="form-label fw-medium">
                            Query Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="nlq-save-name"
                               placeholder="e.g. Active Agents in Gauteng">
                    </div>
                    <div class="col-md-4">
                        <label for="nlq-save-category" class="form-label fw-medium">Category</label>
                        <input type="text" class="form-control" id="nlq-save-category"
                               placeholder="e.g. Agents" list="nlq-save-category-list">
                        <datalist id="nlq-save-category-list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="nlq-save-description" class="form-label fw-medium">Description</label>
                    <textarea class="form-control" id="nlq-save-description" rows="2"
                              placeholder="What does this query show?"></textarea>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="nlq-save-query-btn">
                    <i class="bi bi-save me-1"></i> Save Query
                </button>
            </div>
        </div>

        <!-- Save Success -->
        <div id="nlq-save-success" class="alert alert-subtle-success d-flex align-items-center" style="display: none;">
            <i class="bi bi-check-circle-fill me-3 fs-4"></i>
            <div>
                <strong>Query saved!</strong> Use this shortcode to display it anywhere:
                <code id="nlq-saved-shortcode" class="ms-2"></code>
                <button class="btn btn-sm btn-outline-secondary ms-2" id="nlq-copy-saved-shortcode">
                    <i class="bi bi-clipboard me-1"></i> Copy
                </button>
            </div>
        </div>

    </div>

</div>
