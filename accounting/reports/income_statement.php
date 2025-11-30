<?php

/**
 * Income Statement Report - W5OBM Accounting System
 * File: /accounting/reports/income_statement.php
 * Purpose: Generate formal income statement reports
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

$page_title = "Income Statement - W5OBM Accounting";

// Get report parameters
$year = sanitizeInput($_GET['year'] ?? date('Y'), 'int');
$month = sanitizeInput($_GET['month'] ?? date('n'), 'int');
$generate_report = isset($_GET['generate']) && $_GET['generate'] === '1';
$display_month = date('F', mktime(0, 0, 0, (int)$month, 1));

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

        // Generate income statement
        $report_data = generateIncomeStatement($month, $year);

        // Save report record
        $report_params = [
            'year' => $year,
            'month' => $month
        ];
        saveReport('income_statement', $report_params);
    } catch (Exception $e) {
        setToastMessage('danger', 'Report Error', $e->getMessage(), 'club-logo');
        logError("Error generating income statement: " . $e->getMessage(), 'accounting');
    }
}

// Standardized export handling for CSV/Excel via shared exporter
if (isset($_GET['export'])) {
    try {
        if ($year < 2000 || $year > date('Y') + 1) {
            throw new Exception("Invalid year provided.");
        }
        if ($month < 1 || $month > 12) {
            throw new Exception("Invalid month provided.");
        }

        if (!$report_data) {
            $report_data = generateIncomeStatement($month, $year);
        }

        $section = $_GET['section'] ?? 'summary';
        $export_rows = [];
        $meta_title = '';

        switch ($section) {
            case 'income_categories':
                if (!empty($report_data['income']['categories'])) {
                    foreach ($report_data['income']['categories'] as $row) {
                        $export_rows[] = [
                            'Category' => (string)($row['name'] ?? ''),
                            'Amount' => number_format((float)($row['total'] ?? 0), 2, '.', '')
                        ];
                    }
                }
                $meta_title = 'Income Statement - Income by Category';
                break;
            case 'expense_categories':
                if (!empty($report_data['expenses']['categories'])) {
                    foreach ($report_data['expenses']['categories'] as $row) {
                        $export_rows[] = [
                            'Category' => (string)($row['name'] ?? ''),
                            'Amount' => number_format((float)($row['total'] ?? 0), 2, '.', '')
                        ];
                    }
                }
                $meta_title = 'Income Statement - Expenses by Category';
                break;
            case 'summary':
            default:
                $export_rows[] = [
                    'Total Revenue' => number_format((float)($report_data['income']['total'] ?? 0), 2, '.', ''),
                    'Total Expenses' => number_format((float)($report_data['expenses']['total'] ?? 0), 2, '.', ''),
                    'Net Income' => number_format((float)($report_data['net_income'] ?? 0), 2, '.', '')
                ];
                $meta_title = 'Income Statement - Monthly Summary';
                break;
        }

        $period_display = $report_data['period']['display'] ?? (date('F', mktime(0, 0, 0, (int)$month, 1)) . ' ' . (string)$year);
        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['Period', (string)$period_display]
        ];

        // Shared exporter expects $report_data (rows) and $report_meta
        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('income_statement_' . $section, $export_rows, $report_meta);
    } catch (Exception $e) {
        setToastMessage('danger', 'Export Error', $e->getMessage(), 'club-logo');
        logError("Error exporting income statement: " . $e->getMessage(), 'accounting');
    }
}

$income_total = (float)($report_data['income']['total'] ?? 0);
$expenses_total = (float)($report_data['expenses']['total'] ?? 0);
$net_income = (float)($report_data['net_income'] ?? 0);
$report_period_display = $report_data['period']['display'] ?? ($display_month . ' ' . (string)$year);
$report_generated = $generate_report && !empty($report_data);

$incomeHeroChips = [
    'Period: ' . $report_period_display,
    $report_generated ? 'Status: Generated' : 'Status: Awaiting run',
    'Exports: CSV · Excel · PDF',
];

$incomeHeroHighlights = [
    [
        'label' => 'Revenue',
        'value' => '$' . number_format($income_total, 2),
        'meta' => 'Total income',
    ],
    [
        'label' => 'Expenses',
        'value' => '$' . number_format($expenses_total, 2),
        'meta' => 'Total spend',
    ],
    [
        'label' => 'Net Income',
        'value' => ($net_income >= 0 ? '+' : '−') . '$' . number_format(abs($net_income), 2),
        'meta' => $net_income >= 0 ? 'Operating surplus' : 'Operating deficit',
    ],
];

$incomeHeroActions = [
    [
        'label' => 'Download PDF',
        'url' => '/accounting/reports/download.php?type=income_statement&month=' . urlencode((string)$month) . '&year=' . urlencode((string)$year),
        'variant' => 'outline',
        'icon' => 'fa-file-pdf',
    ],
    [
        'label' => 'Reports Dashboard',
        'url' => '/accounting/reports/reports_dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-chart-line',
    ],
    [
        'label' => 'Accounting Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left',
    ],
];

$incomeHeroConfig = [
    'eyebrow' => 'Financial Performance',
    'title' => 'Income Statement',
    'subtitle' => 'Monitor revenue, expenses, and net income in one pass.',
    'chips' => $incomeHeroChips,
    'highlights' => $incomeHeroHighlights,
    'actions' => $incomeHeroActions,
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
        renderPremiumHero($incomeHeroConfig);
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
                                <h1 class="h4 mb-1">Income Statement</h1>
                                <p class="mb-0 small">Review revenue, expenses, and net income for <?= htmlspecialchars($report_period_display); ?>.</p>
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
        <?php renderReportHeader(
            'Income Statement',
            'Formal revenue and expense statement',
            ['Year' => $year, 'Month' => $display_month]
        ); ?>

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
                        <button type="submit" class="btn btn-success btn-lg me-2">
                            <i class="fas fa-chart-pie me-1"></i>Generate Statement
                        </button>

                        <?php if ($report_data): ?>
                            <button type="button" class="btn btn-primary btn-lg me-2" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print Statement
                            </button>
                            <div class="d-inline-flex align-items-center no-print" role="group" aria-label="Export buttons">
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-secondary" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=csv&section=summary"><i class="fas fa-file-csv me-1"></i>Summary CSV</a>
                                    <a class="btn btn-outline-primary" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=excel&section=summary"><i class="fas fa-file-excel me-1"></i>Summary Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-success" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=csv&section=income_categories"><i class="fas fa-file-csv me-1"></i>Income CSV</a>
                                    <a class="btn btn-outline-success" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=excel&section=income_categories"><i class="fas fa-file-excel me-1"></i>Income Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-danger" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=csv&section=expense_categories"><i class="fas fa-file-csv me-1"></i>Expense CSV</a>
                                    <a class="btn btn-outline-danger" href="?generate=1&year=<?= urlencode((string)$year) ?>&month=<?= urlencode((string)$month) ?>&export=excel&section=expense_categories"><i class="fas fa-file-excel me-1"></i>Expense Excel</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report_data): ?>
            <?php $statementLogo = accounting_logo_src_for(__DIR__); ?>
            <div class="statement-wrapper" id="incomeStatement">
                <div class="card shadow statement-card">
                    <div class="card-body">
                        <div class="statement-header text-center">
                            <img src="<?= htmlspecialchars($statementLogo); ?>" alt="W5OBM Logo" class="statement-logo">
                            <div>
                                <h3 class="mb-1">Olive Branch Amateur Radio Club</h3>
                                <h4 class="mb-1">Income Statement · <?= htmlspecialchars($report_data['period']['display']) ?></h4>
                                <p class="text-muted mb-0">Prepared for the <?= htmlspecialchars($report_period_display); ?> period</p>
                            </div>
                        </div>

                        <?php
                        $statementSections = [
                            'income' => [
                                'heading' => 'REVENUE',
                                'subsection' => 'Income Categories',
                                'total_label' => 'Total Revenue',
                                'empty' => 'No revenue recorded for this period',
                            ],
                            'expenses' => [
                                'heading' => 'EXPENSES',
                                'subsection' => 'Expense Categories',
                                'total_label' => 'Total Expenses',
                                'empty' => 'No expenses recorded for this period',
                            ],
                        ];
                        ?>

                        <?php foreach ($statementSections as $sectionKey => $sectionMeta): ?>
                            <?php $categories = $report_data[$sectionKey]['categories'] ?? []; ?>
                            <div class="statement-section">
                                <div class="statement-row statement-heading-row">
                                    <div class="statement-label heading"><?= $sectionMeta['heading'] ?></div>
                                    <div class="statement-amount heading-label">Current Activity</div>
                                    <div class="statement-amount heading-label">Totals / Subtotals</div>
                                </div>

                                <div class="statement-row statement-subsection-row">
                                    <div class="statement-label subsection"><?= $sectionMeta['subsection'] ?></div>
                                    <div class="statement-amount current"></div>
                                    <div class="statement-amount total"></div>
                                </div>

                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <div class="statement-row statement-category-row">
                                            <div class="statement-label category"><?= htmlspecialchars($category['name']) ?></div>
                                            <div class="statement-amount current">$<?= number_format((float)$category['total'], 2) ?></div>
                                            <div class="statement-amount total"></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="statement-row statement-category-row text-muted fst-italic">
                                        <div class="statement-label category"><?= $sectionMeta['empty'] ?></div>
                                        <div class="statement-amount current">—</div>
                                        <div class="statement-amount total">—</div>
                                    </div>
                                <?php endif; ?>

                                <div class="statement-row statement-subtotal-row">
                                    <div class="statement-label subsection"><?= $sectionMeta['total_label'] ?></div>
                                    <div class="statement-amount current"></div>
                                    <div class="statement-amount total">
                                        <strong>$<?= number_format((float)($report_data[$sectionKey]['total'] ?? 0), 2) ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="statement-row statement-heading-row statement-net-row">
                            <div class="statement-label heading">NET INCOME</div>
                            <div class="statement-amount heading-label"></div>
                            <div class="statement-amount total text-<?= $report_data['net_income'] >= 0 ? 'success' : 'danger' ?>">
                                <strong>$<?= number_format((float)$report_data['net_income'], 2) ?></strong>
                            </div>
                        </div>

                        <!-- Financial Analysis -->
                        <?php if ($report_data['income']['total'] > 0): ?>
                            <div class="mt-4">
                                <h6 class="text-info mb-3">
                                    <i class="fas fa-chart-line me-2"></i>Financial Analysis
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">Expense Ratio:</small><br>
                                        <strong>
                                            <?= number_format(($report_data['expenses']['total'] / $report_data['income']['total']) * 100, 1) ?>%
                                        </strong>
                                        <div class="progress mt-1" style="height: 6px;">
                                            <div class="progress-bar bg-danger"
                                                style="width: <?= min(100, ($report_data['expenses']['total'] / $report_data['income']['total']) * 100) ?>%">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Net Margin:</small><br>
                                        <strong class="text-<?= $report_data['net_income'] >= 0 ? 'success' : 'danger' ?>">
                                            <?= number_format(($report_data['net_income'] / $report_data['income']['total']) * 100, 1) ?>%
                                        </strong>
                                        <div class="progress mt-1" style="height: 6px;">
                                            <div class="progress-bar bg-<?= $report_data['net_income'] >= 0 ? 'success' : 'danger' ?>"
                                                style="width: <?= min(100, abs(($report_data['net_income'] / $report_data['income']['total']) * 100)) ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Report Footer -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        Report generated on: <?= date('F j, Y g:i A') ?><br>
                                        Generated by: <?= function_exists('getCurrentUsername') ? htmlspecialchars(getCurrentUsername()) : htmlspecialchars((string)$user_id) ?>
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted">
                                        W5OBM Amateur Radio Club<br>
                                        Financial Management System
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <style>
        .statement-wrapper {
            max-width: 960px;
            margin: 0 auto 2rem;
        }

        .statement-card {
            border-radius: 1.25rem;
        }

        .statement-card .card-body {
            padding: 2.5rem;
        }

        .statement-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .statement-logo {
            max-width: 80px;
            height: auto;
        }

        .statement-section {
            margin-top: 1.75rem;
        }

        .statement-section:first-of-type {
            margin-top: 0;
        }

        .statement-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.35rem 0;
        }

        .statement-label {
            flex: 1 1 auto;
            font-size: 0.95rem;
        }

        .statement-label.heading {
            font-weight: 700;
            text-transform: uppercase;
        }

        .statement-label.subsection {
            font-weight: 600;
            padding-left: 3rem;
        }

        .statement-label.category {
            padding-left: 4.5rem;
        }

        .statement-amount {
            width: 180px;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .statement-amount.heading-label {
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6c757d;
        }

        .statement-row.statement-subtotal-row {
            border-top: 1px solid #e4e7ec;
            margin-top: 0.35rem;
            padding-top: 0.6rem;
        }

        .statement-row.statement-heading-row+.statement-row.statement-subsection-row {
            margin-top: 0.35rem;
        }

        .statement-row.statement-net-row {
            border-top: 2px solid #212529;
            margin-top: 2rem;
            padding-top: 0.75rem;
        }

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

            .card,
            .statement-card {
                border: none !important;
                box-shadow: none !important;
            }

            .statement-card .card-body {
                padding: 1.25rem !important;
            }

            .card-header {
                background: #ffffff !important;
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
            }

            #incomeStatement {
                page-break-inside: avoid;
            }

            .statement-amount.heading-label {
                color: #000 !important;
            }

            .border-bottom {
                border-bottom: 1px solid #000 !important;
            }

            .border-top {
                border-top: 1px solid #000 !important;
            }

            .text-success,
            .text-danger,
            .text-info {
                color: #000 !important;
            }

            .progress {
                display: none !important;
            }
        }

        .progress {
            border-radius: 3px;
        }

        #incomeStatement .row {
            margin-bottom: 0.25rem;
        }

        #incomeStatement .border-top {
            border-top: 2px solid #dee2e6 !important;
        }

        #incomeStatement .border-bottom {
            border-bottom: 2px solid #dee2e6 !important;
        }
    </style>
</body>

</html>