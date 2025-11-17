<?php

/**
 * Delete Transaction - W5OBM Accounting System
 * File: /accounting/transactions/delete.php
 * Purpose: Handle transaction deletion with security checks
 * SECURITY: Requires authentication, permissions, and CSRF protection
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/transaction_controller.php';

// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access the transaction system.', 'fas fa-calculator');
    header('Location: ../../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions
if (!(isAdmin($user_id) || hasPermission($user_id, 'accounting_manage') || hasPermission($user_id, 'accounting_delete'))) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to delete transactions.', 'fas fa-calculator');
    header('Location: list.php');
    exit();
}

// Only allow POST requests for deletion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setToastMessage('danger', 'Invalid Request', 'Invalid request method.', 'fas fa-exclamation-triangle');
    header('Location: list.php');
    exit();
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setToastMessage('danger', 'Security Error', 'Invalid form submission. Please try again.', 'fas fa-shield-alt');
    header('Location: list.php');
    exit();
}

// Get and validate transaction ID
$transaction_id = sanitizeInput($_POST['id'] ?? '', 'int');
if (!$transaction_id) {
    setToastMessage('danger', 'Invalid Request', 'Transaction ID is required.', 'fas fa-exclamation-triangle');
    header('Location: list.php');
    exit();
}

// Get transaction data to verify existence and permissions
$transaction = fetch_transaction_by_id($transaction_id);
if (!$transaction) {
    setToastMessage('danger', 'Transaction Not Found', 'The requested transaction could not be found.', 'fas fa-search');
    header('Location: list.php');
    exit();
}

// Check if user can delete this transaction (admin or manage permission)
if (!isAdmin($user_id) && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to delete this transaction.', 'fas fa-lock');
    header('Location: list.php');
    exit();
}

// Prevent deletion of old transactions (older than 30 days) for non-admins
if (!isAdmin($user_id)) {
    $transaction_date = strtotime($transaction['transaction_date']);
    $thirty_days_ago = strtotime('-30 days');

    if ($transaction_date < $thirty_days_ago) {
        setToastMessage(
            'warning',
            'Cannot Delete Old Transaction',
            'Transactions older than 30 days can only be deleted by administrators.',
            'fas fa-calendar-times'
        );
        header('Location: list.php');
        exit();
    }
}

// Attempt to delete the transaction
try {
    $result = delete_transaction($transaction_id);

    if ($result) {
        // Log the deletion with transaction details for audit trail
        logActivity(
            $user_id,
            'transaction_deleted',
            'acc_transactions',
            $transaction_id,
            "Deleted {$transaction['type']} transaction: {$transaction['description']} for $" . number_format($transaction['amount'], 2)
        );

        setToastMessage(
            'success',
            'Transaction Deleted',
            "Successfully deleted {$transaction['type']} transaction for $" . number_format($transaction['amount'], 2),
            'fas fa-trash'
        );
    } else {
        setToastMessage(
            'danger',
            'Deletion Failed',
            'Failed to delete transaction. It may be referenced by other records.',
            'fas fa-exclamation-triangle'
        );
    }
} catch (Exception $e) {
    logError("Error deleting transaction ID $transaction_id: " . $e->getMessage(), 'accounting');
    setToastMessage(
        'danger',
        'Deletion Error',
        'An error occurred while deleting the transaction. Please try again.',
        'fas fa-exclamation-triangle'
    );
}

// Redirect back to transaction list
header('Location: list.php');
exit();
