<?php

/**
 * Ledger List View - W5OBM Accounting System
 * File: /accounting/views/ledger_list.php
 * Purpose: Display chart of accounts in hierarchical format
 * SECURITY: Only included by ledger/index.php after authentication
 * UPDATED: Follows Website Guidelines with responsive design
 */

// This file should only be included, never called directly
if (!defined('W5OBM_ACCOUNTING') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}

/**
 * Render the chart of accounts display
 * @param array $chart_of_accounts Hierarchical array of accounts
 * @param array $filters Applied filters
 * @param array $totals Account type totals
 */
function renderChartOfAccounts($chart_of_accounts, $filters = [], $totals = [])
{
    $user_id = getCurrentUserId();
    $can_edit = hasPermission($user_id, 'accounting_edit') || hasPermission($user_id, 'accounting_manage');
    $can_add = hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage');
    $can_delete = hasPermission($user_id, 'accounting_delete') || hasPermission($user_id, 'accounting_manage');
?>

    <!-- Account Type Summary Cards -->
    <?php if (!empty($totals)): ?>
        <div class="row mb-4">
            <?php foreach (['Asset', 'Liability', 'Equity', 'Income', 'Expense'] as $type): ?>
                <?php if (isset($totals[$type])): ?>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card shadow-sm h-100 account-type-card" data-type="<?= strtolower($type) ?>">
                            <div class="card-body text-center">
                                <div class="account-type-icon mb-2">
                                    <i class="fas fa-<?php
                                                        switch ($type) {
                                                            case 'Asset':
                                                                echo 'coins text-success';
                                                                break;
                                                            case 'Liability':
                                                                echo 'credit-card text-danger';
                                                                break;
                                                            case 'Equity':
                                                                echo 'balance-scale text-info';
                                                                break;
                                                            case 'Income':
                                                                echo 'arrow-up text-success';
                                                                break;
                                                            case 'Expense':
                                                                echo 'arrow-down text-warning';
                                                                break;
                                                        }
                                                        ?> fa-2x"></i>
                                </div>
                                <h6 class="card-title"><?= $type ?></h6>
                                <div class="fw-bold"><?= $totals[$type]['count'] ?> accounts</div>
                                <small class="text-muted">$<?= number_format($totals[$type]['balance'], 2) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-header bg-light">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>Filters & Search
                    </h5>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleFilters">
                        <i class="fas fa-chevron-down me-1"></i>Show Filters
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-lg-4" id="filterPanel" style="display: none;">
            <form method="GET" action="" class="row gy-3 gx-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="account_type" class="form-label text-muted text-uppercase small mb-1">Account Type</label>
                    <select name="account_type" id="account_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="Asset" <?= ($filters['account_type'] ?? '') === 'Asset' ? 'selected' : '' ?>>Assets</option>
                        <option value="Liability" <?= ($filters['account_type'] ?? '') === 'Liability' ? 'selected' : '' ?>>Liabilities</option>
                        <option value="Equity" <?= ($filters['account_type'] ?? '') === 'Equity' ? 'selected' : '' ?>>Equity</option>
                        <option value="Income" <?= ($filters['account_type'] ?? '') === 'Income' ? 'selected' : '' ?>>Income</option>
                        <option value="Expense" <?= ($filters['account_type'] ?? '') === 'Expense' ? 'selected' : '' ?>>Expenses</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="status" class="form-label text-muted text-uppercase small mb-1">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="active" <?= (($filters['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active Only</option>
                        <option value="all" <?= (($filters['status'] ?? 'active') === 'all') ? 'selected' : '' ?>>All Accounts</option>
                        <option value="inactive" <?= (($filters['status'] ?? 'active') === 'inactive') ? 'selected' : '' ?>>Inactive Only</option>
                    </select>
                </div>
                <div class="col-12 col-lg-6">
                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm"
                        placeholder="Account name, number, or description..."
                        value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2 ms-auto">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>Apply
                    </button>
                </div>
            </form>

            <div class="row mt-3">
                <div class="col">
                    <a href="?" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Clear Filters
                    </a>
                    <?php if ($can_add): ?>
                        <a href="/accounting/ledger/setup_standard.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-magic me-1"></i>Setup Standard Accounts
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart of Accounts Display -->
    <div class="card shadow">
        <div class="card-header bg-light">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">
                        <i class="fas fa-sitemap me-2"></i>Chart of Accounts
                    </h5>
                </div>
                <div class="col-auto">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="expandAll">
                            <i class="fas fa-plus-circle me-1"></i>Expand All
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAll">
                            <i class="fas fa-minus-circle me-1"></i>Collapse All
                        </button>
                        <?php if ($can_add): ?>
                            <a href="/accounting/ledger/add.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Add Account
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($chart_of_accounts)): ?>
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Accounts Found</h5>
                <?php if (!empty($filters['search'])): ?>
                    <p class="text-muted">No accounts match your search criteria.</p>
                    <a href="?" class="btn btn-outline-primary">
                        <i class="fas fa-times me-1"></i>Clear Search
                    </a>
                <?php else: ?>
                    <p class="text-muted">Get started by creating your first account or setting up standard accounts.</p>
                    <?php if ($can_add): ?>
                        <div class="mt-3">
                            <a href="/accounting/ledger/add.php" class="btn btn-primary me-2">
                                <i class="fas fa-plus me-1"></i>Add First Account
                            </a>
                            <a href="/accounting/ledger/setup_standard.php" class="btn btn-outline-success">
                                <i class="fas fa-magic me-1"></i>Setup Standard Accounts
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="accountsTable">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th>Account</th>
                                <th class="text-center">Type</th>
                                <th class="text-end">Balance</th>
                                <th class="text-center">Transactions</th>
                                <th class="text-center">Status</th>
                                <?php if ($can_edit || $can_delete): ?>
                                    <th class="text-center">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php renderAccountRows($chart_of_accounts, 0, $can_edit, $can_delete); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
        /* Chart of Accounts Styling */
        .account-type-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .account-type-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        }

        .account-row {
            border-left: 4px solid transparent;
        }

        .account-row.level-0 {
            border-left-color: #007bff;
        }

        .account-row.level-1 {
            border-left-color: #6c757d;
        }

        .account-row.level-2 {
            border-left-color: #28a745;
        }

        .account-indent {
            padding-left: 0;
        }

        .account-indent.level-1 {
            padding-left: 2rem;
        }

        .account-indent.level-2 {
            padding-left: 4rem;
        }

        .account-indent.level-3 {
            padding-left: 6rem;
        }

        .account-number {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }

        .balance-positive {
            color: #28a745;
        }

        .balance-negative {
            color: #dc3545;
        }

        .balance-zero {
            color: #6c757d;
        }

        .toggle-children {
            background: none;
            border: none;
            color: #007bff;
            font-size: 0.875rem;
            padding: 0;
            margin-right: 0.5rem;
            cursor: pointer;
        }

        .toggle-children:hover {
            color: #0056b3;
        }

        @media (max-width: 768px) {
            .account-indent.level-1 {
                padding-left: 1rem;
            }

            .account-indent.level-2 {
                padding-left: 2rem;
            }

            .account-indent.level-3 {
                padding-left: 3rem;
            }

            .table-responsive th,
            .table-responsive td {
                white-space: nowrap;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle filters
            document.getElementById('toggleFilters').addEventListener('click', function() {
                const panel = document.getElementById('filterPanel');
                const icon = this.querySelector('i');

                if (panel.style.display === 'none') {
                    panel.style.display = 'block';
                    icon.className = 'fas fa-chevron-up me-1';
                    this.innerHTML = '<i class="fas fa-chevron-up me-1"></i>Hide Filters';
                } else {
                    panel.style.display = 'none';
                    icon.className = 'fas fa-chevron-down me-1';
                    this.innerHTML = '<i class="fas fa-chevron-down me-1"></i>Show Filters';
                }
            });

            // Account type cards click to filter
            document.querySelectorAll('.account-type-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.dataset.type;
                    const typeSelect = document.getElementById('account_type');
                    typeSelect.value = type.charAt(0).toUpperCase() + type.slice(1);
                    document.querySelector('form').submit();
                });
            });

            // Expand/Collapse functionality
            document.getElementById('expandAll')?.addEventListener('click', function() {
                document.querySelectorAll('.collapsible-children').forEach(el => {
                    el.style.display = 'table-row';
                });
                document.querySelectorAll('.toggle-children').forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-minus-square"></i>';
                    btn.dataset.expanded = 'true';
                });
            });

            document.getElementById('collapseAll')?.addEventListener('click', function() {
                document.querySelectorAll('.collapsible-children').forEach(el => {
                    el.style.display = 'none';
                });
                document.querySelectorAll('.toggle-children').forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-plus-square"></i>';
                    btn.dataset.expanded = 'false';
                });
            });

            // Individual toggle functionality
            document.querySelectorAll('.toggle-children').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const accountId = this.dataset.accountId;
                    const children = document.querySelectorAll(`[data-parent-id="${accountId}"]`);
                    const expanded = this.dataset.expanded === 'true';

                    children.forEach(child => {
                        child.style.display = expanded ? 'none' : 'table-row';
                    });

                    this.innerHTML = expanded ? '<i class="fas fa-plus-square"></i>' : '<i class="fas fa-minus-square"></i>';
                    this.dataset.expanded = expanded ? 'false' : 'true';
                });
            });

            // Initialize collapsed state
            document.querySelectorAll('.collapsible-children').forEach(el => {
                el.style.display = 'none';
            });
        });
    </script>

