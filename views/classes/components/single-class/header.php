<?php
/**
 * Single Class Header Component
 *
 * Displays loading indicator, error messages, and action buttons.
 *
 * @package WeCoza\Classes
 * @var array $class Class data array
 * @var bool $show_loading Whether to show loading indicator
 * @var string $error_message Error message to display
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are set
$class = $class ?? null;
$show_loading = $show_loading ?? true;
$error_message = $error_message ?? '';
?>

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
   <?php endif; ?>
