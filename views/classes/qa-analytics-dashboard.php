<?php
/**
 * QA Analytics Dashboard View
 * 
 * Comprehensive dashboard for QA statistics and analytics
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Start output buffering to prevent header conflicts
ob_start();
?>

<div class="wrap qa-analytics-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Dashboard Controls -->
    <div class="qa-dashboard-controls">
        <div class="control-group">
            <label for="date-range-start">Start Date:</label>
            <input type="date" id="date-range-start" value="<?php echo date('Y-m-01', strtotime('-6 months')); ?>">
        </div>
        <div class="control-group">
            <label for="date-range-end">End Date:</label>
            <input type="date" id="date-range-end" value="<?php echo date('Y-m-t'); ?>">
        </div>
        <div class="control-group">
            <label for="department-filter">Department:</label>
            <select id="department-filter">
                <option value="">All Departments</option>
                <option value="safety">Safety Training</option>
                <option value="skills">Skills Development</option>
                <option value="technical">Technical Training</option>
                <option value="management">Management</option>
            </select>
        </div>
        <button id="refresh-dashboard" class="button button-primary">Refresh Dashboard</button>
        <button id="export-reports" class="button button-secondary">Export Reports</button>
    </div>

    <!-- Key Metrics Summary -->
    <div class="qa-metrics-summary">
        <div class="metric-card">
            <h3>Total Visits</h3>
            <div class="metric-value" id="total-visits">Loading...</div>
            <div class="metric-change" id="visits-change">-</div>
        </div>
        <div class="metric-card">
            <h3>Classes Visited</h3>
            <div class="metric-value" id="classes-visited">Loading...</div>
            <div class="metric-change" id="classes-change">-</div>
        </div>
        <div class="metric-card">
            <h3>Active Officers</h3>
            <div class="metric-value" id="active-officers">Loading...</div>
            <div class="metric-change" id="officers-change">-</div>
        </div>
        <div class="metric-card">
            <h3>Overall Rating</h3>
            <div class="metric-value" id="overall-rating">Loading...</div>
            <div class="metric-change" id="rating-change">-</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="qa-charts-section">
        <div class="chart-container">
            <h3>Monthly Visit Completion Rates</h3>
            <canvas id="monthly-completion-chart"></canvas>
        </div>
        
        <div class="chart-container">
            <h3>Average Ratings by Department</h3>
            <canvas id="ratings-by-department-chart"></canvas>
        </div>
    </div>

    <div class="qa-charts-section">
        <div class="chart-container">
            <h3>Officer Performance Metrics</h3>
            <canvas id="officer-performance-chart"></canvas>
        </div>
        
        <div class="chart-container">
            <h3>Trending Issues</h3>
            <canvas id="trending-issues-chart"></canvas>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="qa-recent-activity">
        <h3>Recent QA Activity</h3>
        <div id="recent-activity-list">
            Loading recent activity...
        </div>
    </div>

    <!-- Alerts & Notifications -->
    <div class="qa-alerts">
        <h3>Alerts & Notifications</h3>
        <div id="alerts-container">
            Loading alerts...
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize dashboard
    var qaAnalytics = new QAAnalytics();
    qaAnalytics.init();

    // Refresh dashboard on button click
    $('#refresh-dashboard').on('click', function() {
        qaAnalytics.refreshDashboard();
    });

    // Export reports
    $('#export-reports').on('click', function() {
        qaAnalytics.exportReports();
    });
});

// QA Analytics Dashboard JavaScript Class
class QAAnalytics {
    constructor() {
        this.charts = {};
        this.currentData = null;
    }

    init() {
        this.loadDashboardData();
        this.setupEventListeners();
    }

    loadDashboardData() {
        const startDate = document.getElementById('date-range-start').value;
        const endDate = document.getElementById('date-range-end').value;
        const department = document.getElementById('department-filter').value;

        jQuery.ajax({
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
                }
            },
            error: (xhr, status, error) => {
                console.error('Error loading dashboard data:', error);
            }
        });
    }

    updateMetrics() {
        const data = this.currentData;
        if (!data || !data.overall_stats) return;

        const stats = data.overall_stats;
        document.getElementById('total-visits').textContent = stats.total_visits || '0';
        document.getElementById('classes-visited').textContent = stats.classes_visited || '0';
        document.getElementById('active-officers').textContent = stats.active_officers || '0';
        document.getElementById('overall-rating').textContent = stats.overall_rating || '0';
    }

    updateCharts() {
        if (!this.currentData) return;

        this.createMonthlyCompletionChart();
        this.createRatingsByDepartmentChart();
        this.createOfficerPerformanceChart();
        this.createTrendingIssuesChart();
    }

    createMonthlyCompletionChart() {
        const ctx = document.getElementById('monthly-completion-chart').getContext('2d');
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
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    createRatingsByDepartmentChart() {
        const ctx = document.getElementById('ratings-by-department-chart').getContext('2d');
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
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });
    }

    createOfficerPerformanceChart() {
        const ctx = document.getElementById('officer-performance-chart').getContext('2d');
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
                responsive: true
            }
        });
    }

    createTrendingIssuesChart() {
        const ctx = document.getElementById('trending-issues-chart').getContext('2d');
        const data = this.currentData.trending_issues || [];

        const labels = data.map(item => item.issue);
        const counts = data.map(item => item.count);

        if (this.charts.trendingIssues) {
            this.charts.trendingIssues.destroy();
        }

        this.charts.trendingIssues = new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Issue Count',
                    data: counts,
                    backgroundColor: '#dc3232'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    updateRecentActivity() {
        // Load recent activity via separate AJAX call
        jQuery.ajax({
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
            }
        });
    }

    displayRecentActivity(visits) {
        const container = document.getElementById('recent-activity-list');
        let html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Notes</th></tr></thead>';
        html += '<tbody>';

        visits.forEach(visit => {
            html += `<tr>
                <td>${visit.visit_date}</td>
                <td>${visit.class_code}</td>
                <td>${visit.class_subject}</td>
                <td>${visit.notes.substring(0, 50)}...</td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    updateAlerts() {
        jQuery.ajax({
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
            }
        });
    }

    displayAlerts(alerts) {
        const container = document.getElementById('alerts-container');
        let html = '';

        alerts.forEach(alert => {
            html += `<div class="notice notice-${alert.type}">
                <p>${alert.message}</p>
            </div>`;
        });

        container.innerHTML = html;
    }

    refreshDashboard() {
        this.loadDashboardData();
    }

    exportReports() {
        const startDate = document.getElementById('date-range-start').value;
        const endDate = document.getElementById('date-range-end').value;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = qaAjax.url;

        const fields = {
            action: 'export_qa_reports',
            nonce: qaAjax.nonce,
            format: 'csv',
            start_date: startDate,
            end_date: endDate
        };

        Object.keys(fields).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    setupEventListeners() {
        // Add event listeners for date range and department filter changes
        document.getElementById('date-range-start').addEventListener('change', () => {
            this.loadDashboardData();
        });

        document.getElementById('date-range-end').addEventListener('change', () => {
            this.loadDashboardData();
        });

        document.getElementById('department-filter').addEventListener('change', () => {
            this.loadDashboardData();
        });
    }
}
</script>


<?php
// Flush output buffer to prevent header conflicts
ob_end_flush();
?>