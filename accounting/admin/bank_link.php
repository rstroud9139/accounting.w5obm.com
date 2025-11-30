<?php

declare(strict_types=1);

require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../utils/csrf.php';

csrf_ensure_token();

if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'Bank linking is restricted to accounting managers.', 'fas fa-lock');
    header('Location: /accounting/dashboard.php');
    exit();
}

$connectionSuccess = null;
$accountSuccess = null;
$errors = [];

try {
    $accConn = accounting_db_connection();
} catch (Throwable $dbEx) {
    $accConn = null;
    $errors[] = 'Accounting database connection is unavailable: ' . $dbEx->getMessage();
}

function sanitize_json_or_null(?string $input): ?string
{
    if ($input === null || trim($input) === '') {
        return null;
    }
    $decoded = json_decode($input, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Metadata JSON is invalid: ' . json_last_error_msg());
    }
    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify_post_or_throw();
        if (!$accConn instanceof mysqli) {
            throw new RuntimeException('Accounting database connection is unavailable.');
        }
        $action = $_POST['action'] ?? '';

        if ($action === 'connection') {
            $provider = trim((string)($_POST['provider'] ?? 'Manual'));
            $connectionName = trim((string)($_POST['connection_name'] ?? ''));
            $secretReference = trim((string)($_POST['secret_reference'] ?? ''));
            $metadataInput = $_POST['metadata'] ?? '';
            $status = trim((string)($_POST['status'] ?? 'pending'));

            if ($connectionName === '') {
                throw new RuntimeException('Connection name is required.');
            }

            $metadata = sanitize_json_or_null($metadataInput);

            $stmt = $accConn->prepare('INSERT INTO acc_bank_connections (provider, connection_name, status, secret_reference, metadata, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare connection insert: ' . $accConn->error);
            }
            $stmt->bind_param('ssssiii', $provider, $connectionName, $status, $secretReference, $metadata, $userId, $userId);
            $stmt->execute();
            $stmt->close();
            $connectionSuccess = 'Connection saved successfully.';
        } elseif ($action === 'bank_account') {
            $connectionId = isset($_POST['connection_id']) && $_POST['connection_id'] !== '' ? (int)$_POST['connection_id'] : null;
            $ledgerAccountId = isset($_POST['ledger_account_id']) && $_POST['ledger_account_id'] !== '' ? (int)$_POST['ledger_account_id'] : null;
            $institutionName = trim((string)($_POST['institution_name'] ?? ''));
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $accountType = trim((string)($_POST['account_type'] ?? 'checking'));
            $currency = trim((string)($_POST['currency'] ?? 'USD')) ?: 'USD';
            $accountMask = trim((string)($_POST['account_mask'] ?? ''));
            $routingLast4 = trim((string)($_POST['routing_last4'] ?? ''));
            $externalId = trim((string)($_POST['external_account_id'] ?? ''));
            $status = trim((string)($_POST['account_status'] ?? 'unlinked'));

            if ($institutionName === '' || $displayName === '') {
                throw new RuntimeException('Institution and display name are required.');
            }

            $stmt = $accConn->prepare('INSERT INTO acc_bank_accounts (connection_id, ledger_account_id, institution_name, display_name, account_type, currency, account_mask, routing_last4, external_account_id, status, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare bank account insert: ' . $accConn->error);
            }
            $stmt->bind_param(
                'iissssssssii',
                $connectionId,
                $ledgerAccountId,
                $institutionName,
                $displayName,
                $accountType,
                $currency,
                $accountMask,
                $routingLast4,
                $externalId,
                $status,
                $userId,
                $userId
            );
            $stmt->execute();
            $stmt->close();
            $accountSuccess = 'Bank account linked locally.';
        }
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
    }
}

$connections = [];
$bankAccounts = [];
$ledgerAccounts = [];

if ($accConn instanceof mysqli) {
    $result = $accConn->query('SELECT * FROM acc_bank_connections ORDER BY created_at DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $connections[] = $row;
        }
        $result->close();
    }

    $sql = 'SELECT a.*, c.connection_name, la.name AS ledger_name, la.account_number
            FROM acc_bank_accounts a
            LEFT JOIN acc_bank_connections c ON a.connection_id = c.id
            LEFT JOIN acc_ledger_accounts la ON a.ledger_account_id = la.id
            ORDER BY a.created_at DESC';
    $result = $accConn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bankAccounts[] = $row;
        }
        $result->close();
    }

    $result = $accConn->query('SELECT id, account_number, name FROM acc_ledger_accounts ORDER BY account_number ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ledgerAccounts[] = $row;
        }
        $result->close();
    }
}

$page_title = 'Bank Connections & Ledger Mapping';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>
<body class="accounting-app bg-light">
<?php include __DIR__ . '/../../include/menu.php'; ?>

