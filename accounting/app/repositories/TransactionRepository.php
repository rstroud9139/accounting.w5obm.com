<?php

class TransactionRepository
{
    private mysqli $conn;

    public function __construct(?mysqli $conn = null)
    {
        $this->conn = $conn ?? accounting_db_connection();
    }

    public function getRecent($limit = 200)
    {
        $limit = max(1, min(1000, (int)$limit));
        $sql = "SELECT t.id, t.transaction_date, t.type, t.amount, t.description,
                       c.name AS category_name
                FROM acc_transactions t
                LEFT JOIN acc_transaction_categories c ON c.id = t.category_id
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT $limit";
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getByFilters($filters = array())
    {
        $where = array();
        $types = '';
        $params = array();

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 't.transaction_date BETWEEN ? AND ?';
            $types .= 'ss';
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $where[] = 't.type = ?';
            $types .= 's';
            $params[] = $filters['type'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 't.category_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = 't.description LIKE ?';
            $types .= 's';
            $params[] = '%' . $filters['q'] . '%';
        }

        $sql = "SELECT t.id, t.transaction_date, t.type, t.amount, t.description,
                   c.name AS category_name
            FROM acc_transactions t
            LEFT JOIN acc_transaction_categories c ON c.id = t.category_id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function create($data)
    {
        // Minimal create (single-row, non-split). Journal/splits will replace this.
        $stmt = $this->conn->prepare("INSERT INTO acc_transactions
            (transaction_date, type, category_id, amount, description, notes, reference_number, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $date = $data['transaction_date'] ?? date('Y-m-d');
        $type = $data['type'] ?? 'Expense';
        $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        $description = $data['description'] ?? '';
        $notes = $data['notes'] ?? '';
        $ref = $data['reference_number'] ?? '';
        $stmt->bind_param('ssidsss', $date, $type, $category_id, $amount, $description, $notes, $ref);
        return $stmt->execute();
    }

    public function createWithPosting($data, $splits = array())
    {
        $ok = $this->create($data);
        if (!$ok) return false;
        $id = $this->conn->insert_id;
        // Attempt to post journals/splits if service available and tables exist
        require_once __DIR__ . '/../services/PostingService.php';
        $posting = new PostingService($this->conn);
        $posting->postTransaction($id, $data, $splits);
        return $id;
    }
}
