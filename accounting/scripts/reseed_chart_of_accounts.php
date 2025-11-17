<?php

/**
 * Reseed Chart of Accounts for accounting_w5obm
 *
 * WARNING: This script will DELETE all existing ledger accounts and their
 *          related transactions in the accounting database. Use only on
 *          a development or freshly migrated copy where this is safe.
 */

session_start();

// Include accounting app's DB connection and helpers
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

// Require authentication and accounting_manage permission to run this utility
if (!isAuthenticated()) {
    echo "<!DOCTYPE html><html><body>";
    echo "<h2>Authentication Required</h2>";
    echo "<p>You must be logged in to use this accounting admin utility.</p>";
    echo "<p><a href='https://dev.w5obm.com/authentication/login.php'>Login to Dev Site</a></p>";
    echo "</body></html>";
    exit();
}

$user_id = getCurrentUserId();
if (!hasPermission($user_id, 'accounting_manage')) {
    echo "<!DOCTYPE html><html><body>";
    echo "<h2>Access Denied</h2>";
    echo "<p>You do not have permission to run this accounting admin utility.</p>";
    echo "<p><a href='/accounting/dashboard.php'>Back to Accounting Dashboard</a></p>";
    echo "</body></html>";
    exit();
}

// Extra safety: require explicit confirmation via query string
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'YES') {
    echo "<!DOCTYPE html><html><body>";
    echo "<h2>Reseed Chart of Accounts</h2>";
    echo "<p><strong>Warning:</strong> This will delete all existing ledger accounts and related transactions in the accounting DB.</p>";
    echo "<p>To proceed, <a href='?confirm=YES'>click here to reseed now</a>.</p>";
    echo "<p><a href='/accounting/dashboard.php'>Cancel and return to dashboard</a></p>";
    echo "</body></html>";
    exit();
}

// Disable foreign key checks to allow truncation
$conn->query('SET FOREIGN_KEY_CHECKS=0');

// Wipe dependent tables first (adjust list as your schema evolves)
$tables = [
    'acc_journal_lines',
    'acc_journals',
    'acc_transactions',
    'acc_ledger_accounts'
];

foreach ($tables as $table) {
    $conn->query("TRUNCATE TABLE `{$table}`");
}

$conn->query('SET FOREIGN_KEY_CHECKS=1');

// Now include the standard setup and auto-create all standard accounts
require_once __DIR__ . '/../ledger/setup_standard.php';

// Force-create all standard accounts without user selection UI
if (!empty($standard_accounts)) {
    foreach ($standard_accounts as $account_data) {
        $stmt = $conn->prepare('SELECT id FROM acc_ledger_accounts WHERE account_number = ?');
        $stmt->bind_param('s', $account_data['account_number']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            addLedgerAccount($account_data);
        } else {
            $stmt->close();
        }
    }
}

logActivity(
    $user_id,
    'reseed_chart_of_accounts',
    'acc_ledger_accounts',
    null,
    'Reseeded chart of accounts using updated standard chart definition'
);

// Show a clear success page with an explicit continue link
echo "<!DOCTYPE html><html><body>";
echo "<h2>Chart of Accounts Reseeded</h2>";
echo "<p>Chart of accounts has been reseeded successfully.</p>";
echo "<p><a href='/accounting/dashboard.php'>Back to Accounting Dashboard</a></p>";
echo "</body></html>";
exit();
