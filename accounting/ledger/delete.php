<?php

/**
 * Delete Ledger Account Handler - W5OBM Accounting System
 * File: /accounting/ledger/delete.php
 * Purpose: Handle ledger account deletion requests
 * SECURITY: Requires authentication, accounting permissions, and CSRF protection
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/ledgerController.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setToastMessage('danger', 'Error', 'Invalid request method.', 'club-logo');
    header('Location: /accounting/ledger/');
    exit();
}

// Authentication check
if (!isAuthenticated()) {
    http_response_code(401);
    setToastMessage('danger', 'Access Denied', 'Please log in to access this feature.', 'club-logo');
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_manage') && !hasPermission($user_id, 'accounting_delete')) {
    http_response_code(403);
    setToastMessage('danger', 'Access Denied', 'You do not have permission to delete ledger accounts.', 'club-logo');
    header('Location: /accounting/ledger/');
    exit();
}

try {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid security token. Please refresh the page and try again.");
    }

    // Get account ID
    $account_id = null;
    if (isset($_POST['id'])) {
        $account_id = sanitizeInput($_POST['id'], 'int');
    } elseif (preg_match('/\/delete\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches)) {
        $account_id = intval($matches[1]);
    }

    if (!$account_id) {
        throw new Exception("Account ID is required.");
    }

    // Get account details before deletion (for logging and validation)
    $account = getLedgerAccountById($account_id);
    if (!$account) {
        throw new Exception("Account not found.");
    }

    // Get reassignment account ID if provided
    $reassign_to_id = null;
    if (!empty($_POST['reassign_to'])) {
        $reassign_to_id = sanitizeInput($_POST['reassign_to'], 'int');
    }

    // Check if account has transactions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_transactions WHERE account_id = ?");
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $transaction_count = intval($result['count']);
    $stmt->close();

    // If has transactions but no reassignment, show error
    if ($transaction_count > 0 && !$reassign_to_id) {
        throw new Exception("This account has $transaction_count transaction(s). Please reassign transactions before deletion.");
    }

    // Check if account has child accounts
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_ledger_accounts WHERE parent_account_id = ? AND active = 1");
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $child_count = intval($result['count']);
    $stmt->close();

    if ($child_count > 0) {
        throw new Exception("This account has $child_count sub-account(s). Please delete or reassign sub-accounts first.");
    }

    // Perform deletion with optional transaction reassignment
    $success = deleteLedgerAccount($account_id, $reassign_to_id);

    if ($success) {
        $message = "Ledger account '{$account['name']}' has been successfully deleted.";
        if ($transaction_count > 0 && $reassign_to_id) {
            $reassign_account = getLedgerAccountById($reassign_to_id);
            $message .= " $transaction_count transaction(s) were reassigned to '{$reassign_account['name']}'.";
        }

        setToastMessage('success', 'Account Deleted', $message, 'club-logo');

        // Log the deletion for audit trail
        logActivity(
            $user_id,
            'ledger_account_deleted',
            'acc_ledger_accounts',
            $account_id,
            "Deleted ledger account: {$account['name']} ({$account['account_number']})" .
                ($transaction_count > 0 ? " with $transaction_count transactions reassigned" : "")
        );
    } else {
        throw new Exception("Failed to delete account. Please try again.");
    }
} catch (Exception $e) {
    setToastMessage('danger', 'Error Deleting Account', $e->getMessage(), 'club-logo');
    logError("Error deleting ledger account: " . $e->getMessage(), 'accounting');
}

// Redirect back to chart of accounts
header('Location: /accounting/ledger/');
exit();
