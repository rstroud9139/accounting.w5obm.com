<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/categoryController.php';
require_once __DIR__ . '/../views/categoryManager.php';

if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();
$canView = hasPermission($user_id, 'accounting_view') || hasPermission($user_id, 'accounting_manage');
$canAdd = hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage');
$canManage = hasPermission($user_id, 'accounting_manage');

if (!$canView) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view categories.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Transaction Categories - W5OBM Accounting';

$filters = [
    'type' => sanitizeInput($_GET['type'] ?? '', 'string'),
    'search' => sanitizeInput($_GET['search'] ?? '', 'string'),
    'status' => sanitizeInput($_GET['status'] ?? 'active', 'string'),
];

$statusWhitelist = ['active', 'inactive', 'all'];
if (!in_array($filters['status'], $statusWhitelist, true)) {
    $filters['status'] = 'active';
}

$queryFilters = [];
if (!empty($filters['type'])) {
    $queryFilters['type'] = $filters['type'];
}
if (!empty($filters['search'])) {
    $queryFilters['search'] = $filters['search'];
}

switch ($filters['status']) {
    case 'inactive':
        $queryFilters['active'] = false;
        break;
    case 'all':
        $queryFilters['active'] = 'all';
        break;
    default:
        $queryFilters['active'] = true;
        break;
}

try {
    $categories = getAllCategories($queryFilters);
} catch (Exception $e) {
    $categories = [];
    setToastMessage('danger', 'Categories', 'Unable to load categories: ' . $e->getMessage(), 'club-logo');
    logError('Error loading categories: ' . $e->getMessage(), 'accounting');
}

try {
    $parentOptions = getAllCategories(['active' => true], ['order_by' => 'c.name ASC']);
} catch (Exception $e) {
    $parentOptions = [];
    logError('Error loading category parent options: ' . $e->getMessage(), 'accounting');
}

$summary = [
    'total' => count($categories),
    'active' => 0,
    'inactive' => 0,
    'withTransactions' => 0,
    'needsDescription' => 0,
    'typeBreakdown' => array_fill_keys(['Income', 'Expense', 'Asset', 'Liability', 'Equity'], 0),
];

foreach ($categories as $category) {
    if (!empty($category['active'])) {
        $summary['active']++;
    } else {
        $summary['inactive']++;
    }

    if (!empty($category['transaction_count'])) {
        $summary['withTransactions']++;
    }

    if (empty(trim($category['description'] ?? ''))) {
        $summary['needsDescription']++;
    }

    $type = $category['type'] ?? null;
    if ($type && isset($summary['typeBreakdown'][$type])) {
        $summary['typeBreakdown'][$type]++;
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="categories_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Type', 'Description', 'Parent', 'Transactions', 'Status']);
    foreach ($categories as $category) {
        fputcsv($output, [
            $category['id'] ?? '',
            $category['name'] ?? '',
            $category['type'] ?? '',
            $category['description'] ?? '',
            $category['parent_category_name'] ?? '',
            $category['transaction_count'] ?? 0,
            !empty($category['active']) ? 'Active' : 'Archived',
        ]);
    }
    fclose($output);
    exit();
}

require_once __DIR__ . '/../../include/header.php';
?>

<div class="page-container" style="margin-top:0;padding-top:0;">
    <section class="hero hero-small mb-4">
        <div class="hero-body py-3">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-2 d-none d-md-flex justify-content-center">
                        <img src="https://w5obm.com/images/badges/club_logo.png" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                    </div>
                    <div class="col-md-6 text-center text-md-start text-white">
                        <h1 class="h4 mb-1">Category Management</h1>
                        <p class="mb-0 small">Organize every transaction under a consistent taxonomy</p>
                    </div>
                    <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                        <?php if ($canAdd): ?>
                            <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-plus-circle me-1"></i>New Category
                            </button>
                        <?php endif; ?>
                        <a href="/accounting/manual.php#categories" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-book me-1"></i>Documentation
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row mb-3 hero-summary-row">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Total Categories</span>
                    <i class="fas fa-tags text-warning"></i>
                </div>
                <h4 class="mb-0"><?= number_format($summary['total']) ?></h4>
                <small class="text-muted">After filters</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Active vs Archived</span>
                    <i class="fas fa-archive text-secondary"></i>
                </div>
                <h4 class="mb-0"><?= number_format($summary['active']) ?> / <?= number_format($summary['inactive']) ?></h4>
                <small class="text-muted">Active / Archived</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Linked Transactions</span>
                    <i class="fas fa-link text-info"></i>
                </div>
                <h4 class="mb-0"><?= number_format($summary['withTransactions']) ?></h4>
                <small class="text-muted">Categories in use</small>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Needs Description</span>
                    <i class="fas fa-highlighter text-danger"></i>
                </div>
                <h4 class="mb-0"><?= number_format($summary['needsDescription']) ?></h4>
                <small class="text-muted">Add guidance text</small>
            </div>
        </div>
    </div>

    <?php if (function_exists('displayToastMessage')): ?>
        <?php displayToastMessage(); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-3 mb-4">
            <nav class="bg-light border rounded h-100 p-0 shadow-sm">
                <div class="px-3 py-2 border-bottom">
                    <span class="text-muted text-uppercase small">Workspace</span>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/accounting/dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-pie me-2 text-primary"></i>Dashboard</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/transactions/transactions.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-exchange-alt me-2 text-success"></i>Transactions</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/reports_dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-bar me-2 text-info"></i>Reports</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/ledger/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-book me-2 text-warning"></i>Chart of Accounts</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/categories/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center active">
                        <span><i class="fas fa-tags me-2 text-secondary"></i>Categories</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <a href="/accounting/vendors/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-store me-2 text-danger"></i>Vendors</span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <div class="list-group-item small text-muted text-uppercase">Other</div>
                    <a href="/accounting/assets/" class="list-group-item list-group-item-action">
                        <i class="fas fa-boxes me-2"></i>Assets
                    </a>
                    <a href="/accounting/donations/" class="list-group-item list-group-item-action">
                        <i class="fas fa-heart me-2"></i>Donations
                    </a>
                </div>
            </nav>
        </div>
        <div class="col-lg-9 mb-4">
            <?php
            renderCategoryWorkspace(
                $categories,
                $filters,
                $summary,
                $parentOptions,
                $canAdd,
                $canManage
            );
            ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../include/footer.php'; ?>