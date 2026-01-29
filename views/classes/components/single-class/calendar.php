<?php
/**
 * Single Class Display - Calendar Component
 *
 * Displays the Class Schedule Calendar section with:
 * - Tab navigation (Calendar View / List View)
 * - FullCalendar integration container
 * - Calendar loading and error states
 * - Calendar legend
 * - List view with filters
 * - List view loading, error, and empty states
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables:
 *   - $class: Array of class data from the database
 *
 * Note: The JavaScript initialization is handled in single-class-display.js
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];
?>
<!-- Class Calendar Section -->
<div class="card mb-4">
   <div class="card-header">
      <h4 class="mb-0">
         <i class="bi bi-calendar3 me-2"></i>Class Schedule Calendar
      </h4>
   </div>
   <div class="card-body">
      <!-- View Toggle Navigation -->
      <div class="mb-3">
         <ul class="nav nav-tabs" id="scheduleViewTabs" role="tablist">
            <li class="nav-item" role="presentation">
               <button class="nav-link active" id="calendar-view-tab" data-bs-toggle="tab" data-bs-target="#calendar-view" type="button" role="tab" aria-controls="calendar-view" aria-selected="true">
               <i class="bi bi-calendar3 me-2"></i>Calendar View
               </button>
            </li>
            <li class="nav-item" role="presentation">
               <button class="nav-link" id="list-view-tab" data-bs-toggle="tab" data-bs-target="#list-view" type="button" role="tab" aria-controls="list-view" aria-selected="false">
               <i class="bi bi-list-ul me-2"></i>List View
               </button>
            </li>
         </ul>
      </div>
      <!-- Tab Content Container -->
      <div class="tab-content" id="scheduleViewContent">
         <!-- Calendar View Tab Pane -->
         <div class="tab-pane fade show active" id="calendar-view" role="tabpanel" aria-labelledby="calendar-view-tab">
            <!-- Calendar Container -->
            <div id="classCalendar" class="mb-4">
               <!-- FullCalendar will be rendered here -->
            </div>
            <!-- Calendar Loading State -->
            <div id="calendar-loading" class="text-center py-4">
               <div class="spinner-border text-primary me-2" role="status">
                  <span class="visually-hidden">Loading calendar...</span>
               </div>
               <span class="text-muted">Loading class schedule...</span>
            </div>
            <!-- Calendar Error State -->
            <div id="calendar-error" class="alert alert-warning d-none">
               <i class="bi bi-exclamation-triangle me-2"></i>
               <strong>Calendar Unavailable:</strong>
               <span id="calendar-error-message">Unable to load class schedule data.</span>
            </div>
            <!-- Calendar Legend -->
            <div class="calendar-legend">
               <div class="legend-item">
                  <div class="legend-color class-event"></div>
                  <span>Class Sessions</span>
               </div>
               <div class="legend-item">
                  <div class="legend-color public-holiday"></div>
                  <span>Public Holidays</span>
               </div>
               <div class="legend-item">
                  <div class="legend-color exception"></div>
                  <span>Exception Dates</span>
               </div>
               <div class="legend-item">
                  <div class="legend-color stop-restart"></div>
                  <span>Stop Dates</span>
               </div>
               <div class="legend-item">
                  <div class="legend-color stop-restart-restart"></div>
                  <span>Restart Dates</span>
               </div>
               <div class="legend-item">
                  <div class="legend-color stop-period"></div>
                  <span>Stop Period Days</span>
               </div>
            </div>
         </div>
         <!-- List View Tab Pane -->
         <div class="tab-pane fade" id="list-view" role="tabpanel" aria-labelledby="list-view-tab">
            <!-- List View Filters -->
            <div id="listViewFilters" class="mb-3 d-none">
               <div class="row g-2 align-items-end">
                  <div class="col-md-4">
                     <label for="eventTypeFilter" class="form-label text-muted small">Filter by Event Type</label>
                     <select id="eventTypeFilter" class="form-select form-select-sm">
                        <option value="">All Event Types</option>
                        <option value="class_session">Class Sessions</option>
                        <option value="public_holiday">Public Holidays</option>
                        <option value="exception">Exception Dates</option>
                        <option value="stop_date">Stop/Restart Dates</option>
                     </select>
                  </div>
                  <div class="col-md-3">
                     <label for="dateFromFilter" class="form-label text-muted small">From Date</label>
                     <input type="date" id="dateFromFilter" class="form-control form-control-sm">
                  </div>
                  <div class="col-md-3">
                     <label for="dateToFilter" class="form-label text-muted small">To Date</label>
                     <input type="date" id="dateToFilter" class="form-control form-control-sm">
                  </div>
                  <div class="col-md-2">
                     <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm w-100">
                     <i class="bi bi-x-circle me-1"></i>Clear
                     </button>
                  </div>
               </div>
            </div>
            <!-- List View Container -->
            <div id="classScheduleList">
               <!-- List view content will be rendered here -->
            </div>
            <!-- List View Loading State -->
            <div id="list-loading" class="text-center py-4 d-none">
               <div class="spinner-border text-primary me-2" role="status">
                  <span class="visually-hidden">Loading schedule list...</span>
               </div>
               <span class="text-muted">Loading class schedule list...</span>
            </div>
            <!-- List View Error State -->
            <div id="list-error" class="alert alert-warning d-none">
               <i class="bi bi-exclamation-triangle me-2"></i>
               <strong>List View Unavailable:</strong>
               <span id="list-error-message">Unable to load class schedule data.</span>
            </div>
            <!-- List View Empty State -->
            <div id="list-empty" class="text-center py-5 d-none">
               <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
               <h5 class="text-muted mt-3">No Schedule Events Found</h5>
               <p class="text-muted">There are no scheduled events to display for this class.</p>
            </div>
         </div>
      </div>
   </div>
</div>
