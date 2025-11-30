<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/ledgerController.php';
require_once __DIR__ . '/../controllers/LedgerRegisterController.php';
require_once __DIR__ . '/../../include/premium_hero.php';

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

$registerController = new LedgerRegisterController($accConn ?? $conn);
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

$accountsById = [];
foreach ($accounts as $acct) {
    $accountsById[(int)$acct['id']] = $acct;
}

$groupedLedger = groupLedgerEntries($entries, $accountsById);
$activeFiltersList = buildLedgerActiveFilters($selectedAccount, $date_from, $date_to, $source, $search, $min_amount, $max_amount);
$filtersBadgeSummary = summarizeLedgerFilters($selectedAccount, $date_from, $date_to, $source, $search, $min_amount, $max_amount);
$recentManualChanges = summarizeRecentLedgerChanges($entries);

$page_title = 'Ledger Register - W5OBM Accounting';
include __DIR__ . '/../../include/header.php';

$endingBalance = ($account_id && isset($runningBalances[$account_id])) ? $runningBalances[$account_id] : null;
$openingBalance = ($account_id && isset($openingBalances[$account_id])) ? $openingBalances[$account_id] : null;
$netChange = $totalDebit - $totalCredit;
$entryCount = count($entries);
$coveredAccounts = array_keys($runningBalances);
$returnUrl = htmlspecialchars($currentUrl, ENT_QUOTES);

$logoSrc = accounting_logo_src_for(__DIR__);

$dateChip = 'Range: ' . date('M j', strtotime($date_from)) . ' → ' . date('M j, Y', strtotime($date_to));
$accountChip = $account_id ? 'Account #' . $account_id : 'Account: All';
$sourceChip = 'Source: ' . ucwords(str_replace('_', ' ', $source));

$registerHeroChips = [$dateChip, $accountChip, $sourceChip];
if (!empty($search)) {
    $registerHeroChips[] = 'Search: ' . $search;
}

$registerHeroHighlights = [
    [
        'label' => 'Entries',
        'value' => number_format($entryCount),
        'meta' => 'Matched filters'
    ],
    [
        'label' => 'Total Debit',
        'value' => '$' . number_format($totalDebit, 2),
        'meta' => 'Period activity'
    ],
    [
        'label' => 'Total Credit',
        'value' => '$' . number_format($totalCredit, 2),
        'meta' => 'Period activity'
    ],
];

if ($account_id && $endingBalance !== null) {
    $registerHeroHighlights[] = [
        'label' => 'Ending Balance',
        'value' => '$' . number_format($endingBalance, 2),
        'meta' => $selectedAccount['name'] ?? 'Selected account'
    ];
}

