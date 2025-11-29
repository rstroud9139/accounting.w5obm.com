<?php

/**
 * Authentication Compatibility Layer - W5OBM Amateur Radio Club
 * File: /authentication/utils/auth_compatibility.php
 * Purpose: Ensure backwards compatibility and resolve any function conflicts
 * CREATED: To provide seamless integration with existing codebase
 */

// Prevent multiple includes
if (defined('AUTH_COMPATIBILITY_LOADED')) {
    return;
}
define('AUTH_COMPATIBILITY_LOADED', true);

// Ensure all required files are loaded
if (!defined('AUTH_UTILS_LOADED')) {
    require_once __DIR__ . '/../auth_utils.php';
}

if (!defined('W5OBM_TOTP_UTILS')) {
    require_once __DIR__ . '/../totp_utils.php';
}

/**
 * ============================================================================
 * LEGACY FUNCTION COMPATIBILITY
 * ============================================================================
 */

// Legacy authentication check functions
if (!function_exists('checkUserAuth')) {
    function checkUserAuth()
    {
        return isUserAuthenticated();
    }
}

if (!function_exists('getUserID')) {
    function getUserID()
    {
        return getCurrentUserId();
    }
}

if (!function_exists('checkAdminRights')) {
    function checkAdminRights($user_id = null)
    {
        return $user_id ? isAdmin($user_id) : currentUserIsAdmin();
    }
}

// Legacy session functions
if (!function_exists('startUserSession')) {
    function startUserSession($user_id, $remember = false)
    {
        global $session_manager;
        if (!$session_manager) {
            $session_manager = SessionManager::getInstance();
        }
        return $session_manager->loginUser($user_id, $remember);
    }
}

if (!function_exists('endUserSession')) {
    function endUserSession()
    {
        return performUserLogout();
    }
}

// Legacy password functions (ensure they exist)
if (!function_exists('createPasswordHash')) {
    function createPasswordHash($password)
    {
        return hashPassword($password);
    }
}

if (!function_exists('checkPassword')) {
    function checkPassword($password, $hash)
    {
        return verifyPassword($password, $hash);
    }
}

/**
 * ============================================================================
 * ENHANCED UTILITY FUNCTIONS
 * ============================================================================
 */

/**
 * Safe function wrapper to prevent fatal errors
 * @param string $function_name Function to call
 * @param array $args Arguments to pass
 * @param mixed $default Default return value if function doesn't exist
 * @return mixed Function result or default value
 */
function safeCall($function_name, $args = [], $default = false)
{
    if (function_exists($function_name)) {
        return call_user_func_array($function_name, $args);
    }
    return $default;
}

/**
 * Check if all authentication components are loaded
 * @return array Status of each component
 */
function checkAuthComponentsLoaded()
{
    return [
        'helper_functions' => function_exists('isAuthenticated'),
        'session_manager' => class_exists('SessionManager'),
        'auth_utils' => defined('AUTH_UTILS_LOADED'),
        'totp_utils' => defined('W5OBM_TOTP_UTILS'),
        'database_connection' => isset($GLOBALS['conn']) && $GLOBALS['conn']->ping()
    ];
}

/**
 * Get comprehensive authentication status
 * @param int|null $user_id User ID to check (optional)
 * @return array Complete authentication status
 */
function getComprehensiveAuthStatus($user_id = null)
{
    $user_id = $user_id ?? getCurrentUserId();

    $status = [
        'authenticated' => isUserAuthenticated(),
        'user_id' => $user_id,
        'is_admin' => false,
        'is_super_admin' => false,
        'has_2fa' => false,
        'session_expires' => null,
        'privilege_level' => 'guest'
    ];

    if ($user_id) {
        $status['is_admin'] = isAdmin($user_id);
        $status['is_super_admin'] = isSuperAdmin($user_id);
        $status['has_2fa'] = userNeeds2FA($user_id);
        $status['privilege_level'] = getCurrentUserPrivilegeLevel();

        if (isset($_SESSION['timeout'])) {
            $status['session_expires'] = date('Y-m-d H:i:s', $_SESSION['timeout']);
        }
    }

    return $status;
}

