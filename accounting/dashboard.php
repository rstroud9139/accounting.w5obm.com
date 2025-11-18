<?php

/**
 * Accounting Dashboard - W5OBM Accounting System
 * File: /accounting/dashboard.php
 * Purpose: Main dashboard for accounting system
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/lib/helpers.php';
// Consolidated controller naming: prefer snake_case versions going forward
require_once __DIR__ . '/controllers/transaction_controller.php';
require_once __DIR__ . '/controllers/report_controller.php';
// Centralized stats service layer
require_once __DIR__ . '/utils/stats_service.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
// Super Admin/Admin bypass for accounting access
if (!isSuperAdmin($user_id) && !isAdmin($user_id) && !hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to access the accounting system.', 'club-logo');
    header('Location: /authentication/dashboard.php');
    exit();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Club Accounting System Dashboard";

// Get dashboard statistics
try {
    // Current month stats
    $current_month = date('Y-m');
    $month_start = $current_month . '-01';
    $month_end = date('Y-m-t');

    $month_totals = get_transaction_totals($month_start, $month_end);

    // Year-to-date stats
    $year_start = date('Y') . '-01-01';
    $ytd_totals = get_transaction_totals($year_start, date('Y-m-d'));

    // Recent transactions
    $recent_transactions = get_recent_transactions(5);

    // Centralized balance & asset value
    $cash_balance = get_cash_balance();
    $asset_value = get_asset_value();
} catch (Exception $e) {
    $month_totals = ['income' => 0, 'expenses' => 0, 'net_balance' => 0, 'transaction_count' => 0];
    $ytd_totals = ['income' => 0, 'expenses' => 0, 'net_balance' => 0, 'transaction_count' => 0];
    $recent_transactions = [];
    $cash_balance = 0;
    $asset_value = 0;
    logError("Error loading dashboard data: " . $e->getMessage(), 'accounting');
    setToastMessage('warning', 'Dashboard Warning', 'Some dashboard data could not be loaded.', 'club-logo');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../include/menu.php'; ?>

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
                        <i class="fas fa-calculator fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Accounting Dashboard</h3>
                        <small>W5OBM Financial Management System</small>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group" role="group">
                            <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                                <a href="/accounting/transactions/add_transaction.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-plus me-1"></i>Add Transaction
                                </a>
                            <?php endif; ?>
                            <a href="/accounting/reports/reports_dashboard.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-chart-bar me-1"></i>Reports
                            </a>
                            <a href="/accounting/manual.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-book me-1"></i>User Manual
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Overview Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white h-100 shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="fs-6 fw-bold">Cash Balance</div>
                                <div class="fs-4">$<?= number_format($cash_balance, 2) ?></div>
                                <small class="opacity-75">Current available funds</small>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-info text-white h-100 shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="fs-6 fw-bold">Asset Value</div>
                                <div class="fs-4">$<?= number_format($asset_value, 2) ?></div>
                                <small class="opacity-75">Physical assets</small>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-boxes fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-warning text-dark h-100 shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="fs-6 fw-bold">Monthly Net</div>
                                <div class="fs-4 text-<?= $month_totals['net_balance'] >= 0 ? 'success' : 'danger' ?>">
                                    $<?= number_format($month_totals['net_balance'], 2) ?>
                                </div>
                                <small class="opacity-75"><?= date('F Y') ?></small>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-alt fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-primary text-white h-100 shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="fs-6 fw-bold">YTD Net</div>
                                <div class="fs-4">$<?= number_format($ytd_totals['net_balance'], 2) ?></div>
                                <small class="opacity-75"><?= date('Y') ?> Year-to-Date</small>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-3x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                                <div class="col-md-6 mb-3">
                                    <a href="/accounting/transactions/add_transaction.php" class="btn btn-success btn-lg w-100 shadow-sm">
                                        <i class="fas fa-plus me-2"></i>Add Transaction
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6 mb-3">
                                <a href="/accounting/transactions/transactions.php" class="btn btn-primary btn-lg w-100 shadow-sm">
                                    <i class="fas fa-list me-2"></i>View Transactions
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="/accounting/reports/reports_dashboard.php" class="btn btn-info btn-lg w-100 shadow-sm">
                                    <i class="fas fa-chart-bar me-2"></i>Generate Reports
                                </a>
                            </div>
                            <?php if (hasPermission($user_id, 'accounting_manage')): ?>
                                <div class="col-md-6 mb-3">
                                    <a href="/accounting/categories/" class="btn btn-warning btn-lg w-100 shadow-sm">
                                        <i class="fas fa-tags me-2"></i>Manage Categories
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="/accounting/ledger/" class="btn btn-info btn-lg w-100 shadow-sm">
                                        <i class="fas fa-sitemap me-2"></i>Chart of Accounts
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6 mb-3">
                                <a href="/accounting/manual.php" class="btn btn-secondary btn-lg w-100 shadow-sm">
                                    <i class="fas fa-book me-2"></i>User Manual
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Month Summary -->
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar me-2 text-primary"></i><?= date('F Y') ?> Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-success"><i class="fas fa-arrow-up me-1"></i>Income:</span>
                                <span class="fw-bold text-success">$<?= number_format($month_totals['income'], 2) ?></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-danger"><i class="fas fa-arrow-down me-1"></i>Expenses:</span>
                                <span class="fw-bold text-danger">$<?= number_format($month_totals['expenses'], 2) ?></span>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Net Balance:</span>
                                <span class="fw-bold text-<?= $month_totals['net_balance'] >= 0 ? 'success' : 'danger' ?>">
                                    $<?= number_format($month_totals['net_balance'], 2) ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-center">
                            <small class="text-muted">
                                <?= $month_totals['transaction_count'] ?> transactions this month
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <?php if (!empty($recent_transactions)): ?>
            <div class="card shadow">
                <div class="card-header bg-light">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2 text-secondary"></i>Recent Transactions
                            </h5>
                        </div>
                        <div class="col-auto">
                            <a href="/accounting/transactions/transactions.php" class="btn btn-outline-primary btn-sm">
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
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('M j', strtotime($transaction['transaction_date'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                                    switch ($transaction['type']) {
                                                                        case 'Income':
                                                                            echo 'success';
                                                                            break;
                                                                        case 'Expense':
                                                                            echo 'danger';
                                                                            break;
                                                                        case 'Asset':
                                                                            echo 'info';
                                                                            break;
                                                                        case 'Transfer':
                                                                            echo 'warning';
                                                                            break;
                                                                        default:
                                                                            echo 'secondary';
                                                                    }
                                                                    ?>">
                                                <?= htmlspecialchars($transaction['type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($transaction['description']) ?></div>
                                            <?php if (!empty($transaction['vendor_name'])): ?>
                                                <small class="text-muted">Vendor: <?= htmlspecialchars($transaction['vendor_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($transaction['category_name'] ?? 'N/A') ?></small>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-bold text-<?= $transaction['type'] === 'Income' ? 'success' : 'danger' ?>">
                                                <?= $transaction['type'] === 'Income' ? '+' : '-' ?>$<?= number_format($transaction['amount'], 2) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (hasPermission($user_id, 'accounting_edit') || hasPermission($user_id, 'accounting_manage')): ?>
                                                <a href="/accounting/transactions/edit_transaction.php?id=<?= $transaction['id'] ?>"
                                                    class="btn btn-outline-primary btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Recent Transactions</h5>
                    <p class="text-muted">Get started by adding your first transaction.</p>
                    <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                        <a href="/accounting/transactions/add_transaction.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Add First Transaction
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script>
        // Dashboard refresh functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh dashboard data every 5 minutes
            setInterval(function() {
                // You could implement AJAX refresh here if needed
                console.log('Dashboard auto-refresh check');
            }, 300000); // 5 minutes
        });
    </script>
</body>

</html>