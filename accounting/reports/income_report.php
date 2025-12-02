<?php

/**
 * Income Report - W5OBM Accounting System
 * File: /accounting/reports/income_report.php
 * Purpose: Generate detailed income reports by category and time period
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../app/repositories/TransactionRepository.php';
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

$page_title = "Income Report - W5OBM Accounting";

// Get report parameters
$start_date = sanitizeInput($_GET['start_date'] ?? date('Y-m-01'), 'string');
$end_date = sanitizeInput($_GET['end_date'] ?? date('Y-m-d'), 'string');
$category_id = sanitizeInput($_GET['category_id'] ?? '', 'int');
$generate_report = isset($_GET['generate']) && $_GET['generate'] === '1';

// Generate report data if requested
$report_data = null;
$income_breakdown = null;

if ($generate_report) {
    try {
        // Validate date range
        if (!strtotime($start_date) || !strtotime($end_date)) {
            throw new Exception("Invalid date range provided.");
        }

        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception("Start date cannot be after end date.");
        }

        // Get income breakdown
        $stmt = $conn->prepare("
            SELECT c.id, c.name, 
                   SUM(t.amount) AS total_amount,
                   COUNT(t.id) AS transaction_count,
                   AVG(t.amount) AS average_amount
            FROM acc_transactions t
            JOIN acc_transaction_categories c ON t.category_id = c.id
            WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?
            " . (!empty($category_id) ? "AND t.category_id = ?" : "") . "
            GROUP BY c.id, c.name
            ORDER BY total_amount DESC
        ");

        if (!empty($category_id)) {
            $stmt->bind_param('ssi', $start_date, $end_date, $category_id);
        } else {
            $stmt->bind_param('ss', $start_date, $end_date);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $income_categories = [];
        $total_income = 0;

        while ($row = $result->fetch_assoc()) {
            $amount = floatval($row['total_amount']);
            $income_categories[] = [
                'category_id' => $row['id'],
                'category_name' => $row['name'],
                'total_amount' => $amount,
                'transaction_count' => intval($row['transaction_count']),
                'average_amount' => floatval($row['average_amount'])
            ];
            $total_income += $amount;
        }

        // Calculate percentages
        foreach ($income_categories as &$category) {
            $category['percentage'] = $total_income > 0 ?
                round(($category['total_amount'] / $total_income) * 100, 1) : 0;
        }

        $stmt->close();

        $income_breakdown = [
            'categories' => $income_categories,
            'total_income' => $total_income,
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ]
        ];

        // Get detailed income transactions
        $filters = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'type' => 'Income'
        ];

        if (!empty($category_id)) {
            $filters['category_id'] = $category_id;
        }

        $txRepo = new TransactionRepository($conn instanceof mysqli ? $conn : null);
        $income_transactions = $txRepo->findAll($filters);

        $report_data = [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'display' => date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))
            ],
            'transactions' => $income_transactions,
            'breakdown' => $income_breakdown,
            'total_income' => $income_breakdown['total_income'] ?? 0
        ];

        // Save report record
        $report_params = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'category_id' => $category_id
        ];
        saveReport('income_report', $report_params);
    } catch (Exception $e) {
        setToastMessage('danger', 'Report Error', $e->getMessage(), 'club-logo');
        logError("Error generating income report: " . $e->getMessage(), 'accounting');
    }
}

// Standardized export handling for CSV/Excel via admin shared exporter
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    try {
        if (!$report_data) {
            // Build the same data used in the view for consistent exports
            $stmt = $conn->prepare("\n            SELECT c.id, c.name, \n                   SUM(t.amount) AS total_amount,\n                   COUNT(t.id) AS transaction_count,\n                   AVG(t.amount) AS average_amount\n            FROM acc_transactions t\n            JOIN acc_transaction_categories c ON t.category_id = c.id\n            WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?\n            " . (!empty($category_id) ? "AND t.category_id = ?" : "") . "\n            GROUP BY c.id, c.name\n            ORDER BY total_amount DESC\n        ");
            if (!empty($category_id)) {
                $stmt->bind_param('ssi', $start_date, $end_date, $category_id);
            } else {
                $stmt->bind_param('ss', $start_date, $end_date);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $income_categories = [];
            $total_income = 0;
            while ($row = $result->fetch_assoc()) {
                $amount = (float)$row['total_amount'];
                $income_categories[] = [
                    'category_id' => $row['id'],
                    'category_name' => $row['name'],
                    'total_amount' => $amount,
                    'transaction_count' => (int)$row['transaction_count'],
                    'average_amount' => (float)$row['average_amount']
                ];
                $total_income += $amount;
            }
            foreach ($income_categories as &$category) {
                $category['percentage'] = $total_income > 0 ? round(($category['total_amount'] / $total_income) * 100, 1) : 0;
            }
            unset($category);

            $income_breakdown = [
                'categories' => $income_categories,
                'total_income' => $total_income
            ];
        }

        $section = $_GET['section'] ?? 'categories';
        $rows = [];
        $meta_title = '';
        if ($section === 'categories') {
            foreach ($income_breakdown['categories'] as $cat) {
                $rows[] = [
                    'Category' => (string)$cat['category_name'],
                    'Total' => number_format((float)$cat['total_amount'], 2, '.', ''),
                    'Transactions' => (string)$cat['transaction_count'],
                    'Average' => number_format((float)$cat['average_amount'], 2, '.', ''),
                    'Percent' => number_format((float)$cat['percentage'], 1, '.', '')
                ];
            }
            $meta_title = 'Income Report - Category Breakdown';
        } else {
            // Summary row
            $rows[] = [
                'Total Income' => number_format((float)($income_breakdown['total_income'] ?? 0), 2, '.', ''),
                'Period' => date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))
            ];
            $meta_title = 'Income Report - Summary';
        }

        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['Period', date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))]
        ];

        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('income_report_' . $section, $rows, $report_meta);
    } catch (Exception $e) {
        setToastMessage('danger', 'Export Error', $e->getMessage(), 'club-logo');
        logError('Error exporting income report: ' . $e->getMessage(), 'accounting');
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

$reportPeriodDisplay = $report_data['period']['display'] ?? (date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date)));
$incomeReportTotal = (float)($report_data['total_income'] ?? 0);
$incomeReportCategories = !empty($income_breakdown['categories']) ? count($income_breakdown['categories']) : 0;
$incomeReportTransactions = isset($report_data['transactions']) && is_array($report_data['transactions']) ? count($report_data['transactions']) : 0;
$incomeReportStatus = $generate_report && $report_data ? 'Status: Generated' : 'Status: Awaiting run';

$incomeReportHeroChips = array_filter([
    'Period: ' . $reportPeriodDisplay,
    $selectedCategoryName ? 'Category: ' . $selectedCategoryName : ($category_id ? 'Category: #' . $category_id : 'Category: All'),
    $incomeReportStatus,
]);

$incomeReportHeroHighlights = [
    [
        'label' => 'Total Income',
        'value' => '$' . number_format($incomeReportTotal, 2),
        'meta' => 'Across selected period',
    ],
    [
        'label' => 'Categories',
        'value' => number_format($incomeReportCategories),
        'meta' => 'With activity',
    ],
    [
        'label' => 'Transactions',
        'value' => number_format($incomeReportTransactions),
        'meta' => 'Detailed rows',
    ],
];

$incomeReportHeroActions = [
    [
        'label' => 'Reports Dashboard',
        'url' => '/accounting/reports/reports_dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-chart-line',
    ],
    [
        'label' => 'Download PDF',
        'url' => '/accounting/reports/download.php?type=income_report&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) . ($category_id ? '&category_id=' . urlencode((string)$category_id) : ''),
        'variant' => 'outline',
        'icon' => 'fa-file-pdf',
    ],
    [
        'label' => 'Accounting Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left',
    ],
];

$incomeReportHeroConfig = [
    'eyebrow' => 'Revenue Intelligence',
    'title' => 'Income Report',
    'subtitle' => 'See which categories drive income and how transactions trend.',
    'chips' => $incomeReportHeroChips,
    'highlights' => $incomeReportHeroHighlights,
    'actions' => $incomeReportHeroActions,
    'theme' => 'emerald',
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
        renderPremiumHero($incomeReportHeroConfig);
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
                                <h1 class="h4 mb-1">Income Report</h1>
                                <p class="mb-0 small">Detailed income analysis by category and time period.</p>
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
                        <button type="submit" class="btn btn-success btn-lg me-2">
                            <i class="fas fa-chart-bar me-1"></i>Generate Report
                        </button>

                        <?php if ($report_data): ?>
                            <button type="button" class="btn btn-primary btn-lg me-2" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print Report
                            </button>
                            <div class="d-inline-flex align-items-center no-print" role="group" aria-label="Export buttons">
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-success" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'section' => 'categories'])) ?>"><i class="fas fa-file-csv me-1"></i>Categories CSV</a>
                                    <a class="btn btn-outline-primary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel', 'section' => 'categories'])) ?>"><i class="fas fa-file-excel me-1"></i>Categories Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv', 'section' => 'summary'])) ?>"><i class="fas fa-file-csv me-1"></i>Summary CSV</a>
                                    <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel', 'section' => 'summary'])) ?>"><i class="fas fa-file-excel me-1"></i>Summary Excel</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report_data): ?>
            <!-- Report Results -->
            <div class="card shadow mb-4" id="reportContent">
                <div class="card-header bg-success text-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-0">Income Report Results</h4>
                            <small><?= htmlspecialchars($report_data['period']['display']) ?></small>
                        </div>
                        <div class="col-auto">
                            <h4 class="mb-0">Total: $<?= number_format($report_data['total_income'], 2) ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Income Breakdown by Category -->
                <?php if (!empty($income_breakdown['categories'])): ?>
                    <div class="card-body">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-pie me-2 text-success"></i>Income Breakdown by Category
                        </h5>

                        <div class="row">
                            <?php foreach ($income_breakdown['categories'] as $category): ?>
                                <div class="col-lg-6 mb-3">
                                    <div class="card border">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <h6 class="mb-1"><?= htmlspecialchars($category['category_name']) ?></h6>
                                                    <div class="progress mb-2" style="height: 10px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $category['percentage'] ?>%"></div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= $category['transaction_count'] ?> transactions •
                                                        Avg: $<?= number_format($category['average_amount'], 2) ?>
                                                    </small>
                                                </div>
                                                <div class="col-auto">
                                                    <div class="text-end">
                                                        <div class="fw-bold fs-5 text-success">$<?= number_format($category['total_amount'], 2) ?></div>
                                                        <small class="text-muted"><?= $category['percentage'] ?>%</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Income Sources Summary -->
                        <div class="mt-4">
                            <h6 class="mb-3">
                                <i class="fas fa-chart-line me-2 text-info"></i>Income Sources Summary
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Total Amount</th>
                                            <th>Transactions</th>
                                            <th>Average</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($income_breakdown['categories'] as $category): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($category['category_name']) ?></td>
                                                <td class="text-success fw-bold">$<?= number_format($category['total_amount'], 2) ?></td>
                                                <td><?= $category['transaction_count'] ?></td>
                                                <td>$<?= number_format($category['average_amount'], 2) ?></td>
                                                <td><?= $category['percentage'] ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr>
                                            <th>Total</th>
                                            <th class="text-success">$<?= number_format($report_data['total_income'], 2) ?></th>
                                            <th><?= array_sum(array_column($income_breakdown['categories'], 'transaction_count')) ?></th>
                                            <th>-</th>
                                            <th>100%</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Detailed Transaction List -->
                <?php if (!empty($report_data['transactions'])): ?>
                    <div class="card-footer">
                        <h5 class="mb-3">
                            <i class="fas fa-list me-2 text-primary"></i>Detailed Income Transactions
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
                                                <span class="fw-bold text-success">$<?= number_format($transaction['amount'], 2) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="4" class="text-end">Total Income:</th>
                                        <th class="text-end text-success">$<?= number_format($report_data['total_income'], 2) ?></th>
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

        // Export to CSV function
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');

            // Create a temporary form to submit CSV export request
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = window.location.pathname;

            for (const [key, value] of params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>

</html>