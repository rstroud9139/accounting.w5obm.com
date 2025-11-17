<?php

/**
 * Edit Ledger Account - W5OBM Accounting System
 * File: /accounting/ledger/edit.php
 * Purpose: Form to edit existing ledger account
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
if (!hasPermission($user_id, 'accounting_edit') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to edit ledger accounts.', 'club-logo');
    header('Location: /accounting/ledger/');
    exit();
}

// Get account ID
$account_id = intval($_GET['id'] ?? 0);
if (!$account_id) {
    setToastMessage('danger', 'Invalid Request', 'Account ID is required.', 'club-logo');
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

$errors = [];
$success = false;

// Get existing account data
try {
    $account = getLedgerAccountById($account_id);
    if (!$account) {
        setToastMessage('danger', 'Account Not Found', 'The requested account could not be found.', 'club-logo');
        header('Location: /accounting/ledger/');
        exit();
    }
} catch (Exception $e) {
    setToastMessage('danger', 'Error', 'Could not load account data.', 'club-logo');
    header('Location: /accounting/ledger/');
    exit();
}

// Initialize form data with existing account data
$form_data = [
    'name' => $account['name'],
    'account_number' => $account['account_number'],
    'account_type' => $account['account_type'],
    'description' => $account['description'] ?? '',
    'parent_account_id' => $account['parent_account_id']
];

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
        $validation = validateLedgerAccountData($form_data, $account_id);

        if ($validation['valid']) {
            // Attempt to update account
            $result = updateLedgerAccount($account_id, $form_data);

            if ($result) {
                logActivity(
                    $user_id,
                    'ledger_account_updated',
                    'acc_ledger_accounts',
                    $account_id,
                    "Updated ledger account: {$form_data['name']} ({$form_data['account_number']})"
                );

                setToastMessage(
                    'success',
                    'Account Updated',
                    'Ledger account "' . $form_data['name'] . '" has been updated successfully.',
                    'club-logo'
                );
                header('Location: /accounting/ledger/');
                exit();
            } else {
                $errors[] = 'Failed to update account. Please check the error logs.';
            }
        } else {
            $errors = $validation['errors'];
        }
    }
}

// Get potential parent accounts for dropdown (excluding current account and its children)
try {
    $all_accounts = getAllLedgerAccounts(['active' => true], ['order_by' => 'account_type, account_number']);
    // Filter out current account and its children to prevent circular references
    $parent_accounts = array_filter($all_accounts, function ($acc) use ($account_id) {
        return $acc['id'] != $account_id && $acc['parent_account_id'] != $account_id;
    });
} catch (Exception $e) {
    $parent_accounts = [];
    logError("Error getting parent accounts: " . $e->getMessage(), 'accounting');
}

// Check if account has transactions
$has_transactions = false;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_transactions WHERE account_id = ?");
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $has_transactions = $result['count'] > 0;
    $transaction_count = $result['count'];
    $stmt->close();
} catch (Exception $e) {
    $transaction_count = 0;
}

$page_title = "Edit Ledger Account - W5OBM Accounting";
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
            <div class="card-header bg-warning text-dark">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-edit fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Edit Ledger Account</h3>
                        <small>Modify account: <?= htmlspecialchars($account['account_number']) ?> - <?= htmlspecialchars($account['name']) ?></small>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group" role="group">
                            <a href="/accounting/ledger/account_detail.php?id=<?= $account_id ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-eye me-1"></i>View Details
                            </a>
                            <a href="/accounting/ledger/" class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Chart of Accounts
                            </a>
                        </div>
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

        <!-- Account Info Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Account Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-sm-6">
                                <strong>Account Number:</strong> <?= htmlspecialchars($account['account_number']) ?><br>
                                <strong>Account Type:</strong> <?= htmlspecialchars($account['account_type']) ?><br>
                                <strong>Status:</strong> <span class="badge bg-<?= $account['active'] ? 'success' : 'secondary' ?>"><?= $account['active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                            <div class="col-sm-6">
                                <strong>Transactions:</strong> <?= number_format($transaction_count) ?><br>
                                <strong>Current Balance:</strong> $<?= number_format(getAccountBalance($account_id), 2) ?><br>
                                <strong>Created:</strong> <?= date('M j, Y', strtotime($account['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <?php if ($has_transactions): ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> This account has <?= number_format($transaction_count) ?> transaction(s).
                                Changing the account type may affect reports.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>Edit Account Details
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
                                placeholder="Enter account name">
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
                            <?php if ($has_transactions): ?>
                                <div class="text-warning small mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Changing this will affect existing transaction references
                                </div>
                            <?php endif; ?>
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
                            <?php if ($has_transactions): ?>
                                <div class="text-warning small mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Changing this may affect financial reports
                                </div>
                            <?php endif; ?>
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
                                foreach ($parent_accounts as $parent_account):
                                    if ($current_type !== $parent_account['account_type']):
                                        if ($current_type !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($parent_account['account_type']) . ' Accounts">';
                                        $current_type = $parent_account['account_type'];
                                    endif;
                                ?>
                                    <option value="<?= $parent_account['id'] ?>"
                                        <?= $form_data['parent_account_id'] == $parent_account['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($parent_account['account_number']) ?> - <?= htmlspecialchars($parent_account['name']) ?>
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
                            <a href="/accounting/ledger/" class="btn btn-outline-secondary btn-lg me-2">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <a href="/accounting/ledger/account_detail.php?id=<?= $account_id ?>" class="btn btn-outline-info btn-lg">
                                <i class="fas fa-eye me-1"></i>View Account Details
                            </a>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-outline-warning btn-lg me-2" onclick="resetForm()">
                                <i class="fas fa-undo me-1"></i>Reset Changes
                            </button>
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="fas fa-save me-1"></i>Update Account
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Related Information -->
        <?php if ($has_transactions): ?>
            <div class="card shadow mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Related Transactions
                    </h5>
                </div>
                <div class="card-body">
                    <p>This account has <strong><?= number_format($transaction_count) ?></strong> associated transaction(s).</p>
                    <div class="row">
                        <div class="col-md-6">
                            <a href="/accounting/transactions/transactions.php?account_id=<?= $account_id ?>" class="btn btn-outline-primary">
                                <i class="fas fa-list me-1"></i>View All Transactions
                            </a>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                Current Balance: <strong>$<?= number_format(getAccountBalance($account_id), 2) ?></strong>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        // Store original form values for reset functionality
        const originalValues = {
            name: <?= json_encode($account['name']) ?>,
            account_number: <?= json_encode($account['account_number']) ?>,
            account_type: <?= json_encode($account['account_type']) ?>,
            description: <?= json_encode($account['description'] ?? '') ?>,
            parent_account_id: <?= json_encode($account['parent_account_id']) ?>
        };

        function resetForm() {
            document.getElementById('name').value = originalValues.name;
            document.getElementById('account_number').value = originalValues.account_number;
            document.getElementById('account_type').value = originalValues.account_type;
            document.getElementById('description').value = originalValues.description;
            document.getElementById('parent_account_id').value = originalValues.parent_account_id || '';

            // Remove validation classes
            document.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                el.classList.remove('is-valid', 'is-invalid');
            });
        }

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
                } else {
                    // Show confirmation for accounts with transactions
                    <?php if ($has_transactions): ?>
                        if (!confirm('This account has <?= $transaction_count ?> transactions. Are you sure you want to update it? This may affect existing reports.')) {
                            e.preventDefault();
                            return false;
                        }
                    <?php endif; ?>
                }
            });
        });

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