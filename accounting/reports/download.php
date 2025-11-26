<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../utils/pdf_generator.php';
require_once __DIR__ . '/../controllers/reportController.php';
require_once __DIR__ . '/../controllers/donation_controller.php';
require_once __DIR__ . '/../controllers/assetController.php';
require_once __DIR__ . '/../lib/helpers.php';

validate_session();

$type = strtolower(trim($_GET['type'] ?? ''));
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($reportId) {
        streamExistingReport($reportId);
    }

    if ($type === '') {
        throw new InvalidArgumentException('Report type is required.');
    }

    $filePath = generateReportFile($type, $_GET);
    streamPdfFile($filePath);
} catch (InvalidArgumentException $e) {
    respondWithError($e->getMessage(), 400);
} catch (RuntimeException $e) {
    logError('Report download runtime error: ' . $e->getMessage(), 'accounting');
    respondWithError($e->getMessage(), 422);
} catch (Throwable $e) {
    logError('Report download failure: ' . $e->getMessage(), 'accounting');
    respondWithError('Unable to generate the requested report right now.', 500);
}

function streamExistingReport(int $reportId): void
{
    $report = getReportById($reportId);
    if (!$report || empty($report['file_path'])) {
        throw new InvalidArgumentException('Report not found.');
    }

    if (!is_readable($report['file_path'])) {
        throw new RuntimeException('Stored report file is missing.');
    }

    streamPdfFile($report['file_path']);
}

function generateReportFile(string $type, array $params): string
{
    switch ($type) {
        case 'income_statement':
            $month = (int)($params['month'] ?? date('n'));
            $year = (int)($params['year'] ?? date('Y'));
            return ensureFilePath(generate_income_statement_pdf($month, $year), 'income statement');
        case 'ytd_income_statement':
            $year = (int)($params['year'] ?? date('Y'));
            return generateYtdIncomeStatementPdf($year);
        case 'balance_sheet':
            $date = $params['date'] ?? date('Y-m-d');
            return generateBalanceSheetPdf($date);
        case 'cash_flow':
            $month = (int)($params['month'] ?? date('n'));
            $year = (int)($params['year'] ?? date('Y'));
            return generateCashFlowPdf($month, $year);
        case 'ytd_cash_flow':
            $year = (int)($params['year'] ?? date('Y'));
            return generateYtdCashFlowPdf($year);
        case 'donation_summary':
            $year = (int)($params['year'] ?? date('Y'));
            return generateDonationSummaryPdf($year);
        case 'donor_list':
            return generateDonorListPdf();
        case 'asset_listing':
            return generateAssetListingPdf();
        default:
            throw new InvalidArgumentException('Invalid or unsupported report type.');
    }
}

