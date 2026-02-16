<?php

use WeCoza\Clients\Helpers\ViewHelpers;

$baseUrl = get_permalink();
if (!$baseUrl) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = $requestUri ? strtok($requestUri, '?') : '/';
    $baseUrl = home_url($path);
}

$stats = wp_parse_args(
    is_array($stats ?? null) ? $stats : array(),
    array(
        'total_clients' => 0,
        'active_clients' => 0,
        'leads' => 0,
        'cold_calls' => 0,
        'lost_clients' => 0,
    )
);

$currentArgs = array();
if (!empty($_GET) && is_array($_GET)) {
    foreach ($_GET as $key => $value) {
        if (in_array($key, array('client_search', 'client_status', 'client_seta', 'client_page'), true)) {
            continue;
        }
        $currentArgs[$key] = sanitize_text_field(is_array($value) ? implode(',', $value) : $value);
    }
}

$paginationArgs = array_filter(
    array(
        'client_search' => $search,
        'client_status' => $status,
        'client_seta' => $seta,
    ),
    function ($value) {
        return $value !== '' && $value !== null;
    }
);

$statusBadgeMap = array(
    'Active Client' => 'badge-phoenix-primary',
    'Lead' => 'badge-phoenix-warning',
    'Cold Call' => 'badge-phoenix-info',
    'Lost Client' => 'badge-phoenix-danger',
);

// Build edit URL - using update clients shortcode with edit mode
// This should point to a WordPress page containing [wecoza_update_clients] shortcode
$editUrl = site_url($atts['edit_url'] ?? '/app/all-clients', is_ssl() ? 'https' : 'http');
?>

