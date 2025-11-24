<?php

/**
 * Report Controller - W5OBM Accounting System
 * File: /accounting/controllers/reportController.php
 * Purpose: Complete report generation and management controller
 * SECURITY: All operations require authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Build normalized period metadata for financial statements.
 */
function buildStatementPeriod(string $periodType, ?int $year = null, ?int $value = null): array
{
    $periodType = strtolower($periodType);
    $year = $year ?: (int)date('Y');
    $today = new DateTimeImmutable('today');

    $sanitizeMonth = static function ($month) {
        $month = (int)$month;
        if ($month < 1) {
            return 1;
        }
        if ($month > 12) {
            return 12;
        }
        return $month;
    };

    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-12-31', $year);
    $label = 'Full Year ' . $year;
    $displayValue = null;

    switch ($periodType) {
        case 'monthly':
            $month = $sanitizeMonth($value ?? (int)date('n'));
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));
            $label = date('F Y', strtotime($start));
            $displayValue = $month;
            break;
        case 'quarterly':
            $quarter = $value ?? (int)ceil(date('n') / 3);
            if ($quarter < 1) {
                $quarter = 1;
            }
            if ($quarter > 4) {
                $quarter = 4;
            }
            $startMonth = (($quarter - 1) * 3) + 1;
            $start = sprintf('%04d-%02d-01', $year, $startMonth);
            $endMonth = $startMonth + 2;
            $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));
            $label = sprintf('Q%d %d', $quarter, $year);
            $displayValue = $quarter;
            break;
        case 'ytd':
            $cutoffMonth = $sanitizeMonth($value ?? ((int)$today->format('Y') === $year ? (int)$today->format('n') : 12));
            $start = sprintf('%04d-01-01', $year);
            $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $cutoffMonth)));
            $label = sprintf('Year to Date %d (through %s)', $year, date('F', strtotime(sprintf('%04d-%02d-01', $year, $cutoffMonth))));
            $displayValue = $cutoffMonth;
            break;
        case 'annual':
        default:
            $periodType = 'annual';
            $label = 'Full Year ' . $year;
            break;
    }

    if ($year === (int)$today->format('Y')) {
        $end = min($end, $today->format('Y-m-d'));
    }

    if (strtotime($end) < strtotime($start)) {
        $end = $start;
    }

    return [
        'type' => $periodType,
        'label' => $label,
        'start_date' => $start,
        'end_date' => $end,
        'year' => $year,
        'value' => $displayValue,
        'slug' => $periodType . '_' . $year . '_' . ($displayValue ?? 'all'),
    ];
}

/**
 * Generate an income statement for arbitrary start/end dates.
 */
