<?php

require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../../include/report_header.php';

validate_session();

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_view') && !hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view financial statements.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

$page_title = 'Financial Statements - W5OBM Accounting';

$allowedPeriodTypes = ['monthly', 'quarterly', 'ytd', 'annual'];
$periodType = strtolower(sanitizeInput($_GET['period_type'] ?? 'monthly', 'string'));
if (!in_array($periodType, $allowedPeriodTypes, true)) {
    $periodType = 'monthly';
}

$year = (int)sanitizeInput($_GET['year'] ?? date('Y'), 'int');
if ($year < 2000) {
    $year = 2000;
}
if ($year > (int)date('Y') + 1) {
    $year = (int)date('Y') + 1;
}

$month = (int)sanitizeInput($_GET['month'] ?? date('n'), 'int');
$quarter = (int)sanitizeInput($_GET['quarter'] ?? ceil(date('n') / 3), 'int');
$ytdMonth = (int)sanitizeInput($_GET['ytd_month'] ?? date('n'), 'int');

$shouldGenerate = isset($_GET['generate']) ? $_GET['generate'] === '1' : true;
$exportType = $_GET['export'] ?? null;
if ($exportType !== null) {
    $shouldGenerate = true;
}

$selectionValue = null;
switch ($periodType) {
    case 'monthly':
        $selectionValue = $month;
        break;
    case 'quarterly':
        $selectionValue = $quarter;
        break;
    case 'ytd':
        $selectionValue = $ytdMonth;
        break;
    case 'annual':
    default:
        $selectionValue = null;
        break;
}

$periodMeta = buildStatementPeriod($periodType, $year, $selectionValue);
$reportData = null;
if ($shouldGenerate) {
    $reportData = generateIncomeStatementRange($periodMeta['start_date'], $periodMeta['end_date'], $periodMeta['label']);
}

if ($reportData && isset($reportData['error'])) {
    setToastMessage('danger', 'Report Error', $reportData['error'], 'club-logo');
}

$queryBase = [
    'period_type' => $periodType,
    'year' => $year,
    'month' => $month,
    'quarter' => $quarter,
    'ytd_month' => $ytdMonth,
    'generate' => '1',
];

if ($reportData && empty($reportData['error']) && $exportType === 'csv') {
    require_once __DIR__ . '/../lib/export_bridge.php';
    $rows = [];
    $rows[] = ['Label' => 'Total Revenue', 'Amount' => number_format((float)$reportData['income']['total'], 2, '.', '')];
    $rows[] = ['Label' => 'Total Expenses', 'Amount' => number_format((float)$reportData['expenses']['total'], 2, '.', '')];
    $rows[] = ['Label' => 'Net Income', 'Amount' => number_format((float)$reportData['net_income'], 2, '.', '')];
    $rows[] = ['Label' => ''];
    foreach ($reportData['income']['categories'] as $row) {
        $rows[] = ['Label' => 'Income • ' . $row['name'], 'Amount' => number_format((float)$row['total'], 2, '.', '')];
    }
    foreach ($reportData['expenses']['categories'] as $row) {
        $rows[] = ['Label' => 'Expense • ' . $row['name'], 'Amount' => number_format((float)$row['total'], 2, '.', '')];
    }

    $meta = [
        ['Report', 'Financial Statement (' . strtoupper($periodType) . ')'],
        ['Period', $periodMeta['label']],
        ['Generated', date('Y-m-d H:i:s')],
    ];

    accounting_export('financial_statement_' . $periodType, $rows, $meta);
}

if ($reportData && empty($reportData['error']) && $shouldGenerate && $exportType === null) {
    $params = $queryBase;
    unset($params['generate']);
    saveReport('financial_statement_' . $periodType, $params);
}

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
    12 => 'December',
];

$quarters = [
    1 => 'Q1 (Jan - Mar)',
    2 => 'Q2 (Apr - Jun)',
    3 => 'Q3 (Jul - Sep)',
    4 => 'Q4 (Oct - Dec)',
];

