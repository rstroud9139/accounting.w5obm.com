<?php

/**
 * Ledger Account Detail Page
 * Provides a read-only view of a single ledger account with basic statistics.
 */

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/ledgerController.php';
require_once __DIR__ . '/../utils/stats_service.php';

if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();
if (!isSuperAdmin($user_id) && !isAdmin($user_id) && !hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'Insufficient permissions.', 'club-logo');
    header('Location: /authentication/dashboard.php');
    exit();
}

$account_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$account_id) {
    setToastMessage('warning', 'Missing Account', 'No account id provided.', 'club-logo');
    header('Location: /accounting/ledger/');
    exit();
}

// Fetch account details (using existing helper from controller if available)
function fetch_account($id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT a.*, \n        (SELECT COUNT(*) FROM acc_transactions t WHERE t.account_id = a.id) AS transaction_count,\n        (SELECT COALESCE(SUM(CASE WHEN type='Income' THEN amount ELSE -amount END),0) FROM acc_transactions t WHERE t.account_id = a.id) AS account_balance\n        FROM acc_ledger_accounts a WHERE a.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

$account = fetch_account($account_id);
if (!$account) {
    setToastMessage('danger', 'Not Found', 'Account not found.', 'club-logo');
    header('Location: /accounting/ledger/');
    exit();
}

$page_title = 'Account Detail - ' . htmlspecialchars($account['name']);
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>
    <div class="container mt-4">
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0">Account Detail</h3>
                    <small><?= htmlspecialchars($account['account_number']) ?> • <?= htmlspecialchars($account['account_type']) ?></small>
                </div>
                <div class="btn-group">
                    <a href="/accounting/ledger/edit.php?id=<?= $account_id ?>" class="btn btn-light btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                    <a href="/accounting/ledger/" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Description</h5>
                        <p class="text-muted mb-0"><?= $account['description'] ? nl2br(htmlspecialchars($account['description'])) : '—' ?></p>
                    </div>
                    <div class="col-md-3">
                        <h5>Status</h5>
                        <span class="badge bg-<?= $account['active'] ? 'success' : 'secondary' ?>"><?= $account['active'] ? 'Active' : 'Inactive' ?></span>
                    </div>
                    <div class="col-md-3">
                        <h5>Parent Account</h5>
                        <p class="mb-0 text-muted">
                            <?php if (!empty($account['parent_account_id'])): ?>
                                #<?= intval($account['parent_account_id']) ?>
                            <?php else: ?>
                                None
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="fw-bold">Balance</div>
                                <div class="fs-4 <?= $account['account_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($account['account_balance'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="fw-bold">Transactions</div>
                                <div class="fs-4"><?= intval($account['transaction_count']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="fw-bold">Account Number</div>
                                <div class="fs-5 text-muted"><?= htmlspecialchars($account['account_number']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <h5 class="mb-3">Recent Transactions</h5>
                <?php
                $stmt = $conn->prepare("SELECT id, description, amount, type, transaction_date FROM acc_transactions WHERE account_id = ? ORDER BY transaction_date DESC LIMIT 10");
                $stmt->bind_param('i', $account_id);
                $stmt->execute();
                $res = $stmt->get_result();
                ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tx = $res->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('m/d/Y', strtotime($tx['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars($tx['description']) ?></td>
                                    <td><span class="badge bg-<?= $tx['type'] === 'Income' ? 'success' : 'danger' ?>"><?= htmlspecialchars($tx['type']) ?></span></td>
                                    <td class="text-end <?= $tx['type'] === 'Income' ? 'text-success' : 'text-danger' ?>">$<?= number_format($tx['amount'], 2) ?></td>
                                    <td><a href="/accounting/transactions/edit_transaction.php?id=<?= $tx['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i></a></td>
                                </tr>
                            <?php endwhile;
                            $stmt->close(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>