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
    <link rel="stylesheet" href="/accounting/app/assets/accounting.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php
    $currentRoute = $_GET['route'] ?? 'dashboard';
    include __DIR__ . '/partials/accounting_nav.php';
    ?>

    <div class="container mt-4">
        <div class="print-header">
            <?php @include_once __DIR__ . '/../../../include/report_header.php';
            if (function_exists('renderReportHeader')) {
                renderReportHeader($page_title ?? 'Accounting Report');
            } ?>
        </div>
        <?= $content ?>
    </div>

    <?php include __DIR__ . '/../../../include/footer.php'; ?>
</body>

</html>