<?php

/**
 * Accounting Dashboard - W5OBM Accounting System
 * File: /accounting/dashboard.php
 * Design: Following W5OBM Modern Website Design Guidelines
 */

require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/../include/premium_hero.php';

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $query = http_build_query(array_merge(['route' => $name], $params));
        return '/accounting/app/index.php?' . $query;
    }
}

$cash_balance = 0.00;
$asset_value = 0.00;
$month_totals = ['income' => 0.00, 'expenses' => 0.00, 'net_balance' => 0.00];
$ytd_totals = ['income' => 0.00, 'expenses' => 0.00, 'net_balance' => 0.00];
$recent_transactions = [];
$page_title = 'Accounting Dashboard - W5OBM';
$user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : ($_SESSION['user_id'] ?? null);

if (!function_exists('accTableExists')) {
    function accTableExists(mysqli $connection, string $table): bool
    {
        $safeTable = $connection->real_escape_string($table);
        $result = $connection->query("SHOW TABLES LIKE '{$safeTable}'");
        return $result && $result->num_rows > 0;
    }
}

if (isset($accConn) && $accConn instanceof mysqli) {
    $hasAccountsTable = accTableExists($accConn, 'acc_accounts');
    $hasTransactionsTable = accTableExists($accConn, 'acc_transactions');
    $hasCategoriesTable = accTableExists($accConn, 'acc_categories');

    if ($hasAccountsTable) {
        $result = $accConn->query("
            SELECT COALESCE(SUM(current_balance), 0) as total
            FROM acc_accounts
            WHERE account_type = 'Asset'
              AND (account_name LIKE '%Cash%' OR account_name LIKE '%Bank%')
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $cash_balance = (float)$row['total'];
        }

        $result = $accConn->query("
            SELECT COALESCE(SUM(current_balance), 0) as total
            FROM acc_accounts
            WHERE account_type = 'Asset'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $asset_value = (float)$row['total'];
        }
    }

    if ($hasTransactionsTable) {
        $current_month = date('Y-m');
        $result = $accConn->query("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses
            FROM acc_transactions
            WHERE DATE_FORMAT(transaction_date, '%Y-%m') = '$current_month'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $month_totals['income'] = (float)$row['income'];
            $month_totals['expenses'] = (float)$row['expenses'];
            $month_totals['net_balance'] = $month_totals['income'] - $month_totals['expenses'];
        }

        $current_year = date('Y');
        $result = $accConn->query("
            SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses
            FROM acc_transactions
            WHERE YEAR(transaction_date) = $current_year
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $ytd_totals['income'] = (float)$row['income'];
            $ytd_totals['expenses'] = (float)$row['expenses'];
            $ytd_totals['net_balance'] = $ytd_totals['income'] - $ytd_totals['expenses'];
        }

        $categorySelect = $hasCategoriesTable ? 'c.name as category_name' : 'NULL as category_name';
        $categoryJoin = $hasCategoriesTable ? 'LEFT JOIN acc_categories c ON t.category_id = c.id' : '';

        $result = $accConn->query("
            SELECT
                t.id,
                t.transaction_date,
                t.description,
                t.amount,
                t.type,
                {$categorySelect}
            FROM acc_transactions t
            {$categoryJoin}
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT 25
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_transactions[] = $row;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php require __DIR__ . '/../include/header.php'; ?>
    <link rel="stylesheet" href="/accounting/app/assets/accounting.css">
</head>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/app/views/partials/accounting_nav.php'; ?>

    <div class="page-container accounting-dashboard-shell">
    <?php
    renderPremiumHero([
        'eyebrow' => 'Accounting Command',
        'title' => 'Accounting Dashboard',
        'subtitle' => 'Real-time health indicators for the W5OBM Amateur Radio Club.',
        'description' => 'Stay ahead of budget, cash, and compliance with a single mission control built for club leadership.',
        'theme' => 'midnight',
        'chips' => [
            'Budget vs Actual insight',
            'GAAP-ready exports',
            'Role-based security'
        ],
        'actions' => array_filter([
            (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')) ? [
                'label' => 'New Transaction',
                'url' => '/accounting/transactions/add_transaction.php',
                'variant' => 'primary',
                'icon' => 'fa-plus-circle'
            ] : null,
            [
                'label' => 'View Reports',
                'url' => '/accounting/reports_dashboard.php',
                'variant' => 'outline',
                'icon' => 'fa-chart-line'
            ]
        ]),
        'highlights' => [
            [
                'value' => '$' . number_format($cash_balance, 2),
                'label' => 'Cash on Hand',
                'meta' => 'Available funds'
            ],
            [
                'value' => '$' . number_format($month_totals['net_balance'], 2),
                'label' => 'Monthly Net',
                'meta' => date('F Y')
            ],
            [
                'value' => '$' . number_format($ytd_totals['net_balance'], 2),
                'label' => 'Year-to-Date',
                'meta' => 'Operating position'
            ],
        ],
        'slides' => [
            [
                'src' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1100&q=80',
                'alt' => 'Team reviewing financial statements'
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=1100&q=80',
                'alt' => 'Budget planning session'
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1100&q=80',
                'alt' => 'Strategic financial discussion'
            ],
        ]
    ]);
    ?>

    <div class="row mb-3 hero-summary-row">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Cash Balance</span>
                    <i class="fas fa-wallet text-success"></i>
                </div>
                <h4 class="mb-0">$<?= number_format($cash_balance, 2) ?></h4>
                <small class="text-muted">Current available funds</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Asset Value</span>
                    <i class="fas fa-boxes text-info"></i>
                </div>
                <h4 class="mb-0">$<?= number_format($asset_value, 2) ?></h4>
                <small class="text-muted">Physical assets</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Monthly Net</span>
                    <i class="fas fa-calendar-alt text-warning"></i>
                </div>
                <h4 class="mb-0 text-<?= $month_totals['net_balance'] >= 0 ? 'success' : 'danger' ?>">
                    $<?= number_format($month_totals['net_balance'], 2) ?>
                </h4>
                <small class="text-muted"><?= date('F Y') ?></small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">YTD Net</span>
                    <i class="fas fa-chart-line text-primary"></i>
                </div>
                <h4 class="mb-0">$<?= number_format($ytd_totals['net_balance'], 2) ?></h4>
                <small class="text-muted"><?= date('Y') ?> Year-to-Date</small>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Current Month Summary</h5>
                        <small class="text-muted"><?= date('F Y') ?></small>
                    </div>
                    <span class="badge bg-secondary"><i class="fas fa-calendar-day"></i></span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Income</span>
                        <strong>$<?= number_format($month_totals['income'], 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Expenses</span>
                        <strong>$<?= number_format($month_totals['expenses'], 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Net Balance</span>
                        <span class="h5 mb-0 text-<?= $month_totals['net_balance'] >= 0 ? 'success' : 'danger' ?>">
                            $<?= number_format($month_totals['net_balance'], 2) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">YTD Summary</h5>
                        <small class="text-muted">Fiscal Year <?= date('Y') ?></small>
                    </div>
                    <span class="badge bg-primary"><i class="fas fa-chart-area"></i></span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Income</span>
                        <strong>$<?= number_format($ytd_totals['income'], 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Expenses</span>
                        <strong>$<?= number_format($ytd_totals['expenses'], 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Net Balance</span>
                        <span class="h5 mb-0 text-<?= $ytd_totals['net_balance'] >= 0 ? 'success' : 'danger' ?>">
                            $<?= number_format($ytd_totals['net_balance'], 2) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 mb-4">
            <nav class="bg-light border rounded h-100 p-0 shadow-sm">
                <div class="px-3 py-2 border-bottom">
                    <span class="text-muted text-uppercase small">Workspace</span>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                        <a href="/accounting/transactions/add_transaction.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-plus me-2 text-success"></i>Add Transaction</span>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </a>
                    <?php endif; ?>
                    <a href="/accounting/transactions/transactions.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-exchange-alt me-2 text-primary"></i>Transactions</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/reports/reports_dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-bar me-2 text-success"></i>Reports</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/ledger/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-book me-2 text-info"></i>Chart of Accounts</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/categories/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-tags me-2 text-warning"></i>Categories</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <div class="list-group-item small text-muted text-uppercase">
                        Other
                    </div>
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
            <div class="row mb-4">
                <div class="col-12 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2 text-secondary"></i>Recent Transactions
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_transactions)): ?>
                                <div class="table-responsive">
                                    <table id="recentTransactionsTable" class="table table-striped table-hover mb-0 w-100">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Description</th>
                                                <th class="text-end">Amount</th>
                                                <th>Category</th>
                                                <th>Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_transactions as $txn): ?>
                                                <tr class="cursor-pointer" role="button" data-href="/accounting/transactions/transactions.php?focus=<?= (int)$txn['id'] ?>">
                                                    <td><?= htmlspecialchars($txn['transaction_date']) ?></td>
                                                    <td><?= htmlspecialchars($txn['description']) ?></td>
                                                    <td class="text-end">$<?= number_format($txn['amount'], 2) ?></td>
                                                    <td><?= htmlspecialchars($txn['category_name'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $txn['type'] === 'income' ? 'success' : 'danger' ?>">
                                                            <?= ucfirst($txn['type']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No recent transactions found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#recentTransactionsTable tbody tr[data-href]').forEach(function(row) {
            row.addEventListener('click', function() {
                const target = row.getAttribute('data-href');
                if (target) {
                    window.location.href = target;
                }
            });
        });
    });
</script>

    <?php include __DIR__ . '/../include/footer.php'; ?>
</body>

</html>