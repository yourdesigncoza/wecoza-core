<?php
/**
 * Single Class Display - Logistics Details Component
 *
 * Right column showing dates, schedule, and staff information:
 * - End Date, Start Date, Delivery Date
 * - Created/Updated timestamps
 * - QA Visit Dates
 * - Stop/Restart Periods
 * - Agent information (primary, backup, original)
 * - Supervisor information
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables:
 *   - $class: Array of class data from the database
 *   - $end_date: Calculated end date from schedule data
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];
$end_date = $end_date ?? null;
?>
<!-- Right Column - Dates & Schedule -->
<div class="col-sm-12 col-xxl-6 border-bottom py-3">
   <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
      <tbody>
         <tr>
            <td class="py-2 ydcoza-w-150">
               <div class="d-inline-flex align-items-center">
                  <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-calendar-check text-success" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">End Date : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0">
                  <?php if (!empty($end_date)): ?>
                  <?php echo esc_html(wp_date('M j, Y', strtotime($end_date))); ?>
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </p>
            </td>
         </tr>
         <tr>
            <td class="py-2 ydcoza-w-150">
               <div class="d-inline-flex align-items-center">
                  <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-calendar-plus text-info" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Start Date : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0">
                  <?php if (!empty($class['original_start_date'])): ?>
                  <?php echo esc_html(wp_date('M j, Y', strtotime($class['original_start_date']))); ?>
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </p>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-truck text-warning" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Deliveries : </p>
               </div>
            </td>
            <td class="py-2">
               <?php
               $deliveries = array_filter($class['event_dates'] ?? [], function($event) {
                   return ($event['type'] ?? '') === 'Deliveries';
               });
               if (!empty($deliveries)): ?>
                  <?php foreach ($deliveries as $delivery): ?>
                  <div class="mb-1">
                     <span class="fw-semibold"><?php echo esc_html(wp_date('M j, Y', strtotime($delivery['date']))); ?></span>
                     <?php if (!empty($delivery['description'])): ?>
                        <span class="text-muted small"> - <?php echo esc_html($delivery['description']); ?></span>
                     <?php endif; ?>
                     <span class="badge badge-phoenix <?php
                        $status = $delivery['status'] ?? 'Pending';
                        echo $status === 'Completed' ? 'badge-phoenix-success' : ($status === 'Cancelled' ? 'badge-phoenix-danger' : 'badge-phoenix-warning');
                     ?> ms-2"><?php echo esc_html($status); ?></span>
                  </div>
                  <?php endforeach; ?>
               <?php else: ?>
                  <span class="text-muted">N/A</span>
               <?php endif; ?>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-secondary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-calendar-check text-secondary" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Created : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0">
                  <?php if (!empty($class['created_at'])): ?>
                  <?php echo esc_html(wp_date('M j, Y g:i A', strtotime($class['created_at']))); ?>
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </p>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-calendar-event text-primary" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Last Updated : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0">
                  <?php if (!empty($class['updated_at'])): ?>
                  <?php echo esc_html(wp_date('M j, Y g:i A', strtotime($class['updated_at']))); ?>
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </p>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-calendar3 text-info" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">QA Visit Dates : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php if (!empty($class['qa_visits']['visits']) && is_array($class['qa_visits']['visits'])): ?>
                  <?php foreach ($class['qa_visits']['visits'] as $visit): ?>
                  <div class="mb-2">
                     <div class="d-flex align-items-center mb-1">
                        <span class="badge badge-phoenix fs-10 badge-phoenix-info me-2">
                           <?php echo esc_html($visit['type'] ?? 'QA Visit'); ?>
                        </span>
                        <span class="fw-semibold">
                           <?php echo esc_html(wp_date('M j, Y', strtotime($visit['date']))); ?>
                        </span>
                     </div>
                     <?php if (!empty($visit['officer'])): ?>
                     <div class="fs-9 text-muted">
                        <i class="bi bi-person me-1"></i>
                        <?php echo esc_html($visit['officer']); ?>
                        <?php if (!empty($visit['document']) && !empty($visit['document']['file_url'])): ?>
                        <a href="<?php echo esc_url($visit['document']['file_url']); ?>"
                           class="text-success ms-2 text-decoration-none"
                           title="Download QA Report: <?php echo esc_attr($visit['document']['original_name'] ?? $visit['document']['filename']); ?>"
                           download>
                           <i class="bi bi-file-earmark-pdf"></i>
                           <small class="ms-1">Download</small>
                        </a>
                        <?php endif; ?>
                     </div>
                     <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </div>
            </td>
         </tr>
         <!-- Stop/Restart Periods -->
         <?php if (!empty($class['stop_restart_dates']) && is_array($class['stop_restart_dates'])): ?>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-pause-circle text-warning" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Stop/Restart Periods : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php
                     $stopPeriods = $class['stop_restart_dates'];
                     $periodCount = count($stopPeriods);
                     ?>
                  <span class="badge badge-phoenix fs-10 badge-phoenix-warning me-2">
                  <?php echo $periodCount; ?> Period<?php echo $periodCount !== 1 ? 's' : ''; ?>
                  </span>
                  <div class="mt-2">
                     <?php foreach ($stopPeriods as $period): ?>
                     <?php
                        if (isset($period['stop_date']) && isset($period['restart_date'])):
                            $tz = wp_timezone();
                            $stopDate = new DateTime($period['stop_date'], $tz);
                            $restartDate = new DateTime($period['restart_date'], $tz);
                            $interval = $stopDate->diff($restartDate);
                            $days = $interval->days;
                        ?>
                     <div class="fs-9 mb-1">
                        <i class="bi bi-calendar-range me-1"></i>
                        <?php echo wp_date('M j', strtotime($period['stop_date'])); ?> -
                        <?php echo wp_date('M j, Y', strtotime($period['restart_date'])); ?>
                        <span class="text-muted">(<?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?>)</span>
                     </div>
                     <?php endif; ?>
                     <?php endforeach; ?>
                  </div>
               </div>
            </td>
         </tr>
         <?php endif; ?>
         <tr>
            <td class="py-2 ydcoza-w-150 ">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-person-badge text-success" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Agent : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php if (!empty($class['agent_name'])): ?>
                  <?php echo esc_html($class['agent_name']); ?>
                  <div class="fs-9 text-muted">ID: <?php echo esc_html($class['class_agent']); ?></div>
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </div>
            </td>
         </tr>
         <!-- Backup Agents -->
         <?php if (!empty($class['backup_agent_names']) && is_array($class['backup_agent_names'])): ?>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-people text-info" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Backup Agents : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <span class="badge badge-phoenix fs-10 badge-phoenix-info me-2"><?php echo count($class['backup_agent_names']); ?> Backup<?php echo count($class['backup_agent_names']) !== 1 ? 's' : ''; ?></span>
                  <div class="mt-1">
                     <?php foreach ($class['backup_agent_names'] as $backupAgent): ?>
                     <div class="fs-9 mb-1">
                        <i class="bi bi-person me-1"></i>
                        <?php echo esc_html($backupAgent['name']); ?>
                        <span class="text-muted">(ID: <?php echo esc_html($backupAgent['id']); ?>)</span>
                     </div>
                     <?php endforeach; ?>
                  </div>
               </div>
            </td>
         </tr>
         <?php endif; ?>
         <!-- Initial Agent History - Only show if different from current agent -->
         <?php if (!empty($class['initial_class_agent']) &&
            $class['initial_class_agent'] != $class['class_agent'] &&
            !empty($class['initial_agent_name'])): ?>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-secondary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-clock-history text-secondary" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Original Agent : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php echo esc_html($class['initial_agent_name']); ?>
                  <div class="fs-9 text-muted">
                     Started: <?php echo !empty($class['initial_agent_start_date']) ? wp_date('M j, Y', strtotime($class['initial_agent_start_date'])) : 'N/A'; ?>
                  </div>
               </div>
            </td>
         </tr>
         <?php endif; ?>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-person-gear text-warning" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Supervisor : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php if (!empty($class['supervisor_name'])): ?>
                  <?php echo esc_html($class['supervisor_name']); ?>
                  <div class="fs-9 text-muted">ID: <?php echo esc_html($class['project_supervisor_id']); ?></div>
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </div>
            </td>
         </tr>
      </tbody>
   </table>
</div>
