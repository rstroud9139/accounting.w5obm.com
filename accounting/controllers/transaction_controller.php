<?php

/**
 * Transaction Controller (underscore) - Wrapper to canonical camelCase controller
 * Purpose: Maintain API compatibility while consolidating logic in transactionController.php
 */

require_once __DIR__ . '/transactionController.php';
// Centralized stats service (category aggregations, totals)
require_once __DIR__ . '/../utils/stats_service.php';

// Create thin wrappers mapping underscore API to camelCase implementations

function add_transaction($category_id, $amount, $transaction_date, $description, $type, $account_id = null, $vendor_id = null)
{
    return addTransaction([
        'category_id' => $category_id,
        'amount' => $amount,
        'transaction_date' => $transaction_date,
        'description' => $description,
        'type' => $type,
        'account_id' => $account_id,
        'vendor_id' => $vendor_id,
    ]);
}

function update_transaction($id, $category_id, $amount, $transaction_date, $description, $type, $account_id = null, $vendor_id = null)
{
    return updateTransaction($id, [
        'category_id' => $category_id,
        'amount' => $amount,
        'transaction_date' => $transaction_date,
        'description' => $description,
        'type' => $type,
        'account_id' => $account_id,
        'vendor_id' => $vendor_id,
    ]);
}

function delete_transaction($id)
{
    return deleteTransaction($id);
}

function fetch_transaction_by_id($id)
{
    return getTransactionById($id);
}

function fetch_all_transactions($start_date = null, $end_date = null, $category_id = null, $type = null, $account_id = null)
{
    // Delegate to TransactionModel via controller convenience function if present
    // Fallback: compose filters from args using camelCase getTransactions (if exists)
    if (function_exists('getTransactions')) {
        return getTransactions([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'category_id' => $category_id,
            'type' => $type,
            'account_id' => $account_id,
        ]);
    }

    // Use camelCase controller patterns
    global $conn;
    $filters = [];
    if ($start_date && $end_date) {
        $filters['start_date'] = $start_date;
        $filters['end_date'] = $end_date;
    }
    if ($category_id) {
        $filters['category_id'] = $category_id;
    }
    if ($type) {
        $filters['type'] = $type;
    }
    if ($account_id) {
        $filters['account_id'] = $account_id;
    }

    // Replicate previous behavior using prepared query
    $types = '';
    $params = [];
    $where = [];
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $where[] = 't.transaction_date BETWEEN ? AND ?';
        $types .= 'ss';
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];
    }
    if (!empty($filters['category_id'])) {
        $where[] = 't.category_id = ?';
        $types .= 'i';
        $params[] = $filters['category_id'];
    }
    if (!empty($filters['type'])) {
        $where[] = 't.type = ?';
        $types .= 's';
        $params[] = $filters['type'];
    }
    if (!empty($filters['account_id'])) {
        $where[] = 't.account_id = ?';
        $types .= 'i';
        $params[] = $filters['account_id'];
    }

    $sql = 'SELECT t.*, c.name AS category_name, a.name AS account_name FROM acc_transactions t LEFT JOIN acc_transaction_categories c ON t.category_id=c.id LEFT JOIN acc_ledger_accounts a ON t.account_id=a.id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY t.transaction_date DESC';
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

function get_recent_transactions($limit = 5)
{
    if (function_exists('getRecentTransactions')) {
        return getRecentTransactions($limit);
    }
    global $conn;
    $stmt = $conn->prepare("SELECT t.*, c.name AS category_name, a.name AS account_name FROM acc_transactions t LEFT JOIN acc_transaction_categories c ON t.category_id=c.id LEFT JOIN acc_ledger_accounts a ON t.account_id=a.id ORDER BY t.transaction_date DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

function calculate_total_income($connRef = null)
{
    $c = $connRef ?: (function_exists('getDbConnection') ? getDbConnection() : null);
    if (!$c) {
        global $conn;
        $c = $conn;
    }
    $res = $c->query("SELECT COALESCE(SUM(amount),0) AS total FROM acc_transactions WHERE type='Income'");
    $row = $res->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

function calculate_total_expenses($connRef = null)
{
    $c = $connRef ?: (function_exists('getDbConnection') ? getDbConnection() : null);
    if (!$c) {
        global $conn;
        $c = $conn;
    }
    $res = $c->query("SELECT COALESCE(SUM(amount),0) AS total FROM acc_transactions WHERE type='Expense'");
    $row = $res->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

function calculate_income_by_category($start_date, $end_date, $limit = null)
{
    // Normalize optional limit for static analyzers
    $limit = ($limit !== null) ? (int)$limit : null;
    if (function_exists('get_income_by_category')) {
        return get_income_by_category($start_date, $end_date, $limit);
    }
    // Fallback (should not be needed once stats_service is loaded)
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

function calculate_expenses_by_category($start_date, $end_date, $limit = null)
{
    $limit = ($limit !== null) ? (int)$limit : null;
    if (function_exists('get_expenses_by_category')) {
        return get_expenses_by_category($start_date, $end_date, $limit);
    }
    // Fallback
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
