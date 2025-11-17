<?php

/**
 * Transaction Controller - W5OBM Accounting System
 * File: /accounting/controllers/transactionController.php
 * Purpose: Complete transaction management controller
 * SECURITY: All operations require authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
// Centralized stats service for aggregation reuse
@require_once __DIR__ . '/../utils/stats_service.php';

/**
 * Add a new transaction to the database
 * @param array $data Transaction data
 * @return bool|int Transaction ID on success, false on failure
 */
function addTransaction($data)
{
    global $conn;

    try {
        // Validate required fields
        $required = ['category_id', 'amount', 'transaction_date', 'description', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate amount
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new Exception("Invalid amount");
        }

        // Validate type
        if (!in_array($data['type'], ['Income', 'Expense', 'Asset', 'Transfer'])) {
            throw new Exception("Invalid transaction type");
        }

        // Validate date
        if (!strtotime($data['transaction_date'])) {
            throw new Exception("Invalid transaction date");
        }

        $transaction_type = ($data['type'] === 'Income') ? 'Deposit' : 'Payment';
        $stmt = $conn->prepare("
            INSERT INTO acc_transactions 
            (category_id, amount, transaction_date, description, type, account_id, vendor_id, reference_number, notes, transaction_type, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $created_by = getCurrentUserId();
        $account_id = !empty($data['account_id']) ? intval($data['account_id']) : null;
        $vendor_id = !empty($data['vendor_id']) ? intval($data['vendor_id']) : null;
        $reference_number = sanitizeInput($data['reference_number'] ?? '');
        $notes = sanitizeInput($data['notes'] ?? '');

        // Bind parameters with correct types, allowing NULL for optional foreign keys
        // Types: i (category_id), d (amount), s (date), s (desc), s (type), i (account_id|null), i (vendor_id|null), s (ref), s (notes), s (transaction_type), i (created_by)
        $stmt->bind_param(
            'idsssiisssi',
            $data['category_id'],
            $data['amount'],
            $data['transaction_date'],
            $data['description'],
            $data['type'],
            $account_id,
            $vendor_id,
            $reference_number,
            $notes,
            $transaction_type,
            $created_by
        );

        if ($stmt->execute()) {
            $transaction_id = $conn->insert_id;
            $stmt->close();

            // Log activity
            logActivity(
                $created_by,
                'transaction_created',
                'acc_transactions',
                $transaction_id,
                "Created {$data['type']} transaction for $" . number_format($data['amount'], 2)
            );

            return $transaction_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error adding transaction: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Update an existing transaction
 * @param int $id Transaction ID
 * @param array $data Updated transaction data
 * @return bool Success status
 */
function updateTransaction($id, $data)
{
    global $conn;

    try {
        // Validate ID
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid transaction ID");
        }

        // Check if transaction exists and user has permission to edit
        $existing = getTransactionById($id);
        if (!$existing) {
            throw new Exception("Transaction not found");
        }

        // Validate required fields
        $required = ['category_id', 'amount', 'transaction_date', 'description', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        $transaction_type = ($data['type'] === 'Income') ? 'Deposit' : 'Payment';
        $stmt = $conn->prepare("
            UPDATE acc_transactions 
            SET category_id = ?, amount = ?, transaction_date = ?, description = ?, 
                type = ?, account_id = ?, vendor_id = ?, reference_number = ?, 
                notes = ?, transaction_type = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $updated_by = getCurrentUserId();
        $account_id = !empty($data['account_id']) ? intval($data['account_id']) : null;
        $vendor_id = !empty($data['vendor_id']) ? intval($data['vendor_id']) : null;
        $reference_number = sanitizeInput($data['reference_number'] ?? '');
        $notes = sanitizeInput($data['notes'] ?? '');

        // Types: i (category_id), d (amount), s (date), s (desc), s (type), i (account_id|null), i (vendor_id|null), s (ref), s (notes), s (transaction_type), i (updated_by), i (id)
        $stmt->bind_param(
            'idsssiisssii',
            $data['category_id'],
            $data['amount'],
            $data['transaction_date'],
            $data['description'],
            $data['type'],
            $account_id,
            $vendor_id,
            $reference_number,
            $notes,
            $transaction_type,
            $updated_by,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Log activity
            logActivity(
                $updated_by,
                'transaction_updated',
                'acc_transactions',
                $id,
                "Updated {$data['type']} transaction for $" . number_format($data['amount'], 2)
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error updating transaction: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Delete a transaction by its ID
 * @param int $id Transaction ID
 * @return bool Success status
 */
function deleteTransaction($id)
{
    global $conn;

    try {
        // Validate ID
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid transaction ID");
        }

        // Get transaction details for logging
        $transaction = getTransactionById($id);
        if (!$transaction) {
            throw new Exception("Transaction not found");
        }

        // Check if user has permission to delete
        $user_id = getCurrentUserId();
        if (!isAdmin($user_id) && $transaction['created_by'] != $user_id) {
            throw new Exception("Insufficient permissions to delete this transaction");
        }

        $stmt = $conn->prepare("DELETE FROM acc_transactions WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $stmt->close();

            // Log activity
            logActivity(
                $user_id,
                'transaction_deleted',
                'acc_transactions',
                $id,
                "Deleted {$transaction['type']} transaction: {$transaction['description']}"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error deleting transaction: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get a single transaction by its ID
 * @param int $id Transaction ID
 * @return array|false Transaction data or false if not found
 */
function getTransactionById($id)
{
    global $conn;

    try {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT t.*, 
                   c.name AS category_name,
                   a.name AS account_name, 
                   v.name AS vendor_name,
                   cu.username AS created_by_username,
                   uu.username AS updated_by_username
            FROM acc_transactions t 
            LEFT JOIN acc_transaction_categories c ON t.category_id = c.id 
            LEFT JOIN acc_ledger_accounts a ON t.account_id = a.id
            LEFT JOIN acc_vendors v ON t.vendor_id = v.id
            LEFT JOIN auth_users cu ON t.created_by = cu.id
            LEFT JOIN auth_users uu ON t.updated_by = uu.id
            WHERE t.id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        logError("Error getting transaction by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all transactions with optional filtering
 * @param array $filters Optional filters
 * @param int $limit Optional limit
 * @param int $offset Optional offset
 * @return array Transactions array
 */
function getAllTransactions($filters = [], $limit = null, $offset = 0)
{
    global $conn;

    try {
        $where_conditions = [];
        $params = [];
        $types = '';

        // Build WHERE clause
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where_conditions[] = "t.transaction_date BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= 'ss';
        }

        if (!empty($filters['category_id'])) {
            $where_conditions[] = "t.category_id = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }

        if (!empty($filters['type'])) {
            $where_conditions[] = "t.type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }

        if (!empty($filters['account_id'])) {
            $where_conditions[] = "t.account_id = ?";
            $params[] = $filters['account_id'];
            $types .= 'i';
        }

        if (!empty($filters['vendor_id'])) {
            $where_conditions[] = "t.vendor_id = ?";
            $params[] = $filters['vendor_id'];
            $types .= 'i';
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(t.description LIKE ? OR c.name LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ss';
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        $query = "
            SELECT t.*, 
                   c.name AS category_name,
                   a.name AS account_name, 
                   v.name AS vendor_name
            FROM acc_transactions t 
            LEFT JOIN acc_transaction_categories c ON t.category_id = c.id 
            LEFT JOIN acc_ledger_accounts a ON t.account_id = a.id
            LEFT JOIN acc_vendors v ON t.vendor_id = v.id
            $where_clause
            ORDER BY t.transaction_date DESC, t.id DESC
        ";

        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';

            if ($offset > 0) {
                $query .= " OFFSET ?";
                $params[] = $offset;
                $types .= 'i';
            }
        }

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

        $stmt->close();
        return $transactions;
    } catch (Exception $e) {
        logError("Error getting all transactions: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Get recent transactions
 * @param int $limit Number of transactions to return
 * @return array Recent transactions
 */
function getRecentTransactions($limit = 5)
{
    return getAllTransactions([], $limit);
}

/**
 * Calculate transaction totals
 * @param array $filters Optional filters
 * @return array Totals array
 */
function calculateTransactionTotals($filters = [])
{
    // Prefer centralized implementation for consistency
    if (function_exists('get_transaction_totals') && !empty($filters['start_date']) && !empty($filters['end_date'])) {
        $totals = get_transaction_totals($filters['start_date'], $filters['end_date']);
        return [
            'income' => $totals['income'],
            'expenses' => $totals['expenses'],
            'net_balance' => $totals['net_balance'],
            'transaction_count' => $totals['transaction_count'],
        ];
    }
    // Fallback to local SQL path when date range not provided
    global $conn;
    try {
        $where_conditions = [];
        $params = [];
        $types = '';
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where_conditions[] = "transaction_date BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= 'ss';
        }
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "category_id = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }
        if (!empty($filters['type'])) {
            $where_conditions[] = "type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        $query = "SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS total_income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS total_expenses,
                COUNT(*) AS transaction_count
            FROM acc_transactions $where_clause";
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $income = (float)($result['total_income'] ?? 0);
        $expenses = (float)($result['total_expenses'] ?? 0);
        return [
            'income' => $income,
            'expenses' => $expenses,
            'net_balance' => $income - $expenses,
            'transaction_count' => (int)($result['transaction_count'] ?? 0),
        ];
    } catch (Exception $e) {
        logError("Error calculating transaction totals: " . $e->getMessage(), 'accounting');
        return [
            'income' => 0,
            'expenses' => 0,
            'net_balance' => 0,
            'transaction_count' => 0,
        ];
    }
}

/**
 * Validate transaction data
 * @param array $data Transaction data
 * @return array Validation result with 'valid' boolean and 'errors' array
 */
function validateTransactionData($data)
{
    $errors = [];

    // Required fields
    $required_fields = ['category_id', 'amount', 'transaction_date', 'description', 'type'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }

    // Validate amount
    if (!empty($data['amount'])) {
        if (!is_numeric($data['amount']) || floatval($data['amount']) <= 0) {
            $errors[] = "Amount must be a positive number";
        }
        if (floatval($data['amount']) > 999999.99) {
            $errors[] = "Amount cannot exceed $999,999.99";
        }
    }

    // Validate type
    if (!empty($data['type'])) {
        $valid_types = ['Income', 'Expense', 'Asset', 'Transfer'];
        if (!in_array($data['type'], $valid_types)) {
            $errors[] = "Invalid transaction type";
        }
    }

    // Validate date
    if (!empty($data['transaction_date'])) {
        if (!strtotime($data['transaction_date'])) {
            $errors[] = "Invalid transaction date";
        } else {
            $transaction_date = strtotime($data['transaction_date']);
            $max_future = strtotime('+1 year');
            $min_past = strtotime('-10 years');

            if ($transaction_date > $max_future) {
                $errors[] = "Transaction date cannot be more than 1 year in the future";
            }
            if ($transaction_date < $min_past) {
                $errors[] = "Transaction date cannot be more than 10 years in the past";
            }
        }
    }

    // Validate description length
    if (!empty($data['description']) && strlen($data['description']) > 500) {
        $errors[] = "Description cannot exceed 500 characters";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
