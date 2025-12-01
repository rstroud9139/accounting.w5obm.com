<?php

require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../utils/csrf.php';

csrf_ensure_token();

try {
    $db = accounting_db_connection();
} catch (Exception $e) {
    setToastMessage('danger', 'Database Unavailable', 'The accounting database connection is not available.', 'fas fa-plug');
    header('Location: /accounting/dashboard.php');
    exit();
}

if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'Only accounting managers can reseed the chart.', 'fas fa-ban');
    header('Location: /accounting/dashboard.php');
    exit();
}

$tablesToTruncate = [
    'acc_journal_lines',
    'acc_journals',
    'acc_transactions',
    'acc_ledger_accounts',
];

$results = [];
$errors = [];
$didReseed = false;

function accounting_reseed_truncate(mysqli $conn, array $tables): array
{
    $messages = [];
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        if ($conn->query("TRUNCATE TABLE `{$table}`")) {
            $messages[] = "Cleared {$table}";
        } else {
            throw new RuntimeException('Failed to truncate ' . $table . ': ' . $conn->error);
        }
    }
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    return $messages;
}

function accounting_reseed_seed_accounts(mysqli $conn, array $accounts, ?int $userId): array
{
    if (empty($accounts)) {
        throw new RuntimeException('No ledger templates were provided.');
    }

    $insert = $conn->prepare('INSERT INTO acc_ledger_accounts (category_id, name, description, account_number, account_type, parent_account_id, active, created_by, updated_by, normal_balance, template_code, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW(), NOW())');
    if (!$insert) {
        throw new RuntimeException('Unable to prepare ledger insert: ' . $conn->error);
    }

    $created = 0;
    $codeMap = [];
    foreach ($accounts as $account) {
        $name = isset($account['name']) ? $account['name'] : null;
        $accountNumber = isset($account['account_number']) ? $account['account_number'] : null;
        $accountType = isset($account['account_type']) ? $account['account_type'] : null;
        if (!$name || !$accountNumber || !$accountType) {
            continue;
        }

        $parentId = null;
        if (!empty($account['parent_code']) && isset($codeMap[$account['parent_code']])) {
            $parentId = $codeMap[$account['parent_code']];
        }

        $description = isset($account['description']) ? $account['description'] : '';
        $normalBalance = isset($account['normal_balance']) ? $account['normal_balance'] : null;
        $templateCode = isset($account['code']) ? $account['code'] : null;
        $createdBy = $userId ?: null;
        $updatedBy = $userId ?: null;

        $insert->bind_param(
            'ssssiiiss',
            $name,
            $description,
            $accountNumber,
            $accountType,
            $parentId,
            $createdBy,
            $updatedBy,
            $normalBalance,
            $templateCode
        );

        if (!$insert->execute()) {
            $insert->close();
            throw new RuntimeException('Failed to insert ledger account ' . $accountNumber . ': ' . $conn->error);
        }

        $created++;
        $newId = (int)$insert->insert_id;
        if (!empty($templateCode)) {
            $codeMap[$templateCode] = $newId;
        }
        if (!empty($accountNumber)) {
            $codeMap[$accountNumber] = $newId;
        }
    }

    $insert->close();
    return ['created' => $created];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify_post_or_throw();
        $confirmationInput = isset($_POST['confirm_text']) ? $_POST['confirm_text'] : '';
        $confirmation = strtoupper(trim((string)$confirmationInput));
        if ($confirmation !== 'RESEED') {
            throw new RuntimeException('Type RESEED in the confirmation box to continue.');
        }

        $seedData = require __DIR__ . '/../data/coa_seed.php';
        $accountsSeed = isset($seedData['accounts']) && is_array($seedData['accounts']) ? $seedData['accounts'] : [];

        $results = accounting_reseed_truncate($db, $tablesToTruncate);
        $ledgerResult = accounting_reseed_seed_accounts($db, $accountsSeed, $userId);
        $results[] = sprintf('Seeded %d ledger accounts from template.', $ledgerResult['created']);

        $didReseed = true;

        if (function_exists('logActivity')) {
            @logActivity($userId, 'reseed_chart_of_accounts', 'acc_ledger_accounts', null, 'Ledger wiped and reseeded via reseed_chart_of_accounts.php');
        }
    } catch (Exception $ex) {
        $errors[] = $ex->getMessage();
    }
}

$pageTitle = 'Reseed Chart of Accounts (Legacy)';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="page-container accounting-app bg-light">
        <div class="container py-4">
            <div class="mb-4">
                <h1 class="h4 mb-1"><i class="fas fa-exclamation-triangle text-danger me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="text-muted mb-0">Danger zone: truncates ledger, journals, and transactions before applying the template chart.</p>
            </div>

            <?php if ($didReseed && empty($errors)): ?>
                <div class="alert alert-success shadow-sm">
                    <h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Ledger Reseeded</h5>
                    <ul class="mb-2">
                        <?php foreach ($results as $line): ?>
                            <li><?= htmlspecialchars($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="/accounting/dashboard.php" class="btn btn-success btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm">
                    <h5 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Issues Encountered</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$didReseed): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><i class="fas fa-bomb me-2"></i>Destructive Operation</h5>
                        <p class="mb-2">This action truncates the following tables in <code>accounting_w5obm</code>:</p>
                        <ul class="small">
                            <?php foreach ($tablesToTruncate as $table): ?>
                                <li><?= htmlspecialchars($table) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mb-0">Use only on disposable datasets or immediately after a full backup.</p>
                    </div>
                </div>

                <form method="POST" class="card border-0 shadow-sm">
                    <div class="card-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Confirmation</label>
                            <p class="mb-2">Type <strong>RESEED</strong> to confirm you understand this will wipe existing ledger data.</p>
                            <input type="text" name="confirm_text" class="form-control" maxlength="10" placeholder="RESEED" required>
                        </div>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-skull-crossbones me-1"></i>Yes, reseed the ledger</button>
                        <a href="/accounting/dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>