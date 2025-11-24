<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/ledgerController.php';
require_once __DIR__ . '/../controllers/LedgerRegisterController.php';

validate_session();

$user_id = getCurrentUserId();
$canManageAccounting = hasPermission($user_id, 'accounting_manage');
if (!hasPermission($user_id, 'accounting_view') && !$canManageAccounting) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view the ledger register.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

if (!isset($_SESSION['ledger_adjust_csrf'])) {
    $_SESSION['ledger_adjust_csrf'] = bin2hex(random_bytes(32));
}
$adjustCsrfToken = $_SESSION['ledger_adjust_csrf'];

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value, $type = 'string')
    {
        if ($type === 'string') {
            return trim(filter_var($value, FILTER_SANITIZE_STRING));
        }
        if ($type === 'int') {
            return intval($value);
        }
        if ($type === 'float') {
            return floatval($value);
        }
        return $value;
    }
}

$preset = sanitizeInput($_GET['preset'] ?? '', 'string');
$requestedFrom = sanitizeInput($_GET['date_from'] ?? '', 'string');
$requestedTo = sanitizeInput($_GET['date_to'] ?? '', 'string');

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');

switch ($preset) {
    case 'today':
        $requestedFrom = date('Y-m-d');
        $requestedTo = date('Y-m-d');
        break;
    case 'week':
        $requestedFrom = date('Y-m-d', strtotime('monday this week'));
        $requestedTo = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $requestedFrom = date('Y-m-01');
        $requestedTo = date('Y-m-t');
        break;
    case 'last_month':
        $requestedFrom = date('Y-m-01', strtotime('first day of last month'));
        $requestedTo = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'quarter':
        $quarter = ceil(date('n') / 3);
        $year = date('Y');
        $startMonth = (($quarter - 1) * 3) + 1;
        $startString = sprintf('%04d-%02d-01', $year, $startMonth);
        $endMonth = $startMonth + 2;
        $endString = sprintf('%04d-%02d-01', $year, $endMonth);
        $requestedFrom = date('Y-m-01', strtotime($startString));
        $requestedTo = date('Y-m-t', strtotime($endString));
        break;
    case 'ytd':
        $requestedFrom = date('Y-01-01');
        $requestedTo = date('Y-m-d');
        break;
    default:
        break;
}

$date_from = $requestedFrom ?: $defaultFrom;
$date_to = $requestedTo ?: $defaultTo;

if (strtotime($date_from) > strtotime($date_to)) {
    $tmp = $date_from;
    $date_from = $date_to;
    $date_to = $tmp;
}

$account_id = isset($_GET['account_id']) && $_GET['account_id'] !== 'all'
    ? intval($_GET['account_id'])
    : null;

$source = $_GET['source'] ?? 'all';
$allowed_sources = ['all', 'transactions', 'journal'];
if (!in_array($source, $allowed_sources, true)) {
    $source = 'all';
}

$search = sanitizeInput($_GET['search'] ?? '', 'string');
$min_amount = isset($_GET['min_amount']) ? trim($_GET['min_amount']) : '';
$max_amount = isset($_GET['max_amount']) ? trim($_GET['max_amount']) : '';

$filters = [
    'account_id' => $account_id,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'source' => $source,
    'search' => $search,
    'min_amount' => $min_amount,
    'max_amount' => $max_amount,
];

$currentUrl = $_SERVER['REQUEST_URI'] ?? '/accounting/ledger/register.php';

$registerController = new LedgerRegisterController($conn);
$entries = $registerController->fetchEntries($filters);

$openingBoundary = $date_from ? date('Y-m-d', strtotime($date_from . ' -1 day')) : null;
$runningBalances = [];
$openingBalances = [];
$totalDebit = 0.0;
$totalCredit = 0.0;

foreach ($entries as &$entry) {
    $accountKey = (int)($entry['account_id'] ?? 0);

    $totalDebit += $entry['debit_amount'];
    $totalCredit += $entry['credit_amount'];

    if ($accountKey <= 0) {
        $entry['running_balance'] = 0.0;
        continue;
    }

    if (!isset($runningBalances[$accountKey])) {
        $opening = getAccountBalance($accountKey, $openingBoundary);
        $runningBalances[$accountKey] = $opening;
        $openingBalances[$accountKey] = $opening;
    }

    $runningBalances[$accountKey] = adjustLedgerBalance(
        $runningBalances[$accountKey],
        $entry['debit_amount'],
        $entry['credit_amount'],
        $entry['account_type'] ?? 'Asset'
    );
    $entry['running_balance'] = $runningBalances[$accountKey];
}
unset($entry);

