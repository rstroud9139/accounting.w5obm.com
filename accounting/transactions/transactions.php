<?php

/**
 * Transactions List Page - W5OBM Accounting System
 * File: /accounting/transactions/transactions.php
 * Purpose: Main transactions listing with filtering and management
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/transaction_controller.php';
require_once __DIR__ . '/../views/transactionList.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view transactions.', 'club-logo');
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

$page_title = "Transactions - W5OBM Accounting";

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        // Get filters from GET parameters
        $filters = [
            'start_date' => sanitizeInput($_GET['start_date'] ?? '', 'string'),
            'end_date' => sanitizeInput($_GET['end_date'] ?? '', 'string'),
            'category_id' => sanitizeInput($_GET['category_id'] ?? '', 'int'),
            'type' => sanitizeInput($_GET['type'] ?? '', 'string'),
            'account_id' => sanitizeInput($_GET['account_id'] ?? '', 'int'),
            'search' => sanitizeInput($_GET['search'] ?? '', 'string')
        ];

        // Remove empty filters
        $filters = array_filter($filters);

        // Get all transactions (no limit for export)
        require_once __DIR__ . '/../controllers/transaction_controller.php';
        $transactions = fetch_all_transactions(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            $filters['category_id'] ?? null,
            $filters['type'] ?? null,
            $filters['account_id'] ?? null
        );

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Write CSV headers
        fputcsv($output, [
            'Date',
            'Type',
            'Category',
            'Description',
            'Account',
            'Vendor',
            'Amount',
            'Reference Number',
            'Notes',
            'Created By',
            'Created Date'
        ]);

        // Write transaction data
        foreach ($transactions as $transaction) {
            fputcsv($output, [
                $transaction['transaction_date'],
                $transaction['type'],
                $transaction['category_name'] ?? '',
                $transaction['description'],
                $transaction['account_name'] ?? '',
                $transaction['vendor_name'] ?? '',
                number_format($transaction['amount'], 2),
                $transaction['reference_number'] ?? '',
                $transaction['notes'] ?? '',
                $transaction['created_by_username'] ?? '',
                $transaction['created_at']
            ]);
        }

        fclose($output);
        exit();
    } catch (Exception $e) {
        setToastMessage('danger', 'Export Error', 'Failed to export transactions: ' . $e->getMessage(), 'club-logo');
        logError("Error exporting transactions: " . $e->getMessage(), 'accounting');
    }
}

// Get filters from GET parameters
$filters = [
    'start_date' => sanitizeInput($_GET['start_date'] ?? '', 'string'),
    'end_date' => sanitizeInput($_GET['end_date'] ?? '', 'string'),
    'category_id' => sanitizeInput($_GET['category_id'] ?? '', 'int'),
    'type' => sanitizeInput($_GET['type'] ?? '', 'string'),
    'account_id' => sanitizeInput($_GET['account_id'] ?? '', 'int'),
    'search' => sanitizeInput($_GET['search'] ?? '', 'string')
];

// Remove empty filters
$filters = array_filter($filters);

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25; // Transactions per page
$offset = ($page - 1) * $limit;

try {
    // Get transactions with pagination
    require_once __DIR__ . '/../controllers/transaction_controller.php';
    $transactions = fetch_all_transactions(
        $filters['start_date'] ?? null,
        $filters['end_date'] ?? null,
        $filters['category_id'] ?? null,
        $filters['type'] ?? null,
        $filters['account_id'] ?? null
    );

    // Get total count for pagination
    $all_transactions = $transactions;
    $total_transactions = count($all_transactions);

    // Calculate pagination info
    $pagination = [
        'current_page' => $page,
        'total_pages' => ceil($total_transactions / $limit),
        'total' => $total_transactions,
        'limit' => $limit,
        'offset' => $offset
    ];

    // Get transaction totals
    $totals = calculateTransactionTotals($filters);
} catch (Exception $e) {
    $transactions = [];
    $totals = [];
    $pagination = [];
    setToastMessage('danger', 'Error', 'Failed to load transactions: ' . $e->getMessage(), 'club-logo');
    logError("Error loading transactions: " . $e->getMessage(), 'accounting');
}

// Get categories for filter dropdown
try {
    $stmt = $conn->prepare("SELECT id, name FROM acc_transaction_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $categories = [];
    logError("Error fetching categories: " . $e->getMessage(), 'accounting');
}

// Get accounts for filter dropdown
try {
    $stmt = $conn->prepare("SELECT id, name FROM acc_ledger_accounts ORDER BY name");
    $stmt->execute();
    $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $accounts = [];
    logError("Error fetching accounts: " . $e->getMessage(), 'accounting');
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
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Transaction Management</h3>
                        <small>View, filter, and manage all accounting transactions</small>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group" role="group">
                            <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                                <a href="/accounting/transactions/add_transaction.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-plus me-1"></i>Add Transaction
                                </a>
                            <?php endif; ?>
                            <a href="/accounting/dashboard.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <?php if (!empty($totals)): ?>
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-success text-white h-100 shadow">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="fs-6 fw-bold">Total Income</div>
                                    <div class="fs-4">$<?= number_format($totals['income'], 2) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-arrow-up fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-danger text-white h-100 shadow">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="fs-6 fw-bold">Total Expenses</div>
                                    <div class="fs-4">$<?= number_format($totals['expenses'], 2) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-arrow-down fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card bg-<?= $totals['net_balance'] >= 0 ? 'primary' : 'warning' ?> text-white h-100 shadow">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="fs-6 fw-bold">Net Balance</div>
                                    <div class="fs-4">$<?= number_format($totals['net_balance'], 2) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-balance-scale fa-2x opacity-75"></i>
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
                                    <div class="fs-6 fw-bold">Total Transactions</div>
                                    <div class="fs-4"><?= number_format($totals['transaction_count']) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-list fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Transaction List -->
        <?php
        renderTransactionList(
            $transactions,
            $filters,
            $categories,
            $accounts,
            $totals,
            $pagination
        );
        ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>