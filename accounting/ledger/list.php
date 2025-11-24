<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/ledger_controller.php';
require_once __DIR__ . '/../lib/helpers.php';

validate_session();

$status = $_GET['status'] ?? null;
$accounts = fetch_all_ledger_accounts();

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value, $type = 'string')
    {
        if ($type === 'string') {
            return trim(filter_var($value, FILTER_SANITIZE_STRING));
        }
        return $value;
    }
}

$filters = [
    'category' => sanitizeInput($_GET['category'] ?? 'all', 'string'),
    'search' => sanitizeInput($_GET['search'] ?? '', 'string'),
    'sort' => sanitizeInput($_GET['sort'] ?? 'name_asc', 'string'),
];

$allowedSorts = ['name_asc', 'name_desc', 'category_asc', 'category_desc'];
if (!in_array($filters['sort'], $allowedSorts, true)) {
    $filters['sort'] = 'name_asc';
}

$categoryResolver = static function ($account) {
    $category = $account['category_name'] ?? ($account['categoryName'] ?? ($account['account_type'] ?? ''));
    return trim((string)$category);
};

$availableCategories = array_values(array_unique(array_filter(array_map($categoryResolver, $accounts))));
natcasesort($availableCategories);
$availableCategories = array_values($availableCategories);

if ($filters['category'] !== 'all' && $filters['category'] !== '' && !in_array($filters['category'], $availableCategories, true)) {
    $availableCategories[] = $filters['category'];
    natcasesort($availableCategories);
    $availableCategories = array_values($availableCategories);
}

$filteredAccounts = array_values(array_filter($accounts, static function ($account) use ($filters, $categoryResolver) {
    $category = $categoryResolver($account);
    $matchesCategory = $filters['category'] === 'all' || strcasecmp($category, $filters['category']) === 0;

    $matchesSearch = true;
    if ($filters['search'] !== '') {
        $needle = strtolower($filters['search']);
        $haystack = strtolower(
            ($account['name'] ?? '') . ' ' .
                ($account['description'] ?? '') . ' ' .
                ($category ?? '') . ' ' .
                (string)($account['id'] ?? '')
        );
        $matchesSearch = strpos($haystack, $needle) !== false;
    }

    return $matchesCategory && $matchesSearch;
}));

if (!empty($filteredAccounts)) {
    usort($filteredAccounts, static function ($a, $b) use ($filters, $categoryResolver) {
        $categoryA = $categoryResolver($a);
        $categoryB = $categoryResolver($b);
        switch ($filters['sort']) {
            case 'name_desc':
                return strcasecmp($b['name'] ?? '', $a['name'] ?? '');
            case 'category_asc':
                return strcasecmp($categoryA, $categoryB);
            case 'category_desc':
                return strcasecmp($categoryB, $categoryA);
            case 'name_asc':
            default:
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        }
    });
}

$summaryTotals = [
    'total' => count($accounts),
    'categories' => count($availableCategories),
    'missingDescription' => count(array_filter($accounts, static function ($account) {
        return trim((string)($account['description'] ?? '')) === '';
    })),
    'filtered' => count($filteredAccounts),
];

$presetCategories = array_slice($availableCategories, 0, 3);

