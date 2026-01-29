<?php
/**
 * Single Class Display - General Details Component
 *
 * Left column showing basic class information including:
 * - Class ID, Duration, Address
 * - Class Type, Subject
 * - SETA Funding details
 * - Exam information
 * - Class Schedule (days and times)
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables:
 *   - $class: Array of class data from the database
 *   - $schedule_data: Decoded schedule data (array or null)
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];
$schedule_data = $schedule_data ?? null;
?>
<!-- Left Column - Basic Information -->
<div class="col-sm-12 col-xxl-6 border-bottom border-end-xxl py-3">
   <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
      <tbody>
         <tr>

            <td class="py-2 ydcoza-w-150 ">
               <div class="d-inline-flex align-items-center">
                  <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-hash text-primary" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Class ID : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0">#<?php echo esc_html($class['class_id']); ?></p>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-clock text-warning" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Duration :</p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0">
                  <?php if (!empty($class['class_duration'])): ?>
                  <?php echo esc_html($class['class_duration']); ?> hours
                  <?php else: ?>
                  <span class="text-muted">N/A</span>
                  <?php endif; ?>
               </p>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-geo-alt text-success" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Address : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0"><?php echo esc_html($class['class_address_line'] ?? 'N/A'); ?></p>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-layers text-primary" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Class Type : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0"><?php echo esc_html($class['class_type'] ?? 'N/A'); ?></p>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-book text-success" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Class Subject : </p>
               </div>
            </td>
            <td class="py-2">
               <p class="fw-semibold mb-0"><?php echo esc_html($class['class_subject'] ?? 'N/A'); ?></p>
            </td>
         </tr>
         <tr>
            <td class="py-2 ydcoza-w-150 ">
               <div class="d-inline-flex align-items-center">
                  <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-check-circle text-success" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">SETA Funded : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php if ($class['seta_funded']): ?>
                  <span>Yes</span>
                  <?php else: ?>
                  <span>No</span>
                  <?php endif; ?>
               </div>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-building-gear text-info" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">SETA Name : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php echo esc_html($class['seta'] ?? 'N/A'); ?>
               </div>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-mortarboard text-warning" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Exam Class : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php if ($class['exam_class']): ?>
                  <span>Yes</span>
                  <?php else: ?>
                  <span>No</span>
                  <?php endif; ?>
               </div>
            </td>
         </tr>
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-clipboard-check text-primary" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Exam Type : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php echo esc_html($class['exam_type'] ?? 'N/A'); ?>
               </div>
            </td>
         </tr>
         <!-- Class Schedule Row -->
         <tr>
            <td class="py-2">
               <div class="d-flex align-items-center">
                  <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                     <i class="bi bi-calendar-week text-info" style="font-size: 12px;"></i>
                  </div>
                  <p class="fw-bold mb-0">Class Schedule : </p>
               </div>
            </td>
            <td class="py-2">
               <div class="fw-semibold mb-0">
                  <?php
                     // Display class schedule (days and times)
                     if (!empty($schedule_data) && isset($schedule_data['selectedDays'])) {
                         $schedule_output = [];
                         $selected_days = $schedule_data['selectedDays'];
                         $time_data = $schedule_data['timeData'] ?? [];

                         foreach ($selected_days as $day) {
                             $day_display = $day;

                             // Check if we have per-day times
                             if (isset($time_data['mode']) && $time_data['mode'] === 'per-day' &&
                                 isset($time_data['perDayTimes'][$day])) {
                                 $day_times = $time_data['perDayTimes'][$day];

                                 // Handle both camelCase and snake_case field names
                                 $start_field = isset($day_times['startTime']) ? 'startTime' : 'start_time';
                                 $end_field = isset($day_times['endTime']) ? 'endTime' : 'end_time';

                                 if (isset($day_times[$start_field], $day_times[$end_field])) {
                                     $start_time = date('g:i A', strtotime($day_times[$start_field]));
                                     $end_time = date('g:i A', strtotime($day_times[$end_field]));
                                     $day_display .= ": {$start_time} - {$end_time}";
                                 }
                             }
                             // Check if we have single time mode
                             elseif (isset($time_data['mode']) && $time_data['mode'] === 'single' &&
                                     isset($time_data['startTime'], $time_data['endTime'])) {
                                 $start_time = date('g:i A', strtotime($time_data['startTime']));
                                 $end_time = date('g:i A', strtotime($time_data['endTime']));
                                 $day_display .= ": {$start_time} - {$end_time}";
                             }

                             $schedule_output[] = $day_display;
                         }

                         // Display each day in a clean list format for better readability
                         echo '<div class="schedule-days-list">';
                         foreach ($schedule_output as $day_info) {
                             echo '<div class="schedule-day-item mb-1">' . esc_html($day_info) . '</div>';
                         }
                         echo '</div>';
                     } else {
                         echo 'Schedule not available';
                     }
                     ?>
               </div>
            </td>
         </tr>
      </tbody>
   </table>
</div>
