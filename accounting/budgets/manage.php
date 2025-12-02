<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/budgetController.php';
require_once __DIR__ . '/../app/repositories/AccountRepository.php';
require_once __DIR__ . '/../../include/premium_hero.php';

validate_session();

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage') && !hasPermission($userId, 'accounting_add')) {
    setToastMessage('danger', 'Access Denied', 'You do not have budget maintenance permissions.', 'fas fa-ban');
    header('Location: /accounting/dashboard.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$budgetId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$budget = $budgetId ? getBudgetById($budgetId) : null;
$errors = [];

$statuses = budgetStatuses();
$currentYear = (int)date('Y');
$fiscalYears = range($currentYear - 1, $currentYear + 3);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $incomingId = isset($_POST['budget_id']) ? (int)$_POST['budget_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $fiscalYear = (int)($_POST['fiscal_year'] ?? $currentYear);
        $status = $_POST['status'] ?? 'draft';
        $notes = trim($_POST['notes'] ?? '');
        $lines = $_POST['lines'] ?? [];

        if ($name === '') {
            $errors[] = 'Budget name is required.';
        }
        if (!in_array($fiscalYear, $fiscalYears, true)) {
            $fiscalYear = $currentYear;
        }
        if (!array_key_exists($status, $statuses)) {
            $status = 'draft';
        }

        $lineItems = [];
        foreach ($lines as $accountId => $lineData) {
            $amount = isset($lineData['annual_amount']) ? (float)$lineData['annual_amount'] : 0;
            if ($amount <= 0) {
                continue;
            }
            $lineItems[(int)$accountId] = round($amount, 2);
        }

        if (empty($lineItems)) {
            $errors[] = 'Enter at least one account allocation.';
        }

        if (empty($errors)) {
            $payload = [
                'name' => $name,
                'fiscal_year' => $fiscalYear,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
            ];

            if ($incomingId) {
                $result = updateBudget($incomingId, $payload, $lineItems);
                if ($result) {
                    setToastMessage('success', 'Budget Saved', 'Budget updated successfully.', 'fas fa-piggy-bank');
                    header('Location: manage.php?id=' . $incomingId);
                    exit();
                }
                $errors[] = 'Unable to update budget.';
            } else {
                $newId = createBudget($payload, $lineItems);
                if ($newId) {
                    setToastMessage('success', 'Budget Created', 'Budget created successfully.', 'fas fa-piggy-bank');
                    header('Location: manage.php?id=' . $newId);
                    exit();
                }
                $errors[] = 'Unable to create budget.';
            }
        }

        $budget = $budget ?? [];
        $budget['name'] = $name;
        $budget['fiscal_year'] = $fiscalYear;
        $budget['status'] = $status;
        $budget['notes'] = $notes;
        $budget['lines'] = array_map(static function ($amount) {
            return ['annual_amount' => $amount];
        }, $lineItems);
        $budgetId = $incomingId;
    }
}

$dbConn = accounting_db_connection();
$accountRepository = new AccountRepository($dbConn);
$rawAccounts = $accountRepository->getAll();
$namesById = [];
foreach ($rawAccounts as $row) {
    $namesById[(int)($row['id'] ?? 0)] = $row['name'] ?? '';
}

$allAccounts = [];
foreach ($rawAccounts as $row) {
    $isActive = (int)($row['is_active'] ?? 1) === 1;
    if (!$isActive) {
        continue;
    }
    $parentId = isset($row['parent_id']) && (int)$row['parent_id'] > 0 ? (int)$row['parent_id'] : null;
    $allAccounts[] = [
        'id' => (int)($row['id'] ?? 0),
        'account_number' => $row['code'] ?? '',
        'account_type' => $row['type'] ?? 'Other',
        'name' => $row['name'] ?? '',
        'parent_account_id' => $parentId,
        'parent_account_name' => $parentId && isset($namesById[$parentId]) ? $namesById[$parentId] : null,
    ];
}

usort($allAccounts, static function ($a, $b) {
    $keyA = ($a['account_type'] ?? '') . '|' . ($a['account_number'] ?? '') . '|' . ($a['name'] ?? '');
    $keyB = ($b['account_type'] ?? '') . '|' . ($b['account_number'] ?? '') . '|' . ($b['name'] ?? '');
    return $keyA <=> $keyB;
});

$groupedAccounts = [];
foreach ($allAccounts as $account) {
    $groupKey = $account['account_type'] ?? 'Other';
    $groupedAccounts[$groupKey][] = $account;
}

$lineMap = $budget['lines'] ?? [];
$totalAllocated = summarizeBudgetLines($lineMap);

$page_title = ($budgetId ? 'Edit Budget' : 'New Budget') . ' - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';
?>

<style>
    .budget-meta-card textarea {
        min-height: 140px;
    }

    .budget-accounts-table th,
    .budget-accounts-table td {
        vertical-align: middle;
    }

    .budget-accounts-table input[type="number"] {
        max-width: 140px;
    }

    .account-type-header {
        background: #f8fafc;
        border-radius: 16px;
        padding: 0.65rem 1rem;
        margin-bottom: 0.5rem;
    }

    .account-filter-input {
        max-width: 360px;
    }