if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ledger_accounts_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Category', 'Description']);
    foreach ($filteredAccounts as $account) {
        fputcsv($output, [
            $account['id'] ?? '',
            $account['name'] ?? '',
            $categoryResolver($account),
            $account['description'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$statusMessages = [
    'success' => ['type' => 'success', 'text' => 'Ledger account added successfully!'],
    'updated' => ['type' => 'success', 'text' => 'Ledger account updated successfully!'],
    'deleted' => ['type' => 'success', 'text' => 'Ledger account deleted successfully!'],
    'error' => ['type' => 'danger', 'text' => 'An error occurred. Please try again.'],
    'in_use' => ['type' => 'warning', 'text' => 'This account has transactions. Please reassign them first.'],
    'reassign_error' => ['type' => 'danger', 'text' => 'Failed to reassign transactions. Please try again.'],
];

$page_title = 'Ledger Accounts - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <section class="hero hero-small mb-4">
            <div class="hero-body py-3">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-2 d-none d-md-flex justify-content-center">
                            <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                            <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                        </div>
                        <div class="col-md-6 text-center text-md-start text-white">
                            <h1 class="h4 mb-1">Ledger Accounts</h1>
                            <p class="mb-0 small">Manage the chart of accounts powering every transaction.</p>
                        </div>
                        <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                            <a href="../dashboard.php" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-arrow-left me-1"></i>Back to Accounting
                            </a>
                            <a href="add.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus-circle me-1"></i>Add Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($status && isset($statusMessages[$status])): ?>
            <div class="alert alert-<?= $statusMessages[$status]['type']; ?> alert-dismissible fade show shadow" role="alert">
                <?= htmlspecialchars($statusMessages[$status]['text']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3 hero-summary-row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Total Accounts</span>
                        <i class="fas fa-layer-group text-primary"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($summaryTotals['total']); ?></h4>
                    <small class="text-muted">Across all categories</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Categories</span>
                        <i class="fas fa-tags text-success"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format(max($summaryTotals['categories'], 1)); ?></h4>
                    <small class="text-muted">Unique classifications</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Needs Description</span>
                        <i class="fas fa-info-circle text-warning"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($summaryTotals['missingDescription']); ?></h4>
                    <small class="text-muted">Accounts missing context</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Filtered Results</span>
                        <i class="fas fa-filter text-warning"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($summaryTotals['filtered']); ?></h4>
                    <small class="text-muted">Match current filters</small>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Ledger Accounts</h5>
                    </div>
                    <div class="col-auto">
                        <a href="list.php" class="btn btn-outline-light btn-sm"><i class="fas fa-times me-1"></i>Reset</a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 flex-column flex-lg-row">
                    <div class="col-12 col-lg-3 border-bottom border-lg-bottom-0 border-lg-end bg-light-subtle p-3">
                        <h6 class="text-uppercase small text-muted fw-bold mb-2">Preset Filters</h6>
                        <p class="text-muted small mb-3">Jump to common categories or clear everything.</p>
                        <div class="d-grid gap-2">
                            <?php if (!empty($presetCategories)): ?>
                                <?php foreach ($presetCategories as $category): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-category="<?= htmlspecialchars($category); ?>">
                                        <span class="fw-semibold"><?= htmlspecialchars($category); ?></span>
                                        <small class="d-block text-muted">Focus on this category</small>
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info small mb-0">Add categories to unlock quick presets.</div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-sort="name_desc">
                                <span class="fw-semibold">Name Z-A</span>
                                <small class="d-block text-muted">Reverse alphabetical</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-clear="true">
                                <span class="fw-semibold">Clear Presets</span>
                                <small class="d-block text-muted">Reset filters & keyword</small>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="ledgerFilterForm">
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <div class="col">
                                    <label for="category" class="form-label text-muted text-uppercase small mb-1">Category</label>
                                    <select id="category" name="category" class="form-select form-select-sm">
                                        <option value="all" <?= $filters['category'] === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                        <?php foreach ($availableCategories as $category): ?>
                                            <option value="<?= htmlspecialchars($category); ?>" <?= $filters['category'] === $category ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($category); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="sort" class="form-label text-muted text-uppercase small mb-1">Sort By</label>
                                    <select id="sort" name="sort" class="form-select form-select-sm">
                                        <option value="name_asc" <?= $filters['sort'] === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                        <option value="name_desc" <?= $filters['sort'] === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                        <option value="category_asc" <?= $filters['sort'] === 'category_asc' ? 'selected' : ''; ?>>Category (A-Z)</option>
                                        <option value="category_desc" <?= $filters['sort'] === 'category_desc' ? 'selected' : ''; ?>>Category (Z-A)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 col-lg-8">
                                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                    <input type="text" id="search" name="search" class="form-control form-control-sm" placeholder="Name, description, ID" value="<?= htmlspecialchars($filters['search']); ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="ledgerExportBtn">
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

        <div class="card shadow border-0">
            <div class="card-header bg-light border-0 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas the rest...``` (truncated due to message length maybe). need to ensure entire content. create_file aborted due to size? yes message truncated? Could be handshake raising unstoppable. Need to send entire content but there is limit, but create_file call may have been truncated causing invalid? Need check file after attempt maybe partially created? Since command likely failed due to length? `create_file` responded? check result maybe? Not provided. Possibly failure due to size? need check file to confirm: read file. Need rest part, but due to token limit, autop? We'll check file contents. `read_file entire maybe 400 lines? We'll inspect portion near bottom to ensure entire script inserted. Use read_file 1-400 etc. We'll check lumps. Need to ensure script ends as intended. Use `read_file` glimpsed near top to confirm though unstoppable. We'll read entire file to verify. Use multiple read_file segments. We'll check first 400 lines. (Large file). Need to ensure truncated not ironically? We'll read glimpsed. Need to ensure we ended up truncated due to `create_file` error? but there was no error message after create_file call? Actually yes there was no mention of success/fail because truncated? I must check message from tool – returned zipped? there is no response meaning success? panel? Wait after calling create_file we expect 