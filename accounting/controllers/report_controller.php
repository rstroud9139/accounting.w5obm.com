<?php

/**
 * Report Controller (underscore) - Wrapper to canonical camelCase controller
 */
require_once __DIR__ . '/reportController.php';
// Centralized stats (cash flow, YTD, category, totals)
@require_once __DIR__ . '/../utils/stats_service.php';

function generate_balance_sheet($date = null)
{
    return generateBalanceSheet($date);
}

function generate_income_statement($month, $year)
{
    return generateIncomeStatement($month, $year);
}

function generate_ytd_income_statement($year)
{
    // Prefer centralized stats service implementation if available
    if (function_exists('get_ytd_income_statement')) {
        $ytd = get_ytd_income_statement($year);
        // Adapt to legacy return shape
        $monthly_legacy = [];
        foreach ($ytd['monthly'] as $k => $m) {
            $monthly_legacy[$k] = [
                'month' => $m['display'],
                'income' => $m['income'],
                'expenses' => $m['expenses'],
                'net' => $m['net'],
            ];
        }
        return [
            'year' => $ytd['year'],
            'monthly_data' => $monthly_legacy,
            'totals' => [
                'income' => $ytd['totals']['income'],
                'expenses' => $ytd['totals']['expenses'],
                'net_income' => $ytd['totals']['net'],
            ],
        ];
    }
    // Fallback to previous inline generation
    $monthly = [];
    $total_income = 0.0;
    $total_expenses = 0.0;
    for ($m = 1; $m <= 12; $m++) {
        $start = sprintf('%04d-%02d-01', $year, $m);
        if (strtotime($start) > time()) {
            break;
        }
        $stmt = generateIncomeStatement($m, $year);
        $monthly[$m] = [
            'month' => $stmt['period']['display'] ?? date('F', strtotime($start)),
            'income' => $stmt['income']['total'] ?? 0,
            'expenses' => $stmt['expenses']['total'] ?? 0,
            'net' => ($stmt['income']['total'] ?? 0) - ($stmt['expenses']['total'] ?? 0),
        ];
        $total_income += ($stmt['income']['total'] ?? 0);
        $total_expenses += ($stmt['expenses']['total'] ?? 0);
    }
    return [
        'year' => $year,
        'monthly_data' => $monthly,
        'totals' => [
            'income' => $total_income,
            'expenses' => $total_expenses,
            'net_income' => $total_income - $total_expenses,
        ],
    ];
}

/**
 * Generate a cash flow statement for a given period.
 */
function generate_cash_flow_statement($start_date, $end_date)
{
    // Delegate to stats service if advanced implementation exists
    if (function_exists('get_cash_flow_statement')) {
        return get_cash_flow_statement($start_date, $end_date);
    }
    global $conn;
    // Beginning Balance (all prior activity)
    $stmt = $conn->prepare("SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
              FROM acc_transactions 
              WHERE transaction_date < ?");
    $stmt->bind_param('s', $start_date);
    $stmt->execute();
    $beginning_balance = (float)($stmt->get_result()->fetch_assoc()['balance'] ?? 0);
    $stmt->close();

    // Helper to aggregate category groups
    $aggregate = function (string $groupType) use ($conn, $start_date, $end_date) {
        $stmt = $conn->prepare("SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expense
              FROM acc_transactions 
              WHERE transaction_date BETWEEN ? AND ?
              AND category_id IN (SELECT id FROM acc_transaction_categories WHERE type = ?)");
        $stmt->bind_param('sss', $start_date, $end_date, $groupType);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $income = (float)($data['income'] ?? 0);
        $expense = (float)($data['expense'] ?? 0);
        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    };

    $operating  = $aggregate('Operating');
    $investing  = $aggregate('Investing');
    $financing  = $aggregate('Financing');
    $ending_balance = $beginning_balance + $operating['net'] + $investing['net'] + $financing['net'];

    return [
        'period' => ["$start_date to $end_date"],
        'beginning_balance' => $beginning_balance,
        'operating_activities' => $operating,
        'investing_activities' => $investing,
        'financing_activities' => $financing,
        'ending_balance' => $ending_balance
    ];
}

/**
 * Save a report to the database.
 */
function save_report($report_type, $parameters, $file_path)
{
    // Prefer canonical saveReport for consistency (records generated_by)
    if (function_exists('saveReport')) {
        return (bool) saveReport($report_type, $parameters, $file_path);
    }
    global $conn;
    $query = "INSERT INTO acc_reports (report_type, parameters, file_path, generated_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $parameters_json = json_encode($parameters);
    $stmt->bind_param('sss', $report_type, $parameters_json, $file_path);
    return $stmt->execute();
}

/**
 * Fetch a report by its ID.
 */
function fetch_report_by_id($id)
{
    global $conn;

    $query = "SELECT * FROM acc_reports WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/**
 * Fetch all reports with optional filters.
 */
function fetch_all_reports($report_type = null, $start_date = null, $end_date = null)
{
    global $conn;

    $query = "SELECT * FROM acc_reports WHERE 1 = 1";
    $types = '';
    $params = [];

    if ($report_type) {
        $query .= " AND report_type = ?";
        $params[] = $report_type;
        $types .= 's';
    }

    if ($start_date && $end_date) {
        $query .= " AND generated_at BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= 'ss';
    }

    $query .= " ORDER BY generated_at DESC";

    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }

    return $reports;
}
