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

$mainSiteBase = defined('BASE_URL') ? BASE_URL : '/';
$mainSiteBase = rtrim($mainSiteBase, '/') . '/';

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $query = http_build_query(array_merge(['route' => $name], $params));
        return '/accounting/app/index.php?' . $query;
    }
}

// Determine where the authentication module lives. Prefer the local copy that now ships with
// accounting.w5obm.com; fall back to historic remote hosts only if those files are missing.
$authBase = '/authentication/';
$authFolder = realpath(__DIR__ . '/../authentication') ?: (__DIR__ . '/../authentication');
if (!is_dir($authFolder)) {
    $hostName = $_SERVER['HTTP_HOST'] ?? 'accounting.w5obm.com';
    if (stripos($hostName, 'dev.') !== false || stripos($hostName, 'localhost') !== false) {
        $authBase = 'https://dev.w5obm.com/authentication/';
    } else {
        $authBase = 'https://www.w5obm.com/authentication/';
    }
}

$loginEndpoint = rtrim($authBase, '/') . '/login.php';
$dashboardEndpoint = rtrim($authBase, '/') . '/dashboard.php';

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
    $loginTarget = $loginEndpoint . '?return_url=' . $returnUrl;
    header('Location: ' . $loginTarget);
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
    $loginTarget = $loginEndpoint . '?return_url=' . $returnUrl;
    header('Location: ' . $loginTarget);
    exit();
}

// Super/Admin gate using DB flags as the source of truth
//
// - Super Admins always allowed
// - Admins allowed
// - Otherwise require the configured permission (admin.access)
if (!$is_super_admin_db && !$is_admin_db && !$has_app_perm) {
    setToastMessage('danger', 'Access Denied', "You do not have permission to access {$APP_NAME}.", 'club-logo');
    header('Location: ' . $dashboardEndpoint);
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
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <div class="page-container accounting-dashboard-shell">
        <?php if (function_exists('renderPremiumHero')): ?>
            <?php
            $roleLabel = $is_super_admin_db ? 'Super Admin' : ($is_admin_db ? 'Admin' : 'Authorized');
            renderPremiumHero([
                'eyebrow' => 'Accounting Portal',
                'title' => 'Finance Operations HQ',
                'subtitle' => 'Launch into transactions, reporting, and stewardship workflows from one secure gateway.',
                'description' => 'Use the tiles below for quick navigation or jump straight into a workspace with the primary actions.',
                'theme' => 'midnight',
                'size' => 'compact',
                'chips' => [
                    'Secure role-based entry',
                    'Audit logging enabled',
                    'Modern UI kit'
                ],
                'highlights' => [
                    [
                        'label' => 'Primary Modules',
                        'value' => '4',
                        'meta' => 'Transactions · Reports · Assets · Donations'
                    ],
                    [
                        'label' => 'Security Role',
                        'value' => $roleLabel,
                        'meta' => 'Session verified'
                    ],
                    [
                        'label' => 'Today',
                        'value' => date('M d'),
                        'meta' => date('Y')
                    ],
                ],
                'actions' => [
                    [
                        'label' => 'Enter Transactions',
                        'url' => '/accounting/transactions/',
                        'icon' => 'fa-table'
                    ],
                    [
                        'label' => 'Open Reports',
                        'url' => '/accounting/reports_dashboard.php',
                        'variant' => 'outline',
                        'icon' => 'fa-chart-line'
                    ],
                    [
                        'label' => 'Asset Center',
                        'url' => '/accounting/assets/',
                        'variant' => 'outline',
                        'icon' => 'fa-boxes-stacked'
                    ],
                ],
                'media' => [
                    'type' => 'image',
                    'src' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1000&q=80',
                    'alt' => 'Finance team collaborating'
                ],
            ]);
            ?>
        <?php else: ?>
            <div class="bg-dark text-white py-4 shadow-sm">
                <div class="container">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <div class="mb-3 mb-md-0">
                            <h2 class="h4 mb-1"><i class="<?= $APP_ICON; ?> me-2 text-success"></i><?= htmlspecialchars($APP_NAME); ?></h2>
                            <p class="text-white-50 mb-0 small"><?= htmlspecialchars($APP_DESCRIPTION); ?></p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="/accounting/transactions/add.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus-circle me-1"></i>New Transaction
                            </a>
                            <a href="/accounting/reports/" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-chart-line me-1"></i>Quick Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="container mt-4">
            <div class="row">
                <!-- Left Column: Activity & Maintenance Navigation -->
                <div class="col-md-3 mb-4">
                    <nav class="bg-light border rounded h-100 p-0 shadow-sm">
                        <div class="px-3 py-2 border-bottom">
                            <span class="text-muted text-uppercase small">Workspace</span>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="/accounting/transactions/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-exchange-alt me-2 text-primary"></i>Transactions</span>
                                <i class="fas fa-chevron-right small text-muted"></i>
                            </a>
                            <a href="/accounting/reports/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-chart-bar me-2 text-success"></i>Reports</span>
                                <i class="fas fa-chevron-right small text-muted"></i>
                            </a>
                            <a href="/accounting/assets/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-boxes me-2 text-info"></i>Assets</span>
                                <i class="fas fa-chevron-right small text-muted"></i>
                            </a>
                            <a href="/accounting/budgets/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-wallet me-2 text-warning"></i>Budgets</span>
                                <i class="fas fa-chevron-right small text-muted"></i>
                            </a>
                            <a href="/accounting/donations/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-heart me-2 text-danger"></i>Donations</span>
                                <i class="fas fa-chevron-right small text-muted"></i>
                            </a>
                            <div class="list-group-item small text-muted text-uppercase">
                                Maintenance
                            </div>
                            <a href="/accounting/categories/" class="list-group-item list-group-item-action">
                                <i class="fas fa-tags me-2"></i>Categories
                            </a>
                            <a href="/accounting/ledger/" class="list-group-item list-group-item-action">
                                <i class="fas fa-book me-2"></i>Chart of Accounts
                            </a>
                            <a href="/accounting/admin/utilities.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog me-2"></i>Admin Utilities
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