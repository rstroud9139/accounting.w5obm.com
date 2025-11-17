<?php

/**
 * Sources and Uses of Funds Report - W5OBM Accounting System
 * File: /accounting/reports/sources_uses.php
 * Purpose: Summarize sources (inflows) and uses (outflows) of funds for a period
 * SECURITY: Requires authentication and accounting permissions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../../include/report_header.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_view') && !hasPermission($user_id, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to view accounting reports.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Sources and Uses of Funds - W5OBM Accounting';

// Parameters
$start_date = sanitizeInput($_GET['start_date'] ?? date('Y-m-01'), 'string');
$end_date = sanitizeInput($_GET['end_date'] ?? date('Y-m-t'), 'string');
$generate_report = isset($_GET['generate']) && $_GET['generate'] === '1';

$sources = [];
$uses = [];
$totals = [
    'sources' => 0.0,
    'uses' => 0.0,
    'net' => 0.0,
];

if ($generate_report) {
    try {
        if (!strtotime($start_date) || !strtotime($end_date)) {
            throw new Exception('Invalid date range provided.');
        }
        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception('Start date cannot be after end date.');
        }

        // Sources: Income by category
        $stmt = $conn->prepare("SELECT c.name, SUM(t.amount) AS total, COUNT(t.id) AS count
                                 FROM acc_transactions t
                                 JOIN acc_transaction_categories c ON c.id = t.category_id
                                 WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?
                                 GROUP BY c.id, c.name
                                 ORDER BY total DESC");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $sources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Uses: Expenses by category
        $stmt = $conn->prepare("SELECT c.name, SUM(t.amount) AS total, COUNT(t.id) AS count
                                 FROM acc_transactions t
                                 JOIN acc_transaction_categories c ON c.id = t.category_id
                                 WHERE t.type = 'Expense' AND t.transaction_date BETWEEN ? AND ?
                                 GROUP BY c.id, c.name
                                 ORDER BY total DESC");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $uses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $totals['sources'] = 0.0;
        foreach ($sources as $r) {
            $totals['sources'] += (float)$r['total'];
        }
        $totals['uses'] = 0.0;
        foreach ($uses as $r) {
            $totals['uses'] += (float)$r['total'];
        }
        $totals['net'] = $totals['sources'] - $totals['uses'];
    } catch (Exception $e) {
        setToastMessage('danger', 'Report Error', $e->getMessage(), 'club-logo');
        logError('Error generating Sources & Uses report: ' . $e->getMessage(), 'accounting');
    }
}

// Export handling via shared exporter
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    $section = $_GET['section'] ?? 'summary';
    $rows = [];
    $meta_title = '';

    if ($section === 'sources') {
        foreach ($sources as $row) {
            $rows[] = [
                'Source' => (string)$row['name'],
                'Amount' => number_format((float)$row['total'], 2, '.', ''),
                'Transactions' => (string)($row['count'] ?? 0)
            ];
        }
        $meta_title = 'Sources of Funds';
    } elseif ($section === 'uses') {
        foreach ($uses as $row) {
            $rows[] = [
                'Use' => (string)$row['name'],
                'Amount' => number_format((float)$row['total'], 2, '.', ''),
                'Transactions' => (string)($row['count'] ?? 0)
            ];
        }
        $meta_title = 'Uses of Funds';
    } else {
        $rows[] = [
            'Total Sources' => number_format((float)$totals['sources'], 2, '.', ''),
            'Total Uses' => number_format((float)$totals['uses'], 2, '.', ''),
            'Net Change' => number_format((float)$totals['net'], 2, '.', ''),
        ];
        $meta_title = 'Sources and Uses - Summary';
    }

    $report_meta = [
        ['Report', $meta_title],
        ['Generated', date('Y-m-d H:i:s')],
        ['Period', date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date))]
    ];

    require_once __DIR__ . '/../lib/export_bridge.php';
    accounting_export('sources_uses_' . $section, $rows, $report_meta);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>
    <?php renderReportHeader('Sources and Uses of Funds', 'Inflows and outflows for selected period', [
        'Start' => date('M j, Y', strtotime($start_date)),
        'End' => date('M j, Y', strtotime($end_date))
    ]); ?>

    <div class="page-container">
        <!-- Parameters -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-filter me-2 text-primary"></i>Report Parameters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="generate" value="1">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control form-control-lg" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control form-control-lg" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-lg me-2"><i class="fas fa-chart-pie me-1"></i>Generate Report</button>
                        <?php if ($generate_report): ?>
                            <button type="button" class="btn btn-success btn-lg me-2" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
                            <div class="d-inline-flex align-items-center no-print" role="group" aria-label="Export buttons">
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-secondary" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=csv&section=summary"><i class="fas fa-file-csv me-1"></i>Summary CSV</a>
                                    <a class="btn btn-outline-primary" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=excel&section=summary"><i class="fas fa-file-excel me-1"></i>Summary Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm me-2">
                                    <a class="btn btn-outline-success" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=csv&section=sources"><i class="fas fa-file-csv me-1"></i>Sources CSV</a>
                                    <a class="btn btn-outline-success" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=excel&section=sources"><i class="fas fa-file-excel me-1"></i>Sources Excel</a>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-danger" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=csv&section=uses"><i class="fas fa-file-csv me-1"></i>Uses CSV</a>
                                    <a class="btn btn-outline-danger" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=excel&section=uses"><i class="fas fa-file-excel me-1"></i>Uses Excel</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($generate_report): ?>
            <!-- Summary Cards -->
            <div class="card shadow mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Total Sources</h6>
                                    <div class="h3 mb-0">$<?= number_format($totals['sources'], 2) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Total Uses</h6>
                                    <div class="h3 mb-0">$<?= number_format($totals['uses'], 2) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card <?= $totals['net'] >= 0 ? 'bg-primary' : 'bg-warning' ?> text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Net Change</h6>
                                    <div class="h3 mb-0">$<?= number_format($totals['net'], 2) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sources -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sources of Funds</h5>
                    <div class="btn-group btn-group-sm no-print">
                        <a class="btn btn-outline-success" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=csv&section=sources"><i class="fas fa-file-csv me-1"></i>CSV</a>
                        <a class="btn btn-outline-primary" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=excel&section=sources"><i class="fas fa-file-excel me-1"></i>Excel</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Amount</th>
                                    <th>Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sources as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="text-success fw-bold">$<?= number_format($row['total'], 2) ?></td>
                                        <td><?= (int)$row['count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-success">
                                <tr>
                                    <th>Total Sources</th>
                                    <th>$<?= number_format($totals['sources'], 2) ?></th>
                                    <th><?= array_sum(array_column($sources, 'count')) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Uses -->
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Uses of Funds</h5>
                    <div class="btn-group btn-group-sm no-print">
                        <a class="btn btn-outline-success" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=csv&section=uses"><i class="fas fa-file-csv me-1"></i>CSV</a>
                        <a class="btn btn-outline-primary" href="?generate=1&start_date=<?= urlencode((string)$start_date) ?>&end_date=<?= urlencode((string)$end_date) ?>&export=excel&section=uses"><i class="fas fa-file-excel me-1"></i>Excel</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Use</th>
                                    <th>Amount</th>
                                    <th>Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($uses as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="text-danger fw-bold">$<?= number_format($row['total'], 2) ?></td>
                                        <td><?= (int)$row['count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-danger">
                                <tr>
                                    <th>Total Uses</th>
                                    <th>$<?= number_format($totals['uses'], 2) ?></th>
                                    <th><?= array_sum(array_column($uses, 'count')) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>
</body>

</html>