if (isset($_GET['export']) && $_GET['export'] === '1') {
    $filename = 'ledger_register_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Reference', 'Source', 'Account', 'Category', 'Description', 'Debit', 'Credit', 'Balance']);
    foreach ($entries as $entry) {
        fputcsv($out, [
            $entry['entry_date'],
            $entry['reference'],
            ucfirst($entry['entry_source']),
            $entry['account_name'],
            $entry['category_name'],
            $entry['memo'],
            number_format($entry['debit_amount'], 2, '.', ''),
            number_format($entry['credit_amount'], 2, '.', ''),
            number_format($entry['running_balance'] ?? 0, 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

$accounts = getAllLedgerAccounts([], ['order_by' => 'a.name ASC']);
$selectedAccount = null;
if ($account_id) {
    foreach ($accounts as $acct) {
        if ((int)$acct['id'] === $account_id) {
            $selectedAccount = $acct;
            break;
        }
    }
}

$page_title = 'Ledger Register - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';

$endingBalance = ($account_id && isset($runningBalances[$account_id])) ? $runningBalances[$account_id] : null;
$openingBalance = ($account_id && isset($openingBalances[$account_id])) ? $openingBalances[$account_id] : null;
$netChange = $totalDebit - $totalCredit;
$entryCount = count($entries);
$coveredAccounts = array_keys($runningBalances);
$returnUrl = htmlspecialchars($currentUrl, ENT_QUOTES);

$logoSrc = accounting_logo_src_for(__DIR__);
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <?php if (function_exists('displayToastMessage')) {
        displayToastMessage();
    } ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <section class="hero hero-small mb-4">
            <div class="hero-body py-3">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-2 d-none d-md-flex justify-content-center">
                            <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                        </div>
                        <div class="col-md-6 text-center text-md-start text-white">
                            <h1 class="h4 mb-1">Ledger Register</h1>
                            <p class="mb-0 small">Full debit/credit history across your accounts.</p>
                        </div>
                        <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                            <a href="/accounting/ledger/index.php" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-sitemap me-1"></i>Chart of Accounts
                            </a>
                            <a href="/accounting/dashboard.php" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-arrow-left me-1"></i>Dashboard
                            </a>
                            <?php if ($canManageAccounting): ?>
                                <a href="/accounting/transactions/add_transaction.php?mode=journal" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus-circle me-1"></i>New Journal Entry
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row mb-3 hero-summary-row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Opening Balance</span>
                        <i class="fas fa-unlock text-primary"></i>
                    </div>
                    <h4 class="mb-0"><?= formatCurrencyValue($openingBalance); ?></h4>
                    <small class="text-muted">As of <?= htmlspecialchars($date_from); ?></small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Ending Balance</span>
                        <i class="fas fa-lock text-success"></i>
                    </div>
                    <h4 class="mb-0"><?= formatCurrencyValue($endingBalance); ?></h4>
                    <small class="text-muted">After filters</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Total Debits</span>
                        <i class="fas fa-arrow-down text-danger"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($totalDebit, 2); ?></h4>
                    <small class="text-muted">All entries in view</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Total Credits</span>
                        <i class="fas fa-arrow-up text-success"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($totalCredit, 2); ?></h4>
                    <small class="text-muted">Entries in scope</small>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Ledger Filters</h5>
                    </div>
                    <div class="col-auto">
                        <a href="register.php" class="btn btn-outline-light btn-sm"><i class="fas fa-times me-1"></i>Reset</a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 flex-column flex-lg-row">
                    <div class="col-12 col-lg-3 border-bottom border-lg-bottom-0 border-lg-end bg-light-subtle p-3">
                        <h6 class="text-uppercase small text-muted fw-bold mb-2">Quick Presets</h6>
                        <p class="text-muted small mb-3">Jump to a common date range.</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="today">
                                <span class="fw-semibold">Today</span>
                                <small class="d-block text-muted">Current day</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="week">
                                <span class="fw-semibold">This Week</span>
                                <small class="d-block text-muted">Monday - Sunday</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="month">
                                <span class="fw-semibold">This Month</span>
                                <small class="d-block text-muted">Month to date</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="last_month">
                                <span class="fw-semibold">Last Month</span>
                                <small class="d-block text-muted">Previous month</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="ytd">
                                <span class="fw-semibold">Year to Date</span>
                                <small class="d-block text-muted">Jan 1 - Today</small>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="registerFilterForm" class="row g-3">
                            <div class="col-md-6">
                                <label for="account_id" class="form-label text-muted text-uppercase small mb-1">Account</label>
                                <select name="account_id" id="account_id" class="form-select form-select-sm">
                                    <option value="all">All Accounts</option>
                                    <?php foreach ($accounts as $acct): ?>
                                        <option value="<?= intval($acct['id']); ?>" <?= ($account_id === intval($acct['id'])) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($acct['account_number'] . ' • ' . $acct['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label text-muted text-uppercase small mb-1">From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label text-muted text-uppercase small mb-1">To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="source" class="form-label text-muted text-uppercase small mb-1">Source</label>
                                <select name="source" id="source" class="form-select form-select-sm">
                                    <option value="all" <?= $source === 'all' ? 'selected' : ''; ?>>All Sources</option>
                                    <option value="transactions" <?= $source === 'transactions' ? 'selected' : ''; ?>>Transactions</option>
                                    <option value="journal" <?= $source === 'journal' ? 'selected' : ''; ?>>Journal Entries</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Reference, memo, category" value="<?= htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="min_amount" class="form-label text-muted text-uppercase small mb-1">Min Amount</label>
                                <input type="number" step="0.01" name="min_amount" id="min_amount" class="form-control form-control-sm" value="<?= htmlspecialchars($min_amount); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="max_amount" class="form-label text-muted text-uppercase small mb-1">Max Amount</label>
                                <input type="number" step="0.01" name="max_amount" id="max_amount" class="form-control form-control-sm" value="<?= htmlspecialchars($max_amount); ?>">
                            </div>
                            <div class="col-12 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2 mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="registerExportBtn">
                                    <i class="fas fa-file-export me-1"></i>Export CSV
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-header bg-light border-0 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>Entries (<?= $entryCount; ?>)</h5>
                    <small class="text-muted">Covering <?= htmlspecialchars($date_from); ?> to <?= htmlspecialchars($date_to); ?> across <?= count($coveredAccounts); ?> account(s)</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Source</th>
                                <?php if (!$account_id): ?>
                                    <th>Account</th>
                                <?php endif; ?>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($entries)): ?>
                                <tr>
                                    <td colspan="<?= $account_id ? '8' : '9'; ?>" class="text-center text-muted py-4">
                                        No ledger entries found for the selected filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><?= date('m/d/Y', strtotime($entry['entry_date'])); ?></td>
                                        <td><?= htmlspecialchars($entry['reference'] ?: '—'); ?></td>
                                        <td>
                                            <span class="badge bg-<?= $entry['entry_source'] === 'journal' ? 'secondary' : 'info'; ?>">
                                                <?= ucfirst($entry['entry_source']); ?>
                                            </span>
                                        </td>
                                        <?php if (!$account_id): ?>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($entry['account_name'] ?? 'Unknown'); ?></div>
                                                <small class="text-muted">#<?= htmlspecialchars($entry['account_number'] ?? '—'); ?></small>
                                            </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($entry['category_name'] ?? '—'); ?></td>
                                        <td>
                                            <div class="fw-semibold mb-0"><?= htmlspecialchars($entry['memo'] ?? '—'); ?></div>
                                            <?php if (!empty($entry['vendor_name'])): ?>
                                                <small class="text-muted">Vendor: <?= htmlspecialchars($entry['vendor_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-danger">$<?= number_format($entry['debit_amount'], 2); ?></td>
                                        <td class="text-end text-success">$<?= number_format($entry['credit_amount'], 2); ?></td>
                                        <td class="text-end fw-semibold">$<?= number_format($entry['running_balance'], 2); ?></td>
                                        <td class="text-end">
                                            <?php $detailUrl = getEntryDetailUrl($entry); ?>
                                            <?php $canAdjustThis = $canManageAccounting && !empty($entry['account_id']); ?>
                                            <?php if ($detailUrl || $canAdjustThis): ?>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if ($detailUrl): ?>
                                                        <a href="<?= htmlspecialchars($detailUrl); ?>" class="btn btn-outline-primary" title="Edit original entry">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($canAdjustThis): ?>
                                                        <button type="button" class="btn btn-outline-warning ledger-adjust-btn" title="Create adjusting entry"
                                                            data-account-id="<?= intval($entry['account_id']); ?>"
                                                            data-account-name="<?= htmlspecialchars($entry['account_name'] ?? ''); ?>"
                                                            data-account-type="<?= htmlspecialchars($entry['account_type'] ?? ''); ?>"
                                                            data-entry-source="<?= htmlspecialchars($entry['entry_source']); ?>"
                                                            data-entry-reference="<?= htmlspecialchars($entry['reference'] ?? ''); ?>"
                                                            data-entry-memo="<?= htmlspecialchars($entry['memo'] ?? ''); ?>"
                                                            data-entry-date="<?= htmlspecialchars($entry['entry_date']); ?>"
                                                            data-debit="<?= htmlspecialchars((string)$entry['debit_amount']); ?>"
                                                            data-credit="<?= htmlspecialchars((string)$entry['credit_amount']); ?>">
                                                            <i class="fas fa-adjust"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($canManageAccounting): ?>
            <div class="modal fade" id="adjustEntryModal" tabindex="-1" aria-labelledby="adjustEntryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form method="POST" action="/accounting/ledger/adjust_entry.php" class="modal-content" id="adjustEntryForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="adjustEntryModalLabel">Create Adjusting Entry</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info small" id="adjustEntrySummary">
                                Select a ledger row to load its details.
                            </div>
                            <input type="hidden" name="account_id" id="adjust_account_id">
                            <input type="hidden" name="entry_source" id="adjust_entry_source">
                            <input type="hidden" name="entry_reference" id="adjust_entry_reference">
                            <input type="hidden" name="entry_memo" id="adjust_entry_memo">
                            <input type="hidden" name="return_url" value="<?= $returnUrl; ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adjustCsrfToken, ENT_QUOTES); ?>">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="adjust_date" class="form-label text-muted text-uppercase small mb-1">Adjustment Date</label>
                                    <input type="date" name="adjust_date" id="adjust_date" class="form-control form-control-sm" value="<?= htmlspecialchars(date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-8">
                                    <label for="adjust_offset_account" class="form-label text-muted text-uppercase small mb-1">Offset Account</label>
                                    <select name="offset_account_id" id="adjust_offset_account" class="form-select form-select-sm" required>
                                        <option value="">Select offset account</option>
                                        <?php foreach ($accounts as $acct): ?>
                                            <option value="<?= intval($acct['id']); ?>"><?= htmlspecialchars($acct['account_number'] . ' • ' . $acct['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Use an offset account to balance the adjustment.</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="adjust_debit" class="form-label text-muted text-uppercase small mb-1">Debit Amount</label>
                                    <input type="number" step="0.01" name="debit_amount" id="adjust_debit" class="form-control form-control-sm" placeholder="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label for="adjust_credit" class="form-label text-muted text-uppercase small mb-1">Credit Amount</label>
                                    <input type="number" step="0.01" name="credit_amount" id="adjust_credit" class="form-control form-control-sm" placeholder="0.00">
                                </div>
                                <div class="col-12">
                                    <label for="adjust_memo" class="form-label text-muted text-uppercase small mb-1">Memo</label>
                                    <textarea name="memo" id="adjust_memo" rows="3" class="form-control form-control-sm" placeholder="Explain why this adjustment is needed" required></textarea>
                                    <small class="text-muted">Enter either a debit or credit amount (not both). The offset account will inherit the opposite value automatically.</small>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-check me-1"></i>Post Adjustment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        document.querySelectorAll('[data-preset]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const preset = btn.getAttribute('data-preset');
                const url = new URL(window.location.href);
                url.searchParams.set('preset', preset);
                url.searchParams.delete('export');
                window.location.href = url.toString();
            });
        });

        const exportBtn = document.getElementById('registerExportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                const form = document.getElementById('registerFilterForm');
                if (!form) return;
                const url = new URL(window.location.href);
                const formData = new FormData(form);
                formData.forEach((value, key) => {
                    url.searchParams.set(key, value);
                });
                url.searchParams.set('export', '1');
                window.location.href = url.toString();
            });
        }

        <?php if ($canManageAccounting): ?>
                (function() {
                    const adjustModalEl = document.getElementById('adjustEntryModal');
                    const adjustButtons = document.querySelectorAll('.ledger-adjust-btn');
                    if (!adjustModalEl || !adjustButtons.length || typeof bootstrap === 'undefined') {
                        return;
                    }

                    const adjustModal = new bootstrap.Modal(adjustModalEl);
                    const adjustAccountInput = document.getElementById('adjust_account_id');
                    const adjustSourceInput = document.getElementById('adjust_entry_source');
                    const adjustReferenceInput = document.getElementById('adjust_entry_reference');
                    const adjustMemoHidden = document.getElementById('adjust_entry_memo');
                    const adjustSummary = document.getElementById('adjustEntrySummary');
                    const adjustDateInput = document.getElementById('adjust_date');
                    const adjustDebitInput = document.getElementById('adjust_debit');
                    const adjustCreditInput = document.getElementById('adjust_credit');
                    const adjustOffsetSelect = document.getElementById('adjust_offset_account');
                    const adjustMemoInput = document.getElementById('adjust_memo');

                    adjustButtons.forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const accountId = btn.getAttribute('data-account-id') || '';
                            const accountName = btn.getAttribute('data-account-name') || 'Selected account';
                            const entryDate = btn.getAttribute('data-entry-date') || new Date().toISOString().slice(0, 10);
                            const reference = btn.getAttribute('data-entry-reference') || 'N/A';
                            const memo = btn.getAttribute('data-entry-memo') || '';
                            const source = btn.getAttribute('data-entry-source') || '';
                            const debit = parseFloat(btn.getAttribute('data-debit')) || 0;
                            const credit = parseFloat(btn.getAttribute('data-credit')) || 0;

                            if (adjustSummary) {
                                adjustSummary.textContent = accountName + ' • ' + entryDate + ' (Ref: ' + reference + ')';
                            }

                            if (adjustAccountInput) adjustAccountInput.value = accountId;
                            if (adjustSourceInput) adjustSourceInput.value = source;
                            if (adjustReferenceInput) adjustReferenceInput.value = reference;
                            if (adjustMemoHidden) adjustMemoHidden.value = memo;
                            if (adjustDateInput) adjustDateInput.value = entryDate;

                            if (adjustDebitInput) {
                                adjustDebitInput.value = debit > 0 ? debit.toFixed(2) : '';
                            }
                            if (adjustCreditInput) {
                                adjustCreditInput.value = credit > 0 ? credit.toFixed(2) : '';
                            }

                            if (adjustOffsetSelect) {
                                let fallbackOption = '';
                                Array.from(adjustOffsetSelect.options).forEach(function(option) {
                                    if (!option.value || option.value === accountId) {
                                        return;
                                    }
                                    if (!fallbackOption) {
                                        fallbackOption = option.value;
                                    }
                                });
                                if (adjustOffsetSelect.value === accountId || !adjustOffsetSelect.value) {
                                    adjustOffsetSelect.value = fallbackOption;
                                }
                                if (adjustOffsetSelect.value === accountId) {
                                    adjustOffsetSelect.selectedIndex = 0;
                                }
                            }

                            if (adjustMemoInput) {
                                const defaultMemo = reference && reference !== 'N/A' ?
                                    'Adjustment for ' + reference :
                                    'Adjustment for entry on ' + entryDate;
                                adjustMemoInput.value = defaultMemo;
                            }

                            adjustModal.show();
                        });
                    });
                })();
        <?php endif; ?>
    </script>
</body>

</html>
<?php
function adjustLedgerBalance(float $currentBalance, float $debit, float $credit, string $accountType): float
{
    $type = strtolower($accountType);
    if (in_array($type, ['asset', 'expense'], true)) {
        return $currentBalance + $debit - $credit;
    }
    return $currentBalance - $debit + $credit;
}

function formatCurrencyValue($value): string
{
    if ($value === null) {
        return '—';
    }
    return '$' . number_format((float)$value, 2);
}

function getEntryDetailUrl(array $entry): ?string
{
    if ($entry['entry_source'] === 'transaction') {
        return '/accounting/transactions/edit_transaction.php?id=' . intval($entry['raw']['id'] ?? 0);
    }
    return null;
}
?>