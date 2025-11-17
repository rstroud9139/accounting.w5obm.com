<?php

// Accounting Admin Utilities Dashboard

session_start();

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

if (!isAuthenticated()) {
    echo "<h2>Authentication Required</h2>";
    echo "<p>You must be logged in to use accounting admin utilities.</p>";
    echo "<p><a href='https://dev.w5obm.com/authentication/login.php'>Login to Dev Site</a></p>";
    exit();
}

$user_id = getCurrentUserId();
if (!hasPermission($user_id, 'accounting_manage')) {
    echo "<h2>Access Denied</h2>";
    echo "<p>You do not have permission to access accounting admin utilities.</p>";
    echo "<p><a href='/accounting/dashboard.php'>Back to Accounting Dashboard</a></p>";
    exit();
}

echo "<h1>Accounting Admin Utilities</h1>";
echo "<p>These tools are for maintaining the accounting system and should only be used by site administrators.</p>";

echo "<h2>Chart of Accounts Utilities</h2>";
echo "<ul>";
echo "  <li><a href='../scripts/reseed_chart_of_accounts.php'>Reset / Reseed Chart of Accounts</a> - Deletes all existing ledger accounts and related transactions and reloads the standard chart.</li>";
echo "</ul>";

echo "<p><a href='/accounting/dashboard.php'>Back to Accounting Dashboard</a></p>";
