<?php
// /accounting/assets/list.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/assetController.php';
require_once __DIR__ . '/../../include/premium_hero.php';
require_once __DIR__ . '/../include/accounting_nav_helpers.php';

// Ensure the user is logged in
validate_session();

$userId = getCurrentUserId();
$canViewAssets = hasPermission($userId, 'accounting_view') || hasPermission($userId, 'accounting_manage') || hasPermission($userId, 'accounting_add');
$canManageAssets = hasPermission($userId, 'accounting_manage') || hasPermission($userId, 'accounting_add');
$canDeleteAssets = hasPermission($userId, 'accounting_manage');

if (!$canViewAssets) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view assets.', 'fas fa-lock');
    header('Location: /accounting/dashboard.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$statusKey = $_GET['status'] ?? null;
if ($statusKey && function_exists('setToastMessage')) {
    switch ($statusKey) {
        case 'success':
            setToastMessage('success', 'Asset Added', 'The asset was added successfully.', 'fas fa-box');
            break;
        case 'updated':
            setToastMessage('success', 'Asset Updated', 'Changes saved to the asset record.', 'fas fa-check');
            break;
        case 'deleted':
            setToastMessage('success', 'Asset Deleted', 'Asset removed from the register.', 'fas fa-trash');
            break;
        case 'not_found':
            setToastMessage('warning', 'Missing Asset', 'The requested asset could not be found.', 'fas fa-question-circle');
            break;
        case 'invalid_request':
            setToastMessage('danger', 'Invalid Request', 'The request could not be validated.', 'fas fa-exclamation-triangle');
            break;
        case 'error':
            setToastMessage('danger', 'Action Failed', 'Unable to complete the asset request.', 'fas fa-times-circle');
            break;
    }
}

$searchTerm = sanitizeInput($_GET['search'] ?? '', 'string');
$filters = [];
if ($searchTerm !== '') {
    $filters['search'] = $searchTerm;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportAssets = getAllAssets($filters);
    $filename = 'w5obm_assets_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Asset Name', 'Value', 'Acquisition Date', 'Depreciation Rate (%)', 'Current Value', 'Created By', 'Description']);
    foreach ($exportAssets as $asset) {
        $currentValue = calculateAssetCurrentValue($asset);
        fputcsv($output, [
            $asset['id'] ?? '',
            $asset['name'] ?? '',
            $asset['value'] ?? 0,
            $asset['acquisition_date'] ?? '',
            $asset['depreciation_rate'] ?? 0,
            number_format($currentValue, 2, '.', ''),
            $asset['created_by_username'] ?? 'System',
            trim(preg_replace('/\s+/', ' ', $asset['description'] ?? '')),
        ]);
    }
    fclose($output);
    exit();
}

$assets = getAllAssets($filters);
$totalAssets = count($assets);
$totalValue = 0;
$totalCurrentValue = 0;
$depreciableAssets = 0;
$assetsWithNotes = 0;
$totalAgeYears = 0;

foreach ($assets as $asset) {
    $value = (float)($asset['value'] ?? 0);
    $totalValue += $value;

    $currentValue = calculateAssetCurrentValue($asset);
    $totalCurrentValue += $currentValue;

    if (!empty($asset['depreciation_rate'])) {
        $depreciableAssets++;
    }

    if (!empty(trim($asset['description'] ?? ''))) {
        $assetsWithNotes++;
    }

    if (!empty($asset['acquisition_date'])) {
        $totalAgeYears += calculateYearsSinceAcquisition($asset['acquisition_date']);
    }
}

$averageAge = $totalAssets > 0 ? $totalAgeYears / $totalAssets : 0;

$assetHeroChips = array_values(array_filter([
    $searchTerm !== '' ? 'Search: ' . $searchTerm : null,
    $totalAssets ? 'Results: ' . number_format($totalAssets) : 'Scope: Entire register',
]));

