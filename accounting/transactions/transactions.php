<?php

/**
 * Transactions Workspace - W5OBM Accounting System
 * File: /accounting/transactions/transactions.php
 * Purpose: Modernized transactions dashboard with inline CRUD modals
 * Design: Follows W5OBM Modern Website Design Guidelines
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/transactionController.php';
require_once __DIR__ . '/../views/transactionList.php';
require_once __DIR__ . '/../../include/premium_hero.php';

// Authentication gate
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();
$can_view_transactions = hasPermission($user_id, 'accounting_view') || hasPermission($user_id, 'accounting_manage');

if (!$can_view_transactions) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view transactions.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

$can_manage_transactions = hasPermission($user_id, 'accounting_manage');
$can_add_transactions = $can_manage_transactions || hasPermission($user_id, 'accounting_add');

// CSRF token for inline forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Transactions - W5OBM Accounting';

// Handle legacy status query params forwarded from deprecated pages
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            setToastMessage('success', 'Transaction Added', 'Transaction added successfully!', 'fas fa-check-circle');
            break;
        case 'updated':
            setToastMessage('success', 'Transaction Updated', 'Transaction updated successfully!', 'fas fa-edit');
            break;
        case 'deleted':
            setToastMessage('success', 'Transaction Deleted', 'Transaction deleted successfully!', 'fas fa-trash');
            break;
        case 'error':
            setToastMessage('danger', 'Error', 'An error occurred. Please try again.', 'fas fa-exclamation-triangle');
            break;
    }
}

// -----------------------------------------------------------------------------
// Export handler
// -----------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $filters = [
            'start_date' => sanitizeInput($_GET['start_date'] ?? '', 'string'),
            'end_date' => sanitizeInput($_GET['end_date'] ?? '', 'string'),
            'category_id' => sanitizeInput($_GET['category_id'] ?? '', 'int'),
            'type' => sanitizeInput($_GET['type'] ?? '', 'string'),
            'account_id' => sanitizeInput($_GET['account_id'] ?? '', 'int'),
            'vendor_id' => sanitizeInput($_GET['vendor_id'] ?? '', 'int'),
            'search' => sanitizeInput($_GET['search'] ?? '', 'string')
        ];

        $filters = array_filter($filters);

        $transactions = fetch_all_transactions(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            $filters['category_id'] ?? null,
            $filters['type'] ?? null,
            $filters['account_id'] ?? null,
            $filters['vendor_id'] ?? null,
            $filters['search'] ?? null
        );

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
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
                $transaction['created_at'] ?? ''
            ]);
        }

        fclose($output);
        exit();
    } catch (Exception $e) {
        setToastMessage('danger', 'Export Error', 'Failed to export transactions: ' . $e->getMessage(), 'club-logo');
        logError('Error exporting transactions: ' . $e->getMessage(), 'accounting');
    }
}

// -----------------------------------------------------------------------------
// Filters & pagination
// -----------------------------------------------------------------------------
$filters = [
    'start_date' => sanitizeInput($_GET['start_date'] ?? '', 'string'),
    'end_date' => sanitizeInput($_GET['end_date'] ?? '', 'string'),
    'category_id' => sanitizeInput($_GET['category_id'] ?? '', 'int'),
    'type' => sanitizeInput($_GET['type'] ?? '', 'string'),
    'account_id' => sanitizeInput($_GET['account_id'] ?? '', 'int'),
    'vendor_id' => sanitizeInput($_GET['vendor_id'] ?? '', 'int'),
    'search' => sanitizeInput($_GET['search'] ?? '', 'string')
];

$filters = array_filter($filters);

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

try {
    $transactions = fetch_all_transactions(
        $filters['start_date'] ?? null,
        $filters['end_date'] ?? null,
        $filters['category_id'] ?? null,
        $filters['type'] ?? null,
        $filters['account_id'] ?? null,
        $filters['vendor_id'] ?? null,
        $filters['search'] ?? null
    );

    $all_transactions = $transactions;
    $total_transactions = count($all_transactions);

    $pagination = [
        'current_page' => $page,
        'total_pages' => max(1, (int)ceil($total_transactions / $limit)),
        'total' => $total_transactions,
        'limit' => $limit,
        'offset' => $offset
    ];

    $totals = calculateTransactionTotals($filters);
} catch (Exception $e) {
    $transactions = [];
    $totals = [];
    $pagination = [];
    setToastMessage('danger', 'Error', 'Failed to load transactions: ' . $e->getMessage(), 'club-logo');
    logError('Error loading transactions: ' . $e->getMessage(), 'accounting');
}

// -----------------------------------------------------------------------------
// Supporting dropdown data
// -----------------------------------------------------------------------------
try {
    $stmt = $conn->prepare('SELECT id, name, type, description FROM acc_transaction_categories ORDER BY type, name');
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $categories = [];
    logError('Error fetching categories: ' . $e->getMessage(), 'accounting');
}

try {
    $stmt = $conn->prepare('SELECT id, account_number, name, account_type FROM acc_ledger_accounts ORDER BY account_type, name');
    $stmt->execute();
    $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $accounts = [];
    logError('Error fetching accounts: ' . $e->getMessage(), 'accounting');
}

try {
    $stmt = $conn->prepare('SELECT id, name FROM acc_vendors ORDER BY name');
    $stmt->execute();
    $vendors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $vendors = [];
    logError('Error fetching vendors: ' . $e->getMessage(), 'accounting');
}

$heroTotals = [
    'income' => $totals['income'] ?? 0,
    'expenses' => $totals['expenses'] ?? 0,
    'net' => $totals['net_balance'] ?? 0,
    'count' => $totals['transaction_count'] ?? count($transactions)
];

$formatDateChip = static function ($value) {
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('M j, Y', $timestamp) : $value;
};

$transactionHeroHighlights = [
    [
        'label' => 'Income',
        'value' => '$' . number_format($heroTotals['income'], 2),
        'meta' => 'Filtered total'
    ],
    [
        'label' => 'Expenses',
        'value' => '$' . number_format($heroTotals['expenses'], 2),
        'meta' => 'Filtered total'
    ],
    [
        'label' => 'Net',
        'value' => '$' . number_format($heroTotals['net'], 2),
        'meta' => 'Income - expenses'
    ],
    [
        'label' => 'Entries',
        'value' => number_format($heroTotals['count']),
        'meta' => 'Matching filters'
    ],
];

$transactionHeroChips = array_values(array_filter([
    !empty($filters['start_date']) ? 'From ' . $formatDateChip($filters['start_date']) : null,
    !empty($filters['end_date']) ? 'Thru ' . $formatDateChip($filters['end_date']) : null,
    !empty($filters['type']) ? 'Type: ' . ucfirst(strtolower($filters['type'])) : null,
    !empty($filters['category_id']) ? 'Category #' . $filters['category_id'] : null,
    !empty($filters['account_id']) ? 'Account #' . $filters['account_id'] : null,
    !empty($filters['vendor_id']) ? 'Vendor #' . $filters['vendor_id'] : null,
    !empty($filters['search']) ? 'Search: ' . $filters['search'] : null,
]));

if (empty($transactionHeroChips)) {
    $transactionHeroChips[] = 'Scope: All transactions';
}

$exportQuery = array_filter([
    'start_date' => $filters['start_date'] ?? '',
    'end_date' => $filters['end_date'] ?? '',
    'category_id' => $filters['category_id'] ?? '',
    'type' => $filters['type'] ?? '',
    'account_id' => $filters['account_id'] ?? '',
    'vendor_id' => $filters['vendor_id'] ?? '',
    'search' => $filters['search'] ?? '',
], static function ($value) {
    return $value !== null && $value !== '';
});

$exportQuery['export'] = 'csv';
$exportUrl = '/accounting/transactions/transactions.php?' . http_build_query($exportQuery);

$transactionHeroActions = array_values(array_filter([
    $can_add_transactions ? [
        'label' => 'Add Transaction',
        'url' => '/accounting/transactions/add_transaction.php',
        'icon' => 'fa-plus-circle'
    ] : null,
    [
        'label' => 'Export CSV',
        'url' => $exportUrl,
        'variant' => 'outline',
        'icon' => 'fa-file-export'
    ],
    [
        'label' => 'Accounting Manual',
        'url' => '/accounting/manual.php#transactions',
        'variant' => 'outline',
        'icon' => 'fa-book'
    ],
]));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title><?= htmlspecialchars($page_title); ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <?php if (function_exists('renderPremiumHero')): ?>
            <?php renderPremiumHero([
                'eyebrow' => 'Ledger Operations',
                'title' => 'Transactions Workspace',
                'subtitle' => 'Review, post, and reconcile every ledger movement in one unified stream.',
                'description' => 'Use the filters on the right to narrow the ledger, export polished CSVs, or jump into a detailed entry.',
                'theme' => 'midnight',
                'size' => 'compact',
                'media_mode' => 'none',
                'chips' => $transactionHeroChips,
                'highlights' => $transactionHeroHighlights,
                'actions' => $transactionHeroActions,
            ]); ?>
            <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
                <?php if ($can_add_transactions): ?>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                        <i class="fas fa-plus-circle me-1"></i>New Transaction
                    </button>
                <?php endif; ?>
                <a href="/accounting/manual.php#transactions" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-book me-1"></i>Documentation
                </a>
            </div>
        <?php else: ?>
            <section class="hero hero-small mb-4">
                <div class="hero-body py-3">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-2 d-none d-md-flex justify-content-center">
                                <img src="https://w5obm.com/images/badges/club_logo.png" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                            </div>
                            <div class="col-md-6 text-center text-md-start text-white">
                                <h1 class="h4 mb-1">Transaction Management</h1>
                                <p class="mb-0 small">Review, post, and reconcile every ledger movement</p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <?php if ($can_add_transactions): ?>
                                    <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                        <i class="fas fa-plus-circle me-1"></i>New Transaction
                                    </button>
                                <?php endif; ?>
                                <a href="/accounting/manual.php#transactions" class="btn btn-outline-light btn-sm">
                                    <i class="fas fa-book me-1"></i>Documentation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    <div class="row mb-3 hero-summary-row">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Total Income</span>
                    <i class="fas fa-arrow-trend-up text-success"></i>
                </div>
                <h4 class="mb-0">$<?= number_format($heroTotals['income'], 2) ?></h4>
                <small class="text-muted">Across all filters</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Total Expenses</span>
                    <i class="fas fa-arrow-trend-down text-danger"></i>
                </div>
                <h4 class="mb-0">$<?= number_format($heroTotals['expenses'], 2) ?></h4>
                <small class="text-muted">Operational + capital</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Net Position</span>
                    <i class="fas fa-scale-balanced text-primary"></i>
                </div>
                <h4 class="mb-0 text-<?= $heroTotals['net'] >= 0 ? 'success' : 'danger' ?>">
                    $<?= number_format($heroTotals['net'], 2) ?>
                </h4>
                <small class="text-muted">Income minus expenses</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Total Entries</span>
                    <i class="fas fa-list text-info"></i>
                </div>
                <h4 class="mb-0"><?= number_format($heroTotals['count']) ?></h4>
                <small class="text-muted">Filter-adjusted count</small>
            </div>
        </div>
    </div>

    <?php if (function_exists('displayToastMessage')): ?>
        <?php displayToastMessage(); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-3 mb-4">
            <nav class="bg-light border rounded h-100 p-0 shadow-sm">
                <div class="px-3 py-2 border-bottom">
                    <span class="text-muted text-uppercase small">Workspace</span>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/accounting/dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-pie me-2 text-primary"></i>Dashboard</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/transactions/transactions.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center active">
                        <span><i class="fas fa-exchange-alt me-2 text-success"></i>Transactions</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/reports/reports_dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-bar me-2 text-info"></i>Reports</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/ledger/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-book me-2 text-warning"></i>Chart of Accounts</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/categories/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-tags me-2 text-secondary"></i>Categories</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/vendors/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-store me-2 text-danger"></i>Vendors</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <div class="list-group-item small text-muted text-uppercase">Other</div>
                    <a href="/accounting/assets/" class="list-group-item list-group-item-action">
                        <i class="fas fa-boxes me-2"></i>Assets
                    </a>
                    <a href="/accounting/donations/" class="list-group-item list-group-item-action">
                        <i class="fas fa-heart me-2"></i>Donations
                    </a>
                </div>
            </nav>
        </div>

        <div class="col-lg-9 mb-4">
            <?php
            renderTransactionList(
                $transactions,
                $filters,
                $categories,
                $accounts,
                $vendors,
                $totals,
                $pagination,
                $can_add_transactions,
                $can_manage_transactions
            );
            ?>
        </div>
    </div>
</div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>