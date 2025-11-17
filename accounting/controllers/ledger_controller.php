<?php

/**
 * Ledger Controller (legacy underscore API)
 * Clean, working implementation matching existing schema and callers.
 */

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
// Transitional include: canonical camelCase ledger controller
// Allows gradual migration; legacy functions will delegate when safe.
@require_once __DIR__ . '/ledgerController.php';

/**
 * Add a new ledger account to the database.
 */
function add_ledger_account($name, $description, $category_id)
{
    // Deprecation trace: using legacy underscore API
    if (function_exists('logActivity')) {
        @logActivity(function_exists('getCurrentUserId') ? getCurrentUserId() : null, 'legacy_ledger_api_used', 'acc_ledger_accounts', null, 'add_ledger_account(name, description, category_id)');
    }
    // Prefer canonical implementation to match current schema (account_number, account_type, etc.)
    if (function_exists('addLedgerAccount')) {
        // Generate a safe default account number from name
        $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', $name));
        $base = substr($base ?: 'ACC', 0, 12);
        $suffix = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $account_number = $base . '-' . $suffix;

        $data = [
            'name' => $name,
            // Default conservatively to Asset when legacy category is unknown
            'account_type' => 'Asset',
            'account_number' => $account_number,
            'description' => $description,
            // Legacy category_id has no direct field; keep hierarchy null
            'parent_account_id' => null,
        ];
        $res = addLedgerAccount($data);
        return (bool)$res;
    }
    // Legacy fallback for older schema
    global $conn;
    $stmt = $conn->prepare("INSERT INTO acc_ledger_accounts (name, description, category_id, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('ssi', $name, $description, $category_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Update an existing ledger account.
 */
function update_ledger_account($id, $name, $description, $category_id)
{
    if (function_exists('logActivity')) {
        @logActivity(function_exists('getCurrentUserId') ? getCurrentUserId() : null, 'legacy_ledger_api_used', 'acc_ledger_accounts', $id, 'update_ledger_account(id, name, description, category_id)');
    }
    // Prefer canonical update to preserve schema (account_type/number)
    if (function_exists('updateLedgerAccount') && function_exists('getLedgerAccountById')) {
        $existing = getLedgerAccountById($id);
        if ($existing) {
            $data = [
                'name' => $name,
                'description' => $description,
                // Preserve existing required fields
                'account_number' => $existing['account_number'] ?? ($existing['accountNumber'] ?? ''),
                'account_type' => $existing['account_type'] ?? ($existing['accountType'] ?? 'Asset'),
                'parent_account_id' => $existing['parent_account_id'] ?? null,
            ];
            $res = updateLedgerAccount((int)$id, $data);
            return (bool)$res;
        }
    }
    // Legacy fallback
    global $conn;
    $stmt = $conn->prepare("UPDATE acc_ledger_accounts SET name = ?, description = ?, category_id = ? WHERE id = ?");
    $stmt->bind_param('ssii', $name, $description, $category_id, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Delete a ledger account by its ID.
 */
function delete_ledger_account($id)
{
    if (function_exists('logActivity')) {
        @logActivity(function_exists('getCurrentUserId') ? getCurrentUserId() : null, 'legacy_ledger_api_used', 'acc_ledger_accounts', $id, 'delete_ledger_account(id)');
    }
    // Prefer canonical delete (handles reassignment checks and children)
    if (function_exists('deleteLedgerAccount')) {
        return (bool)deleteLedgerAccount((int)$id, null);
    }
    // Legacy fallback
    global $conn;
    $stmt = $conn->prepare("DELETE FROM acc_ledger_accounts WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Check if a ledger account is being used by any transactions.
 */
function is_ledger_account_in_use($id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_transactions WHERE account_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($res['count'] ?? 0) > 0;
}

/**
 * Fetch a single ledger account by its ID.
 */
function fetch_ledger_account_by_id($id)
{
    // Prefer canonical implementation if available (richer dataset)
    if (function_exists('getLedgerAccountById')) {
        return getLedgerAccountById($id);
    }
    global $conn;
    $query = "SELECT l.*, c.name as category_name FROM acc_ledger_accounts l LEFT JOIN acc_transaction_categories c ON l.category_id = c.id WHERE l.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

/**
 * Fetch all ledger accounts.
 */
function fetch_all_ledger_accounts()
{
    // Delegate to canonical list if present (note: loses category_name from legacy schema if category_id deprecated)
    if (function_exists('getAllLedgerAccounts')) {
        return getAllLedgerAccounts();
    }
    global $conn;
    $result = $conn->query("SELECT l.*, c.name as category_name FROM acc_ledger_accounts l LEFT JOIN acc_transaction_categories c ON l.category_id = c.id ORDER BY l.name ASC");
    $accounts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
    }
    return $accounts;
}

/**
 * Fetch ledger accounts by category.
 */
function fetch_ledger_accounts_by_category($category_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM acc_ledger_accounts WHERE category_id = ? ORDER BY name ASC");
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $accounts = [];
    while ($row = $res->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt->close();
    return $accounts;
}

/**
 * Calculate the balance of a ledger account for a given time period.
 */
function calculate_ledger_account_balance($account_id, $end_date = null)
{
    // Canonical balance provides consistent logic with broader account types
    if (function_exists('getAccountBalance')) {
        return getAccountBalance($account_id, $end_date);
    }
    global $conn;
    if ($end_date === null) {
        $end_date = date('Y-m-d');
    }
    $stmt = $conn->prepare("SELECT SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income, SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expense FROM acc_transactions WHERE account_id = ? AND transaction_date <= ?");
    $stmt->bind_param('is', $account_id, $end_date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $income = floatval($row['income'] ?? 0);
    $expense = floatval($row['expense'] ?? 0);
    return $income - $expense;
}

/**
 * Reassign transactions from one ledger account to another.
 */
function reassign_transactions($old_account_id, $new_account_id)
{
    global $conn;
    $stmt = $conn->prepare("UPDATE acc_transactions SET account_id = ? WHERE account_id = ?");
    $stmt->bind_param('ii', $new_account_id, $old_account_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
