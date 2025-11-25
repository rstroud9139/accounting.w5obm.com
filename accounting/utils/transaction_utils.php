<?php
// /accounting/utils/transaction_utils.php
    /**
     * Transaction Utilities
     * Helper functions for transactions
     */

    /**
     * Calculate the total income from all transactions.
     */
    function calculate_total_income($conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $query = "SELECT SUM(amount) AS total FROM acc_transactions WHERE type = 'Income'";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }

    /**
     * Calculate the total expenses from all transactions.
     */
    function calculate_total_expenses($conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $query = "SELECT SUM(amount) AS total FROM acc_transactions WHERE type = 'Expense'";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }

    /**
     * Calculate the net balance (income - expenses).
     */
    function calculate_net_balance($conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $total_income = calculate_total_income($conn);
        $total_expenses = calculate_total_expenses($conn);

        return $total_income - $total_expenses;
    }

    /**
     * Calculate the total assets value.
     */
    function calculate_total_assets($conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $query = "SELECT SUM(value) AS total FROM acc_assets";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }

    /**
     * Calculate the total donations.
     */
    function calculate_total_donations($conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $query = "SELECT SUM(amount) AS total FROM acc_donations";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }

    /**
     * Get monthly transaction summary for a year.
     */
    function get_monthly_transaction_summary($year, $conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $summary = [];

        // Initialize monthly data
        for ($month = 1; $month <= 12; $month++) {
            $month_name = date('F', mktime(0, 0, 0, $month, 1));
            $summary[$month] = [
                'month' => $month_name,
                'income' => 0,
                'expenses' => 0,
                'net' => 0
            ];
        }

        // Get actual monthly data
        $query = "SELECT 
                MONTH(transaction_date) AS month,
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expenses
              FROM acc_transactions
              WHERE YEAR(transaction_date) = ?
              GROUP BY MONTH(transaction_date)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $month = (int)$row['month'];
            $summary[$month]['income'] = $row['income'];
            $summary[$month]['expenses'] = $row['expenses'];
            $summary[$month]['net'] = $row['income'] - $row['expenses'];
        }

        return $summary;
    }

    /**
     * Get transaction totals by category for a given period.
     */
    function get_transactions_by_category($start_date, $end_date, $type = null, $conn = null)
    {
        if ($conn === null) {
            global $conn;
        }
        $query = "SELECT 
                c.name AS category_name,
                SUM(t.amount) AS total
              FROM acc_transactions t
              JOIN acc_transaction_categories c ON t.category_id = c.id
              WHERE t.transaction_date BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
        $types = 'ss';

        if ($type) {
            $query .= " AND t.type = ?";
            $params[] = $type;
            $types .= 's';
        }

        $query .= " GROUP BY c.name ORDER BY total DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }

    /**
     * Get transaction details for a specific period.
     */
    function get_transaction_details($start_date, $end_date, $conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $query = "SELECT 
                t.*,
                c.name AS category_name,
                a.name AS account_name
              FROM acc_transactions t
              LEFT JOIN acc_transaction_categories c ON t.category_id = c.id
              LEFT JOIN acc_ledger_accounts a ON t.account_id = a.id
              WHERE t.transaction_date BETWEEN ? AND ?
              ORDER BY t.transaction_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }

        return $transactions;
    }

    /**
     * Calculate financial ratios.
     */
    function calculate_financial_ratios($start_date, $end_date, $conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        // Get income and expense totals
        $query = "SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expenses
              FROM acc_transactions
              WHERE transaction_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $totals = $result->fetch_assoc();

        $income = $totals['income'] ?? 0;
        $expenses = $totals['expenses'] ?? 0;

        // Calculate ratios
        $ratios = [];

        // Expense ratio (Expenses / Income)
        $ratios['expense_ratio'] = $income > 0 ? ($expenses / $income) : 0;

        // Program expense ratio (Program Expenses / Total Expenses)
        $query = "SELECT 
                SUM(t.amount) AS program_expenses
              FROM acc_transactions t
              JOIN acc_transaction_categories c ON t.category_id = c.id
              WHERE t.type = 'Expense' 
                AND c.name LIKE '%Program%'
                AND t.transaction_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $program_expenses = $result->fetch_assoc()['program_expenses'] ?? 0;

        $ratios['program_expense_ratio'] = $expenses > 0 ? ($program_expenses / $expenses) : 0;

        return $ratios;
    }
