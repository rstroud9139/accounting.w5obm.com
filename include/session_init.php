<?php

/**
 * Centralized Session Initialization
 * File: /include/session_init.php
 * Purpose: ONE session configuration for the ENTIRE SITE
 * FIXED: Removed duplicate PHP sections and redundant code
 */

// Prevent multiple includes
if (defined('W5OBM_SESSION_INITIALIZED')) {
    return;
}
define('W5OBM_SESSION_INITIALIZED', true);

/**
 * Initialize session with consistent configuration across entire site
 */
function initializeW5OBMSession()
{
    // Don't start if already active
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    // Configure session settings BEFORE starting
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);

    // Determine if we're on HTTPS
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Use consistent session name
    if (isset($_COOKIE['PHPSESSID']) && !empty($_COOKIE['PHPSESSID'])) {
        session_name('PHPSESSID');
    } else {
        session_name('W5OBM_SESSION');
    }

    // Set consistent cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,           // Session cookie
        'path' => '/',             // Available site-wide
        'domain' => '',            // Current domain
        'secure' => $is_https,     // HTTPS only if available
        'httponly' => true,        // Not accessible via JavaScript
        'samesite' => 'Strict'     // CSRF protection
    ]);

    // Start the session
    return session_start();
}

// Auto-initialize when this file is included (skip CLI to avoid noisy warnings)
if (php_sapi_name() !== 'cli') {
    initializeW5OBMSession();
}
