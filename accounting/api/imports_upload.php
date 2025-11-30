<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../../include/helper_functions.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../lib/import_helpers.php';

if (!function_exists('imports_upload_log')) {
    function imports_upload_log(string $message, array $context = []): void
    {
        static $logFile = null;
        if ($logFile === null) {
            $logFile = __DIR__ . '/../logs/import_upload.log';
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        imports_upload_log('Fatal error during upload handler', $error);
    }
});

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    imports_upload_log('Invalid verb', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown']);
    respond(405, ['success' => false, 'error' => 'Method not allowed']);
}

try {
    csrf_verify_post_or_throw();
} catch (Exception $ex) {
    imports_upload_log('CSRF failure', ['message' => $ex->getMessage()]);
    respond(419, ['success' => false, 'error' => $ex->getMessage()]);
}

if (!isAuthenticated()) {
    imports_upload_log('Authentication required');
    respond(401, ['success' => false, 'error' => 'Authentication required.']);
}

$userId = getCurrentUserId();
if (!isAdmin($userId) && !hasPermission($userId, 'app.accounting') && !hasPermission($userId, 'accounting_manage')) {
    imports_upload_log('Permission denied', ['user_id' => $userId]);
    respond(403, ['success' => false, 'error' => 'Insufficient permissions.']);
}

try {
    $accConn = accounting_db_connection();
} catch (Throwable $dbEx) {
    imports_upload_log('Accounting DB unavailable', [
        'user_id' => $userId,
        'message' => $dbEx->getMessage(),
    ]);
    respond(500, ['success' => false, 'error' => 'Accounting database connection unavailable.']);
}

accounting_imports_ensure_tables($accConn);
$sourceTypes = accounting_imports_get_source_types();
$sourceType = trim((string)($_POST['source_type'] ?? ''));
if ($sourceType === '' || !array_key_exists($sourceType, $sourceTypes)) {
    imports_upload_log('Invalid source type', ['user_id' => $userId, 'source_type' => $sourceType]);
    respond(422, ['success' => false, 'error' => 'Select a supported source type.']);
}

if (empty($_FILES['import_file'])) {
    imports_upload_log('File missing', ['user_id' => $userId]);
    respond(422, ['success' => false, 'error' => 'Upload file is required.']);
}

$allowedExtensions = ['csv', 'xlsx', 'xls', 'qbo', 'qfx', 'ofx', 'iif', 'gnucash'];
$uploadedExt = strtolower(pathinfo($_FILES['import_file']['name'] ?? '', PATHINFO_EXTENSION));
if ($uploadedExt === '' || !in_array($uploadedExt, $allowedExtensions, true)) {
    imports_upload_log('Invalid extension', ['user_id' => $userId, 'extension' => $uploadedExt]);
    respond(422, ['success' => false, 'error' => 'Unsupported file type.']);
}

$maxSize = 50 * 1024 * 1024;
if (($_FILES['import_file']['size'] ?? 0) > $maxSize) {
    imports_upload_log('Oversize upload', ['user_id' => $userId, 'size' => $_FILES['import_file']['size'] ?? 0]);
    respond(413, ['success' => false, 'error' => 'File too large. Max 50MB.']);
}

imports_upload_log('Upload request accepted', [
    'user_id' => $userId,
    'source_type' => $sourceType,
    'filename' => $_FILES['import_file']['name'] ?? null,
    'size' => $_FILES['import_file']['size'] ?? null,
]);

try {
    $fileMeta = accounting_imports_stage_uploaded_file($_FILES['import_file']);
    $batchId = accounting_imports_create_batch($accConn, $userId, $sourceType, $fileMeta);
    accounting_imports_populate_batch($accConn, $batchId, $sourceType, $fileMeta);
    imports_upload_log('Batch staged', ['user_id' => $userId, 'batch_id' => $batchId]);
} catch (RuntimeException $ex) {
    if (isset($batchId) && $accConn instanceof mysqli) {
        $accConn->query('DELETE FROM acc_import_batches WHERE id = ' . (int)$batchId);
    }
    imports_upload_log('Runtime staging error', ['user_id' => $userId, 'message' => $ex->getMessage()]);
    respond(400, ['success' => false, 'error' => $ex->getMessage()]);
} catch (Throwable $ex) {
    if (isset($batchId) && $accConn instanceof mysqli) {
        $accConn->query('DELETE FROM acc_import_batches WHERE id = ' . (int)$batchId);
    }
    imports_upload_log('Unexpected staging error', [
        'user_id' => $userId,
        'message' => $ex->getMessage(),
        'trace' => $ex->getTraceAsString(),
    ]);
    respond(500, ['success' => false, 'error' => 'Unexpected error staging batch.']);
}

respond(201, [
    'success' => true,
    'message' => 'Batch staged successfully. Proceed to mapping wizard (coming soon).',
    'batch' => [
        'id' => $batchId,
        'source_type' => $sourceType,
        'status' => 'staging',
        'original_filename' => $fileMeta['original_name'] ?? null,
        'stored_path' => $fileMeta['relative_path'] ?? null,
        'checksum' => $fileMeta['checksum'] ?? null,
        'size' => $fileMeta['size'] ?? null,
    ],
]);
