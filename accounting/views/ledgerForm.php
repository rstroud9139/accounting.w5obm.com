<?php

/**
 * Ledger Form View - W5OBM Accounting System
 * File: /accounting/views/ledgerForm.php
 * Purpose: Reusable ledger account form component
 * SECURITY: All inputs are sanitized and validated
 * UPDATED: Follows Website Guidelines with proper responsive design
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Render ledger account form
 * @param array $account Existing account data for edit mode
 * @param array $parent_accounts Available parent accounts
 * @param string $action Form action URL
 * @param string $mode 'add' or 'edit'
 */
function renderLedgerForm($account = [], $parent_accounts = [], $action = '', $mode = 'add')
{
    // CSRF token generation
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Set default values
    $defaults = [
        'id' => '',
        'name' => '',
        'account_number' => '',
        'account_type' => '',
        'description' => '',
        'parent_account_id' => ''
    ];

    $data = array_merge($defaults, $account);
    $is_edit = ($mode === 'edit' && !empty($data['id']));
    $form_title = $is_edit ? 'Edit Ledger Account' : 'Add New Ledger Account';
    $form_icon = $is_edit ? 'fa-edit' : 'fa-plus';
    $submit_text = $is_edit ? 'Update Account' : 'Add Account';

    // Account types with descriptions
    $account_types = [
        'Asset' => 'Assets (Cash, Equipment, Inventory)',
        'Liability' => 'Liabilities (Loans, Accounts Payable)',
        'Equity' => 'Equity (Retained Earnings, Capital)',
        'Income' => 'Income (Revenue, Donations, Dues)',
        'Expense' => 'Expenses (Operating Costs, Supplies)'
    ];

?>

    <!-- Ledger Account Form Card -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="fas <?= $form_icon ?> fa-2x"></i>
                </div>
                <div class="col">
                    <h4 class="mb-0"><?= htmlspecialchars($form_title) ?></h4>
                    <small>Configure chart of accounts</small>
                </div>
                <div class="col-auto">
                    <a href="/accounting/ledger/" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Chart of Accounts
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form method="POST" action="<?= htmlspecialchars($action) ?>" id="ledgerForm" class="needs-validation" novalidate>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <!-- Account Name -->
                    <div class="col-md-8 mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-tag me-1 text-primary"></i>Account Name *
                        </label>
                        <input type="text" class="form-control form-control-lg" id="name" name="name"
                            value="<?= htmlspecialchars($data['name']) ?>"
                            maxlength="255" required
                            placeholder="Enter descriptive account name">
                        <div class="invalid-feedback">Please enter an account name.</div>
                    </div>

                    <!-- Account Number -->
                    <div class="col-md-4 mb-3">
                        <label for="account_number" class="form-label">
                            <i class="fas fa-hashtag me-1 text-info"></i>Account Number *
                        </label>
                        <input type="text" class="form-control form-control-lg" id="account_number" name="account_number"
                            value="<?= htmlspecialchars($data['account_number']) ?>"
                            maxlength="50" required
                            placeholder="e.g., 1000, CASH-01"
                            pattern="[A-Za-z0-9-]+"
                            title="Only letters, numbers, and hyphens allowed">
                        <div class="form-text">Unique identifier (letters, numbers, hyphens only)</div>
                        <div class="invalid-feedback">Please enter a valid account number.</div>
                    </div>
                </div>

                <div class="row">
                    <!-- Account Type -->
                    <div class="col-md-6 mb-3">
                        <label for="account_type" class="form-label">
                            <i class="fas fa-layer-group me-1 text-success"></i>Account Type *
                        </label>
                        <select class="form-select form-control-lg" id="account_type" name="account_type" required>
                            <option value="">Select Account Type</option>
                            <?php foreach ($account_types as $type => $description): ?>
                                <option value="<?= $type ?>"
                                    <?= $data['account_type'] === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($description) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select an account type.</div>
                    </div>

                    <!-- Parent Account -->
                    <div class="col-md-6 mb-3">
                        <label for="parent_account_id" class="form-label">
                            <i class="fas fa-sitemap me-1 text-secondary"></i>Parent Account
                        </label>
                        <select class="form-select form-control-lg" id="parent_account_id" name="parent_account_id">
                            <option value="">None (Top Level Account)</option>
                            <?php foreach ($parent_accounts as $parent): ?>
                                <?php if (!$is_edit || $parent['id'] != $data['id']): // Don't allow self as parent 
                                ?>
                                    <option value="<?= $parent['id'] ?>"
                                        <?= $data['parent_account_id'] == $parent['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($parent['account_number']) ?> - <?= htmlspecialchars($parent['name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Optional: Create sub-account under existing account</div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left me-1 text-dark"></i>Description
                    </label>
                    <textarea class="form-control form-control-lg" id="description" name="description"
                        rows="3" maxlength="1000"
                        placeholder="Optional detailed description of this account's purpose"><?= htmlspecialchars($data['description']) ?></textarea>
                    <div class="form-text">Optional description (maximum 1000 characters)</div>
                </div>

                <!-- Account Type Help -->
                <div class="alert alert-info border-0">
                    <div class="d-flex">
                        <i class="fas fa-info-circle me-3 mt-1 text-info"></i>
                        <div>
                            <h6 class="alert-heading mb-2">Account Type Guide</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Assets:</strong> Things the club owns (cash, equipment)</p>
                                    <p class="mb-1"><strong>Liabilities:</strong> Money the club owes (loans, bills)</p>
                                    <p class="mb-0"><strong>Equity:</strong> Club's net worth and capital</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Income:</strong> Money coming in (dues, donations, sales)</p>
                                    <p class="mb-0"><strong>Expenses:</strong> Money going out (utilities, supplies)</p>
                                </div>
                            </div>
                        </div>
                    </div>
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
                            <a href="/accounting/ledger/" class="btn btn-secondary btn-lg w-100">
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
            const form = document.getElementById('ledgerForm');
            const accountTypeField = document.getElementById('account_type');
            const parentAccountField = document.getElementById('parent_account_id');

            // Bootstrap validation
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });

            // Account number format validation
            const accountNumberField = document.getElementById('account_number');
            accountNumberField.addEventListener('input', function() {
                // Remove invalid characters as they type
                this.value = this.value.replace(/[^A-Za-z0-9-]/g, '');
            });

            // Filter parent accounts by type when account type changes
            accountTypeField.addEventListener('change', function() {
                const selectedType = this.value;
                const parentOptions = parentAccountField.options;

                // Show/hide parent account options based on selected type
                for (let i = 1; i < parentOptions.length; i++) { // Skip first "None" option
                    const option = parentOptions[i];
                    const optionText = option.textContent;

                    // Simple logic: only show parent accounts of the same type
                    // This could be enhanced with more sophisticated business rules
                    option.style.display = 'block'; // Show all for now
                }
            });

            // Auto-suggest account numbers based on type
            accountTypeField.addEventListener('change', function() {
                const accountNumberField = document.getElementById('account_number');

                // Only suggest if field is empty
                if (accountNumberField.value.trim() === '') {
                    const suggestions = {
                        'Asset': '1000',
                        'Liability': '2000',
                        'Equity': '3000',
                        'Income': '4000',
                        'Expense': '5000'
                    };

                    if (suggestions[this.value]) {
                        accountNumberField.value = suggestions[this.value];
                        accountNumberField.focus();
                        accountNumberField.select();
                    }
                }
            });
        });
    </script>

<?php
}
?>