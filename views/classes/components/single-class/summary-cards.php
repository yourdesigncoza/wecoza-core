<?php
/**
 * Single Class Summary Cards Component
 *
 * Displays top summary cards with client, type, subject, code, and duration.
 *
 * @package WeCoza\Classes
 * @var array $class Class data array
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure class data is available
if (empty($class)) {
    return;
}
?>

<!-- Top Summary Cards -->
<div class="card mb-3">
   <div class="card-body ydcoza-mini-card-header">
      <div class="row g-4 justify-content-between">
         <!-- Client Card -->
         <div class="col-sm-auto">
            <div class="d-flex align-items-center">
               <div class="d-flex bg-primary-subtle rounded flex-center me-3" style="width:32px; height:32px">
                  <i class="bi bi-building text-primary"></i>
               </div>
               <div>
                  <p class="fw-bold mb-1">Client</p>
                  <h5 class="fw-bolder text-nowrap">
                     <?php if (!empty($class['client_name'])): ?>
                     <?php echo esc_html($class['client_name']); ?>
                     <?php else: ?>
                     N/A
                     <?php endif; ?>
                  </h5>
               </div>
            </div>
         </div>
         <!-- Class Type Card -->
         <div class="col-sm-auto">
            <div class="d-flex align-items-center border-start-sm ps-sm-5">
               <div class="d-flex bg-primary-subtle rounded flex-center me-3" style="width:32px; height:32px">
                  <i class="bi bi-layers text-primary"></i>
               </div>
               <div>
                  <p class="fw-bold mb-1">Class Type</p>
                  <h5 class="fw-bolder text-nowrap"><?php echo esc_html($class['class_type'] ?? 'Unknown Type'); ?></h5>
               </div>
            </div>
         </div>
         <!-- Class Subject Card -->
         <div class="col-sm-auto">
            <div class="d-flex align-items-center border-start-sm ps-sm-5">
               <div class="d-flex bg-success-subtle rounded flex-center me-3" style="width:32px; height:32px">
                  <i class="bi bi-book text-success"></i>
               </div>
               <div>
                  <p class="fw-bold mb-1">Class Subject</p>
                  <h5 class="fw-bolder text-nowrap"><?php echo esc_html($class['class_subject'] ?? 'N/A'); ?></h5>
               </div>
            </div>
         </div>
         <!-- Class Code Card -->
         <div class="col-sm-auto">
            <div class="d-flex align-items-center border-start-sm ps-sm-5">
               <div class="d-flex bg-info-subtle rounded flex-center me-3" style="width:32px; height:32px">
                  <i class="bi bi-tag text-info"></i>
               </div>
               <div>
                  <p class="fw-bold mb-1">Class Code</p>
                  <h5 class="fw-bolder text-nowrap"><?php echo esc_html($class['class_code'] ?? 'N/A'); ?></h5>
               </div>
            </div>
         </div>
         <!-- Total Hours Card -->
         <div class="col-sm-auto">
            <div class="d-flex align-items-center border-start-sm ps-sm-5">
               <div class="d-flex bg-warning-subtle rounded flex-center me-3" style="width:32px; height:32px">
                  <i class="bi bi-clock-history text-warning"></i>
               </div>
               <div>
                  <p class="fw-bold mb-1">Total Hours</p>
                  <h5 class="fw-bolder text-nowrap"><?php echo isset($class['class_duration']) ? number_format($class['class_duration'], 0) : 'N/A'; ?></h5>
               </div>
            </div>
         </div>
         <!-- Class Status Card -->
         <?php
         $classStatus = wecoza_resolve_class_status($class);
         $statusBgClass = match($classStatus) {
             'active'  => 'bg-success-subtle',
             'stopped' => 'bg-danger-subtle',
             default   => 'bg-warning-subtle',
         };
         $statusIconClass = match($classStatus) {
             'active'  => 'bi-check-circle-fill text-success',
             'stopped' => 'bi-stop-circle-fill text-danger',
             default   => 'bi-file-earmark-text-fill text-warning',
         };
         $statusLabel = match($classStatus) {
             'active'  => __('Active', 'wecoza-core'),
             'stopped' => __('Stopped', 'wecoza-core'),
             default   => __('Draft', 'wecoza-core'),
         };
         ?>
         <div class="col-sm-auto">
            <div class="d-flex align-items-center border-start-sm ps-sm-5">
               <div class="d-flex <?= esc_attr($statusBgClass); ?> rounded flex-center me-3" style="width:32px; height:32px">
                  <i class="bi <?= esc_attr($statusIconClass); ?>"></i>
               </div>
               <div>
                  <p class="fw-bold mb-1"><?= esc_html__('Status', 'wecoza-core'); ?></p>
                  <h5 class="fw-bolder text-nowrap"><?= esc_html($statusLabel); ?></h5>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>
