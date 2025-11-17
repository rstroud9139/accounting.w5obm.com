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

// Database configuration (accounting-specific)
if ($is_local) {
    $servername = $_ENV['LOCAL_ACC_DB_HOST'] ?? 'localhost';
    $username = $_ENV['LOCAL_ACC_DB_USER'] ?? 'root';
    $password = $_ENV['LOCAL_ACC_DB_PASS'] ?? '';
    $dbname = $_ENV['LOCAL_ACC_DB_NAME'] ?? 'accounting_w5obm';
} else {
    $servername = $_ENV['ACC_DB_HOST'] ?? 'mysql.w5obm.com';
    $username = $_ENV['ACC_DB_USER'] ?? 'w5obmcom_admin';
    $password = $_ENV['ACC_DB_PASS'] ?? '';
    $dbname = $_ENV['ACC_DB_NAME'] ?? 'accounting_w5obm';
}

// Create database connection only if not already created
if (!isset($conn) || $conn === null) {
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset
    $conn->set_charset("utf8");
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

global $conn;
if (!$conn) {
    require_once __DIR__ . '/dbconn.php';
}
