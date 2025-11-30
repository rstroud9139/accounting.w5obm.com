<?php
class ImportController extends BaseController
{
    private function getCategories()
    {
        $db = accounting_db_connection();
        try {
            $res = $db->query("SELECT id, name FROM acc_transaction_categories ORDER BY name");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        } catch (Throwable $e) {
            return array();
        }
    }

    private function getAccounts()
    {
        // Try to load accounts from either acc_chart_of_accounts or acc_ledger_accounts
        $db = accounting_db_connection();
        try {
            // Prefer acc_ledger_accounts if present
            $res = $db->query("SHOW TABLES LIKE 'acc_ledger_accounts'");
            if ($res && $res->num_rows > 0) {
                $res2 = $db->query("SELECT id, name, type FROM acc_ledger_accounts ORDER BY type, name");
                return $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : array();
            }
            // Fallback to chart of accounts
            $res = $db->query("SHOW TABLES LIKE 'acc_chart_of_accounts'");
            if ($res && $res->num_rows > 0) {
                $res2 = $db->query("SELECT id, name, type FROM acc_chart_of_accounts ORDER BY type, name");
                return $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : array();
            }
        } catch (Throwable $e) {
            // ignore
        }
        return array();
    }

    public function index()
    {
        $this->render('import/index', array(
            'page_title' => 'Import Transactions',
            'categories' => $this->getCategories(),
            'accounts' => $this->getAccounts(),
            'csrf' => getCsrfToken()
        ));
    }

    public function upload()
    {
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        $db = accounting_db_connection();
        $type = $_POST['import_type'] ?? 'auto';
        $default_income_cat = isset($_POST['default_income_cat']) ? (int)$_POST['default_income_cat'] : 0;
        $default_expense_cat = isset($_POST['default_expense_cat']) ? (int)$_POST['default_expense_cat'] : 0;

        if (empty($_FILES['import_file']['tmp_name'])) {
            $this->render('import/index', array(
                'page_title' => 'Import Transactions',
                'categories' => $this->getCategories(),
                'error' => 'No file uploaded.',
                'csrf' => getCsrfToken()
            ));
            return;
        }

        $tmp = $_FILES['import_file']['tmp_name'];
        $filename = $_FILES['import_file']['name'] ?? '';

        require_once __DIR__ . '/../services/ImportService.php';
        $svc = new ImportService();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($type === 'auto') {
            $type = $svc->guessType($filename);
        }

        $normalized = $svc->parseFile($type, $tmp);
        if (!is_array($normalized)) {
            $this->render('import/index', array(
                'page_title' => 'Import Transactions',
                'categories' => $this->getCategories(),
                'error' => 'Unable to parse file as ' . htmlspecialchars($type),
                'csrf' => getCsrfToken()
            ));
            return;
        }

        // Precompute likely duplicates for preview (date + abs(amount) + description)
        $dupFlags = array();
        $dupCount = 0;
        try {
            $db->query("CREATE TABLE IF NOT EXISTS acc_transactions (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB");
            $stmt = $db->prepare('SELECT id FROM acc_transactions WHERE transaction_date = ? AND amount = ? AND description = ? LIMIT 1');
        } catch (Throwable $e) {
            $stmt = null;
        }
        foreach ($normalized as $row) {
            $d = isset($row['date']) ? $row['date'] : date('Y-m-d');
            $a = isset($row['amount']) ? (float)$row['amount'] : 0.0;
            $desc = isset($row['description']) ? $row['description'] : (isset($row['payee']) ? $row['payee'] : '');
            $isDup = false;
            if ($stmt) {
                try {
                    $absAmt = abs($a);
                    $stmt->bind_param('sds', $d, $absAmt, $desc);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $isDup = ($res && $res->num_rows > 0);
                    if ($isDup) $dupCount++;
                } catch (Throwable $e) {
                }
            }
            $dupFlags[] = $isDup;
        }
        if ($stmt) {
            try {
                $stmt->close();
            } catch (Throwable $e) {
            }
        }

        // Store import in session for commit step
        $_SESSION['import_preview'] = array(
            'type' => $type,
            'filename' => $filename,
            'items' => $normalized,
            'defaults' => array(
                'income_cat' => $default_income_cat,
                'expense_cat' => $default_expense_cat,
            ),
        );

        $this->render('import/preview', array(
            'page_title' => 'Preview Import',
            'items' => $normalized,
            'categories' => $this->getCategories(),
            'accounts' => $this->getAccounts(),
            'category_map' => $this->loadCategoryMap(),
            'defaults' => $_SESSION['import_preview']['defaults'],
            'duplicates' => $dupFlags,
            'dup_count' => $dupCount,
            'csrf' => getCsrfToken()
        ));
    }

