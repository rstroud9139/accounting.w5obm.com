<?php

declare(strict_types=1);

session_start();

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
    setToastMessage('danger', 'Access Denied', 'Only accounting managers can run the reset utility.', 'fas fa-shield');
    header('Location: /accounting/dashboard.php');
    exit();
}

$tableGroups = [
    'Operational Tables' => [
        'acc_import_row_errors',
        'acc_import_rows',
        'acc_import_batches',
        'acc_journal_lines',
        'acc_journals',
        'acc_transactions',
        'acc_transaction_categories',
        'acc_donations',
        'acc_recurring_transactions',
        'acc_reports',
        'acc_assets',
        'acc_items',
        'acc_membership_dues',
    ],
    'Bank Linking' => [
        'acc_bank_accounts',
        'acc_bank_connections',
    ],
];

$chartTables = ['acc_ledger_accounts', 'acc_categories'];

$results = [];
$errors = [];
$didReset = false;

function tableExists(mysqli $conn, string $table): bool
{
    $sql = 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function ensureResetAuditTable(mysqli $conn): void
{
    $conn->query('CREATE TABLE IF NOT EXISTS acc_admin_resets (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        run_by INT NULL,
        include_chart_of_accounts TINYINT(1) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        cleared_tables JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify_post_or_throw();
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
    }

    if (empty($errors)) {
        $wipeChart = isset($_POST['wipe_coa']) && $_POST['wipe_coa'] === '1';
        $note = trim((string)($_POST['note'] ?? ''));

        $tablesToClear = [];
        foreach ($tableGroups as $groupTables) {
            foreach ($groupTables as $table) {
                if (tableExists($accConn, $table)) {
                    $tablesToClear[] = $table;
                }
            }
        }

        if ($wipeChart) {
            foreach ($chartTables as $table) {
                if (tableExists($accConn, $table)) {
                    $tablesToClear[] = $table;
                }
            }
        }

        if (empty($tablesToClear)) {
            $errors[] = 'No matching tables were found in accounting_w5obm. Nothing to reset.';
        } else {
            $accConn->query('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tablesToClear as $table) {
                if ($accConn->query("TRUNCATE TABLE `{$table}`")) {
                    $results[] = "Cleared {$table}";
                } else {
                    $errors[] = "Failed to truncate {$table}: " . $accConn->error;
                }
            }
            $accConn->query('SET FOREIGN_KEY_CHECKS=1');
        }

        if (empty($errors)) {
            ensureResetAuditTable($accConn);
            $clearedJson = json_encode($tablesToClear, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt = $accConn->prepare('INSERT INTO acc_admin_resets (run_by, include_chart_of_accounts, note, cleared_tables) VALUES (?, ?, ?, ?)');
            if ($stmt) {
                $includeCoa = $wipeChart ? 1 : 0;
                $stmt->bind_param('iiss', $userId, $includeCoa, $note, $clearedJson);
                $stmt->execute();
                $stmt->close();
            }

            if (function_exists('logActivity')) {
                @logActivity($userId, 'accounting_data_reset', 'acc_admin_resets', null, 'Tables cleared: ' . count($tablesToClear));
            }

            $didReset = true;
        }
    }
}

$pageTitle = 'Accounting Data Reset Utility';
include __DIR__ . '/../../include/header.php';
include __DIR__ . '/../../include/menu.php';
?>

<div class="page-container accounting-app bg-light">
    <div class="container py-4">
        <div class="mb-4">
            <h1 class="h4 mb-1"><i class="fas fa-broom text-danger me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="text-muted mb-0">Purge transactional data while optionally preserving the chart of accounts.</p>
        </div>

        <?php if ($didReset): ?>
            <div class="alert alert-success shadow-sm">
                <h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Reset Complete</h5>
                <p class="mb-2">Cleared <?= count($results) ?> tables successfully.</p>
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

        <?php if (!$didReset): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="card-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Destructive Operation</h5>
                    <p class="card-text">This will permanently delete transactions, journals, imports, and related records from <code>accounting_w5obm</code>. Back up the database first. The chart of accounts and categories are preserved unless you check the optional box.</p>
                    <p class="small text-muted mb-0">Tip: Run this during maintenance windows and notify leadership beforehand.</p>
                </div>
            </div>

            <form method="POST" class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tables that will be truncated</label>
                        <?php foreach ($tableGroups as $group => $tables): ?>
                            <div class="mb-2">
                                <span class="text-uppercase text-muted small d-block mb-1"><?= htmlspecialchars($group) ?></span>
                                <div class="small bg-light rounded p-2">
                                    <?= htmlspecialchars(implode(', ', $tables)) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="wipeCoa" name="wipe_coa" value="1">
                        <label class="form-check-label" for="wipeCoa">Also truncate Chart of Accounts (<?= htmlspecialchars(implode(', ', $chartTables)) ?>)</label>
                        <div class="form-text text-danger">Only enable if you plan to immediately reseed the chart.</div>
                    </div>
                    <div class="mb-3">
                        <label for="note" class="form-label">Maintenance note (optional)</label>
                        <input type="text" class="form-control" id="note" name="note" maxlength="200" placeholder="e.g., Reset before 2026 migration">
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Yes, purge selected data</button>
                    <a href="/accounting/admin/utilities.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../include/footer.php'; ?>
