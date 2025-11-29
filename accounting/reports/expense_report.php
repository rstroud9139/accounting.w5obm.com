<?php

/**
 * Expense Report - W5OBM Accounting System
 * File: /accounting/reports/expense_report.php
 * Purpose: Generate detailed expense reports by category and time period
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../../include/report_header.php';
require_once __DIR__ . '/../../include/premium_hero.php';

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

$page_title = "Expense Report - W5OBM Accounting";

// Get report parameters
$start_date = sanitizeInput($_GET['start_date'] ?? date('Y-m-01'), 'string');
$end_date = sanitizeInput($_GET['end_date'] ?? date('Y-m-d'), 'string');
$category_id = sanitizeInput($_GET['category_id'] ?? '', 'int');
$generate_report = isset($_GET['generate']) && $_GET['generate'] === '1';

// Handle PDF export (placeholder)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    setToastMessage('info', 'PDF Export', 'PDF export functionality will be implemented soon.', 'club-logo');
}

// Generate report data if requested
$report_data = null;
$expense_breakdown = null;

if ($generate_report) {
    try {
        // Validate date range
        if (!strtotime($start_date) || !strtotime($end_date)) {
            throw new Exception("Invalid date range provided.");
        }

        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception("Start date cannot be after end date.");
        }

        // Get expense breakdown
        $expense_breakdown = getExpenseBreakdown($start_date, $end_date);

        // Get detailed expense transactions
        $filters = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'type' => 'Expense'
        ];

        if (!empty($category_id)) {
            $filters['category_id'] = $category_id;
        }

        require_once __DIR__ . '/../controllers/transactionController.php';
        $expense_transactions = getAllTransactions($filters);

        $report_data = [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'display' => date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))
            ],
            'transactions' => $expense_transactions,
            'breakdown' => $expense_breakdown,
            'total_expenses' => $expense_breakdown['total_expenses'] ?? 0
        ];

        // Save report record
        $report_params = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'category_id' => $category_id
        ];
        saveReport('expense_report', $report_params);
    } catch (Exception $e) {
        setToastMessage('danger', 'Report Error', $e->getMessage(), 'club-logo');
        logError("Error generating expense report: " . $e->getMessage(), 'accounting');
    }
}

// Standardized export handling for CSV/Excel via admin shared exporter
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    try {
        if (!$report_data) {
            $expense_breakdown = getExpenseBreakdown($start_date, $end_date);
        }

        $section = $_GET['section'] ?? 'categories';
        $rows = [];
        $meta_title = '';
        if ($section === 'categories') {
            foreach ($expense_breakdown['categories'] as $cat) {
                $rows[] = [
                    'Category' => (string)$cat['category_name'],
                    'Total' => number_format((float)$cat['total_amount'], 2, '.', ''),
                    'Transactions' => (string)$cat['transaction_count'],
                    'Average' => number_format((float)$cat['average_amount'], 2, '.', ''),
                    'Percent' => number_format((float)$cat['percentage'], 1, '.', '')
                ];
            }
            $meta_title = 'Expense Report - Category Breakdown';
        } else {
            $rows[] = [
                'Total Expenses' => number_format((float)($expense_breakdown['total_expenses'] ?? 0), 2, '.', ''),
                'Period' => date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))
            ];
            $meta_title = 'Expense Report - Summary';
        }

        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['Period', date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))]
        ];

        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('expense_report_' . $section, $rows, $report_meta);
    } catch (Exception $e) {
        setToastMessage('danger', 'Export Error', $e->getMessage(), 'club-logo');
        logError('Error exporting expense report: ' . $e->getMessage(), 'accounting');
    }
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

$selectedCategoryName = null;
if (!empty($category_id)) {
    foreach ($categories as $category) {
        if ((int)$category['id'] === (int)$category_id) {
            $selectedCategoryName = $category['name'];
            break;
        }
    }
}

$expensePeriodDisplay = $report_data['period']['display'] ?? (date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)));
$expenseReportTotal = (float)($report_data['total_expenses'] ?? 0);
$expenseReportCategories = !empty($expense_breakdown['categories']) ? count($expense_breakdown['categories']) : 0;
$expenseReportTransactions = isset($report_data['transactions']) && is_array($report_data['transactions']) ? count($report_data['transactions']) : 0;
$expenseReportStatus = $generate_report && $report_data ? 'Status: Generated' : 'Status: Awaiting run';

$expenseReportHeroChips = array_filter([
    'Period: ' . $expensePeriodDisplay,
    $selectedCategoryName ? 'Category: ' . $selectedCategoryName : ($category_id ? 'Category: #' . $category_id : 'Category: All'),
    $expenseReportStatus,
]);

$expenseReportHeroHighlights = [
    [
        'label' => 'Total Expenses',
        'value' => '$' . number_format($expenseReportTotal, 2),
        'meta' => 'Across selected period',
    ],
    [
        'label' => 'Categories',
        'value' => number_format($expenseReportCategories),
        'meta' => 'With activity',
    ],
    [
        'label' => 'Transactions',
        'value' => number_format($expenseReportTransactions),
        'meta' => 'Detailed rows',
    ],
];

$expenseReportHeroActions = [
    [
        'label' => 'Reports Dashboard',
        'url' => '/accounting/reports/reports_dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-chart-line',
    ],
    [
        'label' => 'Export CSV',
        'url' => '?'. http_build_query(array_merge($_GET, ['export' => 'csv', 'section' => 'categories'])),
        'variant' => 'outline',
        'icon' => 'fa-file-csv',
    ],
    [
        'label' => 'Accounting Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left',
    ],
];

$expenseReportHeroConfig = [
    'eyebrow' => 'Spending Intelligence',
    'title' => 'Expense Report',
    'subtitle' => 'Spot cost drivers, evaluate categories, and export results fast.',
    'chips' => $expenseReportHeroChips,
    'highlights' => $expenseReportHeroHighlights,
    'actions' => $expenseReportHeroActions,
    'theme' => 'crimson',
    'size' => 'compact',
    'media_mode' => 'none',
];

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

    <?php if (function_exists('renderPremiumHero')) {
        renderPremiumHero($expenseReportHeroConfig);
    } ?>

    <!-- Page Container -->
    <div class="page-container">
        <?php if (!function_exists('renderPremiumHero')): ?>
            <?php $fallbackLogo = accounting_logo_src_for(__DIR__); ?>
            <section class="hero hero-small mb-4">
                <div class="hero-body py-3">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-2 d-none d-md-flex justify-content-center">
                                <img src="<?= htmlspecialchars($fallbackLogo); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                            </div>
                            <div class="col-md-6 text-center text-md-start text-white">
                                <h1 class="h4 mb-1">Expense Report</h1>
                                <p class="mb-0 small">Detailed expense analysis by category and time period.</p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <a href="/accounting/reports/reports_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                                    <i class="fas fa-chart-line me-1"></i>Reports Dashboard
                                </a>
                                <a href="/accounting/dashboard.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Accounting Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

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

                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control form-control-lg" id="start_date" name="start_date"
                            value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control form-control-lg" id="end_date" name="end_date"
                            value="<?= htmlspecialchars($end_date) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="category_id" class="form-label">Category (Optional)</label>
                        <select class="form-select form-control-lg" id="category_id" name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"
                                    <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
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
                                    <a class="btn btn-outline-success" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'section' => 'categories'])) ?>"><i class="fas fa-file-csv me-1"></i>Categories CSV</a>
                                    <a class="btn btn-outline-primary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel', 'section' => 'categories'])) ?>"><i class="fas fa-file-excel me-1"></i>Categories Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'section' => 'summary'])) ?>"><i class="fas fa-file-csv me-1"></i>Summary CSV</a>
                                    <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel', 'section' => 'summary'])) ?>"><i class="fas fa-file-excel me-1"></i>Summary Excel</a>
                                </div>
                                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>" class="btn btn-danger btn-sm">
                                    <i class="fas fa-file-pdf me-1"></i>PDF
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report_data): ?>
            <!-- Report Results -->
            <div class="card shadow mb-4" id="reportContent">
                <div class="card-header bg-danger text-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-0">Expense Report Results</h4>
                            <small><?= htmlspecialchars($report_data['period']['display']) ?></small>
                        </div>
                        <div class="col-auto">
                            <h4 class="mb-0">Total: $<?= number_format($report_data['total_expenses'], 2) ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Expense Breakdown by Category -->
                <?php if (!empty($expense_breakdown['categories'])): ?>
                    <div class="card-body">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-pie me-2 text-danger"></i>Expense Breakdown by Category
                        </h5>

                        <div class="row">
                            <?php foreach ($expense_breakdown['categories'] as $category): ?>
                                <div class="col-lg-6 mb-3">
                                    <div class="card border">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <h6 class="mb-1"><?= htmlspecialchars($category['category_name']) ?></h6>
                                                    <div class="progress mb-2" style="height: 10px;">
                                                        <div class="progress-bar bg-danger" style="width: <?= $category['percentage'] ?>%"></div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= $category['transaction_count'] ?> transactions •
                                                        Avg: $<?= number_format($category['average_amount'], 2) ?>
                                                    </small>
                                                </div>
                                                <div class="col-auto">
                                                    <div class="text-end">
                                                        <div class="fw-bold fs-5 text-danger">$<?= number_format($category['total_amount'], 2) ?></div>
                                                        <small class="text-muted"><?= $category['percentage'] ?>%</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Detailed Transaction List -->
                <?php if (!empty($report_data['transactions'])): ?>
                    <div class="card-footer">
                        <h5 class="mb-3">
                            <i class="fas fa-list me-2 text-primary"></i>Detailed Expense Transactions
                        </h5>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['transactions'] as $transaction): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></td>
                                            <td><?= htmlspecialchars($transaction['category_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($transaction['description']) ?></div>
                                                <?php if (!empty($transaction['notes'])): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($transaction['notes']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($transaction['reference_number'] ?? '-') ?></small>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-bold text-danger">$<?= number_format($transaction['amount'], 2) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="4" class="text-end">Total Expenses:</th>
                                        <th class="text-end text-danger">$<?= number_format($report_data['total_expenses'], 2) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
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
            }

            .card-header {
                background: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Date validation
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');

            function validateDates() {
                if (startDate.value && endDate.value) {
                    if (new Date(startDate.value) > new Date(endDate.value)) {
                        endDate.setCustomValidity('End date must be after start date');
                    } else {
                        endDate.setCustomValidity('');
                    }
                }
            }

            startDate.addEventListener('change', validateDates);
            endDate.addEventListener('change', validateDates);
        });
    </script>
</body>

</html>