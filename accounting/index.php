<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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
// Permission required for access; aligned with auth_applications configuration
$APP_PERMISSION = "admin.access";
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
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/lib/helpers.php';

$accountingNavHelper = __DIR__ . '/include/accounting_nav_helpers.php';
if (!file_exists($accountingNavHelper)) {
    $accountingNavHelper = __DIR__ . '/../include/accounting_nav_helpers.php';
}
require_once $accountingNavHelper;
require_once __DIR__ . '/../include/premium_hero.php';

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $query = http_build_query(array_merge(['route' => $name], $params));
        return '/accounting/app/index.php?' . $query;
    }
}

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
    header('Location: ' . $mainSiteBase . 'authentication/login.php?return_url=' . $returnUrl);
    exit();
}

// Resolve current user ID and permission state (with verbose debug)
$user_id = getCurrentUserId();
$is_auth = isAuthenticated();
$is_super = $user_id ? isSuperAdmin($user_id) : false;
$is_admin = $user_id ? isAdmin($user_id) : false;
$has_app_perm = $user_id ? hasPermission($user_id, $APP_PERMISSION) : false;

// Additionally, load role flags directly from auth_users to avoid any helper mismatch
$is_super_admin_db = false;
$is_admin_db = false;

try {
    global $conn; // from included dbconn.php
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT is_SuperAdmin, is_admin FROM auth_users WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $is_super_admin_db = !empty($row['is_SuperAdmin']);
            $is_admin_db       = !empty($row['is_admin']);
        }
    }
} catch (Exception $e) {
    // On any error, default to not-super/not-admin (safer)
    $is_super_admin_db = false;
    $is_admin_db = false;
}

// If somehow authenticated() is true but user_id is missing, treat as not logged in
if (!$user_id) {
    setToastMessage('info', 'Login Required', "Please login to access {$APP_NAME}.", 'club-logo');
    header('Location: ' . $mainSiteBase . 'authentication/login.php?return_url=' . $returnUrl);
    exit();
}

// Super/Admin gate using DB flags as the source of truth
//
// - Super Admins always allowed
// - Admins allowed
// - Otherwise require the configured permission (admin.access)
if (!$is_super_admin_db && !$is_admin_db && !$has_app_perm) {
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
accounting_head_assets();
?>

<body class="accounting-app bg-light">
    <?php accounting_render_nav(__DIR__); ?>

    <div class="page-container accounting-dashboard-shell">
        <div class="container mt-4">
        <!-- Compact hero / top bar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 mb-1"><i class="<?php echo $APP_ICON; ?> me-2 text-success"></i><?php echo $APP_NAME; ?></h2>
                <p class="text-muted mb-0 small"><?php echo $APP_DESCRIPTION; ?></p>
            </div>
            <div class="text-end">
                <a href="transactions/new.php" class="btn btn-success btn-sm me-2">
                    <i class="fas fa-plus-circle me-1"></i>New Transaction
                </a>
                <a href="reports/" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-chart-line me-1"></i>Quick Reports
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Activity & Maintenance Navigation -->
            <div class="col-md-3 mb-4">
                <nav class="bg-light border rounded h-100 p-0 shadow-sm">
                    <div class="px-3 py-2 border-bottom">
                        <span class="text-muted text-uppercase small">Workspace</span>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="transactions/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-exchange-alt me-2 text-primary"></i>Transactions</span>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </a>
                        <a href="reports/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-chart-bar me-2 text-success"></i>Reports</span>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </a>
                        <a href="assets/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-boxes me-2 text-info"></i>Assets</span>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </a>
                        <a href="donations/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-heart me-2 text-danger"></i>Donations</span>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </a>
                        <div class="list-group-item small text-muted text-uppercase">
                            Maintenance
                        </div>
                        <a href="maintenance/categories.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tags me-2"></i>Categories
                        </a>
                        <a href="maintenance/accounts.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-book me-2"></i>Chart of Accounts
                        </a>
                        <a href="maintenance/settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i>Accounting Settings
                        </a>
                    </div>
                    <div class="px-3 py-2 border-top text-center">
                        <a href="<?= $mainSiteBase ?>authentication/dashboard.php" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-arrow-left me-1"></i>Back to Site Dashboard
                        </a>
                    </div>
                </nav>
            </div>

            <!-- Right Column: Main Transactional / Reporting Area -->
            <div class="col-md-9 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Current Balance</span>
                                        <i class="fas fa-wallet text-success"></i>
                                    </div>
                                    <h4 class="mb-0">$0.00</h4>
                                    <small class="text-muted">Updated when transactions are posted</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">This Month - Income</span>
                                        <i class="fas fa-arrow-circle-down text-primary"></i>
                                    </div>
                                    <h4 class="mb-0">$0.00</h4>
                                    <small class="text-muted">Placeholder until reporting is wired</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">This Month - Expenses</span>
                                        <i class="fas fa-arrow-circle-up text-danger"></i>
                                    </div>
                                    <h4 class="mb-0">$0.00</h4>
                                    <small class="text-muted">Placeholder until reporting is wired</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="mb-3">Recent Activity</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">Description</th>
                                        <th scope="col" class="text-end">Amount</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-muted text-center py-4">
                                            No recent transactions to display yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>
</body>

</html>