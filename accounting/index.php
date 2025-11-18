<?php

/**
 * STANDARDIZED INDEX.PHP - ACCOUNTING FOLDER
 * File: /accounting/index.php
 * Purpose: Redirect to accounting dashboard
 * UPDATED: Using standardized template and consolidated helper functions
 */

// =============================================================================
// CONFIGURATION FOR ACCOUNTING FOLDER
// =============================================================================

$APP_NAME = "Accounting System";
$APP_DESCRIPTION = "Financial management and accounting";
$APP_PERMISSION = "app.accounting";
$APP_ICON = "fas fa-calculator";
$APP_FOLDER = "accounting";
$DASHBOARDS = ["dashboard.php"];

// =============================================================================
// STANDARD APPLICATION ROUTING LOGIC
// =============================================================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/lib/helpers.php';

// For current phase: always use DEV main site for authentication
// This keeps accounting.w5obm.com pointing at dev.w5obm.com auth
$mainSiteBase = 'https://dev.w5obm.com/';

// Build return URL so that, after login, control returns here and
// this script can continue with permission checks and routing.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'accounting.w5obm.com';
$selfPath = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/accounting/index.php'), '/');
$currentScript = $selfPath . '/index.php';
$returnUrl = urlencode($scheme . '://' . $host . $currentScript);

// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', "Please login to access {$APP_NAME}.", 'club-logo');
    header('Location: ' . $mainSiteBase . 'authentication/login.php?redirect=' . $returnUrl);
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions (Super Admin/Admin bypass)
if (!isSuperAdmin($user_id) && !isAdmin($user_id) && !hasPermission($user_id, $APP_PERMISSION)) {
    setToastMessage('danger', 'Access Denied', "You do not have permission to access {$APP_NAME}.", 'club-logo');
    header('Location: ' . $mainSiteBase . 'authentication/dashboard.php');
    exit();
}

// Log access
logActivity($user_id, "{$APP_FOLDER}_access", 'auth_activity_log', null, "Accessed {$APP_NAME}");

// Check for application dashboard
foreach ($DASHBOARDS as $dashboard) {
    if (file_exists(__DIR__ . '/' . $dashboard)) {
        header('Location: ' . $dashboard);
        exit();
    }
}

// If no dashboard found, show placeholder page
$page_title = "{$APP_NAME} - Club Accounting System";
include __DIR__ . '/../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <div class="container mt-4">
        <!-- Header Card per Website Guidelines -->
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="<?php echo $APP_ICON; ?> fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0"><?php echo $APP_NAME; ?></h3>
                        <small><?php echo $APP_DESCRIPTION; ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="alert alert-info shadow mb-4" role="alert">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="fas fa-info-circle fa-2x"></i>
                </div>
                <div class="col">
                    <h5 class="alert-heading mb-1">Dashboard Loading</h5>
                    <p class="mb-0">The accounting dashboard is loading. If this message persists, please contact support.</p>
                </div>
            </div>
        </div>

        <!-- Available Functions -->
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-exchange-alt fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Transactions</h5>
                        <p class="card-text text-muted">Manage income and expense transactions</p>
                        <a href="transactions/" class="btn btn-primary shadow">Access</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-bar fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Reports</h5>
                        <p class="card-text text-muted">Generate financial reports and analytics</p>
                        <a href="reports/" class="btn btn-success shadow">Access</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                        <h5 class="card-title">Assets</h5>
                        <p class="card-text text-muted">Track club assets and inventory</p>
                        <a href="assets/" class="btn btn-info shadow">Access</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-heart fa-3x text-danger mb-3"></i>
                        <h5 class="card-title">Donations</h5>
                        <p class="card-text text-muted">Manage donations and donor information</p>
                        <a href="donations/" class="btn btn-danger shadow">Access</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="card shadow">
            <div class="card-body text-center">
                <a href="<?= $mainSiteBase ?>authentication/dashboard.php" class="btn btn-outline-primary shadow">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>
</body>

</html>