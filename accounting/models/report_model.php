<?php
// /accounting/models/report_model.php
    /**
     * Report Model
     * Database operations for reports
     */

    /**
     * Fetch all reports from the database.
     */
    function get_all_reports($report_type = null, $start_date = null, $end_date = null)
    {
        global $conn;

        $query = "SELECT * FROM acc_reports WHERE 1=1";
        $params = [];
        $types = '';

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

    /**
     * Get a specific report by ID.
     */
    function get_report_by_id($id)
    {
        global $conn;

        $query = "SELECT * FROM acc_reports WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Save a new report.
     */
    function save_new_report($report_type, $parameters, $file_path)
    {
        global $conn;

        $query = "INSERT INTO acc_reports (report_type, parameters, file_path, generated_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $parameters_json = json_encode($parameters);
        $stmt->bind_param('sss', $report_type, $parameters_json, $file_path);

        return $stmt->execute();
    }

    /**
     * Delete a report from the database and file system.
     */
    function delete_report_by_id($id)
    {
        global $conn;

        // First, get the file path
        $query = "SELECT file_path FROM acc_reports WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();

        if ($report && file_exists($report['file_path'])) {
            // Delete the file
            unlink($report['file_path']);
        }

        // Now delete the record
        $query = "DELETE FROM acc_reports WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Generate income statement data.
     */
    function generate_income_statement_data($month, $year)
    {
        global $conn;

        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));

        // Get income categories
        $income_query = "SELECT c.id, c.name, SUM(t.amount) as total
                    FROM acc_transactions t
                    JOIN acc_transaction_categories c ON t.category_id = c.id
                    WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?
                    GROUP BY c.id, c.name
                    ORDER BY total DESC";
        $stmt = $conn->prepare($income_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $income_result = $stmt->get_result();

        $income = [];
        $total_income = 0;
        while ($row = $income_result->fetch_assoc()) {
            $income[] = $row;
            $total_income += $row['total'];
        }

        // Get expense categories
        $expense_query = "SELECT c.id, c.name, SUM(t.amount) as total
                      FROM acc_transactions t
                      JOIN acc_transaction_categories c ON t.category_id = c.id
                      WHERE t.type = 'Expense' AND t.transaction_date BETWEEN ? AND ?
                      GROUP BY c.id, c.name
                      ORDER BY total DESC";
        $stmt = $conn->prepare($expense_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $expense_result = $stmt->get_result();

        $expenses = [];
        $total_expenses = 0;
        while ($row = $expense_result->fetch_assoc()) {
            $expenses[] = $row;
            $total_expenses += $row['total'];
        }

        $net_income = $total_income - $total_expenses;

        return [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'income' => $income,
            'total_income' => $total_income,
            'expenses' => $expenses,
            'total_expenses' => $total_expenses,
            'net_income' => $net_income
        ];
    }

    /**
     * Generate balance sheet data.
     */
    function generate_balance_sheet_data($date = null)
    {
        global $conn;

        if ($date === null) {
            $date = date('Y-m-d');
        }

        // Assets
        $assets_query = "SELECT SUM(value) AS total FROM acc_assets WHERE acquisition_date <= ?";
        $stmt = $conn->prepare($assets_query);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $assets_result = $stmt->get_result();
        $total_physical_assets = $assets_result->fetch_assoc()['total'] ?? 0;

        // Cash (from transactions)
        $cash_query = "SELECT 
                    SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                    SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
                  FROM acc_transactions 
                  WHERE transaction_date <= ?";
        $stmt = $conn->prepare($cash_query);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $cash_result = $stmt->get_result();
        $cash_balance = $cash_result->fetch_assoc()['balance'] ?? 0;

        // Liabilities (if table exists)
        $total_liabilities = 0;
        $liabilities_query = "SHOW TABLES LIKE 'acc_liabilities'";
        $result = $conn->query($liabilities_query);

        if ($result->num_rows > 0) {
            $query = "SELECT SUM(amount) AS total FROM acc_liabilities WHERE date_incurred <= ? AND status = 'Active'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $liabilities_result = $stmt->get_result();
            $total_liabilities = $liabilities_result->fetch_assoc()['total'] ?? 0;
        }

        // Equity (Assets - Liabilities)
        $total_assets = $total_physical_assets + $cash_balance;
        $total_equity = $total_assets - $total_liabilities;

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
    }

    /**
     * Generate cash flow statement data.
     */
    function generate_cash_flow_data($start_date, $end_date)
    {
        global $conn;

        // Beginning balance
        $balance_query = "SELECT 
                        SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                        SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
                      FROM acc_transactions 
                      WHERE transaction_date < ?";
        $stmt = $conn->prepare($balance_query);
        $stmt->bind_param('s', $start_date);
        $stmt->execute();
        $balance_result = $stmt->get_result();
        $beginning_balance = $balance_result->fetch_assoc()['balance'] ?? 0;

        // Operating activities (use acc_categories mapping)
        $operating_query = "SELECT 
                          SUM(CASE WHEN t.type = 'Income' THEN t.amount ELSE 0 END) AS income,
                          SUM(CASE WHEN t.type = 'Expense' THEN t.amount ELSE 0 END) AS expense
                        FROM acc_transactions t
                        JOIN acc_transaction_categories c ON t.category_id = c.id
                        JOIN acc_categories ac ON c.category_id = ac.id
                        WHERE t.transaction_date BETWEEN ? AND ?
                        AND ac.name = 'Operating'";
        $stmt = $conn->prepare($operating_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $operating_result = $stmt->get_result();
        $operating = $operating_result->fetch_assoc();
        $operating_income = $operating['income'] ?? 0;
        $operating_expense = $operating['expense'] ?? 0;
        $net_operating = $operating_income - $operating_expense;

        // Investing activities
        $investing_query = "SELECT 
                          SUM(CASE WHEN t.type = 'Income' THEN t.amount ELSE 0 END) AS income,
                          SUM(CASE WHEN t.type = 'Expense' THEN t.amount ELSE 0 END) AS expense
                        FROM acc_transactions t
                        JOIN acc_transaction_categories c ON t.category_id = c.id
                        JOIN acc_categories ac ON c.category_id = ac.id
                        WHERE t.transaction_date BETWEEN ? AND ?
                        AND ac.name = 'Investing'";
        $stmt = $conn->prepare($investing_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $investing_result = $stmt->get_result();
        $investing = $investing_result->fetch_assoc();
        $investing_income = $investing['income'] ?? 0;
        $investing_expense = $investing['expense'] ?? 0;
        $net_investing = $investing_income - $investing_expense;

        // Financing activities
        $financing_query = "SELECT 
                          SUM(CASE WHEN t.type = 'Income' THEN t.amount ELSE 0 END) AS income,
                          SUM(CASE WHEN t.type = 'Expense' THEN t.amount ELSE 0 END) AS expense
                        FROM acc_transactions t
                        JOIN acc_transaction_categories c ON t.category_id = c.id
                        JOIN acc_categories ac ON c.category_id = ac.id
                        WHERE t.transaction_date BETWEEN ? AND ?
                        AND ac.name = 'Financing'";
        $stmt = $conn->prepare($financing_query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $financing_result = $stmt->get_result();
        $financing = $financing_result->fetch_assoc();
        $financing_income = $financing['income'] ?? 0;
        $financing_expense = $financing['expense'] ?? 0;
        $net_financing = $financing_income - $financing_expense;

        // Ending balance
        $ending_balance = $beginning_balance + $net_operating + $net_investing + $net_financing;

        return [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'beginning_balance' => $beginning_balance,
            'operating_activities' => [
                'income' => $operating_income,
                'expense' => $operating_expense,
                'net' => $net_operating
            ],
            'investing_activities' => [
                'income' => $investing_income,
                'expense' => $investing_expense,
                'net' => $net_investing
            ],
            'financing_activities' => [
                'income' => $financing_income,
                'expense' => $financing_expense,
                'net' => $net_financing
            ],
            'ending_balance' => $ending_balance
        ];
    }
