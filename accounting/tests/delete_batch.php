<?php
require __DIR__ . '/../../include/session_init.php';
require __DIR__ . '/../../include/dbconn.php';

$batchId = (int)($_SERVER['argv'][1] ?? 0);
if ($batchId <= 0) {
    fwrite(STDERR, "Usage: php delete_batch.php <batchId>" . PHP_EOL);
    exit(1);
}

$conn->query('DELETE FROM acc_import_rows WHERE batch_id = ' . $batchId);
$conn->query('DELETE FROM acc_import_batches WHERE id = ' . $batchId);

echo "Batch $batchId deleted" . PHP_EOL;
