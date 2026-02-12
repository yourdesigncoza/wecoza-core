/**
 * Agents AJAX Pagination JavaScript
 *
 * Implements AJAX-based pagination for the agents display table.
 * Handles page navigation, per-page changes, and maintains search/sort state.
 *
 * @package WeCozaCore
 * @since 3.0.0
 */

(function($) {
    'use strict';

    /**
     * Debug flag - set to false in production
     */
    const DEBUG_MODE = false;

    /**
     * Pagination state
     */
    let currentState = {
        page: 1,
        per_page: 10,
        search: '',
        orderby: 'surname',
        order: 'ASC'
    };

    /**
     * Selectors
     */
    const SELECTORS = {
        container: '#agents-container',
        tableBody: '#agents-display-data tbody',
        pagination: '.fixed-table-pagination',
        loader: '#wecoza-agents-loader-container',
        searchInput: '.search-input',
        sortableHeaders: 'th[data-sortable="true"] a',
        pageLinks: '.page-link[data-page]',
        perPageLinks: '.dropdown-item[data-per-page]',
        statisticsContainer: '.scrollbar .row',
        paginationInfo: '.pagination-info'
    };

    /**
     * Initialize AJAX pagination
     */
    function init() {
        // Get initial state from URL/page
        updateStateFromPage();

        // Bind event handlers
        bindEventHandlers();
    }

    /**
     * Update state from current page parameters
     */
    function updateStateFromPage() {
        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);

        currentState.page = parseInt(urlParams.get('paged')) || 1;
        currentState.per_page = parseInt(urlParams.get('per_page')) || 10;
        currentState.search = urlParams.get('search') || '';
        currentState.orderby = urlParams.get('orderby') || 'surname';
        currentState.order = urlParams.get('order') || 'ASC';

        // Update search input if exists
        const $searchInput = $(SELECTORS.searchInput);
        if ($searchInput.length && currentState.search) {
            $searchInput.val(currentState.search);
        }
    }

    /**
     * Bind event handlers
     */
    function bindEventHandlers() {
        // Use event delegation for dynamic content
        $(document).on('click', SELECTORS.pageLinks, handlePageClick);
        $(document).on('click', SELECTORS.perPageLinks, handlePerPageClick);
        $(document).on('click', SELECTORS.sortableHeaders, handleSortClick);

        // Search handling (integrate with existing search functionality)
        $(document).on('search-completed', function(e, searchTerm) {
            currentState.search = searchTerm;
            currentState.page = 1; // Reset to first page on new search
            loadAgents();
        });
    }

    /**
     * Handle page link clicks
     */
    function handlePageClick(e) {
        e.preventDefault();

        const $link = $(this);
        if ($link.parent().hasClass('disabled')) {
            return;
        }

        const page = parseInt($link.data('page'));
        if (page && page !== currentState.page) {
            currentState.page = page;
            loadAgents();
        }
    }

    /**
     * Handle per page dropdown clicks
     */
    function handlePerPageClick(e) {
        e.preventDefault();

        const perPage = parseInt($(this).data('per-page'));
        if (perPage && perPage !== currentState.per_page) {
            currentState.per_page = perPage;
            currentState.page = 1; // Reset to first page
            loadAgents();
        }
    }

    /**
     * Handle sort column clicks
     */
    function handleSortClick(e) {
        e.preventDefault();

        const $link = $(this);
        const column = $link.data('column');

        if (column === currentState.orderby) {
            // Toggle order
            currentState.order = currentState.order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            // New column, default to ASC
            currentState.orderby = column;
            currentState.order = 'ASC';
        }

        currentState.page = 1; // Reset to first page
        loadAgents();
    }

    /**
     * Load agents via AJAX
     */
    function loadAgents() {
        // Show loader
        showLoader();

        // Prepare data
        const data = {
            action: 'wecoza_agents_paginate',
            nonce: wecozaAgents.nonce,
            page: currentState.page,
            per_page: currentState.per_page,
            search: currentState.search,
            orderby: currentState.orderby,
            order: currentState.order
        };

        // Make AJAX request
        $.ajax({
            url: wecozaAgents.ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: handleLoadSuccess,
            error: handleLoadError,
            complete: hideLoader
        });

        // Update URL without refresh
        updateURL();
    }

    /**
     * Handle successful AJAX response
     */
    function handleLoadSuccess(response) {
        if (response.success && response.data) {
            // Update table body
            $(SELECTORS.tableBody).html(response.data.table_html);

            // Update pagination
            $(SELECTORS.pagination).html(response.data.pagination_html);

            // Update statistics
            if (response.data.statistics_html) {
                $(SELECTORS.statisticsContainer).html(response.data.statistics_html);
            }

            // Trigger event for other scripts
            $(document).trigger('agents-loaded', [response.data]);

            // Scroll to top of table
            scrollToTable();
        } else {
            handleLoadError();
        }
    }

    /**
     * Handle AJAX error
     */
    function handleLoadError() {
        showErrorMessage(wecozaAgents.errorText);
    }

    /**
     * Show loader
     */
    function showLoader() {
        // Use Bootstrap 5 spinner in the table container
        const $tableBody = $(SELECTORS.tableBody);
        const $pagination = $(SELECTORS.pagination);

        // Store current content
        $tableBody.data('original-content', $tableBody.html());

        // Show spinner in table
        const spinnerHtml = `
            <tr>
                <td colspan="100%" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">${wecozaAgents.loadingText}</span>
                    </div>
                    <p class="mt-2 mb-0 text-muted">${wecozaAgents.loadingText}</p>
                </td>
            </tr>
        `;
        $tableBody.html(spinnerHtml);

        // Add opacity to pagination
        $pagination.css('opacity', '0.5');
    }

    /**
     * Hide loader
     */
    function hideLoader() {
        // Remove opacity from pagination
        const $pagination = $(SELECTORS.pagination);
        $pagination.css('opacity', '1');
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        const $alertContainer = $('#alert-container');
        if ($alertContainer.length) {
            const alert = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $alertContainer.html(alert);
        } else {
            alert(message);
        }
    }

    /**
     * Update URL without page refresh
     */
    function updateURL() {
        if (window.history && window.history.pushState) {
            const params = new URLSearchParams();

            if (currentState.page > 1) {
                params.set('paged', currentState.page);
            }
            if (currentState.per_page !== 10) {
                params.set('per_page', currentState.per_page);
            }
            if (currentState.search) {
                params.set('search', currentState.search);
            }
            if (currentState.orderby !== 'surname') {
                params.set('orderby', currentState.orderby);
            }
            if (currentState.order !== 'ASC') {
                params.set('order', currentState.order);
            }

            const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({state: currentState}, '', newURL);
        }
    }

    /**
     * Scroll to top of table
     */
    function scrollToTable() {
        const $container = $(SELECTORS.container);
        if ($container.length) {
            $('html, body').animate({
                scrollTop: $container.offset().top - 100
            }, 300);
        }
    }

    /**
     * Public API
     */
    window.WeCozaAgentsAjaxPagination = {
        init: init,
        reload: loadAgents,
        getCurrentState: function() { return currentState; },
        setSearch: function(search) {
            currentState.search = search;
            currentState.page = 1;
            loadAgents();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        init();
    });

})(jQuery);
