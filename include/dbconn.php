<?php
// Basic error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple .env file loader - only declare if not already exists
if (!function_exists('loadEnvFile')) {
    function loadEnvFile($path)
    {
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value, '"\'');
            }
        }
        return true;
    }
}

// Try to load .env from the config folder
// NOTE: loadEnvFile already parses KEY=VALUE lines into $_ENV.
// The previous parse_ini_file + array_merge caused fatal errors when
// the .env file contained comments or non-INI-compatible syntax, so
// we rely solely on loadEnvFile here.
loadEnvFile(__DIR__ . '/../config/.env');

// Simple environment detection
$is_local = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
    ($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1');

// =====================================================================================
// Primary AUTH database connection ($conn) - points to shared w5obm DB
// =====================================================================================
if ($is_local) {
    $authServer = $_ENV['LOCAL_DB_HOST'] ?? 'localhost';
    $authUser   = $_ENV['LOCAL_DB_USER'] ?? 'root';
    $authPass   = $_ENV['LOCAL_DB_PASS'] ?? '';
    $authDb     = $_ENV['LOCAL_DB_NAME'] ?? 'w5obm';
} else {
    $authServer = $_ENV['AUTH_DB_HOST'] ?? 'mysql.w5obm.com';
    $authUser   = $_ENV['AUTH_DB_USER'] ?? 'w5obmcom_admin';
    $authPass   = $_ENV['AUTH_DB_PASS'] ?? '';
    $authDb     = $_ENV['AUTH_DB_NAME'] ?? 'w5obm';
}

if (!isset($conn) || $conn === null) {
    $conn = new mysqli($authServer, $authUser, $authPass, $authDb);

    if ($conn->connect_error) {
        die("Auth DB connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");
}

// =====================================================================================
// Secondary ACCOUNTING database connection ($accConn) - points to accounting_w5obm
// =====================================================================================
$accConn = null;
if ($is_local) {
    $accServer = $_ENV['LOCAL_ACC_DB_HOST'] ?? 'localhost';
    $accUser   = $_ENV['LOCAL_ACC_DB_USER'] ?? 'root';
    $accPass   = $_ENV['LOCAL_ACC_DB_PASS'] ?? '';
    $accDb     = $_ENV['LOCAL_ACC_DB_NAME'] ?? 'accounting_w5obm';
} else {
    $accServer = $_ENV['ACC_DB_HOST'] ?? 'mysql.w5obm.com';
    $accUser   = $_ENV['ACC_DB_USER'] ?? 'w5obmcom_admin';
    $accPass   = $_ENV['ACC_DB_PASS'] ?? '';
    $accDb     = $_ENV['ACC_DB_NAME'] ?? 'accounting_w5obm';
}

try {
    $accConn = new mysqli($accServer, $accUser, $accPass, $accDb);
    if ($accConn->connect_error) {
        // Do not hard-fail the app if accounting DB is unreachable; auth still works
        $accConn = null;
    } else {
        $accConn->set_charset("utf8");
    }
} catch (Exception $e) {
    $accConn = null;
}

// URL constants - only define if not already defined by header.php
if (!defined('BASE_URL')) {
    // Environment detection based on domain name and file path
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    if ($host === 'localhost' && strpos(__DIR__, 'dev.w5obm.com') !== false) {
        // Local development in dev folder
        define('BASE_URL', 'http://localhost/w5obmcom_admin/dev.w5obm.com/');
    } elseif ($host === 'localhost') {
        // Local development in production folder
        define('BASE_URL', 'http://localhost/w5obmcom_admin/w5obm.com/');
    } elseif (strpos($host, 'dev.w5obm.com') !== false) {
        // Development server
        define('BASE_URL', '/');
    } else {
        // Production server
        define('BASE_URL', '/');
    }
}

if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', BASE_URL);
}

if (!defined('IMAGE_URL')) {
    define('IMAGE_URL', BASE_URL . 'images/');
}

global $conn, $accConn;
