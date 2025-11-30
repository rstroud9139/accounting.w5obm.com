<?php

session_start();

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/import_helpers.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../../include/premium_hero.php';

csrf_ensure_token();

if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to manage imports.', 'fas fa-file-import');
    header('Location: /authentication/login.php');
    exit();
}

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'Only accounting managers can review staged imports.', 'fas fa-ban');
    header('Location: /accounting/dashboard.php');
    exit();
}

$accConn = null;
try {
    $accConn = accounting_db_connection();
} catch (Throwable $dbEx) {
    setToastMessage('danger', 'Database Error', 'Accounting database connection is unavailable: ' . $dbEx->getMessage(), 'fas fa-database');
}

$page_title = 'Accounting Import Batches - W5OBM';

$batches = [];
if ($accConn instanceof mysqli) {
    $result = $accConn->query('SELECT b.*, u.username FROM acc_import_batches b LEFT JOIN auth_users u ON b.created_by = u.id ORDER BY b.updated_at DESC LIMIT 50');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $batches[] = $row;
        }
        $result->close();
    }
}

$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;
if (!$selectedBatchId && !empty($batches)) {
    $selectedBatchId = (int)$batches[0]['id'];
}

$currentBatch = ($selectedBatchId && $accConn instanceof mysqli)
    ? accounting_imports_fetch_batch($accConn, $selectedBatchId)
    : null;
$batchAccounts = $currentBatch && $accConn instanceof mysqli
    ? accounting_imports_fetch_batch_accounts($accConn, $selectedBatchId)
    : [];
$currentMaps = $currentBatch && $accConn instanceof mysqli
    ? accounting_imports_fetch_account_maps($accConn, $currentBatch['source_type'])
    : [];
$currentBatchOwner = null;
if ($currentBatch) {
    foreach ($batches as $batchMeta) {
        if ((int)$batchMeta['id'] === (int)$currentBatch['id']) {
            $currentBatchOwner = $batchMeta['username'] ?? null;
            break;
        }
    }
}

$ledgerAccounts = [];
if ($accConn instanceof mysqli) {
    $ledgerResult = $accConn->query('SELECT id, account_number, name, account_type FROM acc_ledger_accounts ORDER BY account_number ASC, name ASC');
    if ($ledgerResult) {
        while ($row = $ledgerResult->fetch_assoc()) {
            $ledgerAccounts[] = $row;
        }
        $ledgerResult->close();
    }
}

