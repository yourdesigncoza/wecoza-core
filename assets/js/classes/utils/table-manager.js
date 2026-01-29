/**
 * TableManager - Reusable table search, filter, and pagination utility
 *
 * A configurable class that provides consistent table management across the plugin.
 * Supports debounced search, multi-column filtering, sorting, and Bootstrap pagination.
 *
 * @package WeCozaClasses
 * @since 1.0.0
 *
 * Usage:
 * ```javascript
 * const manager = new WeCozaTableManager({
 *     tableId: '#my-table',
 *     searchInputId: '#my-search',
 *     searchColumns: [0, 1, 2],
 *     itemsPerPage: 20,
 *     onRender: (visibleRows, totalRows) => { console.log(`${visibleRows}/${totalRows}`); }
 * });
 * ```
 */

(function(global) {
    'use strict';

    /**
     * Default configuration options
     */
    const DEFAULT_CONFIG = {
        // Selectors
        tableId: null,                    // Required: Table element ID (e.g., '#my-table')
        searchInputId: null,              // Optional: Search input ID
        paginationContainerId: null,      // Optional: Pagination container ID (auto-generated if null)
        statusIndicatorId: null,          // Optional: Status indicator ID (auto-generated if null)

        // Search configuration
        searchColumns: [],                // Array of column indices to search (empty = all columns)
        debounceDelay: 300,               // Milliseconds before search executes
        minSearchLength: 0,               // Minimum chars to trigger search

        // Pagination configuration
        itemsPerPage: 20,                 // Items per page
        maxPageButtons: 5,                // Maximum page number buttons to show

        // Display options
        showPagination: true,             // Show pagination controls
        showStatusIndicator: true,        // Show search status badge
        showInfoDisplay: true,            // Show "X to Y of Z" info

        // CSS classes (Bootstrap 5 defaults)
        paginationClass: 'pagination pagination-sm',
        pageItemClass: 'page-item',
        pageLinkClass: 'page-link',
        activeClass: 'active',
        disabledClass: 'disabled',
        statusBadgeClass: 'badge badge-phoenix badge-phoenix-primary mb-2',

        // Callbacks
        onSearch: null,                   // function(searchTerm) - Called when search executes
        onFilter: null,                   // function(filteredRows) - Called after filtering
        onPageChange: null,               // function(pageNumber) - Called on page change
        onRender: null,                   // function(visibleCount, totalCount) - Called after render

        // Text templates
        statusTemplate: 'Showing {visible} of {total} items matching "{term}"',
        noResultsText: 'No items found matching "{term}"',
        infoTemplate: '{start} to {end} <span class="text-body-tertiary">Items of</span> {total}'
    };

    /**
     * WeCozaTableManager class
     */
    class WeCozaTableManager {
        /**
         * @param {Object} config Configuration options
         */
        constructor(config) {
            this.config = { ...DEFAULT_CONFIG, ...config };
            this.validateConfig();

            // State
            this.currentPage = 1;
            this.totalPages = 1;
            this.searchTerm = '';
            this.allRows = [];
            this.filteredRows = [];
            this.searchTimeout = null;
            this.isInitialized = false;

            // DOM elements (initialized in init())
            this.$table = null;
            this.$tbody = null;
            this.$searchInput = null;
            this.$paginationContainer = null;
            this.$statusIndicator = null;

            // Auto-initialize if tableId exists in DOM
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        /**
         * Validate configuration
         */
        validateConfig() {
            if (!this.config.tableId) {
                throw new Error('WeCozaTableManager: tableId is required');
            }
        }

        /**
         * Initialize the table manager
         */
        init() {
            if (this.isInitialized) {
                console.warn('WeCozaTableManager: Already initialized');
                return;
            }

            // Get table element
            this.$table = document.querySelector(this.config.tableId);
            if (!this.$table) {
                console.warn(`WeCozaTableManager: Table not found: ${this.config.tableId}`);
                return;
            }

            this.$tbody = this.$table.querySelector('tbody');
            if (!this.$tbody) {
                console.warn('WeCozaTableManager: Table tbody not found');
                return;
            }

            // Cache all rows
            this.allRows = Array.from(this.$tbody.querySelectorAll('tr'));
            this.filteredRows = [...this.allRows];

            // Setup search
            if (this.config.searchInputId) {
                this.setupSearch();
            }

            // Setup pagination
            if (this.config.showPagination) {
                this.setupPagination();
            }

            // Setup status indicator
            if (this.config.showStatusIndicator) {
                this.setupStatusIndicator();
            }

            // Initial render
            this.render();

            this.isInitialized = true;
            console.log(`WeCozaTableManager: Initialized for ${this.config.tableId}`);
        }

        /**
         * Setup search input event handlers
         */
        setupSearch() {
            this.$searchInput = document.querySelector(this.config.searchInputId);
            if (!this.$searchInput) {
                console.warn(`WeCozaTableManager: Search input not found: ${this.config.searchInputId}`);
                return;
            }

            this.$searchInput.addEventListener('input', (e) => {
                this.debouncedSearch(e.target.value);
            });

            // Clear search on form reset
            const form = this.$searchInput.closest('form');
            if (form) {
                form.addEventListener('reset', () => {
                    setTimeout(() => this.search(''), 10);
                });
            }
        }

        /**
         * Debounced search execution
         * @param {string} term Search term
         */
        debouncedSearch(term) {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.search(term);
            }, this.config.debounceDelay);
        }

        /**
         * Perform search
         * @param {string} term Search term
         */
        search(term) {
            this.searchTerm = term.toLowerCase().trim();

            // Callback
            if (typeof this.config.onSearch === 'function') {
                this.config.onSearch(term);
            }

            // Filter rows
            this.filter();

            // Reset to page 1
            this.currentPage = 1;

            // Update display
            this.render();
            this.updateStatusIndicator();
        }

        /**
         * Filter rows based on search term
         */
        filter() {
            if (!this.searchTerm || this.searchTerm.length < this.config.minSearchLength) {
                this.filteredRows = [...this.allRows];
            } else {
                this.filteredRows = this.allRows.filter(row => this.rowMatches(row));
            }

            // Callback
            if (typeof this.config.onFilter === 'function') {
                this.config.onFilter(this.filteredRows);
            }
        }

        /**
         * Check if a row matches the search term
         * @param {HTMLElement} row Table row
         * @returns {boolean}
         */
        rowMatches(row) {
            const cells = row.querySelectorAll('td');
            const columnsToSearch = this.config.searchColumns.length > 0
                ? this.config.searchColumns
                : Array.from({ length: cells.length }, (_, i) => i);

            return columnsToSearch.some(colIndex => {
                const cell = cells[colIndex];
                if (!cell) return false;
                const text = cell.textContent.toLowerCase().trim();
                return text.includes(this.searchTerm);
            });
        }

        /**
         * Setup pagination container
         */
        setupPagination() {
            if (this.config.paginationContainerId) {
                this.$paginationContainer = document.querySelector(this.config.paginationContainerId);
            }

            if (!this.$paginationContainer) {
                // Create pagination container
                this.$paginationContainer = document.createElement('div');
                this.$paginationContainer.id = `${this.config.tableId.replace('#', '')}-pagination`;
                this.$paginationContainer.className = 'd-flex justify-content-between align-items-center mt-3';

                // Insert after table's parent (usually .table-responsive)
                const tableParent = this.$table.closest('.table-responsive') || this.$table.parentElement;
                tableParent.after(this.$paginationContainer);
            }
        }

        /**
         * Setup status indicator
         */
        setupStatusIndicator() {
            if (this.config.statusIndicatorId) {
                this.$statusIndicator = document.querySelector(this.config.statusIndicatorId);
            }

            if (!this.$statusIndicator) {
                // Create status indicator
                this.$statusIndicator = document.createElement('span');
                this.$statusIndicator.id = `${this.config.tableId.replace('#', '')}-status`;
                this.$statusIndicator.className = this.config.statusBadgeClass;
                this.$statusIndicator.style.display = 'none';

                // Insert before table
                this.$table.before(this.$statusIndicator);
            }
        }

        /**
         * Render the current page
         */
        render() {
            // Calculate pagination
            this.totalPages = Math.ceil(this.filteredRows.length / this.config.itemsPerPage) || 1;
            this.currentPage = Math.min(Math.max(1, this.currentPage), this.totalPages);

            // Calculate range
            const startIndex = (this.currentPage - 1) * this.config.itemsPerPage;
            const endIndex = startIndex + this.config.itemsPerPage;

            // Hide all rows
            this.allRows.forEach(row => row.style.display = 'none');

            // Show filtered rows for current page
            this.filteredRows.slice(startIndex, endIndex).forEach(row => {
                row.style.display = '';
            });

            // Update pagination controls
            if (this.config.showPagination) {
                this.renderPagination();
            }

            // Callback
            if (typeof this.config.onRender === 'function') {
                this.config.onRender(this.filteredRows.length, this.allRows.length);
            }
        }

        /**
         * Render pagination controls
         */
        renderPagination() {
            if (!this.$paginationContainer) return;

            const total = this.filteredRows.length;
            const start = total === 0 ? 0 : (this.currentPage - 1) * this.config.itemsPerPage + 1;
            const end = Math.min(this.currentPage * this.config.itemsPerPage, total);

            let html = '';

            // Info display
            if (this.config.showInfoDisplay) {
                const infoText = this.config.infoTemplate
                    .replace('{start}', start)
                    .replace('{end}', end)
                    .replace('{total}', total);
                html += `<span class="d-none d-sm-inline-block">${infoText}</span>`;
            }

            // Navigation
            html += '<nav aria-label="Table pagination">';
            html += `<ul class="${this.config.paginationClass}">`;

            // Previous button
            const prevDisabled = this.currentPage <= 1;
            html += `<li class="${this.config.pageItemClass} ${prevDisabled ? this.config.disabledClass : ''}">`;
            if (prevDisabled) {
                html += `<span class="${this.config.pageLinkClass}" aria-hidden="true">&laquo;</span>`;
            } else {
                html += `<a class="${this.config.pageLinkClass}" href="#" data-page="prev" aria-label="Previous">&laquo;</a>`;
            }
            html += '</li>';

            // Page numbers
            const pageNumbers = this.getPageNumbers();
            pageNumbers.forEach(page => {
                if (page === '...') {
                    html += `<li class="${this.config.pageItemClass} ${this.config.disabledClass}">`;
                    html += `<span class="${this.config.pageLinkClass}">...</span></li>`;
                } else if (page === this.currentPage) {
                    html += `<li class="${this.config.pageItemClass} ${this.config.activeClass}" aria-current="page">`;
                    html += `<span class="${this.config.pageLinkClass}">${page}</span></li>`;
                } else {
                    html += `<li class="${this.config.pageItemClass}">`;
                    html += `<a class="${this.config.pageLinkClass}" href="#" data-page="${page}">${page}</a></li>`;
                }
            });

            // Next button
            const nextDisabled = this.currentPage >= this.totalPages;
            html += `<li class="${this.config.pageItemClass} ${nextDisabled ? this.config.disabledClass : ''}">`;
            if (nextDisabled) {
                html += `<span class="${this.config.pageLinkClass}" aria-hidden="true">&raquo;</span>`;
            } else {
                html += `<a class="${this.config.pageLinkClass}" href="#" data-page="next" aria-label="Next">&raquo;</a>`;
            }
            html += '</li>';

            html += '</ul></nav>';

            this.$paginationContainer.innerHTML = html;
            this.bindPaginationEvents();
        }

        /**
         * Get array of page numbers to display
         * @returns {Array}
         */
        getPageNumbers() {
            const pages = [];
            const max = this.config.maxPageButtons;
            const total = this.totalPages;
            const current = this.currentPage;

            if (total <= max) {
                // Show all pages
                for (let i = 1; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                // Show truncated pagination
                const half = Math.floor(max / 2);
                let start = Math.max(1, current - half);
                let end = Math.min(total, start + max - 1);

                if (end - start < max - 1) {
                    start = Math.max(1, end - max + 1);
                }

                if (start > 1) {
                    pages.push(1);
                    if (start > 2) pages.push('...');
                }

                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }

                if (end < total) {
                    if (end < total - 1) pages.push('...');
                    pages.push(total);
                }
            }

            return pages;
        }

        /**
         * Bind pagination click events
         */
        bindPaginationEvents() {
            if (!this.$paginationContainer) return;

            this.$paginationContainer.querySelectorAll('[data-page]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = e.target.dataset.page;

                    if (page === 'prev') {
                        this.goToPage(this.currentPage - 1);
                    } else if (page === 'next') {
                        this.goToPage(this.currentPage + 1);
                    } else {
                        this.goToPage(parseInt(page, 10));
                    }
                });
            });
        }

        /**
         * Navigate to specific page
         * @param {number} page Page number
         */
        goToPage(page) {
            if (page < 1 || page > this.totalPages || page === this.currentPage) return;

            this.currentPage = page;
            this.render();

            // Callback
            if (typeof this.config.onPageChange === 'function') {
                this.config.onPageChange(page);
            }
        }

        /**
         * Update status indicator
         */
        updateStatusIndicator() {
            if (!this.$statusIndicator) return;

            if (!this.searchTerm) {
                this.$statusIndicator.style.display = 'none';
                return;
            }

            let text;
            if (this.filteredRows.length === 0) {
                text = this.config.noResultsText.replace('{term}', this.searchTerm);
            } else {
                text = this.config.statusTemplate
                    .replace('{visible}', this.filteredRows.length)
                    .replace('{total}', this.allRows.length)
                    .replace('{term}', this.searchTerm);
            }

            this.$statusIndicator.textContent = text;
            this.$statusIndicator.style.display = '';
        }

        /**
         * Reset search and pagination
         */
        reset() {
            this.searchTerm = '';
            if (this.$searchInput) {
                this.$searchInput.value = '';
            }
            this.filteredRows = [...this.allRows];
            this.currentPage = 1;
            this.render();
            this.updateStatusIndicator();
        }

        /**
         * Refresh rows from DOM (useful after dynamic updates)
         */
        refresh() {
            this.allRows = Array.from(this.$tbody.querySelectorAll('tr'));
            this.filter();
            this.render();
        }

        /**
         * Get current statistics
         * @returns {Object}
         */
        getStats() {
            return {
                totalRows: this.allRows.length,
                filteredRows: this.filteredRows.length,
                currentPage: this.currentPage,
                totalPages: this.totalPages,
                itemsPerPage: this.config.itemsPerPage,
                searchTerm: this.searchTerm,
                isSearchActive: this.searchTerm.length > 0
            };
        }

        /**
         * Destroy the manager instance
         */
        destroy() {
            // Remove event listeners
            if (this.$searchInput) {
                this.$searchInput.removeEventListener('input', this.debouncedSearch);
            }

            // Remove generated elements
            if (this.$paginationContainer && !this.config.paginationContainerId) {
                this.$paginationContainer.remove();
            }
            if (this.$statusIndicator && !this.config.statusIndicatorId) {
                this.$statusIndicator.remove();
            }

            // Show all rows
            this.allRows.forEach(row => row.style.display = '');

            this.isInitialized = false;
        }
    }

    // Export to global scope
    global.WeCozaTableManager = WeCozaTableManager;

})(window);
