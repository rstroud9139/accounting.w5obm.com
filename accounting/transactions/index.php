<?php

/**
 * Transaction Index - W5OBM Accounting System
 * File: /accounting/transactions/index.php
 * Purpose: Redirect to transaction list
 * UPDATED: Uses proper relative path
 */

// Redirect to modern transactions workspace
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'transactions.php' . ($query ? '?' . $query : '');
header('Location: ' . $target);
exit();
