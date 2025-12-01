<?php

/**
 * Edit Transaction - W5OBM Accounting System
 * File: /accounting/transactions/edit.php
 * Purpose: Edit existing transaction form and processing
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/transactionController.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../../include/premium_hero.php';

$db = accounting_db_connection();

// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access the transaction system.', 'fas fa-calculator');
    header('Location: ../../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to access the Accounting System.', 'fas fa-calculator');
    header('Location: ../../authentication/dashboard.php');
    exit();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get and validate transaction ID
$transaction_id = sanitizeInput($_GET['id'] ?? '', 'int');
if (!$transaction_id) {
    setToastMessage('danger', 'Invalid Request', 'Transaction ID is required.', 'fas fa-exclamation-triangle');
    header('Location: list.php');
    exit();
}

// Get transaction data
$transaction = fetch_transaction_by_id($transaction_id);
if (!$transaction) {
    setToastMessage('danger', 'Transaction Not Found', 'The requested transaction could not be found.', 'fas fa-search');
    header('Location: list.php');
    exit();
}

// Check if user can edit this transaction (admin or creator)
// Allow edit for admins or users with manage permission
if (!isAdmin($user_id) && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to edit this transaction.', 'fas fa-lock');
    header('Location: list.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setToastMessage('danger', 'Security Error', 'Invalid form submission. Please try again.', 'fas fa-shield-alt');
        header('Location: edit.php?id=' . $transaction_id);
        exit();
    }

    // Sanitize input data
    $transaction_data = [
        'category_id' => sanitizeInput($_POST['category_id'] ?? '', 'int'),
        'amount' => sanitizeInput($_POST['amount'] ?? '', 'float'),
        'transaction_date' => sanitizeInput($_POST['transaction_date'] ?? '', 'string'),
        'description' => sanitizeInput($_POST['description'] ?? '', 'html'),
        'type' => sanitizeInput($_POST['type'] ?? '', 'string'),
        'account_id' => !empty($_POST['account_id']) ? sanitizeInput($_POST['account_id'], 'int') : null,
        'vendor_id' => !empty($_POST['vendor_id']) ? sanitizeInput($_POST['vendor_id'], 'int') : null,
        'reference_number' => sanitizeInput($_POST['reference_number'] ?? '', 'string'),
        'notes' => sanitizeInput($_POST['notes'] ?? '', 'html')
    ];

    // Validate transaction data
    // Basic validation
    $valid = !empty($transaction_data['category_id']) && !empty($transaction_data['amount']) && !empty($transaction_data['transaction_date']) && in_array($transaction_data['type'], ['Income', 'Expense']);

    if ($valid) {
        $result = update_transaction(
            $transaction_id,
            $transaction_data['category_id'],
            $transaction_data['amount'],
            $transaction_data['transaction_date'],
            $transaction_data['description'],
            $transaction_data['type'],
            $transaction_data['account_id'],
            $transaction_data['vendor_id']
        );

        if ($result) {
            setToastMessage(
                'success',
                'Transaction Updated',
                "Successfully updated {$transaction_data['type']} transaction for $" . number_format($transaction_data['amount'], 2),
                'fas fa-check-circle'
            );

            header('Location: list.php');
            exit();
        } else {
            setToastMessage('danger', 'Update Failed', 'Failed to update transaction. Please try again.', 'fas fa-exclamation-triangle');
        }
    } else {
        setToastMessage('danger', 'Validation Error', 'Please provide all required fields.', 'fas fa-exclamation-triangle');
    }
}

// Fetch data for dropdowns
$categories = [];
$accounts = [];
$vendors = [];

try {
    // Get categories
    $cat_query = "SELECT id, name, type FROM acc_transaction_categories ORDER BY name";
    $cat_result = $db->query($cat_query);
    if ($cat_result) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    // Get ledger accounts
    $acc_query = "SELECT id, name FROM acc_ledger_accounts ORDER BY name";
    $acc_result = $db->query($acc_query);
    if ($acc_result) {
        while ($row = $acc_result->fetch_assoc()) {
            $accounts[] = $row;
        }
    }

    // Get vendors
    $ven_query = "SELECT id, name FROM acc_vendors ORDER BY name";
    $ven_result = $db->query($ven_query);
    if ($ven_result) {
        while ($row = $ven_result->fetch_assoc()) {
            $vendors[] = $row;
        }
    }
} catch (Exception $e) {
    logError("Error fetching dropdown data: " . $e->getMessage(), 'accounting');
}

$page_title = "Edit Transaction - W5OBM Accounting";

$transactionHeroHighlights = [
    [
        'label' => 'Amount',
        'value' => '$' . number_format((float) $transaction['amount'], 2),
        'meta' => ($transaction['type'] ?? 'Entry') . ' value'
    ],
    [
        'label' => 'Category',
        'value' => $transaction['category_name'] ?? 'Uncategorized',
        'meta' => 'Current mapping'
    ],
    [
        'label' => 'Transaction Date',
        'value' => date('M j, Y', strtotime($transaction['transaction_date'])),
        'meta' => 'Scheduled posting'
    ]
];

$transactionHeroActions = [
    [
        'label' => 'Transactions Workspace',
        'url' => '/accounting/transactions/transactions.php',
        'variant' => 'outline',
        'icon' => 'fa-list'
    ],
    [
        'label' => 'Back to List',
        'url' => '/accounting/transactions/list.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left'
    ],
    [
        'label' => 'Accounting Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'primary',
        'icon' => 'fa-chart-line'
    ]
];

$transactionHeroChips = array_filter([
    'Mode: Edit transaction',
    'Security: CSRF protected',
    $transaction['vendor_id'] ? 'Vendor linked' : 'Vendor optional'
]);
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
    <?php if (function_exists('displayToastMessage')) {
        displayToastMessage();
    } ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <?php if (function_exists('renderPremiumHero')): ?>
            <?php renderPremiumHero([
                'eyebrow' => 'Ledger Operations',
                'title' => 'Edit Transaction',
                'subtitle' => 'Adjust an existing ledger entry while preserving audit-friendly context.',
                'description' => 'Review the current attributes below, apply updates, and keep leadership synchronized with the latest figures.',
                'theme' => 'sunset',
                'size' => 'compact',
                'media_mode' => 'none',
                'chips' => $transactionHeroChips,
                'highlights' => $transactionHeroHighlights,
                'actions' => $transactionHeroActions,
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
                                <h1 class="h4 mb-1">Edit Transaction</h1>
                                <p class="mb-0 small">Modify income or expense details with confidence.</p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <a href="/accounting/transactions/list.php" class="btn btn-outline-light btn-sm">
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
            <!-- Current Transaction Info -->
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Current Transaction Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Type:</strong><br>
                            <span class="badge <?php
                                                switch ($transaction['type']) {
                                                    case 'Income':
                                                        echo 'bg-success';
                                                        break;
                                                    case 'Expense':
                                                        echo 'bg-danger';
                                                        break;
                                                    case 'Asset':
                                                        echo 'bg-primary';
                                                        break;
                                                    case 'Transfer':
                                                        echo 'bg-warning';
                                                        break;
                                                    default:
                                                        echo 'bg-secondary';
                                                }
                                                ?>"><?= htmlspecialchars($transaction['type']) ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Amount:</strong><br>
                            <span class="h5 <?= $transaction['type'] === 'Income' ? 'text-success' : 'text-danger' ?>">
                                $<?= number_format($transaction['amount'], 2) ?>
                            </span>
                        </div>
                        <div class="col-md-3">
                            <strong>Date:</strong><br>
                            <?= date('M j, Y', strtotime($transaction['transaction_date'])) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Category:</strong><br>
                            <?= htmlspecialchars($transaction['category_name'] ?? 'Uncategorized') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="card shadow">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Edit Transaction Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="edit.php?id=<?= $transaction_id ?>" id="editTransactionForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="row">
                            <!-- Transaction Type -->
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">
                                    <i class="fas fa-tag me-1"></i>Transaction Type *
                                </label>
                                <select id="type" name="type" class="form-control form-control-lg shadow-sm" required>
                                    <option value="">Select transaction type</option>
                                    <option value="Income" <?= $transaction['type'] === 'Income' ? 'selected' : '' ?>>Income</option>
                                    <option value="Expense" <?= $transaction['type'] === 'Expense' ? 'selected' : '' ?>>Expense</option>
                                    <option value="Asset" <?= $transaction['type'] === 'Asset' ? 'selected' : '' ?>>Asset Purchase</option>
                                    <option value="Transfer" <?= $transaction['type'] === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                                </select>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">
                                    <i class="fas fa-dollar-sign me-1"></i>Amount *
                                </label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-light">$</span>
                                    <input type="number" step="0.01" min="0.01" max="999999.99"
                                        id="amount" name="amount" class="form-control form-control-lg"
                                        value="<?= htmlspecialchars($transaction['amount']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Transaction Date -->
                            <div class="col-md-6 mb-3">
                                <label for="transaction_date" class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Transaction Date *
                                </label>
                                <input type="date" id="transaction_date" name="transaction_date"
                                    class="form-control form-control-lg shadow-sm"
                                    value="<?= htmlspecialchars($transaction['transaction_date']) ?>" required>
                            </div>

                            <!-- Category -->
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">
                                    <i class="fas fa-folder me-1"></i>Category *
                                </label>
                                <select id="category_id" name="category_id" class="form-control form-control-lg shadow-sm" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"
                                            <?= $transaction['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                            <?php if (!empty($category['type'])): ?>
                                                <small>(<?= htmlspecialchars($category['type']) ?>)</small>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Ledger Account -->
                            <div class="col-md-6 mb-3">
                                <label for="account_id" class="form-label">
                                    <i class="fas fa-university me-1"></i>Ledger Account
                                </label>
                                <select id="account_id" name="account_id" class="form-control form-control-lg shadow-sm">
                                    <option value="">Select an account (optional)</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"
                                            <?= $transaction['account_id'] == $account['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($account['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Vendor -->
                            <div class="col-md-6 mb-3">
                                <label for="vendor_id" class="form-label">
                                    <i class="fas fa-building me-1"></i>Vendor
                                </label>
                                <select id="vendor_id" name="vendor_id" class="form-control form-control-lg shadow-sm">
                                    <option value="">Select a vendor (optional)</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?= $vendor['id'] ?>"
                                            <?= $transaction['vendor_id'] == $vendor['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vendor['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Reference Number -->
                            <div class="col-md-6 mb-3">
                                <label for="reference_number" class="form-label">
                                    <i class="fas fa-hashtag me-1"></i>Reference Number
                                </label>
                                <input type="text" id="reference_number" name="reference_number"
                                    class="form-control form-control-lg shadow-sm"
                                    value="<?= htmlspecialchars($transaction['reference_number'] ?? '') ?>"
                                    placeholder="Check #, Invoice #, etc.">
                            </div>

                            <!-- Description -->
                            <div class="col-md-6 mb-3">
                                <label for="description" class="form-label">
                                    <i class="fas fa-comment me-1"></i>Description *
                                </label>
                                <input type="text" id="description" name="description"
                                    class="form-control form-control-lg shadow-sm"
                                    value="<?= htmlspecialchars($transaction['description']) ?>"
                                    placeholder="Brief description of transaction"
                                    maxlength="500" required>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note me-1"></i>Additional Notes
                            </label>
                            <textarea id="notes" name="notes" class="form-control form-control-lg shadow-sm"
                                rows="3" placeholder="Optional additional details or notes..."><?= htmlspecialchars($transaction['notes'] ?? '') ?></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-area bg-light border-top p-3 rounded-bottom">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Last updated: <?= $transaction['updated_at'] ? date('M j, Y g:i A', strtotime($transaction['updated_at'])) : 'Never' ?>
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="btn-group shadow" role="group">
                                        <a href="list.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </a>
                                        <button type="submit" class="btn btn-warning btn-lg">
                                            <i class="fas fa-save me-1"></i>Update Transaction
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editTransactionForm');
            const amountInput = document.getElementById('amount');

            // Form validation
            form.addEventListener('submit', function(e) {
                const amount = parseFloat(amountInput.value);
                if (isNaN(amount) || amount <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid amount greater than 0.');
                    return false;
                }

                if (!confirm('Are you sure you want to update this transaction?')) {
                    e.preventDefault();
                    return false;
                }
            });

            // Auto-format amount
            amountInput.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                }
            });
        });
    </script>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>