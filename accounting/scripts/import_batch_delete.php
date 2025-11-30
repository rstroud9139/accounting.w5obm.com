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
    setToastMessage('info', 'Login Required', 'Please login to continue.', 'fas fa-user-lock');
    header('Location: /authentication/login.php');
    exit();
}

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to delete batches.', 'fas fa-ban');
    header('Location: /accounting/admin/import_batches.php');
    exit();
}

$batchId = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
if ($batchId <= 0) {
    setToastMessage('danger', 'Missing Batch', 'Choose a batch before deleting.', 'fas fa-layer-group');
    header('Location: /accounting/admin/import_batches.php');
    exit();
}

try {
    accounting_imports_delete_batch($accConn, $batchId, $userId);
    setToastMessage('success', 'Batch Removed', 'The staged batch and its rows were deleted.', 'fas fa-trash-alt');
} catch (Exception $ex) {
    setToastMessage('danger', 'Delete Failed', $ex->getMessage(), 'fas fa-bug');
    header('Location: /accounting/admin/import_batches.php?batch_id=' . $batchId);
    exit();
}

header('Location: /accounting/admin/import_batches.php');
exit();
