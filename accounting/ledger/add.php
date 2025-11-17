<?php

/**
 * Add Ledger Account - W5OBM Accounting System
 * File: /accounting/ledger/add.php
 * Purpose: Form to add new ledger account to chart of accounts
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines with full responsive design
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/ledgerController.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_add') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to add ledger accounts.', 'club-logo');
    header('Location: /accounting/ledger/');
    exit();
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize form variables
$form_data = [
    'name' => '',
    'account_number' => '',
    'account_type' => '',
    'description' => '',
    'parent_account_id' => ''
];

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Sanitize input
        $form_data = [
            'name' => sanitizeInput($_POST['name'] ?? '', 'string'),
            'account_number' => sanitizeInput($_POST['account_number'] ?? '', 'string'),
            'account_type' => sanitizeInput($_POST['account_type'] ?? '', 'string'),
            'description' => sanitizeInput($_POST['description'] ?? '', 'string'),
            'parent_account_id' => !empty($_POST['parent_account_id']) ? intval($_POST['parent_account_id']) : null
        ];

        // Validate data
        $validation = validateLedgerAccountData($form_data);

        if ($validation['valid']) {
            // Attempt to add account
            $account_id = addLedgerAccount($form_data);

            if ($account_id) {
                logActivity(
                    $user_id,
                    'ledger_account_created',
                    'acc_ledger_accounts',
                    $account_id,
                    "Created ledger account: {$form_data['name']} ({$form_data['account_number']})"
                );

                setToastMessage(
                    'success',
                    'Account Created',
                    'Ledger account "' . $form_data['name'] . '" has been created successfully.',
                    'club-logo'
                );
                header('Location: /accounting/ledger/');
                exit();
            } else {
                $errors[] = 'Failed to create account. Please check the error logs.';
            }
        } else {
            $errors = $validation['errors'];
        }
    }
}

// Get potential parent accounts for dropdown
try {
    $parent_accounts = getAllLedgerAccounts(['active' => true], ['order_by' => 'account_type, account_number']);
} catch (Exception $e) {
    $parent_accounts = [];
    logError("Error getting parent accounts: " . $e->getMessage(), 'accounting');
}

$page_title = "Add Ledger Account - W5OBM Accounting";
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

    <!-- Page Container -->
    <div class="page-container">
        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-plus fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Add Ledger Account</h3>
                        <small>Create a new account in your chart of accounts</small>
                    </div>
                    <div class="col-auto">
                        <a href="/accounting/ledger/" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Chart of Accounts
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger border-0 shadow mb-4">
                <div class="d-flex">
                    <i class="fas fa-exclamation-triangle me-3 mt-1 text-danger"></i>
                    <div>
                        <h6 class="alert-heading mb-2">Please correct the following errors:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Account Types Guide -->
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Account Types Guide
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success">Assets</h6>
                        <p class="small text-muted mb-3">Things the club owns that have value (cash, equipment, etc.)</p>

                        <h6 class="text-danger">Liabilities</h6>
                        <p class="small text-muted mb-3">Money the club owes to others (loans, unpaid bills)</p>

                        <h6 class="text-info">Equity</h6>
                        <p class="small text-muted mb-3">The club's net worth (retained earnings, reserves)</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Income</h6>
                        <p class="small text-muted mb-3">Money coming into the club (dues, donations, events)</p>

                        <h6 class="text-warning">Expenses</h6>
                        <p class="small text-muted mb-3">Money spent by the club (utilities, supplies, maintenance)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Form -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>Account Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="row">
                        <!-- Account Name -->
                        <div class="col-md-8 mb-4">
                            <label for="name" class="form-label">
                                <i class="fas fa-tag me-1"></i>Account Name *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="name" name="name"
                                value="<?= htmlspecialchars($form_data['name']) ?>" required maxlength="255"
                                placeholder="Enter account name (e.g., Checking Account)">
                            <div class="form-text">
                                A clear, descriptive name for this account
                            </div>
                        </div>

                        <!-- Account Number -->
                        <div class="col-md-4 mb-4">
                            <label for="account_number" class="form-label">
                                <i class="fas fa-hashtag me-1"></i>Account Number *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="account_number" name="account_number"
                                value="<?= htmlspecialchars($form_data['account_number']) ?>" required maxlength="50"
                                placeholder="e.g., 1000">
                            <div class="form-text">
                                Unique identifier (numbers, letters, hyphens)
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Account Type -->
                        <div class="col-md-6 mb-4">
                            <label for="account_type" class="form-label">
                                <i class="fas fa-sitemap me-1"></i>Account Type *
                            </label>
                            <select class="form-control form-control-lg" id="account_type" name="account_type" required>
                                <option value="">Select Account Type</option>
                                <option value="Asset" <?= $form_data['account_type'] === 'Asset' ? 'selected' : '' ?>>
                                    Asset - Things the club owns
                                </option>
                                <option value="Liability" <?= $form_data['account_type'] === 'Liability' ? 'selected' : '' ?>>
                                    Liability - Money the club owes
                                </option>
                                <option value="Equity" <?= $form_data['account_type'] === 'Equity' ? 'selected' : '' ?>>
                                    Equity - Club's net worth
                                </option>
                                <option value="Income" <?= $form_data['account_type'] === 'Income' ? 'selected' : '' ?>>
                                    Income - Money coming in
                                </option>
                                <option value="Expense" <?= $form_data['account_type'] === 'Expense' ? 'selected' : '' ?>>
                                    Expense - Money going out
                                </option>
                            </select>
                        </div>

                        <!-- Parent Account -->
                        <div class="col-md-6 mb-4">
                            <label for="parent_account_id" class="form-label">
                                <i class="fas fa-level-up-alt me-1"></i>Parent Account (Optional)
                            </label>
                            <select class="form-control form-control-lg" id="parent_account_id" name="parent_account_id">
                                <option value="">No Parent (Top Level Account)</option>
                                <?php
                                $current_type = '';
                                foreach ($parent_accounts as $account):
                                    if ($current_type !== $account['account_type']):
                                        if ($current_type !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($account['account_type']) . ' Accounts">';
                                        $current_type = $account['account_type'];
                                    endif;
                                ?>
                                    <option value="<?= $account['id'] ?>"
                                        <?= $form_data['parent_account_id'] == $account['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($account['account_number']) ?> - <?= htmlspecialchars($account['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($current_type !== '') echo '</optgroup>'; ?>
                            </select>
                            <div class="form-text">
                                Create sub-accounts by selecting a parent account
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left me-1"></i>Description (Optional)
                        </label>
                        <textarea class="form-control form-control-lg" id="description" name="description" rows="3"
                            placeholder="Enter a detailed description of this account's purpose..."><?= htmlspecialchars($form_data['description']) ?></textarea>
                        <div class="form-text">
                            Additional details about this account and its intended use
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <a href="/accounting/ledger/" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-outline-warning btn-lg me-2">
                                <i class="fas fa-undo me-1"></i>Reset Form
                            </button>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-1"></i>Create Account
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Add Examples -->
        <div class="card shadow mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>Quick Add Examples
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success">Common Asset Accounts:</h6>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-success btn-sm me-2 mb-2"
                                onclick="quickFill('Checking Account', '1000', 'Asset', 'Primary club checking account')">
                                1000 - Checking Account
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm me-2 mb-2"
                                onclick="quickFill('Savings Account', '1100', 'Asset', 'Club savings/reserve account')">
                                1100 - Savings Account
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm me-2 mb-2"
                                onclick="quickFill('Petty Cash', '1200', 'Asset', 'Cash on hand for small expenses')">
                                1200 - Petty Cash
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm me-2 mb-2"
                                onclick="quickFill('Radio Equipment', '1500', 'Asset', 'Radios, antennas, and related equipment')">
                                1500 - Radio Equipment
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Common Income Accounts:</h6>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm me-2 mb-2"
                                onclick="quickFill('Membership Dues', '4000', 'Income', 'Annual membership dues')">
                                4000 - Membership Dues
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm me-2 mb-2"
                                onclick="quickFill('Donations', '4100', 'Income', 'Charitable donations received')">
                                4100 - Donations
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm me-2 mb-2"
                                onclick="quickFill('Event Income', '4200', 'Income', 'Income from club events')">
                                4200 - Event Income
                            </button>
                        </div>

                        <h6 class="text-warning">Common Expense Accounts:</h6>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-warning btn-sm me-2 mb-2"
                                onclick="quickFill('Meeting Expenses', '5000', 'Expense', 'Costs for club meetings')">
                                5000 - Meeting Expenses
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm me-2 mb-2"
                                onclick="quickFill('Utilities', '5100', 'Expense', 'Repeater and facility utilities')">
                                5100 - Utilities
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm me-2 mb-2"
                                onclick="quickFill('Equipment Maintenance', '5200', 'Expense', 'Repair and maintenance costs')">
                                5200 - Equipment Maintenance
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.querySelector('form');
            const nameField = document.getElementById('name');
            const accountNumberField = document.getElementById('account_number');
            const accountTypeField = document.getElementById('account_type');

            // Real-time validation
            nameField.addEventListener('input', function() {
                if (this.value.length > 0) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });

            accountNumberField.addEventListener('input', function() {
                // Only allow alphanumeric and hyphens
                this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '');

                if (this.value.length > 0) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });

            accountTypeField.addEventListener('change', function() {
                if (this.value !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');

                    // Filter parent accounts by type
                    filterParentAccounts(this.value);
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const requiredFields = [nameField, accountNumberField, accountTypeField];

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                        field.classList.add('is-valid');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    showToast('danger', 'Validation Error', 'Please fill in all required fields.', 'club-logo');
                }
            });
        });

        // Quick fill function for example buttons
        function quickFill(name, number, type, description) {
            document.getElementById('name').value = name;
            document.getElementById('account_number').value = number;
            document.getElementById('account_type').value = type;
            document.getElementById('description').value = description;

            // Trigger validation styling
            document.getElementById('name').classList.add('is-valid');
            document.getElementById('account_number').classList.add('is-valid');
            document.getElementById('account_type').classList.add('is-valid');

            // Filter parent accounts
            filterParentAccounts(type);

            // Scroll to form
            document.querySelector('.card form').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Filter parent accounts by selected account type
        function filterParentAccounts(selectedType) {
            const parentSelect = document.getElementById('parent_account_id');
            const optgroups = parentSelect.querySelectorAll('optgroup');

            optgroups.forEach(optgroup => {
                if (optgroup.label.startsWith(selectedType)) {
                    optgroup.style.display = 'block';
                } else {
                    optgroup.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>