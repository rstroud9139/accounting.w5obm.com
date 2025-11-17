<?php

/**
 * Delete Report
 * File: /accounting/reports/delete_report.php
 * Purpose: Delete generated report records and files
 * SECURITY: Requires accounting permissions and admin privileges
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

// Check application permissions - require admin for deletion
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to delete reports.', 'fas fa-chart-line');
    header('Location: ../../authentication/dashboard.php');
    exit();
}

// Additional check - only admins can delete reports
if (!isAdmin($user_id)) {
    setToastMessage('danger', 'Admin Required', 'Only administrators can delete reports.', 'fas fa-shield-alt');
    header('Location: reports_dashboard.php');
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

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $success = true;
    $error_message = '';

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete physical file if it exists
        if ($report['file_path'] && file_exists($report['file_path'])) {
            // Security check - ensure file is in allowed directory
            $allowed_paths = [
                realpath(__DIR__ . '/../../reports/'),
                realpath(__DIR__ . '/../../exports/'),
                realpath(__DIR__ . '/../../temp/')
            ];

            $file_path = realpath($report['file_path']);
            $is_allowed = false;

            foreach ($allowed_paths as $allowed_path) {
                if ($allowed_path && strpos($file_path, $allowed_path) === 0) {
                    $is_allowed = true;
                    break;
                }
            }

            if ($is_allowed) {
                if (!unlink($report['file_path'])) {
                    $error_message = 'Failed to delete report file.';
                    $success = false;
                }
            }
        }

        // Delete database record
        if ($success) {
            $stmt = $conn->prepare("DELETE FROM acc_reports WHERE id = ?");
            $stmt->bind_param('i', $report_id);

            if (!$stmt->execute()) {
                $error_message = 'Failed to delete report record.';
                $success = false;
            }
            $stmt->close();
        }

        if ($success) {
            // Commit transaction
            $conn->commit();

            // Log the action
            logActivity($user_id, 'report_deleted', 'auth_activity_log', $report_id, "Deleted report: {$report['report_type']}");

            setToastMessage('success', 'Report Deleted', 'The report has been successfully deleted.', 'fas fa-check-circle');
            header('Location: reports_dashboard.php');
            exit();
        } else {
            // Rollback transaction
            $conn->rollback();
            setToastMessage('danger', 'Deletion Failed', $error_message, 'fas fa-exclamation-triangle');
        }
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        setToastMessage('danger', 'Deletion Failed', 'An error occurred while deleting the report.', 'fas fa-exclamation-triangle');
        error_log("Report deletion error: " . $e->getMessage());
    }
}

$page_title = "Delete Report - W5OBM";
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="container mt-4">
        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-danger text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-trash fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Delete Report</h3>
                        <small>Permanently remove report and file</small>
                    </div>
                    <div class="col-auto">
                        <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn btn-light btn-sm shadow">
                            <i class="fas fa-arrow-left me-1"></i>Back to Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warning Alert -->
        <div class="alert alert-danger shadow mb-4" role="alert">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <div class="col">
                    <h5 class="alert-heading mb-1">Warning: Permanent Deletion</h5>
                    <p class="mb-0">This action cannot be undone. The report record and any associated files will be permanently deleted.</p>
                </div>
            </div>
        </div>

        <!-- Report Details -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Report to be Deleted
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
                                <td><strong>File Exists:</strong></td>
                                <td>
                                    <?php if ($report['file_path'] && file_exists($report['file_path'])): ?>
                                        <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Form -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-check-square me-2"></i>Deletion Confirmation
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-4">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>What will be deleted:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Report record from database</li>
                                <?php if ($report['file_path'] && file_exists($report['file_path'])): ?>
                                    <li>Report file: <?php echo basename($report['file_path']); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" required>
                            <label class="form-check-label" for="confirm_delete">
                                <strong>I understand that this action cannot be undone and want to permanently delete this report.</strong>
                            </label>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn btn-secondary shadow me-md-2">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-danger shadow" id="deleteBtn" disabled>
                            <i class="fas fa-trash me-1"></i>Delete Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        // Enable delete button only when checkbox is checked
        document.getElementById('confirm_delete').addEventListener('change', function() {
            document.getElementById('deleteBtn').disabled = !this.checked;
        });

        // Additional confirmation on form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!document.getElementById('confirm_delete').checked) {
                e.preventDefault();
                showToast('danger', 'Confirmation Required', 'Please confirm that you want to delete this report.', 'fas fa-exclamation-triangle');
                return;
            }

            if (!confirm('Are you absolutely sure you want to delete this report? This action cannot be undone.')) {
                e.preventDefault();
            }
        });

        // Show warning toast on page load
        setTimeout(function() {
            showToast('warning', 'Delete Report', 'You are about to permanently delete this report. Please review the information carefully.', 'fas fa-trash');
        }, 500);
    </script>
</body>

</html>