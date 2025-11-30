<?php

require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../utils/csrf.php';

/** @var mysqli $accConn */

csrf_ensure_token();

if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'Only accounting managers can run the seed utility.', 'fas fa-ban');
    header('Location: /accounting/dashboard.php');
    exit();
}

$seedData = require __DIR__ . '/../data/coa_seed.php';
$accountsSeed = isset($seedData['accounts']) && is_array($seedData['accounts']) ? $seedData['accounts'] : [];
$categoriesSeed = isset($seedData['categories']) && is_array($seedData['categories']) ? $seedData['categories'] : [];

$resultMessages = [];
$errorMessages = [];
$didSeed = false;

function upsertLedgerAccount(mysqli $conn, array $account, int $userId): void
{
    $parentId = null;
    if (!empty($account['parent_code'])) {
        $stmt = $conn->prepare('SELECT id FROM acc_ledger_accounts WHERE template_code = ? OR account_number = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ss', $account['parent_code'], $account['parent_code']);
            $stmt->execute();
            $stmt->bind_result($parentId);
            $stmt->fetch();
            $stmt->close();
        }
    }

    $select = $conn->prepare('SELECT id FROM acc_ledger_accounts WHERE account_number = ? LIMIT 1');
    if (!$select) {
        throw new RuntimeException('Unable to prepare ledger lookup: ' . $conn->error);
    }
    $select->bind_param('s', $account['account_number']);
    $select->execute();
    $select->bind_result($existingId);
    $exists = $select->fetch();
    $select->close();

    if ($exists) {
        $update = $conn->prepare('UPDATE acc_ledger_accounts SET name = ?, description = ?, account_type = ?, parent_account_id = ?, normal_balance = ?, template_code = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
        if (!$update) {
            throw new RuntimeException('Unable to prepare ledger update: ' . $conn->error);
        }
        $parentParam = $parentId;
        $templateCode = $account['code'];
        $update->bind_param(
            'sssissii',
            $account['name'],
            $account['description'],
            $account['account_type'],
            $parentParam,
            $account['normal_balance'],
            $templateCode,
            $userId,
            $existingId
        );
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare('INSERT INTO acc_ledger_accounts (category_id, name, description, account_number, account_type, parent_account_id, active, created_by, updated_by, normal_balance, template_code)
            VALUES (NULL, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)');
        if (!$insert) {
            throw new RuntimeException('Unable to prepare ledger insert: ' . $conn->error);
        }
        $parentParam = $parentId;
        $templateCode = $account['code'];
        $insert->bind_param(
            'ssssiiiss',
            $account['name'],
            $account['description'],
            $account['account_number'],
            $account['account_type'],
            $parentParam,
            $userId,
            $userId,
            $account['normal_balance'],
            $templateCode
        );
        $insert->execute();
        $insert->close();
    }
}

function upsertCategory(mysqli $conn, array $category): void
{
    $select = $conn->prepare('SELECT id FROM acc_categories WHERE name = ? LIMIT 1');
    if (!$select) {
        throw new RuntimeException('Unable to prepare category lookup: ' . $conn->error);
    }
    $select->bind_param('s', $category['name']);
    $select->execute();
    $select->bind_result($existingId);
    $exists = $select->fetch();
    $select->close();

    if ($exists) {
        $update = $conn->prepare('UPDATE acc_categories SET description = ?, account_number = ?, account_type = ?, updated_at = NOW() WHERE id = ?');
        if (!$update) {
            throw new RuntimeException('Unable to prepare category update: ' . $conn->error);
        }
        $update->bind_param('sssi', $category['description'], $category['default_account_number'], $category['account_type'], $existingId);
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare('INSERT INTO acc_categories (name, description, account_number, account_type) VALUES (?, ?, ?, ?)');
        if (!$insert) {
            throw new RuntimeException('Unable to prepare category insert: ' . $conn->error);
        }
        $insert->bind_param('ssss', $category['name'], $category['description'], $category['default_account_number'], $category['account_type']);
        $insert->execute();
        $insert->close();
    }
}

function seedCoaTemplates(mysqli $conn, array $accounts): void
{
    $sql = 'INSERT INTO acc_coa_templates (code, account_number, name, account_type, normal_balance, description, parent_code, reporting_category)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE account_number = VALUES(account_number), name = VALUES(name), account_type = VALUES(account_type), normal_balance = VALUES(normal_balance), description = VALUES(description), parent_code = VALUES(parent_code), reporting_category = VALUES(reporting_category)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare COA template upsert: ' . $conn->error);
    }
    foreach ($accounts as $account) {
        $parent = isset($account['parent_code']) ? $account['parent_code'] : null;
        $reportingCategory = isset($account['reporting_category']) ? $account['reporting_category'] : null;
        $stmt->bind_param(
            'ssssssss',
            $account['code'],
            $account['account_number'],
            $account['name'],
            $account['account_type'],
            $account['normal_balance'],
            $account['description'],
            $parent,
            $reportingCategory
        );
        $stmt->execute();
    }
    $stmt->close();
}