$registerHeroActions = array_values(array_filter([
    [
        'label' => 'Export CSV',
        'url' => '/accounting/ledger/register.php?' . http_build_query(array_merge($_GET, ['export' => '1'])),
        'variant' => 'outline',
        'icon' => 'fa-file-export'
    ],
    $canManageAccounting ? [
        'label' => 'New Journal Entry',
        'url' => '/accounting/transactions/transactions.php',
        'variant' => 'outline',
        'icon' => 'fa-table'
    ] : null,
    [
        'label' => 'Back to Ledger',
        'url' => '/accounting/ledger/',
        'icon' => 'fa-arrow-left'
    ],
]));
?>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <?php if (function_exists('displayToastMessage')) {
        displayToastMessage();
    } ?>

    <style>
        .ledger-register-shell {
            padding: 1rem 0 3rem;
        }

        .ledger-stat-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            margin-bottom: 1.5rem;
        }

        .ledger-stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.1rem 1.35rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.3);
        }

        .ledger-stat-label {
            font-size: 0.78rem;

            .ledger-sidebar-intro {
                margin-bottom: 1rem;
            }

            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.15rem;
            display: block;
        }

        .ledger-stat-value {
            font-size: 1.6rem;
            font-weight: 600;
            color: #0f172a;
        }

        .ledger-register-grid {
            display: grid;
            gap: 1.5rem;
        }

        @media (min-width: 1200px) {
            .ledger-register-grid {
                grid-template-columns: minmax(0, 1fr) 320px;
            }
        }

        .ledger-filter-shell .ledger-card-header {
            margin-bottom: 0.5rem;
        }

        .ledger-filter-compact {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .ledger-filter-summary {
            font-size: 0.78rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 0.35rem;
        }

        .ledger-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .filter-chip {
            background: #f1f5f9;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.4);
            padding: 0.35rem 0.85rem;
            font-size: 0.82rem;
            color: #0f172a;
        }

        .filter-chip strong {
            display: block;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .ledger-filter-expanded {
            margin-top: 1rem;
        }

        .ledger-filter-expanded .ledger-filter-body {
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            padding-top: 1.25rem;
        }

        #ledgerFilterToggle .expanded-label {
            display: none;
        }

        #ledgerFilterToggle:not(.collapsed) .expanded-label {
            display: inline;
        }

        #ledgerFilterToggle:not(.collapsed) .collapsed-label {
            display: none;
        }

        .ledger-sidebar {
            display: flex;
            flex-direction: column;
        }

        .ledger-card {
            background: #fff;
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.25);
            margin-bottom: 1.25rem;
        }

        .ledger-card-header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .ledger-card-header h2 {
            font-size: 1.4rem;
            margin: 0;
        }

        .ledger-eyebrow {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #94a3b8;
            margin-bottom: 0;
        }

        .ledger-filter-pill {
            align-self: flex-start;
            background: #0f172a;
            color: #fff;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            font-size: 0.85rem;
        }

        .ledger-filter-body {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        @media (min-width: 992px) {
            .ledger-filter-body {
                flex-direction: row;
            }
        }

        .ledger-presets {
            flex: 0 0 260px;
            border-right: 1px solid rgba(226, 232, 240, 0.9);
            padding-right: 1.5rem;
        }

        @media (max-width: 991px) {
            .ledger-presets {
                border-right: none;
                border-bottom: 1px solid rgba(226, 232, 240, 0.9);
                padding-bottom: 1rem;
                padding-right: 0;
            }
        }

        .ledger-presets p {
            margin-bottom: 0.75rem;
            color: #475569;
        }

        .ledger-chip {
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.5);
            padding: 0.55rem 0.75rem;
            background: #f8fafc;
            font-weight: 600;
            color: #0f172a;
        }

        .ledger-chip small {
            display: block;
            font-weight: 400;
            color: #64748b;
        }

        .ledger-filter-form {
            flex: 1;
        }

        .ledger-filter-form label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
        }

        .ledger-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .ledger-table-wrap {
            overflow-x: auto;
        }

        .table-ledger {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
        }

        .table-ledger thead th {
            background: #0f172a;
            color: #fff;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.6rem 0.75rem;
            border: none;
            white-space: nowrap;
        }

        .table-ledger tbody td {
            padding: 0.6rem 0.75rem;
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            vertical-align: top;
        }

        .category-cell {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        .account-number-cell {
            font-weight: 600;
            color: #0f172a;
        }

        .account-cell {
            min-width: 220px;
        }

        .indent {
            display: block;
            padding-left: 0.35rem;
        }

        .account-name {
            display: block;
            font-weight: 600;
            color: #0f172a;
        }

        .account-meta,
        .ledger-account-meta {
            display: block;
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .ledger-date-cell {
            min-width: 200px;
        }

        .ledger-row-meta {
            font-size: 0.82rem;
            color: #475569;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
        }

        .ledger-repeat-value {
            color: #94a3b8;
        }

        .ledger-source-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: rgba(14, 116, 144, 0.12);
            color: #0f172a;
        }

        .amount-debit {
            color: #dc2626;
            font-weight: 600;
            text-align: right;
        }

        .amount-credit {
            color: #16a34a;
            font-weight: 600;
            text-align: right;
        }

        .amount-balance {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }

        .actions-cell {
            min-width: 150px;
        }

        .ledger-row-actions {
            display: flex;
            gap: 0.35rem;
            justify-content: flex-start;
        }

        .ledger-row-action {
            border: 1px solid rgba(148, 163, 184, 0.6);
            background: #fff;
            border-radius: 999px;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .ledger-row-action:hover {
            background: #0f172a;
            color: #fff;
        }

        .ledger-sidebar-intro {
            margin-bottom: 1rem;
        }

        .ledger-empty td {
            background: #f8fafc;
        }

        .no-transactions {
            color: #94a3b8;
            font-style: italic;
        }

        .ledger-sidebar-card {
            background: #0f172a;
            color: #fff;
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .ledger-sidebar-card+.ledger-sidebar-card {
            margin-top: 1rem;
        }

        .ledger-sidebar-card h3 {
            margin-top: 0;
            font-size: 1.1rem;
        }

        .audit-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .audit-list li {
            font-size: 0.9rem;
            line-height: 1.4;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            padding-bottom: 0.9rem;
        }

        .audit-list li:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .audit-amount-chip {
            display: inline-block;
            margin-top: 0.35rem;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            background: rgba(248, 250, 252, 0.2);
            font-size: 0.78rem;
        }

        .ledger-actions-panel p {
            color: #475569;
        }

        .ledger-actions-panel .btn {
            min-width: 160px;
        }


        .annotation {
            font-size: 0.85rem;
            color: #475569;
        }

        .ledger-sidebar-card .annotation {
            color: rgba(255, 255, 255, 0.7);
        }
    </style>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <?php if (function_exists('renderPremiumHero')): ?>
            <?php renderPremiumHero([
                'eyebrow' => 'Ledger Intelligence',
                'title' => 'Ledger Register',
                'subtitle' => 'Trace every debit and credit with running balances, filters, and exports.',
                'description' => 'Dial in the date span, account, and data source to reconcile activity quickly.',
                'theme' => 'midnight',
                'size' => 'compact',
                'media_mode' => 'none',
                'chips' => $registerHeroChips,
                'highlights' => $registerHeroHighlights,
                'actions' => $registerHeroActions,
            ]); ?>
        <?php else: ?>
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
        <?php endif; ?>

        <div class="ledger-register-shell container-fluid px-3 px-lg-4">
            <div class="ledger-stat-grid">
                <div class="ledger-stat-card">
                    <span class="ledger-stat-label">Opening Balance</span>
                    <div class="ledger-stat-value"><?= formatCurrencyValue($openingBalance); ?></div>
                    <div class="text-muted small">As of <?= htmlspecialchars($date_from); ?></div>
                </div>
                <div class="ledger-stat-card">
                    <span class="ledger-stat-label">Ending Balance</span>
                    <div class="ledger-stat-value"><?= formatCurrencyValue($endingBalance); ?></div>
                    <div class="text-muted small">After filters</div>
                </div>
                <div class="ledger-stat-card">
                    <span class="ledger-stat-label">Total Debits</span>
                    <div class="ledger-stat-value">$<?= number_format($totalDebit, 2); ?></div>
                    <div class="text-muted small">Entries in scope</div>
                </div>
                <div class="ledger-stat-card">
                    <span class="ledger-stat-label">Total Credits</span>
                    <div class="ledger-stat-value">$<?= number_format($totalCredit, 2); ?></div>
                    <div class="text-muted small">Entries in scope</div>
                </div>
            </div>

            <div class="ledger-register-grid">
                <div class="ledger-main-column">
                    <section class="ledger-card ledger-filter-shell">
                        <div class="ledger-card-header">
                            <div>
                                <p class="ledger-eyebrow mb-1">Audit Filters</p>
                                <h2 class="mb-1">Ledger Filters</h2>
                                <p class="text-muted mb-0">Review the current scope or expand for full control of every filter.</p>
                            </div>
                            <span class="ledger-filter-pill">Filters Active · <?= htmlspecialchars($filtersBadgeSummary); ?></span>
                        </div>
                        <div class="ledger-filter-compact">
                            <div class="flex-grow-1">
                                <div class="ledger-filter-summary">Snapshot · <?= htmlspecialchars($filtersBadgeSummary); ?></div>
                                <div class="ledger-filter-chips">
                                    <?php if (!empty($activeFiltersList)): ?>
                                        <?php foreach ($activeFiltersList as $filter): ?>
                                            <span class="filter-chip">
                                                <strong><?= htmlspecialchars($filter['label']); ?></strong>
                                                <?= htmlspecialchars($filter['value']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="filter-chip">
                                            <strong>Status</strong>
                                            No filters applied
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-outline-secondary btn-sm collapsed" type="button"
                                    id="ledgerFilterToggle"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#ledgerFilterPanel"
                                    aria-expanded="false"
                                    aria-controls="ledgerFilterPanel">
                                    <span class="collapsed-label"><i class="fas fa-sliders-h me-1"></i>Show Advanced Filters</span>
                                    <span class="expanded-label"><i class="fas fa-angle-up me-1"></i>Hide Advanced Filters</span>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="registerExportBtn">
                                    <i class="fas fa-file-export me-1"></i>Export CSV
                                </button>
                            </div>
                        </div>
                        <div class="collapse ledger-filter-expanded" id="ledgerFilterPanel">
                            <div class="ledger-filter-body">
                                <div class="ledger-presets">
                                    <h6 class="text-uppercase small text-muted fw-bold mb-2">Quick Presets</h6>
                                    <p class="small">Jump to a common date range.</p>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="today">
                                            <span class="fw-semibold">Today</span>
                                            <small>Current day</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="week">
                                            <span class="fw-semibold">This Week</span>
                                            <small>Monday - Sunday</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="month">
                                            <span class="fw-semibold">This Month</span>
                                            <small>Month to date</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="last_month">
                                            <span class="fw-semibold">Last Month</span>
                                            <small>Previous month</small>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm ledger-chip text-start" data-preset="ytd">
                                            <span class="fw-semibold">Year to Date</span>
                                            <small>Jan 1 - Today</small>
                                        </button>
                                    </div>
                                </div>
                                <div class="ledger-filter-form">
                                    <form method="GET" id="registerFilterForm" class="row g-3">
                                        <div class="col-md-6">
                                            <label for="account_id" class="form-label mb-1">Account</label>
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
                                            <label for="date_from" class="form-label mb-1">From</label>
                                            <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="date_to" class="form-label mb-1">To</label>
                                            <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="source" class="form-label mb-1">Source</label>
                                            <select name="source" id="source" class="form-select form-select-sm">
                                                <option value="all" <?= $source === 'all' ? 'selected' : ''; ?>>All Sources</option>
                                                <option value="transactions" <?= $source === 'transactions' ? 'selected' : ''; ?>>Transactions</option>
                                                <option value="journal" <?= $source === 'journal' ? 'selected' : ''; ?>>Journal Entries</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="search" class="form-label mb-1">Keyword</label>
                                            <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Reference, memo, category" value="<?= htmlspecialchars($search); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="min_amount" class="form-label mb-1">Min Amount</label>
                                            <input type="number" step="0.01" name="min_amount" id="min_amount" class="form-control form-control-sm" value="<?= htmlspecialchars($min_amount); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="max_amount" class="form-label mb-1">Max Amount</label>
                                            <input type="number" step="0.01" name="max_amount" id="max_amount" class="form-control form-control-sm" value="<?= htmlspecialchars($max_amount); ?>">
                                        </div>
                                        <div class="col-12">
                                            <div class="ledger-filter-actions">
                                                <a href="register.php" class="btn btn-link px-0">Reset Filters</a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search me-1"></i>Apply Filters
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="ledger-card ledger-actions-panel">
                        <h3 class="h5 mb-2">Entry Actions</h3>
                        <p class="annotation mb-4">Each action launches a modal with audit documentation so reversals and adjustments remain traceable.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($canManageAccounting): ?>
                                <a class="btn btn-primary" href="/accounting/transactions/transactions.php?start=add">
                                    <i class="fas fa-plus-circle me-2"></i>New Entry
                                </a>
                                <a class="btn btn-outline-secondary" href="/accounting/transactions/transactions.php#reverseTransactionModal" title="Opens the transactions workspace and reverse modal">
                                    <i class="fas fa-undo me-2"></i>Reverse Entry
                                </a>
                                <button type="button" class="btn btn-outline-secondary" id="ledgerAdjustQuickLaunch">
                                    <i class="fas fa-pen me-2"></i>Adjust Entry
                                </button>
                            <?php endif; ?>
                            <a class="btn btn-outline-secondary" href="/accounting/transactions/transactions.php">
                                <i class="fas fa-search me-2"></i>Transaction Details
                            </a>
                        </div>
                    </section>

                    <section class="ledger-card">
                        <div class="ledger-card-header">
                            <div>
                                <p class="ledger-eyebrow mb-1">Category ➜ Account ➜ Date</p>
                                <h2 class="mb-1">Ledger Activity</h2>
                                <p class="text-muted mb-0">Covering <?= htmlspecialchars(formatLedgerDateRange($date_from, $date_to)); ?> across <?= count($coveredAccounts); ?> account(s).</p>
                            </div>
                        </div>
                        <div class="ledger-table-wrap">
                            <table class="table-ledger" id="ledgerActivityTable">
                                <thead>
                                    <tr>
                                        <th style="width:16%">Category</th>
                                        <th style="width:10%">Acct #</th>
                                        <th style="width:18%">Account</th>
                                        <th style="width:18%">Transaction Date</th>
                                        <th style="width:12%" class="text-end">Debit</th>
                                        <th style="width:12%" class="text-end">Credit</th>
                                        <th style="width:12%" class="text-end">Balance</th>
                                        <th style="width:12%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($groupedLedger)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">No ledger entries found for the selected filters.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($groupedLedger as $categoryBlock): ?>
                                            <?php
                                            $categoryRowspan = max(1, (int)($categoryBlock['rowspan'] ?? 1));
                                            $categoryPrinted = false;
                                            ?>
                                            <?php foreach ($categoryBlock['accounts'] as $accountBlock): ?>
                                                <?php
                                                $rows = $accountBlock['rows'] ?? [];
                                                if (!$rows) {
                                                    $rows = [['__empty' => true]];
                                                }
                                                $accountRowspan = max(1, count($rows));
                                                $accountPrinted = false;
                                                ?>
                                                <?php foreach ($rows as $entry): ?>
                                                    <?php
                                                    $isPlaceholder = isset($entry['__empty']);
                                                    $debitAmount = $isPlaceholder ? 0.0 : (float)($entry['debit_amount'] ?? 0);
                                                    $creditAmount = $isPlaceholder ? 0.0 : (float)($entry['credit_amount'] ?? 0);
                                                    $balanceAmount = $isPlaceholder ? 0.0 : (float)($entry['running_balance'] ?? 0);
                                                    $memoDetails = $isPlaceholder ? 'Awaiting new activity' : formatLedgerMemoDetails($entry);
                                                    $detailUrl = !$isPlaceholder ? getEntryDetailUrl($entry) : null;
                                                    $canAdjustThis = !$isPlaceholder && $canManageAccounting && !empty($entry['account_id']);
                                                    $canReverseThis = !$isPlaceholder && $canManageAccounting && $entry['entry_source'] === 'transaction' && !empty($entry['raw']['id']);
                                                    ?>
                                                    <tr<?= $isPlaceholder ? ' class="ledger-empty"' : ''; ?>>
                                                        <?php if (!$categoryPrinted): ?>
                                                            <td class="category-cell" rowspan="<?= $categoryRowspan ?>">
                                                                <?= htmlspecialchars($categoryBlock['name']); ?>
                                                            </td>
                                                            <?php $categoryPrinted = true; ?>
                                                        <?php endif; ?>
                                                        <?php if (!$accountPrinted): ?>
                                                            <td class="account-number-cell" rowspan="<?= $accountRowspan ?>">
                                                                <?= htmlspecialchars($accountBlock['account_number'] ?? '—'); ?>
                                                            </td>
                                                            <td class="account-cell" rowspan="<?= $accountRowspan ?>">
                                                                <span class="indent account-name">
                                                                    <?= htmlspecialchars($accountBlock['account_name'] ?? 'Unassigned'); ?>
                                                                </span>
                                                                <?php if (!empty($accountBlock['account_meta'])): ?>
                                                                    <span class="indent account-meta">
                                                                        <?= htmlspecialchars($accountBlock['account_meta']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <?php $accountPrinted = true; ?>
                                                        <?php endif; ?>
                                                        <?php if ($isPlaceholder): ?>
                                                            <td class="no-transactions text-center">No transactions within this window</td>
                                                            <td class="amount-debit"><?= formatLedgerAmount(0); ?></td>
                                                            <td class="amount-credit"><?= formatLedgerAmount(0); ?></td>
                                                            <td class="amount-balance">—</td>
                                                            <td class="actions-cell">
                                                                <div class="ledger-row-actions">
                                                                    <?php if ($canManageAccounting): ?>
                                                                        <a class="ledger-row-action" href="/accounting/transactions/transactions.php?start=add" title="Post new entry">
                                                                            <i class="fas fa-plus"></i>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="text-muted small">—</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        <?php else: ?>
                                                            <td class="ledger-date-cell" data-order="<?= htmlspecialchars($entry['entry_date']); ?>">
                                                                <div class="fw-semibold"><?= htmlspecialchars(formatLedgerDate($entry['entry_date'])); ?></div>
                                                                <div class="ledger-row-meta">
                                                                    <span><?= htmlspecialchars($memoDetails); ?></span>
                                                                    <span class="ledger-source-chip"><?= ucfirst($entry['entry_source']); ?></span>
                                                                </div>
                                                            </td>
                                                            <td class="amount-debit" data-order="<?= number_format($debitAmount, 2, '.', ''); ?>">
                                                                <?= formatLedgerAmount($debitAmount); ?>
                                                            </td>
                                                            <td class="amount-credit" data-order="<?= number_format($creditAmount, 2, '.', ''); ?>">
                                                                <?= formatLedgerAmount($creditAmount); ?>
                                                            </td>
                                                            <td class="amount-balance" data-order="<?= number_format($balanceAmount, 2, '.', ''); ?>">
                                                                <?= formatLedgerAmount($balanceAmount); ?>
                                                            </td>
                                                            <td class="actions-cell">
                                                                <div class="ledger-row-actions">
                                                                    <?php if ($detailUrl): ?>
                                                                        <a href="<?= htmlspecialchars($detailUrl); ?>" class="ledger-row-action" title="View source details">
                                                                            <i class="fas fa-search"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <?php if ($canReverseThis): ?>
                                                                        <a href="/accounting/transactions/transactions.php?transaction_id=<?= intval($entry['raw']['id']); ?>#reverseTransactionModal"
                                                                            class="ledger-row-action" title="Reverse this transaction">
                                                                            <i class="fas fa-undo"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <?php if ($canAdjustThis): ?>
                                                                        <button type="button" class="ledger-row-action ledger-adjust-btn" title="Create adjusting entry"
                                                                            data-account-id="<?= intval($entry['account_id']); ?>"
                                                                            data-account-name="<?= htmlspecialchars($entry['account_name'] ?? ''); ?>"
                                                                            data-account-type="<?= htmlspecialchars($entry['account_type'] ?? ''); ?>"
                                                                            data-entry-source="<?= htmlspecialchars($entry['entry_source']); ?>"
                                                                            data-entry-reference="<?= htmlspecialchars($entry['reference'] ?? ''); ?>"
                                                                            data-entry-memo="<?= htmlspecialchars($entry['memo'] ?? ''); ?>"
                                                                            data-entry-date="<?= htmlspecialchars($entry['entry_date']); ?>"
                                                                            data-debit="<?= htmlspecialchars((string)$entry['debit_amount']); ?>"
                                                                            data-credit="<?= htmlspecialchars((string)$entry['credit_amount']); ?>">
                                                                            <i class="fas fa-pen"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <aside class="ledger-sidebar">
                    <div class="ledger-sidebar-intro">
                        <h3>Audit &amp; Filters</h3>
                        <p class="annotation">Sidebar lists the active filters plus manual changes with who/why metadata.</p>
                    </div>
                    <div class="ledger-sidebar-card">
                        <h3>Active Filters</h3>
                        <p class="annotation">Snapshot of the context applied to this register.</p>
                        <?php if (!empty($activeFiltersList)): ?>
                            <ul class="audit-list">
                                <?php foreach ($activeFiltersList as $filter): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($filter['label']); ?></strong><br>
                                        <?= htmlspecialchars($filter['value']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mb-0">No filters applied.</p>
                        <?php endif; ?>
                    </div>
                    <div class="ledger-sidebar-card">
                        <h3>Recent Manual Changes</h3>
                        <p class="annotation">Latest ledger activity requiring review.</p>
                        <?php if (!empty($recentManualChanges)): ?>
                            <ul class="audit-list">
                                <?php foreach ($recentManualChanges as $change): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($change['stamp']); ?></strong><br>
                                        <?= htmlspecialchars($change['account']); ?><br>
                                        <em><?= htmlspecialchars($change['memo']); ?></em><br>
                                        <span class="audit-amount-chip"><?= htmlspecialchars($change['amount']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mb-0">No manual changes during this period.</p>
                        <?php endif; ?>
                    </div>
                </aside>
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
                    if (!adjustModalEl || typeof bootstrap === 'undefined') {
                        return;
                    }

                    const adjustButtons = document.querySelectorAll('.ledger-adjust-btn');
                    const quickLaunchBtn = document.getElementById('ledgerAdjustQuickLaunch');
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

                    const resetAdjustForm = function(message) {
                        if (adjustSummary) {
                            adjustSummary.textContent = message || 'Select a ledger row to load its details.';
                        }
                        if (adjustAccountInput) adjustAccountInput.value = '';
                        if (adjustSourceInput) adjustSourceInput.value = '';
                        if (adjustReferenceInput) adjustReferenceInput.value = '';
                        if (adjustMemoHidden) adjustMemoHidden.value = '';
                        if (adjustDateInput) adjustDateInput.value = new Date().toISOString().slice(0, 10);
                        if (adjustDebitInput) adjustDebitInput.value = '';
                        if (adjustCreditInput) adjustCreditInput.value = '';
                        if (adjustOffsetSelect) adjustOffsetSelect.selectedIndex = 0;
                        if (adjustMemoInput) adjustMemoInput.value = '';
                    };

                    const populateFromButton = function(btn) {
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
                    };

                    adjustButtons.forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            populateFromButton(btn);
                            adjustModal.show();
                        });
                    });

                    if (quickLaunchBtn) {
                        quickLaunchBtn.addEventListener('click', function() {
                            resetAdjustForm('Start a blank adjustment');
                            adjustModal.show();
                        });
                    }
                })();
        <?php endif; ?>
    </script>
</body>

</html>
<?php
function adjustLedgerBalance(float $currentBalance, float $debit, float $credit, string $accountType): float
{

    function formatLedgerMemoDetails(array $entry): string
    {
        $memoParts = [];
        if (!empty($entry['memo'])) {
            $memoParts[] = trim((string)$entry['memo']);
        }
        if (!empty($entry['vendor_name'])) {
            $memoParts[] = 'Vendor: ' . trim((string)$entry['vendor_name']);
        }
        if (!empty($entry['reference'])) {
            $memoParts[] = 'Ref ' . trim((string)$entry['reference']);
        }
        if (empty($memoParts)) {
            return 'No memo provided';
        }
        return implode(' · ', $memoParts);
    }
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

function groupLedgerEntries(array $entries, array $accountsMap): array
{
    $grouped = [];
    foreach ($entries as $entry) {
        $categoryName = $entry['category_name'] ?: 'Uncategorized';
        $accountId = (int)($entry['account_id'] ?? 0);
        $accountDetails = $accountsMap[$accountId] ?? null;

        if (!isset($grouped[$categoryName])) {
            $grouped[$categoryName] = [
                'name' => $categoryName,
                'accounts' => [],
                'rowspan' => 0,
            ];
        }

        if (!isset($grouped[$categoryName]['accounts'][$accountId])) {
            $grouped[$categoryName]['accounts'][$accountId] = [
                'account_id' => $accountId,
                'account_number' => $entry['account_number'] ?? ($accountDetails['account_number'] ?? '—'),
                'account_name' => $entry['account_name'] ?? ($accountDetails['name'] ?? 'Unassigned'),
                'account_meta' => deriveLedgerAccountMeta($accountDetails, $entry),
                'rows' => [],
            ];
        }

        $grouped[$categoryName]['accounts'][$accountId]['rows'][] = $entry;
    }

    foreach ($grouped as &$categoryBlock) {
        $categoryBlock['rowspan'] = 0;
        foreach ($categoryBlock['accounts'] as &$accountBlock) {
            $accountBlock['rowspan'] = max(1, count($accountBlock['rows']));
            $categoryBlock['rowspan'] += $accountBlock['rowspan'];
        }
        unset($accountBlock);
    }
    unset($categoryBlock);

    return array_values($grouped);
}

function deriveLedgerAccountMeta(?array $accountDetails, array $entry): string
{
    $parts = [];
    if (!empty($accountDetails['description'])) {
        $parts[] = trim((string)$accountDetails['description']);
    }
    if (!empty($entry['entry_source'])) {
        $parts[] = 'Source: ' . ucfirst($entry['entry_source']);
    }
    if (!$parts) {
        $parts[] = ucfirst(strtolower($entry['account_type'] ?? 'Ledger')) . ' account';
    }
    return implode(' · ', $parts);
}

function formatLedgerAmount($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return '$' . number_format((float)$value, 2);
}

function formatLedgerDate(?string $value): string
{
    if (empty($value)) {
        return '—';
    }
    return date('M j, Y', strtotime($value));
}

function formatLedgerDateRange(string $start, string $end): string
{
    return formatLedgerDate($start) . ' – ' . formatLedgerDate($end);
}

function buildLedgerActiveFilters(?array $selectedAccount, string $dateFrom, string $dateTo, string $source, string $search, $minAmount, $maxAmount): array
{
    $filters = [];
    $filters[] = [
        'label' => 'Account',
        'value' => $selectedAccount
            ? trim(($selectedAccount['name'] ?? 'Selected') . ' · ' . ($selectedAccount['account_number'] ?? '#'))
            : 'All accounts',
    ];
    $filters[] = [
        'label' => 'Date Range',
        'value' => formatLedgerDateRange($dateFrom, $dateTo),
    ];
    $filters[] = [
        'label' => 'Source',
        'value' => $source === 'all' ? 'All entries' : ucfirst($source),
    ];
    if (!empty($search)) {
        $filters[] = [
            'label' => 'Keyword',
            'value' => $search,
        ];
    }
    if ($minAmount !== '' && $minAmount !== null) {
        $filters[] = [
            'label' => 'Min Amount',
            'value' => '$' . number_format((float)$minAmount, 2),
        ];
    }
    if ($maxAmount !== '' && $maxAmount !== null) {
        $filters[] = [
            'label' => 'Max Amount',
            'value' => '$' . number_format((float)$maxAmount, 2),
        ];
    }
    return $filters;
}

function summarizeLedgerFilters(?array $selectedAccount, string $dateFrom, string $dateTo, string $source, string $search, $minAmount, $maxAmount): string
{
    $parts = [];
    $parts[] = $selectedAccount ? ($selectedAccount['name'] ?? 'Selected account') : 'All accounts';
    $parts[] = formatLedgerDateRange($dateFrom, $dateTo);
    if ($source !== 'all') {
        $parts[] = 'Source: ' . ucfirst($source);
    }
    if (!empty($search)) {
        $parts[] = 'Search: ' . $search;
    }
    if ($minAmount !== '' && $minAmount !== null) {
        $parts[] = 'Min $' . number_format((float)$minAmount, 2);
    }
    if ($maxAmount !== '' && $maxAmount !== null) {
        $parts[] = 'Max $' . number_format((float)$maxAmount, 2);
    }
    return implode(' · ', $parts);
}

function summarizeRecentLedgerChanges(array $entries): array
{
    if (empty($entries)) {
        return [];
    }

    $sorted = $entries;
    usort($sorted, static function (array $a, array $b) {
        if ($a['entry_date'] === $b['entry_date']) {
            return strcmp($b['sort_key'], $a['sort_key']);
        }
        return strcmp($b['entry_date'], $a['entry_date']);
    });

    $changes = [];
    foreach ($sorted as $entry) {
        $stamp = formatLedgerDate($entry['entry_date']) . ' · ' . ($entry['entry_source'] === 'journal' ? 'Journal' : 'Transaction');
        $memo = formatLedgerMemoDetails($entry);
        if (strlen($memo) > 90) {
            $memo = substr($memo, 0, 87) . '…';
        }
        if (strlen($stamp) > 64) {
            $stamp = substr($stamp, 0, 61) . '…';
        }
        $amountLabel = $entry['debit_amount'] > 0
            ? 'Debit ' . formatLedgerAmount($entry['debit_amount'])
            : ($entry['credit_amount'] > 0 ? 'Credit ' . formatLedgerAmount($entry['credit_amount']) : 'No amount');

        $changes[] = [
            'stamp' => $stamp,
            'account' => $entry['account_name'] ?? 'Unassigned account',
            'memo' => $memo,
            'amount' => $amountLabel,
        ];

        if (count($changes) >= 4) {
            break;
        }
    }

    return $changes;
}
?>