if (empty($assetHeroChips)) {
    $assetHeroChips[] = 'Scope: Entire register';
}

$exportQuery = array_filter([
    'search' => $searchTerm !== '' ? $searchTerm : null,
    'export' => 'csv',
]);

$assetHeroHighlights = [
    [
        'label' => 'Assets',
        'value' => number_format($totalAssets),
        'meta' => 'Filtered list'
    ],
    [
        'label' => 'Book Value',
        'value' => '$' . number_format($totalValue, 2),
        'meta' => 'Nominal cost'
    ],
    [
        'label' => 'Depreciable',
        'value' => number_format($depreciableAssets),
        'meta' => 'Tracking depreciation'
    ],
];

$assetHeroActions = array_values(array_filter([
    $canManageAssets ? [
        'label' => 'Add Asset',
        'url' => '/accounting/assets/add.php',
        'icon' => 'fa-plus-circle'
    ] : null,
    [
        'label' => 'Export CSV',
        'url' => '/accounting/assets/list.php' . (!empty($exportQuery) ? '?' . http_build_query($exportQuery) : ''),
        'variant' => 'outline',
        'icon' => 'fa-file-export'
    ],
    [
        'label' => 'Back to Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left'
    ],
]));

$page_title = 'Asset Register - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';
?>
<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="page-container accounting-assets-shell">
        <?php if (function_exists('displayToastMessage')): ?>
            <?php displayToastMessage(); ?>
        <?php endif; ?>

        <?php if (function_exists('renderPremiumHero')): ?>
            <?php renderPremiumHero([
                'eyebrow' => 'Asset Operations',
                'title' => 'Capital Asset Register',
                'subtitle' => 'Gain a live view of club-owned equipment, book values, and depreciation readiness.',
                'description' => 'Search, export, and manage assets with the same workspace experience as transactions.',
                'theme' => 'midnight',
                'size' => 'compact',
                'chips' => $assetHeroChips,
                'highlights' => $assetHeroHighlights,
                'actions' => $assetHeroActions,
                'media_mode' => 'none',
            ]); ?>
        <?php else: ?>
            <section class="hero hero-small mb-4">
                <div class="hero-body py-3">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-2 d-none d-md-flex justify-content-center">
                                <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                                <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                            </div>
                            <div class="col-md-6 text-center text-md-start text-white">
                                <h1 class="h4 mb-1">Capital Asset Register</h1>
                                <p class="mb-0 small">Track club equipment with values and aging.</p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <?php if ($canManageAssets): ?>
                                    <a href="add.php" class="btn btn-outline-light btn-sm me-2">
                                        <i class="fas fa-plus-circle me-1"></i>Add Asset
                                    </a>
                                <?php endif; ?>
                                <a href="/accounting/dashboard.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="container-fluid py-4">
            <div class="row g-4">
                <div class="col-lg-3">
                    <?php if (function_exists('accounting_render_workspace_nav')): ?>
                        <?php accounting_render_workspace_nav('assets', ['user_id' => $userId]); ?>
                    <?php endif; ?>
                </div>
                <div class="col-lg-9">
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
                        <div class="col">
                            <div class="border rounded p-3 h-100 bg-white shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small text-uppercase">Total Assets</span>
                                    <i class="fas fa-boxes text-primary"></i>
                                </div>
                                <h4 class="mb-0"><?= number_format($totalAssets); ?></h4>
                                <small class="text-muted">Filtered results</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-3 h-100 bg-white shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small text-uppercase">Book Value</span>
                                    <i class="fas fa-dollar-sign text-success"></i>
                                </div>
                                <h4 class="mb-0">$<?= number_format($totalValue, 2); ?></h4>
                                <small class="text-muted">Historical cost</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-3 h-100 bg-white shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small text-uppercase">Current Value</span>
                                    <i class="fas fa-chart-line text-warning"></i>
                                </div>
                                <h4 class="mb-0">$<?= number_format($totalCurrentValue, 2); ?></h4>
                                <small class="text-muted">After depreciation</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-3 h-100 bg-white shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small text-uppercase">Avg. Age</span>
                                    <i class="fas fa-hourglass-half text-secondary"></i>
                                </div>
                                <h4 class="mb-0"><?= $averageAge > 0 ? number_format($averageAge, 1) . ' yrs' : '—'; ?></h4>
                                <small class="text-muted">Across register</small>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">Filters</h5>
                                    <small class="text-muted">Search by asset name or description</small>
                                </div>
                                <?php if ($searchTerm !== ''): ?>
                                    <a href="list.php" class="btn btn-link text-decoration-none">Reset</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-9">
                                    <label class="form-label text-uppercase small text-muted">Keyword</label>
                                    <input type="search" name="search" class="form-control" placeholder="Radio, repeater, trailer..." value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Apply</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (empty($assets)): ?>
                        <div class="card shadow-sm border-0">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="mb-2">No assets match the current filters.</h5>
                                <p class="text-muted mb-3">Try adjusting your search or add a new asset to get started.</p>
                                <?php if ($canManageAssets): ?>
                                    <a href="add.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Asset</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1"><i class="fas fa-boxes text-primary me-2"></i>Asset Register</h5>
                                    <small class="text-muted"><?= number_format($totalAssets); ?> items · $<?= number_format($totalValue, 2); ?> book value</small>
                                </div>
                                <?php if ($canManageAssets): ?>
                                    <a href="add.php" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add Asset</a>
                                <?php endif; ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Acquired</th>
                                            <th>Book Value</th>
                                            <th>Current Value</th>
                                            <th>Depreciation</th>
                                            <th>Owner</th>
                                            <th style="width: 140px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assets as $asset): ?>
                                            <?php
                                                $acquired = $asset['acquisition_date'] ? date('M d, Y', strtotime($asset['acquisition_date'])) : '—';
                                                $currentValue = calculateAssetCurrentValue($asset);
                                                $deprRate = (float)($asset['depreciation_rate'] ?? 0);
                                                $ageYears = !empty($asset['acquisition_date']) ? calculateYearsSinceAcquisition($asset['acquisition_date']) : null;
                                                $descriptionPreview = trim($asset['description'] ?? '');
                                                if ($descriptionPreview !== '') {
                                                    if (function_exists('mb_strimwidth')) {
                                                        $descriptionPreview = mb_strimwidth($descriptionPreview, 0, 80, '…', 'UTF-8');
                                                    } elseif (strlen($descriptionPreview) > 80) {
                                                        $descriptionPreview = substr($descriptionPreview, 0, 77) . '...';
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold mb-1"><?= htmlspecialchars($asset['name'] ?? 'Unnamed Asset', ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <?php if ($descriptionPreview !== ''): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($descriptionPreview, ENT_QUOTES, 'UTF-8'); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">No description</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><?= $acquired; ?></div>
                                                    <small class="text-muted"><?= $ageYears ? number_format($ageYears, 1) . ' yrs' : 'Age unknown'; ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">$<?= number_format((float)($asset['value'] ?? 0), 2); ?></div>
                                                    <small class="text-muted">Original cost</small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">$<?= number_format($currentValue, 2); ?></div>
                                                    <small class="text-muted">Straight-line</small>
                                                </td>
                                                <td>
                                                    <?php if ($deprRate > 0): ?>
                                                        <span class="badge bg-light text-success border border-success"><?= number_format($deprRate, 2); ?>%</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border">n/a</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($asset['created_by_username'] ?? 'System', ENT_QUOTES, 'UTF-8'); ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="edit.php?id=<?= (int)$asset['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit Asset">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($canDeleteAssets): ?>
                                                            <form action="delete.php" method="POST" onsubmit="return confirm('Delete this asset? This action cannot be undone.');">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="hidden" name="id" value="<?= (int)$asset['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Asset">
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
