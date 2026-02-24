<?php
/**
 * Single Class Display View - Componentized Layout
 *
 * This view displays detailed information for a single class from the database.
 * Uses componentized architecture for maintainability.
 * Used by the [wecoza_display_single_class] shortcode.
 *
 * Available Variables:
 *   - $class: Array of class data from the database
 *   - $show_loading: Boolean indicating whether to show loading indicator
 *   - $error_message: String containing error message if class not found or invalid
 *
 * Components Used:
 *   - single-class/header.php - Loading indicator, error states, action buttons
 *   - single-class/summary-cards.php - Top summary cards
 *   - single-class/details-general.php - Left column details
 *   - single-class/details-logistics.php - Right column dates/schedule
 *   - single-class/details-staff.php - People/staff information
 *   - single-class/notes.php - Class notes section
 *   - single-class/qa-reports.php - QA reports section
 *   - single-class/calendar.php - Calendar and list view
 *   - single-class/modal-learners.php - Learners modal dialog
 *
 * @package WeCoza
 * @see \WeCoza\Controllers\ClassController::displaySingleClassShortcode()
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure we have the class data
$class = $class ?? null;
$show_loading = $show_loading ?? true;
$error_message = $error_message ?? '';

// Process schedule data early for display purposes
$schedule_data = null;
$end_date = null;
$start_date = null;

if (!empty($class['schedule_data'])) {
    $schedule_data = is_string($class['schedule_data'])
        ? json_decode($class['schedule_data'], true)
        : $class['schedule_data'];

    if (!empty($schedule_data)) {
        $start_date = $schedule_data['startDate'] ?? $class['original_start_date'] ?? null;
        $end_date = $schedule_data['endDate'] ?? null;
    }
}

// Process learners data early for use in multiple components
$learners = [];
if (!empty($class['learner_ids'])) {
    $learners = is_string($class['learner_ids'])
        ? json_decode($class['learner_ids'], true)
        : $class['learner_ids'];
    if (!is_array($learners)) {
        $learners = [];
    }
}

// Process class notes data for notes component
$class_notes_data = [];
if (!empty($class['class_notes_data'])) {
    $class_notes_data = is_string($class['class_notes_data'])
        ? json_decode($class['class_notes_data'], true)
        : $class['class_notes_data'];
    if (!is_array($class_notes_data)) {
        $class_notes_data = [];
    }
}

// Data array to pass to all components
$component_data = [
    'class' => $class,
    'show_loading' => $show_loading,
    'error_message' => $error_message,
    'schedule_data' => $schedule_data,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'learners' => $learners,
    'class_notes_data' => $class_notes_data
];
?>
<div class="wecoza-single-class-display">
   <!-- Loading Indicator -->
   <?php if ($show_loading): ?>
   <div id="single-class-loading" class="d-flex justify-content-center align-items-center py-4">
      <div class="spinner-border text-primary me-3" role="status">
         <span class="visually-hidden">Loading...</span>
      </div>
      <span class="text-muted">Loading class details...</span>
   </div>
   <?php endif; ?>

   <!-- Class Content -->
   <div id="single-class-content" class="<?php echo $show_loading ? 'd-none' : ''; ?>">
      <?php if (!empty($error_message)): ?>
      <!-- Error Message -->
      <div class="alert alert-subtle-danger d-flex align-items-center">
         <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
         <div>
            <h6 class="alert-heading mb-1">Error Loading Class</h6>
            <p class="mb-0"><?php echo esc_html($error_message); ?></p>
         </div>
      </div>
      <?php elseif (empty($class)): ?>
      <!-- No Class Found -->
      <div class="alert alert-warning d-flex align-items-center">
         <i class="bi bi-info-circle-fill me-3 fs-4"></i>
         <div>
            <h6 class="alert-heading mb-1">Class Not Found</h6>
            <p class="mb-0">The requested class could not be found in the database.</p>
         </div>
      </div>
      <?php else: ?>

      <!-- Action Buttons -->
      <div class="d-flex justify-content-end mb-4">
          <div class="btn-group mt-2 me-2" role="group" aria-label="Class Actions">
            <button class="btn btn-subtle-primary" type="button" onclick="backToClasses()">Back To Classes</button>
            <?php if (current_user_can('edit_posts') || current_user_can('manage_options')): ?>
            <button class="btn btn-subtle-success" type="button" onclick="editClass(<?php echo esc_js($class['class_id']); ?>)">Edit</button>
            <?php endif; ?>
            <?php if (current_user_can('manage_options')): ?>
            <button class="btn btn-subtle-danger" type="button" onclick="deleteClass(<?php echo esc_js($class['class_id']); ?>)">Delete</button>
            <?php endif; ?>
          </div>
      </div>

      <!-- Status Management (manage_options only) -->
      <?php if (current_user_can('manage_options')): ?>
      <?php $classStatus = wecoza_resolve_class_status($class); ?>

      <!-- Status Management Card -->
      <div class="card mb-3">
          <div class="card-body">
              <h5 class="card-title mb-3"><?= esc_html__('Class Status Management', 'wecoza-core'); ?></h5>
              <div class="d-flex gap-2 align-items-center">
                  <?php if ($classStatus === 'draft'): ?>
                      <span class="badge badge-phoenix badge-phoenix-warning me-2"><i class="bi bi-file-earmark-text me-1"></i><?= esc_html__('Draft', 'wecoza-core'); ?></span>
                      <button type="button" class="btn btn-phoenix-success btn-sm" data-bs-toggle="modal" data-bs-target="#activateClassModal">
                          <i class="bi bi-check-circle me-1"></i><?= esc_html__('Activate Class', 'wecoza-core'); ?>
                      </button>
                  <?php elseif ($classStatus === 'active'): ?>
                      <span class="badge badge-phoenix badge-phoenix-success me-2"><i class="bi bi-check-circle me-1"></i><?= esc_html__('Active', 'wecoza-core'); ?></span>
                      <button type="button" class="btn btn-phoenix-danger btn-sm" data-bs-toggle="modal" data-bs-target="#stopClassModal">
                          <i class="bi bi-stop-circle me-1"></i><?= esc_html__('Stop Class', 'wecoza-core'); ?>
                      </button>
                  <?php elseif ($classStatus === 'stopped'): ?>
                      <span class="badge badge-phoenix badge-phoenix-danger me-2"><i class="bi bi-stop-circle me-1"></i><?= esc_html__('Stopped', 'wecoza-core'); ?></span>
                      <button type="button" class="btn btn-phoenix-success btn-sm" id="btn-reactivate-class">
                          <i class="bi bi-arrow-counterclockwise me-1"></i><?= esc_html__('Reactivate Class', 'wecoza-core'); ?>
                      </button>
                  <?php endif; ?>
              </div>
          </div>
      </div>

      <!-- Activate Class Modal -->
      <div class="modal fade" id="activateClassModal" tabindex="-1" aria-labelledby="activateClassModalLabel" aria-hidden="true">
          <div class="modal-dialog">
              <div class="modal-content">
                  <div class="modal-header">
                      <h5 class="modal-title" id="activateClassModalLabel"><?= esc_html__('Activate Class', 'wecoza-core'); ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc_attr__('Close', 'wecoza-core'); ?>"></button>
                  </div>
                  <div class="modal-body">
                      <p><?= esc_html__('Enter the order number to activate this class.', 'wecoza-core'); ?></p>
                      <div class="mb-3">
                          <label for="activate-order-nr" class="form-label"><?= esc_html__('Order Number', 'wecoza-core'); ?> <span class="text-danger">*</span></label>
                          <input type="text" class="form-control" id="activate-order-nr" placeholder="<?= esc_attr__('e.g. ORD-2024-001', 'wecoza-core'); ?>" required>
                          <div class="invalid-feedback"><?= esc_html__('Order number is required.', 'wecoza-core'); ?></div>
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= esc_html__('Cancel', 'wecoza-core'); ?></button>
                      <button type="button" class="btn btn-phoenix-success btn-sm" id="btn-confirm-activate">
                          <i class="bi bi-check-circle me-1"></i><?= esc_html__('Activate', 'wecoza-core'); ?>
                      </button>
                  </div>
              </div>
          </div>
      </div>

      <!-- Stop Class Modal -->
      <div class="modal fade" id="stopClassModal" tabindex="-1" aria-labelledby="stopClassModalLabel" aria-hidden="true">
          <div class="modal-dialog">
              <div class="modal-content">
                  <div class="modal-header">
                      <h5 class="modal-title" id="stopClassModalLabel"><?= esc_html__('Stop Class', 'wecoza-core'); ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc_attr__('Close', 'wecoza-core'); ?>"></button>
                  </div>
                  <div class="modal-body">
                      <p><?= esc_html__('Select a reason for stopping this class.', 'wecoza-core'); ?></p>
                      <div class="mb-3">
                          <label for="stop-reason" class="form-label"><?= esc_html__('Reason', 'wecoza-core'); ?> <span class="text-danger">*</span></label>
                          <select class="form-select" id="stop-reason" required>
                              <option value=""><?= esc_html__('Select reason...', 'wecoza-core'); ?></option>
                              <option value="programme_ended"><?= esc_html__('Programme Ended', 'wecoza-core'); ?></option>
                              <option value="temporary_hold"><?= esc_html__('Temporary Hold / Pause', 'wecoza-core'); ?></option>
                              <option value="annual_stop"><?= esc_html__('Annual Stop', 'wecoza-core'); ?></option>
                          </select>
                          <div class="invalid-feedback"><?= esc_html__('Please select a reason.', 'wecoza-core'); ?></div>
                      </div>
                      <div class="mb-3">
                          <label for="stop-notes" class="form-label"><?= esc_html__('Notes (optional)', 'wecoza-core'); ?></label>
                          <textarea class="form-control" id="stop-notes" rows="3" placeholder="<?= esc_attr__('Additional context...', 'wecoza-core'); ?>"></textarea>
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= esc_html__('Cancel', 'wecoza-core'); ?></button>
                      <button type="button" class="btn btn-phoenix-danger btn-sm" id="btn-confirm-stop">
                          <i class="bi bi-stop-circle me-1"></i><?= esc_html__('Stop Class', 'wecoza-core'); ?>
                      </button>
                  </div>
              </div>
          </div>
      </div>

      <!-- Status History Panel -->
      <div class="card mb-3">
          <div class="card-header">
              <h5 class="mb-0">
                  <button class="btn btn-link btn-sm text-decoration-none p-0 collapsed" type="button"
                          data-bs-toggle="collapse" data-bs-target="#statusHistoryCollapse"
                          aria-expanded="false" aria-controls="statusHistoryCollapse">
                      <i class="bi bi-clock-history me-1"></i><?= esc_html__('Status History', 'wecoza-core'); ?>
                  </button>
              </h5>
          </div>
          <div id="statusHistoryCollapse" class="collapse">
              <div class="card-body">
                  <div id="status-history-content">
                      <p class="text-body-tertiary"><?= esc_html__('Click to load status history...', 'wecoza-core'); ?></p>
                  </div>
              </div>
          </div>
      </div>

      <?php endif; /* end manage_options */ ?>

      <!-- Top Summary Cards -->
      <?php echo wecoza_view('classes/components/single-class/summary-cards', $component_data); ?>

      <!-- Details Tables -->
      <div class="px-xl-4 mb-7">
         <div class="row mx-0">
            <!-- Left Column - Basic Information -->
            <?php echo wecoza_view('classes/components/single-class/details-general', $component_data); ?>

            <!-- Right Column - Dates & Schedule -->
            <?php echo wecoza_view('classes/components/single-class/details-logistics', $component_data); ?>
         </div>

         <!-- Bottom Row - People Information -->
         <div class="row mx-0">
            <?php echo wecoza_view('classes/components/single-class/details-staff', $component_data); ?>
         </div>
      </div>

      <!-- Class Notes Section -->
      <?php echo wecoza_view('classes/components/single-class/notes', $component_data); ?>

      <!-- Attendance Section -->
      <?php echo wecoza_view('classes/components/single-class/attendance', $component_data); ?>

      <!-- Monthly Schedule Summary Section -->
      <?php
      // Process monthly schedule data for single class
      // Note: This section has complex PHP logic that remains inline
      // until refactored to a service class
      $monthly_data = [];

      if (!empty($class['schedule_data'])) {
          $schedule_data_local = is_string($class['schedule_data'])
              ? json_decode($class['schedule_data'], true)
              : $class['schedule_data'];

          $stop_restart_dates = is_string($class['stop_restart_dates'] ?? '')
              ? json_decode($class['stop_restart_dates'], true)
              : ($class['stop_restart_dates'] ?? []);

          if (!empty($schedule_data_local)) {
              // Extract date range
              $start_date_local = $schedule_data_local['startDate'] ?? $class['original_start_date'] ?? null;
              $end_date_local = $schedule_data_local['endDate'] ?? null;

              if ($start_date_local) {
                  // Calculate monthly statistics
                  $tz = wp_timezone();
                  $current_date = new DateTime($start_date_local, $tz);
                  $end_datetime = $end_date_local ? new DateTime($end_date_local, $tz) : (clone $current_date)->add(new DateInterval('P1Y'));

                  // Calculate daily hours based on schedule format
                  $daily_hours = 0;
                  if (isset($schedule_data_local['timeData'])) {
                      $time_data = $schedule_data_local['timeData'];

                      if ($time_data['mode'] === 'single' && isset($time_data['startTime'], $time_data['endTime'])) {
                          $start_time = new DateTime($time_data['startTime'], $tz);
                          $end_time = new DateTime($time_data['endTime'], $tz);
                          $daily_hours = ($end_time->getTimestamp() - $start_time->getTimestamp()) / 3600;
                      } elseif ($time_data['mode'] === 'per-day' && isset($time_data['perDayTimes'])) {
                          // Average hours across all configured days
                          $total_hours = 0;
                          $day_count = 0;
                          foreach ($time_data['perDayTimes'] as $day_times) {
                              // Handle both camelCase (startTime) and snake_case (start_time) field names
                              $start_field = isset($day_times['startTime']) ? 'startTime' : 'start_time';
                              $end_field = isset($day_times['endTime']) ? 'endTime' : 'end_time';

                              if (isset($day_times[$start_field], $day_times[$end_field])) {
                                  $start_time = new DateTime($day_times[$start_field], $tz);
                                  $end_time = new DateTime($day_times[$end_field], $tz);
                                  $total_hours += ($end_time->getTimestamp() - $start_time->getTimestamp()) / 3600;
                                  $day_count++;
                              }
                          }
                          $daily_hours = $day_count > 0 ? $total_hours / $day_count : 0;
                      }
                  }

                  // Get selected days
                  $selected_days = $schedule_data_local['selectedDays'] ?? [];
                  $day_map = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
                  $selected_day_nums = array_map(function($day) use ($day_map) {
                      return $day_map[$day] ?? null;
                  }, $selected_days);
                  $selected_day_nums = array_filter($selected_day_nums, function($v) { return $v !== null; });

                  // Get exception dates from schedule data
                  $exception_dates = $schedule_data_local['exceptionDates'] ?? [];

                  // Get event dates for QA visits and other additions
                  $event_dates = [];
                  if (!empty($class['event_dates'])) {
                      $event_dates = is_string($class['event_dates'])
                          ? json_decode($class['event_dates'], true)
                          : $class['event_dates'];
                  }

                  // Collect holiday override additions (dates to add back as class sessions)
                  $holiday_override_additions = [];
                  if (!empty($event_dates) && is_array($event_dates)) {
                      foreach ($event_dates as $event) {
                          if (isset($event['type']) && $event['type'] === 'holiday_override' && !empty($event['date'])) {
                              $holiday_override_additions[] = $event['date'];
                          }
                      }
                  }

                  // Build monthly data
                  while ($current_date <= $end_datetime) {
                      $month_key = $current_date->format('Y-m');
                      $month_name = $current_date->format('F Y');

                      if (!isset($monthly_data[$month_key])) {
                          $monthly_data[$month_key] = [
                              'name' => $month_name,
                              'year' => $current_date->format('Y'),
                              'sessions' => 0,
                              'hours' => 0,
                              'exceptions' => 0,
                              'holidays' => 0,
                              'stop_periods' => 0,
                              'additions' => 0,
                              'breakdown' => []
                          ];
                      }

                      // Check if this date is a scheduled day
                      $day_num = (int)$current_date->format('w');
                      $date_str = $current_date->format('Y-m-d');
                      $is_scheduled_day = in_array($day_num, $selected_day_nums);

                      if ($is_scheduled_day) {
                          // Check for various exclusions
                          $is_exception = in_array($date_str, $exception_dates);
                          $is_holiday = false;
                          $is_in_stop_period = false;
                          $is_holiday_override = in_array($date_str, $holiday_override_additions);

                          // Check public holidays using controller
                          try {
                              $holidays_controller = \WeCoza\Classes\Controllers\PublicHolidaysController::getInstance();
                              $year = (int)$current_date->format('Y');
                              $holidays = $holidays_controller->getHolidaysByYear($year);
                              foreach ($holidays as $holiday) {
                                  if (isset($holiday['date']) && $holiday['date'] === $date_str) {
                                      $is_holiday = true;
                                      break;
                                  }
                              }
                          } catch (\Exception $e) {
                              // Silently continue if holiday check fails
                          }

                          // Check stop/restart periods
                          if (!empty($stop_restart_dates) && is_array($stop_restart_dates)) {
                              foreach ($stop_restart_dates as $period) {
                                  if (!empty($period['stopDate'])) {
                                      $stop_date = new DateTime($period['stopDate'], $tz);
                                      $restart_date = !empty($period['restartDate']) ? new DateTime($period['restartDate'], $tz) : null;

                                      if ($current_date >= $stop_date && ($restart_date === null || $current_date < $restart_date)) {
                                          $is_in_stop_period = true;
                                          break;
                                      }
                                  }
                              }
                          }

                          // Count potential session
                          $monthly_data[$month_key]['breakdown'][] = [
                              'date' => $date_str,
                              'potential' => true,
                              'exception' => $is_exception,
                              'holiday' => $is_holiday,
                              'stop_period' => $is_in_stop_period,
                              'holiday_override' => $is_holiday_override
                          ];

                          // If it's a holiday override, it counts as an addition
                          if ($is_holiday_override) {
                              $monthly_data[$month_key]['additions']++;
                              $monthly_data[$month_key]['sessions']++;
                              $monthly_data[$month_key]['hours'] += $daily_hours;
                          }
                          // Count session if not excluded
                          elseif (!$is_exception && !$is_holiday && !$is_in_stop_period) {
                              $monthly_data[$month_key]['sessions']++;
                              $monthly_data[$month_key]['hours'] += $daily_hours;
                          } else {
                              // Track removals
                              if ($is_exception) $monthly_data[$month_key]['exceptions']++;
                              if ($is_holiday) $monthly_data[$month_key]['holidays']++;
                              if ($is_in_stop_period) $monthly_data[$month_key]['stop_periods']++;
                          }
                      }

                      $current_date->add(new DateInterval('P1D'));
                  }
              }
          }
      }
      ?>

      <?php if (!empty($monthly_data)): ?>
      <div class="card mb-4">
         <div class="card-header">
            <h4 class="mb-0">
               <i class="bi bi-calendar-month me-2"></i>Schedule Statistics
            </h4>
         </div>
         <div class="card-body">
            <?php
            // Group by year
            $by_year = [];
            foreach ($monthly_data as $month_key => $data) {
                $year = $data['year'];
                if (!isset($by_year[$year])) {
                    $by_year[$year] = [];
                }
                $by_year[$year][$month_key] = $data;
            }
            ?>
            <?php foreach ($by_year as $year => $months): ?>
            <h5 class="mb-3"><?php echo esc_html($year); ?></h5>
            <div class="row g-3 mb-4">
               <?php foreach ($months as $month_key => $data): ?>
               <div class="col-md-3">
                  <div class="card h-100">
                     <div class="card-body p-3">
                        <h6 class="card-title mb-2"><?php echo esc_html($data['name']); ?></h6>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                           <span class="text-muted small">Sessions:</span>
                           <span class="fw-bold"><?php echo esc_html($data['sessions']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                           <span class="text-muted small">Hours:</span>
                           <span class="fw-bold"><?php echo number_format($data['hours'], 1); ?></span>
                        </div>
                        <?php if ($data['exceptions'] > 0): ?>
                        <span class="badge badge-phoenix badge-phoenix-warning me-1" title="Exception dates">
                           <i class="bi bi-x-circle"></i> <?php echo $data['exceptions']; ?> exc
                        </span>
                        <?php endif; ?>
                        <?php if ($data['holidays'] > 0): ?>
                        <span class="badge badge-phoenix badge-phoenix-info me-1" title="Public holidays">
                           <i class="bi bi-calendar-heart"></i> <?php echo $data['holidays']; ?> hol
                        </span>
                        <?php endif; ?>
                        <?php if ($data['stop_periods'] > 0): ?>
                        <span class="badge badge-phoenix badge-phoenix-secondary me-1" title="Stop period days">
                           <i class="bi bi-pause-circle"></i> <?php echo $data['stop_periods']; ?> stop
                        </span>
                        <?php endif; ?>
                        <?php if ($data['additions'] > 0): ?>
                        <span class="badge badge-phoenix badge-phoenix-success me-1" title="Holiday override additions">
                           <i class="bi bi-plus-circle"></i> <?php echo $data['additions']; ?> add
                        </span>
                        <?php endif; ?>

                        <!-- Calculation Breakdown Toggle -->
                        <?php if (!empty($data['breakdown'])): ?>
                        <div class="mt-2">
                           <button class="btn btn-sm btn-link p-0 text-decoration-none" type="button"
                                   data-bs-toggle="collapse"
                                   data-bs-target="#breakdown-<?php echo esc_attr($month_key); ?>"
                                   aria-expanded="false">
                              <i class="bi bi-calculator me-1"></i>View Breakdown
                           </button>
                           <div class="collapse mt-2" id="breakdown-<?php echo esc_attr($month_key); ?>">
                              <div class="card card-body p-2 bg-light small">
                                 <?php
                                 $potential = count(array_filter($data['breakdown'], fn($d) => $d['potential']));
                                 $removed_exc = count(array_filter($data['breakdown'], fn($d) => $d['exception']));
                                 $removed_hol = count(array_filter($data['breakdown'], fn($d) => $d['holiday'] && !$d['holiday_override']));
                                 $removed_stop = count(array_filter($data['breakdown'], fn($d) => $d['stop_period']));
                                 $added_override = count(array_filter($data['breakdown'], fn($d) => $d['holiday_override']));
                                 ?>
                                 <div>Potential: <?php echo $potential; ?></div>
                                 <?php if ($removed_exc > 0): ?>
                                 <div class="text-warning">- Exceptions: <?php echo $removed_exc; ?></div>
                                 <?php endif; ?>
                                 <?php if ($removed_hol > 0): ?>
                                 <div class="text-info">- Holidays: <?php echo $removed_hol; ?></div>
                                 <?php endif; ?>
                                 <?php if ($removed_stop > 0): ?>
                                 <div class="text-secondary">- Stop Days: <?php echo $removed_stop; ?></div>
                                 <?php endif; ?>
                                 <?php if ($added_override > 0): ?>
                                 <div class="text-success">+ Overrides: <?php echo $added_override; ?></div>
                                 <?php endif; ?>
                                 <div class="fw-bold border-top mt-1 pt-1">= <?php echo $data['sessions']; ?> sessions</div>
                              </div>
                           </div>
                        </div>
                        <?php endif; ?>
                     </div>
                  </div>
               </div>
               <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
         </div>
      </div>
      <?php endif; ?>

      <!-- QA Reports Section -->
      <?php echo wecoza_view('classes/components/single-class/qa-reports', $component_data); ?>

      <!-- Calendar Section -->
      <?php echo wecoza_view('classes/components/single-class/calendar', $component_data); ?>

      <?php endif; ?>
   </div>
</div>

<!-- Learners Modal (outside main wrapper for proper Bootstrap modal behavior) -->
<?php echo wecoza_view('classes/components/single-class/modal-learners', $component_data); ?>
