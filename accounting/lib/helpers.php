<?php

/**
 * Accounting Helpers Shim
 * Purpose: Provide accounting-local helper functions while reusing core /include/helper_functions.php
 * When /accounting is split into its own site, this file can either copy needed
 * functions or keep thin fallbacks to avoid breaking pages.
 */

// Try to load the shared site helpers if available
$__global_helpers = __DIR__ . '/../../include/helper_functions.php';
if (is_file($__global_helpers)) {
    require_once $__global_helpers;
}

if (!function_exists('accounting_db_connection')) {
    function accounting_db_connection(): mysqli
    {
        global $accConn, $conn;

        if (isset($accConn) && $accConn instanceof mysqli && $accConn->connect_errno === 0) {
            return $accConn;
        }

        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }

        throw new RuntimeException('Accounting database connection is unavailable.');
    }
}

// Fallback: basic sanitize
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value, $type = 'string')
    {
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'bool':
                return (bool)$value;
            case 'string':
            default:
                return is_string($value) ? trim($value) : $value;
        }
    }
}

// Fallback: authentication checks
if (!function_exists('isAuthenticated')) {
    function isAuthenticated()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'User';
    }
}

// Fallback permission: allow by default but log
if (!function_exists('hasPermission')) {
    function hasPermission($user_id, $permission)
    {
        if (!function_exists('logError')) {
            error_log("[accounting] hasPermission fallback used for '$permission'");
        } else {
            logError("hasPermission fallback used for '$permission'", 'accounting');
        }
        return true;
    }
}

// Toast messages
if (!function_exists('setToastMessage')) {
    function setToastMessage($type, $title, $message, $icon = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['toast'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
        ];
    }
}

if (!function_exists('displayToastMessage')) {
    function displayToastMessage()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['toast'])) {
            $t = $_SESSION['toast'];
            echo '<div class="alert alert-' . htmlspecialchars($t['type']) . ' alert-dismissible fade show" role="alert">';
            if (!empty($t['title'])) {
                echo '<strong>' . htmlspecialchars($t['title']) . ':</strong> ';
            }
            echo htmlspecialchars($t['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['toast']);
        }
    }
}

// Logging
if (!function_exists('logError')) {
    function logError($msg, $context = 'accounting')
    {
        error_log("[$context] $msg");
    }
}

// Optional fallbacks for admin/roles and activity logging to support decoupling
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin($user_id)
    {
        return false;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin($user_id)
    {
        return false;
    }
}

if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $table = null, $recordId = null, $message = '')
    {
        $uid = $user_id !== null ? (int)$user_id : 0;
        $tbl = $table ? (string)$table : '-';
        $rid = $recordId !== null ? (string)$recordId : '-';
        $msg = $message ? (string)$message : '';
        error_log("[accounting-activity] user=$uid action=$action table=$tbl id=$rid msg=$msg");
    }
}

// Logo path helpers for accounting decoupling
if (!function_exists('accounting_get_logo_path')) {
    function accounting_get_logo_path()
    {
        $accRoot = realpath(__DIR__ . '/..');
        $local = $accRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'badges' . DIRECTORY_SEPARATOR . 'club_logo.png';
        $site = realpath($accRoot . DIRECTORY_SEPARATOR . '..')
            . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'badges' . DIRECTORY_SEPARATOR . 'club_logo.png';

        if (is_file($local)) {
            return $local;
        }
        if (is_file($site)) {
            return $site;
        }
        return null;
    }
}

if (!function_exists('accounting_relative_path')) {
    function accounting_relative_path($from, $to)
    {
        $from = str_replace('\\', '/', realpath($from));
        $to = str_replace('\\', '/', $to);
        if (is_file($to)) {
            $to = str_replace('\\', '/', realpath($to));
        } elseif (is_dir($to)) {
            $to = str_replace('\\', '/', realpath($to));
        }
        $fromParts = explode('/', rtrim(is_dir($from) ? $from : dirname($from), '/'));
        $toParts = explode('/', rtrim(is_dir($to) ? $to : dirname($to), '/'));
        $length = min(count($fromParts), count($toParts));
        $commonLength = 0;
        for ($i = 0; $i < $length; $i++) {
            if (strcasecmp($fromParts[$i], $toParts[$i]) !== 0) {
                break;
            }
            $commonLength++;
        }
        $up = array_fill(0, count($fromParts) - $commonLength, '..');
        $down = array_slice($toParts, $commonLength);
        $relDir = implode('/', array_merge($up, $down));
        return $relDir === '' ? '' : $relDir . '/';
    }
}

if (!function_exists('accounting_logo_src_for')) {
    function accounting_logo_src_for($currentDir)
    {
        $accRoot = realpath(__DIR__ . '/..');
        $localFs = $accRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'badges' . DIRECTORY_SEPARATOR . 'club_logo.png';
        $siteFs = realpath($accRoot . DIRECTORY_SEPARATOR . '..')
            . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'badges' . DIRECTORY_SEPARATOR . 'club_logo.png';

        $target = is_file($localFs) ? $localFs : $siteFs;
        $rel = accounting_relative_path($currentDir, $target);
        $filename = 'club_logo.png';
        // Determine which base the target used to build the web path
        if (strpos($target, $accRoot . DIRECTORY_SEPARATOR . 'images') === 0) {
            return rtrim($rel, '/') . 'images/badges/' . $filename;
        }
        // Site-level images folder
        $siteRoot = realpath($accRoot . DIRECTORY_SEPARATOR . '..');
        if (strpos($target, $siteRoot . DIRECTORY_SEPARATOR . 'images') === 0) {
            return rtrim($rel, '/') . 'images/badges/' . $filename;
        }
        // Fallback: site images relative from current
        return rtrim(accounting_relative_path($currentDir, $siteRoot), '/') . '/images/badges/' . $filename;
    }
}
