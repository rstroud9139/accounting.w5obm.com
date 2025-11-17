<?php

/**
 * Web Routes
 * Defines routing for standard web pages.
 */

include_once __DIR__ . '/../../include/dbconn.php';
include_once __DIR__ . '/../utils/session_manager.php';
include_once __DIR__ . '/../utils/stats_service.php';

if (function_exists('start_session')) {
    start_session();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Example: Redirect to dashboard
if ($_SERVER['REQUEST_URI'] === '/dashboard') {
    header('Location: ../dashboard.php');
    exit();
}

// Example: Redirect to login page if user is not logged in
if (!isset($_SESSION['loggedIn'])) {
    header('Location: ../login.php');
    exit();
}
