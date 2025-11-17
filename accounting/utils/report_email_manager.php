<?php

/**
 * Email Report Distribution System - W5OBM Accounting System
 * File: /accounting/utils/report_email_manager.php
 * Purpose: Manage email distribution of accounting reports
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Uses existing emailService and follows Website Guidelines
 */

// Security check - ensure this file is included properly
if (!defined('SECURE_ACCESS')) {
    if (!isset($_SESSION) || !function_exists('isAuthenticated')) {
        die('Direct access not permitted');
    }
}

// Use accounting email bridge to decouple from site EmailService
require_once __DIR__ . '/../lib/email_bridge.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Email a report to specified recipients
 * @param int $report_id Report ID
 * @param array $recipients Array of email addresses
 * @param array $options Email options
 * @return array Results array
 */
function emailReport($report_id, $recipients, $options = [])
{
    try {
        // Validate report exists
        $report = getReportById($report_id);
        if (!$report) {
            throw new Exception("Report not found");
        }

        // Check permissions
        $user_id = getCurrentUserId();
        if (!hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to email reports");
        }

        // Validate recipients
        if (empty($recipients) || !is_array($recipients)) {
            throw new Exception("No valid recipients provided");
        }

        // use email bridge
        $results = [];

        // Prepare email content
        $subject = $options['subject'] ?? generateReportEmailSubject($report);
        $template_params = [
            'report_name' => ucwords(str_replace('_', ' ', $report['report_type'])),
            'generated_date' => date('F j, Y', strtotime($report['generated_at'])),
            'generated_by' => $report['generated_by_username'] ?? 'System',
            'additional_message' => $options['message'] ?? '',
            'club_name' => 'W5OBM Amateur Radio Club',
            'report_id' => $report_id
        ];

        foreach ($recipients as $recipient) {
            // Validate email address
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $results[] = [
                    'email' => $recipient,
                    'success' => false,
                    'message' => 'Invalid email address'
                ];
                continue;
            }

            try {
                // Send email using template
                $result = accounting_email_send_template($recipient, 'accounting_report_email', $template_params, $subject);

                $results[] = [
                    'email' => $recipient,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];

                // Log successful sends
                if ($result['success']) {
                    logActivity(
                        $user_id,
                        'report_emailed',
                        'acc_reports',
                        $report_id,
                        "Emailed {$report['report_type']} report to $recipient"
                    );
                }
            } catch (Exception $e) {
                $results[] = [
                    'email' => $recipient,
                    'success' => false,
                    'message' => 'Failed to send: ' . $e->getMessage()
                ];

                logError("Error emailing report to $recipient: " . $e->getMessage(), 'accounting');
            }

            // Small delay to prevent overwhelming the email server
            usleep(500000); // 0.5 seconds
        }

        return $results;
    } catch (Exception $e) {
        logError("Error in emailReport: " . $e->getMessage(), 'accounting');
        return [
            [
                'email' => 'system',
                'success' => false,
                'message' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Generate appropriate subject line for report email
 * @param array $report Report data
 * @return string Email subject
 */
function generateReportEmailSubject($report)
{
    $report_name = ucwords(str_replace('_', ' ', $report['report_type']));
    $date = date('F j, Y', strtotime($report['generated_at']));

    return "W5OBM $report_name - $date";
}

/**
 * Email monthly financial summary to specified recipients
 * @param int $month Month (1-12)
 * @param int $year Year
 * @param array $recipients Email addresses
 * @return array Results
 */
function emailMonthlySummary($month, $year, $recipients)
{
    try {
        // Generate summary data
        $summary_data = generateMonthlySummaryData($month, $year);

        // use email bridge
        $results = [];

        $month_name = date('F', mktime(0, 0, 0, $month, 1));
        $subject = "W5OBM Monthly Financial Summary - $month_name $year";

        $template_params = [
            'month_name' => $month_name,
            'year' => $year,
            'total_income' => $summary_data['total_income'],
            'total_expenses' => $summary_data['total_expenses'],
            'net_income' => $summary_data['net_income'],
            'transaction_count' => $summary_data['transaction_count'],
            'top_income_categories' => $summary_data['top_income_categories'],
            'top_expense_categories' => $summary_data['top_expense_categories'],
            'cash_balance' => $summary_data['cash_balance'],
            'club_name' => 'W5OBM Amateur Radio Club'
        ];

        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $results[] = [
                    'email' => $recipient,
                    'success' => false,
                    'message' => 'Invalid email address'
                ];
                continue;
            }

            $result = accounting_email_send_template($recipient, 'monthly_financial_summary', $template_params, $subject);

            $results[] = [
                'email' => $recipient,
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }

        return $results;
    } catch (Exception $e) {
        logError("Error emailing monthly summary: " . $e->getMessage(), 'accounting');
        return [
            [
                'email' => 'system',
                'success' => false,
                'message' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Generate monthly summary data
 * @param int $month Month
 * @param int $year Year
 * @return array Summary data
 */
function generateMonthlySummaryData($month, $year)
{
    global $conn;

    try {
        $start_date = sprintf("%04d-%02d-01", $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));

        // Get totals
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) as total_expenses,
                COUNT(*) as transaction_count
            FROM acc_transactions 
            WHERE transaction_date BETWEEN ? AND ?
        ");

        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $totals = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total_income = floatval($totals['total_income'] ?? 0);
        $total_expenses = floatval($totals['total_expenses'] ?? 0);
        $net_income = $total_income - $total_expenses;

        // Get top income categories
        $stmt = $conn->prepare("
            SELECT c.name, SUM(t.amount) as total
            FROM acc_transactions t
            JOIN acc_transaction_categories c ON t.category_id = c.id
            WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY total DESC
            LIMIT 5
        ");

        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $income_result = $stmt->get_result();

        $top_income_categories = [];
        while ($row = $income_result->fetch_assoc()) {
            $top_income_categories[] = $row;
        }
        $stmt->close();

        // Get top expense categories
        $stmt = $conn->prepare("
            SELECT c.name, SUM(t.amount) as total
            FROM acc_transactions t
            JOIN acc_transaction_categories c ON t.category_id = c.id
            WHERE t.type = 'Expense' AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY total DESC
            LIMIT 5
        ");

        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $expense_result = $stmt->get_result();

        $top_expense_categories = [];
        while ($row = $expense_result->fetch_assoc()) {
            $top_expense_categories[] = $row;
        }
        $stmt->close();

        // Get current cash balance
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS cash_balance
            FROM acc_transactions 
            WHERE transaction_date <= ?
        ");

        $stmt->bind_param('s', $end_date);
        $stmt->execute();
        $balance_result = $stmt->get_result()->fetch_assoc();
        $cash_balance = floatval($balance_result['cash_balance'] ?? 0);
        $stmt->close();

        return [
            'total_income' => $total_income,
            'total_expenses' => $total_expenses,
            'net_income' => $net_income,
            'transaction_count' => intval($totals['transaction_count'] ?? 0),
            'top_income_categories' => $top_income_categories,
            'top_expense_categories' => $top_expense_categories,
            'cash_balance' => $cash_balance
        ];
    } catch (Exception $e) {
        logError("Error generating monthly summary data: " . $e->getMessage(), 'accounting');
        return [
            'total_income' => 0,
            'total_expenses' => 0,
            'net_income' => 0,
            'transaction_count' => 0,
            'top_income_categories' => [],
            'top_expense_categories' => [],
            'cash_balance' => 0
        ];
    }
}

/**
 * Check if table exists
 * @param string $table_name Table name
 * @return bool
 */
function tableExists($table_name)
{
    global $conn;

    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param('s', $table_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    return $exists;
}
