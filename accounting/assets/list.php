<?php
// /accounting/assets/list.php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/asset_controller.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../../include/premium_hero.php';

validate_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$filters = [
    'search' => sanitizeInput($_GET['search'] ?? '', 'string'),
    'status' => sanitizeInput($_GET['status'] ?? 'all', 'string'),
    'sort' => sanitizeInput($_GET['sort'] ?? 'name_asc', 'string'),
];

$allowedSorts = ['name_asc', 'name_desc', 'value_desc', 'value_asc', 'acquired_newest', 'acquired_oldest'];
if (!in_array($filters['sort'], $allowedSorts, true)) {
    $filters['sort'] = 'name_asc';
}

// Fetch assets gracefully
$allAssets = [];
try {
    if (function_exists('fetch_all_assets')) {
        $allAssets = fetch_all_assets();
    } elseif ($conn) {
        $q = "SELECT id, name, value, acquisition_date, depreciation_rate, status FROM acc_assets ORDER BY name";
        if ($res = $conn->query($q)) {
            $allAssets = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (Exception $e) {
    $allAssets = [];
    setToastMessage('warning', 'Assets', 'Could not load assets list.');
}

$availableStatuses = array_values(array_unique(array_filter(array_map(static function ($asset) {
    return $asset['status'] ?? '';
}, $allAssets))));

if ($filters['status'] !== 'all' && !in_array($filters['status'], $availableStatuses, true)) {
    $availableStatuses[] = $filters['status'];
}

sort($availableStatuses);

$assets = array_values(array_filter($allAssets, static function ($asset) use ($filters) {
    $matchesStatus = $filters['status'] === 'all' || strcasecmp($asset['status'] ?? '', $filters['status']) === 0;
    $matchesSearch = true;
    if (!empty($filters['search'])) {
        $needle = strtolower($filters['search']);
        $haystack = strtolower(($asset['name'] ?? '') . ' ' . ($asset['description'] ?? '') . ' ' . ($asset['status'] ?? ''));
        $matchesSearch = strpos($haystack, $needle) !== false;
    }
    return $matchesStatus && $matchesSearch;
}));

if (!empty($assets)) {
    usort($assets, static function ($a, $b) use ($filters) {
        $sort = $filters['sort'];
        switch ($sort) {
            case 'name_desc':
                return strcasecmp($b['name'] ?? '', $a['name'] ?? '');
            case 'value_desc':
                return (float)($b['value'] ?? 0) <=> (float)($a['value'] ?? 0);
            case 'value_asc':
                return (float)($a['value'] ?? 0) <=> (float)($b['value'] ?? 0);
            case 'acquired_newest':
                return strtotime($b['acquisition_date'] ?? '1970-01-01') <=> strtotime($a['acquisition_date'] ?? '1970-01-01');
            case 'acquired_oldest':
                return strtotime($a['acquisition_date'] ?? '1970-01-01') <=> strtotime($b['acquisition_date'] ?? '1970-01-01');
            case 'name_asc':
            default:
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        }
    });
}

$summaryTotals = [
    'count' => count($assets),
    'value' => array_sum(array_map(static fn($asset) => (float)($asset['value'] ?? 0), $assets)),
    'depreciating' => count(array_filter($assets, static fn($asset) => (float)($asset['depreciation_rate'] ?? 0) > 0)),
];

if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assets_export_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Value', 'Acquired', 'Depreciation %', 'Status']);
    foreach ($assets as $asset) {
        fputcsv($output, [
            $asset['name'] ?? 'Unnamed Asset',
            (float)($asset['value'] ?? 0),
            $asset['acquisition_date'] ?? '',
            (float)($asset['depreciation_rate'] ?? 0),
            $asset['status'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$presetStatuses = array_slice($availableStatuses, 0, 3);

$sortLabels = [
    'name_asc' => 'Name A→Z',
    'name_desc' => 'Name Z→A',
    'value_desc' => 'Value High→Low',
    'value_asc' => 'Value Low→High',
    'acquired_newest' => 'Newest First',
    'acquired_oldest' => 'Oldest First'
];
$currentSortLabel = $sortLabels[$filters['sort']] ?? strtoupper($filters['sort']);

$assetHeroHighlights = [
    [
        'label' => 'Tracked Assets',
        'value' => number_format($summaryTotals['count']),
        'meta' => 'Inventory records'
    ],
    [
        'label' => 'Total Value',
        'value' => '$' . number_format($summaryTotals['value'], 2),
        'meta' => 'Combined replacement cost'
    ],
    [
        'label' => 'Depreciating',
        'value' => number_format($summaryTotals['depreciating']),
        'meta' => 'Active schedules'
    ],
];

$assetHeroChips = array_values(array_filter([
    !empty($filters['search']) ? 'Search: ' . $filters['search'] : null,
    ($filters['status'] ?? 'all') !== 'all' ? 'Status: ' . ucwords($filters['status']) : 'All statuses',
    'Sort: ' . $currentSortLabel,
]));

$exportQuery = [
    'search' => $filters['search'] ?? null,
    'status' => $filters['status'] ?? 'all',
    'sort' => $filters['sort'] ?? 'name_asc',
    'export' => '1',
];
$assetHeroActions = [
    [
        'label' => 'Add Asset',
        'url' => '/accounting/assets/add.php',
        'icon' => 'fa-plus'
    ],
    [
        'label' => 'Export CSV',
        'url' => '/accounting/assets/list.php?' . http_build_query(array_filter($exportQuery, static function ($value) {
            return $value !== null && $value !== '';
        })),
        'variant' => 'outline',
        'icon' => 'fa-file-export'
    ],
    [
        'label' => 'Back to Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left'
    ],
];

?>

<?php
$page_title = 'Assets - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <?php if (function_exists('renderPremiumHero')): ?>
        <?php renderPremiumHero([
            'eyebrow' => 'Asset Operations',
            'title' => 'Asset Inventory',
            'subtitle' => 'Track equipment value, depreciation, and readiness in one place.',
            'chips' => $assetHeroChips,
            'highlights' => $assetHeroHighlights,
            'actions' => $assetHeroActions,
            'theme' => 'cobalt',
            'size' => 'compact',
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
                            <h1 class="h4 mb-1">Asset Inventory</h1>
                            <p class="mb-0 small">Track equipment value, depreciation, and lifecycle readiness</p>
                        </div>
                        <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                            <a class="btn btn-outline-light btn-sm me-2" href="../dashboard.php">
                                <i class="fas fa-arrow-left me-1"></i>Back to Accounting
                            </a>
                            <a class="btn btn-primary btn-sm" href="add.php">
                                <i class="fas fa-plus-circle me-1"></i>Add Asset
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">

        <div class="row mb-3 hero-summary-row">
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Total Asset Value</span>
                        <i class="fas fa-coins text-warning"></i>
                    </div>
                    <h4 class="mb-0">$<?= number_format($summaryTotals['value'], 2) ?></h4>
                    <small class="text-muted">Across <?= number_format($summaryTotals['count']) ?> tracked assets</small>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Depreciating Assets</span>
                        <i class="fas fa-chart-line text-success"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($summaryTotals['depreciating']) ?></h4>
                    <small class="text-muted">Active depreciation schedules</small>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Filter Results</span>
                        <i class="fas fa-filter text-primary"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($summaryTotals['count']) ?></h4>
                    <small class="text-muted">Assets shown with current filters</small>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Assets</h5>
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
                        <p class="text-muted small mb-3">Jump to common asset groupings or clear filters quickly.</p>
                        <div class="d-grid gap-2">
                            <?php if (!empty($presetStatuses)): ?>
                                <?php foreach ($presetStatuses as $status): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm asset-chip text-start" data-status="<?= htmlspecialchars($status) ?>">
                                        <span class="fw-semibold"><?= htmlspecialchars(ucwords($status)) ?></span>
                                        <small class="d-block text-muted">Status focus</small>
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info small mb-0">Add status labels to unlock quick presets.</div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm asset-chip text-start" data-sort="value_desc">
                                <span class="fw-semibold">Highest Value First</span>
                                <small class="d-block text-muted">Sort by asset value</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm asset-chip text-start" data-sort="acquired_newest">
                                <span class="fw-semibold">Newest Additions</span>
                                <small class="d-block text-muted">Recent acquisitions</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm asset-chip text-start" data-clear="true">
                                <span class="fw-semibold">Clear Presets</span>
                                <small class="d-block text-muted">Reset status, sort, keyword</small>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="assetFilterForm">
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <div class="col">
                                    <label for="status" class="form-label text-muted text-uppercase small mb-1">Status</label>
                                    <select id="status" name="status" class="form-select form-select-sm">
                                        <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                        <?php foreach ($availableStatuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucwords($status)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="sort" class="form-label text-muted text-uppercase small mb-1">Sort By</label>
                                    <select id="sort" name="sort" class="form-select form-select-sm">
                                        <option value="name_asc" <?= $filters['sort'] === 'name_asc' ? 'selected' : '' ?>>Name (A to Z)</option>
                                        <option value="name_desc" <?= $filters['sort'] === 'name_desc' ? 'selected' : '' ?>>Name (Z to A)</option>
                                        <option value="value_desc" <?= $filters['sort'] === 'value_desc' ? 'selected' : '' ?>>Value (High to Low)</option>
                                        <option value="value_asc" <?= $filters['sort'] === 'value_asc' ? 'selected' : '' ?>>Value (Low to High)</option>
                                        <option value="acquired_newest" <?= $filters['sort'] === 'acquired_newest' ? 'selected' : '' ?>>Newest Acquisition</option>
                                        <option value="acquired_oldest" <?= $filters['sort'] === 'acquired_oldest' ? 'selected' : '' ?>>Oldest Acquisition</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 col-lg-8">
                                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                    <input type="text" id="search" name="search" class="form-control form-control-sm"
                                        placeholder="Name, description, status" value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="assetExportBtn">
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
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="fas fa-boxes-stacked me-2 text-primary"></i>Assets
                        <small class="text-muted">(<?= number_format($summaryTotals['count']) ?>)</small>
                    </h4>
                    <small class="text-muted">Manage inventory value and lifecycle</small>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-primary btn-sm" href="add.php">
                        <i class="fas fa-plus-circle me-1"></i>New Asset
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($assets)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-2">No assets match the selected filters.</p>
                        <a href="list.php" class="btn btn-outline-primary">Reset Filters</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-end">Value</th>
                                    <th>Acquired</th>
                                    <th class="text-center">Depreciation %</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assets as $asset): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($asset['name'] ?? 'Unnamed Asset') ?></div>
                                            <?php if (!empty($asset['description'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($asset['description']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">$<?= number_format((float)($asset['value'] ?? 0), 2) ?></td>
                                        <td><?= htmlspecialchars($asset['acquisition_date'] ?? '—') ?></td>
                                        <td class="text-center">
                                            <?php $rate = (float)($asset['depreciation_rate'] ?? 0); ?>
                                            <span class="badge bg-<?= $rate > 0 ? 'success' : 'secondary' ?> bg-opacity-25 text-<?= $rate > 0 ? 'success' : 'secondary' ?>">
                                                <?= $rate > 0 ? number_format($rate, 2) . '%' : 'None' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                                <?= htmlspecialchars($asset['status'] ?? 'Active') ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a class="btn btn-outline-primary" href="edit.php?id=<?= urlencode($asset['id']) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this asset?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($asset['id']) ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('assetFilterForm');
            const statusSelect = document.getElementById('status');
            const sortSelect = document.getElementById('sort');
            const searchInput = document.getElementById('search');
            const exportBtn = document.getElementById('assetExportBtn');
            const presetButtons = document.querySelectorAll('.asset-chip');

            presetButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.dataset.clear === 'true') {
                        if (statusSelect) {
                            statusSelect.value = 'all';
                        }
                        if (sortSelect) {
                            sortSelect.value = 'name_asc';
                        }
                        if (searchInput) {
                            searchInput.value = '';
                        }
                        if (form && typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else if (form) {
                            form.submit();
                        }
                        return;
                    }

                    if (btn.dataset.status && statusSelect) {
                        statusSelect.value = btn.dataset.status;
                    }

                    if (btn.dataset.sort && sortSelect) {
                        sortSelect.value = btn.dataset.sort;
                    }

                    if (form && typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else if (form) {
                        form.submit();
                    }
                });
            });

            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    if (!form) {
                        return;
                    }
                    const formData = new FormData(form);
                    const url = new URL(window.location.href);
                    formData.forEach((value, key) => {
                        url.searchParams.set(key, value);
                    });
                    url.searchParams.set('export', '1');
                    window.location.href = url.toString();
                });
            }
        });
    </script>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>