function seedCategoryTemplates(mysqli $conn, array $categories): void
{
    $sql = 'INSERT INTO acc_category_templates (code, name, account_type, description, default_account_number, reporting_group)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name), account_type = VALUES(account_type), description = VALUES(description), default_account_number = VALUES(default_account_number), reporting_group = VALUES(reporting_group)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare category template upsert: ' . $conn->error);
    }
    foreach ($categories as $category) {
        $reportingGroup = isset($category['reporting_group']) ? $category['reporting_group'] : null;
        $stmt->bind_param(
            'ssssss',
            $category['code'],
            $category['name'],
            $category['account_type'],
            $category['description'],
            $category['default_account_number'],
            $reportingGroup
        );
        $stmt->execute();
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify_post_or_throw();
        $truncateFirst = isset($_POST['truncate_first']) && $_POST['truncate_first'] === '1';

        if ($truncateFirst) {
            $accConn->query('SET FOREIGN_KEY_CHECKS=0');
            $accConn->query('TRUNCATE TABLE acc_ledger_accounts');
            $accConn->query('TRUNCATE TABLE acc_categories');
            $accConn->query('SET FOREIGN_KEY_CHECKS=1');
            $resultMessages[] = 'Existing chart of accounts and categories cleared.';
        }

        foreach ($accountsSeed as $account) {
            upsertLedgerAccount($accConn, $account, $userId);
        }
        $resultMessages[] = 'Chart of accounts synchronized (' . count($accountsSeed) . ' templates).';

        foreach ($categoriesSeed as $category) {
            upsertCategory($accConn, $category);
        }
        $resultMessages[] = 'Categories synchronized (' . count($categoriesSeed) . ' templates).';

        seedCoaTemplates($accConn, $accountsSeed);
        seedCategoryTemplates($accConn, $categoriesSeed);
        $resultMessages[] = 'Template tables updated for future provisioning.';

        if (function_exists('logActivity')) {
            @logActivity($userId, 'seed_chart_of_accounts', 'acc_ledger_accounts', null, 'Seeded chart + categories from template set');
        }

        $didSeed = true;
    } catch (Exception $ex) {
        $errorMessages[] = $ex->getMessage();
    }
}

$pageTitle = 'Seed Chart of Accounts & Categories';
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
            <h1 class="h4 mb-1"><i class="fas fa-sitemap text-primary me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="text-muted mb-0">Load the industry-standard template set for ledger accounts and reporting categories.</p>
        </div>

        <?php if ($didSeed && empty($errorMessages)): ?>
            <div class="alert alert-success shadow-sm">
                <h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Seed Complete</h5>
                <ul class="mb-2">
                    <?php foreach ($resultMessages as $line): ?>
                        <li><?= htmlspecialchars($line) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="/accounting/ledger/" class="btn btn-success btn-sm"><i class="fas fa-book me-1"></i>View Chart of Accounts</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessages)): ?>
            <div class="alert alert-danger shadow-sm">
                <h5 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Issues Encountered</h5>
                <ul class="mb-0">
                    <?php foreach ($errorMessages as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$didSeed): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <p class="mb-2">Template coverage:</p>
                    <ul class="mb-0">
                        <li><?= number_format(count($accountsSeed)) ?> ledger accounts</li>
                        <li><?= number_format(count($categoriesSeed)) ?> reporting categories</li>
                    </ul>
                </div>
            </div>

            <form method="POST" class="card shadow-sm border-0">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="truncateFirst" name="truncate_first" value="1">
                        <label class="form-check-label" for="truncateFirst">Truncate existing chart and categories before seeding</label>
                        <div class="form-text text-danger">Recommended when resetting the ledger or after major migrations.</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-download-alt me-1"></i>Seed Templates</button>
                    <a href="/accounting/admin/utilities.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../include/footer.php'; ?>
</body>
</html>
