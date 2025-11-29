<?php

/** @var mysqli $conn */
require_once __DIR__ . '/dbconn.php';
require_once __DIR__ . '/remember_me.php';

/**
 * COMPLETE Helper Functions for W5OBM - WITH SUPER ADMIN HIERARCHY
 * File: /include/helper_functions.php
 * Purpose: ALL authentication and utility functions in one place
 * Total Functions: 56 + PerformanceTimer class
 * UPDATED: Added Super Admin hierarchy with inherent priority
 */

// Define BASE_URL constant if not already defined
if (!defined("BASE_URL")) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Environment detection based on domain name and file path
    if ($host === 'localhost' && strpos(__DIR__, 'dev.w5obm.com') !== false) {
        // Local development in dev folder
        define("BASE_URL", 'http://localhost/w5obmcom_admin/dev.w5obm.com/');
    } elseif ($host === 'localhost') {
        // Local development in production folder
        define("BASE_URL", 'http://localhost/w5obmcom_admin/w5obm.com/');
    } elseif (strpos($host, 'dev.w5obm.com') !== false) {
        // Development server
        define("BASE_URL", '/');
    } else {
        // Production server
        define("BASE_URL", '/');
    }
}

// --- Add logError function if not defined ---
if (!function_exists('logError')) {
    function logError($msg, $context = 'general')
    {
        error_log("[$context] $msg");
    }
}

// ================================================================================================
// SUPER ADMIN HIERARCHY FUNCTIONS (New priority functions)
// ================================================================================================

