<?php

/**
 * Transaction Model - W5OBM Accounting System
 * File: /accounting/models/transactionModel.php
 * Purpose: Data access layer for transaction operations
 * SECURITY: All database operations use prepared statements
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Transaction Model Class
 * Handles all database operations for transactions
 */
class TransactionModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Create a new transaction
     * @param array $data Transaction data
     * @return bool|int Transaction ID on success, false on failure
     */
    public function create($data)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO acc_transactions 
                (category_id, amount, transaction_date, description, type, account_id, vendor_id, 
                 reference_number, notes, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
            $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
            $transaction_date = (string)($data['transaction_date'] ?? '');
            $description = (string)($data['description'] ?? '');
            $type = (string)($data['type'] ?? '');
            $account_id = isset($data['account_id']) ? (int)$data['account_id'] : null;
            $vendor_id = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
            $reference_number = (string)($data['reference_number'] ?? '');
            $notes = (string)($data['notes'] ?? '');
            $created_by = (int)($data['created_by'] ?? getCurrentUserId());

            $stmt->bind_param(
                'idsssiissi',
                $category_id,
                $amount,
                $transaction_date,
                $description,
                $type,
                $account_id,
                $vendor_id,
                $reference_number,
                $notes,
                $created_by
            );

            if ($stmt->execute()) {
                $transaction_id = $this->conn->insert_id;
                $stmt->close();
                return $transaction_id;
            }

            $stmt->close();
            return false;
        } catch (Exception $e) {
            logError("Error creating transaction: " . $e->getMessage(), 'accounting');
            return false;
        }
    }

    /**
     * Update an existing transaction
     * @param int $id Transaction ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update($id, $data)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE acc_transactions 
                SET category_id = ?, amount = ?, transaction_date = ?, description = ?, 
                    type = ?, account_id = ?, vendor_id = ?, reference_number = ?, 
                    notes = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
            $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
            $transaction_date = (string)($data['transaction_date'] ?? '');
            $description = (string)($data['description'] ?? '');
            $type = (string)($data['type'] ?? '');
            $account_id = isset($data['account_id']) ? (int)$data['account_id'] : null;
            $vendor_id = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
            $reference_number = (string)($data['reference_number'] ?? '');
            $notes = (string)($data['notes'] ?? '');
            $updated_by = (int)($data['updated_by'] ?? getCurrentUserId());
            $id_int = (int)$id;

            $stmt->bind_param(
                'idsssiissii',
                $category_id,
                $amount,
                $transaction_date,
                $description,
                $type,
                $account_id,
                $vendor_id,
                $reference_number,
                $notes,
                $updated_by,
                $id_int
            );

            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            logError("Error updating transaction: " . $e->getMessage(), 'accounting');
            return false;
        }
    }

    /**
     * Delete a transaction
     * @param int $id Transaction ID
     * @return bool Success status
     */
    public function delete($id)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM acc_transactions WHERE id = ?");
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            logError("Error deleting transaction: " . $e->getMessage(), 'accounting');
            return false;
        }
    }

    /**
     * Get transaction by ID
     * @param int $id Transaction ID
     * @return array|false Transaction data or false if not found
     */
    public function getById($id)
    {
        try {
            $stmt = $this->conn->prepare("
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
     * Get all transactions with optional filters
     * @param array $filters Optional filters
     * @param array $options Optional limit, offset, order
     * @return array Transactions array
     */
    public function getAll($filters = [], $options = [])
    {
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
                $where_conditions[] = "(t.description LIKE ? OR t.reference_number LIKE ? OR c.name LIKE ?)";
                $search_term = '%' . $filters['search'] . '%';
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $types .= 'sss';
            }

            $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

            // Order clause
            $order_by = $options['order_by'] ?? 't.transaction_date DESC, t.id DESC';

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

            $stmt = $this->conn->prepare($query);

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
    public function getRecent($limit = 5)
    {
        return $this->getAll([], ['limit' => $limit]);
    }

    /**
     * Get transaction totals with optional filters
     * @param array $filters Optional filters
     * @return array Totals array
     */
    public function getTotals($filters = [])
    {
        try {
            $where_conditions = [];
            $params = [];
            $types = '';

            // Build WHERE clause (same logic as getAll)
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

            $query = "
                SELECT 
                    SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS total_income,
                    SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS total_expenses,
                    COUNT(*) AS transaction_count
                FROM acc_transactions 
                $where_clause
            ";

            $stmt = $this->conn->prepare($query);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $income = floatval($result['total_income'] ?? 0);
            $expenses = floatval($result['total_expenses'] ?? 0);

            return [
                'income' => $income,
                'expenses' => $expenses,
                'net_balance' => $income - $expenses,
                'transaction_count' => intval($result['transaction_count'] ?? 0)
            ];
        } catch (Exception $e) {
            logError("Error getting transaction totals: " . $e->getMessage(), 'accounting');
            return [
                'income' => 0,
                'expenses' => 0,
                'net_balance' => 0,
                'transaction_count' => 0
            ];
        }
    }
}

// Legacy function wrappers for backward compatibility
if (!function_exists('get_all_transactions')) {
    function get_all_transactions($start_date = null, $end_date = null, $category_id = null, $type = null, $account_id = null)
    {
        $model = new TransactionModel();
        $filters = [];

        if ($start_date && $end_date) {
            $filters['start_date'] = $start_date;
            $filters['end_date'] = $end_date;
        }
        if ($category_id) $filters['category_id'] = $category_id;
        if ($type) $filters['type'] = $type;
        if ($account_id) $filters['account_id'] = $account_id;

        return $model->getAll($filters);
    }
}

if (!function_exists('get_transaction_by_id')) {
    function get_transaction_by_id($id)
    {
        $model = new TransactionModel();
        return $model->getById($id);
    }
}

if (!function_exists('add_new_transaction')) {
    function add_new_transaction($category_id, $amount, $transaction_date, $description, $type, $account_id = null, $vendor_id = null)
    {
        $model = new TransactionModel();
        $data = [
            'category_id' => $category_id,
            'amount' => $amount,
            'transaction_date' => $transaction_date,
            'description' => $description,
            'type' => $type,
            'account_id' => $account_id,
            'vendor_id' => $vendor_id
        ];
        return $model->create($data);
    }
}

if (!function_exists('get_recent_transactions')) {
    function get_recent_transactions($limit = 5)
    {
        $model = new TransactionModel();
        return $model->getRecent($limit);
    }
}
