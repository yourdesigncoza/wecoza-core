jQuery(document).ready(function($) {
    const learnerTable = {
        allData: [],
        filteredData: [],
        currentPage: 1,
        itemsPerPage: 20,
        
        init: function() {
            this.bindEvents();
            this.fetchData();
        },

        bindEvents: function() {
            // Note: Delete learner functionality is now handled globally in learners-app.js
            
            // Search functionality
            $('#learners-search').on('keyup', this.handleSearch.bind(this));
            
            // Refresh button
            $('#refresh-learners').on('click', () => {
                this.fetchData();
            });
            
            // Export button
            $('#export-learners').on('click', this.handleExport.bind(this));
            
            // Pagination clicks
            $(document).on('click', '.page-link', this.handlePagination.bind(this));
        },

        fetchData: function() {
            $.ajax({
                url: wecozaAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fetch_learners_data',
                    nonce: wecozaAjax.nonce
                },
                beforeSend: this.showLoader,
                success: this.handleSuccess.bind(this),
                error: this.handleError,
                complete: this.hideLoader
            });
        },

        showLoader: function() {
            $('#learners-loading').removeClass('d-none');
            $('#learners-content').hide();
        },

        hideLoader: function() {
            $('#learners-loading').addClass('d-none');
        },

        handleSuccess: function(response) {
            if (response.success) {
                // Parse the HTML response to extract data
                this.parseAndStoreData(response.data);
                this.updateSummaryStats();
                this.displayData();
                $('#learners-content').fadeIn();
            } else {
                this.showAlert('error', 'Failed to load data: ' + response.data);
            }
        },

        parseAndStoreData: function(html) {
            // Create a temporary container to parse the HTML
            const tempDiv = $('<div>').html(html);
            const rows = tempDiv.find('tr');
            
            this.allData = [];
            rows.each((index, row) => {
                const cells = $(row).find('td');
                if (cells.length > 0) {
                    this.allData.push({
                        id: $(cells[8]).find('.delete-learner-btn').data('id') || index,
                        fullName: $(cells[0]).text().trim(),
                        surname: $(cells[1]).text().trim(),
                        gender: $(cells[2]).text().trim(),
                        race: $(cells[3]).text().trim(),
                        telNumber: $(cells[4]).text().trim(),
                        email: $(cells[5]).text().trim(),
                        cityTown: $(cells[6]).text().trim(),
                        employmentStatus: $(cells[7]).text().trim(),
                        actions: $(cells[8]).html()
                    });
                }
            });
            
            this.filteredData = [...this.allData];
        },

        updateSummaryStats: function() {
            const stats = this.calculateStats();
            
            let summaryHtml = `
                <div class="col-auto border-end pe-4">
                    <h6 class="text-body-tertiary">Total Learners : ${stats.total}</h6>
                </div>
                <div class="col-auto px-4 border-end">
                    <h6 class="text-body-tertiary">Male : ${stats.male} <div class="badge badge-phoenix fs-10 badge-phoenix-info">${this.formatChange(stats.malePercent)}%</div></h6>
                </div>
                <div class="col-auto px-4 border-end">
                    <h6 class="text-body-tertiary">Female : ${stats.female} <div class="badge badge-phoenix fs-10 badge-phoenix-warning">${this.formatChange(stats.femalePercent)}%</div></h6>
                </div>
                <div class="col-auto px-4 border-end">
                    <h6 class="text-body-tertiary">Employed : ${stats.employed} <div class="badge badge-phoenix fs-10 badge-phoenix-success">${this.formatChange(stats.employedPercent)}%</div></h6>
                </div>
                <div class="col-auto px-4">
                    <h6 class="text-body-tertiary">Unemployed : ${stats.unemployed} <div class="badge badge-phoenix fs-10 badge-phoenix-danger">${this.formatChange(stats.unemployedPercent)}%</div></h6>
                </div>
            `;
            
            $('#learners-summary').html(summaryHtml);
        },

        calculateStats: function() {
            const total = this.allData.length;
            const male = this.allData.filter(l => l.gender.toLowerCase() === 'male').length;
            const female = this.allData.filter(l => l.gender.toLowerCase() === 'female').length;
            const employed = this.allData.filter(l => l.employmentStatus.toLowerCase() === 'employed').length;
            const unemployed = this.allData.filter(l => l.employmentStatus.toLowerCase() === 'unemployed').length;
            
            return {
                total,
                male,
                female,
                employed,
                unemployed,
                malePercent: total > 0 ? ((male / total) * 100).toFixed(1) : 0,
                femalePercent: total > 0 ? ((female / total) * 100).toFixed(1) : 0,
                employedPercent: total > 0 ? ((employed / total) * 100).toFixed(1) : 0,
                unemployedPercent: total > 0 ? ((unemployed / total) * 100).toFixed(1) : 0
            };
        },

        formatChange: function(value) {
            return value;
        },

        displayData: function() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            const pageData = this.filteredData.slice(start, end);
            
            let tableHtml = '';
            pageData.forEach((learner, index) => {
                tableHtml += `
                    <tr>
                        <td class="py-2 align-middle text-center fs-8 white-space-nowrap">
                            <span class="badge fs-10 badge-phoenix badge-phoenix-secondary">
                                #${learner.id}
                            </span>
                        </td>
                        <td>${learner.fullName}</td>
                        <td>${learner.surname}</td>
                        <td>
                            <span class="badge badge-phoenix fs-10 ${learner.gender.toLowerCase() === 'male' ? 'badge-phoenix-info' : 'badge-phoenix-warning'}">
                                ${learner.gender}
                            </span>
                        </td>
                        <td>${learner.race}</td>
                        <td>${learner.telNumber}</td>
                        <td>${learner.email}</td>
                        <td>${learner.cityTown}</td>
                        <td>
                            <span class="badge badge-phoenix fs-10 ${learner.employmentStatus.toLowerCase() === 'employed' ? 'badge-phoenix-success' : 'badge-phoenix-danger'}">
                                ${learner.employmentStatus}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2" role="group">
                                <a href="${wecozaAjax.viewLearnerUrl}/?learner_id=${learner.id}" 
                                   class="btn btn-sm btn-outline-secondary border-0" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="${wecozaAjax.updateLearnerUrl}/?learner_id=${learner.id}" 
                                   class="btn btn-sm btn-outline-secondary border-0" title="Edit Learner">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary border-0 delete-learner-btn" 
                                        data-id="${learner.id}" title="Delete Learner">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            $('#learners-table-body').html(tableHtml);
            this.updatePagination();
        },

        updatePagination: function() {
            const totalPages = Math.ceil(this.filteredData.length / this.itemsPerPage);
            const start = ((this.currentPage - 1) * this.itemsPerPage) + 1;
            const end = Math.min(this.currentPage * this.itemsPerPage, this.filteredData.length);
            
            let paginationHtml = `
                <span class="d-none d-sm-inline-block" data-list-info="data-list-info">
                    ${start} to ${end} <span class="text-body-tertiary"> Items of </span>${this.filteredData.length}
                </span>
                <nav aria-label="Learners pagination">
                    <ul class="pagination pagination-sm">
            `;
            
            // Previous button
            paginationHtml += `
                <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${this.currentPage - 1}" aria-label="Previous">
                        <span aria-hidden="true">«</span>
                    </a>
                </li>
            `;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                    paginationHtml += `
                        <li class="page-item ${i === this.currentPage ? 'active' : ''}" ${i === this.currentPage ? 'aria-current="page"' : ''}>
                            ${i === this.currentPage ? 
                                `<span class="page-link">${i}</span>` : 
                                `<a class="page-link" href="#" data-page="${i}">${i}</a>`
                            }
                        </li>
                    `;
                } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                    paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Next button
            paginationHtml += `
                <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${this.currentPage + 1}" aria-label="Next">
                        <span aria-hidden="true">»</span>
                    </a>
                </li>
            `;
            
            paginationHtml += '</ul></nav>';
            $('#learners-pagination').html(paginationHtml);
        },

        handleSearch: function(e) {
            const searchTerm = $(e.target).val().toLowerCase();
            
            if (searchTerm === '') {
                this.filteredData = [...this.allData];
                $('#learners-search-status').hide();
            } else {
                this.filteredData = this.allData.filter(learner => {
                    return learner.fullName.toLowerCase().includes(searchTerm) ||
                           learner.surname.toLowerCase().includes(searchTerm) ||
                           learner.email.toLowerCase().includes(searchTerm) ||
                           learner.cityTown.toLowerCase().includes(searchTerm);
                });
                
                $('#learners-search-status')
                    .text(`Showing ${this.filteredData.length} results for "${searchTerm}"`)
                    .show();
            }
            
            this.currentPage = 1;
            this.displayData();
        },

        handlePagination: function(e) {
            e.preventDefault();
            const page = parseInt($(e.target).data('page'));
            
            if (!isNaN(page) && page > 0) {
                this.currentPage = page;
                this.displayData();
            }
        },

        handleExport: function() {
            // Simple CSV export
            let csv = 'Full Name,Surname,Gender,Race,Tel Number,Email,City/Town,Employment Status\n';
            
            this.filteredData.forEach(learner => {
                csv += `"${learner.fullName}","${learner.surname}","${learner.gender}","${learner.race}","${learner.telNumber}","${learner.email}","${learner.cityTown}","${learner.employmentStatus}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'learners_export.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.showAlert('success', 'Learners data exported successfully!');
        },



        handleError: function(xhr, status, error) {
            const errorMessage = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred while loading data.';
            learnerTable.showAlert('error', errorMessage);
        },

        showAlert: function(type, message) {
            const alertClass = type === 'success' ? 'alert-subtle-success' : 'alert-subtle-danger';
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#alert-container').html(alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $('#alert-container .alert').fadeOut();
            }, 5000);
        }
    };

    // Initialize the learner table
    learnerTable.init();
    
    // Expose learnerTable globally for universal delete handler
    window.learnerTable = learnerTable;
});