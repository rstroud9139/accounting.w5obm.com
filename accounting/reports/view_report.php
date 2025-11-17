<?php

/**
 * View Report
 * File: /accounting/reports/view_report.php
 * Purpose: Display generated report details
 * SECURITY: Requires accounting permissions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access reports.', 'fas fa-chart-line');
    header('Location: ../../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view reports.', 'fas fa-chart-line');
    header('Location: ../../authentication/dashboard.php');
    exit();
}

// Get report ID from URL
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$report_id) {
    setToastMessage('danger', 'Invalid Report', 'Report ID is required.', 'fas fa-exclamation-triangle');
    header('Location: reports_dashboard.php');
    exit();
}

// Fetch report details
$stmt = $conn->prepare("SELECT * FROM acc_reports WHERE id = ?");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    setToastMessage('danger', 'Report Not Found', 'The requested report could not be found.', 'fas fa-exclamation-triangle');
    header('Location: reports_dashboard.php');
    exit();
}

// Log access
logActivity($user_id, 'report_viewed', 'auth_activity_log', $report_id, "Viewed report: {$report['report_type']}");

$page_title = "View Report - W5OBM";
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="container mt-4">
        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">View Report</h3>
                        <small><?php echo ucfirst($report['report_type']); ?> Report</small>
                    </div>
                    <div class="col-auto">
                        <a href="reports_dashboard.php" class="btn btn-light btn-sm shadow">
                            <i class="fas fa-arrow-left me-1"></i>Back to Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Details -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Report Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Report ID:</strong></td>
                                <td><?php echo $report['id']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Report Type:</strong></td>
                                <td><?php echo ucfirst($report['report_type']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Parameters:</strong></td>
                                <td><?php echo htmlspecialchars($report['parameters']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Generated:</strong></td>
                                <td><?php echo date('M d, Y g:i A', strtotime($report['generated_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>File Path:</strong></td>
                                <td><?php echo $report['file_path'] ? basename($report['file_path']) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-success">Generated</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Actions -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>Report Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="download_report.php?id=<?php echo $report['id']; ?>" class="btn btn-success w-100 shadow">
                            <i class="fas fa-download me-2"></i>Download Report
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="email_report.php?id=<?php echo $report['id']; ?>" class="btn btn-primary w-100 shadow">
                            <i class="fas fa-envelope me-2"></i>Email Report
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="regenerate_report.php?id=<?php echo $report['id']; ?>" class="btn btn-warning w-100 shadow">
                            <i class="fas fa-sync me-2"></i>Regenerate
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="delete_report.php?id=<?php echo $report['id']; ?>"
                            class="btn btn-danger w-100 shadow"
                            onclick="return confirm('Are you sure you want to delete this report?');">
                            <i class="fas fa-trash me-2"></i>Delete Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Content Preview -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>Report Preview
                </h5>
            </div>
            <div class="card-body">
                <?php if ($report['file_path'] && file_exists($report['file_path'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Report file is available for download. Preview functionality coming soon.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Report file not found. The report may need to be regenerated.
                    </div>
                <?php endif; ?>

                <div class="text-center">
                    <p class="text-muted">Report parameters: <?php echo htmlspecialchars($report['parameters']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        // Show success toast on page load
        setTimeout(function() {
            showToast('info', 'Report Details', 'Report information loaded successfully.', 'fas fa-chart-line');
        }, 500);
    </script>
</body>

</html>