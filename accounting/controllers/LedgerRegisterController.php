<?php

class LedgerRegisterController
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Fetch ledger entries from transactions and journal lines.
     */
    public function fetchEntries(array $filters): array
    {
        $entries = [];
        $source = $filters['source'] ?? 'all';

        if ($source !== 'journal') {
            $entries = array_merge($entries, $this->fetchTransactionEntries($filters));
        }
        if ($source !== 'transactions') {
            $entries = array_merge($entries, $this->fetchJournalEntries($filters));
        }

        usort($entries, static function (array $a, array $b) {
            if ($a['entry_date'] === $b['entry_date']) {
                return $a['sort_key'] <=> $b['sort_key'];
            }
            return strcmp($a['entry_date'], $b['entry_date']);
        });

        return $entries;
    }

    /**
     * Fetch entries sourced from acc_transactions.
     */
    private function fetchTransactionEntries(array $filters): array
    {
        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $conditions[] = 't.transaction_date BETWEEN ? AND ?';
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
            $types .= 'ss';
        }

        if (!empty($filters['account_id'])) {
            $conditions[] = 't.account_id = ?';
            $params[] = (int)$filters['account_id'];
            $types .= 'i';
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(t.description LIKE ? OR t.reference_number LIKE ? OR cat.name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $conditions[] = 't.amount >= ?';
            $params[] = (float)$filters['min_amount'];
            $types .= 'd';
        }

        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $conditions[] = 't.amount <= ?';
            $params[] = (float)$filters['max_amount'];
            $types .= 'd';
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $query = "SELECT t.id,
                         t.transaction_date AS entry_date,
                         t.description,
                         t.reference_number,
                         t.transaction_type,
                         t.amount,
                         t.type AS classification,
                         t.account_id,
                         acc.name AS account_name,
                         acc.account_number,
                         acc.account_type,
                         cat.name AS category_name,
                         v.name AS vendor_name
                  FROM acc_transactions t
                  LEFT JOIN acc_ledger_accounts acc ON acc.id = t.account_id
                  LEFT JOIN acc_transaction_categories cat ON cat.id = t.category_id
                  LEFT JOIN acc_vendors v ON v.id = t.vendor_id
                  $where
                  ORDER BY t.transaction_date ASC, t.id ASC";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $entries = [];

        while ($row = $result->fetch_assoc()) {
            $debit = 0.0;
            $credit = 0.0;
            $classification = strtolower($row['classification'] ?? '');

            if (in_array($classification, ['expense', 'asset'], true)) {
                $debit = (float)$row['amount'];
            } else {
                $credit = (float)$row['amount'];
            }

            $entries[] = [
                'sort_key' => 'transaction-' . $row['id'],
                'entry_source' => 'transaction',
                'entry_date' => $row['entry_date'],
                'reference' => $row['reference_number'],
                'memo' => $row['description'],
                'transaction_type' => $row['transaction_type'],
                'account_id' => (int)$row['account_id'],
                'account_name' => $row['account_name'],
                'account_number' => $row['account_number'],
                'account_type' => $row['account_type'] ?? 'Asset',
                'category_name' => $row['category_name'],
                'vendor_name' => $row['vendor_name'],
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'raw' => $row,
            ];
        }

        $stmt->close();
        return $entries;
    }

    /**
     * Fetch entries sourced from acc_journal_lines.
     */
    private function fetchJournalEntries(array $filters): array
    {
        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $conditions[] = 'j.journal_date BETWEEN ? AND ?';
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
            $types .= 'ss';
        }

        if (!empty($filters['account_id'])) {
            $conditions[] = 'jl.account_id = ?';
            $params[] = (int)$filters['account_id'];
            $types .= 'i';
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(jl.description LIKE ? OR j.memo LIKE ? OR j.ref_no LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $conditions[] = '(jl.debit + jl.credit) >= ?';
            $params[] = (float)$filters['min_amount'];
            $types .= 'd';
        }

        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $conditions[] = '(jl.debit + jl.credit) <= ?';
            $params[] = (float)$filters['max_amount'];
            $types .= 'd';
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $query = "SELECT jl.id,
                         j.id AS journal_id,
                         j.journal_date AS entry_date,
                         COALESCE(jl.description, j.memo) AS description,
                         j.ref_no,
                         jl.debit,
                         jl.credit,
                         jl.account_id,
                         acc.name AS account_name,
                         acc.account_number,
                         acc.account_type,
                         cat.name AS category_name,
                         j.memo
                  FROM acc_journal_lines jl
                  JOIN acc_journals j ON j.id = jl.journal_id
                  LEFT JOIN acc_ledger_accounts acc ON acc.id = jl.account_id
                  LEFT JOIN acc_transaction_categories cat ON cat.id = jl.category_id
                  $where
                  ORDER BY j.journal_date ASC, jl.line_order ASC, jl.id ASC";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $entries = [];

        while ($row = $result->fetch_assoc()) {
            $entries[] = [
                'sort_key' => 'journal-' . $row['journal_id'] . '-' . $row['id'],
                'entry_source' => 'journal',
                'entry_date' => $row['entry_date'],
                'reference' => $row['ref_no'],
                'memo' => $row['description'],
                'transaction_type' => 'Journal Entry',
                'account_id' => (int)$row['account_id'],
                'account_name' => $row['account_name'],
                'account_number' => $row['account_number'],
                'account_type' => $row['account_type'] ?? 'Asset',
                'category_name' => $row['category_name'],
                'vendor_name' => null,
                'debit_amount' => (float)$row['debit'],
                'credit_amount' => (float)$row['credit'],
                'raw' => $row,
            ];
        }

        $stmt->close();
        return $entries;
    }
}
