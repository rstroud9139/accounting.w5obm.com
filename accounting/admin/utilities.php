<?php

session_start();

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../../include/premium_hero.php';

if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access the accounting admin utilities.', 'fas fa-lock');
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();
if (!hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'Only accounting managers can run admin utilities.', 'fas fa-shield-alt');
    header('Location: /accounting/dashboard.php');
    exit();
}

$page_title = 'Accounting Admin Utilities - W5OBM';

$utilityLinks = [
    [
        'title' => 'Reset Accounting Data',
        'description' => 'Truncates transactional tables while optionally preserving the chart of accounts.',
        'url' => '../scripts/reset_accounting_data.php',
        'icon' => 'fa-broom',
        'impact' => 'Destructive'
    ],
    [
        'title' => 'Data Imports',
        'description' => 'Stage QuickBooks/gnuCash files, map accounts, and post staged batches.',
        'url' => '../imports.php',
        'icon' => 'fa-file-import',
        'impact' => 'Workflow'
    ],
    [
        'title' => 'Seed Chart & Categories',
        'description' => 'Reloads the industry-standard chart of accounts and synchronized category list.',
        'url' => '../scripts/seed_chart_and_categories.php',
        'icon' => 'fa-sitemap',
        'impact' => 'Destructive'
    ],
    [
        'title' => 'Manage Bank Links',
        'description' => 'Configure external bank connections and map them to ledger accounts.',
        'url' => 'bank_link.php',
        'icon' => 'fa-plug-circle-bolt',
        'impact' => 'Configuration'
    ],
];

$heroChips = [
    'Scope: accounting_manage',
    'Environment: Admin only',
];

$heroHighlights = [
    [
        'label' => 'Utility Scripts',
        'value' => number_format(count($utilityLinks)),
        'meta' => 'Available tools'
    ],
    [
        'label' => 'Risk Level',
        'value' => 'High',
        'meta' => 'Irreversible operations'
    ],
    [
        'label' => 'Session User',
        'value' => '#' . $user_id,
        'meta' => 'Authenticated'
    ],
];

$heroActions = [
    [
        'label' => 'Back to Dashboard',
        'url' => '/accounting/dashboard.php',
        'variant' => 'outline',
        'icon' => 'fa-arrow-left'
    ],
    [
        'label' => 'View Ledger',
        'url' => '/accounting/ledger/',
        'variant' => 'outline',
        'icon' => 'fa-book'
    ],
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="page-container accounting-admin-utilities-shell">
        <?php if (function_exists('displayToastMessage')): ?>
            <?php displayToastMessage(); ?>
        <?php endif; ?>

        <?php if (function_exists('renderPremiumHero')): ?>
            <?php renderPremiumHero([
                'eyebrow' => 'Admin Tools',
                'title' => 'Accounting Utilities Center',
                'subtitle' => 'Run high-trust maintenance scripts with clear visibility and guardrails.',
                'description' => 'Use these utilities only when migrating data, repairing corruption, or reseeding the ledger.',
                'theme' => 'midnight',
                'size' => 'compact',
                'media_mode' => 'none',
                'chips' => $heroChips,
                'highlights' => $heroHighlights,
                'actions' => $heroActions,
            ]); ?>
        <?php else: ?>
            <section class="bg-dark text-white py-4 mb-4 shadow-sm">
                <div class="container">
                    <h1 class="h4 mb-1">Accounting Admin Utilities</h1>
                    <p class="mb-0 text-white-50">Restricted to accounting managers</p>
                </div>
            </section>
        <?php endif; ?>

        <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex gap-3">
                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                <div>
                    <h5 class="alert-heading mb-1">Proceed with caution</h5>
                    <p class="mb-1">These utilities can delete or reseed data. Ensure you have a recent backup and that you understand the impact before running any script.</p>
                    <small class="text-muted">Tip: Perform changes during maintenance windows and notify leadership in advance.</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($utilityLinks as $utility): ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start mb-3">
                                <span class="me-3 text-primary"><i class="fas <?= htmlspecialchars($utility['icon']); ?> fa-2x"></i></span>
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($utility['title']); ?></h5>
                                    <p class="text-muted mb-0"><?= htmlspecialchars($utility['description']); ?></p>
                                </div>
                            </div>
                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <span class="badge bg-danger bg-opacity-10 text-danger"><?= htmlspecialchars($utility['impact']); ?></span>
                                <a href="<?= htmlspecialchars($utility['url']); ?>" class="btn btn-outline-danger btn-sm">
                                    Run Utility <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <h5 class="mb-2"><i class="fas fa-clipboard-list text-primary me-2"></i>Usage Guidance</h5>
                <ul class="mb-0 text-muted">
                    <li>Create a database backup before executing any destructive utility.</li>
                    <li>Run utilities only when other accounting users are logged out.</li>
                    <li>Capture screenshots or logs for audit history.</li>
                    <li>Report outcomes to leadership via the accounting activity log.</li>
                </ul>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>
