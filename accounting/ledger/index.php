<?php

/**
 * Chart of Accounts - W5OBM Accounting System
 * File: /accounting/ledger/index.php
 * Purpose: Main chart of accounts listing and management
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Define constant to allow view includes
define('W5OBM_ACCOUNTING', true);

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/ledgerController.php';
require_once __DIR__ . '/../views/ledger_list.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view the chart of accounts.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Chart of Accounts - W5OBM Accounting";

// Get filters from GET parameters
$filters = [
    'account_type' => sanitizeInput($_GET['account_type'] ?? '', 'string'),
    'search' => sanitizeInput($_GET['search'] ?? '', 'string'),
    'active' => true // Only show active accounts by default
];

// Remove empty filters
$filters = array_filter($filters, function ($value) {
    return $value !== '' && $value !== null;
});
$filters['active'] = true; // Ensure this stays

try {
    // Get chart of accounts in hierarchical structure
    $chart_of_accounts = getChartOfAccounts($filters['account_type'] ?? null);

    // If we have search or other filters, get flat list instead
    if (!empty($filters['search'])) {
        $chart_of_accounts = getAllLedgerAccounts($filters);
    }

    // Get account type totals for summary cards
    $totals = [];
    $account_types = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];

    foreach ($account_types as $type) {
        $type_accounts = getAllLedgerAccounts(['account_type' => $type, 'active' => true]);
        $totals[$type] = [
            'count' => count($type_accounts),
            'balance' => array_sum(array_map(function ($account) {
                return getAccountBalance($account['id']);
            }, $type_accounts))
        ];
    }
} catch (Exception $e) {
    $chart_of_accounts = [];
    $totals = [];
    setToastMessage('danger', 'Error', 'Failed to load chart of accounts: ' . $e->getMessage(), 'club-logo');
    logError("Error loading chart of accounts: " . $e->getMessage(), 'accounting');
}

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
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-sitemap fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Chart of Accounts</h3>
                        <small>Manage your accounting ledger and account structure</small>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group" role="group">
                            <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                                <a href="/accounting/ledger/add/" class="btn btn-light btn-sm">
                                    <i class="fas fa-plus me-1"></i>Add Account
                                </a>
                            <?php endif; ?>
                            <a href="/accounting/dashboard.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Setup Helper Card (show if no accounts exist) -->
        <?php if (empty($chart_of_accounts) && empty($filters['search'])): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Getting Started
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Set up your Chart of Accounts</h6>
                    <p>A Chart of Accounts is the foundation of your accounting system. It organizes all your financial accounts into categories.</p>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-success">Suggested Asset Accounts:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle text-success me-2"></i>1000 - Checking Account</li>
                                <li><i class="fas fa-circle text-success me-2"></i>1100 - Savings Account</li>
                                <li><i class="fas fa-circle text-success me-2"></i>1200 - Petty Cash</li>
                                <li><i class="fas fa-circle text-success me-2"></i>1500 - Equipment</li>
                            </ul>

                            <h6 class="text-info">Suggested Income Accounts:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle text-info me-2"></i>4000 - Membership Dues</li>
                                <li><i class="fas fa-circle text-info me-2"></i>4100 - Donations</li>
                                <li><i class="fas fa-circle text-info me-2"></i>4200 - Event Income</li>
                                <li><i class="fas fa-circle text-info me-2"></i>4300 - Interest Income</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-warning">Suggested Expense Accounts:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-circle text-warning me-2"></i>5000 - Meeting Expenses</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>5100 - Utilities</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>5200 - Equipment Maintenance</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>5300 - Insurance</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>5400 - Office Supplies</li>
                                <li><i class="fas fa-circle text-warning me-2"></i>5500 - Event Expenses</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-3">
                        <a href="/accounting/ledger/add/" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Create Your First Account
                        </a>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#setupModal">
                            <i class="fas fa-magic me-1"></i>Auto-Create Standard Accounts
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Chart of Accounts Display -->
        <?php
        renderChartOfAccounts($chart_of_accounts, $filters, $totals);
        ?>
    </div>

    <!-- Auto-Setup Modal -->
    <div class="modal fade" id="setupModal" tabindex="-1" aria-labelledby="setupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="setupModalLabel">Auto-Create Standard Accounts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>This will create a standard set of accounts suitable for most amateur radio clubs. You can modify or add accounts later.</p>

                    <div class="alert alert-info">
                        <h6>Accounts to be created:</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Assets:</strong>
                                <ul class="list-unstyled small">
                                    <li>1000 - Checking Account</li>
                                    <li>1100 - Savings Account</li>
                                    <li>1200 - Petty Cash</li>
                                    <li>1500 - Equipment</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <strong>Income:</strong>
                                <ul class="list-unstyled small">
                                    <li>4000 - Membership Dues</li>
                                    <li>4100 - Donations</li>
                                    <li>4200 - Event Income</li>
                                    <li>4300 - Interest Income</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <strong>Expenses:</strong>
                                <ul class="list-unstyled small">
                                    <li>5000 - Meeting Expenses</li>
                                    <li>5100 - Utilities</li>
                                    <li>5200 - Equipment Maintenance</li>
                                    <li>5300 - Insurance</li>
                                    <li>5400 - Office Supplies</li>
                                    <li>5500 - Event Expenses</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createStandardAccounts()">
                        <i class="fas fa-magic me-1"></i>Create Standard Accounts
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        // Auto-create standard accounts
        function createStandardAccounts() {
            // This would make an AJAX call to create standard accounts
            // For now, we'll redirect to a setup page
            window.location.href = '/accounting/ledger/setup_standard.php';
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>

</html>