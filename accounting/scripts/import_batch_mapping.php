<?php

session_start();

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/import_helpers.php';
require_once __DIR__ . '/../utils/csrf.php';

/** @var mysqli $accConn */

try {
    csrf_verify_post_or_throw();
} catch (Exception $ex) {
    setToastMessage('danger', 'Security Error', $ex->getMessage(), 'fas fa-shield');
    header('Location: /accounting/admin/import_batches.php');
    exit();
}

if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login again to save mappings.', 'fas fa-user-lock');
    header('Location: /authentication/login.php');
    exit();
}

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to modify import mappings.', 'fas fa-ban');
    header('Location: /accounting/admin/import_batches.php');
    exit();
}

$batchId = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
if ($batchId <= 0) {
    setToastMessage('danger', 'Missing Batch', 'Select a batch before saving mappings.', 'fas fa-layer-group');
    header('Location: /accounting/admin/import_batches.php');
    exit();
}

try {
    $batch = accounting_imports_fetch_batch($accConn, $batchId);
    if (!$batch) {
        throw new RuntimeException('Batch not found.');
    }

    $validLedgerIds = [];
    if ($accConn) {
        $validResult = $accConn->query('SELECT id FROM acc_ledger_accounts');
        if ($validResult) {
            while ($row = $validResult->fetch_assoc()) {
                $validLedgerIds[(int)$row['id']] = true;
            }
            $validResult->close();
        }
    }

    $mappings = isset($_POST['mapping']) && is_array($_POST['mapping']) ? $_POST['mapping'] : [];
    $saved = 0;
    foreach ($mappings as $sourceKey => $ledgerId) {
        $sourceKey = trim((string)$sourceKey);
        $ledgerId = (int)$ledgerId;
        if ($sourceKey === '' || $ledgerId <= 0 || empty($validLedgerIds[$ledgerId])) {
            continue;
        }
        accounting_imports_save_account_map($accConn, $userId, $batch['source_type'], $sourceKey, $sourceKey, $ledgerId);
        $saved++;
    }

    if ($saved === 0) {
        setToastMessage('info', 'Nothing Saved', 'No mappings were provided.', 'fas fa-info-circle');
    } else {
        setToastMessage('success', 'Mappings Updated', sprintf('Saved %d mapping%s.', $saved, $saved === 1 ? '' : 's'), 'fas fa-sitemap');
    }
} catch (Exception $ex) {
    setToastMessage('danger', 'Mapping Error', $ex->getMessage(), 'fas fa-bug');
}

header('Location: /accounting/admin/import_batches.php?batch_id=' . $batchId);
exit();
