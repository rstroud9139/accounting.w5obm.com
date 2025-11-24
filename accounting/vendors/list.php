<?php

require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/vendor_controller.php';
require_once __DIR__ . '/../lib/helpers.php';

validate_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$status = $_GET['status'] ?? null;
$filters = [
    'search' => sanitizeInput($_GET['search'] ?? '', 'string'),
    'contact' => sanitizeInput($_GET['contact'] ?? 'all', 'string'),
    'sort' => sanitizeInput($_GET['sort'] ?? 'name_asc', 'string'),
];

$statusMessages = [
    'success' => ['success', 'Vendor Added', 'Vendor added successfully.', 'club-logo'],
    'updated' => ['success', 'Vendor Updated', 'Vendor updated successfully.', 'club-logo'],
    'deleted' => ['success', 'Vendor Removed', 'Vendor deleted successfully.', 'club-logo'],
    'error' => ['danger', 'Vendor Error', 'An error occurred. Please try again.', 'club-logo'],
];

if (!empty($status) && isset($statusMessages[$status])) {
    call_user_func_array('setToastMessage', $statusMessages[$status]);
}

$allVendors = [];
try {
    $allVendors = fetch_all_vendors();
} catch (Exception $e) {
    setToastMessage('danger', 'Vendors', 'Unable to load vendors: ' . $e->getMessage(), 'club-logo');
}

$vendors = array_values(array_filter($allVendors, static function ($vendor) use ($filters) {
    $matchesContact = true;
    if ($filters['contact'] === 'with_email') {
        $matchesContact = !empty($vendor['email']);
    } elseif ($filters['contact'] === 'with_phone') {
        $matchesContact = !empty($vendor['phone']);
    }

    $matchesSearch = true;
    if (!empty($filters['search'])) {
        $needle = strtolower($filters['search']);
        $haystack = strtolower(($vendor['name'] ?? '') . ' ' . ($vendor['contact_name'] ?? '') . ' ' . ($vendor['email'] ?? '') . ' ' . ($vendor['phone'] ?? ''));
        $matchesSearch = strpos($haystack, $needle) !== false;
    }

    return $matchesContact && $matchesSearch;
}));

usort($vendors, static function ($a, $b) use ($filters) {
    $sort = $filters['sort'] ?? 'name_asc';
    $nameA = strtolower($a['name'] ?? '');
    $nameB = strtolower($b['name'] ?? '');
    $comparison = strcmp($nameA, $nameB);
    return $sort === 'name_desc' ? -$comparison : $comparison;
});

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vendors_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Contact', 'Email', 'Phone', 'Category', 'Notes']);
    foreach ($vendors as $vendor) {
        fputcsv($output, [
            $vendor['name'] ?? '',
            $vendor['contact_name'] ?? '',
            $vendor['email'] ?? '',
            $vendor['phone'] ?? '',
            $vendor['category'] ?? '',
            $vendor['notes'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$stats = [
    'total' => count($allVendors),
    'filtered' => count($vendors),
    'withEmail' => count(array_filter($vendors, static fn($vendor) => !empty($vendor['email']))),
    'withPhone' => count(array_filter($vendors, static fn($vendor) => !empty($vendor['phone']))),
];

$page_title = 'Vendors Workspace - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <?php if (function_exists('displayToastMessage')) {
        displayToastMessage();
    } ?>

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
                            <h1 class="h4 mb-1">Vendor Relationships</h1>
                            <p class="mb-0 small">Centralize contacts, communications, and service partners.</p>
                        </div>
                        <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                            <a href="../dashboard.php" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-arrow-left me-1"></i>Back to Accounting
                            </a>
                            <a href="add.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus-circle me-1"></i>Add Vendor
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row mb-3 hero-summary-row">
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Active Vendors</span>
                        <i class="fas fa-handshake text-success"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($stats['total']) ?></h4>
                    <small class="text-muted">Total vendors on file</small>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Contactable (Email)</span>
                        <i class="fas fa-envelope text-primary"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($stats['withEmail']) ?></h4>
                    <small class="text-muted">Filtered list with valid email</small>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Filtered Results</span>
                        <i class="fas fa-filter text-warning"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($stats['filtered']) ?></h4>
                    <small class="text-muted">Matches your current filters</small>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Vendors</h5>
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
                        <p class="text-muted small mb-3">Focus on ready-to-contact vendors or clear everything quickly.</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm vendor-chip text-start" data-contact="with_email">Needs Email</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm vendor-chip text-start" data-contact="with_phone">Call List</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm vendor-chip text-start" data-sort="name_desc">Z-A Sort</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm vendor-chip text-start" data-clear="true">Reset Presets</button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="vendorFilterForm">
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <div class="col">
                                    <label for="contact" class="form-label text-muted text-uppercase small mb-1">Contact Method</label>
                                    <select id="contact" name="contact" class="form-select form-select-sm">
                                        <option value="all" <?= $filters['contact'] === 'all' ? 'selected' : '' ?>>All Vendors</option>
                                        <option value="with_email" <?= $filters['contact'] === 'with_email' ? 'selected' : '' ?>>Has Email</option>
                                        <option value="with_phone" <?= $filters['contact'] === 'with_phone' ? 'selected' : '' ?>>Has Phone</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="sort" class="form-label text-muted text-uppercase small mb-1">Sort By</label>
                                    <select id="sort" name="sort" class="form-select form-select-sm">
                                        <option value="name_asc" <?= $filters['sort'] === 'name_asc' ? 'selected' : '' ?>>Name (A 10Z)</option>
                                        <option value="name_desc" <?= $filters['sort'] === 'name_desc' ? 'selected' : '' ?>>Name (Z 10A)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 col-lg-8">
                                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                    <input type="text" id="search" name="search" class="form-control form-control-sm"
                                        placeholder="Name, contact, email" value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="vendorExportBtn">
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
                    <h4 class="mb-0"><i class="fas fa-handshake-angle me-2 text-primary"></i>Vendors
                        <small class="text-muted">(<?= number_format($stats['filtered']) ?>)</small>
                    </h4>
                    <small class="text-muted">Vendors that supply goods, services, and support</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus-circle me-1"></i>New Vendor
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($vendors)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-2">No vendors match the selected filters.</p>
                        <a href="list.php" class="btn btn-outline-primary">Reset Filters</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Vendor</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $vendor): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($vendor['name'] ?? 'Unnamed Vendor') ?></div>
                                            <?php if (!empty($vendor['category'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($vendor['category']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($vendor['contact_name'] ?? '—') ?></td>
                                        <td>
                                            <?php if (!empty($vendor['email'])): ?>
                                                <a href="mailto:<?= htmlspecialchars($vendor['email']) ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($vendor['email']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No email</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($vendor['phone'] ?? '—') ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="edit.php?id=<?= urlencode($vendor['id']) ?>" class="btn btn-outline-primary" title="Edit vendor">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this vendor?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($vendor['id']) ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete vendor">
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

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>