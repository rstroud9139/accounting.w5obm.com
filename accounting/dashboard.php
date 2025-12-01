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
require_once __DIR__ . '/include/quick_post_widget.php';
require_once __DIR__ . '/utils/csrf.php';
require_once __DIR__ . '/utils/quick_post.php';
require_once __DIR__ . '/lib/dashboard_metrics.php';

$db = accounting_db_connection();

$cash_balance = 0.00;
$asset_value = 0.00;
$asset_current_value = 0.00;
$asset_summary = [
    'count' => 0,
    'book_total' => 0.0,
    'current_total' => 0.0,
    'depreciable_count' => 0,
    'avg_age_years' => 0.0,
];
$month_totals = ['income' => 0.00, 'expenses' => 0.00, 'net_balance' => 0.00];
$ytd_totals = ['income' => 0.00, 'expenses' => 0.00, 'net_balance' => 0.00];
$recent_transactions = [];
$report_insights = ['total' => 0, 'recent_30' => 0, 'last_generated_at' => null];
$page_title = 'Accounting Dashboard - W5OBM';
$user_id = function_exists('getCurrentUserId') ? getCurrentUserId() : ($_SESSION['user_id'] ?? null);
csrf_ensure_token();

$metrics = accounting_collect_dashboard_metrics($db, $db, 25);
$cash_balance = $metrics['cash_balance'];
$asset_value = $metrics['asset_value'];
$asset_summary = $metrics['asset_summary'] ?? $asset_summary;
if (($asset_summary['count'] ?? 0) > 0) {
    $asset_value = $asset_summary['book_total'];
}
$asset_current_value = $asset_summary['current_total'] ?? 0.0;
$month_totals = $metrics['month_totals'];
$ytd_totals = $metrics['ytd_totals'];
$recent_transactions = $metrics['recent_transactions'];
$report_insights = $metrics['reports'];
$monthly_burn_rate = (float)($month_totals['expenses'] ?? 0.0);
$cash_runway_months = $monthly_burn_rate > 0.0 ? ($cash_balance / $monthly_burn_rate) : null;
$operating_margin_percent = ($month_totals['income'] ?? 0.0) > 0.0
    ? ($month_totals['net_balance'] / max($month_totals['income'], 0.01)) * 100
    : null;
$report_last_generated_label = !empty($report_insights['last_generated_at'])
    ? date('M j, Y g:ia', strtotime($report_insights['last_generated_at']))
    : 'Not yet generated';
$reports_recent_30 = (int)($report_insights['recent_30'] ?? 0);
$reports_total = (int)($report_insights['total'] ?? 0);
$reports_recent_badge_class = $reports_recent_30 > 0 ? 'bg-success' : 'bg-warning text-dark';
$cash_runway_label = $cash_runway_months !== null ? number_format($cash_runway_months, 1) . ' months' : 'Need expense data';
$cash_runway_badge_class = 'bg-secondary';
if ($cash_runway_months !== null) {
    $cash_runway_badge_class = $cash_runway_months >= 3
        ? 'bg-success'
        : ($cash_runway_months >= 1 ? 'bg-warning text-dark' : 'bg-danger');
}
$month_trend_positive = ($month_totals['net_balance'] ?? 0.0) >= 0.0;
$month_trend_badge_class = $month_trend_positive ? 'bg-success' : 'bg-danger';
$month_trend_badge_label = $month_trend_positive ? 'Running Surplus' : 'Watch Burn';
$operating_margin_label = $operating_margin_percent !== null ? number_format($operating_margin_percent, 1) . '%' : 'N/A';
$operating_margin_badge_class = ($operating_margin_percent ?? 0.0) >= 0 ? 'bg-success' : 'bg-danger';
$quickPostContext = accounting_quick_cash_resolve_context($db);
$quickPostCategories = $quickPostContext['categories'] ?? [];
$quickPostAccounts = $quickPostContext['accounts'] ?? [];
$quickPostDefaultCategoryId = (int)($quickPostContext['default_category_id'] ?? 0);
$quickPostDefaultAccountId = (int)($quickPostContext['default_account_id'] ?? 0);
$quickPostContactName = $quickPostContext['contact_name'] ?? accounting_quick_cash_contact_name();
$quickPostReady = !empty($quickPostContext['contact_id'])
    && !empty($quickPostContext['default_category_id'])
    && !empty($quickPostContext['default_account_id']);