function generateIncomeStatementRange(string $startDate, string $endDate, ?string $displayLabel = null): array
{
    global $conn;

    try {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);

        if ($end < $start) {
            throw new Exception('End date must be after start date');
        }

        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        $fetchTotals = static function (string $type) use ($conn, $startStr, $endStr) {
            $stmt = $conn->prepare('
                SELECT c.id, c.name, SUM(t.amount) AS total
                FROM acc_transactions t
                JOIN acc_transaction_categories c ON t.category_id = c.id
                WHERE t.type = ? AND t.transaction_date BETWEEN ? AND ?
                GROUP BY c.id, c.name
                ORDER BY c.name
            ');
            if (!$stmt) {
                throw new Exception('Failed preparing statement: ' . $conn->error);
            }
            $stmt->bind_param('sss', $type, $startStr, $endStr);
            if (!$stmt->execute()) {
                throw new Exception('Failed executing statement: ' . $stmt->error);
            }
            $result = $stmt->get_result();
            $categories = [];
            $total = 0.0;
            while ($row = $result->fetch_assoc()) {
                $amount = (float)$row['total'];
                $categories[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'total' => $amount,
                ];
                $total += $amount;
            }
            $stmt->close();
            return [$categories, $total];
        };

        [$incomeCategories, $totalIncome] = $fetchTotals('Income');
        [$expenseCategories, $totalExpenses] = $fetchTotals('Expense');

        $netIncome = $totalIncome - $totalExpenses;

        logActivity(
            getCurrentUserId(),
            'income_statement_range_generated',
            'acc_reports',
            null,
            sprintf('Generated statement %s through %s', $startStr, $endStr)
        );

        return [
            'period' => [
                'start_date' => $startStr,
                'end_date' => $endStr,
                'display' => $displayLabel ?: ($start->format('M d, Y') . ' - ' . $end->format('M d, Y')),
                'days' => $start->diff($end)->days + 1,
            ],
            'income' => [
                'categories' => $incomeCategories,
                'total' => $totalIncome,
            ],
            'expenses' => [
                'categories' => $expenseCategories,
                'total' => $totalExpenses,
            ],
            'net_income' => $netIncome,
        ];
    } catch (Exception $e) {
        logError('Error generating income statement range: ' . $e->getMessage(), 'accounting');
        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'display' => $displayLabel ?: 'Invalid Period',
            ],
            'income' => ['categories' => [], 'total' => 0],
            'expenses' => ['categories' => [], 'total' => 0],
            'net_income' => 0,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Generate a balance sheet for a given date
 * @param string|null $date Date for balance sheet (defaults to today)
 * @return array Balance sheet data
 */
function generateBalanceSheet($date = null)
{
    global $conn;

    try {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        // Validate date
        if (!strtotime($date)) {
            throw new Exception("Invalid date provided");
        }

        // Assets from physical assets table
        $stmt = $conn->prepare("SELECT SUM(value) AS total FROM acc_assets WHERE acquisition_date <= ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $assets_result = $stmt->get_result()->fetch_assoc();
        $total_physical_assets = floatval($assets_result['total'] ?? 0);
        $stmt->close();

        // Cash (from transactions)
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
            FROM acc_transactions 
            WHERE transaction_date <= ?
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $cash_result = $stmt->get_result()->fetch_assoc();
        $cash_balance = floatval($cash_result['balance'] ?? 0);
        $stmt->close();

        // Liabilities (if table exists)
        $total_liabilities = 0;
        if (tableExists('acc_liabilities')) {
            $stmt = $conn->prepare("
                SELECT SUM(amount) AS total 
                FROM acc_liabilities 
                WHERE date_incurred <= ? AND status = 'Active'
            ");
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $liabilities_result = $stmt->get_result()->fetch_assoc();
            $total_liabilities = floatval($liabilities_result['total'] ?? 0);
            $stmt->close();
        }

        // Calculate totals
        $total_assets = $total_physical_assets + $cash_balance;
        $total_equity = $total_assets - $total_liabilities;

        // Log activity
        logActivity(
            getCurrentUserId(),
            'balance_sheet_generated',
            'acc_reports',
            null,
            "Generated balance sheet for date: $date"
        );

        return [
            'date' => $date,
            'assets' => [
                'cash' => $cash_balance,
                'physical_assets' => $total_physical_assets,
                'total' => $total_assets
            ],
            'liabilities' => $total_liabilities,
            'equity' => $total_equity
        ];
    } catch (Exception $e) {
        logError("Error generating balance sheet: " . $e->getMessage(), 'accounting');
        return [
            'date' => $date ?? date('Y-m-d'),
            'assets' => ['cash' => 0, 'physical_assets' => 0, 'total' => 0],
            'liabilities' => 0,
            'equity' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate an income statement for a given month/year
 * @param int $month Month (1-12)
 * @param int $year Year
 * @return array Income statement data
 */
function generateIncomeStatement($month, $year)
{
    if (!is_numeric($month) || $month < 1 || $month > 12) {
        return [
            'period' => ['month' => $month, 'year' => $year, 'display' => 'Invalid'],
            'income' => ['categories' => [], 'total' => 0],
            'expenses' => ['categories' => [], 'total' => 0],
            'net_income' => 0,
            'error' => 'Invalid month',
        ];
    }

    if (!is_numeric($year) || $year < 2000 || $year > date('Y') + 1) {
        return [
            'period' => ['month' => $month, 'year' => $year, 'display' => 'Invalid'],
            'income' => ['categories' => [], 'total' => 0],
            'expenses' => ['categories' => [], 'total' => 0],
            'net_income' => 0,
            'error' => 'Invalid year',
        ];
    }

    $period = buildStatementPeriod('monthly', (int)$year, (int)$month);
    $statement = generateIncomeStatementRange($period['start_date'], $period['end_date'], $period['label']);

    $statement['period']['month'] = (int)$month;
    $statement['period']['year'] = (int)$year;

    return $statement;
}

/**
 * Save a report to the database
 * @param string $report_type Type of report
 * @param array $parameters Report parameters
 * @param string $file_path Path to saved report file
 * @return bool|int Report ID on success, false on failure
 */
function saveReport($report_type, $parameters, $file_path = null)
{
    global $conn;

    try {
        // Validate inputs
        if (empty($report_type)) {
            throw new Exception("Report type is required");
        }

        $stmt = $conn->prepare("
            INSERT INTO acc_reports (report_type, parameters, file_path, generated_by, generated_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");

        $generated_by = getCurrentUserId();
        $parameters_json = json_encode($parameters);

        $stmt->bind_param('sssi', $report_type, $parameters_json, $file_path, $generated_by);

        if ($stmt->execute()) {
            $report_id = $conn->insert_id;
            $stmt->close();

            // Log activity
            logActivity(
                $generated_by,
                'report_saved',
                'acc_reports',
                $report_id,
                "Saved $report_type report"
            );

            return $report_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error saving report: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get a report by its ID
 * @param int $id Report ID
 * @return array|false Report data or false if not found
 */
function getReportById($id)
{
    global $conn;

    try {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT r.*, u.username AS generated_by_username
            FROM acc_reports r
            LEFT JOIN auth_users u ON r.generated_by = u.id
            WHERE r.id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result && $result['parameters']) {
            $result['parameters'] = json_decode($result['parameters'], true);
        }

        return $result;
    } catch (Exception $e) {
        logError("Error getting report by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all reports with optional filtering
 * @param array $filters Optional filters
 * @param int $limit Optional limit
 * @return array Reports array
 */
function getAllReports($filters = [], $limit = null)
{
    global $conn;

    try {
        $where_conditions = [];
        $params = [];
        $types = '';

        // Build WHERE clause
        if (!empty($filters['report_type'])) {
            $where_conditions[] = "r.report_type = ?";
            $params[] = $filters['report_type'];
            $types .= 's';
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where_conditions[] = "r.generated_at BETWEEN ? AND ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
            $params[] = $filters['end_date'] . ' 23:59:59';
            $types .= 'ss';
        }

        if (!empty($filters['generated_by'])) {
            $where_conditions[] = "r.generated_by = ?";
            $params[] = $filters['generated_by'];
            $types .= 'i';
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        $query = "
            SELECT r.*, u.username AS generated_by_username
            FROM acc_reports r
            LEFT JOIN auth_users u ON r.generated_by = u.id
            $where_clause
            ORDER BY r.generated_at DESC
        ";

        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $reports = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['parameters']) {
                $row['parameters'] = json_decode($row['parameters'], true);
            }
            $reports[] = $row;
        }

        $stmt->close();
        return $reports;
    } catch (Exception $e) {
        logError("Error getting all reports: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Delete a report
 * @param int $id Report ID
 * @return bool Success status
 */
function deleteReport($id)
{
    global $conn;

    try {
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid report ID");
        }

        // Get report details for logging
        $report = getReportById($id);
        if (!$report) {
            throw new Exception("Report not found");
        }

        // Check permissions
        $user_id = getCurrentUserId();
        if (!isAdmin($user_id) && $report['generated_by'] != $user_id) {
            throw new Exception("Insufficient permissions to delete this report");
        }

        // Delete file if it exists
        if (!empty($report['file_path']) && file_exists($report['file_path'])) {
            unlink($report['file_path']);
        }

        $stmt = $conn->prepare("DELETE FROM acc_reports WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $stmt->close();

            // Log activity
            logActivity(
                $user_id,
                'report_deleted',
                'acc_reports',
                $id,
                "Deleted {$report['report_type']} report"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error deleting report: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get expense breakdown by category for a period
 * @param string $start_date Start date
 * @param string $end_date End date
 * @return array Category breakdown
 */
function getExpenseBreakdown($start_date, $end_date)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
            SELECT c.id, c.name, 
                   SUM(t.amount) AS total_amount,
                   COUNT(t.id) AS transaction_count,
                   AVG(t.amount) AS average_amount
            FROM acc_transactions t
            JOIN acc_transaction_categories c ON t.category_id = c.id
            WHERE t.type = 'Expense' AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY total_amount DESC
        ");

        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $breakdown = [];
        $total_expenses = 0;

        while ($row = $result->fetch_assoc()) {
            $amount = floatval($row['total_amount']);
            $breakdown[] = [
                'category_id' => $row['id'],
                'category_name' => $row['name'],
                'total_amount' => $amount,
                'transaction_count' => intval($row['transaction_count']),
                'average_amount' => floatval($row['average_amount'])
            ];
            $total_expenses += $amount;
        }

        // Calculate percentages
        foreach ($breakdown as &$category) {
            $category['percentage'] = $total_expenses > 0 ?
                round(($category['total_amount'] / $total_expenses) * 100, 1) : 0;
        }

        $stmt->close();

        return [
            'categories' => $breakdown,
            'total_expenses' => $total_expenses,
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ]
        ];
    } catch (Exception $e) {
        logError("Error getting expense breakdown: " . $e->getMessage(), 'accounting');
        return [
            'categories' => [],
            'total_expenses' => 0,
            'period' => ['start_date' => $start_date, 'end_date' => $end_date]
        ];
    }
}
