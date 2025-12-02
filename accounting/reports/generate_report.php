<?php

/**
 * Generate Report Utility - W5OBM Accounting System
 * File: /accounting/reports/generate_report.php
 * Purpose: Universal report generation handler with different output formats
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../app/repositories/TransactionRepository.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to generate reports.', 'club-logo');
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

$page_title = "Generate Report - W5OBM Accounting";

// Get report parameters
$report_type = sanitizeInput($_GET['type'] ?? $_POST['type'] ?? '', 'string');
$output_format = sanitizeInput($_GET['format'] ?? $_POST['format'] ?? 'html', 'string');

// Define available reports
$available_reports = [
    'income_statement' => [
        'name' => 'Income Statement',
        'description' => 'Revenue and expenses for a specific period',
        'icon' => 'fa-chart-pie',
        'color' => 'success',
        'params' => ['month', 'year']
    ],
    'balance_sheet' => [
        'name' => 'Balance Sheet',
        'description' => 'Assets, liabilities, and equity at a specific date',
        'icon' => 'fa-balance-scale',
        'color' => 'primary',
        'params' => ['date']
    ],
    'expense_report' => [
        'name' => 'Expense Report',
        'description' => 'Detailed expense breakdown by category',
        'icon' => 'fa-credit-card',
        'color' => 'danger',
        'params' => ['start_date', 'end_date', 'category_id']
    ],
    'income_report' => [
        'name' => 'Income Report',
        'description' => 'Income breakdown by source and category',
        'icon' => 'fa-dollar-sign',
        'color' => 'success',
        'params' => ['start_date', 'end_date', 'category_id']
    ],
    'monthly_summary' => [
        'name' => 'Monthly Summary',
        'description' => 'Comprehensive monthly financial analysis',
        'icon' => 'fa-calendar-alt',
        'color' => 'info',
        'params' => ['month', 'year']
    ],
    'cash_flow' => [
        'name' => 'Cash Flow Statement',
        'description' => 'Cash inflows and outflows analysis',
        'icon' => 'fa-money-bill-wave',
        'color' => 'warning',
        'params' => ['start_date', 'end_date']
    ]
];

function report_transactions_repo(): TransactionRepository
{
    static $repo = null;
    if ($repo instanceof TransactionRepository) {
        return $repo;
    }
    global $conn;
    $db = ($conn instanceof mysqli) ? $conn : accounting_db_connection();
    $repo = new TransactionRepository($db);
    return $repo;
}

function report_fetch_transactions(array $filters = []): array
{
    return report_transactions_repo()->findAll($filters);
}

function report_calculate_transaction_totals(array $filters = []): array
{
    $rows = report_fetch_transactions($filters);
    $totals = [
        'income' => 0.0,
        'expense' => 0.0,
        'transfer' => 0.0,
        'asset' => 0.0,
        'net' => 0.0,
        'count' => 0,
    ];
    foreach ($rows as $tx) {
        $amount = isset($tx['amount']) ? (float)$tx['amount'] : 0.0;
        $type = $tx['type'] ?? 'Expense';
        $totals['count']++;
        switch ($type) {
            case 'Income':
                $totals['income'] += $amount;
                break;
            case 'Transfer':
                $totals['transfer'] += $amount;
                break;
            case 'Asset':
                $totals['asset'] += $amount;
                break;
            default:
                $totals['expense'] += $amount;
                break;
        }
    }
    $totals['net'] = $totals['income'] - $totals['expense'];
    return $totals;
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($report_type)) {
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid security token. Please try again.");
        }

        if (!array_key_exists($report_type, $available_reports)) {
            throw new Exception("Invalid report type specified.");
        }

        // Collect parameters based on report type
        $params = [];
        foreach ($available_reports[$report_type]['params'] as $param) {
            $params[$param] = sanitizeInput($_POST[$param] ?? '', 'string');
        }

        // Validate required parameters
        foreach ($available_reports[$report_type]['params'] as $param) {
            if (empty($params[$param]) && !in_array($param, ['category_id'])) { // category_id is optional
                throw new Exception("Missing required parameter: " . ucfirst(str_replace('_', ' ', $param)));
            }
        }

        // Generate report based on type
        switch ($report_type) {
            case 'income_statement':
                $report_data = generateIncomeStatement($params['month'], $params['year']);
                break;

            case 'balance_sheet':
                $report_data = generateBalanceSheet($params['date']);
                break;

            case 'expense_report':
                $report_data = getExpenseBreakdown($params['start_date'], $params['end_date']);
                if (!empty($params['category_id'])) {
                    // Filter by category if specified
                    $filters = [
                        'start_date' => $params['start_date'],
                        'end_date' => $params['end_date'],
                        'category_id' => $params['category_id'],
                        'type' => 'Expense'
                    ];
                    $report_data['transactions'] = report_fetch_transactions($filters);
                }
                break;

            case 'income_report':
                // Custom income report logic
                $filters = [
                    'start_date' => $params['start_date'],
                    'end_date' => $params['end_date'],
                    'type' => 'Income'
                ];
                if (!empty($params['category_id'])) {
                    $filters['category_id'] = $params['category_id'];
                }
                $report_data = [
                    'transactions' => report_fetch_transactions($filters),
                    'totals' => report_calculate_transaction_totals($filters),
                    'period' => [
                        'start_date' => $params['start_date'],
                        'end_date' => $params['end_date']
                    ]
                ];
                break;

            case 'monthly_summary':
                $report_data = generateIncomeStatement($params['month'], $params['year']);
                // Add additional monthly summary data
                $start_date = sprintf("%04d-%02d-01", $params['year'], $params['month']);
                $end_date = date('Y-m-t', strtotime($start_date));
                $filters = ['start_date' => $start_date, 'end_date' => $end_date];
                $report_data['summary'] = report_calculate_transaction_totals($filters);
                break;

            case 'cash_flow':
                // Cash flow report logic
                $filters = [
                    'start_date' => $params['start_date'],
                    'end_date' => $params['end_date']
                ];
                $report_data = [
                    'cash_inflows' => report_fetch_transactions(array_merge($filters, ['type' => 'Income'])),
                    'cash_outflows' => report_fetch_transactions(array_merge($filters, ['type' => 'Expense'])),
                    'totals' => report_calculate_transaction_totals($filters),
                    'period' => [
                        'start_date' => $params['start_date'],
                        'end_date' => $params['end_date']
                    ]
                ];
                break;

            default:
                throw new Exception("Report type not implemented yet.");
        }

        // Save report record
        $report_id = saveReport($report_type, $params);

        // Handle different output formats
        switch ($output_format) {
            case 'csv':
                generateCSVOutput($report_type, $report_data, $params);
                break;

            case 'json':
                generateJSONOutput($report_data);
                break;

            case 'pdf':
                generatePDFOutput($report_type, $report_data, $params);
                break;

            default: // HTML
                // Redirect to appropriate report page with generated data
                $query_params = array_merge($params, ['generate' => '1']);
                header('Location: /accounting/reports/' . $report_type . '.php?' . http_build_query($query_params));
                exit();
        }
    } catch (Exception $e) {
        setToastMessage('danger', 'Report Generation Error', $e->getMessage(), 'club-logo');
        logError("Error generating report: " . $e->getMessage(), 'accounting');
    }
}

// Get categories for dropdowns
try {
    $stmt = $conn->prepare("SELECT id, name FROM acc_transaction_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $categories = [];
    logError("Error fetching categories: " . $e->getMessage(), 'accounting');
}

/**
 * Generate CSV output for reports
 */
