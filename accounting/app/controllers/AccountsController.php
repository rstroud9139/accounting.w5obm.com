<?php
class AccountsController extends BaseController
{
    public function index()
    {
        require_once __DIR__ . '/../repositories/AccountRepository.php';
        $db = accounting_db_connection();
        $repo = new AccountRepository($db);
        $accounts = $repo->getAll();

        $this->render('accounts/index', [
            'page_title' => 'Chart of Accounts',
            'accounts' => $accounts,
        ]);
    }

    public function register()
    {
        requirePermission('accounting_view');
        $db = accounting_db_connection();
        $account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
        $start = isset($_GET['start']) ? $_GET['start'] : '';
        $end = isset($_GET['end']) ? $_GET['end'] : '';
        require_once __DIR__ . '/../repositories/JournalRepository.php';
        $jr = new JournalRepository($db);
        $lines = [];
        if ($account_id) {
            $lines = $jr->getRegisterLines($account_id, $start ?: null, $end ?: null);
        }
        require_once __DIR__ . '/../repositories/AccountRepository.php';
        $repo = new AccountRepository($db);
        $accounts = $repo->getAll();
        $this->render('accounts/register', [
            'page_title' => 'Account Register',
            'lines' => $lines,
            'accounts' => $accounts,
            'account_id' => $account_id,
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function register_export_csv()
    {
        requirePermission('accounting_view');
        $db = accounting_db_connection();
        $account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
        $start = isset($_GET['start']) ? $_GET['start'] : null;
        $end = isset($_GET['end']) ? $_GET['end'] : null;
        if (!$account_id) {
            http_response_code(400);
            echo 'Missing account_id';
            return;
        }
        require_once __DIR__ . '/../repositories/JournalRepository.php';
        $jr = new JournalRepository($db);
        $lines = $jr->getRegisterLines($account_id, $start, $end);
        // Fetch account name for header
        $account_name = '#' . $account_id;
        try {
            $res = $db->prepare("SELECT name FROM acc_ledger_accounts WHERE id = ? LIMIT 1");
            if ($res) {
                $res->bind_param('i', $account_id);
                $res->execute();
                $r = $res->get_result();
                if ($r && $r->num_rows) {
                    $row = $r->fetch_assoc();
                    $account_name = $row['name'] ?? $account_name;
                }
                $res->close();
            }
        } catch (Throwable $e) {
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="account_register_' . $account_id . '.csv"');
        $out = fopen('php://output', 'w');
        // Title rows
        fputcsv($out, ['Report', 'Account Register']);
        fputcsv($out, ['Account', $account_name . ' (#' . $account_id . ')']);
        if ($start || $end) {
            fputcsv($out, ['Period', ($start ?: '…') . ' to ' . ($end ?: '…')]);
        }
        fputcsv($out, ['Generated', date('Y-m-d H:i')]);
        fputcsv($out, ['']);
        // Header row
        fputcsv($out, ['Date', 'Description', 'Debit', 'Credit', 'Running Balance']);
        foreach ($lines as $ln) {
            fputcsv($out, [
                $ln['date'] ?? '',
                $ln['description'] ?? '',
                number_format((float)($ln['debit'] ?? 0), 2, '.', ''),
                number_format((float)($ln['credit'] ?? 0), 2, '.', ''),
                number_format((float)($ln['running_balance'] ?? 0), 2, '.', ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
