<?php

class TransactionRepository
{
    private mysqli $conn;

    public function __construct(?mysqli $conn = null)
    {
        $this->conn = $conn ?? accounting_db_connection();
    }

    public function getRecent(int $limit = 200): array
    {
        return $this->findAll([], $limit);
    }

    public function getByFilters(array $filters = array()): array
    {
        return $this->findAll($filters);
    }

    public function findAll(array $filters = array(), ?int $limit = null, int $offset = 0): array
    {
        $where = [];
        $types = '';
        $params = [];

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
        if (!empty($filters['account_id'])) {
            $where[] = 't.account_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['account_id'];
        }
        if (!empty($filters['vendor_id'])) {
            $where[] = 't.vendor_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['vendor_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(t.description LIKE ? OR t.notes LIKE ? OR c.name LIKE ?)';
            $types .= 'sss';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT t.*, c.name AS category_name, a.name AS account_name, v.name AS vendor_name
                FROM acc_transactions t
                LEFT JOIN acc_transaction_categories c ON c.id = t.category_id
                LEFT JOIN acc_ledger_accounts a ON a.id = t.account_id
                LEFT JOIN acc_vendors v ON v.id = t.vendor_id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $types .= 'i';
            $params[] = max(1, (int)$limit);
            if ($offset > 0) {
                $sql .= ' OFFSET ?';
                $types .= 'i';
                $params[] = max(0, (int)$offset);
            }
        }

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT t.*, c.name AS category_name, a.name AS account_name, v.name AS vendor_name
            FROM acc_transactions t
            LEFT JOIN acc_transaction_categories c ON c.id = t.category_id
            LEFT JOIN acc_ledger_accounts a ON a.id = t.account_id
            LEFT JOIN acc_vendors v ON v.id = t.vendor_id
            WHERE t.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: null;
    }

    public function create(array $data)
    {
        $stmt = $this->conn->prepare("INSERT INTO acc_transactions
            (transaction_date, type, category_id, amount, description, notes, reference_number, account_id, vendor_id, transaction_type, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $date = $data['transaction_date'] ?? date('Y-m-d');
        $type = $data['type'] ?? 'Expense';
        $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        $description = $data['description'] ?? '';
        $notes = $data['notes'] ?? '';
        $ref = $data['reference_number'] ?? '';
        $accountId = isset($data['cash_account_id']) ? (int)$data['cash_account_id'] : (isset($data['account_id']) ? (int)$data['account_id'] : null);
        if ($accountId !== null && $accountId <= 0) $accountId = null;
        $vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        if ($vendorId !== null && $vendorId <= 0) $vendorId = null;
        $transactionType = $data['transaction_type'] ?? ($type === 'Income' ? 'Deposit' : ($type === 'Transfer' ? 'Transfer' : 'Payment'));
        $createdBy = $data['created_by'] ?? (function_exists('getCurrentUserId') ? getCurrentUserId() : null);
        $stmt->bind_param('ssidsssiisi', $date, $type, $category_id, $amount, $description, $notes, $ref, $accountId, $vendorId, $transactionType, $createdBy);
        return $stmt->execute();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->conn->prepare("UPDATE acc_transactions
            SET transaction_date = ?, type = ?, category_id = ?, amount = ?, description = ?, notes = ?, reference_number = ?,
                account_id = ?, vendor_id = ?, transaction_type = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?");
        $date = $data['transaction_date'] ?? date('Y-m-d');
        $type = $data['type'] ?? 'Expense';
        $category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        $description = $data['description'] ?? '';
        $notes = $data['notes'] ?? '';
        $ref = $data['reference_number'] ?? '';
        $accountId = isset($data['cash_account_id']) ? (int)$data['cash_account_id'] : (isset($data['account_id']) ? (int)$data['account_id'] : null);
        if ($accountId !== null && $accountId <= 0) $accountId = null;
        $vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        if ($vendorId !== null && $vendorId <= 0) $vendorId = null;
        $transactionType = $data['transaction_type'] ?? ($type === 'Income' ? 'Deposit' : ($type === 'Transfer' ? 'Transfer' : 'Payment'));
        $updatedBy = $data['updated_by'] ?? (function_exists('getCurrentUserId') ? getCurrentUserId() : null);
        $stmt->bind_param('ssidsssiisii', $date, $type, $category_id, $amount, $description, $notes, $ref, $accountId, $vendorId, $transactionType, $updatedBy, $id);
        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM acc_transactions WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    public function createWithPosting(array $data, array $splits = array())
    {
        $ok = $this->create($data);
        if (!$ok) return false;
        $id = $this->conn->insert_id;
        require_once __DIR__ . '/../services/PostingService.php';
        $posting = new PostingService($this->conn);
        $posting->postTransaction($id, $data, $splits);
        return $id;
    }

    public function updateWithPosting(int $id, array $data, array $splits = array()): bool
    {
        $ok = $this->update($id, $data);
        if (!$ok) return false;
        require_once __DIR__ . '/../services/PostingService.php';
        $posting = new PostingService($this->conn);
        $posting->postTransaction($id, $data, $splits);
        return true;
    }
}
