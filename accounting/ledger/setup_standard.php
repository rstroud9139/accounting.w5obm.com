<?php

/**
 * Setup Standard Accounts - W5OBM Accounting System
 * File: /accounting/ledger/setup_standard.php
 * Purpose: Auto-create standard chart of accounts for amateur radio club
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines with comprehensive account creation
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
    setToastMessage('danger', 'Access Denied', 'You do not have permission to setup standard accounts.', 'club-logo');
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
$created_accounts = [];
$setup_complete = false;

// Standard Chart of Accounts for Amateur Radio Club
$standard_accounts = [
    // ASSETS (1000-1999)
    ['name' => 'Checking Account', 'account_number' => '1000', 'account_type' => 'Asset', 'description' => 'Primary checking account for day-to-day operations'],
    ['name' => 'Savings Account', 'account_number' => '1100', 'account_type' => 'Asset', 'description' => 'Savings account for reserves and long-term funds'],
    ['name' => 'Petty Cash', 'account_number' => '1200', 'account_type' => 'Asset', 'description' => 'Cash on hand for small expenses'],
    ['name' => 'Accounts Receivable', 'account_number' => '1300', 'account_type' => 'Asset', 'description' => 'Money owed to the club'],
    ['name' => 'Radio Equipment', 'account_number' => '1500', 'account_type' => 'Asset', 'description' => 'Radios, antennas, repeaters, and related equipment'],
    ['name' => 'Office Equipment', 'account_number' => '1510', 'account_type' => 'Asset', 'description' => 'Computers, printers, and office equipment'],
    ['name' => 'Furniture & Fixtures', 'account_number' => '1520', 'account_type' => 'Asset', 'description' => 'Meeting room furniture and fixtures'],
    ['name' => 'Accumulated Depreciation - Equipment', 'account_number' => '1600', 'account_type' => 'Asset', 'description' => 'Accumulated depreciation on equipment (contra-asset)'],

    // LIABILITIES (2000-2999)
    ['name' => 'Accounts Payable', 'account_number' => '2000', 'account_type' => 'Liability', 'description' => 'Money owed to vendors and suppliers'],
    ['name' => 'Accrued Expenses', 'account_number' => '2100', 'account_type' => 'Liability', 'description' => 'Expenses incurred but not yet paid'],
    ['name' => 'Deferred Revenue', 'account_number' => '2200', 'account_type' => 'Liability', 'description' => 'Pre-paid membership dues and event fees'],
    ['name' => 'Loans Payable', 'account_number' => '2300', 'account_type' => 'Liability', 'description' => 'Outstanding loans and debt'],

    // EQUITY (3000-3999)
    ['name' => 'Retained Earnings', 'account_number' => '3000', 'account_type' => 'Equity', 'description' => 'Accumulated earnings from previous years'],
    ['name' => 'Current Year Earnings', 'account_number' => '3100', 'account_type' => 'Equity', 'description' => 'Net income/loss for current year'],
    ['name' => 'Equipment Fund', 'account_number' => '3200', 'account_type' => 'Equity', 'description' => 'Funds reserved for equipment purchases'],

    // INCOME (4000-4999)
    ['name' => 'Membership Dues', 'account_number' => '4000', 'account_type' => 'Income', 'description' => 'Annual membership dues from members'],
    ['name' => 'Donations', 'account_number' => '4100', 'account_type' => 'Income', 'description' => 'General donations and gifts received'],
    ['name' => 'Equipment Donations', 'account_number' => '4110', 'account_type' => 'Income', 'description' => 'Value of equipment donated to the club'],
    ['name' => 'Event Income', 'account_number' => '4200', 'account_type' => 'Income', 'description' => 'Income from hamfests, field day, and other events'],
    ['name' => 'Training & Testing Fees', 'account_number' => '4300', 'account_type' => 'Income', 'description' => 'Fees collected for license testing and training'],
    ['name' => 'Interest Income', 'account_number' => '4400', 'account_type' => 'Income', 'description' => 'Interest earned on savings and investments'],
    ['name' => 'Equipment Sales', 'account_number' => '4500', 'account_type' => 'Income', 'description' => 'Revenue from selling club equipment'],
    ['name' => 'Raffle & Fundraising', 'account_number' => '4600', 'account_type' => 'Income', 'description' => 'Income from raffles and fundraising activities'],

    // EXPENSES (5000-5999)
    ['name' => 'Meeting Expenses', 'account_number' => '5000', 'account_type' => 'Expense', 'description' => 'Costs for meeting venue, refreshments, speakers'],
    ['name' => 'Utilities', 'account_number' => '5100', 'account_type' => 'Expense', 'description' => 'Electricity, internet, phone for repeater sites'],
    ['name' => 'Site Rent', 'account_number' => '5110', 'account_type' => 'Expense', 'description' => 'Rent for repeater and meeting locations'],
    ['name' => 'Equipment Maintenance', 'account_number' => '5200', 'account_type' => 'Expense', 'description' => 'Repairs and maintenance of club equipment'],
    ['name' => 'Equipment Purchases', 'account_number' => '5210', 'account_type' => 'Expense', 'description' => 'New equipment and upgrades'],
    ['name' => 'Insurance', 'account_number' => '5300', 'account_type' => 'Expense', 'description' => 'Liability and equipment insurance'],
    ['name' => 'Office Supplies', 'account_number' => '5400', 'account_type' => 'Expense', 'description' => 'Paper, postage, printing, and office supplies'],
    ['name' => 'Event Expenses', 'account_number' => '5500', 'account_type' => 'Expense', 'description' => 'Costs for hamfests, field day, and other events'],
    ['name' => 'Professional Services', 'account_number' => '5600', 'account_type' => 'Expense', 'description' => 'Legal, accounting, and other professional fees'],
    ['name' => 'Bank Fees', 'account_number' => '5700', 'account_type' => 'Expense', 'description' => 'Banking fees and transaction charges'],
    ['name' => 'Travel & Transportation', 'account_number' => '5800', 'account_type' => 'Expense', 'description' => 'Travel expenses for club activities'],
    ['name' => 'Depreciation Expense', 'account_number' => '5900', 'account_type' => 'Expense', 'description' => 'Depreciation on club equipment and assets'],
    ['name' => 'Miscellaneous Expense', 'account_number' => '5990', 'account_type' => 'Expense', 'description' => 'Other miscellaneous expenses']
];

// Handle setup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_accounts'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $selected_accounts = $_POST['accounts'] ?? [];

        if (empty($selected_accounts)) {
            $errors[] = 'Please select at least one account to create.';
        } else {
            foreach ($selected_accounts as $index) {
                if (isset($standard_accounts[$index])) {
                    $account_data = $standard_accounts[$index];

                    // Check if account already exists
                    $stmt = $conn->prepare("SELECT id FROM acc_ledger_accounts WHERE account_number = ?");
                    $stmt->bind_param('s', $account_data['account_number']);
                    $stmt->execute();

                    if ($stmt->get_result()->num_rows > 0) {
                        $errors[] = "Account {$account_data['account_number']} - {$account_data['name']} already exists.";
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();

                    $account_id = addLedgerAccount($account_data);

                    if ($account_id) {
                        $created_accounts[] = $account_data;
                    } else {
                        $errors[] = "Failed to create account: {$account_data['name']}";
                    }
                }
            }

            if (!empty($created_accounts) && empty($errors)) {
                $setup_complete = true;
                logActivity(
                    $user_id,
                    'standard_accounts_created',
                    'acc_ledger_accounts',
                    null,
                    "Created " . count($created_accounts) . " standard chart of accounts"
                );

                setToastMessage(
                    'success',
                    'Setup Complete',
                    'Successfully created ' . count($created_accounts) . ' standard accounts.',
                    'club-logo'
                );
            }
        }
    }
}

$page_title = "Setup Standard Chart of Accounts - W5OBM Accounting";
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
            <div class="card-header bg-info text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-magic fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Setup Standard Chart of Accounts</h3>
                        <small>Create a complete chart of accounts for your amateur radio club</small>
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
                        <h6 class="alert-heading mb-2">Setup Issues:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($setup_complete): ?>
            <div class="alert alert-success border-0 shadow mb-4">
                <div class="d-flex">
                    <i class="fas fa-check-circle me-3 mt-1 text-success"></i>
                    <div>
                        <h6 class="alert-heading mb-2">Setup Complete!</h6>
                        <p class="mb-2">Successfully created <?= count($created_accounts) ?> standard accounts:</p>
                        <ul class="mb-3">
                            <?php foreach ($created_accounts as $account): ?>
                                <li><?= htmlspecialchars($account['account_number']) ?> - <?= htmlspecialchars($account['name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div>
                            <a href="/accounting/ledger/" class="btn btn-success">
                                <i class="fas fa-sitemap me-1"></i>View Chart of Accounts
                            </a>
                            <a href="/accounting/transactions/add_transaction.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Add First Transaction
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Information Card -->
        <?php if (!$setup_complete): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>About Standard Chart of Accounts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p>This setup wizard will create a comprehensive chart of accounts designed specifically for amateur radio clubs. The accounts are organized into the five main categories:</p>

                            <div class="row">
                                <div class="col-sm-6">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-coins text-success me-2"></i><strong>Assets</strong> - Things you own</li>
                                        <li><i class="fas fa-credit-card text-danger me-2"></i><strong>Liabilities</strong> - Money you owe</li>
                                        <li><i class="fas fa-balance-scale text-info me-2"></i><strong>Equity</strong> - Club's net worth</li>
                                    </ul>
                                </div>
                                <div class="col-sm-6">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-arrow-up text-primary me-2"></i><strong>Income</strong> - Money coming in</li>
                                        <li><i class="fas fa-arrow-down text-warning me-2"></i><strong>Expenses</strong> - Money going out</li>
                                    </ul>
                                </div>
                            </div>

                            <p class="mb-0">You can customize this list by selecting only the accounts you need, or create all accounts and deactivate unused ones later.</p>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-3 rounded">
                                <h6 class="text-primary">Benefits:</h6>
                                <ul class="small mb-0">
                                    <li>Industry standard numbering</li>
                                    <li>Amateur radio specific accounts</li>
                                    <li>Ready for financial reporting</li>
                                    <li>Easy to extend and customize</li>
                                    <li>Professional organization</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Selection Form -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Quick Actions -->
                <div class="card shadow mb-4">
                    <div class="card-body text-center">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="selectAll()">
                            <i class="fas fa-check-double me-1"></i>Select All Accounts
                        </button>
                        <button type="button" class="btn btn-outline-secondary me-2" onclick="selectNone()">
                            <i class="fas fa-times me-1"></i>Clear All
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="selectRecommended()">
                            <i class="fas fa-star me-1"></i>Select Recommended
                        </button>
                    </div>
                </div>

                <!-- Accounts by Category -->
                <?php
                $categories = [
                    'Asset' => ['color' => 'success', 'icon' => 'coins', 'range' => '1000-1999'],
                    'Liability' => ['color' => 'danger', 'icon' => 'credit-card', 'range' => '2000-2999'],
                    'Equity' => ['color' => 'info', 'icon' => 'balance-scale', 'range' => '3000-3999'],
                    'Income' => ['color' => 'primary', 'icon' => 'arrow-up', 'range' => '4000-4999'],
                    'Expense' => ['color' => 'warning', 'icon' => 'arrow-down', 'range' => '5000-5999']
                ];

                foreach ($categories as $category => $config):
                    $category_accounts = array_filter($standard_accounts, function ($account) use ($category) {
                        return $account['account_type'] === $category;
                    });
                ?>
                    <div class="card shadow mb-4">
                        <div class="card-header bg-<?= $config['color'] ?> <?= $category === 'Expense' ? 'text-dark' : 'text-white' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-<?= $config['icon'] ?> me-2"></i><?= $category ?> Accounts (<?= $config['range'] ?>)
                                </h5>
                                <div>
                                    <button type="button" class="btn btn-<?= $category === 'Expense' ? 'dark' : 'light' ?> btn-sm" onclick="toggleCategory('<?= strtolower($category) ?>')">
                                        <i class="fas fa-check-square me-1"></i>Select All
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($category_accounts as $index => $account): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input category-<?= strtolower($category) ?>" type="checkbox"
                                                name="accounts[]" value="<?= $index ?>" id="account_<?= $index ?>">
                                            <label class="form-check-label" for="account_<?= $index ?>">
                                                <strong><?= htmlspecialchars($account['account_number']) ?> - <?= htmlspecialchars($account['name']) ?></strong>
                                                <div class="small text-muted"><?= htmlspecialchars($account['description']) ?></div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Form Actions -->
                <div class="card shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span id="selectedCount" class="text-muted">0 accounts selected</span>
                            </div>
                            <div>
                                <a href="/accounting/ledger/" class="btn btn-outline-secondary btn-lg me-2">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" name="create_accounts" class="btn btn-success btn-lg">
                                    <i class="fas fa-magic me-1"></i>Create Selected Accounts
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        function updateSelectedCount() {
            const selected = document.querySelectorAll('input[name="accounts[]"]:checked').length;
            document.getElementById('selectedCount').textContent = `${selected} accounts selected`;
        }

        function toggleCategory(category) {
            const checkboxes = document.querySelectorAll('.category-' + category);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            updateSelectedCount();
        }

        function selectAll() {
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }

        function selectNone() {
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        function selectRecommended() {
            selectNone();

            const recommended = [
                '1000', '1100', '1200', '1500',
                '2000', '2200',
                '3000', '3100',
                '4000', '4100', '4200', '4400',
                '5000', '5100', '5200', '5300', '5400', '5500'
            ];

            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                const label = checkbox.nextElementSibling.textContent;
                const accountNumber = label.match(/^\d+/);

                if (accountNumber && recommended.includes(accountNumber[0])) {
                    checkbox.checked = true;
                }
            });
            updateSelectedCount();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Update count on checkbox changes
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Form submission validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const selected = document.querySelectorAll('input[name="accounts[]"]:checked');

                if (selected.length === 0) {
                    e.preventDefault();
                    showToast('warning', 'No Selection', 'Please select at least one account to create.', 'club-logo');
                    return false;
                }

                const accountCount = selected.length;
                if (!confirm(`Are you sure you want to create ${accountCount} accounts? This action cannot be undone.`)) {
                    e.preventDefault();
                    return false;
                }
            });

            updateSelectedCount();
        });
    </script>
</body>

</html>