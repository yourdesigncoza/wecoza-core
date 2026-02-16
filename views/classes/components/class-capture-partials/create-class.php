<!-- Classes Capture Form -->
<div class="d-flex justify-content-between align-items-center mb-3">
   <h4 class="mb-0">Create New Class</h4>
</div>
<p class="text-muted small mb-5">   <span class="badge badge-phoenix fs-10 badge-phoenix-warning"><i class="bi bi-file-earmark-text me-1"></i><span class="badge-label">INIT. Status Draft</span></span> New classes are created as drafts. They will become active when an order number is assigned.</p>
<form id="classes-form" class="needs-validation ydcoza-compact-form" novalidate method="POST" enctype="multipart/form-data">
   <!-- Hidden Auto-generated Class ID -->
   <input type="hidden" id="class_id" name="class_id" value="auto-generated">
   <input type="hidden" id="redirect_url" name="redirect_url" value="<?php echo esc_attr($data['redirect_url'] ?? ''); ?>">
   <input type="hidden" id="nonce" name="nonce" value="<?php echo wp_create_nonce('wecoza_class_nonce'); ?>">
   <!-- ===== Section: Basic Details ===== -->
   <div class="container container-md classes-form ps-0">
      <!-- ===== Section: Basic Details ===== -->
      <!-- CREATE MODE: Full client/site selection -->
      <div class="row">
         <div class="col-md-12">
            <h6 class="mb-2">Client Details</h6>
         </div>
         <!-- Client Name (ID) -->
         <div class="col-md-3 ">
               <label for="client_id" class="form-label">Client Name (ID) <span class="text-danger">*</span></label>
               <select id="client_id" name="client_id" class="form-select form-select-sm" required>
                  <option value="">Select</option>
                  <?php foreach ($data['clients'] as $client): ?>
                  <option value="<?php echo esc_attr($client['id']); ?>"><?php echo esc_html($client['name']); ?></option>
                  <?php endforeach; ?>
               </select>
               <div class="invalid-feedback">Please select a client.</div>
               <div class="valid-feedback">Looks good!</div>
         </div>
         <!-- Class/Site Name -->
         <div class="col-md-3 ">

               <label for="site_id" class="form-label">Class/Site Name <span class="text-danger">*</span></label>
               <select id="site_id" name="site_id" class="form-select form-select-sm" required>
                  <option value="">Select Site</option>
                  <?php foreach ($data['clients'] as $client): ?>
                  <optgroup label="<?php echo esc_attr($client['name']); ?>">
                     <?php if (isset($data['sites'][$client['id']])): ?>
                     <?php foreach ($data['sites'][$client['id']] as $site): ?>
                     <option value="<?php echo esc_attr($site['id']); ?>"><?php echo esc_html($site['name']); ?></option>
                     <?php endforeach; ?>
                     <?php endif; ?>
                  </optgroup>
                  <?php endforeach; ?>
               </select>
               <div class="invalid-feedback">Please select a class/site name.</div>
               <div class="valid-feedback">Looks good!</div>
         </div>
         <!-- Single Address Field (initially hidden) -->
         <div class="col-md-6 " id="address-wrapper" style="display:none;">
               <label for="site_address" class="form-label">Address</label>
               <input
                  type="text"
                  id="site_address"
                  name="site_address"
                  class="form-control form-control-sm"
                  placeholder="Street, Suburb, Town, Postal Code"
                  readonly
                  />
         </div>
      </div>
      <?php echo section_divider(); ?>
      <!-- ===== Section: Scheduling & Class Info ===== -->
      <div class="row mt-3">
         <div class="col-md-12">
            <h6 class="mb-2">Class Details</h6>
         </div>
         <!-- Class Type (Main Category) -->
         <div class="col-md-3">
            <label for="class_type" class="form-label">Class Type <span class="text-danger">*</span></label>
            <select id="class_type" name="class_type" class="form-select form-select-sm" required>
               <option value="">Select</option>
               <?php foreach ($data['class_types'] as $class_type): ?>
               <option value="<?php echo esc_attr($class_type['id']); ?>"><?php echo esc_html($class_type['name']); ?></option>
               <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select the class type.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
         <!-- Class Subject (Specific Subject/Level/Module) -->
         <div class="col-md-3">
            <label for="class_subject" class="form-label">Class Subject <span class="text-danger">*</span></label>
            <select id="class_subject" name="class_subject" class="form-select form-select-sm" required disabled>
               <option value="">Select Class Type First</option>
            </select>
            <div class="invalid-feedback">Please select the class subject.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
         <!-- Class Duration (Auto-calculated) -->
         <div class="col-md-2">
            <label for="class_duration" class="form-label">Duration (Hours)</label>
            <input type="number" id="class_duration" name="class_duration" class="form-control form-control-sm" placeholder="Duration" readonly>
            <small class="text-muted">Based on class type and subject.</small>
         </div>
      </div>
      <div class="row">
         <!-- Class Code (Auto-generated) -->
         <div class="col-md-3">
            <label for="class_code" class="form-label">Class Code</label>
            <input type="text" id="class_code" name="class_code" class="form-control form-control-sm" placeholder="Class Code" readonly>
            <small class="text-muted">[Abbreviation][month][day][hour] eg. EDU012216</small>
         </div>
         <!-- Class Original Start Date -->
         <div class="col-md-3">
            <label for="class_start_date" class="form-label">Class Start Date <span class="text-danger">*</span></label>
            <input type="date" id="class_start_date" name="class_start_date" class="form-control form-control-sm" placeholder="YYYY-MM-DD" required>
            <div class="invalid-feedback">Please select the start date.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
      </div>
      <?php echo section_divider(); ?>
      <!-- Class Schedule Form Section -->
      <div class="mb-4 mt-3">
         <h6 class="mb-2">Class Schedule</h6>
         <!-- Date Range -->
         <div class="row ">
            <div class="col-md-4 d-none">
               <label for="schedule_start_date" class="form-label">Schedule Start Date <span class="text-danger">*</span></label>
               <input type="date" id="schedule_start_date" name="schedule_start_date" class="form-control form-control-sm" placeholder="YYYY-MM-DD" required>
               <div class="invalid-feedback">Please select a start date.</div>
               <div class="valid-feedback">Looks good!</div>
               <small class="text-muted">Update if it differs from the class original start date.</small>
            </div>
            <div class="col-md-4 ">
               <label for="schedule_pattern" class="form-label">Schedule Pattern <span class="text-danger">*</span></label>
               <select id="schedule_pattern" name="schedule_pattern" class="form-select form-select-sm" required>
                  <option value="">Select Schedule Pattern</option>
                  <option value="weekly">Weekly (Every Week)</option>
                  <option value="biweekly">Bi-Weekly (Every Two Weeks)</option>
                  <option value="monthly">Monthly</option>
                  <option value="custom">Custom</option>
               </select>
               <div class="invalid-feedback">Please select a schedule pattern.</div>
               <div class="valid-feedback">Looks good!</div>
               <small class="text-muted">Set up the recurring schedule for this class.</small>
            </div>
         </div>
         <!-- Schedule Pattern Selection -->
         <div class="row ">
            <!-- Day Selection (for weekly/biweekly) -->
            <div class="col-md-12 " id="day-selection-container">
               <h6 class="mb-2 mt-3">Days of Week <span class="text-danger">*</span></h6>
               <div class="days-checkbox-group">
                  <div class="form-check form-check-inline">
                     <input class="form-check-input schedule-day-checkbox" type="checkbox" id="schedule_day_monday" name="schedule_days[]" value="Monday" required>
                     <label class="form-check-label" for="schedule_day_monday">Monday</label>
                  </div>
                  <div class="form-check form-check-inline">
                     <input class="form-check-input schedule-day-checkbox" type="checkbox" id="schedule_day_tuesday" name="schedule_days[]" value="Tuesday">
                     <label class="form-check-label" for="schedule_day_tuesday">Tuesday</label>
                  </div>
                  <div class="form-check form-check-inline">
                     <input class="form-check-input schedule-day-checkbox" type="checkbox" id="schedule_day_wednesday" name="schedule_days[]" value="Wednesday">
                     <label class="form-check-label" for="schedule_day_wednesday">Wednesday</label>
                  </div>
                  <div class="form-check form-check-inline">
                     <input class="form-check-input schedule-day-checkbox" type="checkbox" id="schedule_day_thursday" name="schedule_days[]" value="Thursday">
                     <label class="form-check-label" for="schedule_day_thursday">Thursday</label>
                  </div>
                  <div class="form-check form-check-inline">
                     <input class="form-check-input schedule-day-checkbox" type="checkbox" id="schedule_day_friday" name="schedule_days[]" value="Friday">
                     <label class="form-check-label" for="schedule_day_friday">Friday</label>
                  </div>
                  <div class="form-check form-check-inline">
                     <input class="form-check-input schedule-day-checkbox" type="checkbox" id="schedule_day_saturday" name="schedule_days[]" value="Saturday">
                     <label class="form-check-label" for="schedule_day_saturday">Saturday</label>
                  </div>
                  <div class="form-check form-check-inline">
                     <input class="form-check-input schedule-day-checkbox" type="checkbox" id="schedule_day_sunday" name="schedule_days[]" value="Sunday">
                     <label class="form-check-label" for="schedule_day_sunday">Sunday</label>
                  </div>
               </div>
               <div class="mt-2">
                  <button type="button" class="btn btn-sm btn-subtle-primary" id="select-all-days">Select All</button>
                  <button type="button" class="btn btn-sm btn-subtle-secondary" id="clear-all-days">Clear All</button>
               </div>
               <div class="invalid-feedback d-none">Please select at least one day.</div>
               <div class="valid-feedback d-none">Looks good!</div>
            </div>
            <!-- Day of Month (for monthly) -->
            <div class="col-md-4  d-none" id="day-of-month-container">
               <label for="schedule_day_of_month" class="form-label">Day of Month <span class="text-danger">*</span></label>
               <select id="schedule_day_of_month" name="schedule_day_of_month" class="form-select form-select-sm">
                  <option value="">Select</option>
                  <?php for ($i = 1; $i <= 31; $i++): ?>
                  <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                  <?php endfor; ?>
                  <option value="last">Last Day</option>
               </select>
               <div class="invalid-feedback">Please select a day of the month.</div>
               <div class="valid-feedback">Looks good!</div>
            </div>
         </div>
         <!-- Time Selection -->
         <!-- Single Time Controls (shown when no days selected) -->
         <div id="single-time-controls" class="row  d-none">
            <!-- This section is now hidden and will be replaced by per-day controls when days are selected -->
         </div>
         <!-- Per-Day Time Controls (shown when multiple days selected) -->
         <div id="per-day-time-controls" class="d-none mt-3">
            <div class="">
               <h6 class="mb-2">Set Times for Each Day</h6>
               <p class="text-muted small mb-2">Configure individual start and end times for each selected day.</p>
            </div>
            <!-- Container for dynamically generated day sections -->
            <div id="per-day-sections-container" class="row g-4">
               <!-- Day sections will be generated here by JavaScript -->
            </div>
            <!-- Hidden template for day time section -->
            <div id="day-time-section-template" class="per-day-time-section col-sm-6 col-md-4 col-lg-3 d-none" data-day="">
               <div class="card h-100 border">
                  <div class="card-body">
                     <div class="d-flex justify-content-between align-items-center ">
                        <h6 class="card-title  day-name"></h6>
                        <button type="button" class="btn btn-sm btn-subtle-secondary copy-to-all-btn" title="Copy to all days">
                        <i class="bi bi-files"></i>
                        </button>
                     </div>
                     <div class="">
                        <label class="form-label">Start Time <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm day-start-time" data-day="" required>
                           <option value="">Select Time</option>
                           <?php
                              // Generate time options from 6:00 AM to 8:00 PM in 30-minute increments
                              $start = strtotime('06:00:00');
                              $end = strtotime('20:00:00');
                              $interval = 30 * 60; // 30 minutes in seconds

                              for ($time = $start; $time <= $end; $time += $interval) {
                                 $timeStr = wp_date('H:i', $time);
                                 echo '<option value="' . $timeStr . '">' . wp_date('g:i A', $time) . '</option>';
                              }
                              ?>
                        </select>
                        <div class="invalid-feedback">Please select a start time.</div>
                        <!-- <div class="valid-feedback">Looks good!</div> -->
                     </div>
                     <div class="mb-2">
                        <label class="form-label">End Time <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm day-end-time" data-day="" required>
                           <option value="">Select Time</option>
                           <?php
                              // Generate time options from 6:30 AM to 8:30 PM in 30-minute increments
                              $start = strtotime('06:30:00');
                              $end = strtotime('20:30:00');
                              $interval = 30 * 60; // 30 minutes in seconds

                              for ($time = $start; $time <= $end; $time += $interval) {
                                 $timeStr = wp_date('H:i', $time);
                                 echo '<option value="' . $timeStr . '">' . wp_date('g:i A', $time) . '</option>';
                              }
                              ?>
                        </select>
                        <div class="invalid-feedback">Please select an end time.</div>
                        <!-- <div class="valid-feedback">Looks good!</div> -->
                     </div>
                     <small class="text-muted day-duration-display  d-none">Duration: <span class="duration-value badge badge-phoenix badge-phoenix-info">-</span> hours</small>
                  </div>
               </div>
            </div>
         </div>
         <div class="col-md-4 d-none">
            <label for="schedule_total_hours" class="form-label">Total Hours</label>
            <input type="text" id="schedule_total_hours" name="schedule_total_hours" class="form-control form-control-sm readonly-field" placeholder="Total Hours" readonly>
            <small class="text-muted">Based on class type</small>
         </div>
      </div>
      <?php echo section_divider(); ?>
      <!-- Exception Dates -->
      <div class="mb-4">
         <h6 class="mb-2">Exception Dates</h6>
         <p class="text-muted small ">Add dates when classes will not occur (e.g. client closed).</p>
         <!-- Container for all exception date rows -->
         <div id="exception-dates-container"></div>
         <!-- Hidden Template Row (initially d-none) -->
         <div class="row exception-date-row align-items-center d-none" id="exception-date-row-template">
            <!-- Exception Date -->
            <div class="col-md-2 mb-2">
               <label class="form-label">Date</label>
               <input type="date" name="exception_dates[]" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
               <div class="invalid-feedback">Please select a valid date.</div>
               <div class="valid-feedback">Looks good!</div>
            </div>
            <!-- Reason -->
            <div class="col-md-2 mb-2">
               <label class="form-label">Reason</label>
               <select name="exception_reasons[]" class="form-select form-select-sm">
                  <option value="">Select</option>
                  <option value="Client Cancelled">Client Cancelled</option>
                  <option value="Agent Absent">Agent Absent</option>
                  <option value="Public Holiday">Public Holiday</option>
                  <option value="Other">Other</option>
               </select>
               <div class="invalid-feedback">Please select a reason.</div>
               <div class="valid-feedback">Looks good!</div>
            </div>
            <!-- Remove Button -->
            <div class="col-md-2 mb-1">
               <div class="d-flex h-100 align-items-end mt-4">
                  <button type="button" class="btn btn-subtle-danger btn-sm remove-exception-btn form-control form-control-sm date-remove-btn">Remove</button>
               </div>
            </div>
         </div>
         <!-- Add Exception Button -->
         <button type="button" class="btn btn-subtle-primary btn-sm" id="add-exception-date-btn">
         + Add Exception Date
         </button>
      </div>
         <?php echo section_divider(); ?>

      <!-- Public Holidays Section -->
      <div class="mb-4">
         <h6 class="mb-2">Public Holidays in Schedule</h6>
         <p class="text-muted small ">By default, classes are not scheduled on public holidays. The system will only show holidays that conflict with your class schedule (when a holiday falls on a scheduled class day). You can override specific holidays to include them in the schedule.</p>
         <!-- No holidays message -->
         <div id="no-holidays-message" class="alert alert-subtle-primary" role="alert">No public holidays that conflict with your class schedule were found. Holidays are only shown when they fall on a scheduled class day.</div>
         <!-- Holidays table container -->
         <div id="holidays-table-container" class="card-body card-body card px-5 d-none" >
            <div class="table-responsive">
               <table class="table table-sm fs-9  table-hover">
                  <thead>
                     <tr>
                        <th style="width: 50px;">
                           <div class="form-check">&nbsp;</div>
                        </th>
                        <th>Date</th>
                        <th>Holiday</th>
                        <th>Override</th>
                     </tr>
                  </thead>
                  <tbody id="holidays-list">
                     <!-- Holidays will be populated here dynamically -->
                  </tbody>
               </table>
            </div>
            <div class="d-flex justify-content-between mt-2">
               <div>
                  <button type="button" class="btn btn-subtle-secondary btn-sm" id="skip-all-holidays-btn">Skip All Holidays</button>
                  <button type="button" class="btn btn-subtle-secondary btn-sm" id="override-all-holidays-btn">Override All Holidays</button>
               </div>
            </div>
         </div>
      </div>
      <!-- Holiday Row Template (for JavaScript) -->
      <template id="holiday-row-template">
         <tr class="holiday-row">
            <td>
               <div class="form-check">
                  <input class="form-check-input holiday-override-checkbox" type="checkbox" id="override-holiday-{id}" data-date="{date}">
                  <label class="form-check-label" for="override-holiday-{id}"></label>
               </div>
            </td>
            <td class="holiday-date">{formatted_date}</td>
            <td class="holiday-name">{name}</td>
            <td class="holiday-status">
               <span class="badge badge-phoenix badge-phoenix-danger holiday-skipped">Skipped</span>
               <span class="badge badge-phoenix badge-phoenix-info holiday-overridden d-none">Included</span>
            </td>
         </tr>
      </template>
      <!-- Hidden input to store holiday override data -->
      <input type="hidden" id="holiday_overrides" name="schedule_data[holiday_overrides]" value="">

   </div>
   <?php echo section_divider(); ?>
   <!-- Class Date History Section -->
   <div class="mb-4 mt-3">
      <h6 class="mb-2">Add Stop and Restart Dates.</h6>
      <p class="text-muted small mb-1">A class can have multiple stop and restart dates.</p>
      <!-- Container for all date history rows -->
      <div id="date-history-container"></div>
      <!-- Hidden Template Row (initially d-none) -->
      <div class="row date-history-row d-none" id="date-history-row-template">
         <!-- Stop Date -->
         <div class="col-md-5 mb-2">
            <label class="form-label">Stop Date</label>
            <input type="date" name="stop_dates[]" class="form-control form-control-sm">
            <div class="invalid-feedback">Please select a valid date.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
         <!-- Restart Date -->
         <div class="col-md-5 mb-2">
            <label class="form-label">Restart Date</label>
            <input type="date" name="restart_dates[]" class="form-control form-control-sm">
            <div class="invalid-feedback">Please select a valid date.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
         <!-- Remove Button -->
         <div class="col-md-2 mb-2">
            <label class="form-label invisible">&nbsp;</label>
            <button type="button" class="btn btn-subtle-danger btn-sm remove-date-row-btn form-control form-control-sm date-remove-btn">Remove</button>
         </div>
      </div>
      <!-- Add Row Button -->
      <button type="button" class="btn btn-subtle-primary btn-sm" id="add-date-history-btn">
      + Add Stop/Restart Dates
      </button>
   </div>
      <?php echo section_divider(); ?>
            <h6>Class End Date</h6>
            <small class="text-muted mb-1">If you make any changes to the schedule, you will need to recalculate the end date.</small>
        <div class="col-md-2">
            <label for="schedule_end_date" class="form-label">Estimated End Date <span class="text-danger">*</span></label>
            <input type="date" id="schedule_end_date" name="schedule_end_date" class="form-control form-control-sm readonly-field" placeholder="YYYY-MM-DD" readonly="" required>
            <div class="invalid-feedback">Please generate the end date.</div>
            <div class="valid-feedback">Looks good!</div>
            <button type="button" class="btn btn-subtle-warning mb-2 mt-2" id="calculate_schedule_end_date-btn">
               Calculate Estimated End Date
            </button>
         </div>
      <?php echo section_divider(); ?>

      <!-- Event Dates Section -->
      <div class="mb-4">
         <h6 class="mb-2">Event Dates</h6>
         <p class="text-muted small mb-3">Add key event dates (does not affect training hours or schedule).</p>

         <!-- Container for event rows -->
         <div id="event-dates-container"></div>

         <!-- Hidden Template Row -->
         <div class="row event-date-row align-items-end mb-2 d-none" id="event-date-row-template">
            <div class="col-md-2">
               <label class="form-label">Event Type</label>
               <select name="event_types[]" class="form-select form-select-sm">
                  <option value="">Select</option>
                  <option value="Deliveries">Deliveries</option>
                  <option value="Collections">Collections</option>
                  <option value="Exams">Exams</option>
                  <option value="Mock Exams">Mock Exams</option>
                  <option value="SBA Collection">SBA Collection</option>
                  <option value="Learner Packs">Learner Packs</option>
                  <option value="QA Visit">QA Visit</option>
                  <option value="SETA Exit">SETA Exit</option>
               </select>
            </div>
            <div class="col-md-2">
               <label class="form-label">Description</label>
               <input type="text" name="event_descriptions[]" class="form-control form-control-sm" placeholder="e.g., Deliver Initial Material" maxlength="255">
            </div>
            <div class="col-md-2">
               <label class="form-label">Date</label>
               <input type="date" name="event_dates_input[]" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
               <label class="form-label">Status</label>
               <select name="event_statuses[]" class="form-select form-select-sm">
                  <option value="Pending" selected>Pending</option>
                  <option value="Completed">Completed</option>
                  <option value="Cancelled">Cancelled</option>
               </select>
            </div>
            <div class="col-md-2">
               <label class="form-label">Notes (Optional)</label>
               <input type="text" name="event_notes[]" class="form-control form-control-sm" placeholder="Optional notes" maxlength="500">
            </div>
            <div class="col-md-2">
               <button type="button" class="btn btn-subtle-danger btn-sm remove-event-btn w-100">Remove</button>
            </div>
         </div>

         <!-- Add Event Button -->
         <button type="button" class="btn btn-subtle-primary btn-sm" id="add-event-date-btn">
            + Add Event Date
         </button>
      </div>

      <?php echo section_divider(); ?>

   <!-- ===== Section: Funding & Exam Details ===== -->
   <?php echo section_header('Funding & Exam Details'); ?>
   <div class="row">
      <!-- SETA Funded -->
      <div class="col-md-2">
         <label for="seta_funded" class="form-label">SETA Funded? <span class="text-danger">*</span></label>
         <select id="seta_funded" name="seta_funded" class="form-select form-select-sm" required>
            <option value="">Select</option>
            <?php foreach ($data['yes_no_options'] as $option): ?>
            <option value="<?php echo $option['id']; ?>"><?php echo $option['name']; ?></option>
            <?php endforeach; ?>
         </select>
         <div class="invalid-feedback">Please select if the class is SETA funded.</div>
         <div class="valid-feedback">Looks good!</div>
      </div>
      <!-- SETA (conditionally displayed) -->
      <div class="col-md-3 " id="seta_container" style="display: none;">
         <label for="seta_id" class="form-label">SETA <span class="text-danger">*</span></label>
         <select id="seta_id" name="seta_id" class="form-select form-select-sm">
            <option value="">Select</option>
            <?php foreach ($data['setas'] as $seta): ?>
            <option value="<?php echo $seta['id']; ?>"><?php echo $seta['name']; ?></option>
            <?php endforeach; ?>
         </select>
         <div class="invalid-feedback">Please select a SETA.</div>
         <div class="valid-feedback">Looks good!</div>
      </div>
      <!-- Exam Class -->
      <div class="col-md-2">
         <label for="exam_class" class="form-label">Exam Class <span class="text-danger">*</span></label>
         <select id="exam_class" name="exam_class" class="form-select form-select-sm" required>
            <option value="">Select</option>
            <?php foreach ($data['yes_no_options'] as $option): ?>
            <option value="<?php echo $option['id']; ?>"><?php echo $option['name']; ?></option>
            <?php endforeach; ?>
         </select>
         <div class="invalid-feedback">Please select if this is an exam class.</div>
         <div class="valid-feedback">Looks good!</div>
      </div>
      <!-- Exam Type (conditionally displayed) -->
      <div class="col-md-3 ">
         <div id="exam_type_container" style="display: none;">
            <label for="exam_type" class="form-label">Exam Type</label>
            <input type="text" id="exam_type" name="exam_type" class="form-control form-control-sm" placeholder="Enter exam type">
            <div class="invalid-feedback">Please provide the exam type.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
      </div>
   </div>
   <?php echo section_divider(); ?>
      <!-- Schedule Statistics Section -->
      <!-- Hidden inputs to store schedule data in the format expected by the backend -->
      <div id="schedule-data-container">
         <!-- These will be populated dynamically via JavaScript -->
      </div>
      
      <!-- Schedule Statistics Section (hidden by default) -->
      <div class="card shadow-none border  d-none" id="schedule-statistics-section" data-component-card="data-component-card">
         <div class="card-header p-3 border-bottom bg-body">
            <h4 class="text-body " data-anchor="schedule-statistics" id="schedule-statistics">
               Schedule Statistics
               <a class="anchorjs-link" aria-label="Anchor" data-anchorjs-icon="#" href="#schedule-statistics" style="margin-left:0.1875em; padding:0 0.1875em;"></a>
            </h4>
         </div>
         <div class="card-body p-4">
            <div class="table-responsive scrollbar ">
               <table class="table table-sm fs-9  overflow-hidden">
                  <thead class="text-body">
                     <tr>
                        <th class="sort pe-1 align-middle white-space-nowrap">Category</th>
                        <th class="sort pe-1 align-middle white-space-nowrap">Metric</th>
                        <th class="sort pe-1 align-middle white-space-nowrap" colspan="3">Value</th>
                     </tr>
                  </thead>
                  <tbody id="schedule-statistics-table">
                     <!-- Training Duration Statistics -->
                     <tr class="ydcoza-table-subheader">
                        <th colspan="5">Training Duration</th>
                     </tr>
                     <tr>
                        <td rowspan="3" class="align-middle">Time Period</td>
                        <td>Total Calendar Days</td>
                        <td colspan="3" id="stat-total-days">-</td>
                     </tr>
                     <tr>
                        <td>Total Weeks</td>
                        <td colspan="3" id="stat-total-weeks">-</td>
                     </tr>
                     <tr>
                        <td>Total Months</td>
                        <td colspan="3" id="stat-total-months">-</td>
                     </tr>
                     <!-- Class Session Statistics -->
                     <tr class="ydcoza-table-subheader">
                        <th colspan="5">Class Sessions</th>
                     </tr>
                     <tr>
                        <td rowspan="3" class="align-middle">Sessions</td>
                        <td>Total Scheduled Classes</td>
                        <td colspan="3" id="stat-total-classes">-</td>
                     </tr>
                     <tr>
                        <td>Total Training Hours</td>
                        <td colspan="3" id="stat-total-hours">-</td>
                     </tr>
                     <tr>
                        <td>Average Hours per Month</td>
                        <td colspan="3" id="stat-avg-hours-month">-</td>
                     </tr>
                     <!-- Attendance Impact Statistics -->
                     <tr class="ydcoza-table-subheader">
                        <th colspan="5">Attendance Impact</th>
                     </tr>
                     <tr>
                        <td rowspan="3" class="align-middle">Adjustments</td>
                        <td>Holidays Affecting Classes</td>
                        <td colspan="3" id="stat-holidays-affecting">-</td>
                     </tr>
                     <tr>
                        <td>Exception Dates</td>
                        <td colspan="3" id="stat-exception-dates">-</td>
                     </tr>
                     <tr>
                        <td>Actual Training Days</td>
                        <td colspan="3" id="stat-actual-days">-</td>
                     </tr>
                     <!-- Event Dates Statistics -->
                     <tr class="ydcoza-table-subheader">
                        <th colspan="5">Event Dates</th>
                     </tr>
                     <tr class="ydcoza-table-subheader" style="font-size: 0.85em; font-weight: 500;">
                        <th>Type</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                     </tr>
                     <tr id="event-dates-stats-empty-row">
                        <td colspan="5" class="text-muted">No event dates added</td>
                     </tr>
                     <!-- Dynamic event date rows will be inserted here by JavaScript -->
                  </tbody>
               </table>
            </div>
         </div>
      </div>
      <!-- Schedule Statistics Toggle Button -->
      <div class="mt-3 ">
         <button type="button" class="btn btn-subtle-primary btn-sm" id="toggle-statistics-btn">
         <i class="bi bi-bar-chart-line me-1"></i> View Schedule Statistics
         </button>
         <div class="clearfix"></div>
         <small class="text-muted mb-1">Click to view detailed statistics about the class schedule</small>
      </div>

   <?php echo section_divider(); ?>
   <!-- Class Learners Section -->
   <h6 class="mt-3 mb-1">Class Learners <span class="text-danger">*</span></h6>
   <p class="text-muted small mb-2">Select learners for this class and manage their status.</p>
   <div class="row mb-4">
      <!-- Learner Selection Table -->
      <div class="col-12">
         <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
               <div class="form-label mb-0">Available Learners</div>
            </div>
            <!-- Learner Selection Table -->
            <div class="card shadow-none border p-4 py-2" data-component-card="data-component-card">
               <div class="card-header p-2 border-bottom">
                  <div class="row g-2 align-items-center">
                     <div class="col-auto">
                           <div class="search-box">
                              <div class="position-relative" role="search">
                                 <input class="form-control search-input search form-control-sm" type="search" id="learner-search-input" placeholder="Search learners..." aria-label="Search">
                                 <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg>
                              </div>
                           </div>
                     </div>
                     <div class="col-auto">
                        <small class="text-muted">Showing <span id="learner-showing-count">0</span> of <span id="learner-total-count"><?php echo count($data['learners']); ?></span> learners</small>
                     </div>
                  </div>
               </div>
               <div class="card-body p-4 py-2 pt-0">
                  <div class="table-responsive">
                     <table id="learner-selection-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                        <thead class="border-bottom">
                           <tr>
                              <th scope="col" class="border-0 ps-3" style="width: 40px;">
                                 <div class="form-check">
                                    <!-- <input class="form-check-input" type="checkbox" id="select-all-learners"> -->
                                 </div>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="first_name" style="cursor: pointer;">
                                 First Name
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="second_name" style="cursor: pointer;">
                                 Second Name
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="initials" style="cursor: pointer;">
                                 Initials
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="surname" style="cursor: pointer;">
                                 Surname
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="id_number" style="cursor: pointer;">
                                 ID/Passport
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="city_town_name" style="cursor: pointer;">
                                 City/Town
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="province_region_name" style="cursor: pointer;">
                                 Province/Region
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="postal_code" style="cursor: pointer;">
                                 Postal Code
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0 sortable-column" data-field="last_course_name" style="cursor: pointer;">
                                 Last Course
                                 <i class="bi bi-arrow-down-up ms-1"></i>
                              </th>
                              <th scope="col" class="border-0" style="width: 60px;">
                                 Active LP
                              </th>
                           </tr>
                        </thead>
                        <tbody id="learner-selection-tbody">
                           <?php foreach ($data['learners'] as $index => $learner): ?>
                           <tr class="learner-row" data-learner-id="<?php echo $learner['id']; ?>" data-index="<?php echo $index; ?>">
                              <td class="py-2 align-middle text-center">
                                 <div class="form-check ps-5" style="min-height: auto;">
                                    <input class="form-check-input learner-checkbox" type="checkbox" 
                                           value="<?php echo $learner['id']; ?>" 
                                           data-learner-data="<?php echo htmlspecialchars(json_encode($learner), ENT_QUOTES, 'UTF-8'); ?>">
                                 </div>
                              </td>
                              <td class="py-2 align-middle"><?php echo esc_html($learner['first_name']); ?></td>
                              <td class="py-2 align-middle"><?php echo esc_html($learner['second_name'] ?? ''); ?></td>
                              <td class="py-2 align-middle"><?php echo esc_html($learner['initials'] ?? ''); ?></td>
                              <td class="py-2 align-middle fw-medium"><?php echo esc_html($learner['surname']); ?></td>
                              <td class="py-2 align-middle">
                                 <?php if ($learner['id_type'] === 'sa_id' && !empty($learner['id_number'])): ?>
                                 <span class="badge fs-10 badge-phoenix badge-phoenix-primary">
                                    <?php echo esc_html($learner['id_number']); ?>
                                 </span>
                                 <?php elseif ($learner['id_type'] === 'passport' && !empty($learner['id_number'])): ?>
                                 <span class="badge fs-10 badge-phoenix badge-phoenix-warning">
                                    <?php echo esc_html($learner['id_number']); ?>
                                 </span>
                                 <?php else: ?>
                                 <span class="text-muted">-</span>
                                 <?php endif; ?>
                              </td>
                              <td class="py-2 align-middle"><?php echo esc_html($learner['city_town_name'] ?? '-'); ?></td>
                              <td class="py-2 align-middle"><?php echo esc_html($learner['province_region_name'] ?? '-'); ?></td>
                              <td class="py-2 align-middle"><?php echo esc_html($learner['postal_code'] ?? '-'); ?></td>
                              <td class="py-2 align-middle">
                                 <?php if (!empty($learner['last_course_name'])): ?>
                                 <span class="badge fs-10 badge-phoenix badge-phoenix-success" title="Completed: <?php echo esc_attr($learner['last_completion_date'] ?? ''); ?>">
                                    <?php echo esc_html($learner['last_course_name']); ?>
                                 </span>
                                 <?php else: ?>
                                 <span class="text-muted">-</span>
                                 <?php endif; ?>
                              </td>
                              <td class="py-2 align-middle text-center">
                                 <?php if (!empty($learner['has_active_lp'])): ?>
                                 <span class="badge fs-10 badge-phoenix badge-phoenix-warning"
                                       title="<?php echo esc_attr($learner['active_course_name'] . ' (' . $learner['active_progress_pct'] . '% complete)'); ?>"
                                       data-active-lp="true"
                                       data-active-course="<?php echo esc_attr($learner['active_course_name']); ?>"
                                       data-active-progress="<?php echo esc_attr($learner['active_progress_pct']); ?>"
                                       data-active-class-code="<?php echo esc_attr($learner['active_class_code'] ?? ''); ?>">
                                    <i class="bi bi-exclamation-triangle"></i>
                                 </span>
                                 <?php else: ?>
                                 <span class="text-muted">-</span>
                                 <?php endif; ?>
                              </td>
                           </tr>
                           <?php endforeach; ?>
                        </tbody>
                     </table>
                  </div>
               </div>
               <!-- Pagination -->
               <div id="classes-pagination" class="d-flex justify-content-between mt-3">
                  <span class="d-none d-sm-inline-block ms-5" data-list-info="data-list-info">
                     <span id="pagination-start">1</span> to <span id="pagination-end"><?php echo min(10, count($data['learners'])); ?></span>
                     <span class="text-body-tertiary"> Items of </span>
                     <span id="pagination-total"><?php echo count($data['learners']); ?></span>
                  </span>
                  <nav aria-label="Classes pagination">
                     <ul class="pagination pagination-sm" id="learner-pagination-ul">
                        <li class="page-item">
                           <a class="page-link" href="#" id="learner-pagination-prev" data-list-pagination="prev" aria-label="Previous">
                              <span aria-hidden="true">«</span>
                           </a>
                        </li>
                        <!-- Pagination numbers will be generated here -->
                        <li class="page-item">
                           <a class="page-link" href="#" id="learner-pagination-next" data-list-pagination="next" aria-label="Next">
                              <span aria-hidden="true">»</span>
                           </a>
                        </li>
                     </ul>
                  </nav>
               </div>
            </div>
            <button type="button" class="btn btn-subtle-primary btn-sm mt-3" id="add-selected-learners-btn"><i class="bi bi-plus-circle me-1"></i>Add Selected Learners</button>
         </div>
      </div>
      <!-- Learners Table -->
      <div class="col-12">
         <div class="">
            <div class="form-label mb-2">Class Learners</div>
            <div id="class-learners-container" class="card-body card px-5">
                <div class="alert alert-subtle-primary mb-0" id="no-learners-message" role="alert">No learners added to this class yet. Select learners from the list and click "Add Selected Learners</div>
                <table class="table table-sm fs-9 d-none" id="class-learners-table">
                  <thead>
                     <tr>
                        <th>Learner</th>
                        <th>Level/Module</th>
                        <th>Status</th>
                        <th>Actions</th>
                     </tr>
                  </thead>
                  <tbody id="class-learners-tbody">
                     <!-- Learner rows will be added here dynamically -->
                  </tbody>
               </table>
            </div>
            <!-- Hidden field to store learner data -->
            <input type="hidden" id="class_learners_data" name="class_learners_data" value="">
         </div>
      </div>
   </div>
   <!-- Exam Learners (conditionally displayed) -->
   <div class="row mt-5" id="exam_learners_container"> <!-- YDCOZA d-none -->
      <?php echo section_divider(); ?>
      <div class="col-12">
         <h5 class="">Select Learners Taking Exams</h5>
         <p class="text-muted small ">Not all learners in an exam class necessarily take exams. Select which learners will take exams.</p>
         <div class="row mb-4">
            <!-- Exam Learner Selection -->
            <div class="col-md-4">
               <!-- For multi-select with floating labels, we need a custom approach -->
               <div class="">
                  <label for="add_learner" class="form-label">Select Learners</label>
                  <select id="exam_learner_select" name="exam_learner_select[]" class="form-select form-select-sm" aria-label="Exam learner selection" multiple>
                     <!-- Will be populated dynamically with class learners -->
                  </select>
                  <small class="text-muted">Select learners who will take exams in this class. Hold Ctrl/Cmd to select multiple.</small>
                  <div class="invalid-feedback">Please select at least one learner for exams.</div>
                  <div class="valid-feedback">Looks good!</div>
                  <button type="button" class="btn btn-subtle-primary btn-sm mt-2" id="add-selected-exam-learners-btn">
                  Add Selected Exam Learners
                  </button>
               </div>
            </div>
            <!-- Exam Learners List -->
            <div class="col-md-8">
               <div class="">
                  <div class="form-label mb-2">Learners Taking Exams</div>
                  <div id="exam-learners-list" class="card-body card px-5">
                     <div class="alert alert-subtle-primary" id="no-exam-learners-message">
                        No exam learners added yet. Select learners from the list and click "Add Selected Exam Learners".
                     </div>
                     <table class="table table-sm fs-9 d-none" id="exam-learners-table">
                        <thead>
                           <tr>
                              <th>Learner</th>
                              <th>Level/Module</th>
                              <th>Status</th>
                              <th>Actions</th>
                           </tr>
                        </thead>
                        <tbody id="exam-learners-tbody">
                           <!-- Exam learner rows will be added here dynamically -->
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
         <!-- Hidden field to store exam learners data -->
         <input type="hidden" id="exam_learners" name="exam_learners" value="">
      </div>
   </div>
   <?php echo section_divider(); ?>
   <!-- ===== Section: Assignments & Dates ===== -->
   <?php echo section_header('Agents'); ?>
   <!-- Class Agents Section -->
   <div class="mb-4">
      <h6 class="mb-2">Class Agents</h6>
      <p class="text-muted small mb-1">Assign the primary class agent. If the agent changes during the class, the history will be tracked.</p>
      <!-- Initial Class Agent -->
      <div class="row mb-2">
         <div class="col-md-3">
            <label for="initial_class_agent" class="form-label">Initial Class Agent <span class="text-danger">*</span></label>
            <select id="initial_class_agent" name="initial_class_agent" class="form-select form-select-sm" required>
               <option value="">Select</option>
               <?php foreach ($data['agents'] as $agent): ?>
               <option value="<?php echo $agent['id']; ?>"><?php echo $agent['name']; ?></option>
               <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select the initial class agent.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
         <div class="col-md-3">
            <label for="initial_agent_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
            <input type="date" id="initial_agent_start_date" name="initial_agent_start_date" class="form-control form-control-sm" placeholder="YYYY-MM-DD" required>
            <div class="invalid-feedback">Please select the start date.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
         <div class="col-md-3">
            <label for="project_supervisor" class="form-label">Project Supervisor <span class="text-danger">*</span></label>
            <select id="project_supervisor" name="project_supervisor" class="form-select form-select-sm" required>
               <option value="">Select</option>
               <?php foreach ($data['supervisors'] as $supervisor): ?>
               <option value="<?php echo $supervisor['id']; ?>"><?php echo $supervisor['name']; ?></option>
               <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select a project supervisor.</div>
            <div class="valid-feedback">Looks good!</div>
         </div>
      </div>
      <!-- Project Supervisor -->
      <!-- Backup Agents Section -->
      <div class="mt-4 mb-4">
         <h6 class="mb-2">Backup Agents</h6>
         <p class="text-muted small mb-1">Add backup agents with specific dates when they will be available.</p>
         <!-- Container for all backup agent rows -->
         <div id="backup-agents-container"></div>
         <!-- Hidden Template Row (initially d-none) -->
         <div class="row backup-agent-row align-items-center d-none" id="backup-agent-row-template">
            <!-- Backup Agent -->
            <div class="col-md-3 mb-2">
               <label class="form-label">Backup Agent</label>
               <select name="backup_agent_ids[]" class="form-select form-select-sm backup-agent-select">
                  <option value="">Select</option>
                  <?php foreach ($data['agents'] as $agent): ?>
                  <option value="<?php echo $agent['id']; ?>"><?php echo $agent['name']; ?></option>
                  <?php endforeach; ?>
               </select>
               <div class="invalid-feedback">Please s elect a backup agent.</div>
               <div class="valid-feedback">Looks good!</div>
            </div>
            <!-- Backup Date -->
            <div class="col-md-3 mb-2">
               <label class="form-label">Backup Date</label>
               <input type="date" name="backup_agent_dates[]" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
               <div class="invalid-feedback">Please select a valid date.</div>
               <div class="valid-feedback">Looks good!</div>
            </div>
            <!-- Remove Button -->
            <div class="col-md-1 mt-3">
               <div class="d-flex h-100 align-items-end">
                  <button type="button" class="btn btn-subtle-danger btn-sm remove-backup-agent-btn form-control form-control-sm date-remove-btn">Remove</button>
               </div>
            </div>
         </div>
         <!-- Add Row Button -->
         <button type="button" class="btn btn-subtle-primary btn-sm mt-2" id="add-backup-agent-btn">
         + Add Backup Agent
         </button>
      </div>
      <?php echo section_divider(); ?>

      <!-- Submit Button - Mode-aware text -->
      <div class="row mt-4">
         <div class="col-md-3">
            <?php echo button('Create Draft Class', 'submit', 'primary'); ?>
         </div>
      </div>
   </div>
</form>
<!-- Alert container for form messages -->
<div id="form-messages" class="mt-3"></div>