<div class="page-container accounting-app bg-light">
    <div class="container py-4">
        <div class="mb-4">
            <h1 class="h4 mb-1"><i class="fas fa-plug text-success me-2"></i><?= htmlspecialchars($page_title) ?></h1>
            <p class="text-muted mb-0">Capture bank metadata, map it to ledger accounts, and prep for automated sync providers.</p>
        </div>

        <?php if ($connectionSuccess): ?>
            <div class="alert alert-success shadow-sm">
                <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($connectionSuccess) ?>
            </div>
        <?php endif; ?>
        <?php if ($accountSuccess): ?>
            <div class="alert alert-success shadow-sm">
                <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($accountSuccess) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <h5 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Issues</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-link me-2 text-primary"></i>Add / Update Connection</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="connection">
                            <div class="mb-3">
                                <label class="form-label">Provider</label>
                                <select name="provider" class="form-select" required>
                                    <option value="Manual">Manual</option>
                                    <option value="Plaid">Plaid</option>
                                    <option value="MX">MX</option>
                                    <option value="Finicity">Finicity</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Connection Name</label>
                                <input type="text" name="connection_name" class="form-control" required placeholder="e.g., Frost Bank Production">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Secret Reference</label>
                                <input type="text" name="secret_reference" class="form-control" placeholder="Vault or secret manager key">
                                <div class="form-text">Store actual credentials in a secure vault; reference them here.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="pending">Pending</option>
                                    <option value="active">Active</option>
                                    <option value="error">Error</option>
                                    <option value="disconnected">Disconnected</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Metadata (JSON)</label>
                                <textarea name="metadata" class="form-control" rows="3" placeholder='{"environment":"sandbox"}'></textarea>
                                <div class="form-text">Use this for provider IDs, webhook URLs, etc.</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Connection</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-building-columns me-2 text-success"></i>Link Bank Account</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="bank_account">
                            <div class="mb-3">
                                <label class="form-label">Connection</label>
                                <select name="connection_id" class="form-select">
                                    <option value="">Manual / Offline</option>
                                    <?php foreach ($connections as $connection): ?>
                                        <option value="<?= (int)$connection['id'] ?>"><?= htmlspecialchars($connection['connection_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ledger Account Mapping</label>
                                <select name="ledger_account_id" class="form-select">
                                    <option value="">Select ledger account (optional)</option>
                                    <?php foreach ($ledgerAccounts as $ledger): ?>
                                        <option value="<?= (int)$ledger['id'] ?>"><?= htmlspecialchars($ledger['account_number'] . ' - ' . $ledger['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Institution Name</label>
                                <input type="text" name="institution_name" class="form-control" required placeholder="e.g., Frost Bank">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Display Name</label>
                                <input type="text" name="display_name" class="form-control" required placeholder="e.g., Frost - Ending 1234">
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label">Account Type</label>
                                    <select name="account_type" class="form-select">
                                        <option value="checking">Checking</option>
                                        <option value="savings">Savings</option>
                                        <option value="credit">Credit</option>
                                        <option value="loan">Loan</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label">Currency</label>
                                    <input type="text" name="currency" class="form-control" value="USD">
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label">Mask</label>
                                    <input type="text" name="account_mask" class="form-control" placeholder="1234">
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-sm-6">
                                    <label class="form-label">Routing Last 4</label>
                                    <input type="text" name="routing_last4" class="form-control" maxlength="4">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">External Account ID</label>
                                    <input type="text" name="external_account_id" class="form-control" placeholder="Provider account token">
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <label class="form-label">Status</label>
                                <select name="account_status" class="form-select">
                                    <option value="unlinked">Unlinked</option>
                                    <option value="linked">Linked</option>
                                    <option value="paused">Paused</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success"><i class="fas fa-plus me-1"></i>Add Mapping</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-plug-circle-check me-2 text-primary"></i>Existing Connections</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($connections)): ?>
                            <p class="text-muted mb-0">No connections yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Provider</th>
                                            <th>Status</th>
                                            <th>Last Synced</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($connections as $connection): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($connection['connection_name']) ?></td>
                                                <td><?= htmlspecialchars($connection['provider']) ?></td>
                                                <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($connection['status']) ?></span></td>
                                                <td><?= $connection['last_synced_at'] ? htmlspecialchars($connection['last_synced_at']) : '—' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2 text-success"></i>Linked Bank Accounts</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bankAccounts)): ?>
                            <p class="text-muted mb-0">No bank accounts have been linked yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Display</th>
                                            <th>Institution</th>
                                            <th>Ledger</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bankAccounts as $account): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($account['display_name']) ?></td>
                                                <td><?= htmlspecialchars($account['institution_name']) ?></td>
                                                <td>
                                                    <?php if (!empty($account['ledger_name'])): ?>
                                                        <?= htmlspecialchars($account['account_number'] . ' · ' . $account['ledger_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not mapped</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($account['status']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body">
                <h5 class="mb-2"><i class="fas fa-road-map me-2 text-info"></i>Next Steps for Automated Sync</h5>
                <ol class="text-muted small mb-0">
                    <li>Register with a bank aggregator (Plaid, MX, Finicity) and store API credentials in a vault referenced above.</li>
                    <li>Implement OAuth/token exchange endpoint that updates <code>acc_bank_connections.metadata</code> with provider access tokens.</li>
                    <li>Schedule nightly sync to fetch transactions into <code>acc_import_batches</code> and auto-stage them for review.</li>
                    <li>Enable webhooks to mark <em>last_synced_at</em> and raise alerts when connections need user re-consent.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../include/footer.php'; ?>
</body>
</html>
