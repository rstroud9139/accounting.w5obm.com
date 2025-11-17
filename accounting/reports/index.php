<?php
// Ensure no output before the redirect
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Redirect to the enhanced reports dashboard
header('Location: /accounting/reports_dashboard.php');
exit();