function isSuperAdmin($user_id = null)
{
    global $conn;
    /** @var mysqli $conn */
    if ($user_id === null) {
        if (!isAuthenticated()) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
        // Check session cache first
        if (isset($_SESSION['is_super_admin'])) {
            return (bool)$_SESSION['is_super_admin'];
        }
    }

    $is_super = false;
    try {
        // Detect available columns
        $has_super_col = false;
        $has_role_col = false;
        $has_status_col = false;
        $has_active_col = false;
        if ($conn) {
            if ($res = @$conn->query("SHOW COLUMNS FROM auth_users LIKE 'is_SuperAdmin'")) {
                $has_super_col = $res->num_rows > 0;
                $res->close();
            }
            if ($res = @$conn->query("SHOW COLUMNS FROM auth_users LIKE 'role_id'")) {
                $has_role_col = $res->num_rows > 0;
                $res->close();
            }
            if ($res = @$conn->query("SHOW COLUMNS FROM auth_users LIKE 'status'")) {
                $has_status_col = $res->num_rows > 0;
                $res->close();
            }
            if ($res = @$conn->query("SHOW COLUMNS FROM auth_users LIKE 'is_active'")) {
                $has_active_col = $res->num_rows > 0;
                $res->close();
            }

            if ($has_super_col) {
                $where = '';
                if ($has_status_col || $has_active_col) {
                    $pred = [];
                    if ($has_status_col) $pred[] = "status='active'";
                    if ($has_active_col) $pred[] = "is_active=1";
                    $where = ' AND (' . implode(' OR ', $pred) . ')';
                }
                $stmt = $conn->prepare("SELECT is_SuperAdmin, username, callsign FROM auth_users WHERE id = ?" . $where);
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $is_super = $row ? (bool)($row['is_SuperAdmin'] ?? false) : false;
                // Explicit override for key account regardless of column state (match username or callsign)
                $u = strtoupper(trim($row['username'] ?? ''));
                $c = strtoupper(trim($row['callsign'] ?? ''));
                if (!$is_super && ($u === 'KD5BS' || $c === 'KD5BS')) {
                    $is_super = true;
                }
            } else {
                // Fallback: if super admin not implemented, treat admin as super admin
                // Prefer role_id = 1 when available, otherwise is_Admin flag
                $stmt = $conn->prepare("SELECT role_id, is_Admin, username, callsign FROM auth_users WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $role_is_admin = $has_role_col ? ((int)$row['role_id'] === 1) : false;
                    $flag_is_admin = isset($row['is_Admin']) ? (bool)$row['is_Admin'] : false;
                    $is_super = $role_is_admin || $flag_is_admin;
                    // Optional explicit override for key accounts
                    $u = strtoupper(trim($row['username'] ?? ''));
                    $c = strtoupper(trim($row['callsign'] ?? ''));
                    if ($u === 'KD5BS' || $c === 'KD5BS') {
                        $is_super = true;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error checking super admin status: " . $e->getMessage());
    }

    // Cache in session if checking current user
    if ($user_id === ($_SESSION['user_id'] ?? null)) {
        $_SESSION['is_super_admin'] = $is_super;
    }

    return $is_super;
}

function getUserPrivilegeLevel($user_id)
{
    if (isSuperAdmin($user_id)) {
        return 'Super Administrator';
    }
    if (isAdmin($user_id)) {
        return 'Administrator';
    }
    return 'User';
}

function canAccessFunctionality($user_id, $functionality)
{
    if (isSuperAdmin($user_id)) {
        return true;
    }

    $admin_only = [
        'user_management',
        'system_settings',
        'database_backup',
        'security_logs',
        'notification_system'
    ];

    if (in_array($functionality, $admin_only)) {
        return isAdmin($user_id);
    }

    return hasPermission($user_id, $functionality);
}

function getUsersByPrivilegeLevel($level = 'all')
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $where_clause = "WHERE status = 'active'";

        switch ($level) {
            case 'super_admin':
                $where_clause .= " AND is_SuperAdmin = 1";
                break;
            case 'admin':
                $where_clause .= " AND is_Admin = 1 AND is_SuperAdmin = 0";
                break;
            case 'user':
                $where_clause .= " AND is_Admin = 0 AND is_SuperAdmin = 0";
                break;
        }

        $stmt = $conn->prepare("
            SELECT id, username, callsign, first_name, last_name, email, 
                   is_Admin, is_SuperAdmin, last_login, created_at,
                   CASE 
                       WHEN is_SuperAdmin = 1 THEN 'Super Administrator'
                       WHEN is_Admin = 1 THEN 'Administrator'
                       ELSE 'User'
                   END as privilege_level
            FROM auth_users 
            $where_clause
            ORDER BY is_SuperAdmin DESC, is_Admin DESC, username
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        return $users;
    } catch (Exception $e) {
        error_log("Error getting users by privilege level: " . $e->getMessage());
        return [];
    }
}

// ================================================================================================
// CORE AUTHENTICATION FUNCTIONS (Updated with Super Admin hierarchy)
// ================================================================================================

function isAuthenticated()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Honor a forced logout marker cookie set by server on logout
    if (isset($_COOKIE['force_logout'])) {
        // Expire the marker immediately
        @setcookie('force_logout', '', time() - 3600, '/', '', false, false);
        @setcookie('force_logout', '', time() - 3600, '/', '', true, false);
        // Clear auth data and deny access
        unset(
            $_SESSION['user_id'],
            $_SESSION['username'],
            $_SESSION['authenticated'],
            $_SESSION['is_admin'],
            $_SESSION['role'],
            $_SESSION['login_time'],
            $_SESSION['last_activity'],
            $_SESSION['timeout']
        );
        return false;
    }

    // Check basic authentication markers
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        if (!function_exists('rememberMeAutoLogin') || !rememberMeAutoLogin()) {
            return false;
        }
    }

    // Check session timeout if available
    if (isset($_SESSION['timeout']) && $_SESSION['timeout'] < time()) {
        // Session expired - clear authentication data
        unset(
            $_SESSION['user_id'],
            $_SESSION['username'],
            $_SESSION['authenticated'],
            $_SESSION['is_admin'],
            $_SESSION['role'],
            $_SESSION['login_time'],
            $_SESSION['last_activity'],
            $_SESSION['timeout']
        );
        return false;
    }

    // Sliding idle timeout: extend when user is active (page hits)
    try {
        $IDLE_WINDOW = 1800; // 30 minutes
        $now = time();
        // If no timeout set yet but user is authenticated, initialize it
        if (!isset($_SESSION['timeout']) || $_SESSION['timeout'] <= $now) {
            $_SESSION['timeout'] = $now + $IDLE_WINDOW;
        } else {
            // Extend the timeout on activity
            $_SESSION['timeout'] = $now + $IDLE_WINDOW;
        }
        $_SESSION['last_activity'] = $now;
    } catch (Exception $e) {
        // Ignore extension errors
    }

    // Check authenticated flag if available
    if (isset($_SESSION['authenticated']) && !$_SESSION['authenticated']) {
        return false;
    }

    // Session fingerprint: bind session to UA + IP prefix to reduce reuse risk
    try {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '');

        $ipKey = $ip;
        if ($ip) {
            if (strpos($ip, ':') !== false) {
                // IPv6: first 2 segments
                $parts = explode(':', $ip);
                $ipKey = implode(':', array_slice($parts, 0, 2));
            } else {
                // IPv4: first 2 octets
                $parts = explode('.', $ip);
                if (count($parts) >= 2) {
                    $ipKey = $parts[0] . '.' . $parts[1];
                }
            }
        }
        $uaPart = substr((string)$ua, 0, 120);
        $fp = hash('sha256', $ipKey . '|' . $uaPart);

        if (!isset($_SESSION['session_fp'])) {
            $_SESSION['session_fp'] = $fp;
        } elseif (!hash_equals($_SESSION['session_fp'], $fp)) {
            unset(
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['authenticated'],
                $_SESSION['is_admin'],
                $_SESSION['role'],
                $_SESSION['login_time'],
                $_SESSION['last_activity'],
                $_SESSION['timeout'],
                $_SESSION['session_fp']
            );
            return false;
        }
    } catch (Exception $e) {
        // Ignore fingerprint errors; default to existing checks
    }

    // Hard-validate session against database auth_sessions table if available
    try {
        global $conn;
        /** @var mysqli $conn */
        if ($conn) {
            $sid = session_id();
            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($sid && $userId) {
                $stmt = @$conn->prepare("SELECT user_id, expires_at FROM auth_sessions WHERE session_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $sid);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $stmt->close();

                    $expired = false;
                    if ($row && !empty($row['expires_at'])) {
                        $expired = (strtotime($row['expires_at']) < time());
                    }

                    if (!$row || (int)$row['user_id'] !== $userId || $expired) {
                        // DB says session is invalid; clear and reject
                        unset(
                            $_SESSION['user_id'],
                            $_SESSION['username'],
                            $_SESSION['authenticated'],
                            $_SESSION['is_admin'],
                            $_SESSION['role'],
                            $_SESSION['login_time'],
                            $_SESSION['last_activity'],
                            $_SESSION['timeout']
                        );
                        return false;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fail open if table unavailable; error is logged for diagnostics
        error_log('Session DB validation skipped: ' . $e->getMessage());
    }

    return true;
}

function getCurrentUserId()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function isAdmin($user_id = null)
{
    global $conn;
    /** @var mysqli $conn */
    if ($user_id === null) {
        if (!isAuthenticated()) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
        // If session asserts admin, trust it; otherwise fall through to DB check
        if (!empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin'])) {
            return true;
        }
    }

    if (isSuperAdmin($user_id)) {
        return true;
    }

    try {
        // Detect status columns
        $has_status_col = false;
        $has_active_col = false;
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_users LIKE 'status'")) {
            $has_status_col = $res->num_rows > 0;
            $res->close();
        }
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_users LIKE 'is_active'")) {
            $has_active_col = $res->num_rows > 0;
            $res->close();
        }
        $where = '';
        if ($has_status_col || $has_active_col) {
            $pred = [];
            if ($has_status_col) $pred[] = "status='active'";
            if ($has_active_col) $pred[] = "is_active=1";
            $where = ' AND (' . implode(' OR ', $pred) . ')';
        }
        $stmt = $conn->prepare("SELECT is_admin, is_Admin, role_id FROM auth_users WHERE id = ?" . $where);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$result) return false;
        $is_admin_flag = isset($result['is_admin']) ? (bool)$result['is_admin'] : (isset($result['is_Admin']) ? (bool)$result['is_Admin'] : false);
        $is_role_admin = isset($result['role_id']) ? ((int)$result['role_id'] === 1) : false;
        return $is_admin_flag || $is_role_admin;
    } catch (Exception $e) {
        error_log("Error checking admin status: " . $e->getMessage());
        return false;
    }
}

function hasPermission($user_id, $permission_name)
{
    global $conn;
    /** @var mysqli $conn */
    if (!$user_id) {
        return false;
    }

    if (isSuperAdmin($user_id)) {
        return true;
    }

    if (isAdmin($user_id)) {
        return true;
    }

    try {
        $stmt = $conn->prepare("
            SELECT 1 FROM auth_permissions p
            LEFT JOIN auth_role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN auth_user_roles ur ON rp.role_id = ur.role_id
            LEFT JOIN auth_user_permissions up ON p.id = up.permission_id
            WHERE p.permission_name = ? AND (ur.user_id = ? OR up.user_id = ?)
            LIMIT 1
        ");
        $stmt->bind_param('sii', $permission_name, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result !== null;
    } catch (Exception $e) {
        logError("Error checking permission: " . $e->getMessage(), 'auth');
        return false;
    }
}

function getUserPermissions($user_id)
{
    global $conn;
    /** @var mysqli $conn */
    if (isSuperAdmin($user_id)) {
        try {
            $stmt = $conn->prepare("
                SELECT DISTINCT id, permission_name, description, category 
                FROM auth_permissions 
                ORDER BY category, permission_name
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $permissions = [];
            while ($row = $result->fetch_assoc()) {
                $permissions[] = $row;
            }
            $stmt->close();
            return $permissions;
        } catch (Exception $e) {
            logError("Error getting super admin permissions: " . $e->getMessage(), 'auth');
            return [];
        }
    }

    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT p.id, p.permission_name, p.description, p.category 
            FROM auth_permissions p 
            LEFT JOIN auth_role_permissions rp ON p.id = rp.permission_id 
            LEFT JOIN auth_user_roles ur ON rp.role_id = ur.role_id 
            LEFT JOIN auth_user_permissions up ON p.id = up.permission_id 
            WHERE (ur.user_id = ? OR up.user_id = ?) 
            ORDER BY p.category, p.permission_name
        ");
        $stmt->bind_param('ii', $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row;
        }
        $stmt->close();
        return $permissions;
    } catch (Exception $e) {
        logError("Error getting user permissions: " . $e->getMessage(), 'auth');
        return [];
    }
}

/**
 * Get user applications (for dashboard)
 * @param int $user_id User ID
 * @return array Array of applications user can access
 */
function getUserApplications($user_id)
{
    global $conn;
    /** @var mysqli $conn */
    $apps = [];
    try {
        $super = isSuperAdmin($user_id);
        // Detect column differences across environments
        $has_app_status = false;
        $has_is_active = false;
        $has_perm = false;
        $has_icon = false;
        $has_cat = false;
        $has_sort = false;
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_applications LIKE 'app_status'")) {
            $has_app_status = $res->num_rows > 0;
            $res->close();
        }
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_applications LIKE 'is_active'")) {
            $has_is_active = $res->num_rows > 0;
            $res->close();
        }
        // Support either legacy permission_name or new required_permission column
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_applications LIKE 'permission_name'")) {
            if ($res->num_rows > 0) {
                $has_perm = true;
            }
            $res->close();
        }
        $has_required_perm = false;
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_applications LIKE 'required_permission'")) {
            $has_required_perm = $res->num_rows > 0;
            $res->close();
        }
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_applications LIKE 'app_icon'")) {
            $has_icon = $res->num_rows > 0;
            $res->close();
        }
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_applications LIKE 'app_category'")) {
            $has_cat = $res->num_rows > 0;
            $res->close();
        }
        if ($res = @$conn->query("SHOW COLUMNS FROM auth_applications LIKE 'sort_order'")) {
            $has_sort = $res->num_rows > 0;
            $res->close();
        }

        $select = ["app_name", "app_url", "description"];
        if ($has_icon) $select[] = "app_icon";
        else $select[] = "'' AS app_icon";
        if ($has_cat) $select[] = "app_category";
        else $select[] = "'' AS app_category";
        if ($has_required_perm) {
            $select[] = "required_permission";
        } elseif ($has_perm) {
            // legacy
            $select[] = "permission_name AS required_permission";
        } else {
            $select[] = "NULL AS required_permission";
        }
        $order = $has_sort ? "ORDER BY sort_order, app_name" : "ORDER BY app_name";
        // Super Admin should see all apps regardless of status
        $where = $super ? "" : ($has_app_status ? "WHERE app_status = 'active'" : ($has_is_active ? "WHERE is_active = 1" : ""));

        $query = "SELECT " . implode(",", $select) . " FROM auth_applications $where $order";

        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Permission gate if column exists
                if (!$super && !empty($row['required_permission'])) {
                    // Gate by required_permission using hasPermission; admins pass automatically
                    if (!isAdmin($user_id) && !hasPermission($user_id, $row['required_permission'])) {
                        continue;
                    }
                }

                // Normalize URL
                $row['app_url'] = isset($row['app_url']) ? trim(preg_replace('/\s+/', '', (string)$row['app_url'])) : '#';

                // Route corrections by URL (authorization legacy removed)
                $url = $row['app_url'];
                if (stripos($url, '/gallery/manage_gallery.php') === 0) {
                    $row['app_url'] = '/photos/piwigo/index.php';
                } elseif (stripos($url, '/survey/manage_survey.php') === 0) {
                    $row['app_url'] = '/survey/dashboard.php';
                } elseif (stripos($url, '/events/manage_events.php') === 0) {
                    $row['app_url'] = '/events/dashboard.php';
                } elseif (rtrim($url, '/') === '/survey') {
                    $row['app_url'] = '/survey/index.php';
                } elseif (rtrim($url, '/') === '/nets') {
                    $row['app_url'] = '/weekly_nets/index.php';
                }

                // Route corrections by name (fallbacks)
                $name = strtolower(trim($row['app_name'] ?? ''));
                switch ($name) {
                    case 'two-factor auth':
                        $row['app_url'] = '/authentication/2fa/two_factor_auth.php';
                        break;
                    case 'member directory':
                        $row['app_url'] = '/members/member_directory.php';
                        break;
                    case 'security settings':
                        $row['app_url'] = '/administration/security_configuration.php';
                        break;
                }

                // Final URL normalization: ensure internal routes start with '/'
                if (!empty($row['app_url']) && $row['app_url'] !== '#' && !preg_match('#^https?://#i', $row['app_url'])) {
                    $path = $row['app_url'];
                    if ($path[0] !== '/') {
                        $path = '/' . ltrim($path, '/');
                    }
                    // Collapse any '/../' or redundant slashes safely
                    $segments = [];
                    foreach (explode('/', $path) as $seg) {
                        if ($seg === '' || $seg === '.') continue;
                        if ($seg === '..') {
                            array_pop($segments);
                            continue;
                        }
                        $segments[] = $seg;
                    }
                    $row['app_url'] = '/' . implode('/', $segments);
                }

                // Keep required_permission so UI can optionally display or audit
                $apps[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error in getUserApplications: " . $e->getMessage());
    }
    return $apps;
}

