<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/budgetController.php';
require_once __DIR__ . '/../../include/premium_hero.php';
require_once __DIR__ . '/../include/accounting_nav_helpers.php';

validate_session();

$userId = getCurrentUserId();
$canManage = hasPermission($userId, 'accounting_manage') || hasPermission($userId, 'accounting_add');
if (!$canManage && !hasPermission($userId, 'accounting_view')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view budgets.', 'fas fa-ban');
    header('Location: /accounting/dashboard.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_budget'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setToastMessage('danger', 'Security', 'Invalid token while deleting budget.', 'fas fa-ban');
        header('Location: index.php');
        exit();
    }

    $deleteId = (int)($_POST['delete_budget'] ?? 0);
    if ($deleteId && deleteBudget($deleteId)) {
        setToastMessage('success', 'Budget Removed', 'Budget deleted successfully.', 'fas fa-trash');
    } else {
        setToastMessage('danger', 'Delete Failed', 'Unable to delete budget.', 'fas fa-exclamation-triangle');
    }

    header('Location: index.php');
    exit();
}

$currentYear = (int)date('Y');
$statusFilter = sanitizeInput($_GET['status'] ?? 'all', 'string');
$yearFilter = (int)($_GET['year'] ?? $currentYear);
$searchFilter = sanitizeInput($_GET['search'] ?? '', 'string');

$filters = [
    'status' => $statusFilter,
    'year' => $yearFilter,
    'search' => $searchFilter,
];

$budgets = fetchBudgets($filters);
$statuses = budgetStatuses();
$statusBadges = [
    'draft' => 'warning',
    'approved' => 'success',
    'archived' => 'secondary',
];

$totalBudgets = count($budgets);
$totalAllocated = array_sum(array_map(static function ($budget) {
    return (float)($budget['allocated_amount'] ?? 0);
}, $budgets));
$statusCounts = [];
foreach ($budgets as $budget) {
    $statusCounts[$budget['status']] = ($statusCounts[$budget['status']] ?? 0) + 1;
}

