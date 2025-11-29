<?php
// Accounting App Bootstrap
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Core includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

// Auth gate (reuse site helpers if available)
if (function_exists('isAuthenticated')) {
    if (!isAuthenticated()) {
        $loginUrl = trim((string)($_ENV['AUTH_LOGIN_URL'] ?? ''));
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($loginUrl === '') {
            $loginUrl = '/authentication/login.php';
        }

        if ($loginUrl === '/authentication/login.php') {
            $siteRoot = dirname(__DIR__, 2); // Jump from app/ to accounting root
            $localLogin = $siteRoot . '/authentication/login.php';
            if (!file_exists($localLogin)) {
                // Fallback to historical centralized hosts only if local files missing
                if (stripos($host, 'dev.') !== false || stripos($host, 'localhost') !== false) {
                    $loginUrl = 'https://dev.w5obm.com/authentication/login.php';
                } else {
                    $loginUrl = 'https://www.w5obm.com/authentication/login.php';
                }
            }
        }

        $currentUrl = '';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $currentUrl = $host ? $scheme . $host . $_SERVER['REQUEST_URI'] : $_SERVER['REQUEST_URI'];
        }

        $redirectUrl = $loginUrl;
        if ($currentUrl !== '') {
            $redirectUrl .= (strpos($loginUrl, '?') === false ? '?' : '&') . 'redirect=' . urlencode($currentUrl);
        }

        header('Location: ' . $redirectUrl);
        exit();
    }
}

// Permissions helper
function requirePermission($perm)
{
    if (function_exists('getCurrentUserId') && function_exists('hasPermission')) {
        $uid = getCurrentUserId();
        if (!$uid || !hasPermission($uid, $perm)) {
            if (function_exists('setToastMessage')) {
                setToastMessage('danger', 'Access Denied', 'You do not have permission to access this section.', 'club-logo');
            }
            header('Location: /accounting/reports/reports_dashboard.php');
            exit();
        }
    }
}

// Simple base controller
abstract class BaseController
{
    protected function render($view, $params = [], $layout = 'layout')
    {
        extract($params, EXTR_SKIP);
        $viewFile = __DIR__ . '/views/' . $view . '.php';
        $layoutFile = __DIR__ . '/views/' . $layout . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($view);
            return;
        }
        ob_start();
        include $viewFile;
        $content = ob_get_clean();
        include $layoutFile;
    }
}

// Route helper
function route($name, $params = [])
{
    $q = http_build_query(array_merge(['route' => $name], $params));
    return '/accounting/app/index.php?' . $q;
}

// CSRF helpers for POST endpoints
function getCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input()
{
    $t = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $t . '">';
}

function verifyPostCsrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return true;
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
