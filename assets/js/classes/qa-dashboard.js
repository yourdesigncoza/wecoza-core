/**
 * QA Dashboard JavaScript
 * Handles QA analytics dashboard and widget functionality
 */

(function($) {
    'use strict';

    // QA Analytics Dashboard Class
    window.QAAnalytics = function() {
        this.charts = {};
        this.currentData = null;
        this.refreshInterval = null;
    };

    QAAnalytics.prototype = {
        init: function() {
            this.loadDashboardData();
            this.setupEventListeners();
            this.setupAutoRefresh();
        },

        loadDashboardData: function() {
            const startDate = $('#date-range-start').val() || '';
            const endDate = $('#date-range-end').val() || '';
            const department = $('#department-filter').val() || '';

            $.ajax({
                url: qaAjax.url,
                type: 'POST',
                data: {
                    action: 'get_qa_analytics',
                    nonce: qaAjax.nonce,
                    start_date: startDate,
                    end_date: endDate,
                    department: department
                },
                success: (response) => {
                    if (response.success) {
                        this.currentData = response.data;
                        this.updateMetrics();
                        this.updateCharts();
                        this.updateRecentActivity();
                        this.updateAlerts();
                    } else {
                        console.error('Error loading dashboard data:', response.data);
                        this.showError('Failed to load dashboard data');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error loading dashboard data:', error);
                    this.showError('Network error loading dashboard data');
                }
            });
        },

        updateMetrics: function() {
            const data = this.currentData;
            if (!data || !data.overall_stats) return;

            const stats = data.overall_stats;
            $('#total-visits').text(stats.total_visits || '0');
            $('#classes-visited').text(stats.classes_visited || '0');
            $('#active-officers').text(stats.active_officers || '0');
            $('#overall-rating').text(stats.overall_rating || '0');
        },

        updateCharts: function() {
            if (!this.currentData) return;

            this.createMonthlyCompletionChart();
            this.createRatingsByDepartmentChart();
            this.createOfficerPerformanceChart();
            this.createTrendingIssuesChart();
        },

        createMonthlyCompletionChart: function() {
            const canvas = document.getElementById('monthly-completion-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const data = this.currentData.monthly_rates || [];

            const labels = data.map(item => {
                const date = new Date(item.month);
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            });

            const visitData = data.map(item => item.total_visits);

            if (this.charts.monthlyCompletion) {
                this.charts.monthlyCompletion.destroy();
            }

            this.charts.monthlyCompletion = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly Visits',
                        data: visitData,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        },

        createRatingsByDepartmentChart: function() {
            const canvas = document.getElementById('ratings-by-department-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const data = this.currentData.average_ratings || [];

            const labels = data.map(item => item.class_subject);
            const ratings = data.map(item => parseFloat(item.avg_rating));

            if (this.charts.ratingsByDepartment) {
                this.charts.ratingsByDepartment.destroy();
            }

            this.charts.ratingsByDepartment = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Average Rating',
                        data: ratings,
                        backgroundColor: [
                            '#0073aa',
                            '#005177',
                            '#2ea2cc',
                            '#00a0d2',
                            '#0085ba'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        },

        createOfficerPerformanceChart: function() {
            const canvas = document.getElementById('officer-performance-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const data = this.currentData.officer_performance || [];

            const labels = data.map(item => `Officer ${item.agent_id}`);
            const visitCounts = data.map(item => item.total_visits);

            if (this.charts.officerPerformance) {
                this.charts.officerPerformance.destroy();
            }

            this.charts.officerPerformance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: visitCounts,
                        backgroundColor: [
                            '#0073aa',
                            '#005177',
                            '#2ea2cc',
                            '#00a0d2',
                            '#0085ba'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'right'
                        }
                    }
                }
            });
        },

        createTrendingIssuesChart: function() {
            const canvas = document.getElementById('trending-issues-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const data = this.currentData.trending_issues || [];

            const labels = data.map(item => item.issue);
            const counts = data.map(item => item.count);

            if (this.charts.trendingIssues) {
                this.charts.trendingIssues.destroy();
            }

            this.charts.trendingIssues = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Issue Count',
                        data: counts,
                        backgroundColor: '#dc3232',
                        borderColor: '#dc3232',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        },

        updateRecentActivity: function() {
            $.ajax({
                url: qaAjax.url,
                type: 'POST',
                data: {
                    action: 'get_qa_summary',
                    nonce: qaAjax.nonce
                },
                success: (response) => {
                    if (response.success && response.data.recent_visits) {
                        this.displayRecentActivity(response.data.recent_visits);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error loading recent activity:', error);
                }
            });
        },

        displayRecentActivity: function(visits) {
            const container = $('#recent-activity-list');
            const esc = window.WeCozaUtils ? window.WeCozaUtils.escapeHtml : this._fallbackEscape;

            if (!visits || visits.length === 0) {
                container.html('<p>No recent visits found.</p>');
                return;
            }

            let html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Notes</th></tr></thead>';
            html += '<tbody>';

            visits.forEach(visit => {
                const notes = visit.notes ? visit.notes.substring(0, 50) + '...' : 'No notes';
                html += `<tr>
                    <td>${esc(visit.visit_date)}</td>
                    <td>${esc(visit.class_code)}</td>
                    <td>${esc(visit.class_subject)}</td>
                    <td>${esc(notes)}</td>
                </tr>`;
            });

            html += '</tbody></table>';
            container.html(html);
        },

        updateAlerts: function() {
            $.ajax({
                url: qaAjax.url,
                type: 'POST',
                data: {
                    action: 'get_qa_summary',
                    nonce: qaAjax.nonce
                },
                success: (response) => {
                    if (response.success && response.data.alerts) {
                        this.displayAlerts(response.data.alerts);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error loading alerts:', error);
                }
            });
        },

        displayAlerts: function(alerts) {
            const container = $('#alerts-container');
            const esc = window.WeCozaUtils ? window.WeCozaUtils.escapeHtml : this._fallbackEscape;

            if (!alerts || alerts.length === 0) {
                container.html('<p>No alerts at this time.</p>');
                return;
            }

            let html = '';
            alerts.forEach(alert => {
                // Whitelist allowed alert types for class attribute
                const allowedTypes = ['info', 'warning', 'error', 'success'];
                const safeType = allowedTypes.includes(alert.type) ? alert.type : 'info';
                html += `<div class="notice notice-${safeType}">
                    <p>${esc(alert.message)}</p>
                </div>`;
            });

            container.html(html);
        },

        refreshDashboard: function() {
            this.showLoading();
            this.loadDashboardData();
        },

        exportReports: function() {
            const startDate = $('#date-range-start').val() || '';
            const endDate = $('#date-range-end').val() || '';

            const form = $('<form>', {
                method: 'POST',
                action: qaAjax.url
            });

            const fields = {
                action: 'export_qa_reports',
                nonce: qaAjax.nonce,
                format: 'csv',
                start_date: startDate,
                end_date: endDate
            };

            $.each(fields, function(key, value) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: value
                }));
            });

            form.appendTo('body').submit().remove();
        },

        setupEventListeners: function() {
            // Date range and department filter changes
            $('#date-range-start, #date-range-end, #department-filter').on('change', () => {
                this.loadDashboardData();
            });

            // Refresh button
            $('#refresh-dashboard').on('click', (e) => {
                e.preventDefault();
                this.refreshDashboard();
            });

            // Export button
            $('#export-reports').on('click', (e) => {
                e.preventDefault();
                this.exportReports();
            });
        },

        setupAutoRefresh: function() {
            // Auto-refresh every 5 minutes
            this.refreshInterval = setInterval(() => {
                this.loadDashboardData();
            }, 300000);
        },

        showLoading: function() {
            $('.metric-value').text('Loading...');
        },

        showError: function(message) {
            const esc = window.WeCozaUtils ? window.WeCozaUtils.escapeHtml : this._fallbackEscape;
            const errorHtml = `<div class="error-message">
                <span class="dashicons dashicons-warning"></span>
                ${esc(message)}
            </div>`;

            $('.qa-analytics-dashboard').prepend(errorHtml);

            setTimeout(() => {
                $('.error-message').fadeOut();
            }, 5000);
        },

        destroy: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }

            Object.keys(this.charts).forEach(key => {
                if (this.charts[key]) {
                    this.charts[key].destroy();
                }
            });
        },

        // Secure fallback escape - fails closed, not open
        _fallbackEscape: function(str) {
            if (str === null || str === undefined) return '';
            var div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        }
    };

    // QA Widget Class
    window.QAWidget = function() {
        this.miniChart = null;
        this.refreshInterval = null;
    };

    QAWidget.prototype = {
        init: function() {
            this.loadWidgetData();
            this.setupAutoRefresh();
        },

        loadWidgetData: function() {
            $.ajax({
                url: qaAjax.url,
                type: 'POST',
                data: {
                    action: 'get_qa_summary',
                    nonce: qaAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateMetrics(response.data.key_metrics);
                        this.updateRecentVisits(response.data.recent_visits);
                        this.updateAlerts(response.data.alerts);
                        this.updateMiniChart(response.data.recent_visits);
                    } else {
                        console.error('Error loading widget data:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error loading widget data:', error);
                }
            });
        },

        updateMetrics: function(metrics) {
            if (!metrics) return;

            $('#widget-classes-count').text(metrics.classes_this_month || '0');
            $('#widget-visits-count').text(metrics.visits_this_month || '0');
            $('#widget-avg-rating').text(metrics.avg_rating_this_month || '0');
        },

        updateRecentVisits: function(visits) {
            const container = $('#widget-visits-list');
            const esc = window.WeCozaUtils ? window.WeCozaUtils.escapeHtml : this._fallbackEscape;

            if (!visits || visits.length === 0) {
                container.html('<p>No recent visits found.</p>');
                return;
            }

            let html = '<ul class="widget-visits-list">';
            visits.forEach(visit => {
                const notes = visit.notes ? visit.notes.substring(0, 80) + '...' : 'No notes';
                html += `<li class="visit-item">
                    <div class="visit-date">${esc(visit.visit_date)}</div>
                    <div class="visit-details">
                        <strong>${esc(visit.class_code)}</strong> - ${esc(visit.class_subject)}
                        <div class="visit-notes">${esc(notes)}</div>
                    </div>
                </li>`;
            });
            html += '</ul>';

            container.html(html);
        },

        updateAlerts: function(alerts) {
            const container = $('#widget-alerts-list');
            const esc = window.WeCozaUtils ? window.WeCozaUtils.escapeHtml : this._fallbackEscape;

            if (!alerts || alerts.length === 0) {
                container.html('<p>No alerts at this time.</p>');
                return;
            }

            // Whitelist allowed alert types for class attribute
            const allowedTypes = ['info', 'warning', 'error', 'success'];

            let html = '<ul class="widget-alerts-list">';
            alerts.forEach(alert => {
                const safeType = allowedTypes.includes(alert.type) ? alert.type : 'info';
                html += `<li class="alert-item alert-${safeType}">
                    <span class="alert-icon dashicons dashicons-${this.getAlertIcon(alert.type)}"></span>
                    <span class="alert-message">${esc(alert.message)}</span>
                </li>`;
            });
            html += '</ul>';

            container.html(html);
        },

        updateMiniChart: function(visits) {
            const canvas = document.getElementById('widget-mini-chart');
            if (!canvas || !visits || visits.length === 0) return;

            const last7Days = this.getLast7Days();
            const visitCounts = this.getVisitCountsForDays(visits, last7Days);

            if (this.miniChart) {
                this.miniChart.destroy();
            }

            this.miniChart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: last7Days.map(date => date.getDate()),
                    datasets: [{
                        label: 'Daily Visits',
                        data: visitCounts,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        getLast7Days: function() {
            const days = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                days.push(date);
            }
            return days;
        },

        getVisitCountsForDays: function(visits, days) {
            return days.map(day => {
                const dayStr = day.toISOString().split('T')[0];
                return visits.filter(visit => visit.visit_date === dayStr).length;
            });
        },

        getAlertIcon: function(type) {
            switch (type) {
                case 'warning': return 'warning';
                case 'error': return 'dismiss';
                case 'success': return 'yes';
                default: return 'info';
            }
        },

        showNewVisitForm: function() {
            // This would typically open a modal or navigate to a form
            // For now, just show an alert
            alert('New QA Visit form would open here. This will be implemented with a modal or separate page.');
        },

        refresh: function() {
            this.loadWidgetData();
        },

        setupAutoRefresh: function() {
            // Auto-refresh every 5 minutes
            this.refreshInterval = setInterval(() => {
                this.loadWidgetData();
            }, 300000);
        },

        destroy: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
            if (this.miniChart) {
                this.miniChart.destroy();
            }
        },

        // Secure fallback escape - fails closed, not open
        _fallbackEscape: function(str) {
            if (str === null || str === undefined) return '';
            var div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Initialize QA Analytics Dashboard if present
        if ($('.qa-analytics-dashboard').length > 0) {
            const qaAnalytics = new QAAnalytics();
            qaAnalytics.init();
            
            // Store instance globally for external access
            window.qaAnalyticsInstance = qaAnalytics;
        }

        // Initialize QA Widget if present
        if ($('.qa-dashboard-widget').length > 0) {
            const qaWidget = new QAWidget();
            qaWidget.init();
            
            // Store instance globally for external access
            window.qaWidgetInstance = qaWidget;
            
            // Widget-specific event handlers
            $('#refresh-qa-widget').on('click', function() {
                qaWidget.refresh();
            });

            $('#new-qa-visit').on('click', function(e) {
                e.preventDefault();
                qaWidget.showNewVisitForm();
            });
        }
    });

})(jQuery);