/**
 * UPDATED: Complete authentication enforcement with Super Admin priority
 * @param bool $require_admin Whether admin access is required
 * @param bool $require_super_admin Whether super admin access is required
 * @param bool $require_2fa Whether 2FA is required
 * @return bool True if authenticated and authorized
 */
function enforceAuthentication($require_admin = false, $require_super_admin = false, $require_2fa = false)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isAuthenticated()) {
        setToastMessage('info', 'Login Required', 'Please log in to access this page.', 'club-logo');
        header('Location: /authentication/login.php');
        exit();
    }

    if (isSessionExpired()) {
        destroyUserSession();
        setToastMessage('warning', 'Session Expired', 'Your session has expired. Please log in again.', 'club-logo');
        header('Location: /authentication/login.php');
        exit();
    }

    $user_id = getCurrentUserId();

    // Check Super Admin requirement first
    if ($require_super_admin && !isSuperAdmin($user_id)) {
        setToastMessage('danger', 'Access Denied', 'Super Administrator privileges required.', 'club-logo');
        header('Location: /authentication/dashboard.php');
        exit();
    }

    // Check Admin requirement (Super Admin automatically passes)
    if ($require_admin && !isAdmin($user_id)) {
        setToastMessage('danger', 'Access Denied', 'Administrator privileges required.', 'club-logo');
        header('Location: /authentication/dashboard.php');
        exit();
    }

    if ($require_2fa && userNeeds2FA($user_id)) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: /authentication/2fa/two_factor_verify.php');
        exit();
    }

    $_SESSION['last_activity'] = time();
    return true;
}

// ================================================================================================
// SESSION MANAGEMENT FUNCTIONS (Updated with Super Admin detection)
// ================================================================================================

/**
 * UPDATED: Initialize user session with Super Admin detection
 * @param int $user_id User ID
 * @param bool $remember_me Whether to extend session
 * @return bool True if session initialized successfully
 */
function initiateUserSession($user_id, $remember_me = false)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $stmt = $conn->prepare("SELECT * FROM auth_users WHERE id = ? AND status = 'active'");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            error_log("User not found or not active for session initiation: $user_id");
            return false;
        }

        // Clear any existing session data
        session_unset();

        // Set session variables including Super Admin status
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        $_SESSION['callsign'] = $user['callsign'] ?? '';
        $is_super = (bool)($user['is_SuperAdmin'] ?? false);
        $is_admin_flag = isset($user['is_admin']) ? (bool)$user['is_admin'] : (isset($user['is_Admin']) ? (bool)$user['is_Admin'] : ((isset($user['role_id']) && (int)$user['role_id'] === 1)));
        $_SESSION['is_super_admin'] = $is_super;
        $_SESSION['is_admin'] = $is_admin_flag || $is_super;
        $_SESSION['role'] = $is_super ? 'super_admin' : ($is_admin_flag ? 'admin' : 'user');
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['timeout'] = time() + 7200;
        $_SESSION['authenticated'] = true;

        // Store session in database
        try {
            $session_id = session_id();
            $ip_address = getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $expires_at = $remember_me ?
                date('Y-m-d H:i:s', time() + (30 * 24 * 3600)) :
                date('Y-m-d H:i:s', time() + 7200);

            $stmt = $conn->prepare("
                INSERT INTO auth_sessions (session_id, user_id, ip_address, user_agent, expires_at, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), updated_at = NOW()
            ");
            $stmt->bind_param('sisss', $session_id, $user_id, $ip_address, $user_agent, $expires_at);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error storing session: " . $e->getMessage());
        }

        // Update last login
        try {
            $stmt = $conn->prepare("UPDATE auth_users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
            $client_ip = getClientIP();
            $stmt->bind_param('si', $client_ip, $user_id);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error updating last login: " . $e->getMessage());
        }

        $role_display = $_SESSION['is_super_admin'] ? 'Super Administrator' : ($_SESSION['is_admin'] ? 'Administrator' : 'User');
        error_log("Session initialized successfully for user: $user_id (username: " . $user['username'] . ", role: $role_display)");
        return true;
    } catch (Exception $e) {
        error_log("Error in initiateUserSession: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user needs 2FA verification
 * @param int $user_id User ID
 * @return bool True if 2FA is required
 */
function userNeeds2FA($user_id)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("SELECT two_factor_enabled FROM auth_users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ? (bool)$result['two_factor_enabled'] : false;
    } catch (Exception $e) {
        error_log("Error checking 2FA status: " . $e->getMessage());
        return false;
    }
}

/**
 * Destroy user session
 * @param int|null $user_id User ID (optional)
 * @return bool True if session destroyed successfully
 */
function destroyUserSession($user_id = null)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $session_id = session_id();
        $user_id = $user_id ?? getCurrentUserId();

        // Remove session from database
        if ($conn && $session_id) {
            try {
                $stmt = $conn->prepare("DELETE FROM auth_sessions WHERE session_id = ? OR user_id = ?");
                $stmt->bind_param('si', $session_id, $user_id);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                error_log("Error removing session from database: " . $e->getMessage());
            }
        }

        // Clear session data
        session_unset();
        session_destroy();

        // Start new session
        session_start();
        session_regenerate_id(true);

        error_log("Session destroyed for user: $user_id");
        return true;
    } catch (Exception $e) {
        error_log("Error in destroyUserSession: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if current session is expired
 * @return bool True if session is expired
 */
function isSessionExpired()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['timeout'])) {
        return true;
    }

    return time() > $_SESSION['timeout'];
}

