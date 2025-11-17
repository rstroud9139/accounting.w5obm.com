<?php

/**
 * Session Manager Utility
 * File: /include/session_manager.php
 * Purpose: Centralized session management to fix session issues
 * 
 * This utility addresses the session startup and persistence issues
 */

class SessionManager
{
    private static $instance = null;
    private $conn;
    private $session_started = false;
    private $debug_mode = false;

    private function __construct()
    {
        // Set session configuration before starting
        $this->configureSession();

        // Enable debug mode based on environment
        $this->debug_mode = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set database connection for session management
     * @param mysqli $conn Database connection
     */
    public function setDatabaseConnection($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Configure session settings before starting
     */
    private function configureSession()
    {
        // Only configure if session hasn't started yet
        if (session_status() === PHP_SESSION_NONE) {
            // Use session_init.php for consistent configuration
            require_once __DIR__ . '/session_init.php';
        }
    }

    /**
     * Start session with error handling
     * @return bool Success status
     */
    public function startSession()
    {
        if ($this->session_started || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        try {
            // Ensure we have database connection
            if (!$this->conn) {
                require_once __DIR__ . '/dbconn.php';
                // Try multiple ways to get the connection
                global $conn;
                $this->conn = $this->conn ?? $conn ?? $GLOBALS['conn'] ?? null;
            }

            // Start the session
            if (!session_start()) {
                $this->debugLog("Failed to start session");
                return false;
            }

            $this->session_started = true;
            $this->debugLog("Session started successfully: " . session_id());

            // Initialize session security
            $this->initializeSessionSecurity();

            // Clean up expired sessions
            $this->cleanupExpiredSessions();

            return true;
        } catch (Exception $e) {
            $this->debugLog("Session start error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize session security measures
     */
    private function initializeSessionSecurity()
    {
        $current_ip = $this->getClientIP();
        $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check for session hijacking
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $current_ip) {
            $this->debugLog("IP address mismatch detected - possible hijacking");
            $this->destroySession();
            return;
        }

        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $current_ua) {
            $this->debugLog("User agent mismatch detected - possible hijacking");
            $this->destroySession();
            return;
        }

        // Set security markers
        $_SESSION['ip_address'] = $current_ip;
        $_SESSION['user_agent'] = $current_ua;

        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        } elseif ((time() - $_SESSION['last_regeneration']) > 1800) { // 30 minutes
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
            $this->debugLog("Session ID regenerated for security");
        }
    }

    /**
     * Login user and create session
     * @param int $user_id User ID
     * @param bool $remember_me Whether to extend session
     * @return bool Success status
     */
    public function loginUser($user_id, $remember_me = false)
    {
        if (!$this->startSession()) {
            return false;
        }

        if (!$this->conn) {
            $this->debugLog("Database connection not available for login");
            return false;
        }

        try {
            // Get user data with status check
            $stmt = $this->conn->prepare("SELECT * FROM auth_users WHERE id = ? AND status = 'active'");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $this->debugLog("User not found or not active: $user_id");
                return false;
            }

            // Clear any existing session data
            session_unset();

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            $_SESSION['callsign'] = $user['callsign'] ?? '';
            $is_super = (bool)($user['is_SuperAdmin'] ?? false);
            $is_admin_flag = isset($user['is_admin']) ? (bool)$user['is_admin'] : (isset($user['is_Admin']) ? (bool)$user['is_Admin'] : ((isset($user['role_id']) && (int)$user['role_id'] === 1)));
            $_SESSION['is_super_admin'] = $is_super;
            $_SESSION['is_admin'] = $is_super || $is_admin_flag;
            $_SESSION['role'] = $is_super ? 'super_admin' : ($is_admin_flag ? 'admin' : 'user');
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['timeout'] = time() + ($remember_me ? 86400 : 7200); // 24h or 2h
            $_SESSION['authenticated'] = true;

            // Store session in database
            $this->storeSessionInDatabase($user_id, $remember_me);

            // Update last login
            $this->updateLastLogin($user_id);

            $this->debugLog("User logged in successfully: {$user['username']} (ID: $user_id)");
            return true;
        } catch (Exception $e) {
            $this->debugLog("Login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store session in database
     * @param int $user_id User ID
     * @param bool $remember_me Extended session
     */
    private function storeSessionInDatabase($user_id, $remember_me = false)
    {
        if (!$this->conn) return;

        try {
            $session_id = session_id();
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $expires_at = $remember_me
                ? date('Y-m-d H:i:s', time() + 86400)
                : date('Y-m-d H:i:s', time() + 7200);

            $stmt = $this->conn->prepare("
                INSERT INTO auth_sessions (session_id, user_id, ip_address, user_agent, expires_at, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()
            ");

            $stmt->bind_param('sisss', $session_id, $user_id, $ip_address, $user_agent, $expires_at);
            $stmt->execute();
            $stmt->close();

            $this->debugLog("Session stored in database");
        } catch (Exception $e) {
            $this->debugLog("Error storing session in database: " . $e->getMessage());
        }
    }

    /**
     * Update user's last login information
     * @param int $user_id User ID
     */
    private function updateLastLogin($user_id)
    {
        if (!$this->conn) return;

        try {
            $stmt = $this->conn->prepare("UPDATE auth_users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
            $client_ip = $this->getClientIP();
            $stmt->bind_param('si', $client_ip, $user_id);
            $stmt->execute();
            $stmt->close();

            $this->debugLog("Last login updated for user: $user_id");
        } catch (Exception $e) {
            $this->debugLog("Error updating last login: " . $e->getMessage());
        }
    }

    /**
     * Check if user is authenticated
     * @return bool Authentication status
     */
    public function isAuthenticated()
    {
        if (!$this->startSession()) {
            return false;
        }

        // Check basic session variables
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['authenticated'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
            $this->debugLog("Session expired for user: " . $_SESSION['user_id']);
            $this->destroySession();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Get current user ID
     * @return int|null User ID or null
     */
    public function getCurrentUserId()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return (int)$_SESSION['user_id'];
    }

    /**
     * Check if current user is admin
     * @return bool Admin status
     */
    public function isAdmin()
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        return (bool)($_SESSION['is_admin'] ?? false);
    }

    /**
     * Extend current session
     * @param int $additional_time Additional seconds
     * @return bool Success status
     */
    public function extendSession($additional_time = 1800)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $_SESSION['timeout'] = time() + $additional_time;
        $_SESSION['last_activity'] = time();

        // Update database session
        if ($this->conn) {
            try {
                $session_id = session_id();
                $expires_at = date('Y-m-d H:i:s', $_SESSION['timeout']);

                $stmt = $this->conn->prepare("UPDATE auth_sessions SET expires_at = ?, updated_at = NOW() WHERE session_id = ?");
                $stmt->bind_param('ss', $expires_at, $session_id);
                $stmt->execute();
                $stmt->close();

                $this->debugLog("Session extended successfully");
                return true;
            } catch (Exception $e) {
                $this->debugLog("Error extending session: " . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Destroy user session
     * @return bool Success status
     */
    public function destroySession()
    {
        if (!$this->session_started && session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }

        try {
            $session_id = session_id();
            $user_id = $_SESSION['user_id'] ?? null;

            // Remove from database
            if ($this->conn && $session_id) {
                $stmt = $this->conn->prepare("DELETE FROM auth_sessions WHERE session_id = ?");
                $stmt->bind_param('s', $session_id);
                $stmt->execute();
                $stmt->close();
            }

            // Clear session data
            $_SESSION = array();

            // Destroy session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }

            // Destroy session
            session_destroy();
            $this->session_started = false;

            $this->debugLog("Session destroyed for user: " . ($user_id ?? 'unknown'));
            return true;
        } catch (Exception $e) {
            $this->debugLog("Error destroying session: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired sessions from database
     */
    private function cleanupExpiredSessions()
    {
        if (!$this->conn) return;

        try {
            // Only clean up occasionally (random 1% chance)
            if (rand(1, 100) <= 1) {
                $stmt = $this->conn->prepare("DELETE FROM auth_sessions WHERE expires_at < NOW()");
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();

                if ($affected > 0) {
                    $this->debugLog("Cleaned up $affected expired sessions");
                }
            }
        } catch (Exception $e) {
            $this->debugLog("Error cleaning up sessions: " . $e->getMessage());
        }
    }

    /**
     * Get client IP address
     * @return string IP address
     */
    private function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }

    /**
     * Debug logging
     * @param string $message Log message
     */
    private function debugLog($message)
    {
        if ($this->debug_mode) {
            error_log("[SessionManager] $message");
        }
    }

    /**
     * Get session information for debugging
     * @return array Session info
     */
    public function getSessionInfo()
    {
        return [
            'session_started' => $this->session_started,
            'session_status' => session_status(),
            'session_id' => session_id(),
            'session_name' => session_name(),
            'is_authenticated' => $this->isAuthenticated(),
            'user_id' => $this->getCurrentUserId(),
            'is_admin' => $this->isAdmin(),
            'timeout' => $_SESSION['timeout'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null
        ];
    }
}

// Global convenience functions that use the SessionManager
function sm_start_session()
{
    return SessionManager::getInstance()->startSession();
}

function sm_is_authenticated()
{
    return SessionManager::getInstance()->isAuthenticated();
}

function sm_get_user_id()
{
    return SessionManager::getInstance()->getCurrentUserId();
}

function sm_is_admin()
{
    return SessionManager::getInstance()->isAdmin();
}

function sm_login_user($user_id, $remember_me = false)
{
    return SessionManager::getInstance()->loginUser($user_id, $remember_me);
}

function sm_extend_session($additional_time = 1800)
{
    return SessionManager::getInstance()->extendSession($additional_time);
}

function sm_destroy_session()
{
    return SessionManager::getInstance()->destroySession();
}

function sm_get_session_info()
{
    return SessionManager::getInstance()->getSessionInfo();
}
