<?php
class PostingService
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    private function tableExists($table)
    {
        $table = $this->conn->real_escape_string($table);
        $sql = "SHOW TABLES LIKE '$table'";
        $res = $this->conn->query($sql);
        return $res && $res->num_rows > 0;
    }

    public function postTransaction($transactionId, $payload, $splits = array())
    {
        $hasSplitsTable = $this->tableExists('acc_transaction_splits');
        $hasJournalEntries = $this->tableExists('acc_journal_entries');
        $hasJournals = $this->tableExists('acc_journals') && $this->tableExists('acc_journal_lines');

        // If splits provided, persist them if split table exists
        if (!empty($splits) && $hasSplitsTable) {
            foreach ($splits as $s) {
                $stmt = $this->conn->prepare("INSERT INTO acc_transaction_splits
                    (transaction_id, category_id, amount, notes)
                    VALUES (?, ?, ?, ?)");
                $cat = isset($s['category_id']) ? (int)$s['category_id'] : null;
                $amt = isset($s['amount']) ? (float)$s['amount'] : 0.0;
                $notes = isset($s['notes']) ? $s['notes'] : '';
                $stmt->bind_param('iids', $transactionId, $cat, $amt, $notes);
                $stmt->execute();
            }
        }

        // Create journal entries if table exists (simple two-line posting)
        if ($hasJournals) {
            $this->postDoubleEntry($transactionId, $payload, $splits);
            return;
        }

        if ($hasJournalEntries) {
            $type = isset($payload['type']) ? $payload['type'] : 'Expense';
            $total = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;

            // Use splits if provided; else use single category
            $lines = array();
            if (!empty($splits)) {
                foreach ($splits as $s) {
                    $lines[] = array(
                        'category_id' => isset($s['category_id']) ? (int)$s['category_id'] : null,
                        'amount' => (float)(isset($s['amount']) ? $s['amount'] : 0.0),
                    );
                }
                // Recompute total from splits
                $total = 0.0;
                foreach ($lines as $ln) {
                    $total += $ln['amount'];
                }
            } else {
                $lines[] = array(
                    'category_id' => isset($payload['category_id']) ? (int)$payload['category_id'] : null,
                    'amount' => $total,
                );
            }

            // Infer debit/credit: For Income, credit income category, debit cash; For Expense, debit expense category, credit cash
            // We don't know cash account id; journal table will store category-level postings; cash balancing as null account
            foreach ($lines as $ln) {
                $amt = (float)$ln['amount'];
                if ($type === 'Income') {
                    // Income: credit income category, debit cash
                    $this->insertJournal($transactionId, $ln['category_id'], 0.0, $amt);
                } else {
                    // Expense: debit expense category, credit cash
                    $this->insertJournal($transactionId, $ln['category_id'], $amt, 0.0);
                }
            }
            // Balancing entry: opposite side total to implicit cash (category_id null)
            if ($type === 'Income') {
                $this->insertJournal($transactionId, null, $total, 0.0);
            } else {
                $this->insertJournal($transactionId, null, 0.0, $total);
            }
        }
    }

    private function insertJournal($transactionId, $categoryId, $debit, $credit)
    {
        $stmt = $this->conn->prepare("INSERT INTO acc_journal_entries
            (transaction_id, category_id, debit, credit, created_at)
            VALUES (?, ?, ?, ?, NOW())");
        if ($categoryId === null) {
            // Bind NULLs correctly by using i (int) then set to null via bind_param is tricky; use explicit NULL in SQL when needed
            // Workaround: use dynamic SQL
            $sql = "INSERT INTO acc_journal_entries (transaction_id, category_id, debit, credit, created_at) VALUES (?, NULL, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('idd', $transactionId, $debit, $credit);
            $stmt->execute();
            return;
        }
        $stmt->bind_param('iidd', $transactionId, $categoryId, $debit, $credit);
        $stmt->execute();
    }

    private function postDoubleEntry($transactionId, $payload, $splits)
    {
        $date = isset($payload['transaction_date']) ? $payload['transaction_date'] : date('Y-m-d');
        $memo = isset($payload['notes']) ? $payload['notes'] : (isset($payload['description']) ? $payload['description'] : '');
        $type = isset($payload['type']) ? $payload['type'] : 'Expense';
        $defaultOffset = isset($payload['offset_account_id']) ? (int)$payload['offset_account_id'] : 0;
        $cashAccount = isset($payload['cash_account_id']) ? (int)$payload['cash_account_id'] : 0;
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;

        // Create journal
        $stmt = $this->conn->prepare("INSERT INTO acc_journals (journal_date, memo, posted_at) VALUES (?, ?, NOW())");
        $stmt->bind_param('ss', $date, $memo);
        if (!$stmt->execute()) return;
        $journalId = $this->conn->insert_id;

        $lines = array();
        $total = 0.0;
        if (!empty($splits)) {
            foreach ($splits as $s) {
                $lnAmt = isset($s['amount']) ? (float)$s['amount'] : 0.0;
                if ($lnAmt <= 0) continue;
                $lines[] = array(
                    'account_id' => isset($s['offset_account_id']) && (int)$s['offset_account_id'] ? (int)$s['offset_account_id'] : $defaultOffset,
                    'category_id' => isset($s['category_id']) ? (int)$s['category_id'] : null,
                    'amount' => $lnAmt,
                    'description' => isset($s['notes']) ? $s['notes'] : '',
                );
                $total += $lnAmt;
            }
        } else {
            $lines[] = array(
                'account_id' => $defaultOffset,
                'category_id' => isset($payload['category_id']) ? (int)$payload['category_id'] : null,
                'amount' => $amount,
                'description' => $memo,
            );
            $total = $amount;
        }

        // Insert offset lines (credit for income, debit for expense). For transfers, treat offset lines as destination/source.
        $order = 1;
        foreach ($lines as $ln) {
            $accId = (int)$ln['account_id'];
            if ($accId <= 0) continue; // skip if no account
            $catId = $ln['category_id'] ? (int)$ln['category_id'] : null;
            $desc = $ln['description'];
            $debit = 0.0;
            $credit = 0.0;
            if ($type === 'Income') {
                $credit = $ln['amount'];
            } elseif ($type === 'Transfer') {
                // For transfer, lines[] represent the destination (offset) side; credit on source is handled by cash line below
                $debit = $ln['amount'];
            } else {
                $debit = $ln['amount'];
            }
            $this->insertJournalLine($journalId, $accId, $catId, $desc, $debit, $credit, $order++);
        }

        // Insert balancing cash line
        if ($cashAccount > 0) {
            $debit = 0.0;
            $credit = 0.0;
            if ($type === 'Income') {
                $debit = $total;
            } elseif ($type === 'Transfer') {
                // For transfer, cashAccount is the source; credit it
                $credit = $total;
            } else {
                $credit = $total;
            }
            $this->insertJournalLine($journalId, $cashAccount, null, $memo, $debit, $credit, $order++);
        }
    }

    private function insertJournalLine($journalId, $accountId, $categoryId, $description, $debit, $credit, $order)
    {
        if ($categoryId === null) {
            $sql = "INSERT INTO acc_journal_lines (journal_id, account_id, category_id, description, debit, credit, line_order) VALUES (?, ?, NULL, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('iisddi', $journalId, $accountId, $description, $debit, $credit, $order);
            $stmt->execute();
            return;
        }
        $stmt = $this->conn->prepare("INSERT INTO acc_journal_lines (journal_id, account_id, category_id, description, debit, credit, line_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiisddi', $journalId, $accountId, $categoryId, $description, $debit, $credit, $order);
        $stmt->execute();
    }
}
