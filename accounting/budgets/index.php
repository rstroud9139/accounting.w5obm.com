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
    header('Location: ' . route('dashboard'));
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

$budgetFilterCollapseId = 'budgetFilterCollapse';

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
                ['label' => 'Chart of Accounts', 'url' => route('accounts'), 'variant' => 'outline', 'icon' => 'fa-sitemap'],
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
                        <div class="card-header bg-primary text-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Budgets</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="index.php" class="btn btn-outline-light btn-sm">
                                    <i class="fas fa-times me-1"></i>Reset
                                </a>
                                <button type="button" class="btn btn-light btn-sm text-primary" data-bs-toggle="collapse" data-bs-target="#<?= $budgetFilterCollapseId ?>" aria-expanded="true" aria-controls="<?= $budgetFilterCollapseId ?>">
                                    <i class="fas fa-chevron-down me-1"></i>Toggle Filters
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0 collapse show" id="<?= $budgetFilterCollapseId ?>">
                            <div class="row g-0 flex-column flex-lg-row">
                                <div class="col-12 col-lg-3 border-bottom border-lg-bottom-0 border-lg-end bg-light-subtle p-3">
                                    <h6 class="text-uppercase small text-muted fw-bold mb-2">Quick Filters</h6>
                                    <p class="text-muted small mb-3">Jump to approval states or focus on active years.</p>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm budget-chip text-start" data-budget-status="draft">Drafts</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm budget-chip text-start" data-budget-status="approved">Approved</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm budget-chip text-start" data-budget-status="archived">Archived</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm budget-chip text-start" data-budget-status="all">All Statuses</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm budget-chip text-start" data-budget-year="<?= $currentYear ?>">Current Year</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm budget-chip text-start" data-budget-year="<?= $currentYear + 1 ?>">Next Year</button>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-9 p-3 p-lg-4">
                                    <form method="GET" id="budgetFilterForm">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-12 col-md-4">
                                                <label class="form-label text-muted text-uppercase small">Status</label>
                                                <select name="status" class="form-select">
                                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                                                    <?php foreach ($statuses as $key => $label): ?>
                                                        <option value="<?= htmlspecialchars($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label text-muted text-uppercase small">Fiscal Year</label>
                                                <select name="year" class="form-select">
                                                    <?php for ($yr = $currentYear - 2; $yr <= $currentYear + 3; $yr++): ?>
                                                        <option value="<?= $yr ?>" <?= $yearFilter === $yr ? 'selected' : '' ?>><?= $yr ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label text-muted text-uppercase small">Keyword</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                    <input type="search" name="search" class="form-control" value="<?= htmlspecialchars($searchFilter) ?>" placeholder="Search name or notes">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter me-1"></i>Apply Filters
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="budgetsClearBtn">
                                                <i class="fas fa-undo me-1"></i>Clear Fields
                                            </button>
                                        </div>
                                    </form>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('budgetFilterForm');
            if (!filterForm) {
                return;
            }
            const statusSelect = filterForm.querySelector('select[name="status"]');
            const yearSelect = filterForm.querySelector('select[name="year"]');
            const searchInput = filterForm.querySelector('input[name="search"]');
            const defaultYear = <?= (int)$currentYear ?>;

            document.querySelectorAll('[data-budget-status]').forEach(function(button) {
                button.addEventListener('click', function() {
                    if (statusSelect) {
                        statusSelect.value = this.dataset.budgetStatus || 'all';
                        filterForm.submit();
                    }
                });
            });

            document.querySelectorAll('[data-budget-year]').forEach(function(button) {
                button.addEventListener('click', function() {
                    if (yearSelect) {
                        yearSelect.value = this.dataset.budgetYear;
                        filterForm.submit();
                    }
                });
            });

            const clearBtn = document.getElementById('budgetsClearBtn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (statusSelect) {
                        statusSelect.value = 'all';
                    }
                    if (yearSelect) {
                        yearSelect.value = defaultYear;
                    }
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    filterForm.submit();
                });
            }
        });
    </script>
</body>

</html>