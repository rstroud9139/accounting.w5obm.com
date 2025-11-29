<?php
require __DIR__ . '/../../include/session_init.php';
require __DIR__ . '/../../include/dbconn.php';
require __DIR__ . '/../../include/helper_functions.php';
require __DIR__ . '/../lib/import_helpers.php';

accounting_imports_ensure_tables($conn);

$fileMeta = [
    'original_name' => 'OBARC Checking.gnucash',
    'stored_path' => __DIR__ . '/../import/OBARC Checking.gnucash',
];

$userId = getCurrentUserId() ?: 1;
$batchId = accounting_imports_create_batch($conn, $userId, 'gnucash_file', $fileMeta);
accounting_imports_populate_batch($conn, $batchId, 'gnucash_file', $fileMeta);

echo 'Batch ' . $batchId . ' rows staged' . PHP_EOL;
