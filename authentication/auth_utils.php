<?php

/**
 * Authentication Utilities - W5OBM Amateur Radio Club
 * File: /authentication/auth_utils.php
 * Purpose: Complete authentication utility functions and session integration
 * CREATED: To fix missing auth_utils.php references
 */

// Prevent direct access
if (!defined('AUTH_UTILS_LOADED')) {
    define('AUTH_UTILS_LOADED', true);
}

// Required includes with error handling
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/remember_me.php';

/** @var mysqli $conn */
global $conn;

// Helper to detect if this file is being requested directly for self-test (ping/health)
if (!function_exists('_authUtilsIsSelfTest')) {
    function _authUtilsIsSelfTest(): bool
    {
        $ping = isset($_GET['ping']);
        $health = isset($_GET['health']);
        if (!($ping || $health)) return false;

        $selfBase = basename(__FILE__);
        $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $phpSelf = basename($_SERVER['PHP_SELF'] ?? '');
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';

        // Consider it a direct request if any of these match/belong to this file
        $byName = ($scriptName === $selfBase) || ($phpSelf === $selfBase);
        $byUri = (strpos($requestUri, '/authentication/auth_utils.php') !== false) || (strpos($requestUri, $selfBase) !== false);
        $byFile = (basename($scriptFilename) === $selfBase);

        return $byName || $byUri || $byFile;
    }
}

// Ensure database connection is available
// Allow direct CLI/web health checks to proceed without hard-failing
if (!$conn) {
    $is_cli = (php_sapi_name() === 'cli');
    if (!($is_cli || _authUtilsIsSelfTest())) {
        die('Database connection failed. Please check your configuration.');
    }
}

// Initialize session manager instance
$session_manager = SessionManager::getInstance();

// Ensure SessionManager has database connection
if (method_exists($session_manager, 'setDatabaseConnection')) {
    $session_manager->setDatabaseConnection($conn);
} else {
    // Fallback: Set connection using reflection if method doesn't exist
    try {
        $reflection = new ReflectionClass($session_manager);
        $conn_property = $reflection->getProperty('conn');
        $conn_property->setAccessible(true);
        $conn_property->setValue($session_manager, $conn);
    } catch (Exception $e) {
        error_log("Failed to set SessionManager database connection: " . $e->getMessage());
    }
}

/**
 * ============================================================================
 * AUTHENTICATION STATE MANAGEMENT
 * ============================================================================
 */

/**
 * Complete authentication check with session validation
 * @return bool True if user is fully authenticated
 */
function isUserAuthenticated()
{
    global $session_manager;

    // Start session if needed
    if (!$session_manager->startSession()) {
        return false;
    }

    // Check authentication status
    return $session_manager->isAuthenticated();
}

/**
 * Get current authenticated user data
 * @return array|null User data or null if not authenticated
 */
function getCurrentAuthenticatedUser()
{
    if (!isUserAuthenticated()) {
        return null;
    }

    $user_id = getCurrentUserId();
    if (!$user_id) {
        return null;
    }

    return getUserData($user_id);
}

/**
 * Check if current user has admin privileges
 * @return bool True if user is admin or super admin
 */
function currentUserIsAdmin()
{
    $user_id = getCurrentUserId();
    if (!$user_id) {
        return false;
    }

    return isAdmin($user_id);
}

/**
 * Check if current user has super admin privileges
 * @return bool True if user is super admin
 */
function currentUserIsSuperAdmin()
{
    $user_id = getCurrentUserId();
    if (!$user_id) {
        return false;
    }

    return isSuperAdmin($user_id);
}

/**
 * Get current user's privilege level
 * @return string Privilege level ('super_admin', 'admin', 'user', 'guest')
 */
function getCurrentUserPrivilegeLevel()
{
    if (!isUserAuthenticated()) {
        return 'guest';
    }

    $user_id = getCurrentUserId();
    if (!$user_id) {
        return 'guest';
    }

    if (isSuperAdmin($user_id)) {
        return 'super_admin';
    } elseif (isAdmin($user_id)) {
        return 'admin';
    } else {
        return 'user';
    }
}

