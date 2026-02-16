<?php
/**
 * Single Class Display - QA Reports Component
 *
 * Displays the Quality Assurance Reports section with:
 * - Reports count badge
 * - Table of QA reports with details
 * - Download links for report files
 *
 * @package WeCoza
 * @subpackage Views/Components/SingleClass
 *
 * Required Variables:
 *   - $class: Array of class data from the database
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Ensure variables are available
$class = $class ?? [];

// Only render if QA reports exist
if (empty($class['qa_reports']) || !is_array($class['qa_reports'])) {
    return;
}
?>
<!-- QA Reports Section -->
<div class="card mb-4">
   <div class="card-header">
      <h5 class="mb-0">
         <i class="bi bi-file-earmark-check me-2"></i>Quality Assurance Reports
         <span class="badge badge-phoenix badge-phoenix-secondary ms-2"><?php echo count($class['qa_reports']); ?></span>
      </h5>
   </div>
   <div class="card-body">
      <div class="table-responsive">
         <table class="table table-sm">
            <thead>
               <tr>
                  <th>Report Date</th>
                  <th>Report Type</th>
                  <th>QA Officer</th>
                  <th>File Name</th>
                  <th>Uploaded By</th>
                  <th>Actions</th>
               </tr>
            </thead>
            <tbody>
               <?php foreach ($class['qa_reports'] as $index => $report): ?>
               <tr>
                  <td>
                     <?php
                        $reportDate = isset($report['date']) ? wp_date('M j, Y', strtotime($report['date'])) : 'N/A';
                        echo esc_html($reportDate);
                        ?>
                  </td>
                  <td>
                     <?php echo esc_html($report['type'] ?? 'Standard QA'); ?>
                  </td>
                  <td>
                     <?php echo esc_html($report['officer'] ?? 'N/A'); ?>
                  </td>
                  <td>
                     <i class="bi bi-file-pdf text-danger me-1"></i>
                     <?php echo esc_html($report['filename'] ?? 'qa_report_' . ($index + 1) . '.pdf'); ?>
                  </td>
                  <td>
                     <?php echo esc_html($report['uploaded_by'] ?? 'System'); ?>
                  </td>
                  <td>
                     <?php if (!empty($report['file_path'])): ?>
                     <a href="<?php echo esc_url($report['file_path']); ?>"
                        class="btn btn-sm btn-outline-primary"
                        download
                        title="Download Report">
                     <i class="bi bi-download me-1"></i>Download
                     </a>
                     <?php else: ?>
                     <span class="text-muted">File not available</span>
                     <?php endif; ?>
                  </td>
               </tr>
               <?php endforeach; ?>
            </tbody>
         </table>
      </div>
   </div>
</div>
