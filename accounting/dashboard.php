<?php

/**
 * Accounting Dashboard - W5OBM Accounting System
 * File: /accounting/dashboard.php
 * Design: Following W5OBM Modern Website Design Guidelines
 */

require_once __DIR__ . '/../include/header.php';

$cash_balance = 0.00;
$asset_value = 0.00;
$month_totals = ['income' => 0.00, 'expenses' => 0.00, 'net_balance' => 0.00];
$ytd_totals = ['income' => 0.00, 'expenses' => 0.00, 'net_balance' => 0.00];
$recent_transactions = [];

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

<div class="page-container" style="margin-top:0;padding-top:0;">
    <section class="hero hero-small mb-4">
        <div class="hero-body py-3">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-2 d-none d-md-flex justify-content-center">
                        <img src="https://w5obm.com/images/badges/club_logo.png" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                    </div>
                    <div class="col-md-6 text-center text-md-start text-white">
                        <h1 class="h4 mb-1">Accounting Dashboard</h1>
                        <p class="mb-0 small">W5OBM Financial Management System</p>
                    </div>
                    <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                        <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                            <a href="/accounting/transactions/add_transaction.php" class="btn btn-success btn-sm me-2">
                                <i class="fas fa-plus-circle me-1"></i>New Transaction
                            </a>
                        <?php endif; ?>
                        <a href="/accounting/manual.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-book me-1"></i>User Manual
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

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