/**
 * ============================================================================
 * LOGIN AND LOGOUT UTILITIES
 * ============================================================================
 */

/**
 * Perform user login with complete validation
 * @param string $username Username or email
 * @param string $password Password
 * @param bool $remember_me Remember login
 * @return array Login result with status and messages
 */
function performUserLogin($username, $password, $remember_me = false)
{
    global $conn, $session_manager;
    /** @var mysqli $conn */

    $result = [
        'success' => false,
        'message' => '',
        'user_id' => null,
        'requires_2fa' => false,
        'redirect_url' => '',
        'error_code' => null
    ];

    try {
        // Validate input
        if (empty($username) || empty($password)) {
            $result['message'] = 'Username and password are required.';
            $result['error_code'] = 'MISSING_CREDENTIALS';
            return $result;
        }

        // Look up user
        $stmt = $conn->prepare("
            SELECT id, username, email, password, role_id,
                   is_active, login_attempts, locked_until,
                   callsign, first_name, last_name, last_login
            FROM auth_users 
            WHERE (username = ? OR email = ?) AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $result['message'] = 'Invalid username or password.';
            $result['error_code'] = 'USER_NOT_FOUND';
            logAuthActivity(0, 'login_user_not_found', 'Login attempt for non-existent user: ' . $username, null, false);
            return $result;
        }

        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $result['message'] = 'Account is temporarily locked. Please try again later.';
            $result['error_code'] = 'ACCOUNT_LOCKED';
            logAuthActivity($user['id'], 'login_account_locked', 'Attempted login on locked account', null, false);
            return $result;
        }

        // Verify password
        // Password column normalization: many legacy scripts still reference password_hash
        $stored_hash = $user['password_hash'] ?? ($user['password'] ?? null);
        if (!verifyPassword($password, $stored_hash)) {
            // Increment failed attempts
            $failed_attempts = $user['login_attempts'] + 1;
            $lock_until = null;

            if ($failed_attempts >= 5) {
                $lock_until = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
                $result['message'] = 'Too many failed attempts. Account locked for 30 minutes.';
                $result['error_code'] = 'ACCOUNT_LOCKED_FAILED_ATTEMPTS';
            } else {
                $result['message'] = 'Invalid username or password.';
                $result['error_code'] = 'INVALID_PASSWORD';
            }

            $stmt = $conn->prepare("UPDATE auth_users SET login_attempts = ?, locked_until = ? WHERE id = ?");
            $stmt->bind_param('isi', $failed_attempts, $lock_until, $user['id']);
            $stmt->execute();
            $stmt->close();

            logAuthActivity($user['id'], 'login_invalid_password', "Failed login attempt #{$failed_attempts}", null, false);
            return $result;
        }

        // Password is correct - clear failed attempts
        if ($user['login_attempts'] > 0 || $user['locked_until']) {
            $stmt = $conn->prepare("UPDATE auth_users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();
        }

        // ADDITIONAL VALIDATION: Check member status if user has a callsign
        if (!empty($user['callsign'])) {
            $stmt = $conn->prepare("SELECT status FROM members WHERE callsign = ? LIMIT 1");
            $stmt->bind_param('s', $user['callsign']);
            $stmt->execute();
            $member_result = $stmt->get_result();

            if ($member_result->num_rows > 0) {
                $member = $member_result->fetch_assoc();
                if ($member['status'] !== 'Active') {
                    $result['message'] = 'Your membership status is not active. Please contact the club to resolve this issue.';
                    $result['error_code'] = 'MEMBERSHIP_INACTIVE';
                    logAuthActivity($user['id'], 'login_membership_inactive', "Login denied - membership status: {$member['status']}", null, false);
                    $stmt->close();
                    return $result;
                }
            } else {
                // Callsign not found in members table - this could be for system admin accounts
                // Log but don't block (allows for system administrators without club membership)
                logAuthActivity($user['id'], 'login_no_membership_record', "Login successful but no membership record found for callsign: {$user['callsign']}", null, true);
            }
            $stmt->close();
        }

        // Check if password needs rehashing
        if (passwordNeedsRehash($stored_hash)) {
            $new_hash = hashPassword($password);
            $stmt = $conn->prepare("UPDATE auth_users SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $new_hash, $user['id']);
            $stmt->execute();
            $stmt->close();
        }

        // Initialize session (using simple PHP sessions instead of session manager)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Set basic session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['timeout'] = time() + 3600; // 1 hour timeout

        // Set role-based session variables
        $_SESSION['is_admin'] = ($user['role_id'] == 1);

        // Handle remember me functionality
        rememberMeHandleLoginSuccess($user['id'], $remember_me);

        // Update database - clear login attempts and set last login
        $stmt = $conn->prepare("UPDATE auth_users SET last_login = NOW(), login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $stmt->close();

        // Complete login (no 2FA in current schema)
        $result['success'] = true;
        $result['user_id'] = $user['id'];
        $result['message'] = 'Login successful!';

        // Determine redirect URL based on role_id (1=admin, 2=editor, 3=user)
        if ($user['role_id'] == 1 || $user['role_id'] == 2) {
            $result['redirect_url'] = '/administration/dashboard.php';
        } else {
            $result['redirect_url'] = '/authentication/dashboard.php';
        }

        logAuthActivity($user['id'], 'login_success', 'User logged in successfully', null, true);
        return $result;
    } catch (Exception $e) {
        // Temporarily surface the underlying error message to diagnose issues.
        // In production, you may want to revert to a generic message.
        $result['message'] = 'System error: ' . $e->getMessage();
        $result['error_code'] = 'SYSTEM_ERROR';
        logError("Login error: " . $e->getMessage(), 'auth');
        return $result;
    }
}

/**
 * Perform user logout with complete cleanup
 * @param int|null $user_id User ID (optional, uses current session if not provided)
 * @return bool True if logout successful
 */
function performUserLogout($user_id = null)
{
    global $session_manager;

    try {
        $user_id = $user_id ?? getCurrentUserId();

        if ($user_id) {
            logAuthActivity($user_id, 'logout', 'User logged out', null, true);
        }

        rememberMeHandleLogout($user_id);

        // Destroy session
        return $session_manager->destroySession();
    } catch (Exception $e) {
        logError("Logout error: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * ============================================================================
 * 2FA INTEGRATION UTILITIES
 * ============================================================================
 */

/**
 * Check if user needs 2FA verification
 * @param int|null $user_id User ID (optional)
 * @return bool True if 2FA is required
 */
function userRequires2FA($user_id = null)
{
    global $conn;
    /** @var mysqli $conn */

    $user_id = $user_id ?? getCurrentUserId();
    if (!$user_id) {
        return false;
    }

    try {
        // 2FA not implemented in current schema
        return false;
    } catch (Exception $e) {
        logError("Error checking 2FA requirement: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Verify 2FA code for user
 * @param int $user_id User ID
 * @param string $code TOTP or backup code
 * @return array Verification result
 */
function verify2FACode($user_id, $code)
{
    // 2FA is not implemented in current schema
    $result = [
        'success' => false,
        'message' => '2FA is not implemented in current schema',
        'is_backup_code' => false,
        'backup_codes_remaining' => 0
    ];

    return $result;
}

/**
 * Complete 2FA authentication process
 * @param int $user_id User ID
 * @param bool $trust_device Whether to trust this device
 * @return bool True if authentication completed
 */
function complete2FAAuthentication($user_id, $trust_device = false)
{
    try {
        // Clear 2FA pending status
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['2fa_user_id']);

        // Create trusted device if requested
        if ($trust_device && function_exists('createTrustedDevice')) {
            $ip_address = getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            createTrustedDevice($user_id, $ip_address, $user_agent);
        }

        logAuthActivity($user_id, '2fa_authentication_complete', 'Two-factor authentication completed successfully', null, true);
        return true;
    } catch (Exception $e) {
        logError("Error completing 2FA authentication: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * ============================================================================
 * SESSION UTILITIES
 * ============================================================================
 */

/**
 * Extend current user session
 * @param int $additional_seconds Additional seconds to extend
 * @return bool True if extended successfully
 */
function extendCurrentSession($additional_seconds = 1800)
{
    global $session_manager;
    return $session_manager->extendSession($additional_seconds);
}

/**
 * Check if current session is about to expire
 * @param int $warning_threshold Warning threshold in seconds
 * @return array Session expiration information
 */
function getSessionExpirationInfo($warning_threshold = 300)
{
    $info = [
        'is_authenticated' => false,
        'expires_in' => 0,
        'expires_at' => null,
        'is_expiring_soon' => false,
        'can_extend' => false
    ];

    if (!isUserAuthenticated()) {
        return $info;
    }

    $info['is_authenticated'] = true;

    if (isset($_SESSION['timeout'])) {
        $expires_at = $_SESSION['timeout'];
        $current_time = time();
        $expires_in = $expires_at - $current_time;

        $info['expires_in'] = max(0, $expires_in);
        $info['expires_at'] = date('Y-m-d H:i:s', $expires_at);
        $info['is_expiring_soon'] = $expires_in <= $warning_threshold && $expires_in > 0;
        $info['can_extend'] = $expires_in > 0;
    }

    return $info;
}

/**
 * Get detailed session information for debugging
 * @return array Complete session information
 */
function getDetailedSessionInfo()
{
    global $session_manager;

    $info = $session_manager->getSessionInfo();
    $info['expiration_info'] = getSessionExpirationInfo();
    $info['user_data'] = getCurrentAuthenticatedUser();
    $info['privilege_level'] = getCurrentUserPrivilegeLevel();

    return $info;
}

/**
 * ============================================================================
 * PERMISSION CHECKING UTILITIES
 * ============================================================================
 */

/**
 * Check if current user can access a specific functionality
 * @param string $functionality Functionality name
 * @return bool True if user has access
 */
function currentUserCanAccess($functionality)
{
    $user_id = getCurrentUserId();
    if (!$user_id) {
        return false;
    }

    return canAccessFunctionality($user_id, $functionality);
}

/**
 * Require specific permission with automatic redirect
 * @param string $permission Permission name
 * @param string $redirect_url Redirect URL on failure
 * @return bool True if permission granted
 */
function requirePermission($permission, $redirect_url = '/authentication/dashboard.php')
{
    if (!isUserAuthenticated()) {
        setToastMessage('info', 'Login Required', 'Please log in to access this page.');
        header('Location: /authentication/login.php');
        exit();
    }

    $user_id = getCurrentUserId();
    if (!hasPermission($user_id, $permission)) {
        setToastMessage('danger', 'Access Denied', 'You do not have permission to access this resource.');
        header('Location: ' . $redirect_url);
        exit();
    }

    return true;
}

/**
 * Require admin access with automatic redirect
 * @param bool $require_super_admin Whether super admin is required
 * @param string $redirect_url Redirect URL on failure
 * @return bool True if access granted
 */
function requireAdminAccess($require_super_admin = false, $redirect_url = '/authentication/dashboard.php')
{
    if (!isUserAuthenticated()) {
        setToastMessage('info', 'Login Required', 'Please log in to access this page.');
        header('Location: /authentication/login.php');
        exit();
    }

    $user_id = getCurrentUserId();

    if ($require_super_admin && !isSuperAdmin($user_id)) {
        setToastMessage('danger', 'Access Denied', 'Super Administrator privileges required.');
        header('Location: ' . $redirect_url);
        exit();
    }

    if (!$require_super_admin && !isAdmin($user_id)) {
        setToastMessage('danger', 'Access Denied', 'Administrator privileges required.');
        header('Location: ' . $redirect_url);
        exit();
    }

    return true;
}

/**
 * ============================================================================
 * USER MANAGEMENT UTILITIES
 * ============================================================================
 */

/**
 * Create new user account
 * @param array $user_data User data array
 * @return array Creation result
 */
function createUserAccount($user_data)
{
    global $conn;
    /** @var mysqli $conn */

    $result = [
        'success' => false,
        'message' => '',
        'user_id' => null
    ];

    try {
        // Validate required fields
        $required_fields = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required_fields as $field) {
            if (empty($user_data[$field])) {
                $result['message'] = "Required field missing: $field";
                return $result;
            }
        }

        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM auth_users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $user_data['username'], $user_data['email']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $stmt->close();
            $result['message'] = 'Username or email already exists.';
            return $result;
        }
        $stmt->close();

        // Hash password
        $password_hashed = hashPassword($user_data['password']);

        // Resolve a safe default role_id instead of hardcoding a value (avoids FK violations)
        $resolved_role_id = null; // let it be NULL if nothing valid is found
        try {
            // Check if auth_roles table exists
            $tbl = @$conn->query("SHOW TABLES LIKE 'auth_roles'");
            if ($tbl && $tbl->num_rows > 0) {
                // Detect role name column
                $has_role_name = false;
                if ($rc = @$conn->query("SHOW COLUMNS FROM auth_roles LIKE 'role_name'")) {
                    $has_role_name = ($rc->num_rows > 0);
                    $rc->close();
                }

                // Try common default role names in order of preference
                $candidates = ['user', 'member', 'basic'];
                if ($has_role_name) {
                    foreach ($candidates as $cand) {
                        $candLower = strtolower($cand);
                        $st = $conn->prepare("SELECT id FROM auth_roles WHERE LOWER(role_name) = ? LIMIT 1");
                        if ($st) {
                            $st->bind_param('s', $candLower);
                            $st->execute();
                            $row = $st->get_result()->fetch_assoc();
                            $st->close();
                            if ($row && isset($row['id'])) {
                                $resolved_role_id = (int)$row['id'];
                                break;
                            }
                        }
                    }
                }

                // If still not resolved, pick the first available role id as a last resort
                if ($resolved_role_id === null) {
                    if ($st = $conn->prepare("SELECT id FROM auth_roles ORDER BY id LIMIT 1")) {
                        $st->execute();
                        $row = $st->get_result()->fetch_assoc();
                        $st->close();
                        if ($row && isset($row['id'])) {
                            $resolved_role_id = (int)$row['id'];
                        }
                    }
                }
            }
            if (!isset($resolved_role_id)) {
                $resolved_role_id = null;
            }
        } catch (Exception $rex) {
            // If lookup fails for any reason, fall back to NULL (schema uses ON DELETE SET NULL)
            error_log('Role resolution failed during user creation: ' . $rex->getMessage());
            $resolved_role_id = null;
        }

        // Prepare insert with dynamic role_id (NULL allowed by FK definition)
        $stmt = $conn->prepare("
            INSERT INTO auth_users (username, email, password, first_name, last_name, callsign, role_id, is_active, email_verified)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0)
        ");

        $callsign = $user_data['callsign'] ?? '';

        $stmt->bind_param(
            'ssssssi',
            $user_data['username'],
            $user_data['email'],
            $password_hashed,
            $user_data['first_name'],
            $user_data['last_name'],
            $callsign,
            $resolved_role_id
        );

        if ($stmt->execute()) {
            $result['success'] = true;
            $result['user_id'] = $conn->insert_id;
            $result['message'] = 'User account created successfully.';

            if ($result['user_id'] !== null) {
                logAuthActivity((int)$result['user_id'], 'user_created', 'New user account created: ' . $user_data['username'], null, true);
            }
        } else {
            $result['message'] = 'Failed to create user account: ' . $stmt->error;
            $result['error_details'] = 'MySQL Error: ' . $conn->error . ' (Errno: ' . $conn->errno . ')';
            error_log("createUserAccount SQL Error: " . $stmt->error);
            error_log("createUserAccount Full Error: " . $conn->error);
        }

        $stmt->close();
        return $result;
    } catch (Exception $e) {
        $result['message'] = 'An error occurred while creating the account: ' . $e->getMessage();
        $result['error_details'] = 'Exception: ' . $e->getFile() . ' line ' . $e->getLine();
        error_log("createUserAccount Exception: " . $e->getMessage());
        error_log("createUserAccount Stack Trace: " . $e->getTraceAsString());
        logError("User creation error: " . $e->getMessage(), 'auth');
        return $result;
    }
}

/**
 * Update user account
 * @param int $user_id User ID
 * @param array $update_data Data to update
 * @return bool True if updated successfully
 */
function updateUserAccount($user_id, $update_data)
{
    global $conn;
    /** @var mysqli $conn */

    try {
        $allowed_fields = ['first_name', 'last_name', 'email', 'callsign', 'phone', 'address', 'city', 'state', 'zip'];
        $update_fields = [];
        $params = [];

        foreach ($update_data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_fields[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($update_fields)) {
            return false;
        }

        $sql = "UPDATE auth_users SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $user_id;

        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);

        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            logActivity($user_id, 'user_updated', 'auth_users', $user_id, 'User account information updated');
        }

        return $result;
    } catch (Exception $e) {
        logError("User update error: " . $e->getMessage(), 'auth');
        return false;
    }
}

/**
 * Change user password
 * @param int $user_id User ID
 * @param string $current_password Current password
 * @param string $new_password New password
 * @return array Password change result
 */
function changeUserPassword($user_id, $current_password, $new_password)
{
    global $conn;
    /** @var mysqli $conn */

    $result = [
        'success' => false,
        'message' => ''
    ];

    try {
        // Get current password hash
        $stmt = $conn->prepare("SELECT password FROM auth_users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $result['message'] = 'User not found.';
            return $result;
        }

        // Verify current password
        if (!verifyPassword($current_password, $user['password'])) {
            $result['message'] = 'Current password is incorrect.';
            logAuthActivity($user_id, 'password_change_failed', 'Incorrect current password provided', null, false);
            return $result;
        }

        // Hash new password
        $new_hash = hashPassword($new_password);

        // Update password
        $stmt = $conn->prepare("UPDATE auth_users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $new_hash, $user_id);

        if ($stmt->execute()) {
            $result['success'] = true;
            $result['message'] = 'Password changed successfully.';
            logAuthActivity($user_id, 'password_changed', 'User password changed successfully', null, true);
        } else {
            $result['message'] = 'Failed to update password.';
        }

        $stmt->close();
        return $result;
    } catch (Exception $e) {
        $result['message'] = 'An error occurred while changing the password.';
        logError("Password change error: " . $e->getMessage(), 'auth');
        return $result;
    }
}

/**
 * ============================================================================
 * UTILITY FUNCTIONS
 * ============================================================================
 */

/**
 * Check if trusted device functionality exists
 * @return bool True if trusted device functionality is available
 */
function isTrustedDeviceSupported()
{
    return tableExists('auth_trusted_devices') && function_exists('isTrustedDevice');
}

/**
 * Clean up authentication data
 * @return array Cleanup statistics
 */
function cleanupAuthData()
{
    $stats = [
        'expired_sessions' => 0,
        'expired_devices' => 0,
        'old_logs' => 0
    ];

    try {
        // Clean expired sessions
        $stats['expired_sessions'] = cleanExpiredSessions();

        // Clean expired trusted devices if available
        if (function_exists('cleanup2FAData')) {
            cleanup2FAData();
        }

        // Clean old logs
        $stats['old_logs'] = cleanOldLogs(30);
    } catch (Exception $e) {
        logError("Auth cleanup error: " . $e->getMessage(), 'auth');
    }

    return $stats;
}

/**
 * Get authentication system status
 * @return array System status information
 */
function getAuthSystemStatus()
{
    $status = [
        'database_connected' => false,
        'session_manager_available' => false,
        'totp_functions_available' => false,
        'trusted_devices_supported' => false,
        'active_sessions' => 0,
        'total_users' => 0,
        'admin_users' => 0,
        'super_admin_users' => 0
    ];

    try {
        global $conn;
        /** @var mysqli $conn */

        // Check database connection
        $status['database_connected'] = $conn && $conn->ping();

        // Check session manager
        $status['session_manager_available'] = class_exists('SessionManager');

        // Check TOTP functions
        $status['totp_functions_available'] = function_exists('generateTOTPSecret') && function_exists('verifyTOTPCodeWithSecret');

        // Check trusted devices
        $status['trusted_devices_supported'] = isTrustedDeviceSupported();

        if ($status['database_connected']) {
            // Count active sessions
            $result = $conn->query("SELECT COUNT(*) as count FROM auth_sessions WHERE expires_at > NOW()");
            if ($result) {
                $row = $result->fetch_assoc();
                $status['active_sessions'] = $row['count'];
            }

            // Count users
            $result = $conn->query("SELECT COUNT(*) as count FROM auth_users WHERE is_active = 1");
            if ($result) {
                $row = $result->fetch_assoc();
                $status['total_users'] = $row['count'];
            }

            // Count admin users
            $result = $conn->query("SELECT COUNT(*) as count FROM auth_users WHERE is_active = 1 AND role_id = 1");
            if ($result) {
                $row = $result->fetch_assoc();
                $status['admin_users'] = $row['count'];
            }

            // Count super admin users (role_id = 1 are admins in our schema)
            if (tableExists('auth_users')) {
                // In our schema, we don't distinguish super admin from admin
                // Both use role_id = 1, so we'll just show the same count
                $status['super_admin_users'] = $status['admin_users'] ?? 0;
            }
        }
    } catch (Exception $e) {
        logError("Error getting auth system status: " . $e->getMessage(), 'auth');
    }

    return $status;
}

/**
 * ============================================================================
 * BACKWARDS COMPATIBILITY
 * ============================================================================
 */

// Alias functions for backwards compatibility
if (!function_exists('authenticateUser')) {
    function authenticateUser($username, $password, $remember_me = false)
    {
        return performUserLogin($username, $password, $remember_me);
    }
}

if (!function_exists('logoutUser')) {
    function logoutUser($user_id = null)
    {
        return performUserLogout($user_id);
    }
}

if (!function_exists('checkAuthentication')) {
    function checkAuthentication()
    {
        return isUserAuthenticated();
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser()
    {
        return getCurrentAuthenticatedUser();
    }
}

/**
 * ============================================================================
 * INITIALIZATION AND CLEANUP
 * ============================================================================
 */

// Automatically clean up old data occasionally (1% chance)
if (rand(1, 100) === 1) {
    if (function_exists('cleanExpiredSessions')) {
        cleanExpiredSessions();
    }
}

// Log that auth_utils has been loaded (debug mode only)
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_log("Auth Utils loaded successfully");
}

if (!function_exists('cleanExpiredSessions')) {
    function cleanExpiredSessions()
    {
        // Implement your session cleanup logic here
        return 0; // Return the number of expired sessions cleaned up
    }
}

if (!function_exists('logAuthActivity')) {
    /**
     * Log an authentication-related activity.
     * @param int $user_id
     * @param string $action
     * @param string $details
     * @param string|null $ip
     * @param bool $success
     */
    function logAuthActivity($user_id, $action, $details = '', $ip = null, $success = true)
    {
        global $conn;
        /** @var mysqli $conn */

        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $stmt = $conn->prepare("INSERT INTO auth_activity_log (user_id, action, details, ip_address, success, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('isssi', $user_id, $action, $details, $ip, $success);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * ==========================================================================
 * SELF-TEST AND HEALTH ENDPOINTS (safe to invoke directly)
 * ==========================================================================
 * Usage (web):   /authentication/auth_utils.php?ping=1  -> returns "pong"
 *                /authentication/auth_utils.php?health=1 -> returns JSON status
 * Usage (CLI):   php authentication/auth_utils.php       -> prints JSON status
 * These endpoints only respond when this file is executed directly, and will
 * not interfere when the file is included by other scripts.
 */
(function () {
    $is_cli = (php_sapi_name() === 'cli');
    $is_self_test = _authUtilsIsSelfTest();

    if ($is_cli) {
        // CLI: print health JSON
        $status = getAuthSystemStatus();
        $status['file'] = basename(__FILE__);
        $status['mode'] = 'cli';
        echo json_encode($status, JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    // Web: respond only when invoked directly
    if ($is_self_test) {
        if (isset($_GET['ping'])) {
            header('Content-Type: text/plain');
            echo 'pong';
            exit;
        }
        if (isset($_GET['health'])) {
            header('Content-Type: application/json');
            $status = getAuthSystemStatus();
            $status['file'] = basename(__FILE__);
            $status['mode'] = 'web';
            echo json_encode($status, JSON_PRETTY_PRINT);
            exit;
        }
    }
})();
