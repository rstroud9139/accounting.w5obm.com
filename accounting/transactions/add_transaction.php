<?php

/**
 * Add Transaction Page - W5OBM Accounting System
 * File: /accounting/transactions/add_transaction.php
 * Purpose: Add new transactions to the accounting system
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

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_manage') && !hasPermission($user_id, 'accounting_add')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to add transactions.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

// CSRF token generation
csrf_ensure_token();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Add Transaction - W5OBM Accounting";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        csrf_verify_post_or_throw();

        // Prepare transaction data (legacy controller schema)
        $category_id = sanitizeInput($_POST['category_id'], 'int');
        $amount = sanitizeInput($_POST['amount'], 'float');
        $transaction_date = sanitizeInput($_POST['transaction_date'], 'string');
        $description = sanitizeInput($_POST['description'], 'string');
        $type = sanitizeInput($_POST['type'], 'string');
        $account_id = !empty($_POST['account_id']) ? sanitizeInput($_POST['account_id'], 'int') : null;
        $vendor_id = !empty($_POST['vendor_id']) ? sanitizeInput($_POST['vendor_id'], 'int') : null;

        if (!$category_id || !$amount || !$transaction_date || !in_array($type, ['Income', 'Expense'])) {
            throw new Exception('Missing or invalid required fields.');
        }

        // Add transaction via legacy function
        $ok = add_transaction($category_id, $amount, $transaction_date, $description, $type, $account_id, $vendor_id);

        if ($ok) {
            setToastMessage(
                'success',
                'Transaction Added',
                "Transaction '" . htmlspecialchars($description) . "' has been successfully added.",
                'club-logo'
            );
            header('Location: /accounting/transactions/');
            exit();
        } else {
            throw new Exception("Failed to add transaction. Please try again.");
        }
    } catch (Exception $e) {
        setToastMessage('danger', 'Error Adding Transaction', $e->getMessage(), 'club-logo');
        logError("Error adding transaction: " . $e->getMessage(), 'accounting');
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
    $stmt = $conn->prepare("SELECT id, name FROM acc_vendors ORDER BY name");
    $stmt->execute();
    $vendors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $vendors = [];
    logError("Error fetching vendors: " . $e->getMessage(), 'accounting');
}

$formHeroHighlights = [
    [
        'label' => 'Categories',
        'value' => number_format(count($categories)),
        'meta' => 'Available'
    ],
    [
        'label' => 'Ledger Accounts',
        'value' => number_format(count($accounts)),
        'meta' => 'Selectable'
    ],
    [
        'label' => 'Vendors',
        'value' => number_format(count($vendors)),
        'meta' => 'Optional links'
    ],
];

$formHeroActions = [
    [
        'label' => 'Transactions Workspace',
        'url' => '/accounting/transactions/transactions.php',
        'variant' => 'outline',
        'icon' => 'fa-list'
    ],
    [
        'label' => 'Accounting Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left'
    ],
];

$formHeroChips = [
    'Mode: Add transaction',
    'Security: CSRF ready'
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

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <?php if (function_exists('renderPremiumHero')): ?>
            <?php renderPremiumHero([
                'eyebrow' => 'Ledger Operations',
                'title' => 'Add Transaction',
                'subtitle' => 'Post a new income or expense with full vendor and account attribution.',
                'description' => 'Complete the guided form below to keep leadership synchronized with every movement.',
                'theme' => 'cobalt',
                'size' => 'compact',
                'media_mode' => 'none',
                'chips' => $formHeroChips,
                'highlights' => $formHeroHighlights,
                'actions' => $formHeroActions,
            ]); ?>
        <?php else: ?>
            <section class="hero hero-small mb-4">
                <div class="hero-body py-3">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-2 d-none d-md-flex justify-content-center">
                                <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                                <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                            </div>
                            <div class="col-md-6 text-center text-md-start text-white">
                                <h1 class="h4 mb-1">Add Transaction</h1>
                                <p class="mb-0 small">Register income or expense activity with helpful guidance.</p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <a href="/accounting/transactions/" class="btn btn-outline-light btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Transactions
                                </a>
                                <a href="/accounting/dashboard.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-home me-1"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="container mt-4">
            <!-- Header Card -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <i class="fas fa-plus fa-2x"></i>
                        </div>
                        <div class="col">
                            <h3 class="mb-0">Add New Transaction</h3>
                            <small>Enter transaction details to add to the accounting system</small>
                        </div>
                        <div class="col-auto">
                            <a href="/accounting/transactions/" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Transactions
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation Check -->
            <?php if (empty($categories)): ?>
                <div class="alert alert-warning border-0 shadow">
                    <div class="d-flex">
                        <i class="fas fa-exclamation-triangle me-3 mt-1 text-warning"></i>
                        <div>
                            <h6 class="alert-heading mb-2">Setup Required</h6>
                            <p class="mb-2">You need to set up transaction categories before adding transactions.</p>
                            <a href="/accounting/categories/" class="btn btn-warning btn-sm">
                                <i class="fas fa-tags me-1"></i>Manage Categories
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Transaction Form -->
                <?php
                renderTransactionForm(
                    [], // Empty for new transaction
                    $categories,
                    $accounts,
                    $vendors,
                    '/accounting/transactions/add_transaction.php',
                    'add'
                );
                ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>