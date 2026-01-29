<?php
/**
 * Single Class Display - Staff Details Component
 *
 * Bottom section showing people/staff information:
 * - Learners list with preview and modal trigger
 * - Exam Candidates (for exam classes)
 * - SETA & Exam Information placeholder
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables:
 *   - $class: Array of class data from the database
 *   - $learners: Array of learner data (extracted from $class['learner_ids'])
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];

// Get learner_ids data (should already be decoded by controller)
$learners = $class['learner_ids'] ?? [];
?>
<!-- Bottom Left - People & Staff -->
<div class="col-sm-12 col-xxl-6 border-end-xxl py-3">
   <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
      <tbody>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-people text-info" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Learners : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php
                     if (!empty($learners) && is_array($learners)):
                         $learnerCount = count($learners);
                     ?>
                  <span class="badge badge-phoenix fs-10 badge-phoenix-primary me-2"><?php echo $learnerCount; ?> Learner<?php echo $learnerCount !== 1 ? 's' : ''; ?></span>
                  <div class="mt-2">
                     <?php foreach ($learners as $index => $learner): ?>
                     <?php if ($index < 3): // Show first 3 learners ?>
                     <div class="fs-9 mb-1">
                        <i class="bi bi-person-fill me-1"></i>
                        <?php echo esc_html($learner['name'] ?? 'Unknown'); ?>
                        <?php if (!empty($learner['status'])): ?>
                        <span class="text-secondary text-muted ">(<?php echo esc_html($learner['status']); ?>)</span>
                        <?php endif; ?>
                     </div>
                     <?php endif; ?>
                     <?php endforeach; ?>
                     <?php if ($learnerCount > 3): ?>
                     <div class="fs-9 text-muted mb-2">
                        <i class="bi bi-three-dots me-1"></i>
                        and <?php echo ($learnerCount - 3); ?> more learner<?php echo ($learnerCount - 3) !== 1 ? 's' : ''; ?>
                     </div>
                     <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#learnersModal">
                     <i class="bi bi-eye me-1"></i>View All Learners
                     </button>
                     <?php endif; ?>
                  </div>
                  <?php else: ?>
                  <span class="text-muted">No learners assigned</span>
                  <?php endif; ?>
               </div>
            </td>
         </tr>
         <!-- Exam Learners Section - Only show for exam classes -->
         <?php if ($class['exam_class'] && !empty($class['exam_learners']) && is_array($class['exam_learners'])): ?>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-mortarboard-fill text-warning" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Exam Candidates : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php
                     $examLearners = $class['exam_learners'];
                     $examLearnerCount = count($examLearners);
                     ?>
                  <span class="badge badge-phoenix fs-10 badge-phoenix-warning me-2">
                  <?php echo $examLearnerCount; ?> Exam Candidate<?php echo $examLearnerCount !== 1 ? 's' : ''; ?>
                  </span>
                  <div class="mt-2">
                     <?php foreach ($examLearners as $index => $examLearner): ?>
                     <?php if ($index < 3): // Show first 3 exam learners ?>
                     <div class="fs-9 mb-1">
                        <i class="bi bi-mortarboard me-1"></i>
                        <?php echo esc_html($examLearner['name'] ?? 'Unknown'); ?>
                        <?php if (!empty($examLearner['exam_status'])): ?>
                        <span class="badge bg-light text-dark ms-1" style="font-size: 0.7rem;">
                        <?php echo esc_html($examLearner['exam_status']); ?>
                        </span>
                        <?php endif; ?>
                     </div>
                     <?php endif; ?>
                     <?php endforeach; ?>
                     <?php if ($examLearnerCount > 3): ?>
                     <div class="fs-9 text-muted">
                        <i class="bi bi-three-dots me-1"></i>
                        and <?php echo ($examLearnerCount - 3); ?> more exam candidate<?php echo ($examLearnerCount - 3) !== 1 ? 's' : ''; ?>
                     </div>
                     <?php endif; ?>
                  </div>
               </div>
            </td>
         </tr>
         <?php endif; ?>
      </tbody>
   </table>
</div>
<!-- Bottom Right - SETA & Exam Information -->
<div class="col-sm-12 col-xxl-6 py-3">
   <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
      &nbsp;
   </table>
</div>
