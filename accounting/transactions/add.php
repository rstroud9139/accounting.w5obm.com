<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once '../../include/dbconn.php';
require_once __DIR__ . '/../controllers/transaction_controller.php';
require_once __DIR__ . '/../controllers/category_controller.php';
require_once __DIR__ . '/../controllers/ledger_controller.php';
require_once __DIR__ . '/../controllers/vendor_controller.php';

// Validate session
validate_session();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'];
    $amount = $_POST['amount'];
    $transaction_date = $_POST['transaction_date'];
    $description = $_POST['description'];
    $type = $_POST['type'];
    $account_id = !empty($_POST['account_id']) ? $_POST['account_id'] : null;
    $vendor_id = !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null;

    if (add_transaction($category_id, $amount, $transaction_date, $description, $type, $account_id, $vendor_id)) {
        header('Location: list.php?status=success');
        exit();
    } else {
        $error_message = "Failed to add transaction. Please try again.";
    }
}

// Fetch categories for dropdown
$categories = fetch_all_categories();

// Fetch ledger accounts for dropdown
$accounts = fetch_all_ledger_accounts();

// Fetch vendors for dropdown
$vendors = fetch_all_vendors();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Add Transaction</title>
    <?php include '../../include/header.php'; ?>
</head>

<body>
    <?php include '../../include/menu.php'; ?>

    <div class="container mt-5">
        <div class="d-flex align-items-center mb-4">
            <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
            <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
            <h2 class="ms-3">Add Transaction</h2>
        </div>

        <div class="card shadow">
            <div class="card-header">
                <h3>Add New Transaction</h3>
            </div>
            <div class="card-body">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <form action="add.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Transaction Type</label>
                            <select id="type" name="type" class="form-control" required>
                                <option value="Income">Income</option>
                                <option value="Expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="transaction_date" class="form-label">Transaction Date</label>
                            <input type="date" id="transaction_date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="account_id" class="form-label">Ledger Account</label>
                            <select id="account_id" name="account_id" class="form-control">
                                <option value="">Select an account (optional)</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= $account['id'] ?>"
                                        <?= (($_POST['account_id'] ?? '') == $account['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($account['name']) ?>
                                        <?php if (!empty($account['account_type'])): ?>
                                            <small>(<?= htmlspecialchars($account['account_type']) ?>)</small>
                                        <?php endif; ?>
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
                                        <?= (($_POST['vendor_id'] ?? '') == $vendor['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vendor['name']) ?>
                                        <?php if (!empty($vendor['vendor_type'])): ?>
                                            <small>(<?= htmlspecialchars($vendor['vendor_type']) ?>)</small>
                                        <?php endif; ?>
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
                                value="<?= htmlspecialchars($_POST['reference_number'] ?? '') ?>"
                                placeholder="Check #, Invoice #, etc.">
                            <div class="form-text">Optional reference number for tracking</div>
                        </div>

                        <!-- Description -->
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">
                                <i class="fas fa-comment me-1"></i>Description *
                            </label>
                            <input type="text" id="description" name="description"
                                class="form-control form-control-lg shadow-sm"
                                value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
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
                            rows="3" placeholder="Optional additional details or notes..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        <div class="form-text">Any additional information about this transaction</div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-area bg-light border-top p-3 rounded-bottom">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_another" name="add_another" value="1">
                                    <label class="form-check-label" for="add_another">
                                        <i class="fas fa-plus-circle me-1"></i>Add another transaction after saving
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group shadow" role="group">
                                    <a href="index.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-1"></i>Add Transaction
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Help Card -->
        <div class="card shadow mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>Quick Help
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-money-bill-wave text-success me-1"></i>Income Transactions</h6>
                        <p class="small mb-3">Record money coming into the organization such as membership dues, donations, fundraising income, or equipment sales.</p>

                        <h6><i class="fas fa-shopping-cart text-danger me-1"></i>Expense Transactions</h6>
                        <p class="small mb-3">Record money going out of the organization such as equipment purchases, utilities, insurance, or meeting expenses.</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-laptop text-primary me-1"></i>Asset Purchases</h6>
                        <p class="small mb-3">Record purchases of equipment or property that will be owned by the organization long-term.</p>

                        <h6><i class="fas fa-exchange-alt text-warning me-1"></i>Transfers</h6>
                        <p class="small mb-3">Record movement of money between different accounts or funds within the organization.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Validation JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('transactionForm');
            const typeSelect = document.getElementById('type');
            const amountInput = document.getElementById('amount');
            const descriptionInput = document.getElementById('description');

            // Form validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                let errors = [];

                // Validate amount
                const amount = parseFloat(amountInput.value);
                if (isNaN(amount) || amount <= 0) {
                    errors.push('Amount must be a positive number');
                    isValid = false;
                }
                if (amount > 999999.99) {
                    errors.push('Amount cannot exceed $999,999.99');
                    isValid = false;
                }

                // Validate description length
                if (descriptionInput.value.length > 500) {
                    errors.push('Description cannot exceed 500 characters');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    alert('Please fix the following errors:\n' + errors.join('\n'));
                    return false;
                }
            });

            // Auto-format amount on blur
            amountInput.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                }
            });

            // Character counter for description
            const charCounter = document.createElement('div');
            charCounter.className = 'form-text text-end';
            charCounter.style.fontSize = '0.8em';
            descriptionInput.parentNode.appendChild(charCounter);

            function updateCharCounter() {
                const remaining = 500 - descriptionInput.value.length;
                charCounter.textContent = `${remaining} characters remaining`;

            }

            descriptionInput.addEventListener('input', updateCharCounter);
            updateCharCounter();

            // Show success message if redirected after adding
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('added') === '1') {
                // Clear the URL parameter
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>