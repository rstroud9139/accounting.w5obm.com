<?php

/**
 * Edit Transaction Page - W5OBM Accounting System
 * File: /accounting/transactions/edit_transaction.php
 * Purpose: Edit existing transactions in the accounting system
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/transactionController.php';
require_once __DIR__ . '/../views/transactionForm.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../../include/premium_hero.php';

$db = accounting_db_connection();

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_manage') && !hasPermission($user_id, 'accounting_edit')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to edit transactions.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

// CSRF token generation
csrf_ensure_token();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get transaction ID from URL
$transaction_id = null;
if (isset($_GET['id'])) {
    $transaction_id = sanitizeInput($_GET['id'], 'int');
} elseif (isset($_POST['id'])) {
    $transaction_id = sanitizeInput($_POST['id'], 'int');
}

if (!$transaction_id) {
    setToastMessage('danger', 'Error', 'Transaction ID is required.', 'club-logo');
    header('Location: /accounting/transactions/');
    exit();
}

// Get existing transaction
$transaction = fetch_transaction_by_id($transaction_id);
if (!$transaction) {
    setToastMessage('danger', 'Error', 'Transaction not found.', 'club-logo');
    header('Location: /accounting/transactions/');
    exit();
}

// Check if user can edit this transaction (admins can edit any, users can edit their own)
if (!isAdmin($user_id) && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You can only edit transactions you created.', 'club-logo');
    header('Location: /accounting/transactions/');
    exit();
}

$page_title = "Edit Transaction - W5OBM Accounting";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        csrf_verify_post_or_throw();

        // Prepare transaction data
        $transaction_data = [
            'category_id' => sanitizeInput($_POST['category_id'], 'int'),
            'amount' => sanitizeInput($_POST['amount'], 'float'),
            'transaction_date' => sanitizeInput($_POST['transaction_date'], 'string'),
            'description' => sanitizeInput($_POST['description'], 'string'),
            'type' => sanitizeInput($_POST['type'], 'string'),
            'account_id' => !empty($_POST['account_id']) ? sanitizeInput($_POST['account_id'], 'int') : null,
            'vendor_id' => !empty($_POST['vendor_id']) ? sanitizeInput($_POST['vendor_id'], 'int') : null,
            'reference_number' => sanitizeInput($_POST['reference_number'] ?? '', 'string'),
            'notes' => sanitizeInput($_POST['notes'] ?? '', 'string')
        ];

        // Basic validation
        if (empty($transaction_data['category_id']) || empty($transaction_data['amount']) || empty($transaction_data['transaction_date']) || !in_array($transaction_data['type'], ['Income', 'Expense'])) {
            throw new Exception('Missing required fields');
        }

        // Update transaction via legacy controller
        $success = update_transaction(
            $transaction_id,
            $transaction_data['category_id'],
            $transaction_data['amount'],
            $transaction_data['transaction_date'],
            $transaction_data['description'],
            $transaction_data['type'],
            $transaction_data['account_id'],
            $transaction_data['vendor_id']
        );

        if ($success) {
            setToastMessage(
                'success',
                'Transaction Updated',
                "Transaction '{$transaction_data['description']}' has been successfully updated.",
                'club-logo'
            );
            header('Location: /accounting/transactions/');
            exit();
        } else {
            throw new Exception("Failed to update transaction. Please try again.");
        }
    } catch (Exception $e) {
        setToastMessage('danger', 'Error Updating Transaction', $e->getMessage(), 'club-logo');
        logError("Error updating transaction: " . $e->getMessage(), 'accounting');

        // Refresh transaction data in case of error
        $transaction = fetch_transaction_by_id($transaction_id);
    }
}

// Get categories for dropdown
try {
    require_once __DIR__ . '/../controllers/categoryController.php';
    $categories = fetch_all_categories();
} catch (Exception $e) {
    $categories = [];
    logError("Error fetching categories: " . $e->getMessage(), 'accounting');
}

// Get accounts for dropdown
try {
    require_once __DIR__ . '/../controllers/ledgerController.php';
    $accounts = fetch_all_ledger_accounts();
} catch (Exception $e) {
    $accounts = [];
    logError("Error fetching accounts: " . $e->getMessage(), 'accounting');
}

// Get vendors for dropdown
try {
    $stmt = $db->prepare("SELECT id, name FROM acc_vendors ORDER BY name");
    $stmt->execute();
    $vendors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $vendors = [];
    logError("Error fetching vendors: " . $e->getMessage(), 'accounting');
}

// Build hero configuration
$transaction_type = $transaction['type'] ?? 'Unknown';
$transaction_amount = isset($transaction['amount']) ? number_format((float)$transaction['amount'], 2) : '0.00';
$transaction_date = isset($transaction['transaction_date']) ? date('M j, Y', strtotime($transaction['transaction_date'])) : 'N/A';
$transaction_description = $transaction['description'] ?? 'Untitled';

$transactionEditHeroChips = [
    'Type: ' . $transaction_type,
    'Date: ' . $transaction_date,
    'Amount: $' . $transaction_amount,
];

$transactionEditHeroHighlights = [
    [
        'label' => 'Transaction Type',
        'value' => htmlspecialchars($transaction_type),
        'meta' => 'Current',
    ],
    [
        'label' => 'Amount',
        'value' => '$' . $transaction_amount,
        'meta' => $transaction_type === 'Income' ? 'Revenue' : 'Expenditure',
    ],
    [
        'label' => 'Date',
        'value' => $transaction_date,
        'meta' => 'Transaction date',
    ],
];

$transactionEditHeroActions = [
    [
        'label' => 'Transactions List',
        'url' => '/accounting/transactions/',
        'variant' => 'outline',
        'icon' => 'fa-list',
    ],
    [
        'label' => 'Add Transaction',
        'url' => '/accounting/transactions/add_transaction.php',
        'variant' => 'outline',
        'icon' => 'fa-plus',
    ],
    [
        'label' => 'Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left',
    ],
];

$transactionEditHeroConfig = [
    'eyebrow' => 'Transaction Editor',
    'title' => 'Edit Transaction',
    'subtitle' => 'Update amounts, categories, dates, and notes to keep the books clean.',
    'chips' => $transactionEditHeroChips,
    'highlights' => $transactionEditHeroHighlights,
    'actions' => $transactionEditHeroActions,
    'theme' => 'sky',
    'size' => 'compact',
    'media_mode' => 'none',
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <!-- Toast Message Display -->
    <?php
    if (function_exists('displayToastMessage')) {
        displayToastMessage();
    }
    ?>

    <?php if (function_exists('renderPremiumHero')) {
        renderPremiumHero($transactionEditHeroConfig);
    } ?>

    <!-- Page Container -->
    <div class="page-container">
        <?php if (!function_exists('renderPremiumHero')): ?>
            <?php $fallbackLogo = accounting_logo_src_for(__DIR__); ?>
            <section class="hero hero-small mb-4">
                <div class="hero-body py-3">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-2 d-none d-md-flex justify-content-center">
                                <img src="<?= htmlspecialchars($fallbackLogo); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                            </div>
                            <div class="col-md-6 text-center text-md-start text-white">
                                <h1 class="h4 mb-1">Edit Transaction</h1>
                                <p class="mb-0 small">Update transaction details to keep your books accurate.</p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <a href="/accounting/transactions/" class="btn btn-outline-light btn-sm me-2">
                                    <i class="fas fa-list me-1"></i>Transactions
                                </a>
                                <a href="/accounting/dashboard.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Transaction Info Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2 text-info"></i>Transaction Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($transaction['created_at'])) ?></p>
                        <p class="mb-1"><strong>Created by:</strong> <?= htmlspecialchars($transaction['created_by_username'] ?? 'Unknown') ?></p>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($transaction['updated_at'])): ?>
                            <p class="mb-1"><strong>Last Updated:</strong> <?= date('M j, Y g:i A', strtotime($transaction['updated_at'])) ?></p>
                            <p class="mb-1"><strong>Updated by:</strong> <?= htmlspecialchars($transaction['updated_by_username'] ?? 'Unknown') ?></p>
                        <?php else: ?>
                            <p class="mb-1 text-muted">Never updated</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Form -->
        <?php
        renderTransactionForm(
            $transaction,
            $categories,
            $accounts,
            $vendors,
            "/accounting/transactions/edit_transaction.php?id={$transaction_id}",
            'edit'
        );
        ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>