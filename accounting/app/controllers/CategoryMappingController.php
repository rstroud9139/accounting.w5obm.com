<?php
class CategoryMappingController extends BaseController
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
        $db = accounting_db_connection();
        try {
            $res = $db->query("SHOW TABLES LIKE 'acc_ledger_accounts'");
            if ($res && $res->num_rows > 0) {
                $res2 = $db->query("SELECT id, name, type FROM acc_ledger_accounts ORDER BY type, name");
                return $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : array();
            }
            $res = $db->query("SHOW TABLES LIKE 'acc_chart_of_accounts'");
            if ($res && $res->num_rows > 0) {
                $res2 = $db->query("SELECT id, name, type FROM acc_chart_of_accounts ORDER BY type, name");
                return $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : array();
            }
        } catch (Throwable $e) {
        }
        return array();
    }

    public function index()
    {
        require_once __DIR__ . '/../services/MappingService.php';
        $db = accounting_db_connection();
        $svc = new MappingService($db);
        $this->render('mapping/index', array(
            'page_title' => 'Category → Default Offset Account',
            'categories' => $this->getCategories(),
            'accounts' => $this->getAccounts(),
            'map' => $svc->getMap(),
            'csrf' => getCsrfToken(),
        ));
    }

    public function save()
    {
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        $pairs = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : array();
        $map = array();
        foreach ($pairs as $catId => $acctId) {
            $ci = (int)$catId;
            $ai = (int)$acctId;
            if ($ci > 0 && $ai > 0) {
                $map[$ci] = $ai;
            }
        }
        require_once __DIR__ . '/../services/MappingService.php';
        $db = accounting_db_connection();
        $svc = new MappingService($db);
        $ok = $svc->saveMap($map);
        $this->render('mapping/index', array(
            'page_title' => 'Category → Default Offset Account',
            'categories' => $this->getCategories(),
            'accounts' => $this->getAccounts(),
            'map' => $svc->getMap(),
            'csrf' => getCsrfToken(),
            'success' => $ok ? 'Mappings saved' : 'Failed to save mappings (check file permissions or DB table)'
        ));
    }

    public function saveInline()
    {
        // Save provided pairs, then return to import preview if available
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        $pairs = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : array();
        if (empty($pairs)) {
            header('Location: ' . route('import'));
            return;
        }
        require_once __DIR__ . '/../services/MappingService.php';
        $db = accounting_db_connection();
        $svc = new MappingService($db);
        // Merge with existing map
        $current = $svc->getMap();
        foreach ($pairs as $catId => $acctId) {
            $ci = (int)$catId;
            $ai = (int)$acctId;
            if ($ci > 0 && $ai > 0) {
                $current[$ci] = $ai;
            }
        }
        $svc->saveMap($current);

        // If we have a staged preview, re-render it with a success message
        $data = $_SESSION['import_preview'] ?? null;
        if ($data && !empty($data['items'])) {
            // Bring in helper data
            // Duplicated simple fetches from ImportController
            $categories = array();
            $accounts = array();
            try {
                $res = $db->query("SELECT id, name FROM acc_transaction_categories ORDER BY name");
                if ($res) {
                    $categories = $res->fetch_all(MYSQLI_ASSOC);
                }
            } catch (Throwable $e) {
            }
            try {
                $res = $db->query("SHOW TABLES LIKE 'acc_ledger_accounts'");
                if ($res && $res->num_rows > 0) {
                    $res2 = $db->query("SELECT id, name, type FROM acc_ledger_accounts ORDER BY type, name");
                    $accounts = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : array();
                } else {
                    $res = $db->query("SHOW TABLES LIKE 'acc_chart_of_accounts'");
                    if ($res && $res->num_rows > 0) {
                        $res2 = $db->query("SELECT id, name, type FROM acc_chart_of_accounts ORDER BY type, name");
                        $accounts = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : array();
                    }
                }
            } catch (Throwable $e) {
            }
            $this->render('import/preview', array(
                'page_title' => 'Preview Import',
                'items' => $data['items'],
                'categories' => $categories,
                'accounts' => $accounts,
                'category_map' => $svc->getMap(),
                'defaults' => $data['defaults'],
                'success' => 'Mappings saved. Preview updated with latest mapping.',
                'csrf' => getCsrfToken()
            ));
            return;
        }
        header('Location: ' . route('category_map'));
    }
}
