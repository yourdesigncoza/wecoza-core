<?php
/**
 * Learner Progressions Tab Component
 *
 * Displays LP tracking, hours breakdown, progress bar, portfolio upload, and history.
 * Used in learner-single-display-shortcode.php
 *
 * JS targets:
 *   #progression-skeleton       — skeleton loading placeholder (hidden by default)
 *   #progression-current-lp     — current LP card wrapper (in-place updates)
 *   #progression-history        — history section wrapper (auto-refresh target)
 *   #markCompleteConfirmModal   — Bootstrap modal for mark-complete confirmation
 *   #upload-section             — hidden upload section for mark-complete flow
 *   #portfolio-only-upload-section — hidden upload section for standalone upload
 *
 * @package WeCoza_Learners
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// $learner is available from parent scope (learner-single-display-shortcode.php)

// ProgressionService is autoloaded via PSR-4
use WeCoza\Learners\Services\ProgressionService;

$progressionService = new ProgressionService();
$currentLP = $progressionService->getCurrentLPDetails($learner->id);
$history = $progressionService->getProgressionHistory($learner->id);
$isAdmin = current_user_can('manage_options');

// Calculate progress bar color
$progressPercentage = $currentLP['progress_percentage'] ?? 0;
$progressBarClass = $progressPercentage >= 80 ? 'bg-success' : ($progressPercentage >= 50 ? 'bg-warning' : 'bg-danger');
?>

<div class="px-xl-4 mb-7">
    <div class="row mx-0">
        <div class="col-12 py-3">

            <!-- Skeleton Loading Placeholder (shown by JS during refreshProgressionData()) -->
            <div id="progression-skeleton" class="d-none">
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="placeholder-glow">
                            <span class="placeholder col-4"></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="placeholder-glow mb-3">
                            <span class="placeholder col-6"></span>
                            <div class="progress mt-2" style="height: 24px;">
                                <div class="progress-bar placeholder col-12"></div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="card bg-body-subtle text-center p-3">
                                    <span class="placeholder col-8"></span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card bg-body-subtle text-center p-3">
                                    <span class="placeholder col-8"></span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card bg-body-subtle text-center p-3">
                                    <span class="placeholder col-8"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current LP Card (JS targets #progression-current-lp for in-place updates) -->
            <div id="progression-current-lp">
                <?php if ($currentLP): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    <i class="bi bi-graph-up-arrow me-2 text-primary"></i>
                                    <?php echo esc_html($currentLP['subject_name']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php if ($currentLP['class_code']): ?>
                                        <?php echo esc_html($currentLP['class_code']); ?> |
                                    <?php endif; ?>
                                    Started: <?php echo wp_date('M j, Y', strtotime($currentLP['start_date'])); ?>
                                </small>
                            </div>
                            <span class="badge badge-phoenix badge-phoenix-primary">In Progress</span>
                        </div>
                        <div class="card-body">
                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold">Progress</span>
                                    <span class="fw-bold"><?php echo round($progressPercentage, 1); ?>%</span>
                                </div>
                                <div class="progress" style="height: 24px;">
                                    <div class="progress-bar <?php echo $progressBarClass; ?>"
                                         role="progressbar"
                                         style="width: <?php echo $progressPercentage; ?>%"
                                         aria-valuenow="<?php echo $progressPercentage; ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                        <?php echo esc_html($currentLP['hours_present']); ?> / <?php echo esc_html($currentLP['subject_duration']); ?> hrs
                                    </div>
                                </div>
                                <?php if ($currentLP['is_hours_complete']): ?>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle me-1"></i>Required hours completed
                                    </small>
                                <?php endif; ?>
                            </div>

                            <!-- Hours Breakdown -->
                            <div class="row g-3 mb-4">
                                <div class="col-4">
                                    <div class="card bg-body-subtle text-center p-3">
                                        <i class="bi bi-calendar-check text-info fs-4"></i>
                                        <div class="fs-4 fw-bold mt-2"><?php echo number_format($currentLP['hours_trained'], 1); ?></div>
                                        <small class="text-muted">Trained Hours</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card bg-body-subtle text-center p-3">
                                        <i class="bi bi-check2-circle text-success fs-4"></i>
                                        <div class="fs-4 fw-bold mt-2"><?php echo number_format($currentLP['hours_present'], 1); ?></div>
                                        <small class="text-muted">Present Hours</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card bg-body-subtle text-center p-3">
                                        <i class="bi bi-x-circle text-danger fs-4"></i>
                                        <div class="fs-4 fw-bold mt-2"><?php echo number_format($currentLP['hours_absent'], 1); ?></div>
                                        <small class="text-muted">Absent Hours</small>
                                    </div>
                                </div>
                            </div>

                            <?php if ($isAdmin): ?>
                                <!-- Admin Actions -->
                                <div class="admin-actions border-top pt-3">
                                    <!-- Mark Complete button: data attributes feed confirmation modal -->
                                    <button type="button"
                                            class="btn btn-outline-success mark-complete-btn"
                                            data-tracking-id="<?php echo esc_attr($currentLP['tracking_id']); ?>"
                                            data-subject-name="<?php echo esc_attr($currentLP['subject_name']); ?>"
                                            data-progress-pct="<?php echo esc_attr(round($progressPercentage, 1)); ?>"
                                            data-hours-present="<?php echo esc_attr($currentLP['hours_present']); ?>"
                                            data-subject-duration="<?php echo esc_attr($currentLP['subject_duration']); ?>">
                                        <i class="bi bi-check-circle me-1"></i> Mark Complete
                                    </button>

                                    <!-- Standalone "Upload Portfolio" button (AJAX-02) -->
                                    <button type="button"
                                            class="btn btn-outline-primary ms-2 upload-portfolio-btn"
                                            data-tracking-id="<?php echo esc_attr($currentLP['tracking_id']); ?>">
                                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload Portfolio
                                    </button>

                                    <!-- Mark Complete upload section (hidden until modal confirmed) -->
                                    <div class="upload-section d-none mt-3" id="upload-section">
                                        <div class="alert alert-info mb-3">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Upload a portfolio file (PDF, DOC, or DOCX, max 10MB) to complete this LP.
                                        </div>
                                        <form id="portfolio-upload-form" enctype="multipart/form-data">
                                            <input type="hidden" name="tracking_id" value="<?php echo esc_attr($currentLP['tracking_id']); ?>">
                                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('learners_nonce'); ?>">
                                            <div class="mb-3">
                                                <label for="portfolio_file" class="form-label">Portfolio File</label>
                                                <input type="file"
                                                       class="form-control"
                                                       id="portfolio_file"
                                                       name="portfolio_file"
                                                       accept=".pdf,.doc,.docx"
                                                       required>
                                                <div class="form-text">Accepted formats: PDF, DOC, DOCX (Max 10MB)</div>
                                            </div>
                                            <!-- Upload progress bar (shown by JS during file transfer) -->
                                            <div class="progress mt-2 d-none" id="upload-progress">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                                     role="progressbar"
                                                     style="width: 0%">0%</div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <button type="submit" class="btn btn-success confirm-complete-btn" disabled>
                                                    <i class="bi bi-check-lg me-1"></i> Confirm Completion
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary cancel-complete-btn">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Standalone portfolio upload section (AJAX-02, hidden by default) -->
                                    <div class="d-none mt-3" id="portfolio-only-upload-section">
                                        <div class="alert alert-info mb-3">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Upload an additional portfolio file (PDF, DOC, or DOCX, max 10MB).
                                        </div>
                                        <form id="portfolio-only-upload-form" enctype="multipart/form-data">
                                            <input type="hidden" name="tracking_id" value="<?php echo esc_attr($currentLP['tracking_id']); ?>">
                                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('learners_nonce'); ?>">
                                            <div class="mb-3">
                                                <label for="portfolio_only_file" class="form-label">Portfolio File</label>
                                                <input type="file"
                                                       class="form-control"
                                                       id="portfolio_only_file"
                                                       name="portfolio_file"
                                                       accept=".pdf,.doc,.docx"
                                                       required>
                                                <div class="form-text">Accepted formats: PDF, DOC, DOCX (Max 10MB)</div>
                                            </div>
                                            <!-- Upload progress bar for standalone upload -->
                                            <div class="progress mt-2 d-none" id="portfolio-only-progress">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                                     role="progressbar"
                                                     style="width: 0%">0%</div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <button type="submit" class="btn btn-primary" id="portfolio-only-confirm-btn" disabled>
                                                    <i class="bi bi-cloud-upload me-1"></i> Upload Portfolio
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary cancel-portfolio-only-btn">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- No Active LP -->
                    <div class="alert alert-subtle-primary mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        No active Learning Programme. This learner is not currently enrolled in any LP.
                    </div>
                <?php endif; ?>
            </div><!-- #progression-current-lp -->

            <!-- Progression History (JS targets #progression-history for auto-refresh) -->
            <div id="progression-history">
                <?php if (!empty($history)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Completed Progressions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline-basic">
                                <?php foreach ($history as $index => $lp): ?>
                                    <div class="d-flex mb-3 <?php echo $index < count($history) - 1 ? 'border-bottom pb-3' : ''; ?>">
                                        <div class="d-flex align-items-center justify-content-center bg-success rounded-circle me-3"
                                             style="width: 36px; height: 36px; flex-shrink: 0;">
                                            <i class="bi bi-check-lg text-white"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo esc_html($lp['subject_name']); ?></h6>
                                            <div class="text-muted small">
                                                <span>
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?php echo wp_date('M Y', strtotime($lp['start_date'])); ?> -
                                                    <?php echo wp_date('M Y', strtotime($lp['completion_date'])); ?>
                                                </span>
                                                <span class="ms-3">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php echo number_format($lp['hours_present'], 1); ?> / <?php echo number_format($lp['subject_duration'], 1); ?> hrs
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge badge-phoenix badge-phoenix-success">Completed</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif (!$currentLP): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                        <p>No progression history available for this learner.</p>
                    </div>
                <?php endif; ?>
            </div><!-- #progression-history -->

        </div>
    </div>
</div>

<!-- Mark Complete Confirmation Modal -->
<div class="modal fade" id="markCompleteConfirmModal" tabindex="-1" aria-labelledby="markCompleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markCompleteConfirmModalLabel">
                    <i class="bi bi-check-circle text-success me-2"></i>Mark LP as Complete?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You are about to mark <strong id="confirm-lp-name"></strong> as complete.</p>
                <ul class="list-unstyled text-muted">
                    <li><i class="bi bi-graph-up me-2"></i>Progress: <span id="confirm-lp-progress"></span></li>
                    <li><i class="bi bi-clock me-2"></i>Hours: <span id="confirm-lp-hours"></span></li>
                </ul>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>A portfolio file upload is required to complete this LP.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="proceed-to-upload-btn">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Proceed to Upload
                </button>
            </div>
        </div>
    </div>
</div>
