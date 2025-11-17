<?php

/**
 * Monthly Summary Report - W5OBM Accounting System
 * File: /accounting/reports/monthly_summary.php
 * Purpose: Generate monthly financial summary reports
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../../include/report_header.php';

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

$page_title = "Monthly Summary Report - W5OBM Accounting";

// Get report parameters
$year = sanitizeInput($_GET['year'] ?? date('Y'), 'int');
$month = sanitizeInput($_GET['month'] ?? date('n'), 'int');
$generate_report = isset($_GET['generate']) && $_GET['generate'] === '1';

// Generate report data if requested
$report_data = null;

if ($generate_report) {
    try {
        // Validate inputs
        if ($year < 2000 || $year > date('Y') + 1) {
            throw new Exception("Invalid year provided.");
        }

        if ($month < 1 || $month > 12) {
            throw new Exception("Invalid month provided.");
        }

        // Generate income statement for the month
        $income_statement = generateIncomeStatement($month, $year);

        // Get monthly transaction count by day
        $start_date = sprintf("%04d-%02d-01", $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));

        $stmt = $conn->prepare("
            SELECT 
                DAY(transaction_date) as day,
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) as daily_income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) as daily_expenses,
                COUNT(*) as daily_transactions
            FROM acc_transactions 
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY DAY(transaction_date)
            ORDER BY DAY(transaction_date)
        ");

        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $daily_result = $stmt->get_result();

        $daily_data = [];
        while ($row = $daily_result->fetch_assoc()) {
            $daily_data[intval($row['day'])] = [
                'income' => floatval($row['daily_income']),
                'expenses' => floatval($row['daily_expenses']),
                'transactions' => intval($row['daily_transactions']),
                'net' => floatval($row['daily_income']) - floatval($row['daily_expenses'])
            ];
        }
        $stmt->close();

        // Get top expense categories for the month
        $stmt = $conn->prepare("
            SELECT c.name, SUM(t.amount) as total, COUNT(t.id) as count
            FROM acc_transactions t
            JOIN acc_transaction_categories c ON t.category_id = c.id
            WHERE t.type = 'Expense' AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY total DESC
            LIMIT 5
        ");

        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $top_expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get top income categories for the month
        $stmt = $conn->prepare("
            SELECT c.name, SUM(t.amount) as total, COUNT(t.id) as count
            FROM acc_transactions t
            JOIN acc_transaction_categories c ON t.category_id = c.id
            WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY total DESC
            LIMIT 5
        ");

        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $top_income = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Calculate previous month for comparison
        $prev_month = $month == 1 ? 12 : $month - 1;
        $prev_year = $month == 1 ? $year - 1 : $year;
        $prev_income_statement = generateIncomeStatement($prev_month, $prev_year);

        $report_data = [
            'period' => [
                'year' => $year,
                'month' => $month,
                'display' => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'income_statement' => $income_statement,
            'previous_month' => $prev_income_statement,
            'daily_data' => $daily_data,
            'top_expenses' => $top_expenses,
            'top_income' => $top_income
        ];

        // Save report record
        $report_params = [
            'year' => $year,
            'month' => $month
        ];
        saveReport('monthly_summary', $report_params);
    } catch (Exception $e) {
        setToastMessage('danger', 'Report Error', $e->getMessage(), 'club-logo');
        logError("Error generating monthly summary: " . $e->getMessage(), 'accounting');
    }
}

// Standardized export handling for CSV/Excel via admin shared exporter
if (isset($_GET['export'])) {
    try {
        if ($year < 2000 || $year > date('Y') + 1) {
            throw new Exception('Invalid year provided.');
        }
        if ($month < 1 || $month > 12) {
            throw new Exception('Invalid month provided.');
        }

        if (!$report_data) {
            // Generate the same data this page would for export context
            $income_statement = generateIncomeStatement($month, $year);
            $start_date = sprintf("%04d-%02d-01", $year, $month);
            $end_date = date('Y-m-t', strtotime($start_date));

            // Daily data
            $stmt = $conn->prepare("\n            SELECT \n                DAY(transaction_date) as day,\n                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) as daily_income,\n                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) as daily_expenses,\n                COUNT(*) as daily_transactions\n            FROM acc_transactions \n            WHERE transaction_date BETWEEN ? AND ?\n            GROUP BY DAY(transaction_date)\n            ORDER BY DAY(transaction_date)\n        ");
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $daily_result = $stmt->get_result();
            $daily_data = [];
            while ($row = $daily_result->fetch_assoc()) {
                $d = (int)$row['day'];
                $daily_data[$d] = [
                    'income' => (float)$row['daily_income'],
                    'expenses' => (float)$row['daily_expenses'],
                    'transactions' => (int)$row['daily_transactions'],
                    'net' => (float)$row['daily_income'] - (float)$row['daily_expenses']
                ];
            }
            $stmt->close();

            // Top categories
            $stmt = $conn->prepare("\n            SELECT c.name, SUM(t.amount) as total, COUNT(t.id) as count\n            FROM acc_transactions t\n            JOIN acc_transaction_categories c ON t.category_id = c.id\n            WHERE t.type = 'Expense' AND t.transaction_date BETWEEN ? AND ?\n            GROUP BY c.id, c.name\n            ORDER BY total DESC\n            LIMIT 5\n        ");
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $top_expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmt = $conn->prepare("\n            SELECT c.name, SUM(t.amount) as total, COUNT(t.id) as count\n            FROM acc_transactions t\n            JOIN acc_transaction_categories c ON t.category_id = c.id\n            WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?\n            GROUP BY c.id, c.name\n            ORDER BY total DESC\n            LIMIT 5\n        ");
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $top_income = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $report_data = [
                'period' => [
                    'display' => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'income_statement' => $income_statement,
                'daily_data' => $daily_data,
                'top_expenses' => $top_expenses,
                'top_income' => $top_income
            ];
        }

        $section = $_GET['section'] ?? 'summary';
        $rows = [];
        $meta_title = '';

        switch ($section) {
            case 'daily':
                for ($d = 1; $d <= 31; $d++) {
                    if (!isset($report_data['daily_data'][$d])) continue;
                    $r = $report_data['daily_data'][$d];
                    $rows[] = [
                        'Day' => (string)$d,
                        'Income' => number_format((float)$r['income'], 2, '.', ''),
                        'Expenses' => number_format((float)$r['expenses'], 2, '.', ''),
                        'Net' => number_format((float)$r['net'], 2, '.', ''),
                        'Transactions' => (string)$r['transactions']
                    ];
                }
                $meta_title = 'Monthly Summary - Daily Breakdown';
                break;
            case 'top_income':
                foreach ($report_data['top_income'] as $row) {
                    $rows[] = [
                        'Category' => (string)$row['name'],
                        'Amount' => number_format((float)$row['total'], 2, '.', ''),
                        'Transactions' => (string)$row['count']
                    ];
                }
                $meta_title = 'Monthly Summary - Top Income Categories';
                break;
            case 'top_expenses':
                foreach ($report_data['top_expenses'] as $row) {
                    $rows[] = [
                        'Category' => (string)$row['name'],
                        'Amount' => number_format((float)$row['total'], 2, '.', ''),
                        'Transactions' => (string)$row['count']
                    ];
                }
                $meta_title = 'Monthly Summary - Top Expense Categories';
                break;
            case 'summary':
            default:
                $rows[] = [
                    'Total Income' => number_format((float)($report_data['income_statement']['income']['total'] ?? 0), 2, '.', ''),
                    'Total Expenses' => number_format((float)($report_data['income_statement']['expenses']['total'] ?? 0), 2, '.', ''),
                    'Net Income' => number_format((float)($report_data['income_statement']['net_income'] ?? 0), 2, '.', ''),
                    'Transactions' => (string)array_sum(array_column($report_data['daily_data'], 'transactions'))
                ];
                $meta_title = 'Monthly Summary - Overview';
                break;
        }

        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['Period', (string)($report_data['period']['display'] ?? '')]
        ];

        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('monthly_summary_' . $section, $rows, $report_meta);
    } catch (Exception $e) {
        setToastMessage('danger', 'Export Error', $e->getMessage(), 'club-logo');
        logError('Error exporting monthly summary: ' . $e->getMessage(), 'accounting');
    }
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
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Monthly Summary Report</h3>
                        <small>Comprehensive monthly financial analysis</small>
                    </div>
                    <div class="col-auto">
                        <a href="/accounting/reports/reports_dashboard.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Parameters -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2 text-primary"></i>Report Parameters
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="generate" value="1">

                    <div class="col-md-6">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-select form-control-lg" id="year" name="year" required>
                            <?php for ($y = date('Y') + 1; $y >= 2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-select form-control-lg" id="month" name="month" required>
                            <?php
                            $months = [
                                1 => 'January',
                                2 => 'February',
                                3 => 'March',
                                4 => 'April',
                                5 => 'May',
                                6 => 'June',
                                7 => 'July',
                                8 => 'August',
                                9 => 'September',
                                10 => 'October',
                                11 => 'November',
                                12 => 'December'
                            ];
                            foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-lg me-2">
                            <i class="fas fa-chart-bar me-1"></i>Generate Report
                        </button>

                        <?php if ($report_data): ?>
                            <button type="button" class="btn btn-success btn-lg me-2" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print Report
                            </button>
                            <div class="d-inline-flex align-items-center no-print" role="group" aria-label="Export buttons">
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-secondary" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=csv&section=summary"><i class="fas fa-file-csv me-1"></i>Summary CSV</a>
                                    <a class="btn btn-outline-primary" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=excel&section=summary"><i class="fas fa-file-excel me-1"></i>Summary Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-info" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=csv&section=daily"><i class="fas fa-file-csv me-1"></i>Daily CSV</a>
                                    <a class="btn btn-outline-info" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=excel&section=daily"><i class="fas fa-file-excel me-1"></i>Daily Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-success" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=csv&section=top_income"><i class="fas fa-file-csv me-1"></i>Top Income CSV</a>
                                    <a class="btn btn-outline-success" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=excel&section=top_income"><i class="fas fa-file-excel me-1"></i>Top Income Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-danger" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=csv&section=top_expenses"><i class="fas fa-file-csv me-1"></i>Top Expenses CSV</a>
                                    <a class="btn btn-outline-danger" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=excel&section=top_expenses"><i class="fas fa-file-excel me-1"></i>Top Expenses Excel</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report_data): ?>
            <!-- Report Results -->
            <div id="reportContent">
                <!-- Monthly Overview -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            <?= htmlspecialchars($report_data['period']['display']) ?> Summary
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-arrow-up fa-2x text-success mb-2"></i>
                                    <h4 class="text-success mb-0">
                                        $<?= number_format($report_data['income_statement']['income']['total'], 2) ?>
                                    </h4>
                                    <small class="text-muted">Total Income</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-danger bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-arrow-down fa-2x text-danger mb-2"></i>
                                    <h4 class="text-danger mb-0">
                                        $<?= number_format($report_data['income_statement']['expenses']['total'], 2) ?>
                                    </h4>
                                    <small class="text-muted">Total Expenses</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-<?= $report_data['income_statement']['net_income'] >= 0 ? 'primary' : 'warning' ?> bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-balance-scale fa-2x text-<?= $report_data['income_statement']['net_income'] >= 0 ? 'primary' : 'warning' ?> mb-2"></i>
                                    <h4 class="text-<?= $report_data['income_statement']['net_income'] >= 0 ? 'primary' : 'warning' ?> mb-0">
                                        $<?= number_format($report_data['income_statement']['net_income'], 2) ?>
                                    </h4>
                                    <small class="text-muted">Net Income</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="fas fa-exchange-alt fa-2x text-info mb-2"></i>
                                    <h4 class="text-info mb-0">
                                        <?= array_sum(array_column($report_data['daily_data'], 'transactions')) ?>
                                    </h4>
                                    <small class="text-muted">Total Transactions</small>
                                </div>
                            </div>
                        </div>

                        <!-- Month vs Previous Month Comparison -->
                        <?php if (!empty($report_data['previous_month'])): ?>
                            <hr class="my-4">
                            <h6 class="mb-3">
                                <i class="fas fa-chart-area me-2 text-secondary"></i>Month-over-Month Comparison
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-between">
                                        <span>Income Change:</span>
                                        <?php
                                        $income_change = $report_data['income_statement']['income']['total'] - $report_data['previous_month']['income']['total'];
                                        $income_percent = $report_data['previous_month']['income']['total'] > 0 ?
                                            ($income_change / $report_data['previous_month']['income']['total']) * 100 : 0;
                                        ?>
                                        <span class="fw-bold text-<?= $income_change >= 0 ? 'success' : 'danger' ?>">
                                            <?= $income_change >= 0 ? '+' : '' ?>$<?= number_format($income_change, 2) ?>
                                            (<?= $income_change >= 0 ? '+' : '' ?><?= number_format($income_percent, 1) ?>%)
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-between">
                                        <span>Expense Change:</span>
                                        <?php
                                        $expense_change = $report_data['income_statement']['expenses']['total'] - $report_data['previous_month']['expenses']['total'];
                                        $expense_percent = $report_data['previous_month']['expenses']['total'] > 0 ?
                                            ($expense_change / $report_data['previous_month']['expenses']['total']) * 100 : 0;
                                        ?>
                                        <span class="fw-bold text-<?= $expense_change <= 0 ? 'success' : 'danger' ?>">
                                            <?= $expense_change >= 0 ? '+' : '' ?>$<?= number_format($expense_change, 2) ?>
                                            (<?= $expense_change >= 0 ? '+' : '' ?><?= number_format($expense_percent, 1) ?>%)
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-between">
                                        <span>Net Change:</span>
                                        <?php
                                        $net_change = $report_data['income_statement']['net_income'] - $report_data['previous_month']['net_income'];
                                        ?>
                                        <span class="fw-bold text-<?= $net_change >= 0 ? 'success' : 'danger' ?>">
                                            <?= $net_change >= 0 ? '+' : '' ?>$<?= number_format($net_change, 2) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Categories -->
                <div class="row mb-4">
                    <!-- Top Income Sources -->
                    <div class="col-lg-6">
                        <div class="card shadow h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>Top Income Sources
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($report_data['top_income'])): ?>
                                    <?php foreach ($report_data['top_income'] as $index => $income): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="badge bg-success me-2"><?= $index + 1 ?></span>
                                                <span class="fw-bold"><?= htmlspecialchars($income['name']) ?></span>
                                                <br><small class="text-muted"><?= $income['count'] ?> transactions</small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success">$<?= number_format($income['total'], 2) ?></div>
                                            </div>
                                        </div>
                                        <?php if ($index < count($report_data['top_income']) - 1): ?>
                                            <hr class="my-2">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No income recorded for this month.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Top Expense Categories -->
                    <div class="col-lg-6">
                        <div class="card shadow h-100">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>Top Expense Categories
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($report_data['top_expenses'])): ?>
                                    <?php foreach ($report_data['top_expenses'] as $index => $expense): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <span class="badge bg-danger me-2"><?= $index + 1 ?></span>
                                                <span class="fw-bold"><?= htmlspecialchars($expense['name']) ?></span>
                                                <br><small class="text-muted"><?= $expense['count'] ?> transactions</small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-danger">$<?= number_format($expense['total'], 2) ?></div>
                                            </div>
                                        </div>
                                        <?php if ($index < count($report_data['top_expenses']) - 1): ?>
                                            <hr class="my-2">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No expenses recorded for this month.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Activity Chart -->
                <?php if (!empty($report_data['daily_data'])): ?>
                    <div class="card shadow">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Daily Financial Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Income</th>
                                            <th>Expenses</th>
                                            <th>Net</th>
                                            <th>Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $days_in_month = date('t', strtotime($report_data['period']['start_date']));
                                        $total_income = 0;
                                        $total_expenses = 0;
                                        $total_transactions = 0;

                                        for ($day = 1; $day <= $days_in_month; $day++):
                                            $data = $report_data['daily_data'][$day] ?? ['income' => 0, 'expenses' => 0, 'transactions' => 0, 'net' => 0];
                                            $total_income += $data['income'];
                                            $total_expenses += $data['expenses'];
                                            $total_transactions += $data['transactions'];
                                        ?>
                                            <tr class="<?= $data['transactions'] > 0 ? '' : 'text-muted' ?>">
                                                <td><?= $day ?></td>
                                                <td class="text-success">
                                                    <?= $data['income'] > 0 ? '$' . number_format($data['income'], 2) : '-' ?>
                                                </td>
                                                <td class="text-danger">
                                                    <?= $data['expenses'] > 0 ? '$' . number_format($data['expenses'], 2) : '-' ?>
                                                </td>
                                                <td class="fw-bold text-<?= $data['net'] >= 0 ? 'success' : 'danger' ?>">
                                                    <?= $data['net'] != 0 ? '$' . number_format($data['net'], 2) : '-' ?>
                                                </td>
                                                <td><?= $data['transactions'] > 0 ? $data['transactions'] : '-' ?></td>
                                            </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr>
                                            <th>Total</th>
                                            <th class="text-success">$<?= number_format($total_income, 2) ?></th>
                                            <th class="text-danger">$<?= number_format($total_expenses, 2) ?></th>
                                            <th class="fw-bold text-<?= ($total_income - $total_expenses) >= 0 ? 'success' : 'danger' ?>">
                                                $<?= number_format($total_income - $total_expenses, 2) ?>
                                            </th>
                                            <th><?= $total_transactions ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <style>
        @media print {
            .page-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .btn,
            .card-header .btn,
            .no-print {
                display: none !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
                break-inside: avoid;
            }

            .card-header {
                background: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
            }

            .row {
                break-inside: avoid;
            }
        }
    </style>
</body>

</html>