<div class="card shadow-none border my-3" data-component-card="data-component-card">
    <div class="card-header p-3 border-bottom">
        <div class="row g-3 justify-content-between align-items-center mb-3">
            <div class="col-12 col-md">
                <h4 class="text-body mb-0" data-anchor="data-anchor" id="clients-table-header">
                    Clients Management
                    <i class="bi bi-people ms-2"></i>
                </h4>
            </div>
            
            <?php if (!empty($atts['show_search'])): ?>
            <div class="search-box col-auto">
                <form class="position-relative" method="GET" id="clients-search-form">
                    <?php foreach ($currentArgs as $key => $value): ?>
                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                    <?php endforeach; ?>
                    <input class="form-control search-input search form-control-sm" type="search" name="client_search" id="client_search" value="<?php echo esc_attr($search); ?>" placeholder="Search clients... (Press Enter)" aria-label="Search" title="Type your search query and press Enter to search">
                    <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="col-auto">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshClients()">
                        Refresh
                        <i class="bi bi-arrow-clockwise ms-1"></i>
                    </button>
                    <?php if (!empty($atts['show_export'])): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportClients()">
                        Export
                        <i class="bi bi-download ms-1"></i>
                    </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(site_url($atts['add_url'] ?? '/app/all-clients', is_ssl() ? 'https' : 'http')); ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>
                        Add New Client
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Summary strip -->
        <div class="col-12">
            <div class="scrollbar">
                <div class="row g-0 flex-nowrap">
                    <div class="col-auto border-end pe-4">
                        <h6 class="text-body-tertiary">Total Clients: <?php echo (int) $stats['total_clients']; ?></h6>
                    </div>
                    <div class="col-auto px-4 border-end">
                        <h6 class="text-body-tertiary">Active: <?php echo (int) $stats['active_clients']; ?></h6>
                    </div>
                    <div class="col-auto px-4 border-end">
                        <h6 class="text-body-tertiary">Leads: <?php echo (int) $stats['leads']; ?></h6>
                    </div>
                    <div class="col-auto px-4 border-end">
                        <h6 class="text-body-tertiary">Cold Calls: <?php echo (int) $stats['cold_calls']; ?></h6>
                    </div>
                    <div class="col-auto px-4">
                        <h6 class="text-body-tertiary">Lost: <?php echo (int) $stats['lost_clients']; ?></h6>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($atts['show_filters'])): ?>
        <!-- Filters -->
        <div class="row g-3 mt-2">
            <div class="col-12 col-md-4">
                <form method="GET" id="clients-filter-form">
                    <?php foreach ($currentArgs as $key => $value): ?>
                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                    <?php endforeach; ?>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Status</span>
                        <select class="form-select" name="client_status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach ($status_options as $option): ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected($status, $option); ?>><?php echo esc_html($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="col-12 col-md-4">
                <form method="GET" id="clients-seta-filter-form">
                    <?php foreach ($currentArgs as $key => $value): ?>
                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                    <?php endforeach; ?>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">SETA</span>
                        <select class="form-select" name="client_seta" onchange="this.form.submit()">
                            <option value="">All SETAs</option>
                            <?php foreach ($seta_options as $option): ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected($seta, $option); ?>><?php echo esc_html($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card-body p-4 py-2">
        <div class="table-responsive">
            <?php if (!empty($search)): ?>
                <span id="clients-search-status" class="badge badge-phoenix badge-phoenix-primary mb-2">
                    Searching for: <?php echo esc_html($search); ?>
                </span>
            <?php endif; ?>
            
            <table id="clients-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                <thead class="border-bottom">
                    <tr>
                        <th scope="col" class="border-0 ps-4" data-sortable="true" data-sort-key="id" data-sort-type="numeric">
                            ID
                            <i class="bi bi-hash ms-1"></i>
                            <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
                        </th>
                        <th scope="col" class="border-0" data-sortable="true" data-sort-key="client_name" data-sort-type="text">
                            Client Name
                            <i class="bi bi-person-badge ms-1"></i>
                            <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
                        </th>
                        <th scope="col" class="border-0" data-sortable="true" data-sort-key="main_client_id" data-sort-type="numeric">
                            Branch
                            <i class="bi bi-diagram-2 ms-1"></i>
                            <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
                        </th>
                        <th scope="col" class="border-0" data-sortable="true" data-sort-key="company_registration_nr" data-sort-type="text">
                            Company Reg
                            <i class="bi bi-building ms-1"></i>
                            <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
                        </th>
                        <th scope="col" class="border-0" data-sortable="true" data-sort-key="seta" data-sort-type="text">
                            SETA
                            <i class="bi bi-mortarboard ms-1"></i>
                            <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
                        </th>
                        <th scope="col" class="border-0" data-sortable="true" data-sort-key="client_status" data-sort-type="text">
                            Status
                            <i class="bi bi-shield-check ms-1"></i>
                            <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
                        </th>
                        <th scope="col" class="border-0" data-sortable="true" data-sort-key="created_at" data-sort-type="date">
                            Created
                            <i class="bi bi-calendar-date ms-1"></i>
                            <span class="sort-indicator d-none"><i class="bi bi-chevron-up"></i></span>
                        </th>
                        <th scope="col" class="border-0 pe-4" data-sortable="false">
                            Actions
                            <i class="bi bi-gear ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clients)): ?>
                        <?php foreach ($clients as $client): ?>
                            <?php 
                            $statusClass = $statusBadgeMap[$client['client_status']] ?? 'badge-phoenix-info';
                            $createdDate = !empty($client['created_at']) ? wp_date('M j, Y', strtotime($client['created_at'])) : '';
                            $editLink = add_query_arg(['mode' => 'update', 'client_id' => $client['id']], $editUrl);
                            ?>
                            <tr data-client-id="<?php echo (int) $client['id']; ?>" data-client-name="<?php echo esc_attr($client['client_name']); ?>">
                                <td class="py-2 align-middle text-center fs-8 white-space-nowrap">
                                    <span class="badge fs-10 badge-phoenix badge-phoenix-secondary">
                                        #<?php echo (int) $client['id']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-medium">
                                        <?php echo esc_html($client['client_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($client['main_client_id'])): ?>
                                        <span class="badge badge-phoenix badge-phoenix-secondary">
                                            <?php 
                                            $mainName = !empty($client['main_client_name']) ? esc_html($client['main_client_name']) : 'Unknown';
                                            echo $mainName . ' #' . (int) $client['main_client_id']; 
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <small class="text-muted">&nbsp;</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                        <?php echo esc_html($client['company_registration_nr'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($client['seta'])): ?>
                                        <span class="badge badge-phoenix badge-phoenix-secondary">
                                            <?php echo esc_html($client['seta']); ?>
                                        </span>
                                    <?php else: ?>
                                        <small class="text-muted">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-phoenix  <?php echo esc_attr($statusClass); ?>">
                                        <?php echo esc_html($client['client_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($createdDate): ?>
                                        <span class="text-nowrap" title="<?php echo esc_attr($client['created_at']); ?>">
                                            <?php echo esc_html($createdDate); ?>
                                        </span>
                                    <?php else: ?>
                                        <small class="text-muted">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" title="View Details" onclick="viewClientDetails(<?php echo (int) $client['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="<?php echo esc_url($editLink); ?>" class="btn btn-sm btn-outline-secondary border-0" title="Edit Client">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary border-0" title="Delete Client" onclick="deleteClient(<?php echo (int) $client['id']; ?>, '<?php echo esc_js($client['client_name']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                    <?php if (!empty($search)): ?>
                                        No clients found matching "<?php echo esc_html($search); ?>"
                                    <?php else: ?>
                                        No clients found
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-body-tertiary py-2" id="clients-pagination-container">
        <div class="d-flex justify-content-between align-items-center">
            <div class="pagination-info">
                <small class="text-muted">
                    Showing <?php echo (int) ((($page - 1) * $atts['per_page']) + 1); ?> to <?php echo (int) min($page * $atts['per_page'], $total); ?> 
                    of <?php echo (int) $total; ?> clients
                </small>
            </div>
            <nav aria-label="Clients pagination">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $prevPage = $page > 1 ? $page - 1 : 1;
                    $nextPage = $page < $totalPages ? $page + 1 : $totalPages;
                    
                    $prevUrl = add_query_arg(array_merge($paginationArgs, array('client_page' => $prevPage)), $baseUrl);
                    $nextUrl = add_query_arg(array_merge($paginationArgs, array('client_page' => $nextPage)), $baseUrl);
                    ?>
                    
                    <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo esc_url($prevUrl); ?>" aria-label="Previous">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . esc_url(add_query_arg(array_merge($paginationArgs, array('client_page' => 1)), $baseUrl)) . '">1</a></li>';
                        if ($start > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $start; $i <= $end; $i++) {
                        $pageUrl = add_query_arg(array_merge($paginationArgs, array('client_page' => $i)), $baseUrl);
                        echo '<li class="page-item ' . ($i === $page ? 'active' : '') . '">';
                        if ($i === $page) {
                            echo '<span class="page-link">' . $i . '</span>';
                        } else {
                            echo '<a class="page-link" href="' . esc_url($pageUrl) . '">' . $i . '</a>';
                        }
                        echo '</li>';
                    }
                    
                    if ($end < $totalPages) {
                        if ($end < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="' . esc_url(add_query_arg(array_merge($paginationArgs, array('client_page' => $totalPages)), $baseUrl)) . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo esc_url($nextUrl); ?>" aria-label="Next">
                            <span aria-hidden="true">»</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function refreshClients() {
    window.location.reload();
}

function exportClients() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
    
    const nonce = document.createElement('input');
    nonce.type = 'hidden';
    nonce.name = 'nonce';
    nonce.value = '<?php echo wp_create_nonce('clients_nonce_action'); ?>';

    const action = document.createElement('input');
    action.type = 'hidden';
    action.name = 'action';
    action.value = 'wecoza_export_clients';
    
    form.appendChild(nonce);
    form.appendChild(action);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function deleteClient(clientId, clientName) {
    if (confirm('Are you sure you want to delete "' + clientName + '"? This action cannot be undone.')) {
        // Implement delete functionality via AJAX
        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'wecoza_delete_client',
                id: clientId,
                nonce: '<?php echo wp_create_nonce('clients_nonce_action'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to delete client'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the client.');
        });
    }
}
</script>

<!-- Client Details Modal -->
<div class="modal fade" id="clientDetailsModal" tabindex="-1" aria-labelledby="clientDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="clientDetailsModalLabel">
                    <i class="bi bi-person-badge me-2"></i>
                    Client Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="clientDetailsLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading client details...</p>
                </div>
                
                <div id="clientDetailsContent" class="d-none">
                    <div class="px-xl-4 mb-7">
                        <div class="row mx-0">
                            <!-- Left Column - Basic Information & Location -->
                            <div class="col-sm-12 col-xxl-6 border-bottom border-end-xxl py-3">
                                <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                                    <tbody>
                                        <!-- Client Name -->
                                        <tr>
                                            <td class="py-2 ydcoza-w-150">
                                                <div class="d-inline-flex align-items-center">
                                                    <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-building text-primary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Client Name :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalClientName"></p>
                                            </td>
                                        </tr>
                                        <!-- Site Name -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-geo-alt text-info" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Site Name :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalSiteName"></p>
                                            </td>
                                        </tr>
                                        <!-- Company Registration -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-hash text-warning" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Company Reg :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalCompanyReg"></p>
                                            </td>
                                        </tr>
                                        <!-- Main Client -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-layers text-success" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Main Client :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalMainClient"></p>
                                            </td>
                                        </tr>
                                        <!-- Province -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-pin-map text-primary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Province :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalProvince"></p>
                                            </td>
                                        </tr>
                                        <!-- Town -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-geo-alt text-info" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Town :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalTown"></p>
                                            </td>
                                        </tr>
                                        <!-- Suburb -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-secondary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-signpost-split text-secondary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Suburb :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalSuburb"></p>
                                            </td>
                                        </tr>
                                        <!-- Street Address -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-house-door text-success" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Street Address :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalStreetAddress"></p>
                                            </td>
                                        </tr>
                                        <!-- Postal Code -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-secondary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-mailbox text-secondary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Postal Code :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalPostalCode"></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Right Column - Contact & Status -->
                            <div class="col-sm-12 col-xxl-6 border-bottom py-3">
                                <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                                    <tbody>
                                        <!-- Contact Person -->
                                        <tr>
                                            <td class="py-2 ydcoza-w-150">
                                                <div class="d-inline-flex align-items-center">
                                                    <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-person text-primary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Contact Person :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalContactPerson"></p>
                                            </td>
                                        </tr>
                                        <!-- Position -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-secondary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-briefcase text-secondary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Position :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalContactPosition"></p>
                                            </td>
                                        </tr>
                                        <!-- Email -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-envelope text-info" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Email :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalContactEmail"></p>
                                            </td>
                                        </tr>
                                        <!-- Cellphone -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-phone text-success" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Cellphone :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalContactCellphone"></p>
                                            </td>
                                        </tr>
                                        <!-- Telephone -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-telephone text-warning" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Telephone :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalContactTel"></p>
                                            </td>
                                        </tr>
                                        <!-- SETA -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-award text-primary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">SETA :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalSETA"></p>
                                            </td>
                                        </tr>
                                        <!-- Client Status -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-check-circle text-success" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Client Status :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalClientStatus"></p>
                                            </td>
                                        </tr>
                                        <!-- Financial Year End -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-calendar-event text-warning" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Financial YE :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalFinancialYearEnd"></p>
                                            </td>
                                        </tr>
                                        <!-- BBBEE Verification Date -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-shield-check text-info" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">BBBEE Veri. :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalBBBEEVerificationDate"></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Bottom Left - Timestamps -->
                            <div class="col-sm-12 col-xxl-6 border-end-xxl py-3">
                                <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                                    <tbody>
                                        <!-- Created Date -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-calendar-plus text-success" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Created :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalCreatedDate"></p>
                                            </td>
                                        </tr>
                                        <!-- Updated Date -->
                                        <tr>
                                            <td class="py-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                        <i class="bi bi-calendar-check text-primary" style="font-size: 12px;"></i>
                                                    </div>
                                                    <p class="fw-bold mb-0">Last Updated :</p>
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <p class="fw-semibold mb-0" id="modalUpdatedDate"></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Bottom Right - Empty for balance -->
                            <div class="col-sm-12 col-xxl-6 py-3">
                                <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                                    <tbody>
                                        <tr>
                                            
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-subtle-warning" data-bs-dismiss="modal">Close</button>
                <button type="button" id="updateClientBtn" class="btn btn-subtle-info">
                    <i class="bi bi-pencil me-2"></i>
                    Update Client
                </button>
            </div>
        </div>
    </div>
</div>
