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
    setToastMessage('info', 'Login Required', 'Please login to post a batch.', 'fas fa-user-lock');
    header('Location: /authentication/login.php');
    exit();
}

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to post batches.', 'fas fa-ban');
    header('Location: /accounting/admin/import_batches.php');
    exit();
}

$batchId = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
if ($batchId <= 0) {
    setToastMessage('danger', 'Missing Batch', 'Select a batch first.', 'fas fa-layer-group');
    header('Location: /accounting/admin/import_batches.php');
    exit();
}

try {
    $result = accounting_imports_post_batch($accConn, $batchId, $userId);
    $journals = (is_array($result) && isset($result['journals'])) ? (int)$result['journals'] : 0;
    $lines = (is_array($result) && isset($result['lines'])) ? (int)$result['lines'] : 0;
    setToastMessage('success', 'Batch Posted', sprintf('Created %d journal%s and %d line%s.', $journals, $journals === 1 ? '' : 's', $lines, $lines === 1 ? '' : 's'), 'fas fa-check-circle');
} catch (Exception $ex) {
    setToastMessage('danger', 'Posting Failed', $ex->getMessage(), 'fas fa-bug');
}

header('Location: /accounting/admin/import_batches.php?batch_id=' . $batchId);
exit();
