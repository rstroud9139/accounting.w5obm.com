<?php

/**
 * Transactions Test Harness (fallback location since /accounting/tests/ not persisting)
 * Run: php accounting/transactions_test.php
 * Safe: Uses DB transaction and rolls back all changes.
 */
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/app/repositories/TransactionRepository.php';

function assertTrue($cond, $label)
{
    echo ($cond ? "[PASS]" : "[FAIL]") . " $label" . PHP_EOL;
}

$db = null;
if (isset($accConn) && $accConn instanceof mysqli) {
    $db = $accConn;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
}

if (!$db) {
    echo "[ERROR] Database connection not initialized. Check include/dbconn.php." . PHP_EOL;
    exit(1);
}

$db->begin_transaction();
try {
    $repo = new TransactionRepository($db);
    $okCat = $db->query("INSERT INTO acc_transaction_categories (name, type) VALUES ('_tmp_test_category', 'Income')");
    assertTrue($okCat === true, 'Inserted temp category');
    $category_id = $db->insert_id;

    $txId = $repo->createWithPosting([
        'category_id' => $category_id,
        'amount' => 12.34,
        'transaction_date' => date('Y-m-d'),
        'description' => 'Test add txn',
        'type' => 'Income',
        'cash_account_id' => null,
        'vendor_id' => null,
        'reference_number' => 'REF-TEST',
        'notes' => 'Sample notes',
    ], []);
    assertTrue($txId !== false, 'addTransaction returned ID');

    $row = $repo->findById($txId);
    assertTrue($row && (float)$row['amount'] === 12.34, 'Fetched amount correct');
    assertTrue($row['account_id'] === null, 'account_id NULL');
    assertTrue($row['vendor_id'] === null, 'vendor_id NULL');

    $okUpdate = $repo->updateWithPosting($txId, [
        'category_id' => $category_id,
        'amount' => 20.00,
        'transaction_date' => date('Y-m-d'),
        'description' => 'Updated txn',
        'type' => 'Income',
        'cash_account_id' => null,
        'vendor_id' => null,
        'reference_number' => 'REF-TEST-2',
        'notes' => 'Updated notes',
    ], []);
    assertTrue($okUpdate === true, 'updateTransaction success');

    $row2 = $repo->findById($txId);
    assertTrue($row2 && (float)$row2['amount'] === 20.00, 'Updated amount persisted');

    if (function_exists('get_transaction_totals')) {
        require_once __DIR__ . '/utils/stats_service.php';
        $totals = get_transaction_totals(date('Y-m-01'), date('Y-m-t'));
        assertTrue(isset($totals['income']), 'get_transaction_totals returned data');
    }

    $okDelete = $repo->delete($txId);
    assertTrue($okDelete === true, 'deleteTransaction success');
    $row3 = $repo->findById($txId);
    assertTrue(!$row3 || empty($row3), 'Transaction removed');

    $db->rollback();
    echo "\nAll tests executed. Rolled back.\n";
} catch (Throwable $e) {
    $db->rollback();
    echo "[ERROR] Test failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
