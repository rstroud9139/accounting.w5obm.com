<?php

/**
 * Transaction Form View - W5OBM Accounting System
 * File: /accounting/views/transactionForm.php
 * Purpose: Reusable transaction form component
 * SECURITY: All inputs are sanitized and validated
 * UPDATED: Follows Website Guidelines with proper responsive design
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Render transaction form
 * @param array $transaction Existing transaction data for edit mode
 * @param array $categories Available categories
 * @param array $accounts Available accounts
 * @param array $vendors Available vendors
 * @param string $action Form action URL
 * @param string $mode 'add' or 'edit'
 */
function renderTransactionForm($transaction = [], $categories = [], $accounts = [], $vendors = [], $action = '', $mode = 'add')
{
    // CSRF token generation via utility if available
    if (function_exists('csrf_ensure_token')) {
        csrf_ensure_token();
    } elseif (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Set default values
    $defaults = [
        'id' => '',
        'category_id' => '',
        'amount' => '',
        'transaction_date' => date('Y-m-d'),
        'description' => '',
        'type' => '',
        'account_id' => '',
        'vendor_id' => '',
        'reference_number' => '',
        'notes' => ''
    ];

    $data = array_merge($defaults, $transaction);
    $is_edit = ($mode === 'edit' && !empty($data['id']));
    $form_title = $is_edit ? 'Edit Transaction' : 'Add New Transaction';
    $form_icon = $is_edit ? 'fa-edit' : 'fa-plus';
    $submit_text = $is_edit ? 'Update Transaction' : 'Add Transaction';

?>

    <!-- Transaction Form Card -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="fas <?= $form_icon ?> fa-2x"></i>
                </div>
                <div class="col">
                    <h4 class="mb-0"><?= htmlspecialchars($form_title) ?></h4>
                    <small>Enter transaction details below</small>
                </div>
                <div class="col-auto">
                    <a href="/accounting/transactions/" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Transactions
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form method="POST" action="<?= htmlspecialchars($action) ?>" id="transactionForm" class="needs-validation" novalidate>
                <!-- CSRF Token -->
                <?php if (function_exists('csrf_field')) {
                    csrf_field();
                } else { ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <?php } ?>

                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <!-- Transaction Type -->
                    <div class="col-md-6 mb-3">
                        <label for="type" class="form-label">
                            <i class="fas fa-exchange-alt me-1 text-primary"></i>Transaction Type *
                        </label>
                        <select class="form-select form-control-lg" id="type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="Income" <?= $data['type'] === 'Income' ? 'selected' : '' ?>>Income</option>
                            <option value="Expense" <?= $data['type'] === 'Expense' ? 'selected' : '' ?>>Expense</option>
                            <option value="Asset" <?= $data['type'] === 'Asset' ? 'selected' : '' ?>>Asset Purchase</option>
                            <option value="Transfer" <?= $data['type'] === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                        </select>
                        <div class="invalid-feedback">Please select a transaction type.</div>
                    </div>

                    <!-- Amount -->
                    <div class="col-md-6 mb-3">
                        <label for="amount" class="form-label">
                            <i class="fas fa-dollar-sign me-1 text-success"></i>Amount *
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control form-control-lg" id="amount" name="amount"
                                value="<?= htmlspecialchars($data['amount']) ?>"
                                step="0.01" min="0.01" max="999999.99" required>
                            <div class="invalid-feedback">Please enter a valid amount between $0.01 and $999,999.99.</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Category -->
                    <div class="col-md-6 mb-3">
                        <label for="category_id" class="form-label">
                            <i class="fas fa-tags me-1 text-info"></i>Category *
                        </label>
                        <select class="form-select form-control-lg" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php
                            $current_type = '';
                            foreach ($categories as $category):
                                if ($category['type'] !== $current_type) {
                                    if ($current_type !== '') echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($category['type']) . '">';
                                    $current_type = $category['type'];
                                }
                            ?>
                                <option value="<?= $category['id'] ?>"
                                    <?= $data['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                    <?php if (!empty($category['description'])): ?>
                                        - <?= htmlspecialchars(substr($category['description'], 0, 50)) ?><?= strlen($category['description']) > 50 ? '...' : '' ?>
                                    <?php endif; ?>
                                </option>
                            <?php
                            endforeach;
                            if ($current_type !== '') echo '</optgroup>';
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a category.</div>
                    </div>

                    <!-- Transaction Date -->
                    <div class="col-md-6 mb-3">
                        <label for="transaction_date" class="form-label">
                            <i class="fas fa-calendar-alt me-1 text-warning"></i>Transaction Date *
                        </label>
                        <input type="date" class="form-control form-control-lg" id="transaction_date" name="transaction_date"
                            value="<?= htmlspecialchars($data['transaction_date']) ?>" required>
                        <div class="invalid-feedback">Please enter a valid transaction date.</div>
                    </div>
                </div>

                <div class="row">
                    <!-- Account (Optional) -->
                    <div class="col-md-6 mb-3">
                        <label for="account_id" class="form-label">
                            <i class="fas fa-university me-1 text-secondary"></i>Account
                        </label>
                        <select class="form-select form-control-lg" id="account_id" name="account_id">
                            <option value="">Select Account (Optional)</option>
                            <?php
                            $current_type = '';
                            foreach ($accounts as $account):
                                if ($account['account_type'] !== $current_type) {
                                    if ($current_type !== '') echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($account['account_type']) . ' Accounts">';
                                    $current_type = $account['account_type'];
                                }
                            ?>
                                <option value="<?= $account['id'] ?>"
                                    <?= $data['account_id'] == $account['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($account['account_number']) ?> - <?= htmlspecialchars($account['name']) ?>
                                </option>
                            <?php
                            endforeach;
                            if ($current_type !== '') echo '</optgroup>';
                            ?>
                        </select>
                    </div>

                    <!-- Vendor (Optional) -->
                    <div class="col-md-6 mb-3">
                        <label for="vendor_id" class="form-label">
                            <i class="fas fa-store me-1 text-secondary"></i>Vendor
                        </label>
                        <select class="form-select form-control-lg" id="vendor_id" name="vendor_id">
                            <option value="">Select Vendor (Optional)</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>"
                                    <?= $data['vendor_id'] == $vendor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vendor['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left me-1 text-dark"></i>Description *
                    </label>
                    <input type="text" class="form-control form-control-lg" id="description" name="description"
                        value="<?= htmlspecialchars($data['description']) ?>"
                        maxlength="500" required placeholder="Brief description of the transaction">
                    <div class="form-text">Maximum 500 characters</div>
                    <div class="invalid-feedback">Please enter a description.</div>
                </div>

                <!-- Reference Number -->
                <div class="mb-3">
                    <label for="reference_number" class="form-label">
                        <i class="fas fa-hashtag me-1 text-secondary"></i>Reference Number
                    </label>
                    <input type="text" class="form-control form-control-lg" id="reference_number" name="reference_number"
                        value="<?= htmlspecialchars($data['reference_number']) ?>"
                        maxlength="100" placeholder="Check number, invoice number, etc. (optional)">
                    <div class="form-text">Optional reference number for tracking</div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label for="notes" class="form-label">
                        <i class="fas fa-sticky-note me-1 text-secondary"></i>Notes
                    </label>
                    <textarea class="form-control form-control-lg" id="notes" name="notes" rows="3"
                        maxlength="1000" placeholder="Additional notes or details (optional)"><?= htmlspecialchars($data['notes']) ?></textarea>
                    <div class="form-text">Optional additional details (maximum 1000 characters)</div>
                </div>

                <!-- Form Actions -->
                <div class="action-area bg-light border-top mx-n3 mt-4 p-3">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-save me-2"></i><?= htmlspecialchars($submit_text) ?>
                            </button>
                        </div>
                        <div class="col-md-6 mt-2 mt-md-0">
                            <a href="/accounting/transactions/" class="btn btn-secondary btn-lg w-100">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Form validation and enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('transactionForm');
            const typeField = document.getElementById('type');
            const categoryField = document.getElementById('category_id');

            // Bootstrap validation
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });

            // Filter categories based on transaction type
            typeField.addEventListener('change', function() {
                // This could be enhanced to filter categories by type
                // For now, just ensure a category is selected
                if (this.value && !categoryField.value) {
                    categoryField.focus();
                }
            });

            // Real-time amount validation
            const amountField = document.getElementById('amount');
            amountField.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value > 999999.99) {
                    this.setCustomValidity('Amount cannot exceed $999,999.99');
                } else if (value <= 0) {
                    this.setCustomValidity('Amount must be greater than $0.00');
                } else {
                    this.setCustomValidity('');
                }
            });

            // Date validation
            const dateField = document.getElementById('transaction_date');
            dateField.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                const maxFuture = new Date();
                maxFuture.setFullYear(today.getFullYear() + 1);
                const minPast = new Date();
                minPast.setFullYear(today.getFullYear() - 10);

                if (selectedDate > maxFuture) {
                    this.setCustomValidity('Date cannot be more than 1 year in the future');
                } else if (selectedDate < minPast) {
                    this.setCustomValidity('Date cannot be more than 10 years in the past');
                } else {
                    this.setCustomValidity('');
                }
            });
        });
    </script>

<?php
}
?>