/**
 * Initialize authentication system with error handling
 * @return array Initialization result
 */
function initializeAuthSystem()
{
    $result = [
        'success' => false,
        'errors' => [],
        'warnings' => []
    ];

    try {
        // Check database connection
        global $conn;
        if (!$conn || !$conn->ping()) {
            $result['errors'][] = 'Database connection failed';
            return $result;
        }

        // Initialize session
        if (!sm_start_session()) {
            $result['errors'][] = 'Session initialization failed';
            return $result;
        }

        // Check required tables
        $required_tables = ['auth_users', 'auth_sessions', 'auth_activity_log'];
        foreach ($required_tables as $table) {
            if (!tableExists($table)) {
                $result['errors'][] = "Required table missing: $table";
            }
        }

        // Check optional tables
        $optional_tables = ['auth_trusted_devices', 'auth_permissions', 'auth_roles'];
        foreach ($optional_tables as $table) {
            if (!tableExists($table)) {
                $result['warnings'][] = "Optional table missing: $table";
            }
        }

        // Check function availability
        $required_functions = [
            'isAuthenticated',
            'getCurrentUserId',
            'isAdmin',
            'hashPassword',
            'verifyPassword',
            'logAuthActivity'
        ];

        foreach ($required_functions as $func) {
            if (!function_exists($func)) {
                $result['errors'][] = "Required function missing: $func";
            }
        }

        $result['success'] = empty($result['errors']);
    } catch (Exception $e) {
        $result['errors'][] = 'Initialization error: ' . $e->getMessage();
    }

    return $result;
}

/**
 * Generate authentication system diagnostic report
 * @return array Diagnostic information
 */
function generateAuthDiagnostics()
{
    $diagnostics = [
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'session_status' => session_status(),
        'session_id' => session_id(),
        'components' => checkAuthComponentsLoaded(),
        'current_user' => getComprehensiveAuthStatus(),
        'database_status' => getDatabaseStatus(),
        'memory_usage' => formatBytes(memory_get_usage(true))
    ];

    return $diagnostics;
}

/**
 * Clean and optimize authentication system
 * @return array Cleanup results
 */
function cleanupAuthSystem()
{
    $results = [
        'expired_sessions_cleaned' => 0,
        'old_logs_cleaned' => 0,
        'errors' => []
    ];

    try {
        // Clean expired sessions
        if (function_exists('cleanExpiredSessions')) {
            $results['expired_sessions_cleaned'] = cleanExpiredSessions();
        }

        // Clean old logs
        if (function_exists('cleanOldLogs')) {
            $results['old_logs_cleaned'] = cleanOldLogs(30);
        }

        // Clean 2FA data if available
        if (function_exists('cleanup2FAData')) {
            cleanup2FAData();
        }
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }

    return $results;
}

/**
 * ============================================================================
 * INSTALLATION AND UPGRADE HELPERS
 * ============================================================================
 */

/**
 * Check if authentication system needs updates
 * @return array Update requirements
 */
