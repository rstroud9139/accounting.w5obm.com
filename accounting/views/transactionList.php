<?php

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

if (!function_exists('renderTransactionModalFields')) {
    /**
     * Shared fieldset for add/edit transaction modals.
     */
    function renderTransactionModalFields(array $categories, array $accounts, array $vendors, string $prefix = '', array $defaults = []): string
    {
        $defaults = array_merge([
            'transaction_date' => '',
            'type' => '',
            'category_id' => '',
            'amount' => '',
            'account_id' => '',
            'vendor_id' => '',
            'description' => '',
            'reference_number' => '',
            'notes' => '',
        ], $defaults);

        ob_start();
?>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="<?= $prefix ?>transaction_date" class="form-label">Transaction Date *</label>
                <input type="date" class="form-control" id="<?= $prefix ?>transaction_date" name="transaction_date"
                    value="<?= htmlspecialchars($defaults['transaction_date']) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="<?= $prefix ?>type" class="form-label">Type *</label>
                <select class="form-select" id="<?= $prefix ?>type" name="type" required>
                    <option value="">Select type</option>
                    <?php foreach (['Income', 'Expense', 'Asset', 'Transfer'] as $type): ?>
                        <option value="<?= $type ?>" <?= $defaults['type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="<?= $prefix ?>amount" class="form-label">Amount *</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="<?= $prefix ?>amount" name="amount"
                        min="0.01" max="999999.99" step="0.01"
                        value="<?= htmlspecialchars($defaults['amount']) ?>" required>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label for="<?= $prefix ?>category_id" class="form-label">Category *</label>
                <select class="form-select" id="<?= $prefix ?>category_id" name="category_id" required>
                    <option value="">Select category</option>
                    <?php
                    $currentType = '';
                    foreach ($categories as $category):
                        if (!empty($category['type']) && $category['type'] !== $currentType) {
                            if ($currentType !== '') {
                                echo '</optgroup>';
                            }
                            $currentType = $category['type'];
                            echo '<optgroup label="' . htmlspecialchars($currentType) . '">';
                        }
                    ?>
                        <option value="<?= (int)$category['id'] ?>"
                            <?= (int)$defaults['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach;
                    if ($currentType !== '') {
                        echo '</optgroup>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="<?= $prefix ?>account_id" class="form-label">Account</label>
                <select class="form-select" id="<?= $prefix ?>account_id" name="account_id">
                    <option value="">Select account</option>
                    <?php
                    $currentAccountType = '';
                    foreach ($accounts as $account):
                        if (!empty($account['account_type']) && $account['account_type'] !== $currentAccountType) {
                            if ($currentAccountType !== '') {
                                echo '</optgroup>';
                            }
                            $currentAccountType = $account['account_type'];
                            echo '<optgroup label="' . htmlspecialchars($currentAccountType) . ' Accounts">';
                        }
                    ?>
                        <option value="<?= (int)$account['id'] ?>"
                            <?= (int)$defaults['account_id'] === (int)$account['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($account['account_number'] ?? '') ?> <?= htmlspecialchars($account['name'] ?? '') ?>
                        </option>
                    <?php endforeach;
                    if ($currentAccountType !== '') {
                        echo '</optgroup>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label for="<?= $prefix ?>vendor_id" class="form-label">Vendor</label>
                <select class="form-select" id="<?= $prefix ?>vendor_id" name="vendor_id">
                    <option value="">Select vendor</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= (int)$vendor['id'] ?>"
                            <?= (int)$defaults['vendor_id'] === (int)$vendor['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($vendor['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="<?= $prefix ?>reference_number" class="form-label">Reference #</label>
                <input type="text" class="form-control" id="<?= $prefix ?>reference_number" name="reference_number"
                    maxlength="100" value="<?= htmlspecialchars($defaults['reference_number']) ?>">
            </div>
        </div>

        <div class="mt-3">
            <label for="<?= $prefix ?>description" class="form-label">Description *</label>
            <input type="text" class="form-control" id="<?= $prefix ?>description" name="description"
                maxlength="500" value="<?= htmlspecialchars($defaults['description']) ?>" required>
        </div>

        <div class="mt-3">
            <label for="<?= $prefix ?>notes" class="form-label">Notes</label>
            <textarea class="form-control" id="<?= $prefix ?>notes" name="notes" rows="3" maxlength="1000"><?= htmlspecialchars($defaults['notes']) ?></textarea>
        </div>
    <?php
        return ob_get_clean();
    }
}

if (!function_exists('renderTransactionList')) {
    /**
     * Render the transactions workspace (filters, stats, CRUD table, modals).
     */
    function renderTransactionList(
        array $transactions = [],
        array $filters = [],
        array $categories = [],
        array $accounts = [],
        array $vendors = [],
        array $totals = [],
        array $pagination = [],
        bool $canAdd = false,
        bool $canManage = false
    ): void {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];
        $today = date('Y-m-d');
        $addFields = renderTransactionModalFields($categories, $accounts, $vendors, 'add_', ['transaction_date' => $today]);
        $editFields = renderTransactionModalFields($categories, $accounts, $vendors, 'edit_');
        $filterCollapseId = 'transactionsFilterCollapse';
    ?>

        <!-- Filters Card -->
        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Transactions</h5>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-light btn-sm" id="clearFilters">
                        <i class="fas fa-times me-1"></i>Clear All
                    </button>
                    <button type="button" class="btn btn-light btn-sm text-primary" data-bs-toggle="collapse" data-bs-target="#<?= $filterCollapseId ?>" aria-expanded="true" aria-controls="<?= $filterCollapseId ?>">
                        <i class="fas fa-chevron-down me-1"></i>Toggle Filters
                    </button>
                </div>
            </div>
            <div class="card-body p-0 collapse show" id="<?= $filterCollapseId ?>">
                <div class="row g-0 flex-column flex-lg-row">
                    <div class="col-12 col-lg-3 border-bottom border-lg-bottom-0 border-lg-end bg-light-subtle p-3">
                        <h6 class="text-uppercase small text-muted fw-bold mb-2">Preset Filters</h6>
                        <p class="text-muted small mb-3">Common date ranges and type shortcuts.</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm transaction-chip text-start" data-range="today">Today</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm transaction-chip text-start" data-range="7">Last 7 Days</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm transaction-chip text-start" data-range="30">Last 30 Days</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm transaction-chip text-start" data-type="Income">Income Only</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm transaction-chip text-start" data-type="Expense">Expenses Only</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm transaction-chip text-start" data-clear="true">Reset Presets</button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="filterForm">
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <div class="col">
                                    <label for="start_date" class="form-label text-muted text-uppercase small mb-1">Start Date</label>
                                    <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                                </div>
                                <div class="col">
                                    <label for="end_date" class="form-label text-muted text-uppercase small mb-1">End Date</label>
                                    <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                                </div>
                                <div class="col">
                                    <label for="category_id" class="form-label text-muted text-uppercase small mb-1">Category</label>
                                    <select class="form-select form-select-sm" id="category_id" name="category_id">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int)$category['id'] ?>"
                                                <?= ($filters['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="type" class="form-label text-muted text-uppercase small mb-1">Type</label>
                                    <select class="form-select form-select-sm" id="type" name="type">
                                        <option value="">All Types</option>
                                        <?php foreach (['Income', 'Expense', 'Asset', 'Transfer'] as $type): ?>
                                            <option value="<?= $type ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="account_id" class="form-label text-muted text-uppercase small mb-1">Account</label>
                                    <select class="form-select form-select-sm" id="account_id" name="account_id">
                                        <option value="">All Accounts</option>
                                        <?php foreach ($accounts as $account): ?>
                                            <option value="<?= (int)$account['id'] ?>"
                                                <?= ($filters['account_id'] ?? '') == $account['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($account['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="vendor_id" class="form-label text-muted text-uppercase small mb-1">Vendor</label>
                                    <select class="form-select form-select-sm" id="vendor_id" name="vendor_id">
                                        <option value="">All Vendors</option>
                                        <?php foreach ($vendors as $vendor): ?>
                                            <option value="<?= (int)$vendor['id'] ?>"
                                                <?= ($filters['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($vendor['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 col-lg-8">
                                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                    <input type="text" class="form-control form-control-sm" id="search" name="search"
                                        placeholder="Description, reference, vendor..."
                                        value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-success btn-sm" id="exportBtn">
                                    <i class="fas fa-file-export me-1"></i>Export
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($totals)): ?>
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-md-3">
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <i class="fas fa-arrow-up text-success mb-2"></i>
                                <h4 class="mb-0 text-success">$<?= number_format($totals['income'] ?? 0, 2) ?></h4>
                                <small class="text-muted">Total Income</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-danger bg-opacity-10 rounded p-3">
                                <i class="fas fa-arrow-down text-danger mb-2"></i>
                                <h4 class="mb-0 text-danger">$<?= number_format($totals['expenses'] ?? 0, 2) ?></h4>
                                <small class="text-muted">Total Expenses</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-<?= ($totals['net_balance'] ?? 0) >= 0 ? 'success' : 'danger' ?> bg-opacity-10 rounded p-3">
                                <i class="fas fa-scale-balanced text-<?= ($totals['net_balance'] ?? 0) >= 0 ? 'success' : 'danger' ?> mb-2"></i>
                                <h4 class="mb-0 text-<?= ($totals['net_balance'] ?? 0) >= 0 ? 'success' : 'danger' ?>">$<?= number_format($totals['net_balance'] ?? 0, 2) ?></h4>
                                <small class="text-muted">Net Position</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-info bg-opacity-10 rounded p-3">
                                <i class="fas fa-list text-info mb-2"></i>
                                <h4 class="mb-0 text-info"><?= number_format($totals['transaction_count'] ?? 0) ?></h4>
                                <small class="text-muted">Total Transactions</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="mb-0"><i class="fas fa-database me-2"></i>Transactions
                            <?php if (!empty($pagination['total'])): ?>
                                <small class="opacity-75">(<?= number_format($pagination['total']) ?>)</small>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="col-auto">
                        <?php if ($canAdd): ?>
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                <i class="fas fa-plus me-1"></i>Add Transaction
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No transactions found for the selected filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Account</th>
                                    <th class="text-end">Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction):
                                    $payload = htmlspecialchars(json_encode([
                                        'id' => (int)$transaction['id'],
                                        'transaction_date' => $transaction['transaction_date'] ?? '',
                                        'type' => $transaction['type'] ?? '',
                                        'category_id' => $transaction['category_id'] ?? '',
                                        'amount' => $transaction['amount'] ?? '',
                                        'account_id' => $transaction['account_id'] ?? '',
                                        'vendor_id' => $transaction['vendor_id'] ?? '',
                                        'description' => $transaction['description'] ?? '',
                                        'reference_number' => $transaction['reference_number'] ?? '',
                                        'notes' => $transaction['notes'] ?? '',
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>
                                    <tr>
                                        <td><small class="text-muted"><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></small></td>
                                        <td><span class="badge bg-<?= $transaction['type'] === 'Income' ? 'success' : ($transaction['type'] === 'Expense' ? 'danger' : 'secondary') ?>"><?= htmlspecialchars($transaction['type']) ?></span></td>
                                        <td><?= htmlspecialchars($transaction['category_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($transaction['description']) ?></div>
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
                                                <?php if ($canManage): ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editTransactionModal" data-transaction='<?= $payload ?>' title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reverseTransactionModal" data-transaction='<?= $payload ?>' title="Reverse">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-lg-none">
                        <?php foreach ($transactions as $transaction):
                            $payload = htmlspecialchars(json_encode([
                                'id' => (int)$transaction['id'],
                                'transaction_date' => $transaction['transaction_date'] ?? '',
                                'type' => $transaction['type'] ?? '',
                                'category_id' => $transaction['category_id'] ?? '',
                                'amount' => $transaction['amount'] ?? '',
                                'account_id' => $transaction['account_id'] ?? '',
                                'vendor_id' => $transaction['vendor_id'] ?? '',
                                'description' => $transaction['description'] ?? '',
                                'reference_number' => $transaction['reference_number'] ?? '',
                                'notes' => $transaction['notes'] ?? '',
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>
                            <div class="card border-0 border-bottom rounded-0">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($transaction['description']) ?></h6>
                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                <span class="badge bg-<?= $transaction['type'] === 'Income' ? 'success' : 'danger' ?>"><?= htmlspecialchars($transaction['type']) ?></span>
                                                <small class="text-muted"><?= htmlspecialchars($transaction['category_name'] ?? 'N/A') ?></small>
                                            </div>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-<?= $transaction['type'] === 'Income' ? 'success' : 'danger' ?> mb-2">
                                                <?= $transaction['type'] === 'Income' ? '+' : '-' ?>$<?= number_format($transaction['amount'], 2) ?>
                                            </div>
                                            <?php if ($canManage): ?>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editTransactionModal" data-transaction='<?= $payload ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reverseTransactionModal" data-transaction='<?= $payload ?>'>
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($pagination) && ($pagination['total_pages'] ?? 1) > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Transaction pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($pagination['current_page'] > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $pagination['current_page'] - 1 ?>&<?= http_build_query($filters) ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $pagination['current_page'] + 1 ?>&<?= http_build_query($filters) ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canAdd): ?>
            <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <form class="modal-content border-0 shadow-lg needs-validation" method="POST" action="/accounting/transactions/add_transaction.php" novalidate>
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title" id="addTransactionModalLabel"><i class="fas fa-plus-circle me-2"></i>Add Transaction</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <?= $addFields ?>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <form class="modal-content border-0 shadow-lg needs-validation" method="POST" action="/accounting/transactions/edit_transaction.php" novalidate>
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title" id="editTransactionModalLabel"><i class="fas fa-edit me-2"></i>Edit Transaction</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" id="editTransactionId">
                            <?= $editFields ?>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="reverseTransactionModal" tabindex="-1" aria-labelledby="reverseTransactionModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="/accounting/transactions/reverse_transaction.php">
                        <div class="modal-header bg-dark text-white border-0">
                            <h5 class="modal-title" id="reverseTransactionModalLabel">Reverse Transaction</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="transaction_id" id="reverseTransactionId">
                            <p class="mb-2">A reversing entry preserves the original record while balancing the ledger.</p>
                            <div class="alert alert-warning">Reversing <strong id="reverseTransactionSummary">this transaction</strong>.</div>
                            <label for="reversal_reason" class="form-label">Reason *</label>
                            <textarea class="form-control" id="reversal_reason" name="reason" rows="3" required></textarea>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-undo me-2"></i>Post Reversal</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const filterForm = document.getElementById('filterForm');
                const clearBtn = document.getElementById('clearFilters');
                const exportBtn = document.getElementById('exportBtn');
                const startInput = document.getElementById('start_date');
                const endInput = document.getElementById('end_date');
                const typeSelect = document.getElementById('type');

                clearBtn?.addEventListener('click', function() {
                    filterForm.reset();
                    window.location.href = window.location.pathname;
                });

                exportBtn?.addEventListener('click', function() {
                    const params = new URLSearchParams(window.location.search);
                    params.set('export', 'csv');
                    window.location.href = '?' + params.toString();
                });

                document.querySelectorAll('.transaction-chip').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const {
                            range,
                            type,
                            clear
                        } = btn.dataset;
                        if (clear) {
                            if (startInput) startInput.value = '';
                            if (endInput) endInput.value = '';
                            if (typeSelect) typeSelect.value = '';
                            triggerChange(startInput);
                            triggerChange(endInput);
                            triggerChange(typeSelect);
                            return;
                        }

                        if (range) {
                            applyDatePreset(range);
                        }

                        if (type && typeSelect) {
                            typeSelect.value = type;
                            triggerChange(typeSelect);
                        }
                    });
                });

                function applyDatePreset(range) {
                    if (!startInput || !endInput) return;
                    const today = new Date();
                    const endDate = formatDate(today);
                    let startDate = endDate;

                    if (range !== 'today') {
                        const days = parseInt(range, 10);
                        if (!Number.isNaN(days)) {
                            const start = new Date();
                            start.setDate(today.getDate() - (days - 1));
                            startDate = formatDate(start);
                        }
                    }

                    startInput.value = startDate;
                    endInput.value = endDate;
                    triggerChange(startInput);
                    triggerChange(endInput);
                }

                function formatDate(date) {
                    const tzOffset = date.getTimezoneOffset() * 60000;
                    return new Date(date.getTime() - tzOffset).toISOString().split('T')[0];
                }

                function triggerChange(element) {
                    if (!element) return;
                    element.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }

                let debounce;
                filterForm.querySelectorAll('input, select').forEach(el => {
                    el.addEventListener('change', function() {
                        clearTimeout(debounce);
                        debounce = setTimeout(() => filterForm.submit(), 600);
                    });
                });

                <?php if ($canAdd): ?>
                    const addModal = document.getElementById('addTransactionModal');
                    addModal?.addEventListener('show.bs.modal', () => {
                        addModal.querySelectorAll('input:not([type="hidden"]), textarea, select').forEach(field => {
                            if (field.name === 'transaction_date') {
                                field.value = '<?= $today ?>';
                            } else {
                                field.value = '';
                            }
                        });
                    });
                <?php endif; ?>

                <?php if ($canManage): ?>
                    const editModal = document.getElementById('editTransactionModal');
                    editModal?.addEventListener('show.bs.modal', event => {
                        const trigger = event.relatedTarget;
                        if (!trigger) return;
                        const payload = trigger.getAttribute('data-transaction');
                        if (!payload) return;
                        const data = JSON.parse(payload);
                        editModal.querySelector('#editTransactionId').value = data.id;
                        fillModalFields(editModal, data);
                    });

                    const reverseModal = document.getElementById('reverseTransactionModal');
                    reverseModal?.addEventListener('show.bs.modal', event => {
                        const trigger = event.relatedTarget;
                        const data = JSON.parse(trigger.getAttribute('data-transaction'));
                        reverseModal.querySelector('#reverseTransactionId').value = data.id;
                        reverseModal.querySelector('#reverseTransactionSummary').textContent = `${data.description || 'Transaction'} (${data.transaction_date || ''})`;
                        reverseModal.querySelector('#reversal_reason').value = '';
                    });
                <?php endif; ?>

                function fillModalFields(modal, data) {
                    const mapping = ['transaction_date', 'type', 'category_id', 'amount', 'account_id', 'vendor_id', 'description', 'reference_number', 'notes'];
                    mapping.forEach(field => {
                        const input = modal.querySelector(`[name="${field}"]`);
                        if (!input) return;
                        if (input.tagName === 'SELECT') {
                            input.value = data[field] ?? '';
                        } else if (input.type === 'number') {
                            input.value = data[field] ? parseFloat(data[field]).toFixed(2) : '';
                        } else {
                            input.value = data[field] ?? '';
                        }
                    });
                }

                document.querySelectorAll('.needs-validation').forEach(form => {
                    form.addEventListener('submit', event => {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    });
                });
            });
        </script>

<?php
    }
}
