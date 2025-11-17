 <!-- /accounting/models/ledger_model.php -->
 <?php
    /**
     * Ledger Model
     * Database operations for ledger accounts
     */

    /**
     * Fetch all ledger accounts from the database.
     */
    function get_all_ledger_accounts()
    {
        global $conn;

        $query = "SELECT l.*, c.name as category_name 
              FROM acc_ledger_accounts l 
              LEFT JOIN acc_transaction_categories c ON l.category_id = c.id 
              ORDER BY l.name ASC";
        $result = $conn->query($query);

        if (!$result) {
            return [];
        }

        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }

        return $accounts;
    }

    /**
     * Get a specific ledger account by ID.
     */
    function get_ledger_account_by_id($id)
    {
        global $conn;

        $query = "SELECT l.*, c.name as category_name 
              FROM acc_ledger_accounts l 
              LEFT JOIN acc_transaction_categories c ON l.category_id = c.id 
              WHERE l.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Get ledger accounts by category.
     */
    function get_ledger_accounts_by_category($category_id)
    {
        global $conn;

        $query = "SELECT * FROM acc_ledger_accounts WHERE category_id = ? ORDER BY name ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            return [];
        }

        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }

        return $accounts;
    }

    /**
     * Add a new ledger account.
     */
    function add_new_ledger_account($name, $description, $category_id)
    {
        global $conn;

        $query = "INSERT INTO acc_ledger_accounts (name, description, category_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $name, $description, $category_id);

        return $stmt->execute();
    }

    /**
     * Update an existing ledger account.
     */
    function update_existing_ledger_account($id, $name, $description, $category_id)
    {
        global $conn;

        $query = "UPDATE acc_ledger_accounts SET name = ?, description = ?, category_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssii', $name, $description, $category_id, $id);

        return $stmt->execute();
    }

    /**
     * Remove a ledger account from the database.
     */
    function delete_ledger_account_by_id($id)
    {
        global $conn;

        $query = "DELETE FROM acc_ledger_accounts WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Check if a ledger account is being used by any transactions.
     */
    function check_ledger_account_in_use($id)
    {
        global $conn;

        $query = "SELECT COUNT(*) as count FROM acc_transactions WHERE account_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['count'] > 0;
    }

    /**
     * Reassign transactions from one ledger account to another.
     */
    function reassign_ledger_transactions($old_account_id, $new_account_id)
    {
        global $conn;

        $query = "UPDATE acc_transactions SET account_id = ? WHERE account_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $new_account_id, $old_account_id);

        return $stmt->execute();
    }

    /**
     * Get ledger account balance.
     */
    function get_ledger_account_balance($account_id, $end_date = null)
    {
        global $conn;

        if ($end_date === null) {
            $end_date = date('Y-m-d');
        }

        $query = "SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expense
              FROM acc_transactions 
              WHERE account_id = ? AND transaction_date <= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('is', $account_id, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $income = $row['income'] ?? 0;
        $expense = $row['expense'] ?? 0;

        return $income - $expense;
    }
