<?php

use WeCoza\Clients\Helpers\ViewHelpers;

$baseUrl = get_permalink();
if (!$baseUrl) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = $requestUri ? strtok($requestUri, '?') : '/';
    $baseUrl = home_url($path);
}

$showSearch = !empty($atts['show_search']);
$editUrl = isset($edit_url) ? $edit_url : '/edit-locations';

$currentArgs = array();
if (!empty($_GET) && is_array($_GET)) {
    foreach ($_GET as $key => $value) {
        if (in_array($key, array('location_search', 'location_page'), true)) {
            continue;
        }
        $currentArgs[$key] = sanitize_text_field(is_array($value) ? implode(',', $value) : $value);
    }
}

// Build pagination args (preserve search)
$paginationArgs = array();
if (!empty($search)) {
    $paginationArgs['location_search'] = $search;
}
?>
<div class="wecoza-locations-list">
    <div class="card shadow-none border my-3" data-component-card="data-component-card">
        <div class="card-header p-3 border-bottom">
            <div class="row g-3 justify-content-between align-items-center mb-3">
                <div class="col-12 col-md">
                    <h4 class="text-body mb-0">
                        <?php esc_html_e('All Locations', 'wecoza-clients'); ?>
                        <i class="bi bi-geo-alt ms-2"></i>
                    </h4>
                </div>
                <?php if ($showSearch) : ?>
                <div class="search-box col-auto">
                    <form class="position-relative" method="get">
                        <?php foreach ($currentArgs as $key => $value) : ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="location_page" value="1">
                        <input class="form-control search-input search form-control-sm" id="location-search-input" type="search" name="location_search" value="<?php echo esc_attr($search ?? ''); ?>" placeholder="<?php esc_attr_e('Search address, suburb, town, province, postal…', 'wecoza-clients'); ?>" aria-label="<?php esc_attr_e('Search', 'wecoza-clients'); ?>">
                        <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg>
                    </form>
                </div>
                <?php endif; ?>
                <div class="col-auto">
                    <span class="badge badge-phoenix fs-10 badge-phoenix-primary"><?php esc_html_e('Total:', 'wecoza-clients'); ?> <?php echo esc_html((int) ($total ?? 0)); ?></span>
                </div>
            </div>
        </div>

        <div class="card-body p-4 py-2">
            <?php if (!empty($locations)) : ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                        <thead class="border-bottom">
                            <tr>
                                <th scope="col" class="border-0 ps-4"><?php esc_html_e('ID', 'wecoza-clients'); ?> <i class="bi bi-hash ms-1"></i></th>
                                <th scope="col" class="border-0"><?php esc_html_e('Street Address', 'wecoza-clients'); ?> <i class="bi bi-signpost ms-1"></i></th>
                                <th scope="col" class="border-0"><?php esc_html_e('Suburb', 'wecoza-clients'); ?> <i class="bi bi-geo ms-1"></i></th>
                                <th scope="col" class="border-0"><?php esc_html_e('Town', 'wecoza-clients'); ?> <i class="bi bi-building ms-1"></i></th>
                                <th scope="col" class="border-0"><?php esc_html_e('Province', 'wecoza-clients'); ?> <i class="bi bi-pin-map ms-1"></i></th>
                                <th scope="col" class="border-0"><?php esc_html_e('Postal Code', 'wecoza-clients'); ?> <i class="bi bi-mailbox ms-1"></i></th>
                                <th scope="col" class="border-0 pe-4 text-center"><?php esc_html_e('Actions', 'wecoza-clients'); ?> <i class="bi bi-gear ms-1"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($locations as $row) : ?>
                            <?php $id = isset($row['location_id']) ? (int) $row['location_id'] : 0; ?>
                            <tr>
                                <td class="py-2 align-middle text-center fs-8 white-space-nowrap">
                                    <span class="badge fs-10 badge-phoenix badge-phoenix-secondary">#<?php echo esc_html($id); ?></span>
                                </td>
                                <td><span class="fw-medium"><?php echo esc_html($row['street_address'] ?? ''); ?></span></td>
                                <td><span class="fw-medium"><?php echo esc_html($row['suburb'] ?? ''); ?></span></td>
                                <td><span class="fw-medium"><?php echo esc_html($row['town'] ?? ''); ?></span></td>
                                <td><span class="fw-medium"><?php echo esc_html($row['province'] ?? ''); ?></span></td>
                                <td><span class="fw-medium"><?php echo esc_html($row['postal_code'] ?? ''); ?></span></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2" role="group">
                                        <?php if ($id > 0) : ?>
                                            <a href="<?php echo esc_url(add_query_arg(array('mode' => 'update', 'location_id' => $id), $editUrl)); ?>" class="btn btn-sm btn-outline-secondary border-0" title="<?php esc_attr_e('Edit Location', 'wecoza-clients'); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                // Pagination
                $page = isset($page) ? (int) $page : 1;
                $totalPages = isset($totalPages) ? (int) $totalPages : 1;
                if ($totalPages > 1) :
                ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1) : ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($currentArgs, $paginationArgs, array('location_page' => $page - 1)), $baseUrl)); ?>" aria-label="<?php esc_attr_e('Previous', 'wecoza-clients'); ?>">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            if ($start > 1) : ?>
                                <li class="page-item"><a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($currentArgs, $paginationArgs, array('location_page' => 1)), $baseUrl)); ?>">1</a></li>
                                <?php if ($start > 2) : ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $start; $i <= $end; $i++) : ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($currentArgs, $paginationArgs, array('location_page' => $i)), $baseUrl)); ?>"><?php echo (int) $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($end < $totalPages) : ?>
                                <?php if ($end < $totalPages - 1) : ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($currentArgs, $paginationArgs, array('location_page' => $totalPages)), $baseUrl)); ?>"><?php echo (int) $totalPages; ?></a></li>
                            <?php endif; ?>
                            <?php if ($page < $totalPages) : ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($currentArgs, $paginationArgs, array('location_page' => $page + 1)), $baseUrl)); ?>" aria-label="<?php esc_attr_e('Next', 'wecoza-clients'); ?>">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <?php echo ViewHelpers::renderAlert(__('No locations found.', 'wecoza-clients'), 'warning', false); ?>
            <?php endif; ?>
        </div>
    </div>
</div>
