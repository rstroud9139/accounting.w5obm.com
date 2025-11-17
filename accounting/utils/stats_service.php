<?php

/**
 * Accounting Stats Service
 * Centralized aggregation helpers for dashboard and reports.
 */

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Get income/expense/net totals and count for a date range.
 * @return array{income:float, expenses:float, net_balance:float, transaction_count:int}
 */
function get_transaction_totals(string $start_date, string $end_date): array
{
    global $conn;

    $totals = [
        'income' => 0.0,
        'expenses' => 0.0,
        'net_balance' => 0.0,
        'transaction_count' => 0,
    ];

    // Income
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM acc_transactions WHERE type = 'Income' AND transaction_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $totals['income'] = (float)($row['total'] ?? 0);
    $stmt->close();

    // Expenses
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM acc_transactions WHERE type = 'Expense' AND transaction_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $totals['expenses'] = (float)($row['total'] ?? 0);
    $stmt->close();

    // Count
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM acc_transactions WHERE transaction_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $totals['transaction_count'] = (int)($row['cnt'] ?? 0);
    $stmt->close();

    $totals['net_balance'] = $totals['income'] - $totals['expenses'];

    return $totals;
}

/**
 * Get site-wide cash balance (Income - Expense across all time).
 */
function get_cash_balance(): float
{
    global $conn;

    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN type='Income' THEN amount ELSE 0 END) - SUM(CASE WHEN type='Expense' THEN amount ELSE 0 END),0) AS cash_balance FROM acc_transactions");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['cash_balance'] ?? 0);
}

/**
 * Get total asset book value from acc_assets if table exists.
 */
function get_asset_value(): float
{
    global $conn;

    if (!function_exists('tableExists') || !tableExists('acc_assets')) {
        return 0.0;
    }
    $stmt = $conn->prepare("SELECT COALESCE(SUM(value),0) AS total FROM acc_assets");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

/**
 * Cash flow statement aggregates by category groups (Operating/Investing/Financing).
 * Returns beginning balance (before start_date), group subtotals, and ending balance.
 * @return array{period:array,beginning_balance:float,operating_activities:array,investing_activities:array,financing_activities:array,ending_balance:float}
 */
function get_cash_flow_statement(string $start_date, string $end_date): array
{
    global $conn;

    // Beginning balance
    $stmt = $conn->prepare("SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
              FROM acc_transactions 
              WHERE transaction_date < ?");
    $stmt->bind_param('s', $start_date);
    $stmt->execute();
    $beginning_balance = (float)($stmt->get_result()->fetch_assoc()['balance'] ?? 0);
    $stmt->close();

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
 * Get income totals grouped by category for a date range.
 * @return array<int,array{name:string,total:float}>
 */
function get_income_by_category(string $start_date, string $end_date, ?int $limit = null): array
{
    global $conn;
    $sql = "SELECT c.name, SUM(t.amount) AS total FROM acc_transactions t JOIN acc_transaction_categories c ON t.category_id=c.id WHERE t.type='Income' AND t.transaction_date BETWEEN ? AND ? GROUP BY c.name ORDER BY total DESC";
    if ($limit && $limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['total'] = (float)$r['total'];
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

/**
 * Get expense totals grouped by category for a date range.
 * @return array<int,array{name:string,total:float}>
 */
function get_expenses_by_category(string $start_date, string $end_date, ?int $limit = null): array
{
    global $conn;
    $sql = "SELECT c.name, SUM(t.amount) AS total FROM acc_transactions t JOIN acc_transaction_categories c ON t.category_id=c.id WHERE t.type='Expense' AND t.transaction_date BETWEEN ? AND ? GROUP BY c.name ORDER BY total DESC";
    if ($limit && $limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['total'] = (float)$r['total'];
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

/**
 * Get monthly summary (income, expenses, net) for a given month.
 * @return array{year:int,month:int,income:float,expenses:float,net:float}
 */
function get_monthly_summary(int $year, int $month): array
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));
    $totals = get_transaction_totals($start, $end);
    return [
        'year' => $year,
        'month' => $month,
        'income' => $totals['income'],
        'expenses' => $totals['expenses'],
        'net' => $totals['net_balance'],
    ];
}

/**
 * Year-to-date income statement aggregation.
 * Returns monthly breakdown plus YTD totals (income, expenses, net).
 * @return array{year:int,monthly:array<int,array{month:int,income:float,expenses:float,net:float,display:string}>,totals:array{income:float,expenses:float,net:float}}
 */
function get_ytd_income_statement(int $year): array
{
    $monthly = [];
    $total_income = 0.0;
    $total_expenses = 0.0;
    for ($m = 1; $m <= 12; $m++) {
        $start = sprintf('%04d-%02d-01', $year, $m);
        if (strtotime($start) > time()) {
            break; // stop at future months
        }
        $summary = get_monthly_summary($year, $m);
        $monthly[$m] = [
            'month' => $m,
            'income' => $summary['income'],
            'expenses' => $summary['expenses'],
            'net' => $summary['net'],
            'display' => date('F Y', strtotime($start)),
        ];
        $total_income += $summary['income'];
        $total_expenses += $summary['expenses'];
    }
    return [
        'year' => $year,
        'monthly' => $monthly,
        'totals' => [
            'income' => $total_income,
            'expenses' => $total_expenses,
            'net' => $total_income - $total_expenses,
        ],
    ];
}