</style>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <?php if (function_exists('renderPremiumHero')): ?>
        <?php renderPremiumHero([
            'eyebrow' => 'Budget Planning',
            'title' => $budgetId ? 'Update Budget Plan' : 'Create Budget Plan',
            'subtitle' => 'Align allocations to every ledger line for full accountability.',
            'description' => 'Track annual targets by account and keep leadership synced with your fiscal roadmap.',
            'theme' => 'midnight',
            'size' => 'compact',
            'highlights' => [
                ['label' => 'Fiscal Year', 'value' => $budget['fiscal_year'] ?? $currentYear, 'meta' => 'Active plan'],
                ['label' => 'Allocated', 'value' => '$' . number_format($totalAllocated, 2), 'meta' => 'Annual total'],
                ['label' => 'Status', 'value' => ucfirst($budget['status'] ?? 'draft'), 'meta' => 'Lifecycle'],
            ],
            'actions' => array_values(array_filter([
                ['label' => 'Back to Budgets', 'url' => '/accounting/budgets/index.php', 'variant' => 'outline', 'icon' => 'fa-arrow-left'],
                $budgetId ? ['label' => 'View Reports', 'url' => '/accounting/reports_dashboard.php', 'variant' => 'outline', 'icon' => 'fa-chart-line'] : null,
            ])),
        ]); ?>
    <?php endif; ?>

    <div class="page-container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <strong>Budget errors detected:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="budget-form">
            <div class="card shadow border-0 mb-4 budget-meta-card">
                <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="fas fa-piggy-bank me-2 text-success"></i>Budget Details</h5>
                        <small class="text-muted">Define the fiscal envelope before assigning account targets.</small>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i><?= $budgetId ? 'Save Changes' : 'Create Budget' ?>
                    </button>
                </div>
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="budget_id" value="<?= htmlspecialchars($budgetId ?? '') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted text-uppercase small">Budget Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. FY<?= $currentYear ?> Operating Budget" value="<?= htmlspecialchars($budget['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted text-uppercase small">Fiscal Year</label>
                            <select name="fiscal_year" class="form-select">
                                <?php foreach ($fiscalYears as $year): ?>
                                    <option value="<?= $year ?>" <?= (int)($budget['fiscal_year'] ?? $currentYear) === $year ? 'selected' : '' ?>><?= $year ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted text-uppercase small">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= ($budget['status'] ?? 'draft') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted text-uppercase small">Narrative / Notes</label>
                            <textarea name="notes" class="form-control" placeholder="Add context for board review"><?= htmlspecialchars($budget['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow border-0">
                <div class="card-header bg-light border-0">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <h5 class="mb-1"><i class="fas fa-layer-group me-2 text-primary"></i>Account Allocations</h5>
                            <small class="text-muted">Enter annual amounts for each ledger account. Totals update automatically.</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="search" class="form-control form-control-sm account-filter-input" placeholder="Filter accounts" id="accountFilterInput">
                            <div>
                                <div class="text-muted small">Total Allocated</div>
                                <div class="fw-bold" id="allocatedTotal" data-base="<?= htmlspecialchars($totalAllocated) ?>">$<?= number_format($totalAllocated, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($groupedAccounts as $type => $accounts): ?>
                        <div class="mb-4">
                            <div class="account-type-header d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($type) ?></strong>
                                    <small class="text-muted ms-2" data-type-total="<?= htmlspecialchars($type) ?>">$0.00</small>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary"><?= count($accounts) ?> accounts</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle budget-accounts-table" data-account-type="<?= htmlspecialchars($type) ?>">
                                    <thead>
                                        <tr>
                                            <th style="min-width:250px;">Account</th>
                                            <th>Number</th>
                                            <th style="width:200px;">Annual Amount ($)</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($accounts as $account): ?>
                                            <?php
                                            $existing = $lineMap[$account['id']] ?? null;
                                            $searchSeed = strtolower(($account['name'] ?? '') . ' ' . ($account['account_number'] ?? ''));
                                            ?>
                                            <tr data-account-row data-account-search="<?= htmlspecialchars($searchSeed) ?>" data-account-type-row="<?= htmlspecialchars($type) ?>">
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($account['name']) ?></div>
                                                    <?php if (!empty($account['parent_account_name'])): ?>
                                                        <small class="text-muted">Parent: <?= htmlspecialchars($account['parent_account_name']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($account['account_number']) ?></span></td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm budget-input" step="0.01" min="0" name="lines[<?= $account['id'] ?>][annual_amount]" value="<?= htmlspecialchars($existing['annual_amount'] ?? '') ?>" placeholder="0.00">
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($account['description'] ?? 'No description provided.') ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
        </form>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        const budgetInputs = document.querySelectorAll('.budget-input');
        const totalEl = document.getElementById('allocatedTotal');
        const typeTotals = document.querySelectorAll('[data-type-total]');
        const filterInput = document.getElementById('accountFilterInput');

        function formatCurrency(value) {
            return '$' + Number(value).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function recalcTotals() {
            const typeSums = {};
            let total = 0;
            budgetInputs.forEach(input => {
                const raw = parseFloat(input.value || '0');
                if (raw > 0) {
                    const row = input.closest('tr');
                    const type = row.dataset.accountTypeRow;
                    typeSums[type] = (typeSums[type] || 0) + raw;
                    total += raw;
                }
            });

            totalEl.textContent = formatCurrency(total);

            typeTotals.forEach(label => {
                const type = label.dataset.typeTotal;
                label.textContent = formatCurrency(typeSums[type] || 0);
            });
        }

        function filterAccounts() {
            const query = (filterInput.value || '').toLowerCase();
            document.querySelectorAll('[data-account-row]').forEach(row => {
                const haystack = row.dataset.accountSearch || '';
                const matches = haystack.includes(query);
                row.style.display = matches ? '' : 'none';
            });
        }

        budgetInputs.forEach(input => input.addEventListener('input', recalcTotals));
        if (filterInput) {
            filterInput.addEventListener('input', filterAccounts);
        }
        recalcTotals();
    </script>
</body>

</html>