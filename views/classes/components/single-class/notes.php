<?php
/**
 * Single Class Display - Class Notes Component
 *
 * Displays the class notes section with:
 * - Notes count badge
 * - Filter controls (priority, sort)
 * - Priority legend
 * - Notes grid with expandable content
 * - Attachments dropdown per note
 * - Author and timestamp display
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

// Process class notes data (reuse existing JSONB processing pattern)
$class_notes_data = [];
if (!empty($class['class_notes_data'])) {
    $class_notes_data = is_string($class['class_notes_data'])
        ? json_decode($class['class_notes_data'], true)
        : $class['class_notes_data'];
    if (!is_array($class_notes_data)) {
        $class_notes_data = [];
    }
}

// Helper function for author name resolution (reuse existing user lookup pattern)
if (!function_exists('getNoteAuthorName')) {
    function getNoteAuthorName($author_id) {
        $user = get_user_by('ID', $author_id);
        return $user ? $user->display_name : 'Unknown User';
    }
}

// Helper function for processing expandable content
if (!function_exists('processExpandableContent')) {
    function processExpandableContent($content, $threshold = 150) {
        if (strlen($content) > $threshold) {
            $truncated = substr($content, 0, $threshold) . '...';
            return [
                'is_expandable' => true,
                'truncated' => $truncated,
                'full' => $content
            ];
        }
        return [
            'is_expandable' => false,
            'content' => $content
        ];
    }
}

// Helper function to generate category badges (server-side version)
if (!function_exists('generateCategoryBadgesServer')) {
    function generateCategoryBadgesServer($categories) {
        if (empty($categories)) {
            return '<span class="note-category-badge note-category-general">general</span>';
        }

        $categories_array = is_string($categories) ? explode(',', $categories) : (array)$categories;
        $badges = '';

        foreach ($categories_array as $category) {
            $category = trim($category);
            $class_name = 'note-category-' . strtolower(str_replace([' ', '&'], ['-', 'and'], $category));
            $badges .= '<span class="note-category-badge ' . esc_attr($class_name) . '">' . esc_html($category) . '</span> ';
        }

        return $badges;
    }
}

// Sort notes by created_at (newest first by default)
if (!empty($class_notes_data)) {
    usort($class_notes_data, function($a, $b) {
        return strtotime($b['created_at'] ?? 0) - strtotime($a['created_at'] ?? 0);
    });
}
?>
<!-- Class Notes -->
<div class="card-body card px-5 mb-3">
      <h5 class="mb-0">
         <i class="bi bi-notebook me-2"></i>Class Notes
         <span class="badge ms-2 badge badge-phoenix badge-phoenix-warning " id="notes-count">
            <?php
            $notes_count = count($class_notes_data);
            echo $notes_count . ' NOTE' . ($notes_count !== 1 ? 'S' : '');
            ?>
         </span>
      </h5>

   <!-- Class Notes Container for dynamic display -->
   <div id="class-notes-container" class="mt-3">
      <!-- Notes Search and Filter Controls -->
      <div class="notes-controls mb-4">
         <div class="row g-2 mb-2">
            <div class="col-md-2">
               <select class="form-select form-select-sm" id="notes-priority-filter">
                  <option value="">All Priorities</option>
                  <option value="high">High Priority</option>
                  <option value="medium">Medium Priority</option>
                  <option value="low">Low Priority</option>
               </select>
            </div>
            <div class="col-md-2">
               <select class="form-select form-select-sm" id="notes-sort">
                  <option value="newest">Newest First</option>
                  <option value="oldest">Oldest First</option>
               </select>
            </div>
            <div class="col-md-2">
               <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-notes-filters" title="Clear all filters">
               <i class="bi bi-arrow-clockwise"></i> Reset Filters
               </button>
            </div>
            <!-- Priority Legend -->
            <div class="col-md-6 mt-3">
               <div class="priority-legend d-flex align-items-center gap-3 flex-wrap">
                  <span class="legend-title small text-muted me-2">Priority:</span>
                  <div class="legend-item d-flex align-items-center">
                     <div class="legend-color-box priority-high"></div>
                     <span class="legend-label small">High</span>
                  </div>
                  <div class="legend-item d-flex align-items-center">
                     <div class="legend-color-box priority-medium"></div>
                     <span class="legend-label small">Medium</span>
                  </div>
                  <div class="legend-item d-flex align-items-center">
                     <div class="legend-color-box priority-low"></div>
                     <span class="legend-label small">Low</span>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Notes Display Area -->
      <div id="notes-display-area">
         <!-- Loading state -->
         <div id="notes-loading" class="text-center py-3 d-none">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
               <span class="visually-hidden">Loading notes...</span>
            </div>
            <div class="mt-2 text-muted small">Loading notes...</div>
         </div>
         <!-- Empty state -->
         <div id="notes-empty" class="text-center py-4 text-muted d-none">
            <i class="bi bi-sticky-note display-4 mb-2"></i>
            <p class="mb-0">No notes yet.</p>
         </div>
         <!-- Notes list -->
         <div id="notes-list">
            <?php if (empty($class_notes_data)): ?>
               <!-- Show empty state -->
               <div id="notes-empty" class="text-center py-4 text-muted">
                  <i class="bi bi-sticky-note display-4 mb-2"></i>
                  <p class="mb-0">No notes yet.</p>
               </div>
            <?php else: ?>
               <div class="notes-grid">
                  <?php foreach ($class_notes_data as $note): ?>
                     <div class="note-card priority-<?php echo esc_attr($note['priority'] ?? 'medium'); ?>" data-note-id="<?php echo esc_attr($note['id'] ?? ''); ?>">
                        <!-- Note header with categories -->
                        <div class="note-card-header">
                           <div class="note-card-categories">
                              <?php echo generateCategoryBadgesServer($note['category'] ?? []); ?>
                           </div>
                           <div class="note-card-metadata">
                              <?php if (!empty($note['attachments']) && is_array($note['attachments'])): ?>
                                 <!-- Attachments dropdown -->
                                 <div class="dropdown note-attachments-dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle note-attachments-indicator" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?php echo count($note['attachments']); ?> attachment(s)">
                                       <i class="bi bi-paperclip"></i>
                                       <span><?php echo count($note['attachments']); ?></span>
                                    </button>
                                    <ul class="dropdown-menu">
                                       <?php foreach ($note['attachments'] as $attachment): ?>
                                          <li>
                                             <a class="dropdown-item" href="<?php echo esc_url($attachment['url'] ?? '#'); ?>" target="_blank" download="<?php echo esc_attr($attachment['filename'] ?? ''); ?>">
                                                <i class="bi bi-download me-2"></i><?php echo esc_html($attachment['filename'] ?? 'Unknown file'); ?>
                                             </a>
                                          </li>
                                       <?php endforeach; ?>
                                    </ul>
                                 </div>
                              <?php endif; ?>
                           </div>
                        </div>

                        <!-- Note content -->
                        <div class="note-card-body">
                           <?php
                           $content_data = processExpandableContent($note['content'] ?? '');
                           if ($content_data['is_expandable']): ?>
                              <div class="note-content-expandable">
                                 <div class="note-content-full note-content-collapsed" data-full-content="<?php echo esc_attr($content_data['full']); ?>">
                                    <?php echo esc_html($content_data['truncated']); ?>
                                 </div>
                                 <button type="button" class="note-expand-btn fs-10" onclick="toggleNoteContent(this)">
                                    <i class="bi bi-chevron-down me-1"></i>Show More
                                 </button>
                              </div>
                           <?php else: ?>
                              <div class="note-content-full">
                                 <?php echo esc_html($content_data['content']); ?>
                              </div>
                           <?php endif; ?>
                        </div>

                        <!-- Note footer with author and timestamp -->
                        <div class="note-card-footer">
                           <div class="note-card-meta">
                              <span><i class="bi bi-person"></i> <?php echo esc_html(getNoteAuthorName($note['author_id'] ?? 0)); ?></span>
                              <span title="<?php echo esc_attr($note['created_at'] ?? ''); ?>">
                                 <i class="bi bi-calendar"></i>
                                 <?php
                                 if (!empty($note['created_at'])) {
                                     echo date('M j, Y', strtotime($note['created_at']));
                                 } else {
                                     echo 'Unknown date';
                                 }
                                 ?>
                              </span>
                           </div>
                        </div>
                     </div>
                  <?php endforeach; ?>
               </div>
            <?php endif; ?>
         </div>
         <!-- No results state -->
         <div id="notes-no-results" class="text-center py-3 text-muted d-none">
            <i class="bi bi-search display-4 mb-2"></i>
            <p class="mb-0">No notes found matching your search criteria.</p>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="clear-notes-filters">
            Clear Filters
            </button>
         </div>
      </div>
      <!-- Notes Pagination -->
      <nav aria-label="Notes pagination" id="notes-pagination-nav" class="mt-3" style="display: none;">
         <ul class="pagination pagination-sm justify-content-center" id="notes-pagination">
            <!-- Pagination will be generated here -->
         </ul>
      </nav>
   </div>
</div>
