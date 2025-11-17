<?php

/**
 * Category Management - W5OBM Accounting System
 * File: /accounting/categories/index.php
 * Purpose: Main transaction category management page
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
// Use legacy controller to match current DB schema
require_once __DIR__ . '/../controllers/category_controller.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view transaction categories.', 'club-logo');
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

$page_title = "Transaction Categories - W5OBM Accounting";

// Get filters from GET parameters
// Filters
$filter_type = sanitizeInput($_GET['type'] ?? '', 'string');
$filter_search = sanitizeInput($_GET['search'] ?? '', 'string');

// Load categories with simple schema-compatible query
$categories = [];
try {
    $where = [];
    $params = [];
    $types = '';
    if ($filter_type !== '') {
        $where[] = 'type = ?';
        $params[] = $filter_type;
        $types .= 's';
    }
    if ($filter_search !== '') {
        $where[] = '(name LIKE ? OR description LIKE ?)';
        $like = "%$filter_search%";
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }
    $where_clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT id, name, type, description, created_at FROM acc_transaction_categories $where_clause ORDER BY type, name";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // Derive transaction_count per category
        $cid = (int)$row['id'];
        $cnt_stmt = $conn->prepare('SELECT COUNT(*) AS c FROM acc_transactions WHERE category_id = ?');
        $cnt_stmt->bind_param('i', $cid);
        $cnt_stmt->execute();
        $cnt = $cnt_stmt->get_result()->fetch_assoc();
        $cnt_stmt->close();
        $row['transaction_count'] = (int)($cnt['c'] ?? 0);
        // Provide placeholders for UI keys used elsewhere
        $row['parent_category_name'] = '';
        $row['created_by_username'] = 'Unknown';
        $categories[] = $row;
    }
    $stmt->close();

    // Totals by type
    $totals = [];
    $category_types = ['Income', 'Expense', 'Asset', 'Liability', 'Equity'];
    foreach ($category_types as $t) {
        $cnt1 = 0;
        $cnt2 = 0;
        // Count categories by type
        $stmt1 = $conn->prepare('SELECT COUNT(*) AS c FROM acc_transaction_categories WHERE type = ?');
        $stmt1->bind_param('s', $t);
        $stmt1->execute();
        $r1 = $stmt1->get_result()->fetch_assoc();
        $stmt1->close();
        $cnt1 = (int)($r1['c'] ?? 0);
        // Sum transactions in categories of that type
        $stmt2 = $conn->prepare('SELECT COUNT(*) AS c FROM acc_transactions t JOIN acc_transaction_categories c ON t.category_id = c.id WHERE c.type = ?');
        $stmt2->bind_param('s', $t);
        $stmt2->execute();
        $r2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        $cnt2 = (int)($r2['c'] ?? 0);
        $totals[$t] = ['count' => $cnt1, 'transaction_count' => $cnt2];
    }
} catch (Exception $e) {
    $categories = [];
    $totals = [];
    setToastMessage('danger', 'Error', 'Failed to load categories: ' . $e->getMessage(), 'club-logo');
    logError('Error loading categories: ' . $e->getMessage(), 'accounting');
}

// Category type colors for display
$type_colors = [
    'Income' => 'success',
    'Expense' => 'danger',
    'Asset' => 'info',
    'Liability' => 'warning',
    'Equity' => 'primary'
];

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
                        <i class="fas fa-tags fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Transaction Categories</h3>
                        <small>Organize transactions into meaningful categories</small>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group" role="group">
                            <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                                <a href="/accounting/categories/add.php" class="btn btn-dark btn-sm">
                                    <i class="fas fa-plus me-1"></i>Add Category
                                </a>
                            <?php endif; ?>
                            <a href="/accounting/dashboard.php" class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <?php if (!empty($totals)): ?>
            <div class="row mb-4">
                <?php foreach ($totals as $type => $data): ?>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-<?= $type_colors[$type] ?? 'secondary' ?> text-white h-100 shadow">
                            <div class="card-body text-center p-3">
                                <i class="fas fa-<?php
                                                    switch ($type) {
                                                        case 'Income':
                                                            echo 'arrow-up';
                                                            break;
                                                        case 'Expense':
                                                            echo 'arrow-down';
                                                            break;
                                                        case 'Asset':
                                                            echo 'coins';
                                                            break;
                                                        case 'Liability':
                                                            echo 'credit-card';
                                                            break;
                                                        case 'Equity':
                                                            echo 'balance-scale';
                                                            break;
                                                        default:
                                                            echo 'tag';
                                                    }
                                                    ?> fa-2x mb-2 opacity-75"></i>
                                <div class="fs-6 fw-bold"><?= $type ?></div>
                                <div class="fs-4"><?= $data['count'] ?></div>
                                <small class="opacity-75">
                                    categories
                                    <?php if ($data['transaction_count'] > 0): ?>
                                        <br><?= $data['transaction_count'] ?> txns
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Filters Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2 text-primary"></i>Filter Categories
                        </h5>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFilters">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" id="filterForm" class="row g-3">
                    <!-- Type Filter -->
                    <div class="col-md-4">
                        <label for="type" class="form-label">Category Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Types</option>
                            <option value="Income" <?= ($filters['type'] ?? '') === 'Income' ? 'selected' : '' ?>>Income</option>
                            <option value="Expense" <?= ($filters['type'] ?? '') === 'Expense' ? 'selected' : '' ?>>Expense</option>
                            <option value="Asset" <?= ($filters['type'] ?? '') === 'Asset' ? 'selected' : '' ?>>Asset</option>
                            <option value="Liability" <?= ($filters['type'] ?? '') === 'Liability' ? 'selected' : '' ?>>Liability</option>
                            <option value="Equity" <?= ($filters['type'] ?? '') === 'Equity' ? 'selected' : '' ?>>Equity</option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search"
                            value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                            placeholder="Search category name or description...">
                    </div>

                    <!-- Filter Actions -->
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories List -->
        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="mb-0">
                            <i class="fas fa-list me-2"></i>Transaction Categories
                            <?php if (!empty($categories)): ?>
                                <small class="opacity-75">(<?= count($categories) ?> categories)</small>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="col-auto">
                        <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                            <a href="/accounting/categories/add.php" class="btn btn-dark btn-sm">
                                <i class="fas fa-plus me-1"></i>Add Category
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No categories found</h5>
                        <p class="text-muted">Create categories to organize your transactions effectively.</p>
                        <?php if (hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage')): ?>
                            <a href="/accounting/categories/add.php" class="btn btn-warning">
                                <i class="fas fa-plus me-1"></i>Create First Category
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table View -->
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Category Name</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Transactions</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($category['name']) ?></div>
                                            <?php if (!empty($category['parent_category_name'])): ?>
                                                <small class="text-muted">Child of: <?= htmlspecialchars($category['parent_category_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $type_colors[$category['type']] ?? 'secondary' ?>">
                                                <?= htmlspecialchars($category['type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($category['description'] ?? '') ?: '<em class="text-muted">No description</em>' ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($category['transaction_count'] > 0): ?>
                                                <span class="badge bg-info"><?= $category['transaction_count'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('M j, Y', strtotime($category['created_at'])) ?><br>
                                                by <?= htmlspecialchars($category['created_by_username'] ?? 'Unknown') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if (hasPermission($user_id, 'accounting_edit') || hasPermission($user_id, 'accounting_manage')): ?>
                                                    <a href="/accounting/categories/edit.php?id=<?= $category['id'] ?>"
                                                        class="btn btn-outline-primary btn-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (hasPermission($user_id, 'accounting_delete') || hasPermission($user_id, 'accounting_manage')): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                                        onclick="deleteCategory(<?= $category['id'] ?>, '<?= addslashes($category['name']) ?>', <?= $category['transaction_count'] ?>)"
                                                        title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="d-lg-none">
                        <?php foreach ($categories as $category): ?>
                            <div class="card border-0 border-bottom rounded-0">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-8">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="badge bg-<?= $type_colors[$category['type']] ?? 'secondary' ?> me-2">
                                                    <?= htmlspecialchars($category['type']) ?>
                                                </span>
                                                <?php if ($category['transaction_count'] > 0): ?>
                                                    <span class="badge bg-info"><?= $category['transaction_count'] ?> txns</span>
                                                <?php endif; ?>
                                            </div>
                                            <h6 class="mb-1"><?= htmlspecialchars($category['name']) ?></h6>
                                            <?php if (!empty($category['description'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($category['description']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($category['parent_category_name'])): ?>
                                                <br><small class="text-muted">Child of: <?= htmlspecialchars($category['parent_category_name']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-4 text-end">
                                            <small class="text-muted d-block mb-2">
                                                <?= date('M j, Y', strtotime($category['created_at'])) ?>
                                            </small>
                                            <div class="btn-group" role="group">
                                                <?php if (hasPermission($user_id, 'accounting_edit') || hasPermission($user_id, 'accounting_manage')): ?>
                                                    <a href="/accounting/categories/edit.php?id=<?= $category['id'] ?>"
                                                        class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (hasPermission($user_id, 'accounting_delete') || hasPermission($user_id, 'accounting_manage')): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                                        onclick="deleteCategory(<?= $category['id'] ?>, '<?= addslashes($category['name']) ?>', <?= $category['transaction_count'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="deleteContent">
                        <!-- Dynamic content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        // Categories data for reassignment options
        const categoriesData = <?= json_encode(array_map(function ($c) {
                                    return ['id' => $c['id'], 'name' => $c['name'], 'type' => $c['type']];
                                }, $categories)); ?>;
        // JavaScript for category management functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Clear filters functionality
            document.getElementById('clearFilters').addEventListener('click', function() {
                const form = document.getElementById('filterForm');
                form.reset();
                window.location.href = window.location.pathname;
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

        // Delete category function
        function deleteCategory(categoryId, categoryName, hasTransactions) {
            const modal = document.getElementById('deleteModal');
            const content = document.getElementById('deleteContent');

            if (hasTransactions > 0) {
                let options = '<option value="">Select category...</option>';
                categoriesData.forEach(c => {
                    if (c.id !== categoryId) {
                        options += `<option value="${c.id}">${c.name} (${c.type})</option>`;
                    }
                });
                content.innerHTML = `
                <div class="alert alert-warning">
                    <h6>Category Has Transactions</h6>
                    <p>Category "${categoryName}" has ${hasTransactions} transaction(s). You must reassign these transactions before deletion.</p>
                </div>
                <form method="POST" action="/accounting/categories/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" value="${categoryId}">
                    <div class="mb-3">
                        <label class="form-label">Reassign transactions to:</label>
                        <select name="reassign_to" class="form-select" required>
                            ${options}
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">Delete & Reassign</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            `;
            } else {
                content.innerHTML = `
                <p>Are you sure you want to delete the category "${categoryName}"?</p>
                <p class="text-muted">This action cannot be undone.</p>
                <form method="POST" action="/accounting/categories/delete.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" value="${categoryId}">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">Delete Category</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            `;
            }

            // Show modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    </script>
</body>

</html>