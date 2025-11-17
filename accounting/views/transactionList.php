<?php

/**
 * Transaction List View - W5OBM Accounting System
 * File: /accounting/views/transactionList.php
 * Purpose: Reusable transaction list component with filtering and pagination
 * SECURITY: All data is properly escaped for display
 * UPDATED: Follows Website Guidelines with responsive design
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Get color class for transaction type
 */
function getTypeColor($type)
{
    switch ($type) {
        case 'Income':
            return 'success';
        case 'Expense':
            return 'danger';
        case 'Asset':
            return 'info';
        case 'Transfer':
            return 'warning';
        default:
            return 'secondary';
    }
}

/**
 * Render transaction list with filters and pagination
 * @param array $transactions Array of transactions
 * @param array $filters Current filter values
 * @param array $categories Available categories for filtering
 * @param array $accounts Available accounts for filtering
 * @param array $totals Transaction totals
 * @param array $pagination Pagination info
 */
function renderTransactionList($transactions = [], $filters = [], $categories = [], $accounts = [], $totals = [], $pagination = [])
{
    // CSRF token for actions
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

?>

    <!-- Filters Card -->
    <div class="card shadow mb-4">
        <div class="card-header bg-light">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2 text-primary"></i>Filter Transactions
                    </h5>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFilters">
                        <i class="fas fa-times me-1"></i>Clear All
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" id="filterForm" class="row g-3">
                <!-- Date Range -->
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                        value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                        value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                </div>

                <!-- Category Filter -->
                <div class="col-md-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"
                                <?= ($filters['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Type Filter -->
                <div class="col-md-3">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All Types</option>
                        <option value="Income" <?= ($filters['type'] ?? '') === 'Income' ? 'selected' : '' ?>>Income</option>
                        <option value="Expense" <?= ($filters['type'] ?? '') === 'Expense' ? 'selected' : '' ?>>Expense</option>
                        <option value="Asset" <?= ($filters['type'] ?? '') === 'Asset' ? 'selected' : '' ?>>Asset</option>
                        <option value="Transfer" <?= ($filters['type'] ?? '') === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                    </select>
                </div>

                <!-- Search -->
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                        value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                        placeholder="Search description, reference number, or category...">
                </div>

                <!-- Account Filter -->
                <div class="col-md-3">
                    <label for="account_id" class="form-label">Account</label>
                    <select class="form-select" id="account_id" name="account_id">
                        <option value="">All Accounts</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= $account['id'] ?>"
                                <?= ($filters['account_id'] ?? '') == $account['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($account['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Actions -->
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <button type="button" class="btn btn-outline-success" id="exportBtn">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Card -->
    <?php if (!empty($totals)): ?>
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-arrow-up fa-2x text-success mb-2"></i>
                            <h4 class="text-success mb-0">$<?= number_format($totals['income'], 2) ?></h4>
                            <small class="text-muted">Total Income</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="fas fa-arrow-down fa-2x text-danger mb-2"></i>
                            <h4 class="text-danger mb-0">$<?= number_format($totals['expenses'], 2) ?></h4>
                            <small class="text-muted">Total Expenses</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-<?= $totals['net_balance'] >= 0 ? 'success' : 'danger' ?> bg-opacity-10 p-3 rounded">
                            <i class="fas fa-balance-scale fa-2x text-<?= $totals['net_balance'] >= 0 ? 'success' : 'danger' ?> mb-2"></i>
                            <h4 class="text-<?= $totals['net_balance'] >= 0 ? 'success' : 'danger' ?> mb-0">
                                $<?= number_format($totals['net_balance'], 2) ?>
                            </h4>
                            <small class="text-muted">Net Balance</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-list fa-2x text-info mb-2"></i>
                            <h4 class="text-info mb-0"><?= number_format($totals['transaction_count']) ?></h4>
                            <small class="text-muted">Total Transactions</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Transactions List Card -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h4 class="mb-0">
                        <i class="fas fa-list me-2"></i>Transactions
                        <?php if (!empty($pagination['total'])): ?>
                            <small class="opacity-75">(<?= number_format($pagination['total']) ?> total)</small>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="col-auto">
                    <a href="/accounting/transactions/add/" class="btn btn-light btn-sm">
                        <i class="fas fa-plus me-1"></i>Add Transaction
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No transactions found</h5>
                    <p class="text-muted">Try adjusting your filters or add a new transaction.</p>
                    <a href="/accounting/transactions/add/" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add First Transaction
                    </a>
                </div>
            <?php else: ?>
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-lg-block">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Account</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getTypeColor($transaction['type']) ?>">
                                            <?= htmlspecialchars($transaction['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['category_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($transaction['description']) ?></div>
                                        <?php if (!empty($transaction['reference_number'])): ?>
                                            <small class="text-muted">Ref: <?= htmlspecialchars($transaction['reference_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['account_name'] ?? 'N/A') ?></td>
                                    <td class="text-end">
                                        <span class="fw-bold text-<?= $transaction['type'] === 'Income' ? 'success' : 'danger' ?>">
                                            <?= $transaction['type'] === 'Income' ? '+' : '-' ?>$<?= number_format($transaction['amount'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/accounting/transactions/edit/<?= $transaction['id'] ?>"
                                                class="btn btn-outline-primary btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                onclick="deleteTransaction(<?= $transaction['id'] ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-lg-none">
                    <?php foreach ($transactions as $transaction): ?>
                        <div class="card border-0 border-bottom rounded-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <h6 class="mb-1"><?= htmlspecialchars($transaction['description']) ?></h6>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="badge bg-<?= getTypeColor($transaction['type']) ?>">
                                                <?= htmlspecialchars($transaction['type']) ?>
                                            </span>
                                            <small class="text-muted"><?= htmlspecialchars($transaction['category_name'] ?? 'N/A') ?></small>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($transaction['transaction_date'])) ?>
                                            <?php if (!empty($transaction['reference_number'])): ?>
                                                • Ref: <?= htmlspecialchars($transaction['reference_number']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="fw-bold text-<?= $transaction['type'] === 'Income' ? 'success' : 'danger' ?> mb-2">
                                            <?= $transaction['type'] === 'Income' ? '+' : '-' ?>$<?= number_format($transaction['amount'], 2) ?>
                                        </div>
                                        <div class="btn-group" role="group">
                                            <a href="/accounting/transactions/edit/<?= $transaction['id'] ?>"
                                                class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                onclick="deleteTransaction(<?= $transaction['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <nav aria-label="Transaction pagination">
                    <ul class="pagination mb-0 justify-content-center">
                        <?php if ($pagination['current_page'] > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $pagination['current_page'] - 1 ?>&<?= http_build_query($filters) ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                            <li class="page-item <?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $pagination['current_page'] + 1 ?>&<?= http_build_query($filters) ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // JavaScript for transaction list functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Clear filters functionality
            document.getElementById('clearFilters').addEventListener('click', function() {
                const form = document.getElementById('filterForm');
                form.reset();
                window.location.href = window.location.pathname;
            });

            // Export functionality
            document.getElementById('exportBtn').addEventListener('click', function() {
                const params = new URLSearchParams(window.location.search);
                params.set('export', 'csv');
                window.location.href = '?' + params.toString();
            });

            // Auto-submit form on filter changes (with debouncing)
            let timeout;
            const inputs = document.querySelectorAll('#filterForm input, #filterForm select');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        document.getElementById('filterForm').submit();
                    }, 500);
                });
            });
        });

        // Delete transaction function
        function deleteTransaction(id) {
            if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/accounting/transactions/delete/' + id;

                const csrfField = document.createElement('input');
                csrfField.type = 'hidden';
                csrfField.name = 'csrf_token';
                csrfField.value = '<?= $_SESSION['csrf_token'] ?>';

                form.appendChild(csrfField);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

<?php
}
?>