function generateCSVOutput($report_type, $data, $params)
{
    $filename = $report_type . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    switch ($report_type) {
        case 'income_statement':
            fputcsv($output, ['Income Statement', $data['period']['display']]);
            fputcsv($output, []);

            fputcsv($output, ['INCOME']);
            foreach ($data['income']['categories'] as $category) {
                fputcsv($output, [$category['name'], number_format($category['total'], 2)]);
            }
            fputcsv($output, ['Total Income', number_format($data['income']['total'], 2)]);
            fputcsv($output, []);

            fputcsv($output, ['EXPENSES']);
            foreach ($data['expenses']['categories'] as $category) {
                fputcsv($output, [$category['name'], number_format($category['total'], 2)]);
            }
            fputcsv($output, ['Total Expenses', number_format($data['expenses']['total'], 2)]);
            fputcsv($output, []);

            fputcsv($output, ['NET INCOME', number_format($data['net_income'], 2)]);
            break;

        case 'expense_report':
        case 'income_report':
            fputcsv($output, ['Date', 'Category', 'Description', 'Reference', 'Amount']);
            if (!empty($data['transactions'])) {
                foreach ($data['transactions'] as $transaction) {
                    fputcsv($output, [
                        $transaction['transaction_date'],
                        $transaction['category_name'] ?? '',
                        $transaction['description'],
                        $transaction['reference_number'] ?? '',
                        number_format($transaction['amount'], 2)
                    ]);
                }
            }
            break;
    }

    fclose($output);
    exit();
}

