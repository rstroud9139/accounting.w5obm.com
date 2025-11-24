<?php
/**
 * Legacy Transaction List Redirect
 * File retained for backwards compatibility; all traffic is routed to the
 * modern transactions workspace which provides inline modal CRUD.
 */

$legacyQuery = $_SERVER['QUERY_STRING'] ?? '';
$target = 'transactions.php' . ($legacyQuery ? '?' . $legacyQuery : '');
header('Location: ' . $target);
exit();
