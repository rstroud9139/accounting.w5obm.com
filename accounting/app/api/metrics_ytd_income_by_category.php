<?php
require_once __DIR__ . '/../bootstrap.php';
requirePermission('accounting_view');
header('Content-Type: application/json');

try {
    global $conn;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

    $sql = "SELECT c.name AS category, SUM(t.amount) AS total
            FROM acc_transactions t
            JOIN acc_transaction_categories c ON t.category_id = c.id
            WHERE t.type = 'Income' AND YEAR(t.transaction_date) = ?
            GROUP BY c.name
            ORDER BY total DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $rs = $stmt->get_result();

    $labels = array();
    $values = array();
    while ($row = $rs->fetch_assoc()) {
        $labels[] = $row['category'];
        $values[] = round((float)$row['total'], 2);
    }

    echo json_encode(array(
        'labels' => $labels,
        'values' => $values,
        'year' => $year
    ));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('error' => 'metrics_error', 'message' => $e->getMessage()));
}