/**
 * Extend current session timeout
 * @param int $additional_seconds Additional seconds to extend
 * @return bool True if extended successfully
 */
function extendSession($additional_seconds = 1800)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isAuthenticated()) {
        return false;
    }

    $_SESSION['timeout'] = time() + $additional_seconds;
    $_SESSION['last_activity'] = time();

    return true;
}

/**
 * Clean expired sessions from database
 * @return int Number of sessions cleaned
 */
if (!function_exists('cleanExpiredSessions')) {
    function cleanExpiredSessions()
    {
        global $conn;
        $cleaned_count = 0;

        try {
            // Clean expired sessions from database if auth_sessions table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'auth_sessions'");
            if ($check_table && $check_table->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM auth_sessions WHERE expires < NOW()");
                $stmt->execute();
                $cleaned_count += $stmt->affected_rows;
                $stmt->close();
            }

            // Clean expired sessions from file system
            $session_path = session_save_path();
            if (empty($session_path)) {
                $session_path = sys_get_temp_dir();
            }

            if (is_dir($session_path)) {
                $session_files = glob($session_path . '/sess_*');
                $current_time = time();

                foreach ($session_files as $file) {
                    // Check if session file is older than session.gc_maxlifetime (default 1440 seconds = 24 minutes)
                    $max_lifetime = ini_get('session.gc_maxlifetime') ?: 1440;
                    if (($current_time - filemtime($file)) > $max_lifetime) {
                        if (@unlink($file)) {
                            $cleaned_count++;
                        }
                    }
                }
            }

            // Log the cleanup operation
            if ($cleaned_count > 0) {
                logActivity(
                    getCurrentUserId(),
                    'sessions_cleaned',
                    'auth_activity_log',
                    null,
                    "Cleaned {$cleaned_count} expired sessions"
                );
            }
        } catch (Exception $e) {
            logError("Error cleaning expired sessions: " . $e->getMessage());
        }

        return $cleaned_count;
    }
}

// ================================================================================================
// ROLE AND PERMISSION FUNCTIONS (6 functions)
// ================================================================================================

/**
 * Check if user has specific role
 * @param int $user_id User ID
 * @param string $role_name Role name to check
 * @return bool True if user has role
 */
