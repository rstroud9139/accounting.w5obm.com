<?php
class ReconciliationController extends BaseController
{
    private function getAccounts()
    {
        global $conn;
        try {
            $res = $conn->query("SHOW TABLES LIKE 'acc_ledger_accounts'");
            if ($res && $res->num_rows > 0) {
                $res2 = $conn->query("SELECT id, name, type FROM acc_ledger_accounts ORDER BY type, name");
                return $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : array();
            }
        } catch (Throwable $e) {
        }
        return array();
    }

    private function hasDoubleEntry()
    {
        global $conn;
        try {
            $a = $conn->query("SHOW TABLES LIKE 'acc_journals'");
            $b = $conn->query("SHOW TABLES LIKE 'acc_journal_lines'");
            return $a && $a->num_rows > 0 && $b && $b->num_rows > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function index()
    {
        requirePermission('accounting_manage');
        $this->render('reconciliation/index', array(
            'page_title' => 'Reconciliation',
            'accounts' => $this->getAccounts(),
            'csrf' => getCsrfToken(),
            'has_double' => $this->hasDoubleEntry()
        ));
    }

    public function review()
    {
        requirePermission('accounting_manage');
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        if (!$this->hasDoubleEntry()) {
            $this->render('reconciliation/index', array(
                'page_title' => 'Reconciliation',
                'accounts' => $this->getAccounts(),
                'error' => 'Double-entry tables not found. Reconciliation requires journal tables.',
                'csrf' => getCsrfToken(),
                'has_double' => false
            ));
            return;
        }
        global $conn;
        $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        $opening_balance = isset($_POST['opening_balance']) ? (float)$_POST['opening_balance'] : 0.0;
        $ending_balance = isset($_POST['ending_balance']) ? (float)$_POST['ending_balance'] : 0.0;
        $items = array();
        if ($account_id && $start && $end) {
            $stmt = $conn->prepare("SELECT jl.id, j.journal_date, COALESCE(jl.description, j.memo) AS description, jl.debit, jl.credit
                                     FROM acc_journal_lines jl
                                     JOIN acc_journals j ON j.id = jl.journal_id
                                     WHERE jl.account_id = ? AND j.journal_date BETWEEN ? AND ?
                                     ORDER BY j.journal_date, jl.id");
            if ($stmt) {
                $stmt->bind_param('iss', $account_id, $start, $end);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $amount = (float)$r['debit'] - (float)$r['credit'];
                    $items[] = array(
                        'id' => (int)$r['id'],
                        'date' => $r['journal_date'],
                        'description' => $r['description'],
                        'amount' => $amount,
                    );
                }
                $stmt->close();
            }
        }
        $this->render('reconciliation/review', array(
            'page_title' => 'Reconciliation Review',
            'account_id' => $account_id,
            'start' => $start,
            'end' => $end,
            'opening_balance' => $opening_balance,
            'ending_balance' => $ending_balance,
            'items' => $items,
            'csrf' => getCsrfToken()
        ));
    }

    public function commit()
    {
        requirePermission('accounting_manage');
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        global $conn;
        $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
        $start = $_POST['start'] ?? '';
        $end = $_POST['end'] ?? '';
        $opening_balance = isset($_POST['opening_balance']) ? (float)$_POST['opening_balance'] : 0.0;
        $ending_balance = isset($_POST['ending_balance']) ? (float)$_POST['ending_balance'] : 0.0;
        $lines = isset($_POST['line']) && is_array($_POST['line']) ? $_POST['line'] : array();
        if (!$account_id || !$start || !$end) {
            header('Location: ' . route('reconciliation'));
            return;
        }
        $conn->begin_transaction();
        try {
            // Ensure opening_balance column exists (best-effort)
            try {
                $conn->query("ALTER TABLE acc_reconciliations ADD COLUMN opening_balance DECIMAL(12,2) NULL");
            } catch (Throwable $e) {
            }
            // Try insert with opening_balance first, fallback if needed
            $rid = 0;
            $stmt = $conn->prepare('INSERT INTO acc_reconciliations (account_id, start_date, end_date, opening_balance, ending_balance) VALUES (?,?,?,?,?)');
            if ($stmt) {
                $stmt->bind_param('issdd', $account_id, $start, $end, $opening_balance, $ending_balance);
                if ($stmt->execute()) {
                    $rid = $stmt->insert_id;
                }
                $stmt->close();
            }
            if ($rid <= 0) {
                $stmt = $conn->prepare('INSERT INTO acc_reconciliations (account_id, start_date, end_date, ending_balance) VALUES (?,?,?,?)');
                if (!$stmt) throw new Exception('Failed to prepare reconciliation insert');
                $stmt->bind_param('issd', $account_id, $start, $end, $ending_balance);
                $stmt->execute();
                $rid = $stmt->insert_id;
                $stmt->close();
            }
            if (!empty($lines)) {
                // Ensure cleared_at column exists
                try {
                    $conn->query("ALTER TABLE acc_reconciled_lines ADD COLUMN cleared_at DATETIME NULL");
                } catch (Throwable $e) {
                }
                // Try insert with cleared_at timestamp
                $ins = $conn->prepare('INSERT INTO acc_reconciled_lines (reconciliation_id, journal_line_id, cleared_at) VALUES (?,?,NOW())');
                if (!$ins) {
                    $ins = $conn->prepare('INSERT INTO acc_reconciled_lines (reconciliation_id, journal_line_id) VALUES (?,?)');
                }
                if ($ins) {
                    foreach ($lines as $lid => $on) {
                        $lid = (int)$lid;
                        if ($ins->param_count === 3) {
                            $ins->bind_param('ii', $rid, $lid);
                        } else {
                            $ins->bind_param('ii', $rid, $lid);
                        }
                        $ins->execute();
                    }
                    $ins->close();
                }
            }
            $conn->commit();
            header('Location: ' . route('reconciliation_view') . '&rid=' . (int)$rid);
        } catch (Throwable $e) {
            $conn->rollback();
            $this->render('reconciliation/index', array(
                'page_title' => 'Reconciliation',
                'accounts' => $this->getAccounts(),
                'error' => 'Failed to save reconciliation: ' . $e->getMessage(),
                'csrf' => getCsrfToken(),
                'has_double' => $this->hasDoubleEntry()
            ));
        }
    }

    public function view()
    {
        requirePermission('accounting_manage');
        global $conn;
        $rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
        if ($rid <= 0) {
            header('Location: ' . route('reconciliation'));
            return;
        }
        // Load reconciliation header
        $rec = null;
        try {
            $res = $conn->query("SELECT r.*, a.name AS account_name FROM acc_reconciliations r LEFT JOIN acc_ledger_accounts a ON a.id = r.account_id WHERE r.id = " . $rid . " LIMIT 1");
            if ($res) $rec = $res->fetch_assoc();
        } catch (Throwable $e) {
        }
        if (!$rec) {
            header('Location: ' . route('reconciliation'));
            return;
        }
        // Load cleared lines
        $lines = array();
        try {
            $sql = "SELECT jl.id, j.journal_date, COALESCE(jl.description, j.memo) AS description, (jl.debit - jl.credit) AS amount
                    FROM acc_reconciled_lines rl
                    JOIN acc_journal_lines jl ON jl.id = rl.journal_line_id
                    JOIN acc_journals j ON j.id = jl.journal_id
                    WHERE rl.reconciliation_id = ?
                    ORDER BY j.journal_date, jl.id";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $rid);
                $stmt->execute();
                $res = $stmt->get_result();
                $lines = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
                $stmt->close();
            }
        } catch (Throwable $e) {
        }
        $this->render('reconciliation/view', array(
            'page_title' => 'Reconciliation Report',
            'rec' => $rec,
            'lines' => $lines,
        ));
    }

    public function export_csv()
    {
        requirePermission('accounting_manage');
        global $conn;
        $rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
        if ($rid <= 0) {
            http_response_code(400);
            echo 'Missing rid';
            return;
        }
        // Load reconciliation
        $rec = null;
        try {
            $res = $conn->prepare("SELECT r.*, a.name AS account_name FROM acc_reconciliations r LEFT JOIN acc_ledger_accounts a ON a.id = r.account_id WHERE r.id = ? LIMIT 1");
            if ($res) {
                $res->bind_param('i', $rid);
                $res->execute();
                $rs = $res->get_result();
                $rec = $rs ? $rs->fetch_assoc() : null;
                $res->close();
            }
        } catch (Throwable $e) {
        }
        if (!$rec) {
            http_response_code(404);
            echo 'Reconciliation not found';
            return;
        }
        // Load lines
        $lines = array();
        try {
            $sql = "SELECT j.journal_date, COALESCE(jl.description, j.memo) AS description, (jl.debit - jl.credit) AS amount
                    FROM acc_reconciled_lines rl
                    JOIN acc_journal_lines jl ON jl.id = rl.journal_line_id
                    JOIN acc_journals j ON j.id = jl.journal_id
                    WHERE rl.reconciliation_id = ?
                    ORDER BY j.journal_date, jl.id";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $rid);
                $stmt->execute();
                $res = $stmt->get_result();
                $lines = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
                $stmt->close();
            }
        } catch (Throwable $e) {
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reconciliation_' . $rid . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Report', 'Reconciliation Report']);
        fputcsv($out, ['Account', ($rec['account_name'] ?? ('#' . $rec['account_id']))]);
        fputcsv($out, ['Period', $rec['start_date'] . ' to ' . $rec['end_date']]);
        fputcsv($out, ['Opening', number_format((float)($rec['opening_balance'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['Ending', number_format((float)$rec['ending_balance'], 2, '.', '')]);
        fputcsv($out, ['Generated', date('Y-m-d H:i')]);
        fputcsv($out, ['']);
        fputcsv($out, ['Date', 'Description', 'Amount']);
        $sum = 0.0;
        foreach ($lines as $ln) {
            $amt = (float)$ln['amount'];
            $sum += $amt;
            fputcsv($out, [
                $ln['journal_date'],
                $ln['description'] ?? '',
                number_format($amt, 2, '.', ''),
            ]);
        }
        fputcsv($out, ['']);
        fputcsv($out, ['Cleared total', number_format($sum, 2, '.', '')]);
        $openPlus = $sum + (float)($rec['opening_balance'] ?? 0);
        fputcsv($out, ['Opening + Cleared', number_format($openPlus, 2, '.', '')]);
        $diff = (float)$rec['ending_balance'] - $openPlus;
        fputcsv($out, ['Difference vs Ending', number_format($diff, 2, '.', '')]);
        fclose($out);
        exit;
    }
}
