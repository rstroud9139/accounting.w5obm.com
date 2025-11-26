<?php

/**
 * Accounting User Manual (Placeholder)
 * File: /accounting/manual.php
 * Purpose: Provide basic guidance and link to guidelines
 */

// Start session and include common files
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/../include/premium_hero.php';

// Auth check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$page_title = 'Accounting Manual - W5OBM';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>
    <style>
        .manual-section {
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <div class="page-container accounting-dashboard-shell">
        <?php if (function_exists('renderPremiumHero')): ?>
            <?php renderPremiumHero([
                'eyebrow' => 'Accounting Guide',
                'title' => 'Operations Manual',
                'subtitle' => 'Reference the workflows, permissions, and safety rails that keep the finance stack aligned.',
                'description' => 'Use the quick links and sections below to refresh process knowledge before training new treasurers or assistants.',
                'theme' => 'emerald',
                'size' => 'compact',
                'chips' => [
                    'Transactions lifecycle',
                    'Categories & ledger',
                    'Assets & compliance'
                ],
                'highlights' => [
                    [
                        'label' => 'Core Sections',
                        'value' => '5',
                        'meta' => 'Process overviews'
                    ],
                    [
                        'label' => 'Guides Linked',
                        'value' => '12+',
                        'meta' => 'Docs & SOPs'
                    ],
                    [
                        'label' => 'Updated',
                        'value' => date('M Y'),
                        'meta' => 'Latest pass'
                    ],
                ],
                'actions' => [
                    [
                        'label' => 'Design Guidelines',
                        'url' => '/accounting/MODERN_WEBSITE_DESIGN_GUIDELINES.md',
                        'variant' => 'outline',
                        'icon' => 'fa-drafting-compass'
                    ],
                    [
                        'label' => 'Dashboard',
                        'url' => '/accounting/dashboard.php',
                        'variant' => 'outline',
                        'icon' => 'fa-arrow-left'
                    ],
                ],
                'media_mode' => 'none',
            ]); ?>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header bg-secondary text-white">
                <div class="row align-items-center">
                    <div class="col-auto"><i class="fas fa-book fa-2x"></i></div>
                    <div class="col">
                        <h3 class="mb-0">Accounting User Manual</h3><small>Quick reference and resources</small>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="manual-section">
                    <h5>Overview</h5>
                    <p>Use Transactions to add income and expenses, Categories to manage transaction groupings, Ledger for accounts, Assets to track physical assets, and Reports for summaries.</p>
                </div>
                <div class="manual-section">
                    <h5>Common Actions</h5>
                    <ul>
                        <li>Add a transaction: Accounting → Transactions → Add</li>
                        <li>View transactions: Accounting → Transactions → View</li>
                        <li>Manage categories: Accounting → Categories</li>
                        <li>Chart of accounts: Accounting → Ledger</li>
                        <li>Assets: Accounting → Assets</li>
                    </ul>
                </div>
                <div class="manual-section">
                    <h5>Design Guidelines</h5>
                    <p>See the Modern Website Design Guidelines: <a href="/accounting/Website%20Development%20Guidelines.md" target="_blank">Website Development Guidelines.md</a></p>
                </div>
                <div class="text-end">
                    <a href="/accounting/dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>
</body>

</html>