<?php

/**
 * Transaction List - W5OBM Accounting System
 * File: /accounting/transactions/list.php
 * Purpose: Display and filter transaction list
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/transaction_controller.php';

// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access the transaction system.', 'fas fa-calculator');
    header('Location: ../../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to access the Accounting System.', 'fas fa-calculator');
    header('Location: ../../authentication/dashboard.php');
    exit();
}

// Log access
logActivity($user_id, 'transactions_list_accessed', 'acc_transactions', null, 'Accessed transaction list');

// Initialize filter variables with defaults
$filters = [
    'start_date' => sanitizeInput($_GET['start_date'] ?? date('Y-m-01'), 'string'),
    'end_date' => sanitizeInput($_GET['end_date'] ?? date('Y-m-t'), 'string'),
    'category_id' => !empty($_GET['category_id']) ? sanitizeInput($_GET['category_id'], 'int') : null,
    'type' => sanitizeInput($_GET['type'] ?? '', 'string'),
    'search' => sanitizeInput($_GET['search'] ?? '', 'string')
];

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Get transactions with filters
// Use legacy controller for listing to match current schema
require_once __DIR__ . '/../controllers/transaction_controller.php';
$transactions = fetch_all_transactions(
    $filters['start_date'] ?? null,
    $filters['end_date'] ?? null,
    $filters['category_id'] ?? null,
    $filters['type'] ?? null,
    $filters['account_id'] ?? null
);

// Apply pagination to the in-memory list
$transactions = array_slice($transactions, $offset, $per_page);

// Get total count for pagination
$total_transactions = 0;
try {
    $count_filters = $filters;
    unset($count_filters['limit'], $count_filters['offset']);

    $where_conditions = [];
    $params = [];
    $types = '';

    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $where_conditions[] = "transaction_date BETWEEN ? AND ?";
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];
        $types .= 'ss';
    }

    if (!empty($filters['category_id'])) {
        $where_conditions[] = "category_id = ?";
        $params[] = $filters['category_id'];
        $types .= 'i';
    }

    if (!empty($filters['type'])) {
        $where_conditions[] = "type = ?";
        $params[] = $filters['type'];
        $types .= 's';
    }

    // Search on description only (reference_number not in schema)
    if (!empty($filters['search'])) {
        $where_conditions[] = "(description LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $types .= 's';
    }

    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
    $count_query = "SELECT COUNT(*) as total FROM acc_transactions $where_clause";

    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_transactions = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
} catch (Exception $e) {
    logError("Error getting transaction count: " . $e->getMessage(), 'accounting');
}

// Calculate pagination
$total_pages = ceil($total_transactions / $per_page);

// Calculate totals for current filter
// Calculate totals from the current filtered set
$totals = ['income' => 0, 'expenses' => 0, 'net_balance' => 0, 'transaction_count' => 0];
foreach ($transactions as $t) {
    if (($t['type'] ?? '') === 'Income') {
        $totals['income'] += (float)$t['amount'];
    }
    if (($t['type'] ?? '') === 'Expense') {
        $totals['expenses'] += (float)$t['amount'];
    }
}
$totals['net_balance'] = $totals['income'] - $totals['expenses'];
$totals['transaction_count'] = count($transactions);

// Get categories for filter dropdown
$categories = [];
try {
    $cat_result = $conn->query("SELECT id, name FROM acc_transaction_categories ORDER BY name");
    if ($cat_result) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
} catch (Exception $e) {
    logError("Error fetching categories: " . $e->getMessage(), 'accounting');
}

// Handle legacy status messages
$status = $_GET['status'] ?? null;
if ($status) {
    switch ($status) {
        case 'success':
            setToastMessage('success', 'Transaction Added', 'Transaction added successfully!', 'fas fa-check-circle');
            break;
        case 'updated':
            setToastMessage('success', 'Transaction Updated', 'Transaction updated successfully!', 'fas fa-edit');
            break;
        case 'deleted':
            setToastMessage('success', 'Transaction Deleted', 'Transaction deleted successfully!', 'fas fa-trash');
            break;
        case 'error':
            setToastMessage('danger', 'Error', 'An error occurred. Please try again.', 'fas fa-exclamation-triangle');
            break;
    }
}

// Set page title
$page_title = "Transaction List - W5OBM Accounting";
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
    <?php if (function_exists('displayToastMessage')) {
        displayToastMessage();
    } ?>

    <div class="page-container">
        <!-- Header Card per Website Guidelines -->
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-list-alt fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Transaction List</h3>
                        <small>View and manage all financial transactions</small>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group shadow" role="group">
                            <a href="add.php" class="btn btn-light btn-sm">
                                <i class="fas fa-plus me-1"></i>Add New
                            </a>
                            <a href="../dashboard.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 bg-success text-white">
                    <div class="card-body">
                        <h6>Total Income</h6>
                        <p class="h4">$<?= number_format($totals['income'], 2) ?></p>
                        <small><i class="fas fa-arrow-up"></i> For selected period</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 bg-danger text-white">
                    <div class="card-body">
                        <h6>Total Expenses</h6>
                        <p class="h4">$<?= number_format($totals['expenses'], 2) ?></p>
                        <small><i class="fas fa-arrow-down"></i> For selected period</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 <?= $totals['net_balance'] >= 0 ? 'bg-primary' : 'bg-warning' ?> text-white">
                    <div class="card-body">
                        <h6>Net Balance</h6>
                        <p class="h4">$<?= number_format($totals['net_balance'], 2) ?></p>
                        <small><i class="fas fa-balance-scale"></i> Period total</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card shadow text-center h-100 bg-info text-white">
                    <div class="card-body">
                        <h6>Total Records</h6>
                        <p class="h4"><?= number_format($total_transactions) ?></p>
                        <small><i class="fas fa-file-invoice"></i> Transactions</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Transactions
                    <button class="btn btn-sm btn-outline-secondary float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </h5>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body">
                    <form method="GET" action="list.php" id="filterForm">
                        <div class="row">
                            <div class="col-lg-2 col-md-4 mb-3">
                                <label for="start_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>Start Date
                                </label>
                                <input type="date" id="start_date" name="start_date"
                                    class="form-control form-control-lg shadow-sm"
                                    value="<?= htmlspecialchars($filters['start_date']) ?>">
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <label for="end_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>End Date
                                </label>
                                <input type="date" id="end_date" name="end_date"
                                    class="form-control form-control-lg shadow-sm"
                                    value="<?= htmlspecialchars($filters['end_date']) ?>">
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <label for="type" class="form-label">
                                    <i class="fas fa-tag me-1"></i>Type
                                </label>
                                <select id="type" name="type" class="form-control form-control-lg shadow-sm">
                                    <option value="">All Types</option>
                                    <option value="Income" <?= $filters['type'] === 'Income' ? 'selected' : '' ?>>Income</option>
                                    <option value="Expense" <?= $filters['type'] === 'Expense' ? 'selected' : '' ?>>Expense</option>
                                    <option value="Asset" <?= $filters['type'] === 'Asset' ? 'selected' : '' ?>>Asset</option>
                                    <option value="Transfer" <?= $filters['type'] === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <label for="category_id" class="form-label">
                                    <i class="fas fa-folder me-1"></i>Category
                                </label>
                                <select id="category_id" name="category_id" class="form-control form-control-lg shadow-sm">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= $filters['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <label for="search" class="form-label">
                                    <i class="fas fa-search me-1"></i>Search
                                </label>
                                <input type="text" id="search" name="search"
                                    class="form-control form-control-lg shadow-sm"
                                    value="<?= htmlspecialchars($filters['search']) ?>"
                                    placeholder="Description, reference...">
                            </div>
                            <div class="col-lg-1 col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-lg shadow w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 text-end">
                                <a href="list.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-eraser me-1"></i>Clear Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Transactions
                            <?php if ($total_transactions > 0): ?>
                                <span class="badge bg-primary"><?= number_format($total_transactions) ?></span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="col-auto">
                        <a href="add.php" class="btn btn-success btn-sm shadow">
                            <i class="fas fa-plus me-1"></i>Add Transaction
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($transactions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($transaction['description']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($transaction['category_name'] ?? 'Uncategorized') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php
                                                                switch ($transaction['type']) {
                                                                    case 'Income':
                                                                        echo 'bg-success';
                                                                        break;
                                                                    case 'Expense':
                                                                        echo 'bg-danger';
                                                                        break;
                                                                    case 'Asset':
                                                                        echo 'bg-primary';
                                                                        break;
                                                                    case 'Transfer':
                                                                        echo 'bg-warning';
                                                                        break;
                                                                    default:
                                                                        echo 'bg-secondary';
                                                                }
                                                                ?>">
                                                <?= htmlspecialchars($transaction['type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong class="<?= $transaction['type'] === 'Income' ? 'text-success' : 'text-danger' ?>">
                                                $<?= number_format($transaction['amount'], 2) ?>
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group shadow-sm" role="group">
                                                <a href="edit.php?id=<?= $transaction['id'] ?>"
                                                    class="btn btn-outline-primary btn-sm"
                                                    title="Edit Transaction">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button"
                                                    class="btn btn-outline-danger btn-sm"
                                                    title="Delete Transaction"
                                                    onclick="confirmDelete(<?= $transaction['id'] ?>, '<?= htmlspecialchars($transaction['description'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Transaction pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);

                                    for ($p = $start_page; $p <= $end_page; $p++): ?>
                                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                                                <?= $p ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    Showing <?= ($offset + 1) ?> to <?= min($offset + $per_page, $total_transactions) ?>
                                    of <?= number_format($total_transactions) ?> transactions
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No transactions found</h5>
                        <p class="text-muted">Try adjusting your filter criteria or add your first transaction.</p>
                        <a href="add.php" class="btn btn-primary shadow">
                            <i class="fas fa-plus me-1"></i>Add First Transaction
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" action="delete.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
        // Delete confirmation
        function confirmDelete(transactionId, description) {
            if (confirm(`Are you sure you want to delete the transaction "${description}"?\n\nThis action cannot be undone.`)) {
                document.getElementById('deleteId').value = transactionId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Auto-submit form on filter change for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const quickFilters = ['start_date', 'end_date', 'type', 'category_id'];

            quickFilters.forEach(function(filterId) {
                const element = document.getElementById(filterId);
                if (element) {
                    element.addEventListener('change', function() {
                        // Small delay to allow user to make multiple selections
                        setTimeout(function() {
                            document.getElementById('filterForm').submit();
                        }, 300);
                    });
                }
            });
        });
    </script>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>