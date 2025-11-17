<?php

/**
 * Reports Dashboard
 * File: /accounting/reports_dashboard.php
 * Purpose: Main dashboard for financial reports
 * FIXED: Updated to use consolidated helper functions and fixed missing JSON variables
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/lib/helpers.php';

// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access reports.', 'fas fa-chart-line');
    header('Location: ../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to access reports.', 'fas fa-chart-line');
    header('Location: ../authentication/dashboard.php');
    exit();
}

// Log access
logActivity($user_id, 'reports_dashboard_access', 'auth_activity_log', null, 'Accessed Reports Dashboard');

$status = $_GET['status'] ?? null;

// Fetch reports
$query = "SELECT * FROM acc_reports ORDER BY generated_at DESC";
$result = $conn->query($query);
$reports = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Get report type counts for visualization
$report_type_query = "SELECT report_type, COUNT(*) as count FROM acc_reports GROUP BY report_type";
$report_type_result = $conn->query($report_type_query);
$report_types = [];
if ($report_type_result) {
    while ($row = $report_type_result->fetch_assoc()) {
        $report_types[] = [
            'report_type' => $row['report_type'],
            'count' => intval($row['count'])
        ];
    }
}

// Get monthly report generation counts for the past year
$monthly_report_query = "SELECT DATE_FORMAT(generated_at, '%Y-%m') as month, COUNT(*) as count 
                        FROM acc_reports 
                        WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(generated_at, '%Y-%m')
                        ORDER BY month ASC";
$monthly_report_result = $conn->query($monthly_report_query);
$monthly_reports = [];
if ($monthly_report_result) {
    while ($row = $monthly_report_result->fetch_assoc()) {
        $monthly_reports[] = [
            'month' => $row['month'],
            'count' => intval($row['count'])
        ];
    }
}

// Calculate recent report statistics
$recent_reports_count = 0;
$total_reports_count = 0;
$avg_reports_per_month = 0;

$recent_result = $conn->query("SELECT COUNT(*) as count FROM acc_reports WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($recent_result) {
    $recent_reports_count = $recent_result->fetch_assoc()['count'] ?? 0;
}

$total_result = $conn->query("SELECT COUNT(*) as count FROM acc_reports");
if ($total_result) {
    $total_reports_count = $total_result->fetch_assoc()['count'] ?? 0;
}

$avg_result = $conn->query("SELECT AVG(monthly_count) as avg FROM (SELECT COUNT(*) as monthly_count FROM acc_reports GROUP BY YEAR(generated_at), MONTH(generated_at)) as counts");
if ($avg_result) {
    $avg_reports_per_month = $avg_result->fetch_assoc()['avg'] ?? 0;
}

// FIXED: Prepare JSON data for JavaScript
$report_types_json = json_encode($report_types);
$monthly_reports_json = json_encode($monthly_reports);

$page_title = "Reports Dashboard - W5OBM";
include __DIR__ . '/../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Header Card per Website Guidelines -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Reports Dashboard</h3>
                        <small>Financial Reports & Analytics</small>
                    </div>
                    <div class="col-auto">
                        <a href="generate_report.php" class="btn btn-light btn-sm shadow">
                            <i class="fas fa-plus me-1"></i>New Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($status): ?>
            <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show shadow" role="alert">
                <?php echo ucfirst($status) === 'Success' ? 'Report generated successfully!' : 'Error generating report. Please try again.'; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Report Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 bg-primary text-white">
                    <div class="card-body">
                        <h5>Total Reports</h5>
                        <p class="h3"><?php echo number_format($total_reports_count); ?></p>
                        <p class="small">All time</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 bg-success text-white">
                    <div class="card-body">
                        <h5>Recent Reports</h5>
                        <p class="h3"><?php echo number_format($recent_reports_count); ?></p>
                        <p class="small">Last 30 days</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 bg-info text-white">
                    <div class="card-body">
                        <h5>Monthly Average</h5>
                        <p class="h3"><?php echo number_format(round($avg_reports_per_month)); ?></p>
                        <p class="small">Reports per month</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 bg-warning text-white">
                    <div class="card-body">
                        <h5>Report Types</h5>
                        <p class="h3"><?php echo count($report_types); ?></p>
                        <p class="small">Different formats</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Report Types Chart -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Report Type Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="reportTypeChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Report Generation Chart -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Monthly Generation Trends
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyReportChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Generation Options -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>Generate New Reports
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/income_statement.php?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-success w-100 shadow">
                            <i class="fas fa-chart-line me-2"></i>Income Statement
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/expense_report.php?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" class="btn btn-danger w-100 shadow">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Expense Report
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/cash_account_report.php?month=<?php echo date('Y-m'); ?>" class="btn btn-info w-100 shadow">
                            <i class="fas fa-money-bill-wave me-2"></i>Cash Account Report
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/monthly_income_report.php?month=<?php echo date('Y-m'); ?>" class="btn btn-primary w-100 shadow">
                            <i class="fas fa-dollar-sign me-2"></i>Monthly Income
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/ytd_income_statement.php?year=<?php echo date('Y'); ?>" class="btn btn-warning w-100 shadow">
                            <i class="fas fa-calendar-alt me-2"></i>YTD Income Statement
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/YTD_cash_report.php?year=<?php echo date('Y'); ?>" class="btn btn-secondary w-100 shadow">
                            <i class="fas fa-wallet me-2"></i>YTD Cash Report
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/physical_assets_report.php" class="btn btn-dark w-100 shadow">
                            <i class="fas fa-laptop me-2"></i>Physical Assets
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/generate_report.php" class="btn btn-primary w-100 shadow">
                            <i class="fas fa-plus-circle me-2"></i>Custom Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Reports Table -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Available Reports
                        </h5>
                    </div>
                    <div class="col-auto">
                        <a href="reports/generate_report.php" class="btn btn-success btn-sm shadow">
                            <i class="fas fa-plus-circle me-1"></i>Generate New Report
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($reports)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Reports Available</h5>
                        <p class="text-muted">Generate your first report to get started.</p>
                        <a href="reports/generate_report.php" class="btn btn-primary shadow">
                            <i class="fas fa-plus me-1"></i>Generate First Report
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="reportsTable" class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Parameters</th>
                                    <th>Generated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td><?php echo $report['id']; ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo ucfirst($report['report_type']); ?></span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($report['parameters']); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y g:i A', strtotime($report['generated_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="reports/view_report.php?id=<?php echo $report['id']; ?>"
                                                    class="btn btn-info btn-sm shadow" title="View Report">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="reports/download_report.php?id=<?php echo $report['id']; ?>"
                                                    class="btn btn-success btn-sm shadow" title="Download Report">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="reports/email_report.php?id=<?php echo $report['id']; ?>"
                                                    class="btn btn-primary btn-sm shadow" title="Email Report">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                                <?php if (isAdmin($user_id)): ?>
                                                    <a href="reports/delete_report.php?id=<?php echo $report['id']; ?>"
                                                        class="btn btn-danger btn-sm shadow" title="Delete Report"
                                                        onclick="return confirm('Are you sure you want to delete this report?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Wait for document to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable for reports (if reports exist)
            <?php if (!empty($reports)): ?>
                if (typeof $.fn.DataTable !== 'undefined') {
                    $('#reportsTable').DataTable({
                        responsive: true,
                        pageLength: 10,
                        order: [
                            [3, 'desc']
                        ] // Sort by generated date descending
                    });
                }
            <?php endif; ?>

            // Initialize charts
            initializeCharts();

            // Show welcome toast
            setTimeout(function() {
                showToast('info', 'Reports Dashboard', 'Here you can generate and view all financial reports.', 'fas fa-chart-line');
            }, 500);
        });

        function initializeCharts() {
            // FIXED: Check if data exists before initializing charts
            const reportTypeData = <?php echo $report_types_json; ?>;
            const monthlyReportData = <?php echo $monthly_reports_json; ?>;

            // Initialize Report Types Chart
            if (reportTypeData && reportTypeData.length > 0) {
                const reportTypeCtx = document.getElementById('reportTypeChart');
                if (reportTypeCtx) {
                    new Chart(reportTypeCtx, {
                        type: 'pie',
                        data: {
                            labels: reportTypeData.map(data => data.report_type),
                            datasets: [{
                                data: reportTypeData.map(data => data.count),
                                backgroundColor: [
                                    'rgba(40, 167, 69, 0.8)',
                                    'rgba(220, 53, 69, 0.8)',
                                    'rgba(255, 193, 7, 0.8)',
                                    'rgba(23, 162, 184, 0.8)',
                                    'rgba(108, 117, 125, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(40, 167, 69, 1)',
                                    'rgba(220, 53, 69, 1)',
                                    'rgba(255, 193, 7, 1)',
                                    'rgba(23, 162, 184, 1)',
                                    'rgba(108, 117, 125, 1)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw;
                                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: ${value} reports (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            } else {
                // Show placeholder for empty chart
                const reportTypeCtx = document.getElementById('reportTypeChart');
                if (reportTypeCtx) {
                    const ctx = reportTypeCtx.getContext('2d');
                    ctx.fillStyle = '#f8f9fa';
                    ctx.fillRect(0, 0, reportTypeCtx.width, reportTypeCtx.height);
                    ctx.fillStyle = '#6c757d';
                    ctx.font = '16px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('No report data available', reportTypeCtx.width / 2, reportTypeCtx.height / 2);
                }
            }

            // Initialize Monthly Report Generation Chart
            if (monthlyReportData && monthlyReportData.length > 0) {
                const monthlyReportCtx = document.getElementById('monthlyReportChart');
                if (monthlyReportCtx) {
                    new Chart(monthlyReportCtx, {
                        type: 'line',
                        data: {
                            labels: monthlyReportData.map(data => {
                                const dateParts = data.month.split('-');
                                const year = dateParts[0];
                                const month = dateParts[1];
                                const date = new Date(year, month - 1);
                                return date.toLocaleDateString('en-US', {
                                    month: 'short',
                                    year: 'numeric'
                                });
                            }),
                            datasets: [{
                                label: 'Reports Generated',
                                data: monthlyReportData.map(data => data.count),
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 2,
                                tension: 0.3,
                                fill: true
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
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return `${context.dataset.label}: ${context.raw} reports`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            } else {
                // Show placeholder for empty chart
                const monthlyReportCtx = document.getElementById('monthlyReportChart');
                if (monthlyReportCtx) {
                    const ctx = monthlyReportCtx.getContext('2d');
                    ctx.fillStyle = '#f8f9fa';
                    ctx.fillRect(0, 0, monthlyReportCtx.width, monthlyReportCtx.height);
                    ctx.fillStyle = '#6c757d';
                    ctx.font = '16px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('No monthly data available', monthlyReportCtx.width / 2, monthlyReportCtx.height / 2);
                }
            }
        }
    </script>
</body>

</html>