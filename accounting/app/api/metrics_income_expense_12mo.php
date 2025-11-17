<?php
require_once __DIR__ . '/../bootstrap.php';
requirePermission('accounting_view');
header('Content-Type: application/json');

try {
    global $conn;
    $now = new DateTime('first day of this month');
    $start = (clone $now)->modify('-11 months');
    $startStr = $start->format('Y-m-01');
    $endStr = (clone $now)->modify('+1 month -1 day')->format('Y-m-d');

    $sql = "SELECT YEAR(transaction_date) y, MONTH(transaction_date) m,
                   SUM(CASE WHEN type='Income' THEN amount ELSE 0 END) inc,
                   SUM(CASE WHEN type='Expense' THEN amount ELSE 0 END) exp
            FROM acc_transactions
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY YEAR(transaction_date), MONTH(transaction_date)
            ORDER BY y, m";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startStr, $endStr);
    $stmt->execute();
    $rs = $stmt->get_result();

    // Build 12-month series
    $map = [];
    while ($row = $rs->fetch_assoc()) {
        $key = sprintf('%04d-%02d', (int)$row['y'], (int)$row['m']);
        $map[$key] = ['inc' => (float)($row['inc'] ?? 0), 'exp' => (float)($row['exp'] ?? 0)];
    }

    $labels = array();
    $income = array();
    $expense = array();
    $cursor = clone $start;
    for ($i = 0; $i < 12; $i++) {
        $key = $cursor->format('Y-m');
        $labels[] = $cursor->format('M');
        $income[] = isset($map[$key]) ? round($map[$key]['inc'], 2) : 0.0;
        $expense[] = isset($map[$key]) ? round($map[$key]['exp'], 2) : 0.0;
        $cursor->modify('+1 month');
    }

    echo json_encode(array(
        'labels' => $labels,
        'income' => $income,
        'expense' => $expense,
        'range' => array('start' => $startStr, 'end' => $endStr)
    ));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'metrics_error', 'message' => $e->getMessage()]);
}
