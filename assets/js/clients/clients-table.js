/**
 * Clients Table JavaScript
 * Handles sorting, search, and table interactions for the clients display
 */
(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        initClientTable();
    });

    function initClientTable() {
        const $table = $('#clients-table');
        const $searchForm = $('#clients-search-form');
        
        if (!$table.length) return;

        // Initialize sortable columns
        initSortableColumns($table);
        
        // Initialize search form enhancements
        initSearchForm($searchForm);
        
        // Initialize row hover effects
        initRowInteractions($table);
    }

    function initSortableColumns($table) {
        $table.find('th[data-sortable="true"]').each(function() {
            const $th = $(this);
            const $indicator = $th.find('.sort-indicator');
            
            // Add cursor pointer and click handler
            $th.css('cursor', 'pointer').on('click', function() {
                handleSort($th, $indicator);
            });
        });
    }

    function handleSort($th, $indicator) {
        const sortKey = $th.data('sort-key');
        const sortType = $th.data('sort-type');
        const currentUrl = new URL(window.location);
        const currentSort = currentUrl.searchParams.get('order_by') || 'client_name';
        const currentDir = currentUrl.searchParams.get('order_dir') || 'asc';
        
        // Determine new sort direction
        let newDir = 'asc';
        if (currentSort === sortKey && currentDir === 'asc') {
            newDir = 'desc';
        }
        
        // Update URL
        currentUrl.searchParams.set('order_by', sortKey);
        currentUrl.searchParams.set('order_dir', newDir);
        
        // Navigate to new URL
        window.location.href = currentUrl.toString();
    }

    function initSearchForm($searchForm) {
        if (!$searchForm.length) return;
        
        const $searchInput = $searchForm.find('#client_search');
        
        // Auto-submit on Enter with debouncing
        let searchTimeout;
        $searchInput.on('keyup', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $searchForm.submit();
            } else if (e.which !== 13) {
                // Clear any existing timeout
                clearTimeout(searchTimeout);
                
                // Set new timeout for auto-search (optional)
                searchTimeout = setTimeout(function() {
                    if ($searchInput.val().length >= 3 || $searchInput.val().length === 0) {
                        // Auto-search could be implemented here if desired
                        // $searchForm.submit();
                    }
                }, 500);
            }
        });
        
        // Clear search on escape key
        $searchInput.on('keydown', function(e) {
            if (e.which === 27) { // Escape key
                $searchInput.val('');
                $searchForm.submit();
            }
        });
    }

    function initRowInteractions($table) {
        // Add hover effects to rows
        $table.find('tbody tr').hover(
            function() {
                $(this).addClass('table-active');
            },
            function() {
                $(this).removeClass('table-active');
            }
        );
        
        // Add click to select functionality (optional)
        $table.find('tbody tr').on('click', function(e) {
            // Don't select row if clicking on links, buttons, or dropdown toggles
            if ($(e.target).closest('a, button, .dropdown').length) {
                return;
            }
            
            // Toggle selection
            $(this).toggleClass('table-selected');
        });
    }

    // Global functions for table actions
    window.viewClientDetails = function(clientId) {
        // Show modal with loading state
        const $modal = $('#clientDetailsModal');
        const $loading = $('#clientDetailsLoading');
        const $content = $('#clientDetailsContent');
        
        $loading.removeClass('d-none');
        $content.addClass('d-none');
        
        // Show modal
        const modal = new bootstrap.Modal($modal[0]);
        modal.show();
        
        // Fetch client details via AJAX
        $.ajax({
            url: wecozaClients.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wecoza_get_client_details',
                client_id: clientId,
                nonce: wecozaClients.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateClientModal(response.data);
                    $loading.addClass('d-none');
                    $content.removeClass('d-none');
                    
                    // Setup update button
                    $('#updateClientBtn').off('click').on('click', function() {
                        window.location.href = response.data.edit_url;
                    });
                } else {
                    showError('Failed to load client details: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                showError('An error occurred while loading client details.');
            }
        });
    };

    function populateClientModal(client) {
        // Helper function to format date
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-ZA', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return dateString;
            }
        }

        // Helper function to create status badge
        function createStatusBadge(status) {
            if (!status) return 'N/A';
            
            const statusColors = {
                'active': 'badge-phoenix-success',
                'inactive': 'badge-phoenix-secondary', 
                'lead': 'badge-phoenix-warning',
                'lost': 'badge-phoenix-danger',
                'prospect': 'badge-phoenix-info'
            };
            
            const colorClass = statusColors[status.toLowerCase()] || 'badge-phoenix-primary';
            return `<span class="badge badge-phoenix fs-10 ${colorClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
        }

        // Basic Information
        $('#modalClientName').text(client.client_name || 'N/A');
        $('#modalSiteName').text(client.site_name || 'N/A');
        $('#modalCompanyReg').text(client.company_registration_nr || 'N/A');
        $('#modalMainClient').text(client.main_client_name || (client.main_client_id ? `ID: ${client.main_client_id}` : 'N/A'));
        
        // Location Information
        $('#modalProvince').text(client.client_province || 'N/A');
        $('#modalTown').text(client.client_town || 'N/A');
        $('#modalSuburb').text(client.client_suburb || 'N/A');
        $('#modalStreetAddress').text(client.client_street_address || 'N/A');
        $('#modalPostalCode').text(client.client_postal_code || 'N/A');
        
        // Contact Information
        $('#modalContactPerson').text(client.contact_person || 'N/A');
        $('#modalContactPosition').text(client.contact_person_position || 'N/A');
        if (client.contact_person_email) {
            $('#modalContactEmail').html(`<a href="mailto:${client.contact_person_email}" class="text-primary text-decoration-underline">${client.contact_person_email}</a>`);
        } else {
            $('#modalContactEmail').text('N/A');
        }
        $('#modalContactCellphone').text(client.contact_person_cellphone || 'N/A');
        $('#modalContactTel').text(client.contact_person_tel || 'N/A');
        
        // Additional Details
        $('#modalSETA').text(client.seta || 'N/A');
        $('#modalClientStatus').html(createStatusBadge(client.client_status));
        $('#modalFinancialYearEnd').text(client.financial_year_end || 'N/A');
        $('#modalBBBEEVerificationDate').text(client.bbbee_verification_date || 'N/A');
        $('#modalCreatedDate').text(formatDate(client.created_at));
        $('#modalUpdatedDate').text(formatDate(client.updated_at));
    }

    function showError(message) {
        const $modal = $('#clientDetailsModal');
        const $loading = $('#clientDetailsLoading');
        
        $loading.html(`
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `);
    }

    window.refreshClients = function() {
        window.location.reload();
    };

    window.exportClients = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = wecozaClients.ajaxUrl; // WordPress AJAX URL
        
        const nonce = document.createElement('input');
        nonce.type = 'hidden';
        nonce.name = 'nonce';
        nonce.value = wecozaClients.nonce;
        
        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'wecoza_export_clients';
        
        form.appendChild(nonce);
        form.appendChild(action);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    };

    window.deleteClient = function(clientId, clientName) {
        if (confirm('Are you sure you want to delete "' + clientName + '"? This action cannot be undone.')) {
            $.ajax({
                url: wecozaClients.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wecoza_delete_client',
                    id: clientId,
                    nonce: wecozaClients.nonce
                },
                beforeSend: function() {
                    // Show loading state
                    $(`tr[data-client-id="${clientId}"]`).addClass('opacity-50');
                },
                success: function(response) {
                    if (response.success) {
                        // Animate row removal
                        const $row = $(`tr[data-client-id="${clientId}"]`);
                        $row.fadeOut(400, function() {
                            $(this).remove();
                            
                            // Check if table is empty and reload if needed
                            if ($('#clients-table tbody tr').length === 0) {
                                window.location.reload();
                            }
                        });
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to delete client'));
                        // Restore row opacity
                        $(`tr[data-client-id="${clientId}"]`).removeClass('opacity-50');
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the client.');
                    // Restore row opacity
                    $(`tr[data-client-id="${clientId}"]`).removeClass('opacity-50');
                }
            });
        }
    };

})(jQuery);