function accounting_imports_format_status(string $status): array
{
    $class = accounting_imports_status_badge_class($status);
    $label = ucfirst($status);
    return [$class, $label];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
    <style>
        .import-batch-list {
            max-height: 520px;
            overflow-y: auto;
        }
        .mapping-table td {
            vertical-align: middle;
        }
    </style>
</head>

<body class="accounting-app bg-light">
<?php include __DIR__ . '/../../include/menu.php'; ?>
<div class="page-container accounting-admin-utilities-shell">
    <?php if (function_exists('displayToastMessage')) { displayToastMessage(); } ?>

    <?php if (function_exists('renderPremiumHero')):
        renderPremiumHero([
            'eyebrow' => 'Data Pipeline',
            'title' => 'Import Batch Review',
            'subtitle' => 'Map staged rows, delete test uploads, and post clean data to the ledger.',
            'description' => 'Uploads land here after staging. Map each source account to a ledger account, then post when ready.',
            'theme' => 'midnight',
            'size' => 'compact',
            'chips' => [
                'Requires accounting_manage',
                'Tracks audit history',
                'Supports QuickBooks & gnuCash'
            ],
            'actions' => [
                [
                    'label' => 'Back to Imports UI',
                    'url' => '/accounting/imports.php',
                    'variant' => 'outline',
                    'icon' => 'fa-file-import'
                ],
                [
                    'label' => 'Admin Utilities',
                    'url' => '/accounting/admin/utilities.php',
                    'variant' => 'outline',
                    'icon' => 'fa-sliders'
                ],
            ],
        ]);
    else: ?>
        <div class="bg-dark text-white py-4 mb-4">
            <div class="container">
                <h1 class="h4 mb-1">Import Batch Review</h1>
                <p class="mb-0 text-white-50">Map staged imports before posting</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-layer-group me-2 text-primary"></i>Batches</h5>
                    <span class="badge bg-secondary"><?= number_format(count($batches)); ?></span>
                </div>
                <div class="card-body import-batch-list p-0">
                    <?php if (empty($batches)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-2x mb-3"></i>
                            <p class="mb-0">No staged batches yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($batches as $batch):
                                [$badgeClass, $label] = accounting_imports_format_status($batch['status']);
                                $isActive = ((int)$batch['id'] === (int)$selectedBatchId);
                                ?>
                                <a href="?batch_id=<?= (int)$batch['id']; ?>" class="list-group-item list-group-item-action <?= $isActive ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="fw-semibold">Batch #<?= (int)$batch['id']; ?></div>
                                            <small><?= htmlspecialchars($batch['source_type']); ?> · <?= (int)$batch['total_rows']; ?> rows</small>
                                        </div>
                                        <span class="badge <?= htmlspecialchars($badgeClass); ?>"><?= htmlspecialchars($label); ?></span>
                                    </div>
                                    <div class="mt-1 small">
                                        Updated <?= htmlspecialchars(date('M j, Y g:ia', strtotime($batch['updated_at']))); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <?php if (!$currentBatch): ?>
                <div class="alert alert-info shadow-sm">Select a staged batch to begin mapping.</div>
            <?php else: ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Batch #<?= (int)$currentBatch['id']; ?> · <?= htmlspecialchars($currentBatch['source_type']); ?></h5>
                                <small class="text-muted">
                                    Uploaded by <?= htmlspecialchars($currentBatchOwner ?: ('User #' . ($currentBatch['created_by'] ?? 'n/a'))); ?> ·
                                    <?= htmlspecialchars(date('M j, Y g:ia', strtotime($currentBatch['created_at']))); ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="post" action="../scripts/import_batch_delete.php" onsubmit="return confirm('Delete this staged batch? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="batch_id" value="<?= (int)$currentBatch['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt me-1"></i>Delete</button>
                                </form>
                                <form method="post" action="../scripts/import_batch_post.php" onsubmit="return confirm('Post this batch to the ledger? Ensure mappings are complete.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="batch_id" value="<?= (int)$currentBatch['id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm" <?= $currentBatch['status'] === 'committed' ? 'disabled' : ''; ?>>
                                        <i class="fas fa-paper-plane me-1"></i>Post Batch
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted text-uppercase small">Rows</div>
                                    <div class="h4 mb-0"><?= number_format((int)$currentBatch['total_rows']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted text-uppercase small">Ready</div>
                                    <div class="h4 mb-0"><?= number_format((int)$currentBatch['ready_rows']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted text-uppercase small">Errors</div>
                                    <div class="h4 mb-0 text-danger"><?= number_format((int)$currentBatch['error_rows']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php if ($currentBatch['status'] === 'committed'): ?>
                            <div class="alert alert-success border-0 shadow-sm mt-3"><i class="fas fa-check-circle me-2"></i>This batch has already been posted.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-sitemap me-2 text-primary"></i>Account Mapping</h5>
                        <small class="text-muted">Map every source account before posting.</small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($batchAccounts)): ?>
                            <div class="alert alert-info mb-0">This batch does not expose account data yet.</div>
                        <?php else: ?>
                            <form method="post" action="../scripts/import_batch_mapping.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="batch_id" value="<?= (int)$currentBatch['id']; ?>">
                                <div class="table-responsive">
                                    <table class="table table-sm mapping-table align-middle">
                                        <thead>
                                        <tr>
                                            <th>Source Account</th>
                                            <th>Splits</th>
                                            <th class="w-50">Ledger Account</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($batchAccounts as $account):
                                            $sourceKey = $account['account_key'];
                                            $existing = $currentMaps[$sourceKey]['ledger_account_id'] ?? '';
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($account['account_name'] ?? $sourceKey); ?></div>
                                                    <small class="text-muted">Key: <?= htmlspecialchars($sourceKey); ?></small>
                                                </td>
                                                <td><?= number_format((int)$account['split_count']); ?></td>
                                                <td>
                                                    <select class="form-select" name="mapping[<?= htmlspecialchars($sourceKey); ?>]">
                                                        <option value="">Select ledger account</option>
                                                        <?php foreach ($ledgerAccounts as $ledger):
                                                            $label = trim(($ledger['account_number'] ?? '') . ' ' . $ledger['name']);
                                                            ?>
                                                            <option value="<?= (int)$ledger['id']; ?>" <?= ((int)$existing === (int)$ledger['id']) ? 'selected' : ''; ?>>
                                                                <?= htmlspecialchars($label); ?> (<?= htmlspecialchars($ledger['account_type'] ?? ''); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Mappings</button>
                                    <small class="text-muted">Mappings are reusable for future batches of the same source.</small>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../include/footer.php'; ?>
</body>
</html>
