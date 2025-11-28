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
    <?php include_once __DIR__ . '/../../../include/premium_hero.php'; ?>
    <link rel="stylesheet" href="/accounting/app/assets/accounting.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../../../include/menu.php'; ?>

    <?php
    $currentRoute = $_GET['route'] ?? 'dashboard';
    $sessionUserId = function_exists('getCurrentUserId') ? getCurrentUserId() : ($_SESSION['user_id'] ?? '—');

    $routeNames = [
        'dashboard' => 'Command Center',
        'accounts' => 'Accounts Control',
        'account_register' => 'Register Explorer',
        'transactions' => 'Journal Workspace',
        'transaction_new' => 'New Journal Entry',
        'transaction_create' => 'Post Entry',
        'reconciliation' => 'Reconciliation Suite',
        'reconciliation_review' => 'Reconciliation Review',
        'reconciliation_commit' => 'Reconciliation Commit',
        'reconciliation_view' => 'Reconciliation Detail',
        'batch_reports' => 'Batch Reports',
        'batch_reports_run' => 'Run Batch Reports',
        'import' => 'Bank Import',
        'import_upload' => 'Upload Statement',
        'import_commit' => 'Commit Import',
        'import_last' => 'Last Import',
        'category_map' => 'Category Mapping',
        'category_map_save' => 'Save Category Mapping',
        'category_map_save_inline' => 'Inline Mapping Save',
        'migrations' => 'Migrations Suite',
        'migrations_run' => 'Run Migration',
    ];

    $friendlyRoute = $routeNames[$currentRoute] ?? ucwords(str_replace('_', ' ', $currentRoute));
    $heroTheme = in_array($currentRoute, ['accounts', 'account_register'], true) ? 'emerald' : (in_array($currentRoute, ['import', 'migrations', 'category_map'], true) ? 'cobalt' : 'midnight');

    if (function_exists('renderPremiumHero')) {
        renderPremiumHero([
            'eyebrow' => 'Accounting Application',
            'title' => $friendlyRoute,
            'subtitle' => 'Powered by the MVC micro-app, this workspace keeps controllers, services, and reports aligned.',
            'theme' => $heroTheme,
            'size' => 'compact',
            'media_mode' => 'none',
            'chips' => array_filter([
                'Route: ' . strtoupper(str_replace('-', ' ', $currentRoute)),
                $sessionUserId ? 'Session: #' . $sessionUserId : null,
            ]),
            'highlights' => [
                [
                    'label' => 'Nav Modules',
                    'value' => '6+',
                    'meta' => 'App router'
                ],
                [
                    'label' => 'UX Pattern',
                    'value' => 'Premium Hero',
                    'meta' => 'Standardized'
                ],
                [
                    'label' => 'Environment',
                    'value' => strtoupper($_ENV['APP_ENV'] ?? 'PROD'),
                    'meta' => 'Config aware'
                ],
            ],
            'actions' => [
                [
                    'label' => 'Accounting Dashboard',
                    'url' => '/accounting/dashboard.php',
                    'icon' => 'fa-chart-line'
                ],
                [
                    'label' => 'Reports Center',
                    'url' => '/accounting/reports_dashboard.php',
                    'variant' => 'outline',
                    'icon' => 'fa-chart-pie'
                ],
                [
                    'label' => 'Transactions Workspace',
                    'url' => '/accounting/transactions/transactions.php',
                    'variant' => 'outline',
                    'icon' => 'fa-table'
                ],
            ],
        ]);
    } else {
        ?>
        <section class="bg-dark text-white py-3 mb-3 shadow-sm">
            <div class="container">
                <h1 class="h4 mb-1">Accounting Application</h1>
                <p class="mb-0 text-white-50">Current route: <?= htmlspecialchars($friendlyRoute); ?></p>
            </div>
        </section>
        <?php
    }

    include __DIR__ . '/partials/accounting_nav.php';
    ?>

    <div class="container my-4">
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