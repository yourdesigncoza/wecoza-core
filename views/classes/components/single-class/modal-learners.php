<?php
/**
 * Single Class Display - Learners Modal Component
 *
 * Modal dialog displaying all learners assigned to a class with:
 * - Class code and subject in header
 * - Total learner count badge
 * - Table with learner name, status, level/module, and progression info
 * - Status badges with appropriate colors
 * - LP progression display (read-only)
 * - Pagination info
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables:
 *   - $class: Array of class data from the database
 *   - $learners: Array of learner data (extracted from $class['learner_ids'])
 */

use WeCoza\Learners\Services\ProgressionService;

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];

// Get learner_ids data (should already be decoded by controller)
$learners = $class['learner_ids'] ?? [];

// Only render if learners exist
if (empty($learners) || !is_array($learners) || count($learners) === 0) {
    return;
}

// Initialize progression service for fetching LP data
$progressionService = null;
try {
    $progressionService = new ProgressionService();
} catch (\Exception $e) {
    // Service unavailable, continue without progression data
}
?>
<!-- Learners Modal -->
<div class="modal fade" id="learnersModal" tabindex="-1" aria-labelledby="learnersModalLabel" aria-hidden="true">
   <div class="modal-dialog modal-lg">
      <div class="modal-content">
         <div class="modal-header border-0 pb-0">
            <h5 class="modal-title" id="learnersModalLabel">
               <i class="bi bi-people me-2"></i>Class : <?php echo esc_html($class['class_code'] ?? 'N/A'); ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
         </div>
         <div class="modal-body pt-2">
            <div class="row mb-3">
               <div class="col-12">
                  <div class="d-flex align-items-center justify-content-between">
                     <span class="badge badge-phoenix badge-phoenix-primary fs-9"><?php echo count($learners); ?> Total Learner<?php echo count($learners) !== 1 ? 's' : ''; ?></span>
                     <small class="text-muted">Class: <?php echo esc_html($class['class_subject'] ?? 'N/A'); ?></small>
                  </div>
               </div>
            </div>
            <div class="table-responsive">
               <table class="table table-sm fs-9 mb-0">
                  <thead>
                     <tr class="bg-body-highlight">
                        <th class="border-top border-translucent ps-3">Learner Name</th>
                        <th class="border-top border-translucent">Status</th>
                        <th class="border-top border-translucent">Level/Module</th>
                        <th class="border-top border-translucent">Current LP</th>
                        <th class="border-top border-translucent text-end pe-3">Progress</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php foreach ($learners as $index => $learner): ?>
                     <tr>
                        <td class="align-middle ps-3">
                           <?php
                              // Handle different learner data formats
                              $learnerName = 'Unknown Learner';
                              if (is_array($learner)) {
                                  // New format: array with name field
                                  if (isset($learner['name'])) {
                                      $learnerName = $learner['name'];
                                  }
                                  // Legacy format: might have first_name/surname
                                  elseif (isset($learner['first_name']) || isset($learner['surname'])) {
                                      $learnerName = trim(($learner['first_name'] ?? '') . ' ' . ($learner['surname'] ?? ''));
                                  }
                                  // ID only format
                                  elseif (isset($learner['id'])) {
                                      $learnerName = 'Learner ID: ' . $learner['id'];
                                  }
                              } elseif (is_numeric($learner)) {
                                  // Legacy format: just an ID
                                  $learnerName = 'Learner ID: ' . $learner;
                              }

                              echo esc_html($learnerName);
                              ?>
                        </td>
                        <td class="align-middle">
                           <?php
                              $learnerStatus = '';
                              if (is_array($learner) && isset($learner['status'])) {
                                  $learnerStatus = $learner['status'];
                              }

                              if (!empty($learnerStatus)): ?>
                           <?php
                              $statusClass = 'secondary';
                              $statusIcon = 'bi-person';
                              if ($learnerStatus === 'CIC - Currently in Class') {
                                  $statusClass = 'success';
                                  $statusIcon = 'bi-check';
                              } elseif (strpos(strtolower($learnerStatus), 'walk') !== false) {
                                  $statusClass = 'warning';
                                  $statusIcon = 'bi-bars-staggered';
                              }
                              ?>
                           <div class="badge badge-phoenix fs-10 badge-phoenix-<?php echo $statusClass; ?>">
                              <span class="fw-bold"><?php echo esc_html($learnerStatus); ?></span>
                              <i class="<?php echo $statusIcon; ?> ms-1"></i>
                           </div>
                           <?php else: ?>
                           <span class="text-muted">N/A</span>
                           <?php endif; ?>
                        </td>
                        <td class="align-middle py-3">
                           <?php
                              $learnerLevel = '';
                              if (is_array($learner) && isset($learner['level'])) {
                                  $learnerLevel = $learner['level'];
                              }

                              if (!empty($learnerLevel)): ?>
                           <span class="text-body-secondary"><?php echo esc_html($learnerLevel); ?></span>
                           <?php else: ?>
                           <span class="text-muted">-</span>
                           <?php endif; ?>
                        </td>
                        <?php
                        // Get progression data for this learner
                        $lpDetails = null;
                        $learnerId = is_array($learner) ? ($learner['id'] ?? null) : (is_numeric($learner) ? $learner : null);

                        if ($learnerId && $progressionService) {
                            try {
                                $lpDetails = $progressionService->getCurrentLPDetails((int) $learnerId);
                            } catch (\Exception $e) {
                                // Silently fail - show no LP data
                            }
                        }
                        ?>
                        <td class="align-middle">
                           <?php if ($lpDetails): ?>
                           <div>
                              <span class="badge fs-10 badge-phoenix badge-phoenix-info" title="Started: <?php echo esc_attr($lpDetails['start_date'] ?? ''); ?>">
                                 <?php echo esc_html($lpDetails['subject_name'] ?? 'Unknown'); ?>
                              </span>
                              <br>
                              <small class="text-muted">
                                 <?php echo esc_html(number_format($lpDetails['hours_trained'] ?? 0, 1)); ?> / <?php echo esc_html(number_format($lpDetails['subject_duration'] ?? 0, 1)); ?> hrs
                              </small>
                           </div>
                           <?php else: ?>
                           <span class="text-muted">None</span>
                           <?php endif; ?>
                        </td>
                        <td class="align-middle text-end pe-3">
                           <?php if ($lpDetails): ?>
                           <?php
                              $progressPct = $lpDetails['progress_percentage'] ?? 0;
                              $progressColor = 'danger';
                              if ($progressPct >= 80) {
                                  $progressColor = 'success';
                              } elseif ($progressPct >= 50) {
                                  $progressColor = 'warning';
                              }
                              $hoursPresent = $lpDetails['hours_present'] ?? 0;
                              $hoursTrained = $lpDetails['hours_trained'] ?? 0;
                              $productDuration = $lpDetails['subject_duration'] ?? 0;
                           ?>
                           <div class="d-flex flex-column align-items-end">
                              <div class="progress mb-1" style="width: 80px; height: 6px;">
                                 <div class="progress-bar bg-<?php echo $progressColor; ?>"
                                      role="progressbar"
                                      style="width: <?php echo $progressPct; ?>%"
                                      aria-valuenow="<?php echo $progressPct; ?>"
                                      aria-valuemin="0"
                                      aria-valuemax="100"
                                      title="<?php echo esc_attr($hoursTrained); ?> / <?php echo esc_attr($productDuration); ?> hrs">
                                 </div>
                              </div>
                              <small class="text-muted"><?php echo number_format($progressPct, 0); ?>%</small>
                           </div>
                           <?php else: ?>
                           <span class="text-muted">-</span>
                           <?php endif; ?>
                        </td>
                     </tr>
                     <?php endforeach; ?>
                  </tbody>
               </table>
            </div>
            <!-- Pagination info (static for now) -->
            <div class="d-flex justify-content-between mt-3">
               <span class="d-none d-sm-inline-block">
               1 to <?php echo count($learners); ?> <span class="text-body-tertiary">Items of</span> <?php echo count($learners); ?>
               </span>
            </div>
         </div>
         <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Close
            </button>
         </div>
      </div>
   </div>
</div>