function statement_query(array $base, array $overrides = []): string
{
    return http_build_query(array_merge($base, $overrides));
}

$netClass = ($reportData && $reportData['net_income'] >= 0) ? 'text-success' : 'text-danger';
$logoSrc = accounting_logo_src_for(__DIR__);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
    <style>
        .printer-friendly {
            font-family: "Libre Baskerville", "Georgia", serif;
        }

        .statement-card {
            border: 1px solid #e5e7eb;
        }

        .report-totals-row {
            border-top: 2px solid #111;
            border-bottom: 2px solid #111;
            padding: 1rem 0;
        }

        .report-table th,
        .report-table td {
            font-size: 0.95rem;
        }

        .report-table td.amount {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .filters-column {
            position: sticky;
            top: 90px;
        }

        @media (max-width: 991.98px) {
            .filters-column {
                position: static;
            }
        }

        @media print {
            body {
                background: #fff !important;
            }

            nav,
            .hero,
            .filters-column,
            .no-print,
            .page-container>.alert,
            .page-container>.row:first-of-type {
                display: none !important;
            }

            .statement-column {
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }

            .statement-card {
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>
    <?php renderReportHeader('Financial Statements', 'Standardized GAAP presentation for club financials.', [
        'Period Type' => ucfirst($periodType),
        'Period' => $periodMeta['label'],
    ]); ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <section class="hero hero-small mb-4">
            <div class="hero-body py-3">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-2 d-none d-md-flex justify-content-center">
                            <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                        </div>
                        <div class="col-md-6 text-center text-md-start text-white">
                            <h1 class="h4 mb-1">Financial Statements</h1>
                            <p class="mb-0 small">Monthly, quarterly, YTD, and annual reports built for printing.</p>
                        </div>
                        <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                            <a href="/accounting/reports_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-arrow-left me-1"></i>Reports Dashboard
                            </a>
                            <a href="/accounting/dashboard.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-home me-1"></i>Accounting Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if (function_exists('displayToastMessage')) {
            displayToastMessage();
        } ?>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8 order-lg-2 statement-column">
                <div class="card shadow statement-card">
                    <div class="card-header bg-light d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                        <div>
                            <h5 class="mb-1">W5OBM Amateur Radio Club</h5>
                            <h6 class="mb-0 text-muted">Financial Statement &mdash; <?= htmlspecialchars($periodMeta['label']); ?></h6>
                            <small class="text-muted">Coverage: <?= htmlspecialchars($periodMeta['start_date']); ?> &ndash; <?= htmlspecialchars($periodMeta['end_date']); ?></small>
                        </div>
                        <div class="mt-3 mt-lg-0 no-print">
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="printStatementBtn">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                            <?php if ($reportData && empty($reportData['error'])): ?>
                                <button type="button" class="btn btn-primary btn-sm me-2" id="pdfExportBtn">
                                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                                </button>
                                <a href="?<?= htmlspecialchars(statement_query($queryBase, ['export' => 'csv'])); ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-file-csv me-1"></i>CSV
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body printer-friendly" id="statementPreview">
                        <?php if ($reportData && empty($reportData['error'])): ?>
                            <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
                                <div class="col">
                                    <div class="border rounded p-3 h-100 bg-light">
                                        <small class="text-muted text-uppercase">Total Revenue</small>
                                        <h3 class="mb-0 mt-1">$<?= number_format((float)$reportData['income']['total'], 2); ?></h3>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="border rounded p-3 h-100 bg-light">
                                        <small class="text-muted text-uppercase">Total Expenses</small>
                                        <h3 class="mb-0 mt-1">$<?= number_format((float)$reportData['expenses']['total'], 2); ?></h3>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="border rounded p-3 h-100 bg-light">
                                        <small class="text-muted text-uppercase">Net Income</small>
                                        <h3 class="mb-0 mt-1 <?= $netClass; ?>">$<?= number_format((float)$reportData['net_income'], 2); ?></h3>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="border rounded p-3 h-100 bg-light">
                                        <small class="text-muted text-uppercase">Operating Margin</small>
                                        <?php
                                        $margin = ($reportData['income']['total'] > 0)
                                            ? ($reportData['net_income'] / $reportData['income']['total']) * 100
                                            : 0;
                                        ?>
                                        <h3 class="mb-0 mt-1 <?= $margin >= 0 ? 'text-success' : 'text-danger'; ?>"><?= number_format($margin, 1); ?>%</h3>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="text-success border-bottom pb-2">Revenue</h5>
                                <table class="table table-borderless report-table mb-0">
                                    <tbody>
                                        <?php if (!empty($reportData['income']['categories'])): ?>
                                            <?php foreach ($reportData['income']['categories'] as $category): ?>
                                                <tr>
                                                    <td class="ps-3"><?= htmlspecialchars($category['name']); ?></td>
                                                    <td class="amount">$<?= number_format((float)$category['total'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" class="text-muted">No revenue recorded for this period.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr class="fw-bold">
                                            <td>Total Revenue</td>
                                            <td class="amount">$<?= number_format((float)$reportData['income']['total'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mb-4">
                                <h5 class="text-danger border-bottom pb-2">Expenses</h5>
                                <table class="table table-borderless report-table mb-0">
                                    <tbody>
                                        <?php if (!empty($reportData['expenses']['categories'])): ?>
                                            <?php foreach ($reportData['expenses']['categories'] as $category): ?>
                                                <tr>
                                                    <td class="ps-3"><?= htmlspecialchars($category['name']); ?></td>
                                                    <td class="amount">$<?= number_format((float)$category['total'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" class="text-muted">No expenses recorded for this period.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr class="fw-bold">
                                            <td>Total Expenses</td>
                                            <td class="amount">$<?= number_format((float)$reportData['expenses']['total'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="report-totals-row">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="mb-0">Net Income</h4>
                                    <h4 class="mb-0 <?= $netClass; ?>">$<?= number_format((float)$reportData['net_income'], 2); ?></h4>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h6 class="text-muted text-uppercase mb-2">Narrative</h6>
                                <p class="mb-0">
                                    Revenues <?= $reportData['income']['total'] >= $reportData['expenses']['total'] ? 'exceeded' : 'were below'; ?> expenses for this
                                    <?= htmlspecialchars($periodMeta['label']); ?> reporting period. Use the expense ratio and margin above to assess operating efficiency.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <p class="text-muted mb-1">Select a period on the left to generate a statement.</p>
                                <small class="text-muted">Monthly view is selected by default.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 order-lg-1 filters-column">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3" id="statementFilterForm">
                            <input type="hidden" name="generate" value="1">
                            <div class="col-12">
                                <label for="period_type" class="form-label">Period Type</label>
                                <select id="period_type" name="period_type" class="form-select form-select-sm">
                                    <option value="monthly" <?= $periodType === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="quarterly" <?= $periodType === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                    <option value="ytd" <?= $periodType === 'ytd' ? 'selected' : ''; ?>>Year to Date</option>
                                    <option value="annual" <?= $periodType === 'annual' ? 'selected' : ''; ?>>Annual</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="year" class="form-label">Year</label>
                                <select id="year" name="year" class="form-select form-select-sm">
                                    <?php for ($y = date('Y') + 1; $y >= 2020; $y--): ?>
                                        <option value="<?= $y; ?>" <?= (int)$y === (int)$year ? 'selected' : ''; ?>><?= $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-12 period-field" data-period="monthly">
                                <label for="month" class="form-label">Month</label>
                                <select id="month" name="month" class="form-select form-select-sm">
                                    <?php foreach ($months as $value => $label): ?>
                                        <option value="<?= $value; ?>" <?= $value === $month ? 'selected' : ''; ?>><?= $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 period-field" data-period="quarterly">
                                <label for="quarter" class="form-label">Quarter</label>
                                <select id="quarter" name="quarter" class="form-select form-select-sm">
                                    <?php foreach ($quarters as $value => $label): ?>
                                        <option value="<?= $value; ?>" <?= $value === $quarter ? 'selected' : ''; ?>><?= $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 period-field" data-period="ytd">
                                <label for="ytd_month" class="form-label">YTD Cutoff (Month)</label>
                                <select id="ytd_month" name="ytd_month" class="form-select form-select-sm">
                                    <?php foreach ($months as $value => $label): ?>
                                        <option value="<?= $value; ?>" <?= $value === $ytdMonth ? 'selected' : ''; ?>><?= $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 d-grid">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-sync me-1"></i>Generate Statement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card mt-4 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-uppercase text-muted">Quick Presets</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-secondary btn-sm preset-btn" data-type="monthly" data-month="<?= date('n'); ?>" data-year="<?= date('Y'); ?>">Current Month</button>
                            <button class="btn btn-outline-secondary btn-sm preset-btn" data-type="quarterly" data-quarter="<?= ceil(date('n') / 3); ?>" data-year="<?= date('Y'); ?>">Current Quarter</button>
                            <button class="btn btn-outline-secondary btn-sm preset-btn" data-type="ytd" data-year="<?= date('Y'); ?>" data-ytd="<?= date('n'); ?>">Year to Date</button>
                            <button class="btn btn-outline-secondary btn-sm preset-btn" data-type="annual" data-year="<?= date('Y'); ?>">Full Year</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js" integrity="sha384-GpcwYdA1NVuMcZV8IO4D4ew3Efr2E1VlzDq+W8sELpAo0P5NdVs4KJIp4jOSAmUJ" crossorigin="anonymous"></script>
    <script>
        (function() {
            const periodSelect = document.getElementById('period_type');
            const fields = document.querySelectorAll('.period-field');
            const form = document.getElementById('statementFilterForm');
            const presetButtons = document.querySelectorAll('.preset-btn');
            const printBtn = document.getElementById('printStatementBtn');
            const pdfBtn = document.getElementById('pdfExportBtn');

            function toggleFields() {
                const value = periodSelect.value;
                fields.forEach(field => {
                    const target = field.getAttribute('data-period');
                    field.style.display = (target === value) ? 'block' : 'none';
                });
            }

            periodSelect.addEventListener('change', toggleFields);
            toggleFields();

            presetButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const type = btn.getAttribute('data-type');
                    periodSelect.value = type;
                    const year = btn.getAttribute('data-year');
                    if (year) {
                        document.getElementById('year').value = year;
                    }
                    if (btn.dataset.month) {
                        document.getElementById('month').value = btn.dataset.month;
                    }
                    if (btn.dataset.quarter) {
                        document.getElementById('quarter').value = btn.dataset.quarter;
                    }
                    if (btn.dataset.ytd) {
                        document.getElementById('ytd_month').value = btn.dataset.ytd;
                    }
                    toggleFields();
                    form.requestSubmit();
                });
            });

            if (printBtn) {
                printBtn.addEventListener('click', () => window.print());
            }

            if (pdfBtn) {
                pdfBtn.addEventListener('click', () => {
                    const preview = document.getElementById('statementPreview');
                    if (!preview) {
                        return;
                    }
                    const options = {
                        margin: 10,
                        filename: 'financial_statement_<?= htmlspecialchars($periodMeta['slug']); ?>.pdf',
                        html2canvas: {
                            scale: 2
                        },
                        jsPDF: {
                            unit: 'mm',
                            format: 'a4',
                            orientation: 'portrait'
                        }
                    };
                    html2pdf().set(options).from(preview).save();
                });
            }
        })();
    </script>
</body>

</html>