    private function loadCategoryMap()
    {
        require_once __DIR__ . '/../services/MappingService.php';
        $db = accounting_db_connection();
        $svc = new MappingService($db);
        return $svc->getMap();
    }

    public function commit()
    {
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        $db = accounting_db_connection();
        $data = $_SESSION['import_preview'] ?? null;
        if (!$data || empty($data['items'])) {
            header('Location: ' . route('import'));
            return;
        }
        $income_cat = isset($_POST['income_cat']) ? (int)$_POST['income_cat'] : ($data['defaults']['income_cat'] ?? 0);
        $expense_cat = isset($_POST['expense_cat']) ? (int)$_POST['expense_cat'] : ($data['defaults']['expense_cat'] ?? 0);
        $cash_account_id = isset($_POST['cash_account_id']) ? (int)$_POST['cash_account_id'] : 0;
        $offset_account_id = isset($_POST['offset_account_id']) ? (int)$_POST['offset_account_id'] : 0;
        $skip_duplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] == '1';

        require_once __DIR__ . '/../repositories/TransactionRepository.php';
        require_once __DIR__ . '/../services/AccountingService.php';
        $repo = new TransactionRepository($db);
        $svc = new AccountingService();

        $created = 0;
        $splitMismatchCount = 0;
        $rowErrors = array();
        $duplicatesSkipped = 0;