<?php
}

/**
 * Recursively render account rows with hierarchy
 * @param array $accounts Array of accounts
 * @param int $level Indentation level
 * @param bool $can_edit User can edit
 * @param bool $can_delete User can delete
 */
function renderAccountRows($accounts, $level = 0, $can_edit = false, $can_delete = false)
{
    foreach ($accounts as $account) {
        $balance = getAccountBalance($account['id']);
        $balance_class = $balance > 0 ? 'balance-positive' : ($balance < 0 ? 'balance-negative' : 'balance-zero');
        $has_children = !empty($account['children']);

        echo '<tr class="account-row level-' . $level . '"';
        if ($level > 0) {
            echo ' data-parent-id="' . ($account['parent_account_id'] ?? '') . '" class="collapsible-children"';
        }
        echo '>';

        // Account Name & Number
        echo '<td class="account-indent level-' . $level . '">';
        if ($has_children) {
            echo '<button type="button" class="toggle-children" data-account-id="' . $account['id'] . '" data-expanded="false">';
            echo '<i class="fas fa-plus-square"></i></button>';
        }
        echo '<div class="d-flex flex-column">';
        echo '<div class="fw-bold">' . htmlspecialchars($account['name']) . '</div>';
        echo '<div class="account-number text-muted small">' . htmlspecialchars($account['account_number']) . '</div>';
        if (!empty($account['description'])) {
            echo '<div class="text-muted small">' . htmlspecialchars($account['description']) . '</div>';
        }
        echo '</div></td>';

        // Account Type
        echo '<td class="text-center">';
        echo '<span class="badge bg-' . getAccountTypeBadgeColor($account['account_type']) . '">';
        echo htmlspecialchars($account['account_type']);
        echo '</span></td>';

        // Balance
        echo '<td class="text-end fw-bold ' . $balance_class . '">';
        echo '$' . number_format($balance, 2);
        echo '</td>';

        // Transaction Count
        echo '<td class="text-center">';
        if ($account['transaction_count'] > 0) {
            echo '<a href="/accounting/transactions/transactions.php?account_id=' . $account['id'] . '" class="btn btn-outline-primary btn-sm">';
            echo $account['transaction_count'];
            echo '</a>';
        } else {
            echo '<span class="text-muted">0</span>';
        }
        echo '</td>';

        // Status
        echo '<td class="text-center">';
        echo '<span class="badge bg-' . ($account['active'] ? 'success' : 'secondary') . '">';
        echo $account['active'] ? 'Active' : 'Inactive';
        echo '</span></td>';

        // Actions
        if ($can_edit || $can_delete) {
            echo '<td class="text-center">';
            echo '<div class="btn-group" role="group">';

            if ($can_edit) {
                echo '<a href="/accounting/ledger/edit.php?id=' . $account['id'] . '" class="btn btn-outline-primary btn-sm" title="Edit Account">';
                echo '<i class="fas fa-edit"></i></a>';
            }

            echo '<a href="/accounting/ledger/account_detail.php?id=' . $account['id'] . '" class="btn btn-outline-info btn-sm" title="View Details">';
            echo '<i class="fas fa-eye"></i></a>';

            if ($can_delete && $account['transaction_count'] == 0 && ($account['child_count'] ?? 0) == 0) {
                echo '<button type="button" class="btn btn-outline-danger btn-sm" title="Delete Account" ';
                echo 'onclick="confirmDelete(' . $account['id'] . ', \'' . htmlspecialchars($account['name'], ENT_QUOTES) . '\')">';
                echo '<i class="fas fa-trash"></i></button>';
            }

            echo '</div></td>';
        }

        echo '</tr>';

        // Render child accounts
        if ($has_children) {
            renderAccountRows($account['children'], $level + 1, $can_edit, $can_delete);
        }
    }
}

/**
 * Get badge color for account type
 * @param string $account_type Account type
 * @return string Bootstrap badge color
 */
function getAccountTypeBadgeColor($account_type)
{
    switch ($account_type) {
        case 'Asset':
            return 'success';
        case 'Liability':
            return 'danger';
        case 'Equity':
            return 'info';
        case 'Income':
            return 'primary';
        case 'Expense':
            return 'warning';
        default:
            return 'secondary';
    }
}
?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the account <strong id="deleteAccountName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-warning me-2"></i>This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-1"></i>Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Delete confirmation functionality
    function confirmDelete(accountId, accountName) {
        document.getElementById('deleteAccountName').textContent = accountName;
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));

        document.getElementById('confirmDeleteBtn').onclick = function() {
            window.location.href = '/accounting/ledger/delete.php?id=' + accountId;
        };

        modal.show();
    }
</script>