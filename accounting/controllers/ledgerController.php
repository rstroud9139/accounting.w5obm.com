<?php

/**
 * Ledger Controller - W5OBM Accounting System
 * File: /accounting/controllers/ledgerController.php
 * Purpose: Complete ledger account management controller
 * SECURITY: All operations require authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Add a new ledger account
 * @param array $data Ledger account data
 * @return bool|int Account ID on success, false on failure
 */
function addLedgerAccount($data)
{
    global $conn;

    try {
        // Validate required fields
        $required = ['name', 'account_type', 'account_number'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate account type
        $valid_types = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
        if (!in_array($data['account_type'], $valid_types)) {
            throw new Exception("Invalid account type");
        }

        // Check if account number already exists
        $stmt = $conn->prepare("SELECT id FROM acc_ledger_accounts WHERE account_number = ?");
        $stmt->bind_param('s', $data['account_number']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            throw new Exception("Account number already exists");
        }
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO acc_ledger_accounts 
            (name, account_number, account_type, description, parent_account_id, active, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
        ");

        $created_by = getCurrentUserId();
        $parent_account_id = !empty($data['parent_account_id']) ? intval($data['parent_account_id']) : null;
        $description = sanitizeInput($data['description'] ?? '', 'string');

        $stmt->bind_param(
            'ssssii',
            $data['name'],
            $data['account_number'],
            $data['account_type'],
            $description,
            $parent_account_id,
            $created_by
        );

        if ($stmt->execute()) {
            $account_id = $conn->insert_id;
            $stmt->close();

            // Log activity
            logActivity(
                $created_by,
                'ledger_account_created',
                'acc_ledger_accounts',
                $account_id,
                "Created ledger account: {$data['name']} ({$data['account_number']})"
            );

            return $account_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error adding ledger account: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Update an existing ledger account
 * @param int $id Account ID
 * @param array $data Updated account data
 * @return bool Success status
 */
function updateLedgerAccount($id, $data)
{
    global $conn;

    try {
        // Validate ID
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid account ID");
        }

        // Check if account exists
        $existing = getLedgerAccountById($id);
        if (!$existing) {
            throw new Exception("Account not found");
        }

        // Check if account number already exists (excluding current account)
        if (!empty($data['account_number'])) {
            $stmt = $conn->prepare("SELECT id FROM acc_ledger_accounts WHERE account_number = ? AND id != ?");
            $stmt->bind_param('si', $data['account_number'], $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                throw new Exception("Account number already exists");
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("
            UPDATE acc_ledger_accounts 
            SET name = ?, account_number = ?, account_type = ?, description = ?, 
                parent_account_id = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $updated_by = getCurrentUserId();
        $parent_account_id = !empty($data['parent_account_id']) ? intval($data['parent_account_id']) : null;
        $description = sanitizeInput($data['description'] ?? '', 'string');

        $stmt->bind_param(
            'ssssiiii',
            $data['name'],
            $data['account_number'],
            $data['account_type'],
            $description,
            $parent_account_id,
            $updated_by,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Log activity
            logActivity(
                $updated_by,
                'ledger_account_updated',
                'acc_ledger_accounts',
                $id,
                "Updated ledger account: {$data['name']} ({$data['account_number']})"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error updating ledger account: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Delete a ledger account by its ID
 * @param int $id Account ID
 * @param int $reassign_to_id Optional ID to reassign transactions to
 * @return bool Success status
 */
function deleteLedgerAccount($id, $reassign_to_id = null)
{
    global $conn;

    try {
        // Validate ID
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid account ID");
        }

        // Get account details for logging
        $account = getLedgerAccountById($id);
        if (!$account) {
            throw new Exception("Account not found");
        }

        // Check if account has transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_transactions WHERE account_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $transaction_count = intval($result['count']);
        $stmt->close();

        // If has transactions and no reassignment specified, prevent deletion
        if ($transaction_count > 0 && !$reassign_to_id) {
            throw new Exception("Cannot delete account with transactions. Please reassign transactions first.");
        }

        // If has transactions, reassign them
        if ($transaction_count > 0 && $reassign_to_id) {
            if (!is_numeric($reassign_to_id)) {
                throw new Exception("Invalid reassignment account ID");
            }

            // Verify reassignment account exists
            $reassign_account = getLedgerAccountById($reassign_to_id);
            if (!$reassign_account) {
                throw new Exception("Reassignment account not found");
            }

            // Reassign transactions
            $stmt = $conn->prepare("UPDATE acc_transactions SET account_id = ? WHERE account_id = ?");
            $stmt->bind_param('ii', $reassign_to_id, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception("Failed to reassign transactions");
            }
            $stmt->close();

            // Log reassignment
            logActivity(
                getCurrentUserId(),
                'transactions_reassigned',
                'acc_transactions',
                null,
                "Reassigned $transaction_count transactions from account {$account['name']} to {$reassign_account['name']}"
            );
        }

        // Check for child accounts
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_ledger_accounts WHERE parent_account_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $child_count = intval($result['count']);
        $stmt->close();

        if ($child_count > 0) {
            throw new Exception("Cannot delete account with sub-accounts. Please delete or reassign sub-accounts first.");
        }

        // Delete the account
        $stmt = $conn->prepare("DELETE FROM acc_ledger_accounts WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $stmt->close();

            // Log activity
            logActivity(
                getCurrentUserId(),
                'ledger_account_deleted',
                'acc_ledger_accounts',
                $id,
                "Deleted ledger account: {$account['name']} ({$account['account_number']})"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error deleting ledger account: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get a single ledger account by its ID
 * @param int $id Account ID
 * @return array|false Account data or false if not found
 */
function getLedgerAccountById($id)
{
    global $conn;

    try {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT a.*, 
                   p.name AS parent_account_name,
                   cu.username AS created_by_username,
                   uu.username AS updated_by_username
            FROM acc_ledger_accounts a 
            LEFT JOIN acc_ledger_accounts p ON a.parent_account_id = p.id
            LEFT JOIN auth_users cu ON a.created_by = cu.id
            LEFT JOIN auth_users uu ON a.updated_by = uu.id
            WHERE a.id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        logError("Error getting ledger account by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all ledger accounts with optional filtering
 * @param array $filters Optional filters
 * @param array $options Optional limit, offset, order
 * @return array Accounts array
 */
function getAllLedgerAccounts($filters = [], $options = [])
{
    global $conn;

    try {
        $where_conditions = [];
        $params = [];
        $types = '';

        // Build WHERE clause
        if (!empty($filters['account_type'])) {
            $where_conditions[] = "a.account_type = ?";
            $params[] = $filters['account_type'];
            $types .= 's';
        }

        if (!empty($filters['parent_account_id'])) {
            $where_conditions[] = "a.parent_account_id = ?";
            $params[] = $filters['parent_account_id'];
            $types .= 'i';
        }

        if (isset($filters['active'])) {
            $where_conditions[] = "a.active = ?";
            $params[] = $filters['active'] ? 1 : 0;
            $types .= 'i';
        } else {
            // Default to active accounts only
            $where_conditions[] = "a.active = 1";
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(a.name LIKE ? OR a.account_number LIKE ? OR a.description LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        // Order clause
        $order_by = $options['order_by'] ?? 'a.account_number ASC, a.name ASC';

        $query = "
            SELECT a.*, 
                   p.name AS parent_account_name,
                   (SELECT COUNT(*) FROM acc_transactions WHERE account_id = a.id) AS transaction_count,
                   (SELECT COUNT(*) FROM acc_ledger_accounts WHERE parent_account_id = a.id) AS child_count
            FROM acc_ledger_accounts a 
            LEFT JOIN acc_ledger_accounts p ON a.parent_account_id = p.id
            $where_clause
            ORDER BY $order_by
        ";

        // Add limit and offset if specified
        if (!empty($options['limit'])) {
            $query .= " LIMIT ?";
            $params[] = $options['limit'];
            $types .= 'i';

            if (!empty($options['offset'])) {
                $query .= " OFFSET ?";
                $params[] = $options['offset'];
                $types .= 'i';
            }
        }

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }

        $stmt->close();
        return $accounts;
    } catch (Exception $e) {
        logError("Error getting all ledger accounts: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Get chart of accounts in hierarchical structure
 * @param string $account_type Optional filter by account type
 * @return array Hierarchical array of accounts
 */
function getChartOfAccounts($account_type = null)
{
    global $conn;

    try {
        $where_clause = "WHERE a.active = 1";
        $params = [];
        $types = '';

        if ($account_type) {
            $where_clause .= " AND a.account_type = ?";
            $params[] = $account_type;
            $types .= 's';
        }

        $query = "
            SELECT a.*, 
                   p.name AS parent_account_name,
                   (SELECT COUNT(*) FROM acc_transactions WHERE account_id = a.id) AS transaction_count
            FROM acc_ledger_accounts a 
            LEFT JOIN acc_ledger_accounts p ON a.parent_account_id = p.id
            $where_clause
            ORDER BY a.account_type, a.account_number ASC, a.name ASC
        ";

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $all_accounts = [];
        while ($row = $result->fetch_assoc()) {
            $all_accounts[] = $row;
        }
        $stmt->close();

        // Build hierarchical structure
        $chart = [];
        $indexed_accounts = [];

        // First pass: index all accounts
        foreach ($all_accounts as $account) {
            $indexed_accounts[$account['id']] = $account;
            $indexed_accounts[$account['id']]['children'] = [];
        }

        // Second pass: build hierarchy
        foreach ($all_accounts as $account) {
            if ($account['parent_account_id']) {
                // Add as child to parent
                if (isset($indexed_accounts[$account['parent_account_id']])) {
                    $indexed_accounts[$account['parent_account_id']]['children'][] = &$indexed_accounts[$account['id']];
                }
            } else {
                // Top level account
                $chart[] = &$indexed_accounts[$account['id']];
            }
        }

        return $chart;
    } catch (Exception $e) {
        logError("Error getting chart of accounts: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Get account balance for a specific account
 * @param int $account_id Account ID
 * @param string $as_of_date Optional date to calculate balance as of
 * @return float Account balance
 */
function getAccountBalance($account_id, $as_of_date = null)
{
    global $conn;

    try {
        if (!$account_id || !is_numeric($account_id)) {
            return 0.0;
        }

        $where_clause = "WHERE account_id = ?";
        $params = [$account_id];
        $types = 'i';

        if ($as_of_date) {
            $where_clause .= " AND transaction_date <= ?";
            $params[] = $as_of_date;
            $types .= 's';
        }

        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN type IN ('Income', 'Asset') THEN amount ELSE 0 END) - 
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
            FROM acc_transactions 
            $where_clause
        ");

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return floatval($result['balance'] ?? 0);
    } catch (Exception $e) {
        logError("Error getting account balance: " . $e->getMessage(), 'accounting');
        return 0.0;
    }
}

/**
 * Validate ledger account data
 * @param array $data Account data
 * @param int $exclude_id Optional ID to exclude from validation (for updates)
 * @return array Validation result with 'valid' boolean and 'errors' array
 */
function validateLedgerAccountData($data, $exclude_id = null)
{
    global $conn;
    $errors = [];

    // Required fields
    $required_fields = ['name', 'account_number', 'account_type'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }

    // Validate account type
    if (!empty($data['account_type'])) {
        $valid_types = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
        if (!in_array($data['account_type'], $valid_types)) {
            $errors[] = "Invalid account type";
        }
    }

    // Validate account number uniqueness
    if (!empty($data['account_number'])) {
        try {
            $query = "SELECT id FROM acc_ledger_accounts WHERE account_number = ?";
            $params = [$data['account_number']];
            $types = 's';

            if ($exclude_id) {
                $query .= " AND id != ?";
                $params[] = $exclude_id;
                $types .= 'i';
            }

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Account number already exists";
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Error validating account number";
        }
    }

    // Validate name length
    if (!empty($data['name']) && strlen($data['name']) > 255) {
        $errors[] = "Account name cannot exceed 255 characters";
    }

    // Validate account number format (optional - you can customize this)
    if (!empty($data['account_number']) && !preg_match('/^[A-Za-z0-9-]+$/', $data['account_number'])) {
        $errors[] = "Account number can only contain letters, numbers, and hyphens";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
