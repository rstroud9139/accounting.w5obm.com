<?php

/**
 * Site Configuration Constants
 * This file automatically detects the environment and sets appropriate constants
 */

// Prevent direct access
if (!defined('INCLUDED_FROM_APP')) {
    // Allow inclusion from any PHP file in the application
    define('INCLUDED_FROM_APP', true);
}

// Environment Detection
function detectSiteEnvironment()
{
    $server_name = $_SERVER['SERVER_NAME'] ?? '';
    $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    // Multiple ways to detect development environment
    $dev_indicators = [
        strpos($server_name, 'localhost') !== false,
        strpos($server_name, '127.0.0.1') !== false,
        strpos($document_root, 'dev.w5obm.com') !== false,
        strpos($script_name, 'dev.w5obm.com') !== false,
        strpos($request_uri, 'dev.w5obm.com') !== false,
        strpos(__DIR__, 'dev.w5obm.com') !== false
    ];

    return in_array(true, $dev_indicators);
}

// Set environment-specific constants
if (!defined('SITE_ENVIRONMENT')) {
    $is_development = detectSiteEnvironment();

    if ($is_development) {
        define('SITE_ENVIRONMENT', 'development');
        define('BASE_URL', '/w5obmcom_admin/dev.w5obm.com/');
        define('SITE_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/w5obmcom_admin/dev.w5obm.com/');
        define('IMAGES_URL', BASE_URL . 'images/');
        define('CSS_URL', BASE_URL . 'css/');
        define('JS_URL', BASE_URL . 'js/');
    } else {
        define('SITE_ENVIRONMENT', 'production');
        define('BASE_URL', '/');
        define('SITE_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/');
        define('IMAGES_URL', BASE_URL . 'images/');
        define('CSS_URL', BASE_URL . 'css/');
        define('JS_URL', BASE_URL . 'js/');
    }
}

// Additional useful constants
if (!defined('CURRENT_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('CURRENT_URL', $protocol . '://' . $host . BASE_URL);
}
