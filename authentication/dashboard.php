<?php

/**
 * Dashboard - W5OBM Amateur Radio Club
 * File: /authentication/dashboard.php
 * Purpose: Main user dashboard with working links and applications list
 * FIXED: Clean version following Website Guidelines with Toast messaging
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required includes per Website Guidelines
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';

// Error handling per Website Guidelines
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check
if (!isAuthenticated()) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

// Get current user ID
$current_user_id = getCurrentUserId();

if (!$current_user_id) {
    header('Location: login.php');
    exit();
}

// CSRF token generation per Website Guidelines
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$user_data = [];
$user_roles = [];
$recent_activities = [];
$system_stats = [];
$user_applications = [];
$is_admin = false;

try {
    // Use helper functions
    $is_admin = isAdmin($current_user_id);

    // Get user information (graceful if two_factor_enabled column is absent)
    $has_two_factor_col = false;
    try {
        if ($check = @$conn->query("SHOW COLUMNS FROM auth_users LIKE 'two_factor_enabled'")) {
            $has_two_factor_col = $check->num_rows > 0;
            $check->close();
        }
    } catch (Exception $e) {
        $has_two_factor_col = false;
    }

    $selectCols = "id, username, email, first_name, last_name, callsign, is_active, created_at, last_login, is_admin";
    if ($has_two_factor_col) {
        $selectCols .= ", two_factor_enabled";
    }

    $user_query = $conn->prepare("SELECT $selectCols FROM auth_users WHERE id = ? AND is_active = 1");

    if ($user_query) {
        $user_query->bind_param("i", $current_user_id);
        $user_query->execute();
        $user_result = $user_query->get_result();

        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            if ($user_data && !array_key_exists('two_factor_enabled', $user_data)) {
                $user_data['two_factor_enabled'] = false;
            }
        } else {
            // Use session data as fallback
            $user_data = [
                'id' => $_SESSION['user_id'] ?? $current_user_id,
                'username' => $_SESSION['username'] ?? 'unknown',
                'email' => $_SESSION['email'] ?? 'session_fallback@w5obm.com',
                'first_name' => $_SESSION['first_name'] ?? 'Session',
                'last_name' => $_SESSION['last_name'] ?? 'User',
                'callsign' => $_SESSION['callsign'] ?? '',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null,
                'two_factor_enabled' => false,
                'is_admin' => $_SESSION['is_admin'] ?? false
            ];
        }
        $user_query->close();
    }

    // Use helper functions
    $user_applications = getUserApplications($current_user_id);
    $user_roles = getUserRoles($current_user_id);

    // Get recent activity
    $activity_query = $conn->prepare("
        SELECT action, details, created_at, ip_address, success
        FROM auth_activity_log 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");

    if ($activity_query) {
        $activity_query->bind_param("i", $current_user_id);
        $activity_query->execute();
        $activity_result = $activity_query->get_result();
        while ($row = $activity_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
        $activity_query->close();
    }

    // Get system statistics if admin (guard against missing tables/columns)
    if ($is_admin) {
        // Defaults
        $system_stats['total_users'] = $system_stats['total_users'] ?? 0;
        $system_stats['recent_logins'] = $system_stats['recent_logins'] ?? 0;
        $system_stats['failed_attempts'] = $system_stats['failed_attempts'] ?? 0;
        $system_stats['pending_contacts'] = $system_stats['pending_contacts'] ?? 0;

        // Total active users (auth_users exists in this system)
        try {
            if ($res = $conn->query("SHOW TABLES LIKE 'auth_users'")) {
                if ($res->num_rows > 0) {
                    $res->close();
                    $q = $conn->query("SELECT COUNT(*) as count FROM auth_users WHERE is_active = 1");
                    if ($q) {
                        $system_stats['total_users'] = (int)$q->fetch_assoc()['count'];
                    }
                } else {
                    $res->close();
                }
            }
        } catch (Exception $e) { /* leave default */
        }

        // Activity log-based stats (table may not exist in all installs)
        try {
            $act_check = $conn->query("SHOW TABLES LIKE 'auth_activity_log'");
            if ($act_check && $act_check->num_rows > 0) {
                // Recent logins (last 24h)
                $q1 = $conn->query(
                    "SELECT COUNT(DISTINCT user_id) as count FROM auth_activity_log WHERE action = 'login_success' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                );
                if ($q1) {
                    $system_stats['recent_logins'] = (int)$q1->fetch_assoc()['count'];
                }

                // Failed attempts (last 24h)
                $q2 = $conn->query(
                    "SELECT COUNT(*) as count FROM auth_activity_log WHERE action LIKE '%login_failed%' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                );
                if ($q2) {
                    $system_stats['failed_attempts'] = (int)$q2->fetch_assoc()['count'];
                }
            }
        } catch (Exception $e) { /* leave defaults */
        }

        // Pending contacts (optional table)
        try {
            $contact_check = $conn->query("SHOW TABLES LIKE 'contactuslog'");
            if ($contact_check && $contact_check->num_rows > 0) {
                $pending_contacts_result = $conn->query(
                    "SELECT COUNT(*) as count FROM contactuslog WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
                );
                if ($pending_contacts_result) {
                    $system_stats['pending_contacts'] = (int)$pending_contacts_result->fetch_assoc()['count'];
                }
            }
        } catch (Exception $e) { /* leave default */
        }
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    setToastMessage('danger', 'Error', 'Dashboard data could not be loaded completely.', 'club-logo');

    // Better fallback data
    $user_data = [
        'id' => $_SESSION['user_id'] ?? $current_user_id,
        'username' => $_SESSION['username'] ?? 'unknown',
        'email' => $_SESSION['email'] ?? 'error_fallback@w5obm.com',
        'first_name' => $_SESSION['first_name'] ?? 'Error',
        'last_name' => $_SESSION['last_name'] ?? 'Fallback',
        'callsign' => $_SESSION['callsign'] ?? '',
        'status' => 'session_only',
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => null,
        'two_factor_enabled' => false,
        'is_Admin' => $_SESSION['is_admin'] ?? false
    ];
    // Preserve DB-verified admin if already true; otherwise use session flag
    if (!$is_admin) {
        $is_admin = $_SESSION['is_admin'] ?? false;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logActivity($current_user_id, 'logout', 'auth_activity_log', null, "Manual logout from dashboard");

    // Clean up remember token
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $delete_token = $conn->prepare("DELETE FROM auth_remember_tokens WHERE token_hash = ?");
        if ($delete_token) {
            $token_hash = hash('sha256', $token);
            $delete_token->bind_param("s", $token_hash);
            $delete_token->execute();
            $delete_token->close();
        }
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    session_destroy();
    header('Location: login.php');
    exit();
}

// Custom application loading logic
$is_super_admin = false;
foreach ($user_roles as $role) {
    if (strtolower($role['role_name']) === 'super admin') {
        $is_super_admin = true;
        break;
    }
}

// Unified applications retrieval using helper (now supports required_permission)
$user_applications = getUserApplications($current_user_id);

$page_title = "Dashboard - W5OBM Amateur Radio Club";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>
</head>

<body>
    <?php
    include __DIR__ . '/../include/menu.php';
    require_once __DIR__ . '/../include/club_header.php';
    renderSecondaryHeader('Dashboard');
    ?>

    <!-- Toast Container (Required for Bootstrap 5.3 Toast Messages) -->
    <div id="toastContainer" class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999;"></div>

    <!-- Toast Message Display -->
    <?php
    if (function_exists('displayToastMessage')) {
        displayToastMessage();
    }
    ?>

    <!-- Page Container per Website Guidelines -->
    <div class="page-container">

        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-tachometer-alt fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">W5OBM Dashboard</h3>
                        <small>Welcome back, <?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?>!</small>
                    </div>
                    <div class="col-auto">
                        <a href="../index.php" class="btn btn-light btn-sm">
                            <i class="fas fa-home me-1"></i>Main Site
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Information Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-user-circle me-2"></i>Your Account Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle text-primary me-2"></i>Personal Details</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><?= htmlspecialchars($user_data['username']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?= htmlspecialchars($user_data['email']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Callsign:</strong></td>
                                <td><?= htmlspecialchars($user_data['callsign'] ?: 'Not set') ?></td>
                            </tr>
                            <tr>
                                <td><strong>User ID:</strong></td>
                                <td><?= htmlspecialchars($user_data['id']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-clock text-primary me-2"></i>Account Status</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span class="badge bg-success"><?= ucfirst(htmlspecialchars($user_data['status'])) ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Member Since:</strong></td>
                                <td><?= date('F j, Y', strtotime($user_data['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Last Login:</strong></td>
                                <td><?= $user_data['last_login'] ? date('F j, Y g:i A', strtotime($user_data['last_login'])) : 'First login' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Roles:</strong></td>
                                <td>
                                    <?php if (!empty($user_roles)): ?>
                                        <?php foreach ($user_roles as $role): ?>
                                            <span class="badge bg-info me-1"><?= htmlspecialchars($role['role_name']) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Member</span>
                                    <?php endif; ?>
                                    <?php if ($is_admin): ?>
                                        <span class="badge bg-danger">Administrator</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>2FA Status:</strong></td>
                                <td>
                                    <?php if ($user_data['two_factor_enabled']): ?>
                                        <span class="badge bg-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Applications -->
        <?php if (!empty($user_applications)): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-th-large me-2"></i>Available Applications (<?= count($user_applications) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($user_applications as $app): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                <div class="card h-100 shadow-sm application-card">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <?php if (!empty($app['app_icon'])): ?>
                                                <i class="<?= htmlspecialchars($app['app_icon']) ?> fa-3x text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-cogs fa-3x text-primary"></i>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="card-title"><?= htmlspecialchars($app['app_name']) ?></h6>
                                        <p class="card-text small"><?= htmlspecialchars($app['description']) ?></p>
                                        <?php if (!empty($app['app_category'])): ?>
                                            <small class="text-muted d-block mb-2">
                                                <i class="fas fa-tag me-1"></i><?= htmlspecialchars($app['app_category']) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php
                                        // Build a safe, absolute href for application links
                                        $rawUrl = isset($app['app_url']) ? (string)$app['app_url'] : '#';
                                        $cleanUrl = preg_replace('/\s+/', '', $rawUrl); // strip any whitespace/newlines
                                        if ($cleanUrl === '' || $cleanUrl === '#') {
                                            $app_href = '#';
                                        } elseif (preg_match('#^https?://#i', $cleanUrl)) {
                                            $app_href = $cleanUrl;
                                        } else {
                                            // Treat as site-root relative path
                                            $path = $cleanUrl[0] === '/' ? $cleanUrl : ('/' . ltrim($cleanUrl, '/'));
                                            // Collapse relative segments
                                            $segments = [];
                                            foreach (explode('/', $path) as $seg) {
                                                if ($seg === '' || $seg === '.') continue;
                                                if ($seg === '..') {
                                                    array_pop($segments);
                                                    continue;
                                                }
                                                $segments[] = $seg;
                                            }
                                            $path = '/' . implode('/', $segments);
                                            $prefix = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                                            $app_href = $prefix . $path;
                                        }
                                        ?>
                                        <a href="<?= htmlspecialchars($app_href) ?>" class="btn btn-primary btn-lg shadow">
                                            <i class="fas fa-external-link-alt me-1"></i>Open
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Core Applications -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>Core Applications
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Profile Management -->
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="card h-100 shadow-sm application-card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-user-edit fa-3x text-info"></i>
                                </div>
                                <h6 class="card-title">Profile</h6>
                                <p class="card-text small">Edit your profile information</p>
                                <?php if ($is_admin): ?>
                                    <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') ?>administration/users/profile.php" class="btn btn-info btn-lg shadow">
                                    <?php else: ?>
                                        <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') ?>members/edit_profile.php" class="btn btn-info btn-lg shadow">
                                        <?php endif; ?>
                                        <i class="fas fa-edit me-1"></i>Edit Profile
                                        </a>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="card h-100 shadow-sm application-card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-key fa-3x text-warning"></i>
                                </div>
                                <h6 class="card-title">Password</h6>
                                <p class="card-text small">Change your password</p>
                                <a href="change_password.php" class="btn btn-warning btn-lg shadow">
                                    <i class="fas fa-key me-1"></i>Change Password
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Two-Factor Authentication -->
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="card h-100 shadow-sm application-card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-shield-alt fa-3x text-success"></i>
                                </div>
                                <h6 class="card-title">Two-Factor Auth</h6>
                                <p class="card-text small">
                                    <?= $user_data['two_factor_enabled'] ? 'Manage 2FA settings' : 'Enable 2FA security' ?>
                                </p>
                                <a href="2fa/two_factor_auth.php" class="btn btn-success btn-lg shadow">
                                    <i class="fas fa-mobile-alt me-1"></i>
                                    <?= $user_data['two_factor_enabled'] ? 'Manage 2FA' : 'Setup 2FA' ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Main Website -->
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div class="card h-100 shadow-sm application-card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-home fa-3x text-primary"></i>
                                </div>
                                <h6 class="card-title">Main Website</h6>
                                <p class="card-text small">Return to club website</p>
                                <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') ?>index.php" class="btn btn-primary btn-lg shadow">
                                    <i class="fas fa-home me-1"></i>Home Page
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Applications -->
        <?php if ($is_admin): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-users-cog me-2"></i>Administration
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Main Admin Dashboard -->
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card h-100 shadow-sm application-card">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-tachometer-alt fa-3x text-danger"></i>
                                    </div>
                                    <h6 class="card-title">Admin Dashboard</h6>
                                    <p class="card-text small">Main administration center</p>
                                    <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') ?>administration/dashboard.php" class="btn btn-danger btn-lg shadow">
                                        <i class="fas fa-cogs me-1"></i>Admin Center
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- User Management -->
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card h-100 shadow-sm application-card">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-users fa-3x text-info"></i>
                                    </div>
                                    <h6 class="card-title">User Management</h6>
                                    <p class="card-text small">Manage user accounts</p>
                                    <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') ?>administration/users/index.php" class="btn btn-info btn-lg shadow">
                                        <i class="fas fa-users me-1"></i>Manage Users
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- System Administration -->
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card h-100 shadow-sm application-card">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-server fa-3x text-secondary"></i>
                                    </div>
                                    <h6 class="card-title">System Admin</h6>
                                    <p class="card-text small">System configuration</p>
                                    <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') ?>administration/system/index.php" class="btn btn-secondary btn-lg shadow">
                                        <i class="fas fa-cogs me-1"></i>System Settings
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Security Logs -->
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card h-100 shadow-sm application-card">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-clipboard-list fa-3x text-warning"></i>
                                    </div>
                                    <h6 class="card-title">Audit Logs</h6>
                                    <p class="card-text small">View security and activity logs</p>
                                    <a href="<?= (defined('BASE_URL') ? BASE_URL : '/') ?>administration/misc/activity_audit_log.php" class="btn btn-warning btn-lg shadow">
                                        <i class="fas fa-list me-1"></i>View Logs
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Statistics -->
                    <?php if (!empty($system_stats)): ?>
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-number text-primary"><?= number_format($system_stats['total_users'] ?? 0) ?></div>
                                    <div class="stat-label">Total Users</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-number text-success"><?= number_format($system_stats['recent_logins'] ?? 0) ?></div>
                                    <div class="stat-label">Recent Logins (24h)</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-number text-danger"><?= number_format($system_stats['failed_attempts'] ?? 0) ?></div>
                                    <div class="stat-label">Failed Attempts (24h)</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-number text-warning"><?= number_format($system_stats['pending_contacts'] ?? 0) ?></div>
                                    <div class="stat-label">Contact Messages (30d)</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <?php if (!empty($recent_activities)): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Date/Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($activity['action']) ?></strong></td>
                                        <td><?= htmlspecialchars($activity['details'] ?: 'No details') ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?></td>
                                        <td>
                                            <?php if ($activity['success']): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Logout Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-sign-out-alt me-2"></i>Session Management
                </h5>
            </div>
            <div class="card-body text-center">
                <p class="mb-3">Session ID: <code><?= substr(session_id(), 0, 16) ?>...</code></p>
                <a href="dashboard.php?action=logout" class="btn btn-outline-danger btn-lg shadow"
                    onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <!-- Additional CSS per Website Guidelines -->
    <style>
        /* Application card styling */
        .application-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        }

        /* Stat card styling */
        .stat-card {
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--bs-primary);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Table styling */
        .table-borderless td {
            border: none;
            padding: 0.25rem 0.5rem;
        }

        .table-borderless td:first-child {
            width: 40%;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Make application cards clickable
            const appCards = document.querySelectorAll('.application-card');
            appCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on the button itself
                    if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a, button')) {
                        return;
                    }

                    // Find the link in the card and click it
                    const link = card.querySelector('a');
                    if (link) {
                        window.location.href = link.href;
                    }
                });
            });
        });
    </script>
</body>

</html>