$quickPostToday = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php require __DIR__ . '/../include/header.php'; ?>
</head>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../include/menu.php'; ?>

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
            'actions' => [
                [
                    'label' => 'Transactions Workspace',
                    'url' => '/accounting/transactions/transactions.php',
                    'icon' => 'fa-table'
                ],
                [
                    'label' => 'View Reports',
                    'url' => '/accounting/reports_dashboard.php',
                    'variant' => 'outline',
                    'icon' => 'fa-chart-line'
                ]
            ],
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
                    <small class="text-muted">Physical assets (book)</small>
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

        <?php if (($asset_summary['count'] ?? 0) > 0): ?>
            <?php
            $assetDepreciableLabel = number_format($asset_summary['depreciable_count']) . ' / ' . number_format(max(1, $asset_summary['count']));
            $assetAvgAgeLabel = $asset_summary['avg_age_years'] > 0
                ? number_format($asset_summary['avg_age_years'], 1) . ' yrs'
                : '—';
            ?>
            <div class="row g-3 mb-4 asset-summary-row">
                <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Current Value</span>
                            <i class="fas fa-gem text-success"></i>
                        </div>
                        <h4 class="mb-0">$<?= number_format($asset_current_value, 2) ?></h4>
                        <small class="text-muted">Straight-line depreciation</small>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Depreciable Coverage</span>
                            <i class="fas fa-percentage text-warning"></i>
                        </div>
                        <h4 class="mb-0"><?= $assetDepreciableLabel ?></h4>
                        <small class="text-muted">Depreciable / total assets</small>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Average Age</span>
                            <i class="fas fa-hourglass-half text-secondary"></i>
                        </div>
                        <h4 class="mb-0"><?= $assetAvgAgeLabel ?></h4>
                        <small class="text-muted">Across <?= number_format($asset_summary['count']) ?> assets</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4 control-center-grid">
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-header bg-white border-0 pb-1">
                        <p class="text-uppercase text-muted small mb-1">Control Center</p>
                        <h6 class="mb-0">Report Readiness</h6>
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Reports on file</span>
                            <strong><?= number_format($reports_total) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Runs (30 days)</span>
                            <span class="badge <?= $reports_recent_badge_class ?>">
                                <?= number_format($reports_recent_30) ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <span class="text-muted small d-block">Last generated</span>
                            <strong><?= htmlspecialchars($report_last_generated_label, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <a class="btn btn-outline-primary btn-sm w-100" href="/accounting/reports_dashboard.php">
                            <i class="fas fa-chart-line me-1"></i>Launch Reports Workspace
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-header bg-white border-0 pb-1">
                        <p class="text-uppercase text-muted small mb-1">Liquidity</p>
                        <h6 class="mb-0">Cash Runway</h6>
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge <?= $cash_runway_badge_class ?> me-2"><?= htmlspecialchars($cash_runway_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="text-muted small">Based on current month burn</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="text-muted small d-block">Cash on hand</span>
                                <strong>$<?= number_format($cash_balance, 2) ?></strong>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small d-block">Monthly burn</span>
                                <strong>$<?= number_format($monthly_burn_rate, 2) ?></strong>
                            </div>
                        </div>
                        <small class="text-muted">Use the Transactions workspace to confirm fund designations before large draws.</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-header bg-white border-0 pb-1">
                        <p class="text-uppercase text-muted small mb-1">Performance</p>
                        <h6 class="mb-0">Operating Pulse</h6>
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge <?= $month_trend_badge_class ?> me-2"><?= $month_trend_badge_label ?></span>
                            <span class="text-muted small">Month-to-date net</span>
                        </div>
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Monthly net</span>
                                <strong class="text-<?= $month_trend_positive ? 'success' : 'danger' ?>">
                                    $<?= number_format($month_totals['net_balance'], 2) ?>
                                </strong>
                            </li>
                            <li class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">YTD net</span>
                                <strong>$<?= number_format($ytd_totals['net_balance'], 2) ?></strong>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Operating margin</span>
                                <span class="badge <?= $operating_margin_badge_class ?>"><?= $operating_margin_label ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3">
                <div class="me-auto">
                    <p class="text-uppercase text-muted small mb-1">Data Imports</p>
                    <h5 class="mb-1">Need to bring in historic QuickBooks or gnuCash data?</h5>
                    <p class="text-muted mb-0">Use the new Imports workspace to upload, map, and validate batches before they touch the ledger.</p>
                </div>
                <a href="/accounting/imports.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-file-import me-2"></i>Open Imports Workspace
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-3">
                <?php accounting_render_workspace_nav('dashboard'); ?>
                <div class="mt-4">
                    <div class="card shadow-sm quick-post-card">
                        <div class="card-header bg-white border-0 pb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-uppercase text-muted small mb-0">Quick Post</p>
                                    <h6 class="mb-0">Cash Donation</h6>
                                </div>
                                <span class="badge bg-success">New</span>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <?php if (!$quickPostReady): ?>
                                <div class="alert alert-warning mb-0">
                                    Configure at least one income category and deposit account to unlock quick-posting for events.
                                </div>
                            <?php else: ?>
                                <form id="quickCashForm"
                                    data-default-account="<?= (int)$quickPostDefaultAccountId; ?>"
                                    data-default-category="<?= (int)$quickPostDefaultCategoryId; ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="donation_date" value="<?= htmlspecialchars($quickPostToday, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="mb-3">
                                        <label for="quickCashAmount" class="form-label">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="quickCashAmount" placeholder="25.00" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="quickCashEvent" class="form-label">Event / Note</label>
                                        <input type="text" class="form-control" name="event_label" id="quickCashEvent" placeholder="Club Event" value="Club Event">
                                    </div>
                                    <div class="mb-3">
                                        <label for="quickCashAccount" class="form-label">Deposit Account</label>
                                        <select class="form-select" name="account_id" id="quickCashAccount">
                                            <?php foreach ($quickPostAccounts as $account): ?>
                                                <option value="<?= (int)$account['id']; ?>" <?= $account['id'] == $quickPostDefaultAccountId ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($account['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="quickCashCategory" class="form-label">Income Category</label>
                                        <select class="form-select" name="category_id" id="quickCashCategory">
                                            <?php foreach ($quickPostCategories as $category): ?>
                                                <?php if (!empty($category['type']) && strtolower($category['type']) !== 'income') continue; ?>
                                                <option value="<?= (int)$category['id']; ?>" <?= $category['id'] == $quickPostDefaultCategoryId ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="quickCashNotes" class="form-label">Internal Memo <span class="text-muted small">(optional)</span></label>
                                        <textarea class="form-control" name="notes" id="quickCashNotes" rows="2" placeholder="Cash box count, volunteer, etc."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-bolt me-1"></i>Post Cash Donation
                                    </button>
                                    <p class="text-muted small mt-2 mb-0">
                                        Receipt contact: <?= htmlspecialchars($quickPostContactName, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                </form>
                                <div id="quickCashStatus" class="mt-2 small"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-9">
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

                <div class="card shadow-sm mb-4">
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

            const quickForm = document.getElementById('quickCashForm');
            if (quickForm) {
                const quickStatus = document.getElementById('quickCashStatus');
                quickForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    if (!quickStatus) {
                        return;
                    }
                    quickStatus.className = 'alert alert-info mt-2';
                    quickStatus.textContent = 'Posting cash donation...';

                    const formData = new FormData(quickForm);
                    fetch('/accounting/donations/quick_cash_post.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        })
                        .then(function(response) {
                            return response.json().then(function(data) {
                                return {
                                    ok: response.ok,
                                    data: data
                                };
                            }).catch(function() {
                                return {
                                    ok: response.ok,
                                    data: {
                                        ok: false,
                                        message: 'Unexpected server response.'
                                    }
                                };
                            });
                        })
                        .then(function(result) {
                            if (result.ok && result.data && result.data.ok) {
                                quickStatus.className = 'alert alert-success mt-2';
                                quickStatus.textContent = result.data.message || 'Cash donation recorded.';
                                quickForm.reset();
                                const defaultAccount = quickForm.dataset.defaultAccount;
                                const defaultCategory = quickForm.dataset.defaultCategory;
                                const accountSelect = quickForm.querySelector('select[name="account_id"]');
                                const categorySelect = quickForm.querySelector('select[name="category_id"]');
                                if (defaultAccount && accountSelect) {
                                    accountSelect.value = defaultAccount;
                                }
                                if (defaultCategory && categorySelect) {
                                    categorySelect.value = defaultCategory;
                                }
                                const eventInput = document.getElementById('quickCashEvent');
                                if (eventInput) {
                                    eventInput.value = 'Club Event';
                                }
                            } else {
                                quickStatus.className = 'alert alert-danger mt-2';
                                quickStatus.textContent = (result.data && result.data.message) ? result.data.message : 'Unable to post cash donation.';
                            }
                        })
                        .catch(function() {
                            quickStatus.className = 'alert alert-danger mt-2';
                            quickStatus.textContent = 'Network error. Please try again.';
                        });
                });
            }
        });
    </script>

    <?php include __DIR__ . '/../include/footer.php'; ?>
</body>

</html>