 <!-- /accounting/models/transaction_model.php -->
 <?php
    /**
     * Transaction Model
     * Database operations for transactions
     */

    /**
     * Fetch all transactions from the database.
     */
    function get_all_transactions($start_date = null, $end_date = null, $category_id = null, $type = null, $account_id = null)
    {
        global $conn;

        $query = "SELECT t.*, c.name as category_name, a.name as account_name, v.name as vendor_name
              FROM acc_transactions t
              LEFT JOIN acc_transaction_categories c ON t.category_id = c.id
              LEFT JOIN acc_ledger_accounts a ON t.account_id = a.id
              LEFT JOIN acc_vendors v ON t.vendor_id = v.id
              WHERE 1=1";
        $params = [];
        $types = '';

        if ($start_date && $end_date) {
            $query .= " AND t.transaction_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= 'ss';
        }

        if ($category_id) {
            $query .= " AND t.category_id = ?";
            $params[] = $category_id;
            $types .= 'i';
        }

        if ($type) {
            $query .= " AND t.type = ?";
            $params[] = $type;
            $types .= 's';
        }

        if ($account_id) {
            $query .= " AND t.account_id = ?";
            $params[] = $account_id;
            $types .= 'i';
        }

        $query .= " ORDER BY t.transaction_date DESC";

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }

        return $transactions;
    }

    /**
     * Get a specific transaction by ID.
     */
    function get_transaction_by_id($id)
    {
        global $conn;

        $query = "SELECT t.*, c.name as category_name, a.name as account_name, v.name as vendor_name
              FROM acc_transactions t
              LEFT JOIN acc_transaction_categories c ON t.category_id = c.id
              LEFT JOIN acc_ledger_accounts a ON t.account_id = a.id
              LEFT JOIN acc_vendors v ON t.vendor_id = v.id
              WHERE t.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Add a new transaction.
     */
    function add_new_transaction($category_id, $amount, $transaction_date, $description, $type, $account_id = null, $vendor_id = null)
    {
        global $conn;

        $query = "INSERT INTO acc_transactions (category_id, amount, transaction_date, description, type, account_id, vendor_id, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('idsssii', $category_id, $amount, $transaction_date, $description, $type, $account_id, $vendor_id);

        return $stmt->execute();
    }

    /**
     * Update an existing transaction.
     */
    function update_existing_transaction($id, $category_id, $amount, $transaction_date, $description, $type, $account_id = null, $vendor_id = null)
    {
        global $conn;

        $query = "UPDATE acc_transactions
              SET category_id = ?, amount = ?, transaction_date = ?, description = ?, type = ?, account_id = ?, vendor_id = ?
              WHERE id = ?";
        $stmt = $conn->prepare($query);
        // Types: i (category_id), d (amount), s (date), s (desc), s (type), i (account_id), i (vendor_id), i (id)
        $stmt->bind_param('idsssiii', $category_id, $amount, $transaction_date, $description, $type, $account_id, $vendor_id, $id);

        return $stmt->execute();
    }

    /**
     * Remove a transaction from the database.
     */
    function delete_transaction_by_id($id)
    {
        global $conn;

        $query = "DELETE FROM acc_transactions WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Get recent transactions.
     */
    function get_recent_transactions($limit = 5)
    {
        global $conn;

        $query = "SELECT t.*, c.name as category_name, a.name as account_name
              FROM acc_transactions t
              LEFT JOIN acc_transaction_categories c ON t.category_id = c.id
              LEFT JOIN acc_ledger_accounts a ON t.account_id = a.id
              ORDER BY t.transaction_date DESC
              LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }

        return $transactions;
    }

    /**
     * Calculate totals.
     */
    function calculate_transaction_totals($start_date = null, $end_date = null)
    {
        global $conn;

        $query = "SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expense
              FROM acc_transactions 
              WHERE 1=1";
        $params = [];
        $types = '';

        if ($start_date && $end_date) {
            $query .= " AND transaction_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= 'ss';
        }

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $income = $row['income'] ?? 0;
        $expense = $row['expense'] ?? 0;
        $net = $income - $expense;

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $net
        ];
    }

    /**
     * Get category breakdown.
     */
    function get_category_breakdown($start_date, $end_date, $type)
    {
        global $conn;

        $query = "SELECT c.name, SUM(t.amount) as total
              FROM acc_transactions t
              JOIN acc_transaction_categories c ON t.category_id = c.id
              WHERE t.type = ? AND t.transaction_date BETWEEN ? AND ?
              GROUP BY c.name
              ORDER BY total DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sss', $type, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $breakdown = [];
        while ($row = $result->fetch_assoc()) {
            $breakdown[] = $row;
        }

        return $breakdown;
    }
