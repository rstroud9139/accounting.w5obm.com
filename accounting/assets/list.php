<?php
// /accounting/assets/list.php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/asset_controller.php';
require_once __DIR__ . '/../lib/helpers.php';

validate_session();

// CSRF token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Assets - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>
    <div class="container mt-4">
        <div class="d-flex align-items-center mb-4">
            <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
            <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" width="120" height="120">
            <h2 class="ms-3 mb-0">Asset Inventory</h2>
            <div class="ms-auto">
                <a class="btn btn-dark" href="add.php"><i class="fas fa-plus me-1"></i>Add Asset</a>
                <a class="btn btn-outline-secondary" href="../dashboard.php">Back to Accounting</a>
            </div>
        </div>

        <?php
        // Fetch assets gracefully
        $assets = [];
        try {
            // Use legacy controller function matching current schema
            if (function_exists('fetch_all_assets')) {
                $assets = fetch_all_assets();
            } else if ($conn) {
                $q = "SELECT id, name, value, acquisition_date, depreciation_rate FROM acc_assets ORDER BY name";
                if ($res = $conn->query($q)) {
                    $assets = $res->fetch_all(MYSQLI_ASSOC);
                }
            }
        } catch (Exception $e) {
            $assets = [];
            setToastMessage('warning', 'Assets', 'Could not load assets list.');
        }
        ?>

        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Assets</h5>
            </div>
            <div class="card-body">
                <?php if (empty($assets)): ?>
                    <div class="alert alert-info mb-0">No assets found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th class="text-end">Value</th>
                                    <th>Acquired</th>
                                    <th>Depreciation %</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assets as $a): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($a['name'] ?? '') ?></td>
                                        <td class="text-end">$<?= number_format((float)($a['value'] ?? 0), 2) ?></td>
                                        <td><?= htmlspecialchars($a['acquisition_date'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($a['depreciation_rate'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars($a['status'] ?? '-') ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-primary" href="edit.php?id=<?= urlencode($a['id']) ?>">Edit</a>
                                            <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this asset?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($a['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
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