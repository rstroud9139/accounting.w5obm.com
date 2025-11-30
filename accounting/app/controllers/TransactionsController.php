<?php
class TransactionsController extends BaseController
{
    public function index()
    {
        require_once __DIR__ . '/../repositories/TransactionRepository.php';
        $db = accounting_db_connection();
        $repo = new TransactionRepository($db);
        // Filters from GET
        $filters = array();
        if (!empty($_GET['start']) && !empty($_GET['end'])) {
            $filters['start_date'] = $_GET['start'];
            $filters['end_date'] = $_GET['end'];
        }
        if (!empty($_GET['type'])) $filters['type'] = $_GET['type'];
        if (!empty($_GET['category_id'])) $filters['category_id'] = (int)$_GET['category_id'];
        if (!empty($_GET['q'])) $filters['q'] = $_GET['q'];
        $tx = !empty($filters) ? $repo->getByFilters($filters) : $repo->getRecent(200);

        // Load categories for filter dropdown
        $cats = array();
        try {
            $r = $db->query("SELECT id, name FROM acc_transaction_categories ORDER BY name");
            if ($r) $cats = $r->fetch_all(MYSQLI_ASSOC);
        } catch (Throwable $e) {
        }

        $this->render('transactions/index', [
            'page_title' => 'Transactions',
            'transactions' => $tx,
            'filters' => $filters,
            'categories' => $cats,
        ]);
    }

    public function new()
    {
        // Quick entry form for a single-split Income/Expense or Transfer
        $db = accounting_db_connection();
        // Load accounts and categories
        $accounts = [];
        try {
            $res = $db->query("SHOW TABLES LIKE 'acc_ledger_accounts'");
            if ($res && $res->num_rows > 0) {
                $res2 = $db->query("SELECT id, name, account_type AS type FROM acc_ledger_accounts WHERE active = 1 ORDER BY account_type, name");
                $accounts = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
            } else {
                $res2 = $db->query("SELECT id, name, type FROM acc_chart_of_accounts WHERE IFNULL(is_active,1)=1 ORDER BY type, name");
                $accounts = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
            }
        } catch (Throwable $e) {
        }
        $cats = [];
        try {
            $res = $db->query("SELECT id, name, type FROM acc_transaction_categories WHERE active IS NULL OR active=1 ORDER BY name");
            if ($res) {
                $cats = $res->fetch_all(MYSQLI_ASSOC);
            }
        } catch (Throwable $e) {
        }
        $this->render('transactions/new', [
            'page_title' => 'New Transaction',
            'accounts' => $accounts,
            'categories' => $cats,
            'csrf' => getCsrfToken(),
        ]);
    }

    public function create()
    {
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        require_once __DIR__ . '/../repositories/TransactionRepository.php';
        $repo = new TransactionRepository(accounting_db_connection());
        $type = $_POST['type'] ?? 'Expense';
        $date = $_POST['transaction_date'] ?? date('Y-m-d');
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
        $desc = $_POST['description'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $cash = isset($_POST['cash_account_id']) ? (int)$_POST['cash_account_id'] : 0;
        $offset = isset($_POST['offset_account_id']) ? (int)$_POST['offset_account_id'] : 0;
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        if ($type === 'Transfer') {
            $category_id = null;
            // Require both accounts and they must differ
            if ($cash <= 0 || $offset <= 0) {
                $this->render('transactions/new', [
                    'page_title' => 'New Transaction',
                    'error' => 'For a Transfer, select both source and destination accounts.',
                    'csrf' => getCsrfToken(),
                ]);
                return;
            }
            if ($cash === $offset) {
                $this->render('transactions/new', [
                    'page_title' => 'New Transaction',
                    'error' => 'Source and destination accounts cannot be the same for a Transfer.',
                    'csrf' => getCsrfToken(),
                ]);
                return;
            }
        }
        if ($type !== 'Transfer' && $offset <= 0) {
            $this->render('transactions/new', [
                'page_title' => 'New Transaction',
                'error' => 'Select an offset account for Income/Expense.',
                'csrf' => getCsrfToken(),
            ]);
            return;
        }
        if ($type !== 'Transfer' && $cash > 0 && $offset > 0 && $cash === $offset) {
            $this->render('transactions/new', [
                'page_title' => 'New Transaction',
                'error' => 'Cash/Bank and offset accounts must be different.',
                'csrf' => getCsrfToken(),
            ]);
            return;
        }
        $payload = [
            'transaction_date' => $date,
            'type' => $type,
            'category_id' => $category_id,
            'amount' => abs($amount),
            'description' => $desc,
            'notes' => $notes,
            'reference_number' => '',
            'cash_account_id' => $cash ?: null,
            'offset_account_id' => $offset ?: null,
        ];
        $id = $repo->createWithPosting($payload, []);
        if ($id) {
            header('Location: ' . route('transactions') . '&success=' . urlencode('Transaction saved'));
            return;
        }
        $this->render('transactions/new', [
            'page_title' => 'New Transaction',
            'error' => 'Failed to save transaction',
            'csrf' => getCsrfToken(),
        ]);
    }

    public function export_csv()
    {
        requirePermission('accounting_view');
        require_once __DIR__ . '/../repositories/TransactionRepository.php';
        $repo = new TransactionRepository(accounting_db_connection());
        $filters = array();
        if (!empty($_GET['start']) && !empty($_GET['end'])) {
            $filters['start_date'] = $_GET['start'];
            $filters['end_date'] = $_GET['end'];
        }
        if (!empty($_GET['type'])) $filters['type'] = $_GET['type'];
        if (!empty($_GET['category_id'])) $filters['category_id'] = (int)$_GET['category_id'];
        if (!empty($_GET['q'])) $filters['q'] = $_GET['q'];
        $tx = !empty($filters) ? $repo->getByFilters($filters) : $repo->getRecent(1000);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="transactions.csv"');
        $out = fopen('php://output', 'w');
        // Title rows
        fputcsv($out, ['Report', 'Transactions']);
        fputcsv($out, ['Generated', date('Y-m-d H:i')]);
        if (!empty($filters)) {
            $flt = [];
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) $flt[] = 'Period=' . $filters['start_date'] . ' to ' . $filters['end_date'];
            if (!empty($filters['type'])) $flt[] = 'Type=' . $filters['type'];
            if (!empty($filters['category_id'])) $flt[] = 'CategoryID=' . (int)$filters['category_id'];
            if (!empty($filters['q'])) $flt[] = 'q=' . $filters['q'];
            fputcsv($out, ['Filters', implode('; ', $flt)]);
        }
        fputcsv($out, ['']);
        // Header row
        fputcsv($out, ['Date', 'Type', 'Category', 'Description', 'Amount']);
        foreach ($tx as $t) {
            fputcsv($out, [
                $t['transaction_date'],
                $t['type'],
                $t['category_name'] ?? '',
                $t['description'] ?? '',
                number_format((float)$t['amount'], 2, '.', ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
