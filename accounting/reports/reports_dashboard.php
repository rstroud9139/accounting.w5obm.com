<?php

/**
 * Reports Dashboard - W5OBM Accounting System
 * File: /accounting/reports/reports_dashboard.php
 * Purpose: Main dashboard for accessing all accounting reports
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/reportController.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view accounting reports.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Reports Dashboard - W5OBM Accounting";

// Get recent reports
try {
    $recent_reports = getAllReports([], 5); // Last 5 reports
} catch (Exception $e) {
    $recent_reports = [];
    logError("Error fetching recent reports: " . $e->getMessage(), 'accounting');
}

// Get current year summary for dashboard
$current_year = date('Y');
try {
    $year_summary = [
        'income' => 0,
        'expenses' => 0,
        'net_balance' => 0,
        'transaction_count' => 0
    ];

    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS total_expenses,
            COUNT(*) AS transaction_count
        FROM acc_transactions 
        WHERE YEAR(transaction_date) = ?
    ");
    $stmt->bind_param('i', $current_year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        $year_summary['income'] = floatval($result['total_income'] ?? 0);
        $year_summary['expenses'] = floatval($result['total_expenses'] ?? 0);
        $year_summary['net_balance'] = $year_summary['income'] - $year_summary['expenses'];
        $year_summary['transaction_count'] = intval($result['transaction_count'] ?? 0);
    }
} catch (Exception $e) {
    logError("Error fetching year summary: " . $e->getMessage(), 'accounting');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <!-- Toast Message Display -->
    <?php
    if (function_exists('displayToastMessage')) {
        displayToastMessage();
    }
    ?>

    <!-- Page Container -->
    <div class="page-container">
        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-chart-bar fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Reports Dashboard</h3>
                        <small>Generate and manage accounting reports</small>
                    </div>
                    <div class="col-auto d-flex gap-2">
                        <button type="button" class="btn btn-light btn-sm" onclick="window.print()" title="Print">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                        <a href="/accounting/dashboard.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Year Summary -->
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i><?= $current_year ?> Year-to-Date Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-arrow-up fa-2x text-success mb-2"></i>
                            <h4 class="text-success mb-0">$<?= number_format($year_summary['income'], 2) ?></h4>
                            <small class="text-muted">Total Income</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="fas fa-arrow-down fa-2x text-danger mb-2"></i>
                            <h4 class="text-danger mb-0">$<?= number_format($year_summary['expenses'], 2) ?></h4>
                            <small class="text-muted">Total Expenses</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-<?= $year_summary['net_balance'] >= 0 ? 'primary' : 'warning' ?> bg-opacity-10 p-3 rounded">
                            <i class="fas fa-balance-scale fa-2x text-<?= $year_summary['net_balance'] >= 0 ? 'primary' : 'warning' ?> mb-2"></i>
                            <h4 class="text-<?= $year_summary['net_balance'] >= 0 ? 'primary' : 'warning' ?> mb-0">
                                $<?= number_format($year_summary['net_balance'], 2) ?>
                            </h4>
                            <small class="text-muted">Net Balance</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-list fa-2x text-info mb-2"></i>
                            <h4 class="text-info mb-0"><?= number_format($year_summary['transaction_count']) ?></h4>
                            <small class="text-muted">Total Transactions</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Categories -->
        <div class="row">
            <!-- Financial Reports -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Financial Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="/accounting/reports/income_statement.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-chart-pie text-success me-2"></i>Income Statement</h6>
                                    <small class="text-muted">Monthly/Yearly</small>
                                </div>
                                <p class="mb-1 small text-muted">Revenue and expenses by category for specific period</p>
                            </a>
                            <a href="/accounting/reports/balance_sheet.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-balance-scale text-primary me-2"></i>Balance Sheet</h6>
                                    <small class="text-muted">As of Date</small>
                                </div>
                                <p class="mb-1 small text-muted">Assets, liabilities, and equity from chart of accounts</p>
                            </a>
                            <a href="/accounting/reports/cash_flow.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-money-bill-wave text-info me-2"></i>Cash Flow Statement</h6>
                                    <small class="text-muted">Period</small>
                                </div>
                                <p class="mb-1 small text-muted">Cash inflows and outflows by account</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Reports -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Detailed Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="/accounting/reports/expense_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-credit-card text-danger me-2"></i>Expense Report</h6>
                                    <small class="text-muted">By Category</small>
                                </div>
                                <p class="mb-1 small text-muted">Detailed expense breakdown by category</p>
                            </a>
                            <a href="/accounting/reports/income_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-dollar-sign text-success me-2"></i>Income Report</h6>
                                    <small class="text-muted">By Source</small>
                                </div>
                                <p class="mb-1 small text-muted">Income breakdown by category and source</p>
                            </a>
                            <a href="/accounting/reports/monthly_summary.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-calendar-alt text-primary me-2"></i>Monthly Summary</h6>
                                    <small class="text-muted">Month by Month</small>
                                </div>
                                <p class="mb-1 small text-muted">Monthly financial summary and trends</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Asset Reports -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-boxes me-2"></i>Asset Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="/accounting/reports/physical_assets_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-cube text-info me-2"></i>Physical Assets</h6>
                                    <small class="text-muted">Inventory</small>
                                </div>
                                <p class="mb-1 small text-muted">Complete listing of physical assets</p>
                            </a>
                            <a href="/accounting/reports/asset_listing.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-list text-secondary me-2"></i>Asset Listing</h6>
                                    <small class="text-muted">Detailed</small>
                                </div>
                                <p class="mb-1 small text-muted">Detailed asset listing with values</p>
                            </a>
                            <a href="/accounting/reports/cash_account_report.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-university text-primary me-2"></i>Cash Accounts</h6>
                                    <small class="text-muted">Balances</small>
                                </div>
                                <p class="mb-1 small text-muted">Cash account balances and transactions</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Donation Reports -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-heart me-2"></i>Donation Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="/accounting/reports/donation_summary.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-chart-bar text-danger me-2"></i>Donation Summary</h6>
                                    <small class="text-muted">By Period</small>
                                </div>
                                <p class="mb-1 small text-muted">Summary of donations by time period</p>
                            </a>
                            <a href="/accounting/reports/donor_list.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-users text-success me-2"></i>Donor List</h6>
                                    <small class="text-muted">Contact Info</small>
                                </div>
                                <p class="mb-1 small text-muted">List of donors with contact information</p>
                            </a>
                            <a href="/accounting/reports/annual_donor_statement.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-receipt text-primary me-2"></i>Annual Statements</h6>
                                    <small class="text-muted">Tax Year</small>
                                </div>
                                <p class="mb-1 small text-muted">Annual donor statements for tax purposes</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports -->
        <?php if (!empty($recent_reports)): ?>
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Recent Reports
                            </h5>
                        </div>
                        <div class="col-auto">
                            <a href="/accounting/reports/view_report.php" class="btn btn-light btn-sm">
                                <i class="fas fa-eye me-1"></i>View All
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Report Type</th>
                                    <th>Generated</th>
                                    <th>Generated By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['report_type']))) ?></div>
                                            <?php if (!empty($report['parameters'])): ?>
                                                <small class="text-muted">
                                                    <?php
                                                    $params = is_string($report['parameters']) ? json_decode($report['parameters'], true) : $report['parameters'];
                                                    if ($params && is_array($params)) {
                                                        $param_strings = [];
                                                        foreach ($params as $key => $value) {
                                                            if (!empty($value)) {
                                                                $param_strings[] = ucfirst($key) . ': ' . $value;
                                                            }
                                                        }
                                                        echo htmlspecialchars(implode(', ', array_slice($param_strings, 0, 2)));
                                                        if (count($param_strings) > 2) echo '...';
                                                    }
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('M j, Y g:i A', strtotime($report['generated_at'])) ?></small>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($report['generated_by_username'] ?? 'Unknown') ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if (!empty($report['file_path']) && file_exists($report['file_path'])): ?>
                                                    <a href="/accounting/reports/download_report.php?id=<?= $report['id'] ?>"
                                                        class="btn btn-outline-primary btn-sm" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (hasPermission($user_id, 'accounting_manage') || $report['generated_by'] == $user_id): ?>
                                                    <a href="/accounting/reports/delete_report.php?id=<?= $report['id'] ?>"
                                                        class="btn btn-outline-danger btn-sm" title="Delete"
                                                        onclick="return confirm('Are you sure you want to delete this report?')">
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
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>