$page_title = 'Budgets - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <?php if (function_exists('renderPremiumHero')): ?>
        <?php renderPremiumHero([
            'eyebrow' => 'Budget Office',
            'title' => 'Budget Portfolio',
            'subtitle' => 'Build, approve, and monitor budgets aligned to every ledger account.',
            'description' => 'Create multiple fiscal plans, track allocations, and keep board reporting on schedule.',
            'theme' => 'midnight',
            'size' => 'compact',
            'chips' => array_filter([
                $statusFilter !== 'all' ? 'Status: ' . ucfirst($statusFilter) : 'All statuses',
                'Year: ' . $yearFilter,
                !empty($searchFilter) ? 'Search: ' . $searchFilter : null,
            ]),
            'highlights' => [
                ['label' => 'Active Budgets', 'value' => number_format($totalBudgets), 'meta' => 'Filtered list'],
                ['label' => 'Allocated', 'value' => '$' . number_format($totalAllocated, 2), 'meta' => 'Total planned'],
                ['label' => 'Draft Items', 'value' => number_format($statusCounts['draft'] ?? 0), 'meta' => 'Need review'],
            ],
            'actions' => $canManage ? [
                ['label' => 'New Budget', 'url' => '/accounting/budgets/manage.php', 'icon' => 'fa-plus-circle'],
                ['label' => 'Chart of Accounts', 'url' => '/accounting/ledger/', 'variant' => 'outline', 'icon' => 'fa-sitemap'],
            ] : [
                ['label' => 'View Reports', 'url' => '/accounting/reports_dashboard.php', 'variant' => 'outline', 'icon' => 'fa-chart-line'],
            ],
        ]); ?>
    <?php endif; ?>

    <div class="page-container">
        <?php if (function_exists('displayToastMessage')): ?>
            <?php displayToastMessage(); ?>
        <?php endif; ?>

        <div class="container-fluid py-4">
            <div class="row g-4">
                <div class="col-lg-3">
                    <?php if (function_exists('accounting_render_workspace_nav')): ?>
                        <?php accounting_render_workspace_nav('budgets'); ?>
                    <?php endif; ?>
                </div>
                <div class="col-lg-9">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-light border-0">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <form method="GET">
                                        <label class="form-label text-muted text-uppercase small">Status</label>
                                        <select name="status" class="form-select" onchange="this.form.submit()">
                                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                                            <?php foreach ($statuses as $key => $label): ?>
                                                <option value="<?= htmlspecialchars($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="year" value="<?= htmlspecialchars($yearFilter) ?>">
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($searchFilter) ?>">
                                    </form>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <form method="GET">
                                        <label class="form-label text-muted text-uppercase small">Fiscal Year</label>
                                        <select name="year" class="form-select" onchange="this.form.submit()">
                                            <?php for ($yr = $currentYear - 2; $yr <= $currentYear + 3; $yr++): ?>
                                                <option value="<?= $yr ?>" <?= $yearFilter === $yr ? 'selected' : '' ?>><?= $yr ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($searchFilter) ?>">
                                    </form>
                                </div>
                                <div class="col-lg-4 col-md-4">
                                    <form method="GET" class="d-flex gap-2">
                                        <div class="flex-grow-1">
                                            <label class="form-label text-muted text-uppercase small">Search</label>
                                            <input type="search" name="search" class="form-control" value="<?= htmlspecialchars($searchFilter) ?>" placeholder="Search name or notes">
                                        </div>
                                        <div class="align-self-end mb-1">
                                            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                                        </div>
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                        <input type="hidden" name="year" value="<?= htmlspecialchars($yearFilter) ?>">
                                    </form>
                                </div>
                                <div class="col-lg-2 col-sm-12 text-lg-end">
                                    <a href="index.php" class="btn btn-link text-decoration-none">Reset Filters</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($budgets)): ?>
                        <div class="card shadow border-0">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-piggy-bank fa-3x text-muted mb-3"></i>
                                <h5 class="mb-2">No budgets match the filters.</h5>
                                <p class="text-muted">Create a new budget to start tracking allocations.</p>
                                <?php if ($canManage): ?>
                                    <a href="manage.php" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i>Create Budget</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card shadow border-0">
                            <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><i class="fas fa-table me-2 text-primary"></i>Budget Register</h5>
                                    <small class="text-muted">Track approvals, allocations, and quick links into the chart of accounts.</small>
                                </div>
                                <?php if ($canManage): ?>
                                    <a href="manage.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Budget</a>
                                <?php endif; ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Year</th>
                                            <th>Status</th>
                                            <th>Allocated</th>
                                            <th>Lines</th>
                                            <th>Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($budgets as $budget): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold mb-0"><?= htmlspecialchars($budget['name']) ?></div>
                                                    <small class="text-muted">Created by <?= htmlspecialchars($budget['created_by_name'] ?? 'System') ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($budget['fiscal_year']) ?></td>
                                                <td>
                                                    <?php $badge = $statusBadges[$budget['status']] ?? 'secondary'; ?>
                                                    <span class="badge bg-<?= $badge ?> bg-opacity-10 text-<?= $badge ?>">
                                                        <?= htmlspecialchars(ucfirst($budget['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>$<?= number_format($budget['allocated_amount'] ?? 0, 2) ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= number_format($budget['line_count'] ?? 0) ?></span></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= $budget['updated_at'] ? date('M d, Y', strtotime($budget['updated_at'])) : '—' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="manage.php?id=<?= $budget['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i></a>
                                                        <?php if ($canManage): ?>
                                                            <form method="POST" onsubmit="return confirm('Delete this budget?');">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                <button type="submit" name="delete_budget" value="<?= $budget['id'] ?>" class="btn btn-outline-danger btn-sm">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>