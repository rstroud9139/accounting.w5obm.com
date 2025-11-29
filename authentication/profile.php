<?php
// Redirect shim for legacy profile link
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

// Try to include helper to detect admin
@require_once __DIR__ . '/../include/helper_functions.php';
@require_once __DIR__ . '/../include/dbconn.php';

// Determine base
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// If not logged in, send to login
if (!function_exists('isAuthenticated') || !isAuthenticated()) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/authentication/login.php');
    exit;
}

$userId = function_exists('getCurrentUserId') ? getCurrentUserId() : ($_SESSION['user_id'] ?? null);
$isAdmin = $userId && function_exists('isAdmin') ? isAdmin($userId) : false;

// Admins -> admin profile; members -> member profile
if ($isAdmin) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/administration/users/profile.php');
} else {
    header('Location: ' . rtrim(BASE_URL, '/') . '/members/edit_profile.php');
}
exit;
?>