function checkAuthSystemUpdates()
{
    global $conn;

    $updates_needed = [];

    try {
        // Check for Super Admin column
        $result = $conn->query("SHOW COLUMNS FROM auth_users LIKE 'is_SuperAdmin'");
        if (!$result || $result->num_rows === 0) {
            $updates_needed[] = [
                'type' => 'database',
                'description' => 'Add is_SuperAdmin column to auth_users table',
                'sql' => 'ALTER TABLE auth_users ADD COLUMN is_SuperAdmin BOOLEAN DEFAULT FALSE AFTER is_Admin'
            ];
        }

        // Check for trusted devices table
        if (!tableExists('auth_trusted_devices')) {
            $updates_needed[] = [
                'type' => 'database',
                'description' => 'Create auth_trusted_devices table for 2FA trusted devices',
                'sql' => 'CREATE TABLE auth_trusted_devices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    device_token VARCHAR(255) NOT NULL,
                    device_name VARCHAR(255),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
                    INDEX idx_user_device (user_id, device_token),
                    INDEX idx_expires (expires_at)
                )'
            ];
        }

        // Check for backup codes column
        $result = $conn->query("SHOW COLUMNS FROM auth_users LIKE 'two_factor_backup_codes'");
        if (!$result || $result->num_rows === 0) {
            $updates_needed[] = [
                'type' => 'database',
                'description' => 'Add two_factor_backup_codes column to auth_users table',
                'sql' => 'ALTER TABLE auth_users ADD COLUMN two_factor_backup_codes JSON AFTER two_factor_secret'
            ];
        }
    } catch (Exception $e) {
        $updates_needed[] = [
            'type' => 'error',
            'description' => 'Error checking for updates: ' . $e->getMessage()
        ];
    }

    return $updates_needed;
}

/**
 * ============================================================================
 * EMERGENCY FUNCTIONS
 * ============================================================================
 */

/**
 * Create emergency admin user (use only in emergencies)
 * @param string $username Emergency username
 * @param string $password Emergency password
 * @param string $email Emergency email
 * @return array Creation result
 */
function createEmergencyAdmin($username, $password, $email)
{
    global $conn;

    $result = [
        'success' => false,
        'message' => '',
        'user_id' => null
    ];

    try {
        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM auth_users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $stmt->close();
            $result['message'] = 'User already exists';
            return $result;
        }
        $stmt->close();

        // Create emergency admin
        $password_hash = hashPassword($password);
        $stmt = $conn->prepare("
            INSERT INTO auth_users 
            (username, email, password, first_name, last_name, is_Admin, is_SuperAdmin, status, created_at) 
            VALUES (?, ?, ?, 'Emergency', 'Admin', 1, 1, 'active', NOW())
        ");

        $stmt->bind_param('sss', $username, $email, $password_hash);

        if ($stmt->execute()) {
            $result['success'] = true;
            $result['user_id'] = $conn->insert_id;
            $result['message'] = 'Emergency admin created successfully';

            // Log using whichever logging function is available
            if (function_exists('logAuthActivity')) {
                // Ensure user_id is an integer for logging (fallback to 0 if null)
                $log_user_id = is_int($result['user_id']) ? $result['user_id'] : (int)$result['user_id'];
                if ($log_user_id === 0) {
                    // If we somehow lack an ID, skip structured logging to avoid type errors
                    error_log('Emergency admin created but user_id missing for logAuthActivity');
                } else {
                    logAuthActivity(
                        $log_user_id,
                        'emergency_admin_created',
                        'Emergency admin account created: ' . $username,
                        null,
                        true
                    );
                }
            } elseif (function_exists('logActivity')) {
                // Fallback to generic logger (actor, action, table, subject_id, details)
                logActivity(
                    $result['user_id'],
                    'emergency_admin_created',
                    'auth_activity_log',
                    $result['user_id'],
                    'Emergency admin account created: ' . $username
                );
            } elseif (function_exists('log_auth_activity')) {
                // Legacy snake_case logger
                log_auth_activity(
                    $result['user_id'],
                    'emergency_admin_created',
                    'Emergency admin account created: ' . $username,
                    null,
                    true
                );
            } else {
                error_log('Emergency admin created, but no logging function available');
            }
        } else {
            $result['message'] = 'Failed to create emergency admin';
        }

        $stmt->close();
    } catch (Exception $e) {
        $result['message'] = 'Error creating emergency admin: ' . $e->getMessage();
    }

    return $result;
}

// Automatically run maintenance occasionally (1% chance)
if (rand(1, 100) === 1) {
    try {
        cleanupAuthSystem();
    } catch (Exception $e) {
        // Silent fail for maintenance
        error_log("Auth maintenance error: " . $e->getMessage());
    }
}

// Log successful compatibility layer load
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_log("Authentication compatibility layer loaded successfully");
}