        // Guardrails: disallow same cash and offset when both provided
        if ($cash_account_id > 0 && $offset_account_id > 0 && $cash_account_id === $offset_account_id) {
            $this->render('import/preview', array(
                'page_title' => 'Preview Import',
                'items' => $data['items'],
                'categories' => $this->getCategories(),
                'accounts' => $this->getAccounts(),
                'category_map' => $this->loadCategoryMap(),
                'defaults' => $data['defaults'],
                'errors' => array('Default cash/bank account cannot equal default offset account.'),
                'csrf' => getCsrfToken()
            ));
            return;
        }
        // Allow overrides and splits posted from preview
        $postedRows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : array();
        $idx = 0;
        foreach ($data['items'] as $row) {
            $rowPost = isset($postedRows[$idx]) ? $postedRows[$idx] : array();
            // Normalize type/amount and assign category
            $amount = isset($rowPost['amount']) ? (float)$rowPost['amount'] : (float)($row['amount'] ?? 0);
            $type = isset($rowPost['type']) ? $rowPost['type'] : ($row['type'] ?? null);
            if (!$type) {
                $type = $amount >= 0 ? 'Income' : 'Expense';
            }
            if ($type === 'Expense' && $amount > 0) $amount = -$amount; // ensure expense as negative internally not needed, but consistent
            $category_id = isset($rowPost['category_id']) ? (int)$rowPost['category_id'] : ($row['category_id'] ?? 0);
            if (!$category_id) {
                $category_id = ($type === 'Income') ? $income_cat : $expense_cat;
            }

            $payload = array(
                'transaction_date' => isset($rowPost['date']) ? $rowPost['date'] : ($row['date'] ?? date('Y-m-d')),
                'type' => $type,
                'category_id' => $category_id ?: null,
                'amount' => abs($amount),
                'description' => isset($rowPost['description']) ? $rowPost['description'] : ($row['description'] ?? ($row['payee'] ?? '')),
                'notes' => isset($rowPost['memo']) ? $rowPost['memo'] : ($row['memo'] ?? ''),
                'reference_number' => isset($rowPost['reference']) ? $rowPost['reference'] : ($row['reference'] ?? ''),
                'cash_account_id' => $cash_account_id ?: null,
                'offset_account_id' => $offset_account_id ?: null,
            );
            $errors = $svc->validateTransactionData($payload);
            if (!empty($errors)) {
                continue;
            }
            // Build splits if provided
            $splits = array();
            if (isset($rowPost['splits']) && is_array($rowPost['splits'])) {
                foreach ($rowPost['splits'] as $sp) {
                    $sp_amt = isset($sp['amount']) ? (float)$sp['amount'] : 0.0;
                    $sp_cat = isset($sp['category_id']) ? (int)$sp['category_id'] : 0;
                    $sp_off = isset($sp['offset_account_id']) ? (int)$sp['offset_account_id'] : 0;
                    if ($sp_amt == 0) continue;
                    $splits[] = array(
                        'amount' => abs($sp_amt),
                        'category_id' => $sp_cat ?: null,
                        'offset_account_id' => $sp_off ?: null,
                        'notes' => isset($sp['notes']) ? $sp['notes'] : '',
                    );
                }
            }
            // If splits present, ensure each split has an offset account or default offset is provided
            if (!empty($splits)) {
                foreach ($splits as $sidx => $sp) {
                    $effOffset = isset($sp['offset_account_id']) && $sp['offset_account_id'] ? (int)$sp['offset_account_id'] : $offset_account_id;
                    if ($effOffset <= 0) {
                        $rowErrors[$idx] = 'Row #' . ($idx + 1) . ' split #' . ($sidx + 1) . ' missing offset account (select per-split or set default offset).';
                        break;
                    }
                }
            }
            // If splits present, enforce sum equals amount; otherwise block commit on mismatch
            if (!empty($splits)) {
                $sum = 0.0;
                foreach ($splits as $sp) {
                    $sum += (float)$sp['amount'];
                }
                if (abs($sum - abs($amount)) > 0.005) {
                    $splitMismatchCount++;
                    $rowErrors[$idx] = 'Row #' . ($idx + 1) . ' split total $' . number_format($sum, 2) . ' does not match amount $' . number_format(abs($amount), 2);
                }
            }
            // Duplicate detection (optional): date + amount + description exact match
            if ($skip_duplicates) {
                $dupDesc = isset($rowPost['description']) ? $rowPost['description'] : ($row['description'] ?? ($row['payee'] ?? ''));
                $dupDate = isset($rowPost['date']) ? $rowPost['date'] : ($row['date'] ?? date('Y-m-d'));
                $dupAmt = abs($amount);
                $stmt = $db->prepare('SELECT id FROM acc_transactions WHERE transaction_date = ? AND amount = ? AND description = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('sds', $dupDate, $dupAmt, $dupDesc);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $duplicatesSkipped++;
                        $idx++;
                        continue; // skip creating duplicate
                    }
                }
            }
            // Defer creation if any row error exists
            if (!isset($rowErrors[$idx])) {
                $id = $repo->createWithPosting($payload, $splits);
                if ($id) {
                    $created++;
                }
            }
            $idx++;
        }

        // If any errors, re-render preview with errors and do not import any rows
        if (!empty($rowErrors)) {
            // Preserve defaults
            $defaults = $data['defaults'];
            $this->render('import/preview', array(
                'page_title' => 'Preview Import',
                'items' => $data['items'],
                'categories' => $this->getCategories(),
                'accounts' => $this->getAccounts(),
                'category_map' => $this->loadCategoryMap(),
                'defaults' => $defaults,
                'errors' => array_values($rowErrors),
                'csrf' => getCsrfToken()
            ));
            return;
        }

        unset($_SESSION['import_preview']);

        $msg = $created . ' transactions imported.';
        if ($duplicatesSkipped > 0) {
            $msg .= ' ' . $duplicatesSkipped . ' duplicate(s) skipped.';
        }
        // Log import summary if table exists
        try {
            $db->query("CREATE TABLE IF NOT EXISTS acc_imports (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255), rows_imported INT, duplicates_skipped INT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $db->prepare('INSERT INTO acc_imports (filename, rows_imported, duplicates_skipped) VALUES (?,?,?)');
            if ($stmt) {
                $fn = $data['filename'] ?? '';
                $stmt->bind_param('sii', $fn, $created, $duplicatesSkipped);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
        }

        $this->render('import/index', array(
            'page_title' => 'Import Transactions',
            'categories' => $this->getCategories(),
            'success' => $msg,
            'csrf' => getCsrfToken()
        ));
    }

    public function last()
    {
        $db = accounting_db_connection();
        $last = null;
        try {
            $res = $db->query("SHOW TABLES LIKE 'acc_imports'");
            if ($res && $res->num_rows > 0) {
                $q = $db->query("SELECT id, filename, rows_imported, duplicates_skipped, created_at FROM acc_imports ORDER BY id DESC LIMIT 1");
                if ($q) {
                    $rows = $q->fetch_all(MYSQLI_ASSOC);
                    $last = $rows ? $rows[0] : null;
                }
            }
        } catch (Throwable $e) {
        }
        $this->render('import/last', array(
            'page_title' => 'Last Import Summary',
            'last' => $last,
        ));
    }
}
