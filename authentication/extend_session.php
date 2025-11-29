<?php

/**
 * AJAX Session Extension Endpoint
 * File: /authentication/extend_session.php
 * Purpose: Extends user session timeout via AJAX call
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'allowed_methods' => ['POST']
    ]);
    exit();
}

// Include required files
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/../include/session_manager.php';

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Use standardized authentication check
    if (!isAuthenticated()) {
        throw new Exception('Not authenticated');
    }

    $user_id = getCurrentUserId();

    // Verify user is still active in database
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, email, is_active FROM auth_users WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user_data) {
        throw new Exception('User not found or inactive');
    }

    // CSRF token validation for security
    $input = json_decode(file_get_contents('php://input'), true);
    $csrf_token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? null;

    if (!$csrf_token || !validateCSRFToken($csrf_token)) {
        throw new Exception('Invalid security token');
    }

    // Use SessionManager for proper session extension
    $session_manager = SessionManager::getInstance();

    // Ensure SessionManager has database connection
    if (method_exists($session_manager, 'setDatabaseConnection')) {
        $session_manager->setDatabaseConnection($conn);
    } else {
        // Fallback: Set connection using reflection
        try {
            $reflection = new ReflectionClass($session_manager);
            $conn_property = $reflection->getProperty('conn');
            $conn_property->setAccessible(true);
            $conn_property->setValue($session_manager, $conn);
        } catch (Exception $e) {
            error_log("Failed to set SessionManager database connection in extend_session: " . $e->getMessage());
        }
    }

    // Extend session by regenerating session ID and updating timeout
    session_regenerate_id(true);

    // Update session timeout (extend by 30 minutes)
    $new_timeout = time() + 1800; // 30 minutes
    $_SESSION['timeout'] = $new_timeout;
    $_SESSION['last_activity'] = time();

    // Update last activity in database
    global $conn;
    /** @var mysqli $conn */

    if (!$conn) {
        throw new Exception('Database connection not available');
    }

    // Update last activity in auth_users (gracefully handle missing last_login_ip column)
    $has_ip_col = false;
    try {
        if ($res = $conn->query("SHOW COLUMNS FROM auth_users LIKE 'last_login_ip'")) {
            $has_ip_col = $res->num_rows > 0; $res->close();
        }
    } catch (Exception $e) { /* ignore */ }

    if ($has_ip_col) {
        $update_stmt = $conn->prepare("UPDATE auth_users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
        $client_ip = getClientIP();
        $update_stmt->bind_param('si', $client_ip, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        $update_stmt = $conn->prepare("UPDATE auth_users SET last_login = NOW() WHERE id = ?");
        $update_stmt->bind_param('i', $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    // Log session extension
    $log_message = "Session extended for user ID: $user_id ({$user_data['username']}) from IP: $client_ip";
    error_log("AUTH: $log_message");
    logError($log_message, 'auth');

    // Successful response
    $response = [
        'success' => true,
        'message' => 'Session extended successfully',
        'data' => [
            'timeout' => $new_timeout,
            'expires_at' => date('Y-m-d H:i:s', $new_timeout),
            'expires_in' => 1800, // seconds
            'user_id' => $user_id,
            'username' => $user_data['username'],
            'session_id' => session_id()
        ]
    ];

    http_response_code(200);
} catch (Exception $e) {
    // Log the error
    $error_message = "Session extension error: " . $e->getMessage();
    error_log($error_message);
    logError($error_message, 'auth');

    // Determine appropriate HTTP status code
    $status_code = 500;
    $redirect_url = null;

    if (strpos($e->getMessage(), 'Not authenticated') !== false) {
        $status_code = 401;
        $redirect_url = BASE_URL . 'authentication/?action=login';
        // Clear session for security
        session_destroy();
    } elseif (
        strpos($e->getMessage(), 'User not found') !== false ||
        strpos($e->getMessage(), 'not active') !== false
    ) {
        $status_code = 403;
        $redirect_url = BASE_URL . 'authentication/?action=login';
        session_destroy();
    } elseif (strpos($e->getMessage(), 'security token') !== false) {
        $status_code = 403;
    }

    http_response_code($status_code);

    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $status_code
    ];

    // Only include redirect for authentication errors
    if ($redirect_url) {
        $response['redirect'] = $redirect_url;
    }
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if token exists in session and matches
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate and store CSRF token
 * @return string
 */
function generateCSRFToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

// Note: getClientIP() function is available in helper_functions.php
// No need to redefine it here