/**
 * Generate JSON output for reports
 */
function generateJSONOutput($data)
{
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

/**
 * Generate PDF output for reports (placeholder)
 */
function generatePDFOutput($report_type, $data, $params)
{
    // PDF generation would require a library like TCPDF or mPDF
    // For now, redirect to HTML version with print stylesheet
    setToastMessage('info', 'PDF Generation', 'PDF generation will be implemented soon. Please use the print function for now.', 'club-logo');
    $query_params = array_merge($params, ['generate' => '1']);
    header('Location: /accounting/reports/' . $report_type . '.php?' . http_build_query($query_params));
    exit();
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
            <div class="card-header bg-info text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Generate Report</h3>
                        <small>Create custom financial reports with various output formats</small>
                    </div>
                    <div class="col-auto">
                        <a href="/accounting/reports/reports_dashboard.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($report_type)): ?>
            <!-- Report Type Selection -->
            <div class="card shadow">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2 text-primary"></i>Select Report Type
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($available_reports as $type => $info): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="card border h-100 interactive-card" onclick="selectReport('<?= $type ?>')">
                                    <div class="card-body text-center">
                                        <i class="fas <?= $info['icon'] ?> fa-3x text-<?= $info['color'] ?> mb-3"></i>
                                        <h5 class="card-title"><?= htmlspecialchars($info['name']) ?></h5>
                                        <p class="card-text text-muted"><?= htmlspecialchars($info['description']) ?></p>
                                        <button type="button" class="btn btn-<?= $info['color'] ?> btn-lg">
                                            <i class="fas fa-arrow-right me-1"></i>Generate
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Report Parameter Form -->
            <?php $report_info = $available_reports[$report_type]; ?>
            <div class="card shadow">
                <div class="card-header bg-<?= $report_info['color'] ?> text-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-0">
                                <i class="fas <?= $report_info['icon'] ?> me-2"></i>
                                <?= htmlspecialchars($report_info['name']) ?>
                            </h4>
                            <small><?= htmlspecialchars($report_info['description']) ?></small>
                        </div>
                        <div class="col-auto">
                            <a href="?<?= http_build_query(['clear' => '1']) ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-times me-1"></i>Change Report
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">

                        <?php foreach ($report_info['params'] as $param): ?>
                            <?php if ($param === 'month'): ?>
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
                                            <option value="<?= $num ?>" <?= $num == date('n') ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            <?php elseif ($param === 'year'): ?>
                                <div class="col-md-6">
                                    <label for="year" class="form-label">Year</label>
                                    <select class="form-select form-control-lg" id="year" name="year" required>
                                        <?php for ($y = date('Y') + 1; $y >= 2020; $y--): ?>
                                            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                            <?php elseif ($param === 'date'): ?>
                                <div class="col-md-6">
                                    <label for="date" class="form-label">Report Date</label>
                                    <input type="date" class="form-control form-control-lg" id="date" name="date"
                                        value="<?= date('Y-m-d') ?>" required>
                                </div>

                            <?php elseif ($param === 'start_date'): ?>
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control form-control-lg" id="start_date" name="start_date"
                                        value="<?= date('Y-m-01') ?>" required>
                                </div>

                            <?php elseif ($param === 'end_date'): ?>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control form-control-lg" id="end_date" name="end_date"
                                        value="<?= date('Y-m-d') ?>" required>
                                </div>

                            <?php elseif ($param === 'category_id'): ?>
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label">Category (Optional)</label>
                                    <select class="form-select form-control-lg" id="category_id" name="category_id">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>">
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="col-12">
                            <hr>
                            <h6 class="mb-3">Output Format</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <button type="submit" name="format" value="html" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-globe me-1"></i>View Online
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" name="format" value="csv" class="btn btn-success btn-lg w-100">
                                        <i class="fas fa-file-csv me-1"></i>Export CSV
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" name="format" value="pdf" class="btn btn-danger btn-lg w-100">
                                        <i class="fas fa-file-pdf me-1"></i>Export PDF
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" name="format" value="json" class="btn btn-info btn-lg w-100">
                                        <i class="fas fa-file-code me-1"></i>Export JSON
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        function selectReport(type) {
            window.location.href = '?type=' + type;
        }

        // Date validation
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');

            if (startDate && endDate) {
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
            }
        });
    </script>
</body>

</html>