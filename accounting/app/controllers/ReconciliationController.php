<?php
class ReconciliationController extends BaseController
{
    private $tableCache = [];
    private $columnCache = [];

    private function db(): mysqli
    {
        return accounting_db_connection();
    }

    private function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $this->tableCache)) {
            return (bool)$this->tableCache[$table];
        }
        try {
            $conn = $this->db();
            $stmt = $conn->prepare('SHOW TABLES LIKE ?');
            if ($stmt) {
                $stmt->bind_param('s', $table);
                $stmt->execute();
                $res = $stmt->get_result();
                $this->tableCache[$table] = $res && $res->num_rows > 0;
                $stmt->close();
                return $this->tableCache[$table];
            }
        } catch (Throwable $e) {
        }
        $this->tableCache[$table] = false;
        return false;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return (bool)$this->columnCache[$cacheKey];
        }
        if (!$this->tableExists($table)) {
            $this->columnCache[$cacheKey] = false;
            return false;
        }
        try {
            $conn = $this->db();
            $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ss', $table, $column);
                $stmt->execute();
                $res = $stmt->get_result();
                $this->columnCache[$cacheKey] = $res && $res->num_rows > 0;
                $stmt->close();
                return $this->columnCache[$cacheKey];
            }
        } catch (Throwable $e) {
        }
        $this->columnCache[$cacheKey] = false;
        return false;
    }

    private function getAccounts()
    {
        try {
            $conn = $this->db();
            if ($this->tableExists('acc_ledger_accounts')) {
                $res = $conn->query("SELECT id, name, account_type AS type FROM acc_ledger_accounts ORDER BY account_type, name");
                if ($res) {
                    return $res->fetch_all(MYSQLI_ASSOC);
                }
            } elseif ($this->tableExists('acc_chart_of_accounts')) {
                $res = $conn->query("SELECT id, name, type FROM acc_chart_of_accounts ORDER BY type, name");
                if ($res) {
                    return $res->fetch_all(MYSQLI_ASSOC);
                }
            }
        } catch (Throwable $e) {
        }
        return array();
    }

    private function hasDoubleEntry()
    {
        return $this->tableExists('acc_journals') && $this->tableExists('acc_journal_lines');
    }

    private function fetchActiveReconciliation(): ?array
    {
        if (!$this->tableExists('acc_reconciliations')) {
            return null;
        }
        try {
            $conn = $this->db();
            $hasOpening = $this->tableHasColumn('acc_reconciliations', 'opening_balance');
            $hasReviewer = $this->tableHasColumn('acc_reconciliations', 'reviewer_initials');
            $columns = 'r.*, a.name AS account_name, a.account_number AS account_number';
            if (!$hasOpening) {
                $columns .= ', NULL AS opening_balance';
            }
            if (!$hasReviewer) {
                $columns .= ', NULL AS reviewer_initials';
            }
            $sql = "SELECT $columns
                    FROM acc_reconciliations r
                    LEFT JOIN acc_ledger_accounts a ON a.id = r.account_id
                    ORDER BY r.end_date DESC, r.id DESC
                    LIMIT 1";
            $res = $conn->query($sql);
            if ($res && ($row = $res->fetch_assoc())) {
                return $row;
            }
        } catch (Throwable $e) {
        }
        return null;
    }

    private function fetchClearedLineMeta(int $reconciliationId): array
    {
        if (!$this->tableExists('acc_reconciled_lines')) {
            return [];
        }
        $columns = ['journal_line_id'];
        if ($this->tableHasColumn('acc_reconciled_lines', 'cleared_at')) {
            $columns[] = 'cleared_at';
        }
        if ($this->tableHasColumn('acc_reconciled_lines', 'cleared_by')) {
            $columns[] = 'cleared_by';
        }
        try {
            $conn = $this->db();
            $sql = 'SELECT ' . implode(', ', $columns) . ' FROM acc_reconciled_lines WHERE reconciliation_id = ?';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $reconciliationId);
            $stmt->execute();
            $res = $stmt->get_result();
            $meta = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $meta[(int)$row['journal_line_id']] = [
                        'cleared_at' => $row['cleared_at'] ?? null,
                        'cleared_by' => $row['cleared_by'] ?? null,
                    ];
                }
            }
            $stmt->close();
            return $meta;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function fetchRecentReconciliations(int $limit = 4): array
    {
        if (!$this->tableExists('acc_reconciliations')) {
            return [];
        }
        $limit = max(1, min(10, $limit));
        try {
            $conn = $this->db();
            $sql = "SELECT r.id, r.start_date, r.end_date, r.ending_balance, r.created_at, a.name AS account_name
                    FROM acc_reconciliations r
                    LEFT JOIN acc_ledger_accounts a ON a.id = r.account_id
                    ORDER BY r.end_date DESC, r.id DESC
                    LIMIT $limit";
            $res = $conn->query($sql);
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function buildWorkspaceData(): array
    {
        $hasDouble = $this->hasDoubleEntry();
        $hasReconTables = $this->tableExists('acc_reconciliations');
        $data = [
            'ready' => $hasDouble && $hasReconTables,
            'status' => null,
            'active' => null,
            'uncleared' => ['items' => [], 'display' => [], 'count' => 0, 'total' => 0.0, 'prechecked_total' => 0.0],
            'cleared' => ['items' => [], 'display' => [], 'count' => 0, 'total' => 0.0],
            'snapshot' => ['difference' => 0.0, 'cleared_ratio' => '0 / 0', 'next_review' => null, 'cleared_percent' => 0],
            'recent' => $this->fetchRecentReconciliations(),
            'notes' => [],
            'has_double' => $hasDouble,
            'has_tables' => $hasReconTables,
        ];

        if (!$hasDouble) {
            $data['status'] = 'Double-entry journal tables not detected. Run migrations to enable reconciliation.';
            $data['ready'] = false;
            return $data;
        }
        if (!$hasReconTables) {
            $data['status'] = 'Reconciliation tables are missing. Run the latest accounting migrations to continue.';
            $data['ready'] = false;
            return $data;
        }

        $active = $this->fetchActiveReconciliation();
        if (!$active) {
            $data['status'] = 'No statements have been reconciled yet. Use the Statement Inputs form below to begin.';
            $data['ready'] = false;
            return $data;
        }

        require_once __DIR__ . '/../repositories/JournalRepository.php';
        $conn = $this->db();
        $journalRepo = new JournalRepository($conn);
        $lines = $journalRepo->getRegisterLines((int)$active['account_id'], $active['start_date'], $active['end_date']);
        $clearedMeta = $this->fetchClearedLineMeta((int)$active['id']);

        $openingBalance = isset($active['opening_balance']) ? (float)$active['opening_balance'] : null;
        $cleared = [];
        $uncleared = [];
        $clearedTotal = 0.0;
        $unclearedTotal = 0.0;
        foreach ($lines as $line) {
            $amount = (float)($line['amount'] ?? ((float)($line['debit'] ?? 0) - (float)($line['credit'] ?? 0)));
            $entry = [
                'id' => (int)$line['id'],
                'date' => $line['journal_date'],
                'description' => $line['description'] ?? 'Unlabeled entry',
                'notes' => 'Journal line #' . (int)$line['id'],
                'amount' => $amount,
                'cleared_at' => null,
                'cleared_by' => null,
            ];
            if (isset($clearedMeta[$entry['id']])) {
                $entry['cleared_at'] = $clearedMeta[$entry['id']]['cleared_at'];
                $entry['cleared_by'] = $clearedMeta[$entry['id']]['cleared_by'];
                $clearedTotal += $amount;
                $cleared[] = $entry;
            } else {
                $unclearedTotal += $amount;
                $uncleared[] = $entry;
            }
        }

        $totalCount = count($cleared) + count($uncleared);
        $progressPercent = $totalCount > 0 ? (int)round((count($cleared) / $totalCount) * 100) : 0;
        if ($openingBalance === null) {
            $openingBalance = (float)$active['ending_balance'] - ($clearedTotal + $unclearedTotal);
        }
        $ledgerBalance = $openingBalance + $clearedTotal + $unclearedTotal;
        $difference = (float)$active['ending_balance'] - $ledgerBalance;
        $lastClearedAt = null;
        foreach ($cleared as $entry) {
            if (!empty($entry['cleared_at'])) {
                $ts = strtotime($entry['cleared_at']);
                if ($ts && ($lastClearedAt === null || $ts > $lastClearedAt)) {
                    $lastClearedAt = $ts;
                }
            }
        }
        $lastClearedLabel = $lastClearedAt ? date('M j, Y g:ia', $lastClearedAt) : '—';

        $statusLabel = 'On Track';
        $statusClass = 'success';
        if ($progressPercent < 60) {
            $statusLabel = 'In Progress';
            $statusClass = 'info';
        }
        if (abs($difference) >= 1) {
            $statusLabel = 'Needs Review';
            $statusClass = 'warning';
        }
        if ($progressPercent >= 99 && abs($difference) < 1) {
            $statusLabel = 'Ready to Close';
            $statusClass = 'success';
        }

        $data['ready'] = true;
        $data['active'] = [
            'id' => (int)$active['id'],
            'account_id' => (int)$active['account_id'],
            'account_name' => $active['account_name'] ?? ('Account #' . $active['account_id']),
            'statement_title' => sprintf('%s Statement · %s', date('F Y', strtotime($active['end_date'])), $active['account_name'] ?? 'Account #' . $active['account_id']),
            'open_since' => !empty($active['created_at']) ? date('M j, Y', strtotime($active['created_at'])) : date('M j, Y', strtotime($active['start_date'])),
            'progress_percent' => $progressPercent,
            'progress_label' => $progressPercent . '% cleared',
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'statement_balance' => (float)$active['ending_balance'],
            'ledger_balance' => $ledgerBalance,
            'difference' => $difference,
            'last_cleared_label' => $lastClearedLabel,
            'opening_balance' => $openingBalance,
            'start_date' => $active['start_date'],
            'end_date' => $active['end_date'],
            'reviewer_initials' => $active['reviewer_initials'] ?? null,
            'cleared_count' => count($cleared),
            'total_transactions' => $totalCount,
        ];

        $precheckTotal = 0.0;
        foreach ($uncleared as $idx => &$entry) {
            $entry['prechecked'] = $idx < 3;
            if ($entry['prechecked']) {
                $precheckTotal += $entry['amount'];
            }
        }
        unset($entry);

        $data['uncleared'] = [
            'items' => $uncleared,
            'display' => array_slice($uncleared, 0, 10),
            'count' => count($uncleared),
            'total' => $unclearedTotal,
            'prechecked_total' => $precheckTotal,
        ];
        $data['cleared'] = [
            'items' => $cleared,
            'display' => array_slice($cleared, 0, 10),
            'count' => count($cleared),
            'total' => $clearedTotal,
        ];
        $data['snapshot'] = [
            'difference' => $difference,
            'cleared_ratio' => $totalCount > 0 ? sprintf('%d / %d', count($cleared), $totalCount) : '0 / 0',
            'next_review' => date('M d, Y', strtotime($active['end_date'] . ' +7 days')) . ' · Finance',
            'cleared_percent' => $progressPercent,
        ];

        $notes = [];
        foreach (array_slice($uncleared, 0, 3) as $entry) {
            $notes[] = [
                'title' => 'Follow up',
                'body' => sprintf('%s (%s)', $entry['description'], number_format($entry['amount'], 2)),
            ];
        }
        if (empty($notes)) {
            $notes = [
                ['title' => 'Reminder', 'body' => 'Attach supporting documentation before closing the cycle.'],
            ];
        }
        $data['notes'] = $notes;

        return $data;
    }

    public function index()
    {
        requirePermission('accounting_manage');
        $workspace = $this->buildWorkspaceData();
        $this->render('reconciliation/index', array(
            'page_title' => 'Reconciliation Workspace',
            'accounts' => $this->getAccounts(),
            'csrf' => getCsrfToken(),
            'workspace' => $workspace,
            'has_double' => $workspace['has_double'],
            'error' => null,
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
                'has_double' => false,
                'workspace' => $this->buildWorkspaceData(),
            ));
            return;
        }
        $conn = $this->db();
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
        $conn = $this->db();
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
                'has_double' => $this->hasDoubleEntry(),
                'workspace' => $this->buildWorkspaceData(),
            ));
        }
    }

    public function view()
    {
        requirePermission('accounting_manage');
        $conn = $this->db();
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
        $conn = $this->db();
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
