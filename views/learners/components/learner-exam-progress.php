<?php
/**
 * Exam Progress Component
 *
 * Renders the 5-step exam progress card for exam-class learners.
 * Receives $currentLP from parent scope (learner-progressions.php).
 *
 * Expected data in $currentLP:
 *   - tracking_id (int)
 *   - exam_progress (array from ExamService::getExamProgress())
 *     - steps: array keyed by ExamStep value, each null or row array
 *     - completed_count: int
 *     - total_steps: int
 *     - completion_percentage: float
 *
 * JS targets:
 *   #exam-progress-section       — outer wrapper for full-section refresh
 *   .exam-step-card              — individual step card (data-exam-step, data-tracking-id)
 *   .exam-record-form            — form within pending step cards
 *   .exam-percentage-input       — percentage number input
 *   .exam-file-input             — file input for SBA/final steps
 *   .exam-submit-btn             — submit button per step
 *   .exam-delete-btn             — delete button for completed steps
 *
 * @package WeCoza_Learners
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Learners\Enums\ExamStep;

// Validate required data
$examProgress = $currentLP['exam_progress'] ?? null;
$trackingId   = $currentLP['tracking_id'] ?? null;

if (!$examProgress || !$trackingId) {
    ?>
    <div class="alert alert-warning mt-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Unable to load exam progress. Please refresh the page or contact support.
    </div>
    <?php
    return;
}

$steps          = $examProgress['steps'] ?? [];
$completedCount = $examProgress['completed_count'] ?? 0;
$totalSteps     = $examProgress['total_steps'] ?? count(ExamStep::cases());
?>

<div id="exam-progress-section" class="mt-3">
    <!-- Section Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">
            <i class="bi bi-mortarboard me-2 text-primary"></i>Exam Progress
        </h6>
        <span class="badge badge-phoenix badge-phoenix-<?php echo $completedCount === $totalSteps ? 'success' : 'info'; ?>">
            <?php echo (int) $completedCount; ?>/<?php echo (int) $totalSteps; ?> steps
        </span>
    </div>

    <!-- Hidden nonce field for JS AJAX calls -->
    <input type="hidden" id="exam-nonce" value="<?php echo wp_create_nonce('learners_nonce'); ?>">

    <!-- Step Cards -->
    <?php foreach (ExamStep::cases() as $step):
        $stepValue    = $step->value;
        $stepData     = $steps[$stepValue] ?? null;
        $isCompleted  = $stepData !== null;
        $requiresFile = $step->requiresFile();
    ?>
        <div class="exam-step-card <?php echo $isCompleted ? 'completed' : 'pending'; ?> mb-2"
             data-tracking-id="<?php echo esc_attr($trackingId); ?>"
             data-exam-step="<?php echo esc_attr($stepValue); ?>">

            <div class="d-flex justify-content-between align-items-start">
                <!-- Step Label & Badge -->
                <div class="d-flex align-items-center gap-2">
                    <?php if ($isCompleted): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    <?php else: ?>
                        <i class="bi bi-circle text-secondary"></i>
                    <?php endif; ?>
                    <span class="fw-semibold"><?php echo esc_html($step->label()); ?></span>
                    <span class="badge badge-phoenix badge-phoenix-<?php echo $isCompleted ? 'success' : 'secondary'; ?> fs-10">
                        <?php echo $isCompleted ? 'Completed' : 'Pending'; ?>
                    </span>
                </div>

                <?php if ($isCompleted): ?>
                    <!-- Delete/Re-record button -->
                    <button type="button"
                            class="btn btn-link btn-sm text-danger p-0 exam-delete-btn"
                            data-tracking-id="<?php echo esc_attr($trackingId); ?>"
                            data-exam-step="<?php echo esc_attr($stepValue); ?>"
                            title="Remove result to re-record">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($isCompleted): ?>
                <!-- Completed Step Details -->
                <div class="mt-2 ms-4">
                    <div class="d-flex flex-wrap gap-3 text-muted small">
                        <span>
                            <i class="bi bi-percent me-1"></i>
                            <strong class="exam-step-percentage"><?php echo esc_html(number_format((float) ($stepData['percentage'] ?? 0), 1)); ?>%</strong>
                        </span>
                        <?php if (!empty($stepData['recorded_at'])): ?>
                            <span>
                                <i class="bi bi-calendar3 me-1"></i>
                                <?php echo wp_date('j M Y', strtotime($stepData['recorded_at'])); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($stepData['recorded_by'])):
                            $recordedByUser = get_userdata((int) $stepData['recorded_by']);
                        ?>
                            <span>
                                <i class="bi bi-person me-1"></i>
                                <?php echo esc_html($recordedByUser ? $recordedByUser->display_name : 'User #' . $stepData['recorded_by']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($requiresFile && !empty($stepData['file_name'])): ?>
                        <div class="mt-1 small">
                            <i class="bi bi-file-earmark-text me-1 text-primary"></i>
                            <?php if (!empty($stepData['file_path'])): ?>
                                <a href="<?php echo esc_url(content_url(str_replace(WP_CONTENT_DIR, '', $stepData['file_path']))); ?>"
                                   target="_blank"
                                   class="text-decoration-none">
                                    <?php echo esc_html($stepData['file_name']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo esc_html($stepData['file_name']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Pending Step: Input Form -->
                <form class="exam-record-form mt-2 ms-4"
                      data-tracking-id="<?php echo esc_attr($trackingId); ?>"
                      data-exam-step="<?php echo esc_attr($stepValue); ?>"
                      enctype="multipart/form-data">

                    <div class="row g-2 align-items-end">
                        <!-- Percentage Input -->
                        <div class="col-auto">
                            <label class="form-label small mb-1">Score (%)</label>
                            <input type="number"
                                   class="form-control form-control-sm exam-percentage-input"
                                   min="0" max="100" step="0.1"
                                   placeholder="0–100"
                                   required
                                   style="width: 100px;">
                        </div>

                        <?php if ($requiresFile): ?>
                            <!-- File Input for SBA/Final -->
                            <div class="col">
                                <label class="form-label small mb-1">Evidence file</label>
                                <input type="file"
                                       class="form-control form-control-sm exam-file-input"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                       <?php echo $requiresFile ? 'required' : ''; ?>>
                                <div class="form-text fs-10">PDF, DOC, DOCX, JPG, PNG (max 10MB)</div>
                            </div>
                        <?php endif; ?>

                        <!-- Submit Button -->
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary exam-submit-btn">
                                <i class="bi bi-save me-1"></i>Record
                            </button>
                        </div>
                    </div>

                    <!-- Upload progress bar (shown by JS during file transfer) -->
                    <div class="progress mt-2 d-none exam-upload-progress" style="height: 6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             role="progressbar" style="width: 0%"></div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
