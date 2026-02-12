<?php
   /**
    * Agent Display Table Template
    *
    * This template displays the agents table with search, filters, and pagination.
    *
    * @package WeCoza\Core
    * @since 1.0.0
    *
    * @var array $agents Array of agents to display
    * @var int $total_agents Total number of agents
    * @var int $current_page Current page number
    * @var int $per_page Items per page
    * @var int $total_pages Total number of pages
    * @var int $start_index Start index for display
    * @var int $end_index End index for display
    * @var string $search_query Current search query
    * @var string $sort_column Current sort column
    * @var string $sort_order Current sort order (ASC/DESC)
    * @var array $columns Columns to display
    * @var array $atts Shortcode attributes
    * @var bool $can_manage Whether user can manage agents
    */

   // Prevent direct access
   if (!defined('ABSPATH')) {
       exit;
   }
   ?>
<!-- Alert Container -->
<div id="alert-container" class="alert-container"></div>
<!-- Main Content Container -->
<div id="agents-container">
<div id="wecoza-agents-loader-container" class="text-center py-3 d-none">
    <div class="spinner-border spinner-border-sm text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>
   <div class="table-responsive">
      <div class="bootstrap-table bootstrap5">
         <!-- Toolbar -->
         <!-- Table Container -->
         <div class="fixed-table-container" style="padding-bottom: 0px;">
            <div class="card shadow-none border my-4" data-component-card="data-component-card">
               <div class="card-header p-3 border-bottom">
                  <div class="row g-3 justify-content-between align-items-center mb-3">
                     <div class="col-12 col-md">
                        <h4 class="text-body mb-0" data-anchor="data-anchor" id="classes-table-header">
                           All Agents
                           <i class="bi bi-calendar-event ms-2"></i>
                        </h4>
                     </div>
                     <div class="search-box col-auto">
                        <form method="get" action="" class="position-relative d-flex">
                           <input class="form-control search-input search form-control-sm" type="search" placeholder="Search" aria-label="Search">
                           <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                              <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                           </svg>
                        </form>
                     </div>
                     <div class="col-auto">
                        <div class="d-flex gap-2">
                           <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location.reload();">
                           Refresh
                           <i class="bi bi-arrow-clockwise ms-1"></i>
                           </button>
                           <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportAgents()">
                           Export
                           <i class="bi bi-download ms-1"></i>
                           </button>
                        </div>
                     </div>
                  </div>
                  <!-- Summary strip -->
                  <div class="col-12">
                     <div class="scrollbar">
                        <div class="row g-0 flex-nowrap">
                           <?php
                           $stat_keys = array_keys($statistics);
                           $last_key = end($stat_keys);
                           foreach ($statistics as $stat_key => $stat_data) :
                           ?>
                           <div class="col-auto <?php echo $stat_key === 'total_agents' ? 'border-end pe-4' : ($stat_key === $last_key ? 'ps-4' : 'px-4 border-end'); ?>">
                              <h6 class="text-body-tertiary">
                                 <?php echo esc_html($stat_data['label']); ?> : <?php echo esc_html($stat_data['count']); ?>
                                 <?php if (!empty($stat_data['badge'])) : ?>
                                 <div class="badge badge-phoenix fs-10 badge-phoenix-<?php echo esc_attr($stat_data['badge_type']); ?>">
                                    <?php echo esc_html($stat_data['badge']); ?>
                                 </div>
                                 <?php endif; ?>
                              </h6>
                           </div>
                           <?php endforeach; ?>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="card-body p-4 py-2">
                  <div class="fixed-table-body mb-3">
                     <table id="agents-display-data" class="table table-hover table-sm fs-9 mb-0">
                        <thead class="border-bottom">
                           <tr>
                              <?php foreach ($columns as $col_key => $col_label) : ?>
                              <th class="sort" data-field="<?php echo esc_attr($col_key); ?>" data-sortable="true">
                                 <div class="th-inner sortable both">
                                    <?php if ($atts['show_filters']) : ?>
                                    <a href="#" data-column="<?php echo esc_attr($col_key); ?>">
                                    <?php echo esc_html($col_label); ?>
                                    <?php
                                    // Add appropriate icon based on column type
                                    $icon_class = '';
                                    switch($col_key) {
                                        case 'first_name':
                                            $icon_class = 'bi bi-person';
                                            break;
                                        case 'initials':
                                            $icon_class = 'bi bi-type-underline';
                                            break;
                                        case 'last_name':
                                            $icon_class = 'bi bi-person-badge';
                                            break;
                                        case 'gender':
                                            $icon_class = 'bi bi-gender-ambiguous';
                                            break;
                                        case 'race':
                                            $icon_class = 'bi bi-people';
                                            break;
                                        case 'phone':
                                            $icon_class = 'bi bi-telephone';
                                            break;
                                        case 'email':
                                            $icon_class = 'bi bi-envelope';
                                            break;
                                        case 'city':
                                            $icon_class = 'bi bi-geo-alt';
                                            break;
                                        default:
                                            $icon_class = 'bi bi-list-ul';
                                            break;
                                    }
                                    ?>
                                    <i class="<?php echo esc_attr($icon_class); ?> ms-1"></i>
                                    <?php if ($sort_column === $col_key) : ?>
                                    <i class="bi bi-arrow-<?php echo ($sort_order === 'ASC') ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                    </a>
                                    <?php else : ?>
                                    <?php echo esc_html($col_label); ?>
                                    <?php
                                    // Add appropriate icon based on column type
                                    $icon_class = '';
                                    switch($col_key) {
                                        case 'first_name':
                                            $icon_class = 'bi bi-person';
                                            break;
                                        case 'initials':
                                            $icon_class = 'bi bi-type-underline';
                                            break;
                                        case 'last_name':
                                            $icon_class = 'bi bi-person-badge';
                                            break;
                                        case 'gender':
                                            $icon_class = 'bi bi-gender-ambiguous';
                                            break;
                                        case 'race':
                                            $icon_class = 'bi bi-people';
                                            break;
                                        case 'phone':
                                            $icon_class = 'bi bi-telephone';
                                            break;
                                        case 'email':
                                            $icon_class = 'bi bi-envelope';
                                            break;
                                        case 'city':
                                            $icon_class = 'bi bi-geo-alt';
                                            break;
                                        default:
                                            $icon_class = 'bi bi-list-ul';
                                            break;
                                    }
                                    ?>
                                    <i class="<?php echo esc_attr($icon_class); ?> ms-1"></i>
                                    <?php endif; ?>
                                 </div>
                                 <div class="fht-cell"></div>
                              </th>
                              <?php endforeach; ?>
                              <?php if ($atts['show_actions']) : ?>
                              <th class="text-nowrap text-center ydcoza-width-150" data-field="actions">
                                 <div class="th-inner">
                                    <?php esc_html_e('Actions', 'wecoza-core'); ?>
                                    <i class="bi bi-gear ms-1"></i>
                                 </div>
                                 <div class="fht-cell"></div>
                              </th>
                              <?php endif; ?>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           // Render table rows using partial
                           echo wecoza_view('agents/display/agent-display-table-rows', [
                               'agents' => $agents,
                               'columns' => $columns,
                               'can_manage' => $can_manage,
                               'show_actions' => $atts['show_actions']
                           ], true);
                           ?>
                        </tbody>
                     </table>
         <!-- Pagination -->
         <?php if ($atts['show_pagination'] && $total_pages > 1) : ?>
         <div class="fixed-table-pagination d-flex justify-content-between align-items-center flex-wrap mt-3">
            <?php
            // Render pagination controls using partial
            echo wecoza_view('agents/display/agent-pagination', [
                'current_page' => $current_page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
                'start_index' => $start_index,
                'end_index' => $end_index,
                'total_agents' => $total_agents
            ], true);
            ?>
         </div>
         <?php endif; ?>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <div class="clearfix"></div>
   </div>
</div>
