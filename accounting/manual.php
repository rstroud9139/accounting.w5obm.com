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
    <title><?= $page_title ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>
    <style>
        .manual-section {
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <div class="page-container">
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