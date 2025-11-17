<?php
class BatchReportsController extends BaseController
{
    private function getConfig()
    {
        $file = __DIR__ . '/../../reports/batches.php';
        if (file_exists($file)) {
            $cfg = include $file;
            if (is_array($cfg)) return $cfg;
        }
        return array();
    }

    public function index()
    {
        $cfg = $this->getConfig();
        $this->render('reports/batch_index', array(
            'page_title' => 'Batch Reports',
            'batches' => $cfg,
            'year' => (int)date('Y'),
            'month' => (int)date('n'),
            'csrf' => getCsrfToken(),
        ));
    }

    public function run()
    {
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        $cfg = $this->getConfig();
        $batchKey = isset($_POST['batch']) ? $_POST['batch'] : 'monthly';
        if (!isset($cfg[$batchKey])) {
            $this->render('reports/batch_index', array(
                'page_title' => 'Batch Reports',
                'batches' => $cfg,
                'error' => 'Unknown batch selection',
                'year' => (int)date('Y'),
                'month' => (int)date('n'),
                'csrf' => getCsrfToken(),
            ));
            return;
        }
        $year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
        $month = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('n');

        // Compute date ranges
        $start = null;
        $end = null;
        $batchType = $batchKey;
        if ($batchType === 'monthly') {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));
        } elseif ($batchType === 'ytd') {
            $start = sprintf('%04d-01-01', $year);
            $end = date('Y-m-d');
        } else { // annual
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-12-31', $year);
        }

        $reports = array();
        foreach ($cfg[$batchKey]['reports'] as $r) {
            $url = $r['url'];
            $url = str_replace('{year}', $year, $url);
            $url = str_replace('{month}', $month, $url);
            $url = str_replace('{start}', $start, $url);
            $url = str_replace('{end}', $end, $url);
            $reports[] = array(
                'label' => $r['label'],
                'url' => $url,
            );
        }

        $this->render('reports/batch_run', array(
            'page_title' => 'Run Batch: ' . $cfg[$batchKey]['name'],
            'batch' => $cfg[$batchKey],
            'reports' => $reports,
            'year' => $year,
            'month' => $month,
        ));
    }
}
