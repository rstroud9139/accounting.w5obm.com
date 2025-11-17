<?php
// Layout wrapper for accounting app views
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title ?? 'Accounting') ?></title>
    <?php include __DIR__ . '/../../../include/header.php'; ?>
    <link rel="stylesheet" href="/dev.w5obm.com/accounting/app/assets/accounting.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include __DIR__ . '/../../../include/menu.php'; ?>

    <div class="container mt-4">
        <div class="print-header">
            <?php @include_once __DIR__ . '/../../../include/report_header.php';
            if (function_exists('renderReportHeader')) {
                renderReportHeader($page_title ?? 'Accounting Report');
            } ?>
        </div>
        <!-- Accounting App Subnav -->
        <div class="mb-3">
            <div class="btn-group btn-group-sm no-print" role="group">
                <a class="btn btn-outline-primary" href="<?= route('dashboard') ?>"><i class="fas fa-home me-1"></i>Dashboard</a>
                <a class="btn btn-outline-primary" href="<?= route('accounts') ?>"><i class="fas fa-book me-1"></i>Accounts</a>
                <a class="btn btn-outline-primary" href="<?= route('reconciliation') ?>"><i class="fas fa-balance-scale me-1"></i>Reconciliation</a>
                <a class="btn btn-outline-primary" href="<?= route('transactions') ?>"><i class="fas fa-list me-1"></i>Transactions</a>
                <a class="btn btn-outline-primary" href="<?= route('import') ?>"><i class="fas fa-file-import me-1"></i>Import</a>
                <a class="btn btn-outline-secondary" href="<?= route('import_last') ?>"><i class="fas fa-history me-1"></i>Last Import</a>
                <a class="btn btn-outline-primary" href="<?= route('category_map') ?>"><i class="fas fa-link me-1"></i>Category Mapping</a>
                <a class="btn btn-outline-primary" href="<?= route('batch_reports') ?>"><i class="fas fa-layer-group me-1"></i>Batch Reports</a>
                <a class="btn btn-outline-primary" href="<?= route('migrations') ?>"><i class="fas fa-database me-1"></i>Migrations</a>
                <a class="btn btn-outline-secondary" href="/accounting/reports/reports_dashboard.php"><i class="fas fa-chart-pie me-1"></i>Reports</a>
            </div>
        </div>
        <?= $content ?>
    </div>

    <?php include __DIR__ . '/../../../include/footer.php'; ?>
</body>

</html>