function streamPdfFile(string $filePath): void
{
    if (!is_readable($filePath)) {
        throw new RuntimeException('Report file is not available.');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

function respondWithError(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function ensureFilePath(?string $path, string $label): string
{
    if (!$path || !is_string($path)) {
        throw new RuntimeException('Unable to generate ' . $label . ' report.');
    }
    return $path;
}

function formatCurrency(float $value): string
{
    return '$' . number_format($value, 2);
}

function registerReport(string $type, array $parameters, string $filePath): void
{
    saveReport($type, $parameters, $filePath);
}

function generateBalanceSheetPdf(string $date): string
{
    $statement = generateBalanceSheet($date);
    if (isset($statement['error'])) {
        throw new RuntimeException('Unable to build balance sheet: ' . $statement['error']);
    }

    $displayDate = date('F j, Y', strtotime($statement['date']));
    $rows = [
        ['Cash & Equivalents', formatCurrency((float)$statement['assets']['cash'])],
        ['Physical Assets', formatCurrency((float)$statement['assets']['physical_assets'])],
        ['Total Assets', formatCurrency((float)$statement['assets']['total'])],
        ['Total Liabilities', formatCurrency((float)$statement['liabilities'])],
        ['Total Equity', formatCurrency((float)$statement['equity'])],
        ['Assets - Liabilities', formatCurrency((float)$statement['assets']['total'] - (float)$statement['liabilities'])],
    ];

    $fileName = 'balance_sheet_' . date('Ymd', strtotime($statement['date'])) . '.pdf';
    $filePath = create_pdf('Balance Sheet - ' . $displayDate, [
        'headers' => ['Item', 'Amount'],
        'rows' => $rows,
    ], $fileName);

    $filePath = ensureFilePath($filePath, 'balance sheet');
    registerReport('balance_sheet', ['date' => $statement['date']], $filePath);
    return $filePath;
}

function generateYtdIncomeStatementPdf(int $year): string
{
    $period = buildStatementPeriod('ytd', $year);
    $statement = generateIncomeStatementRange($period['start_date'], $period['end_date'], $period['label']);

    if (isset($statement['error'])) {
        throw new RuntimeException('Unable to build YTD statement: ' . $statement['error']);
    }

    $rows = [];
    foreach ($statement['income']['categories'] as $category) {
        $rows[] = ['Income: ' . $category['name'], formatCurrency((float)$category['total'])];
    }
    $rows[] = ['Total Income', formatCurrency((float)$statement['income']['total'])];

    $rows[] = ['-', '-'];

    foreach ($statement['expenses']['categories'] as $category) {
        $rows[] = ['Expense: ' . $category['name'], formatCurrency((float)$category['total'])];
    }
    $rows[] = ['Total Expenses', formatCurrency((float)$statement['expenses']['total'])];
    $rows[] = ['Net Income', formatCurrency((float)$statement['net_income'])];

    $fileName = 'income_statement_ytd_' . $period['year'] . '.pdf';
    $filePath = create_pdf('YTD Income Statement - ' . $period['label'], [
        'headers' => ['Line Item', 'Amount'],
        'rows' => $rows,
    ], $fileName);

    $filePath = ensureFilePath($filePath, 'YTD income statement');
    registerReport('ytd_income_statement', ['year' => $period['year']], $filePath);
    return $filePath;
}

function generateCashFlowPdf(int $month, int $year): string
{
    $period = buildStatementPeriod('monthly', $year, $month);
    return buildCashFlowPdf('Cash Flow - ' . $period['label'], $period['start_date'], $period['end_date'], 'cash_flow_' . $period['year'] . '_' . sprintf('%02d', $period['value']) . '.pdf', [
        'month' => $month,
        'year' => $year,
    ]);
}

function generateYtdCashFlowPdf(int $year): string
{
    $period = buildStatementPeriod('ytd', $year);
    return buildCashFlowPdf('Cash Flow - ' . $period['label'], $period['start_date'], $period['end_date'], 'cash_flow_ytd_' . $period['year'] . '.pdf', [
        'year' => $year,
        'type' => 'ytd',
    ]);
}

function buildCashFlowPdf(string $title, string $startDate, string $endDate, string $fileName, array $meta): string
{
    $flow = calculateCashWindow($startDate, $endDate);

    $rows = [
        ['Beginning Balance', formatCurrency($flow['beginning'])],
        ['Cash Inflows', formatCurrency($flow['income'])],
        ['Cash Outflows', formatCurrency($flow['expenses'])],
        ['Net Change', formatCurrency($flow['net'])],
        ['Ending Balance', formatCurrency($flow['ending'])],
    ];

    $filePath = create_pdf($title . ' (' . date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)) . ')', [
        'headers' => ['Activity', 'Amount'],
        'rows' => $rows,
    ], $fileName);

    $filePath = ensureFilePath($filePath, 'cash flow');
    registerReport('cash_flow', array_merge($meta, ['start_date' => $startDate, 'end_date' => $endDate]), $filePath);
    return $filePath;
}

function calculateCashWindow(string $startDate, string $endDate): array
{
    global $conn;

    $beginStmt = $conn->prepare("SELECT 
            SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
            SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
        FROM acc_transactions
        WHERE transaction_date < ?");
    $beginStmt->bind_param('s', $startDate);
    $beginStmt->execute();
    $beginBalance = (float)($beginStmt->get_result()->fetch_assoc()['balance'] ?? 0);
    $beginStmt->close();

    $activityStmt = $conn->prepare("SELECT 
            SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
            SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expenses
        FROM acc_transactions
        WHERE transaction_date BETWEEN ? AND ?");
    $activityStmt->bind_param('ss', $startDate, $endDate);
    $activityStmt->execute();
    $activity = $activityStmt->get_result()->fetch_assoc();
    $activityStmt->close();

    $income = (float)($activity['income'] ?? 0.0);
    $expenses = (float)($activity['expenses'] ?? 0.0);
    $net = $income - $expenses;

    return [
        'beginning' => $beginBalance,
        'income' => $income,
        'expenses' => $expenses,
        'net' => $net,
        'ending' => $beginBalance + $net,
    ];
}

function generateDonationSummaryPdf(int $year): string
{
    global $conn;

    $stmt = $conn->prepare("SELECT MONTH(donation_date) AS month, SUM(amount) AS total
        FROM acc_donations
        WHERE YEAR(donation_date) = ?
        GROUP BY MONTH(donation_date)
        ORDER BY MONTH(donation_date)");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    $grandTotal = 0.0;
    while ($row = $result->fetch_assoc()) {
        $grandTotal += (float)$row['total'];
        $monthName = DateTime::createFromFormat('!m', (string)$row['month'])->format('F');
        $rows[] = [$monthName, formatCurrency((float)$row['total'])];
    }
    $stmt->close();

    $rows[] = ['Total', formatCurrency($grandTotal)];

    $filePath = create_pdf('Donation Summary - ' . $year, [
        'headers' => ['Month', 'Total'],
        'rows' => $rows,
    ], 'donation_summary_' . $year . '.pdf');

    $filePath = ensureFilePath($filePath, 'donation summary');
    registerReport('donation_summary', ['year' => $year], $filePath);
    return $filePath;
}

function generateDonorListPdf(): string
{
    global $conn;

    $query = "SELECT c.name, c.email, c.phone, SUM(d.amount) AS total, MAX(d.donation_date) AS last_date
        FROM acc_donations d
        JOIN acc_contacts c ON d.contact_id = c.id
        GROUP BY c.id, c.name, c.email, c.phone
        ORDER BY total DESC, c.name ASC";

    $result = $conn->query($query);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            $row['name'],
            $row['email'] ?: '—',
            formatCurrency((float)$row['total']),
            $row['last_date'] ? date('M j, Y', strtotime($row['last_date'])) : '—',
        ];
    }

    $filePath = create_pdf('Donor Rollup', [
        'headers' => ['Donor', 'Email', 'Lifetime Total', 'Last Gift'],
        'rows' => $rows ?: [['No donor records', '—', '—', '—']],
    ], 'donor_list_' . date('Ymd') . '.pdf');

    $filePath = ensureFilePath($filePath, 'donor list');
    registerReport('donor_list', ['generated_on' => date('Y-m-d')], $filePath);
    return $filePath;
}

function generateAssetListingPdf(): string
{
    global $conn;

    $result = $conn->query("SELECT asset_name, asset_tag, category, value, acquisition_date, status
        FROM acc_assets
        ORDER BY status DESC, acquisition_date ASC");

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            $row['asset_name'] ?: $row['asset_tag'] ?: 'Unnamed Asset',
            $row['category'] ?: 'Uncategorized',
            formatCurrency((float)($row['value'] ?? 0)),
            $row['acquisition_date'] ? date('M j, Y', strtotime($row['acquisition_date'])) : 'Unknown',
            ucfirst(strtolower($row['status'] ?? 'active')),
        ];
    }

    $filePath = create_pdf('Asset Listing', [
        'headers' => ['Asset', 'Category', 'Value', 'Acquired', 'Status'],
        'rows' => $rows ?: [['No assets recorded', '—', '—', '—', '—']],
    ], 'asset_listing_' . date('Ymd') . '.pdf');

    $filePath = ensureFilePath($filePath, 'asset listing');
    registerReport('asset_listing', ['generated_on' => date('Y-m-d')], $filePath);
    return $filePath;
}