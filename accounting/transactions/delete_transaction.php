<?php

/**
 * Delete Transaction Handler - W5OBM Accounting System
 * File: /accounting/transactions/delete_transaction.php
 * Purpose: Handle transaction deletion requests
 * SECURITY: Requires authentication, accounting permissions, and CSRF protection
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/transactionController.php';
require_once __DIR__ . '/../utils/csrf.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setToastMessage('danger', 'Error', 'Invalid request method.', 'club-logo');
    header('Location: /accounting/transactions/');
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
    setToastMessage('danger', 'Access Denied', 'You do not have permission to delete transactions.', 'club-logo');
    header('Location: /accounting/transactions/');
    exit();
}

try {
    // Verify CSRF token
    csrf_verify_post_or_throw();

    // Get transaction ID
    $transaction_id = null;
    if (isset($_POST['id'])) {
        $transaction_id = sanitizeInput($_POST['id'], 'int');
    } elseif (preg_match('/\/delete\/(\d+)$/', $_SERVER['REQUEST_URI'], $matches)) {
        $transaction_id = intval($matches[1]);
    }

    if (!$transaction_id) {
        throw new Exception("Transaction ID is required.");
    }

    // Get transaction details before deletion (for logging and permission check)
    $transaction = fetch_transaction_by_id($transaction_id);
    if (!$transaction) {
        throw new Exception("Transaction not found.");
    }

    // Check if user can delete this transaction
    // Admins can delete any transaction, regular users can only delete their own
    if (!isAdmin($user_id) && !hasPermission($user_id, 'accounting_manage')) {
        throw new Exception("You do not have permission to delete this transaction.");
    }

    // Check if transaction is too old to delete (optional business rule)
    $created_date = strtotime($transaction['transaction_date']);
    $thirty_days_ago = strtotime('-30 days');

    if (!isAdmin($user_id) && $created_date < $thirty_days_ago) {
        throw new Exception("Transactions older than 30 days can only be deleted by administrators.");
    }

    // Delete the transaction
    $success = delete_transaction($transaction_id);

    if ($success) {
        setToastMessage(
            'success',
            'Transaction Deleted',
            "Transaction '{$transaction['description']}' has been successfully deleted.",
            'club-logo'
        );

        // Log the deletion for audit trail
        logActivity(
            $user_id,
            'transaction_deleted',
            'acc_transactions',
            $transaction_id,
            "Deleted transaction: {$transaction['description']} ({$transaction['type']}, $" . number_format($transaction['amount'], 2) . ")"
        );
    } else {
        throw new Exception("Failed to delete transaction. Please try again.");
    }
} catch (Exception $e) {
    setToastMessage('danger', 'Error Deleting Transaction', $e->getMessage(), 'club-logo');
    logError("Error deleting transaction: " . $e->getMessage(), 'accounting');
}

// Redirect back to transactions list
header('Location: /accounting/transactions/');
exit();
