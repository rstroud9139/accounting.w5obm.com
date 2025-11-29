<?php

/**
 * STANDARDIZED INDEX.PHP - AUTHENTICATION FOLDER
 * File: /authentication/index.php
 * Purpose: Redirect to authentication dashboard
 */

// =============================================================================
// CONFIGURATION FOR AUTHENTICATION FOLDER
// =============================================================================

$config = [
    'redirect_url' => 'dashboard.php',
    'require_auth' => true,
    'require_admin' => false,
    'required_permission' => '',
    'folder_name' => 'Authentication System',
    'access_denied_message' => 'You do not have permission to access the authentication system.',
    'login_redirect' => 'login.php',
    'dashboard_redirect' => 'dashboard.php',
    'admin_redirect' => '../administration/dashboard.php',
];

// =============================================================================
// UNIVERSAL LOGIC - DO NOT EDIT BELOW THIS LINE
// =============================================================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine the correct include path based on folder depth
$include_paths = [
    __DIR__ . '/include/',           // Root level
    __DIR__ . '/../include/',        // One level deep
    __DIR__ . '/../../include/',     // Two levels deep
    __DIR__ . '/../../../include/',  // Three levels deep
    __DIR__ . '/../../../../include/', // Four levels deep
    __DIR__ . '/../../../../../include/', // Five levels deep
];


$helper_functions_loaded = false;

// Try to load helper functions from different possible paths
foreach ($include_paths as $path) {
    if (file_exists($path . 'helper_functions.php')) {
        try {
            require_once $path . 'session_init.php';
            require_once $path . 'dbconn.php';
            require_once $path . 'helper_functions.php';
            $helper_functions_loaded = true;
            break;
        } catch (Exception $e) {
            continue; // Try next path
        }
    }
}

// If we couldn't load helper functions, create minimal versions
if (!$helper_functions_loaded) {
    // Minimal authentication check
    function isAuthenticated()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    // Minimal admin check
    function isAdmin($user_id = null)
    {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    }

    // Minimal permission check
    function hasPermission($user_id, $permission)
    {
        return isAdmin($user_id); // Fallback: admins have all permissions
    }

    // Simple message function
    function setToastMessage($type, $title, $message, $icon = '')
    {
        $_SESSION['toast'] = compact('type', 'title', 'message', 'icon');
    }

    // Get current user ID
    function getCurrentUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }
}

// Authentication logic
if ($config['require_auth']) {
    if (!isAuthenticated()) {
        if (function_exists('setToastMessage')) {
            setToastMessage(
                'warning',
                'Login Required',
                "Please login to access {$config['folder_name']}.",
                'club-logo'
            );
        }
        header('Location: ' . $config['login_redirect']);
        exit();
    }

    $user_id = getCurrentUserId();

    // Check admin requirement
    if ($config['require_admin'] && !isAdmin($user_id)) {
        if (function_exists('setToastMessage')) {
            setToastMessage(
                'danger',
                'Admin Required',
                'Administrator privileges required.',
                'club-logo'
            );
        }
        header('Location: ' . $config['admin_redirect']);
        exit();
    }

    // Check specific permission
    if (!empty($config['required_permission']) && !hasPermission($user_id, $config['required_permission']) && !isAdmin($user_id)) {
        if (function_exists('setToastMessage')) {
            setToastMessage('danger', 'Access Denied', $config['access_denied_message'], 'club-logo');
        }
        header('Location: ' . $config['dashboard_redirect']);
        exit();
    }
}

// If we get here, redirect to the target file
header('Location: ' . $config['redirect_url']);
exit();

