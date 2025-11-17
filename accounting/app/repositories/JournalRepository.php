<?php

class JournalRepository
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    private function tablesReady()
    {
        $a = $this->conn->query("SHOW TABLES LIKE 'acc_journals'");
        $b = $this->conn->query("SHOW TABLES LIKE 'acc_journal_lines'");
        return $a && $a->num_rows > 0 && $b && $b->num_rows > 0;
    }

    public function getRegisterLines($accountId, $startDate = null, $endDate = null)
    {
        if (!$this->tablesReady()) return [];
        $where = 'WHERE jl.account_id = ?';
        $types = 'i';
        $params = [$accountId];
        if ($startDate && $endDate) {
            $where .= ' AND j.journal_date BETWEEN ? AND ?';
            $types .= 'ss';
            $params[] = $startDate;
            $params[] = $endDate;
        }
        $sql = "SELECT jl.id, j.journal_date, COALESCE(jl.description, j.memo) AS description, jl.debit, jl.credit
                FROM acc_journal_lines jl
                JOIN acc_journals j ON j.id = jl.journal_id
                $where
                ORDER BY j.journal_date, jl.id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        // Compute running balance in PHP (debit - credit accumulative)
        $balance = 0.0;
        foreach ($rows as &$r) {
            $amt = (float)$r['debit'] - (float)$r['credit'];
            $r['amount'] = $amt;
            $balance += $amt;
            $r['running'] = $balance;
        }
        return $rows;
    }
}
