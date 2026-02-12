<?php
/**
 * Agent Pagination Template
 *
 * This template displays the pagination controls for AJAX updates.
 *
 * @package WeCoza\Core
 * @since 1.0.0
 *
 * @var int $current_page Current page number
 * @var int $total_pages Total number of pages
 * @var int $per_page Items per page
 * @var int $start_index Start index for display
 * @var int $end_index End index for display
 * @var int $total_agents Total number of agents
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pagination-detail">
    <span class="pagination-info">
        <?php printf(
            esc_html__('Showing %1$d to %2$d of %3$d rows', 'wecoza-core'),
            $start_index,
            $end_index,
            $total_agents
        ); ?>
    </span>
    <span class="page-list ms-2">
        <span class="btn-group dropdown dropup">
            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <span class="page-size"><?php echo esc_html($per_page); ?></span>
            </button>
            <div class="dropdown-menu">
                <?php
                $page_sizes = array(10, 25, 50);
                foreach ($page_sizes as $size) : ?>
                <a class="dropdown-item <?php echo ($size == $per_page) ? 'active' : ''; ?>"
                   href="#"
                   data-per-page="<?php echo $size; ?>"><?php echo $size; ?></a>
                <?php endforeach; ?>
            </div>
        </span>
        <?php esc_html_e('rows per page', 'wecoza-core'); ?>
    </span>
</div>

<div class="pagination">
    <ul class="pagination mb-0">
        <?php if ($current_page > 1) : ?>
        <li class="page-item page-pre">
            <a class="page-link"
               href="#"
               data-page="<?php echo $current_page - 1; ?>"
               aria-label="<?php esc_attr_e('previous page', 'wecoza-core'); ?>">‹</a>
        </li>
        <?php else : ?>
        <li class="page-item page-pre disabled">
            <span class="page-link">‹</span>
        </li>
        <?php endif; ?>

        <?php
        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        if ($start_page > 1) : ?>
        <li class="page-item">
            <a class="page-link"
               href="#"
               data-page="1"
               aria-label="<?php esc_attr_e('to page 1', 'wecoza-core'); ?>">1</a>
        </li>
        <?php if ($start_page > 2) : ?>
        <li class="page-item disabled">
            <span class="page-link">...</span>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++) : ?>
        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
            <?php if ($i == $current_page) : ?>
            <span class="page-link"><?php echo $i; ?></span>
            <?php else : ?>
            <a class="page-link"
               href="#"
               data-page="<?php echo $i; ?>"
               aria-label="<?php printf(esc_attr__('to page %d', 'wecoza-core'), $i); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        </li>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages) : ?>
        <?php if ($end_page < $total_pages - 1) : ?>
        <li class="page-item disabled">
            <span class="page-link">...</span>
        </li>
        <?php endif; ?>
        <li class="page-item">
            <a class="page-link"
               href="#"
               data-page="<?php echo $total_pages; ?>"
               aria-label="<?php printf(esc_attr__('to page %d', 'wecoza-core'), $total_pages); ?>"><?php echo $total_pages; ?></a>
        </li>
        <?php endif; ?>

        <?php if ($current_page < $total_pages) : ?>
        <li class="page-item page-next">
            <a class="page-link"
               href="#"
               data-page="<?php echo $current_page + 1; ?>"
               aria-label="<?php esc_attr_e('next page', 'wecoza-core'); ?>">›</a>
        </li>
        <?php else : ?>
        <li class="page-item page-next disabled">
            <span class="page-link">›</span>
        </li>
        <?php endif; ?>
    </ul>
</div>