function hasRole($user_id, $role_name)
{
    global $conn;
    /** @var mysqli $conn */
    if (!$user_id) {
        return false;
    }

    // Super Admin has all roles
    if (isSuperAdmin($user_id)) {
        return true;
    }

    try {
        $stmt = $conn->prepare("
            SELECT 1 FROM auth_roles r
            LEFT JOIN auth_user_roles ur ON r.id = ur.role_id
            WHERE r.role_name = ? AND ur.user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('si', $role_name, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result !== null;
    } catch (Exception $e) {
        logError("Error checking role: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Get user roles
 * @param int $user_id User ID
 * @return array Array of roles
 */
function getUserRoles($user_id)
{
    global $conn;
    /** @var mysqli $conn */
    // Super Admin gets all roles
    if (isSuperAdmin($user_id)) {
        try {
            $stmt = $conn->prepare("SELECT id, role_name, description FROM auth_roles ORDER BY role_name");
            $stmt->execute();
            $result = $stmt->get_result();
            $roles = [];
            while ($row = $result->fetch_assoc()) {
                $roles[] = $row;
            }
            $stmt->close();
            return $roles;
        } catch (Exception $e) {
            logError("Error getting super admin roles: " . $e->getMessage(), 'auth');
            return [];
        }
    }

    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT r.id, r.role_name, r.description 
            FROM auth_roles r 
            LEFT JOIN auth_user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ? 
            ORDER BY r.role_name
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        $stmt->close();
        return $roles;
    } catch (Exception $e) {
        logError("Error getting user roles: " . $e->getMessage(), 'auth');
        return [];
    }
}

/**
 * Add role to user
 * @param int $user_id User ID
 * @param int $role_id Role ID
 * @return bool True if role added successfully
 */
function addUserRole($user_id, $role_id)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO auth_user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $user_id, $role_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        logError("Error adding user role: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Remove role from user
 * @param int $user_id User ID
 * @param int $role_id Role ID
 * @return bool True if role removed successfully
 */
function removeUserRole($user_id, $role_id)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("DELETE FROM auth_user_roles WHERE user_id = ? AND role_id = ?");
        $stmt->bind_param('ii', $user_id, $role_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        logError("Error removing user role: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Add permission to user
 * @param int $user_id User ID
 * @param int $permission_id Permission ID
 * @return bool True if permission added successfully
 */
function addUserPermission($user_id, $permission_id)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO auth_user_permissions (user_id, permission_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $user_id, $permission_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        logError("Error adding user permission: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Remove permission from user
 * @param int $user_id User ID
 * @param int $permission_id Permission ID
 * @return bool True if permission removed successfully
 */
function removeUserPermission($user_id, $permission_id)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("DELETE FROM auth_user_permissions WHERE user_id = ? AND permission_id = ?");
        $stmt->bind_param('ii', $user_id, $permission_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        logError("Error removing user permission: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Enhanced authentication activity logging
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $details Additional details
 * @param string|null $ip_override Override IP address
 * @param bool $success Whether action was successful
 * @return bool True if logged successfully
 */
function logAuthActivity($user_id, $action, $details = '', $ip_override = null, $success = true)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("
            INSERT INTO auth_activity_log (user_id, action, details, ip_address, user_agent, success, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $ip_address = $ip_override ?? getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $success_int = $success ? 1 : 0;
        $stmt->bind_param('issssi', $user_id, $action, $details, $ip_address, $user_agent, $success_int);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error logging auth activity: " . $e->getMessage());
        return false;
    }
}

if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $table = '', $record_id = null, $details = '', $target_user_id = null)
    {
        global $conn;
        /** @var mysqli $conn */

        // Get client IP and user agent for logging
        $ip_address = getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Detect optional target_user_id column (cache result)
        static $hasTargetUserColumn = null;
        if ($hasTargetUserColumn === null) {
            try {
                $res = $conn->query("SHOW COLUMNS FROM auth_activity_log LIKE 'target_user_id'");
                $hasTargetUserColumn = $res && $res->num_rows > 0;
                if ($res) {
                    $res->close();
                }
            } catch (Exception $e) {
                $hasTargetUserColumn = false;
            }
        }

        if ($hasTargetUserColumn) {
            $stmt = $conn->prepare("INSERT INTO auth_activity_log (user_id, action, table_name, details, ip_address, user_agent, target_user_id, success, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $tid = $target_user_id !== null ? (int)$target_user_id : null;
            $stmt->bind_param('isssssi', $user_id, $action, $table, $details, $ip_address, $user_agent, $tid);
        } else {
            $stmt = $conn->prepare("INSERT INTO auth_activity_log (user_id, action, table_name, details, ip_address, user_agent, success, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param('isssss', $user_id, $action, $table, $details, $ip_address, $user_agent);
        }
        $stmt->execute();
        $stmt->close();
    }
}

function tableExists($table_name)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $result = $conn->query("SHOW TABLES LIKE '{$table_name}'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

function getTableColumns($table_name)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $result = $conn->query("DESCRIBE `{$table_name}`");
        $columns = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row;
            }
        }
        return $columns;
    } catch (Exception $e) {
        logError("Error getting table columns: " . $e->getMessage());
        return [];
    }
}

function executeQuery($query, $params = [], $types = '')
{
    global $conn;
    /** @var mysqli $conn */
    try {
        if (empty($params)) {
            $result = $conn->query($query);
            if ($result === false) {
                throw new Exception($conn->error);
            }
            if ($result === true) {
                return ['success' => true, 'affected_rows' => $conn->affected_rows];
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            return $data;
        } else {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception($stmt->error);
            }
            $result = $stmt->get_result();
            if ($result === false) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                return ['success' => true, 'affected_rows' => $affected_rows];
            }
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            return $data;
        }
    } catch (Exception $e) {
        logError("Database query error: " . $e->getMessage());
        return false;
    }
}

function getDatabaseStatus()
{
    global $conn;
    /** @var mysqli $conn */
    $status = [
        'connected' => false,
        'server_info' => 'Unknown',
        'database' => 'Unknown',
        'charset' => 'Unknown',
        'tables_exist' => false,
        'user_count' => 0,
        'session_count' => 0
    ];

    if ($conn && $conn->ping()) {
        $status['connected'] = true;
        $status['server_info'] = $conn->server_info;
        $status['charset'] = $conn->character_set_name();

        // Get database name
        $result = $conn->query("SELECT DATABASE() as db_name");
        if ($result) {
            $row = $result->fetch_assoc();
            $status['database'] = $row['db_name'];
        }

        // Check if auth tables exist
        $tables = ['auth_users', 'auth_sessions', 'auth_permissions'];
        $existing_tables = 0;
        foreach ($tables as $table) {
            if (tableExists($table)) {
                $existing_tables++;
            }
        }
        $status['tables_exist'] = $existing_tables === count($tables);

        // Count users and sessions
        try {
            $result = $conn->query("SELECT COUNT(*) as count FROM auth_users");
            if ($result) {
                $row = $result->fetch_assoc();
                $status['user_count'] = $row['count'];
            }

            $result = $conn->query("SELECT COUNT(*) as count FROM auth_sessions WHERE expires_at > NOW()");
            if ($result) {
                $row = $result->fetch_assoc();
                $status['session_count'] = $row['count'];
            }
        } catch (Exception $e) {
            // Tables might not exist yet
        }
    }

    return $status;
}

function checkRateLimit($identifier, $max_requests = 100, $time_window = 3600, $action = 'general')
{
    global $conn;
    /** @var mysqli $conn */
    try {
        // Create rate limit table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                action VARCHAR(100) NOT NULL,
                request_count INT DEFAULT 1,
                window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_rate_limit (identifier, action),
                INDEX idx_window_start (window_start)
            )
        ");

        // Clean old entries
        $conn->query("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL $time_window SECOND)");

        // Check current rate limit
        $stmt = $conn->prepare("
            SELECT request_count, window_start 
            FROM rate_limits 
            WHERE identifier = ? AND action = ? 
            AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->bind_param('ssi', $identifier, $action, $time_window);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result) {
            if ($result['request_count'] >= $max_requests) {
                return false;
            }

            // Increment counter
            $stmt = $conn->prepare("
                UPDATE rate_limits 
                SET request_count = request_count + 1, last_request = NOW() 
                WHERE identifier = ? AND action = ?
            ");
            $stmt->bind_param('ss', $identifier, $action);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create new entry
            $stmt = $conn->prepare("
                INSERT INTO rate_limits (identifier, action, request_count, window_start) 
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                    request_count = 1, 
                    window_start = NOW()
            ");
            $stmt->bind_param('ss', $identifier, $action);
            $stmt->execute();
            $stmt->close();
        }

        return true;
    } catch (Exception $e) {
        logError("Rate limit check error: " . $e->getMessage());
        return true; // Allow on error to prevent blocking legitimate users
    }
}

function get2FAStatus($user_id)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("SELECT two_factor_enabled, two_factor_setup_at, two_factor_backup_codes FROM auth_users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result) {
            $backup_codes = $result['two_factor_backup_codes'] ?
                json_decode($result['two_factor_backup_codes'], true) : [];

            return [
                'enabled' => (bool)$result['two_factor_enabled'],
                'setup_at' => $result['two_factor_setup_at'],
                'backup_codes_count' => count($backup_codes),
                'has_backup_codes' => !empty($backup_codes)
            ];
        }

        return false;
    } catch (Exception $e) {
        logError("Error getting 2FA status: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Generate QR code URL for 2FA setup
 * @param string $secret TOTP secret
 * @param string $issuer Issuer name
 * @param string $account_name Account name
 * @return string QR code URL
 */
function generate2FAQRCodeURL($secret, $issuer = 'W5OBM Club', $account_name = '')
{
    $url = sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
        urlencode($issuer),
        urlencode($account_name),
        $secret,
        urlencode($issuer)
    );

    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
}

/**
 * Verify backup code and mark as used
 * @param int $user_id User ID
 * @param string $code Backup code to verify
 * @return bool True if code is valid and unused
 */
function verifyBackupCode($user_id, $code)
{
    global $conn;
    /** @var mysqli $conn */
    try {
        $stmt = $conn->prepare("SELECT two_factor_backup_codes FROM auth_users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result || !$result['two_factor_backup_codes']) {
            return false;
        }

        $backup_codes = json_decode($result['two_factor_backup_codes'], true);
        if (!is_array($backup_codes)) {
            return false;
        }

        $code_index = array_search($code, $backup_codes);
        if ($code_index === false) {
            return false;
        }

        // Remove used code
        unset($backup_codes[$code_index]);
        $updated_codes = json_encode(array_values($backup_codes));

        $stmt = $conn->prepare("UPDATE auth_users SET two_factor_backup_codes = ? WHERE id = ?");
        $stmt->bind_param('si', $updated_codes, $user_id);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            logAuthActivity($user_id, '2fa_backup_code_used', "Backup code used for 2FA verification");
        }

        return $result;
    } catch (Exception $e) {
        logError("Error verifying backup code: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Get application status for system monitoring
 * @return array Application status information
 */
function getApplicationStatus()
{
    $status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'uptime' => time() - $_SERVER['REQUEST_TIME'],
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'memory_limit' => ini_get('memory_limit'),
        'php_version' => phpversion(),
        'session_status' => session_status(),
        'database' => getDatabaseStatus(),
        'directories' => [
            'logs' => [
                'exists' => is_dir(__DIR__ . '/../logs'),
                'writable' => is_writable(__DIR__ . '/../logs')
            ],
            'cache' => [
                'exists' => is_dir(__DIR__ . '/../cache'),
                'writable' => is_writable(__DIR__ . '/../cache')
            ]
        ]
    ];

    return $status;
}

/**
 * Clean old log files
 * @param int $days_to_keep Number of days to keep logs
 * @return int Number of files deleted
 */
function cleanOldLogs($days_to_keep = 30)
{
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        return 0;
    }

    $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
    $deleted_count = 0;

    try {
        $files = glob($log_dir . '/*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
    } catch (Exception $e) {
        logError("Error cleaning old logs: " . $e->getMessage());
    }

    return $deleted_count;
}

/**
 * Clear system cache files and temporary data
 * @return bool True if cache cleared successfully
 */
if (!function_exists('cacheClear')) {
    function cacheClear()
    {
        $cleared_items = 0;
        $base_path = __DIR__ . '/..';

        try {
            // Clear session files older than 24 hours
            $session_path = session_save_path();
            if (empty($session_path)) {
                $session_path = sys_get_temp_dir();
            }

            if (is_dir($session_path)) {
                $session_files = glob($session_path . '/sess_*');
                $cutoff_time = time() - (24 * 60 * 60); // 24 hours ago

                foreach ($session_files as $file) {
                    if (filemtime($file) < $cutoff_time) {
                        if (@unlink($file)) {
                            $cleared_items++;
                        }
                    }
                }
            }

            // Clear temporary upload files
            $temp_dirs = [
                $base_path . '/uploads/temp',
                $base_path . '/temp',
                $base_path . '/administration/temp'
            ];

            foreach ($temp_dirs as $temp_dir) {
                if (is_dir($temp_dir)) {
                    $temp_files = glob($temp_dir . '/*');
                    $cutoff_time = time() - (6 * 60 * 60); // 6 hours ago

                    foreach ($temp_files as $file) {
                        if (is_file($file) && filemtime($file) < $cutoff_time) {
                            if (@unlink($file)) {
                                $cleared_items++;
                            }
                        }
                    }
                }
            }

            // Clear CSS backup files older than 30 days
            $css_backup_dir = $base_path . '/css/backups';
            if (is_dir($css_backup_dir)) {
                $backup_dirs = glob($css_backup_dir . '/*', GLOB_ONLYDIR);
                $cutoff_time = time() - (30 * 24 * 60 * 60); // 30 days ago

                foreach ($backup_dirs as $backup_dir) {
                    if (filemtime($backup_dir) < $cutoff_time) {
                        if (@rmdir($backup_dir)) {
                            $cleared_items++;
                        }
                    }
                }
            }

            // Clear expired PHP opcode cache if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $cleared_items++;
            }

            // Clear any custom cache files
            $cache_files = [
                $base_path . '/cache',
                $base_path . '/tmp'
            ];

            foreach ($cache_files as $cache_dir) {
                if (is_dir($cache_dir)) {
                    $files = glob($cache_dir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            if (@unlink($file)) {
                                $cleared_items++;
                            }
                        }
                    }
                }
            }

            // Log the cache clear operation
            logActivity(
                getCurrentUserId(),
                'cache_cleared',
                'auth_activity_log',
                null,
                "Cache cleared: {$cleared_items} items removed"
            );

            return true;
        } catch (Exception $e) {
            logError("Error clearing cache: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Format bytes into human readable format
 * @param int $bytes Number of bytes
 * @param int $precision Decimal precision
 * @return string Formatted size string
 */
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * Get user-friendly error message
 * @param string $error_type Error type
 * @param string $context Additional context
 * @return string User-friendly message
 */
function getUserFriendlyErrorMessage($error_type, $context = '')
{
    $messages = [
        'database_error' => 'We\'re experiencing technical difficulties. Please try again later.',
        'permission_denied' => 'You don\'t have permission to perform this action.',
        'session_expired' => 'Your session has expired. Please log in again.',
        'invalid_input' => 'Please check your input and try again.',
        'file_upload_error' => 'There was a problem uploading your file. Please try again.',
        'email_error' => 'We couldn\'t send the email. Please try again later.',
        'authentication_failed' => 'Login failed. Please check your credentials.',
        'rate_limit_exceeded' => 'Too many requests. Please wait a moment and try again.',
        'maintenance_mode' => 'The system is currently under maintenance. Please try again later.',
        'not_found' => 'The requested resource was not found.'
    ];

    $message = $messages[$error_type] ?? 'An unexpected error occurred. Please try again.';

    if (!empty($context)) {
        $message .= " ($context)";
    }

    return $message;
}

/**
 * Check if user agent is a bot/crawler
 * @param string|null $user_agent User agent string
 * @return bool True if bot detected
 */
function isBot($user_agent = null)
{
    $user_agent = $user_agent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');

    $bot_patterns = [
        'googlebot',
        'bingbot',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'facebookexternalhit',
        'twitterbot',
        'rogerbot',
        'linkedinbot',
        'embedly',
        'quora link preview',
        'showyoubot',
        'outbrain',
        'pinterest',
        'developers.google.com',
        'crawler',
        'spider',
        'bot',
        'scraper'
    ];

    foreach ($bot_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

// ================================================================================================
// IMPLEMENTATION NOTES AND COMPATIBILITY
// ================================================================================================

/*
 * SUPER ADMIN HIERARCHY IMPLEMENTATION SUMMARY:
 * 
 * NEW FUNCTIONS ADDED:
 * 1. isSuperAdmin() - Check super admin status (HIGHEST PRIORITY)
 * 2. getUserPrivilegeLevel() - Get user privilege level for display
 * 3. canAccessFunctionality() - Check functionality access with hierarchy
 * 4. getUsersByPrivilegeLevel() - Get users by privilege level
 * 
 * UPDATED FUNCTIONS:
 * 1. isAdmin() - Now includes Super Admin check
 * 2. hasPermission() - Super Admin automatically has ALL permissions
 * 3. getUserPermissions() - Super Admin gets ALL permissions automatically
 * 4. enforceAuthentication() - Added super admin requirement parameter
 * 5. initiateUserSession() - Sets super admin session variables
 * 
 * HIERARCHY PRIORITY:
 * Super Admin > Admin > User
 * 
 * Super Admin Benefits:
 * - Automatically has ALL permissions without assignments
 * - Cannot lose access through permission changes
 * - Inherent priority over all other users
 * - Session shows 'super_admin' role
 * 
 * DATABASE REQUIREMENTS:
 * - Run the SQL updates to add is_SuperAdmin column
 * - Set KD5BS (or your callsign) as Super Admin
 * 
 * USAGE:
 * - Use isSuperAdmin() to check super admin status
 * - Use enforceAuthentication(false, true) for super admin only pages
 * - Super Admins automatically pass all isAdmin() and hasPermission() checks
 * 
 * SECURITY:
 * - Limit Super Admin assignments to trusted users only
 * - Log all Super Admin actions for audit trail
 * - Consider requiring 2FA for Super Admin accounts
 */

// Backwards compatibility aliases (if needed)
if (!function_exists('checkAdminStatus')) {
    function checkAdminStatus($user_id = null)
    {
        return isAdmin($user_id);
    }
}

if (!function_exists('checkUserPermission')) {
    function checkUserPermission($user_id, $permission)
    {
        return hasPermission($user_id, $permission);
    }
}

function getClientIP()
{
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return trim($ip);
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
}
if (!function_exists('hashPassword')) {
    function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
if (!function_exists('checkPasswordStrength')) {
    function checkPasswordStrength($password)
    {
        $password = (string) $password;
        $score = 0;
        $feedback = [];

        $length = strlen($password);
        if ($length >= 8) {
            $score += 2;
        } else {
            $feedback[] = 'Use at least 8 characters';
        }

        if ($length >= 12) {
            $score += 1;
        }

        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Include lowercase letters';
        }

        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Include uppercase letters';
        }

        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Include numbers';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 2;
        } else {
            $feedback[] = 'Include special characters';
        }

        if (preg_match('/(.)\\1{2,}/', $password)) {
            $score -= 1;
            $feedback[] = 'Avoid repeating characters';
        }

        $strength = $score < 3 ? 'weak' : ($score < 6 ? 'medium' : 'strong');

        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback,
        ];
    }
}
if (!function_exists('passwordNeedsRehash')) {
    function passwordNeedsRehash($hash)
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
if (!function_exists('requireAuthentication')) {
    function requireAuthentication()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            // Optionally set a message or redirect
            header('Location: /authentication/login.php');
            exit();
        }
    }
}

/**
 * Log CR (Change Request) access
 * @param int $user_id User ID
 * @param string $details Access details
 */
function logCRMAccess($action, $ip_address, $user_id)
{
    global $conn;
    /** @var mysqli $conn */
    $stmt = $conn->prepare("INSERT INTO crm_access_log (user_id, action, ip_address, accessed_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('iss', $user_id, $action, $ip_address);
    $stmt->execute();
    $stmt->close();
}


if (!function_exists('setToastMessage')) {
    /**
     * Set a toast message to be displayed on the next page load.
     * @param string $type    Bootstrap type: 'success', 'info', 'warning', 'danger'
     * @param string $title   Toast title
     * @param string $message Toast body/message
     * @param string $theme   Optional, for custom icon or theme
     * @param array  $actions Optional, for extra actions or options
     */
    function setToastMessage($type, $title, $message, $theme = '', $actions = [])
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['toast'] = [
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'theme'   => $theme,
            'actions' => $actions
        ];
    }
}

/**
 * Sanitize user input to prevent XSS and trim whitespace.
 * @param string $input
 * @return string
 */
if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input with optional type handling.
     * Backwards-compatible: many legacy calls pass a second $type argument (e.g. 'string','int','float','email','html').
     * @param mixed $input Raw input value
     * @param string $type One of: string,int,float,email,html
     * @param array $options Future extension (unused)
     * @return mixed Sanitized value (string|int|float|null)
     */
    function sanitizeInput($input, $type = 'string', $options = [])
    {
        // Normalize input
        if (is_array($input)) {
            // Recursively sanitize arrays preserving keys
            $sanitized = [];
            foreach ($input as $k => $v) {
                $sanitized[$k] = sanitizeInput($v, $type, $options);
            }
            return $sanitized;
        }

        // Trim basic whitespace for scalar values
        if (is_string($input)) {
            $input = trim($input);
        }

        switch (strtolower($type)) {
            case 'int':
                if ($input === '' || $input === null) return null; // preserve null semantics where optional
                $val = filter_var($input, FILTER_VALIDATE_INT);
                return $val === false ? null : (int)$val;
            case 'float':
                if ($input === '' || $input === null) return null;
                $val = filter_var($input, FILTER_VALIDATE_FLOAT);
                return $val === false ? null : (float)$val;
            case 'email':
                $email = filter_var($input, FILTER_VALIDATE_EMAIL);
                return $email === false ? '' : htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            case 'html':
                // Allow limited safe HTML; strip disallowed tags, then encode critical attributes if needed.
                // Define allowlist (adjust as needed for formatting)
                $allowed_tags = '<p><br><b><strong><i><em><u><ul><ol><li><span><div><a>'; // conservative set
                $clean = strip_tags($input, $allowed_tags);
                // Basic attribute scrubbing for href and style to mitigate XSS vectors
                $clean = preg_replace('/on\w+\s*=\s*"[^"]*"/i', '', $clean); // remove inline event handlers
                $clean = preg_replace('/javascript:/i', '', $clean); // remove javascript: pseudo URLs
                return $clean; // do not htmlspecialchars so allowed tags remain
            case 'string':
            default:
                // Fallback: encode everything to prevent XSS
                return htmlspecialchars((string)$input, ENT_QUOTES, 'UTF-8');
        }
    }
}

// ---------- Consolidated helpers appended below ----------

// Backfill helper for admin dashboard: getUserData
if (!function_exists('getUserData')) {
    /**
     * Fetch user profile data with schema tolerance.
     * @param int|null $user_id
     * @return array|null
     */
    function getUserData($user_id = null)
    {
        global $conn;
        /** @var mysqli $conn */
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if ($user_id === null) {
                $user_id = $_SESSION['user_id'] ?? null;
            }
            if (!$user_id || !$conn) return null;

            // Discover available columns on auth_users
            $available = [];
            if ($res = @$conn->query("SHOW COLUMNS FROM auth_users")) {
                while ($row = $res->fetch_assoc()) {
                    $available[$row['Field']] = true;
                }
                $res->close();
            }
            $wanted = ['id', 'username', 'email', 'first_name', 'last_name', 'callsign', 'status', 'is_active', 'created_at', 'last_login'];
            $select = [];
            foreach ($wanted as $col) {
                if (isset($available[$col])) {
                    $select[] = $col;
                } else {
                    // provide defaults for missing columns
                    $alias = $col;
                    $default = ($col === 'is_active') ? '0' : "''";
                    if ($col === 'id') {
                        $default = 'id'; /* id should exist, but keep safe */
                    }
                    $select[] = "$default AS $alias";
                }
            }
            $sql = "SELECT " . implode(',', $select) . " FROM auth_users WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                // Fallback: minimal selection
                $stmt = $conn->prepare("SELECT id, username, email FROM auth_users WHERE id = ? LIMIT 1");
            }
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) return null;

            // Normalize active flag
            $row['is_active'] = (isset($row['status']) && strtolower((string)$row['status']) === 'active')
                || (!empty($row['is_active']) && (int)$row['is_active'] === 1);

            return $row;
        } catch (Exception $e) {
            error_log('getUserData error: ' . $e->getMessage());
            return null;
        }
    }
}

// Toast helpers: consolidate here so callers don't depend on a separate functions.php
if (!function_exists('showToast')) {
    /**
     * Queue a JS toast to be displayed on page load via existing showToast() JS.
     * (Immediate output variant; use setToastMessage to enqueue in session.)
     */
    function showToast($type, $title, $message, $theme = 'standard', $actions = [])
    {
        $type = htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8');
        $theme = htmlspecialchars((string)$theme, ENT_QUOTES, 'UTF-8');
        $actions_json = json_encode($actions);
        echo "<script>window.addEventListener('load',function(){try{if(typeof showToast==='function'){showToast('$type','$title','$message','$theme',$actions_json);} }catch(e){console&&console.error&&console.error(e);}});</script>";
    }
}

if (!function_exists('getToastMessage')) {
    /**
     * Retrieve and clear toast messages stored in session via setToastMessage().
     * Returns an array of toasts for convenient iteration.
     */
    function getToastMessage()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $toasts = [];
        if (!empty($_SESSION['toast'])) {
            $t = $_SESSION['toast'];
            unset($_SESSION['toast']);
            if (isset($t['type'])) {
                $toasts[] = $t;
            } elseif (is_array($t)) {
                $toasts = $t;
            }
        }
        return $toasts;
    }
}

// Convenience: display queued toasts (wrapper around getToastMessage + showToast)
if (!function_exists('displayToastMessage')) {
    /**
     * Render toast messages on page load. If no toasts provided, it will fetch
     * any queued toasts from session via getToastMessage().
     */
    function displayToastMessage(?array $toasts = null): void
    {
        if ($toasts === null) {
            $toasts = function_exists('getToastMessage') ? getToastMessage() : [];
        }
        if (!$toasts || !is_array($toasts)) {
            return;
        }
        foreach ($toasts as $t) {
            $type = $t['type'] ?? 'info';
            $title = $t['title'] ?? '';
            $message = $t['message'] ?? '';
            $theme = $t['theme'] ?? 'standard';
            $actions = $t['actions'] ?? [];
            if (function_exists('showToast')) {
                showToast($type, $title, $message, $theme, $actions);
            }
        }
    }
}

// ---------- Utility: table existence ----------
if (!function_exists('tableExists')) {
    function tableExists($name)
    {
        global $conn;
        if (!$conn) return false;
        $stmt = @$conn->prepare('SHOW TABLES LIKE ?');
        if (!$stmt) return false;
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok = ($res && $res->num_rows > 0);
        $stmt->close();
        return $ok;
    }
}

// ---------- System stats (schema tolerant) ----------
if (!function_exists('getSystemStats')) {
    function getSystemStats()
    {
        global $conn;
        $stats = [
            'total_members' => 0,
            'active_members' => 0,
            'pending_members' => 0,
            'pending_refunds' => 0,
            'new_applications' => 0,
            'total_users' => 0,
            'active_sessions' => 0,
            'new_users_24h' => 0,
            'total_events' => 0,
            'upcoming_events' => 0,
            'events_this_week' => 0
        ];
        if ($conn) {
            // Members
            if (tableExists('members')) {
                if ($r = $conn->query('SELECT COUNT(*) c FROM members')) {
                    $stats['total_members'] = (int)($r->fetch_assoc()['c'] ?? 0);
                }
                if ($r = $conn->query("SELECT SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) a, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) p FROM members")) {
                    $row = $r->fetch_assoc();
                    $stats['active_members'] = (int)($row['a'] ?? 0);
                    $stats['pending_members'] = (int)($row['p'] ?? 0);
                }
                // new applications last 7 days using created_at/rec_added
                $col = 'created_at';
                $has = false;
                if ($res = @$conn->query("SHOW COLUMNS FROM members LIKE 'created_at'")) {
                    $has = $res->num_rows > 0;
                    $res->close();
                }
                if (!$has) {
                    if ($res = @$conn->query("SHOW COLUMNS FROM members LIKE 'rec_added'")) {
                        if ($res->num_rows > 0) {
                            $col = 'rec_added';
                            $has = true;
                        }
                        $res->close();
                    }
                }
                if ($has) {
                    $q = "SELECT COUNT(*) c FROM members WHERE " . $col . " >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    if ($r = $conn->query($q)) {
                        $stats['new_applications'] = (int)($r->fetch_assoc()['c'] ?? 0);
                    }
                }
            }
            // Users
            if (tableExists('auth_users')) {
                if ($r = $conn->query('SELECT COUNT(*) c FROM auth_users')) {
                    $stats['total_users'] = (int)($r->fetch_assoc()['c'] ?? 0);
                }
                if ($r = $conn->query("SELECT COUNT(*) c FROM auth_users WHERE (status='active' OR IFNULL(is_active,0)=1)")) {
                    $stats['active_sessions'] = (int)($r->fetch_assoc()['c'] ?? 0);
                }
                if ($r = $conn->query("SELECT COUNT(*) c FROM auth_users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")) {
                    $stats['new_users_24h'] = (int)($r->fetch_assoc()['c'] ?? 0);
                }
            }
            // Events
            if (tableExists('events')) {
                if ($r = $conn->query('SELECT COUNT(*) c FROM events')) {
                    $stats['total_events'] = (int)($r->fetch_assoc()['c'] ?? 0);
                }
                if ($r = $conn->query('SELECT COUNT(*) c FROM events WHERE event_date >= CURDATE()')) {
                    $stats['upcoming_events'] = (int)($r->fetch_assoc()['c'] ?? 0);
                }
                if ($r = $conn->query('SELECT COUNT(*) c FROM events WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)')) {
                    $stats['events_this_week'] = (int)($r->fetch_assoc()['c'] ?? 0);
                }
            }
        }
        return $stats;
    }
}

// ---------- Users stats/list (schema tolerant) ----------
if (!function_exists('getUsersStats')) {
    function getUsersStats()
    {
        global $conn;
        $s = ['total_users' => 0, 'active_users' => 0, 'super_admins' => 0, 'admins' => 0, 'recent_logins' => 0];
        if (!$conn || !tableExists('auth_users')) return $s;
        if ($r = $conn->query('SELECT COUNT(*) c FROM auth_users')) {
            $s['total_users'] = (int)($r->fetch_assoc()['c'] ?? 0);
        }
        if ($r = $conn->query("SELECT COUNT(*) c FROM auth_users WHERE (status='active' OR IFNULL(is_active,0)=1)")) {
            $s['active_users'] = (int)($r->fetch_assoc()['c'] ?? 0);
        }
        if ($r = $conn->query("SELECT COUNT(*) c FROM auth_users WHERE is_SuperAdmin=1 AND (status='active' OR IFNULL(is_active,0)=1)")) {
            $s['super_admins'] = (int)($r->fetch_assoc()['c'] ?? 0);
        }
        if ($r = $conn->query("SELECT COUNT(*) c FROM auth_users WHERE is_Admin=1 AND is_SuperAdmin=0 AND (status='active' OR IFNULL(is_active,0)=1)")) {
            $s['admins'] = (int)($r->fetch_assoc()['c'] ?? 0);
        }
        if (tableExists('auth_activity_log')) {
            if ($r = $conn->query("SELECT COUNT(DISTINCT user_id) c FROM auth_activity_log WHERE action='login_success' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")) {
                $s['recent_logins'] = (int)($r->fetch_assoc()['c'] ?? 0);
            }
        }
        return $s;
    }
}
if (!function_exists('getUsersList')) {
    function getUsersList($filters = [])
    {
        global $conn;
        $users = [];
        if (!$conn || !tableExists('auth_users')) return $users;
        $search = trim($filters['search'] ?? '');
        $role_filter = $filters['role'] ?? '';
        $cols = ['id', 'username', 'email'];
        foreach (['first_name', 'last_name', 'callsign', 'status', 'last_login', 'created_at', 'is_Admin', 'is_SuperAdmin'] as $c) {
            $res = @$conn->query("SHOW COLUMNS FROM auth_users LIKE '" . $conn->real_escape_string($c) . "'");
            if ($res && $res->num_rows > 0) {
                $cols[] = $c;
                $res->close();
            }
        }
        $sql = 'SELECT ' . implode(',', $cols) . ' FROM auth_users WHERE 1=1';
        $types = '';
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (username LIKE ? OR email LIKE ?';
            if (in_array('first_name', $cols)) $sql .= ' OR first_name LIKE ?';
            if (in_array('last_name', $cols)) $sql .= ' OR last_name LIKE ?';
            if (in_array('callsign', $cols)) $sql .= ' OR callsign LIKE ?';
            $sql .= ')';
            $term = "%$search%";
            $params[] = $term;
            $params[] = $term;
            if (in_array('first_name', $cols)) $params[] = $term;
            if (in_array('last_name', $cols)) $params[] = $term;
            if (in_array('callsign', $cols)) $params[] = $term;
            $types .= str_repeat('s', count($params));
        }
        if ($role_filter) {
            if ($role_filter === 'super_admin' && in_array('is_SuperAdmin', $cols)) $sql .= ' AND is_SuperAdmin=1';
            elseif ($role_filter === 'admin' && in_array('is_Admin', $cols)) $sql .= ' AND is_Admin=1 AND (is_SuperAdmin IS NULL OR is_SuperAdmin=0)';
            elseif ($role_filter === 'user') {
                if (in_array('is_Admin', $cols) && in_array('is_SuperAdmin', $cols)) $sql .= ' AND (is_Admin=0 OR is_Admin IS NULL) AND (is_SuperAdmin=0 OR is_SuperAdmin IS NULL)';
            }
        }
        $sql .= ' ORDER BY ';
        if (in_array('is_SuperAdmin', $cols)) $sql .= ' is_SuperAdmin DESC,';
        if (in_array('is_Admin', $cols)) $sql .= ' is_Admin DESC,';
        $sql .= ' id DESC LIMIT 50';
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $users[] = $row;
            }
        }
        if (isset($stmt)) $stmt->close();
        return $users;
    }
}

if (!function_exists('computeSpamScore')) {
    /**
     * Compute a basic spam score from category + message content.
     * Higher score indicates more likely solicitation.
     */
    function computeSpamScore(string $category, string $message): int
    {
        $text  = mb_strtolower(trim($category . ' ' . $message), 'UTF-8');
        $score = 0;
        $keywords = [
            'seo',
            'partnership',
            'partner',
            'increase traffic',
            'traffic increase',
            'traffic',
            'marketing',
            'promotion',
            'advertis',
            'sponsor',
            'guest post',
            'backlink',
            'link exchange',
            'crypto',
            'forex',
            'investment',
            'loan',
            'casino',
            'betting',
            'viagra',
            'adult',
            'porn',
            'escort',
            'web design',
            'sell you',
            'buy leads',
            'email list',
            'cold outreach',
            'whatsapp bulk',
            'telegram bulk',
            'scraping service',
            'brand awareness',
            'business proposal',
            'partnership opportunity',
            'influencer',
            'traffic boost',
            'rankings',
            'google ranking'
        ];
        foreach ($keywords as $kw) if (strpos($text, $kw) !== false) $score += 10;
        $urlCount = preg_match_all('/https?:\/\/|www\./i', $text);
        if ($urlCount !== false && $urlCount > 0) $score += min($urlCount, 10) * 5;
        if (preg_match('/(special offer|limited time|discount.*services|guest\s*post|publish.*article)/i', $text)) $score += 15;
        return $score;
    }
}

if (!function_exists('isSolicitationMessage')) {
    /**
     * Heuristic check for solicitation/spam content.
     */
    function isSolicitationMessage(string $category, string $message): bool
    {
        $text = mb_strtolower(trim($category . ' ' . $message), 'UTF-8');

        // Direct keyword hits
        $keywords = [
            'seo',
            'marketing',
            'promotion',
            'advertis',
            'sponsor',
            'guest post',
            'backlink',
            'link exchange',
            'crypto',
            'forex',
            'investment',
            'loan',
            'casino',
            'betting',
            'viagra',
            'adult',
            'porn',
            'escort',
            'web design',
            'sell you',
            'buy leads',
            'email list',
            'cold outreach',
            'whatsapp bulk',
            'telegram bulk',
            'scraping service',
            'brand awareness',
            'business proposal',
            'partnership opportunity',
            'influencer',
            'traffic boost',
            'rankings',
            'google ranking'
        ];
        foreach ($keywords as $kw) {
            if (strpos($text, $kw) !== false) {
                return true;
            }
        }

        // Too many links
        $urlCount = preg_match_all('/https?:\/\/|www\./i', $text);
        if ($urlCount !== false && $urlCount > 2) {
            return true;
        }

        // Aggressive promo phrases
        if (preg_match('/(special offer|limited time|discount.*services|guest\s*post|publish.*(article|post)|sponsored\s*content)/i', $text)) {
            return true;
        }

        // Score-based fallback (if available)
        if (function_exists('computeSpamScore') && computeSpamScore($category, $message) >= 20) {
            return true;
        }

        return false;
    }
}

// ---------- End of helper_functions.php ----------