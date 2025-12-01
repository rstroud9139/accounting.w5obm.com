<?php

/**
 * Delete Category Handler - W5OBM Accounting System
 * File: /accounting/categories/delete.php
 * Purpose: Handle category deletion requests
 * SECURITY: Requires authentication, accounting permissions, and CSRF protection
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/categoryController.php';
$db = accounting_db_connection();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setToastMessage('danger', 'Error', 'Invalid request method.', 'club-logo');
    header('Location: /accounting/categories/');
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
    setToastMessage('danger', 'Access Denied', 'You do not have permission to delete categories.', 'club-logo');
    header('Location: /accounting/categories/');
    exit();
}

try {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid security token. Please refresh the page and try again.");
    }

    // Get category ID
    $category_id = null;
    if (isset($_POST['id'])) {
        $category_id = sanitizeInput($_POST['id'], 'int');
    }

    if (!$category_id) {
        throw new Exception("Category ID is required.");
    }

    // Get category details before deletion (for logging and validation)
    // Load basic category details
    $stmt = $db->prepare('SELECT id, name, type FROM acc_transaction_categories WHERE id = ?');
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$category) {
        throw new Exception("Category not found.");
    }

    // Get reassignment category ID if provided
    $reassign_to_id = null;
    if (!empty($_POST['reassign_to'])) {
        $reassign_to_id = sanitizeInput($_POST['reassign_to'], 'int');
    }

    // Check if category has transactions
    // Count transactions using this category
    $tc_stmt = $db->prepare('SELECT COUNT(*) AS c FROM acc_transactions WHERE category_id = ?');
    $tc_stmt->bind_param('i', $category_id);
    $tc_stmt->execute();
    $tc = $tc_stmt->get_result()->fetch_assoc();
    $tc_stmt->close();
    $transaction_count = intval($tc['c'] ?? 0);

    // If has transactions but no reassignment, show error
    if ($transaction_count > 0 && !$reassign_to_id) {
        throw new Exception("This category has $transaction_count transaction(s). Please reassign transactions before deletion.");
    }

    // Optional reassignment of transactions
    if ($transaction_count > 0 && $reassign_to_id) {
        $rs = $db->prepare('UPDATE acc_transactions SET category_id = ? WHERE category_id = ?');
        $rs->bind_param('ii', $reassign_to_id, $category_id);
        if (!$rs->execute()) {
            throw new Exception('Failed to reassign transactions.');
        }
        $rs->close();
    }

    // Delete category via legacy function
    $success = delete_category($category_id);

    if ($success) {
        $message = "Category '{$category['name']}' has been successfully deleted.";
        if ($transaction_count > 0 && $reassign_to_id) {
            $reassign_category = getCategoryById($reassign_to_id);
            $message .= " $transaction_count transaction(s) were reassigned to '{$reassign_category['name']}'.";
        }

        setToastMessage('success', 'Category Deleted', $message, 'club-logo');

        // Log the deletion for audit trail
        logActivity(
            $user_id,
            'category_deleted',
            'acc_transaction_categories',
            $category_id,
            "Deleted category: {$category['name']} ({$category['type']})" .
                ($transaction_count > 0 ? " with $transaction_count transactions reassigned" : "")
        );
    } else {
        throw new Exception("Failed to delete category. Please try again.");
    }
} catch (Exception $e) {
    setToastMessage('danger', 'Error Deleting Category', $e->getMessage(), 'club-logo');
    logError("Error deleting category: " . $e->getMessage(), 'accounting');
}

// Redirect back to categories
header('Location: /accounting/categories/');
exit();
