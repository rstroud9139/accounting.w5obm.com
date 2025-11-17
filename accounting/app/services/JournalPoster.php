<?php

class JournalPoster
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function postSimplePayment(array $payload): ?int
    {
        $this->conn->begin_transaction();

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO acc_journals (journal_date, memo, source, source_system, external_txn_id, status) VALUES (?, ?, ?, ?, ?, 'Posted')"
            );
            $postedAt = $payload['posted_at'];
            $memo = $payload['memo'];
            $source = $payload['source'] ?? null;
            $sourceSystem = $payload['source_system'] ?? null;
            $externalTxnId = $payload['external_txn_id'] ?? null;

            $stmt->bind_param(
                'sssss',
                $postedAt,
                $memo,
                $source,
                $sourceSystem,
                $externalTxnId
            );
            $stmt->execute();
            $journalId = $stmt->insert_id;
            $stmt->close();

            $lines = $payload['lines'] ?? [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            $stmtLine = $this->conn->prepare(
                "INSERT INTO acc_journal_lines (journal_id, account_id, category_id, description, debit, credit, line_order) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $order = 0;
            foreach ($lines as $line) {
                $order++;
                $debit = (float)($line['debit'] ?? 0);
                $credit = (float)($line['credit'] ?? 0);
                $totalDebit += $debit;
                $totalCredit += $credit;

                $accountId = (int)$line['account_id'];
                $categoryId = isset($line['category_id']) ? (int)$line['category_id'] : null;
                $desc = $line['description'] ?? '';

                $stmtLine->bind_param(
                    'iiissdi',
                    $journalId,
                    $accountId,
                    $categoryId,
                    $desc,
                    $debit,
                    $credit,
                    $order
                );
                $stmtLine->execute();
            }

            $stmtLine->close();

            if (abs($totalDebit - $totalCredit) > 0.0001) {
                throw new RuntimeException('Journal not balanced');
            }

            $this->conn->commit();

            return $journalId;
        } catch (Throwable $e) {
            $this->conn->rollback();
            return null;
        }
    }
}
