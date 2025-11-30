<?php
class MigrationController extends BaseController
{
    private function getMigrationsPath()
    {
        $candidates = array(
            __DIR__ . '/../../../sql/migrations',
            __DIR__ . '/../../sql/migrations',
        );
        foreach ($candidates as $p) {
            if (is_dir($p)) return $p;
        }
        return null;
    }

    private function ensureMigrationsTable($conn)
    {
        $sql = "CREATE TABLE IF NOT EXISTS acc_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            checksum VARCHAR(64) NOT NULL,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        @$conn->query($sql);
    }

    private function listFiles($path)
    {
        $files = array();
        $d = @opendir($path);
        if ($d) {
            while (($f = readdir($d)) !== false) {
                if ($f === '.' || $f === '..') continue;
                if (substr($f, -4) === '.sql') {
                    $files[] = $f;
                }
            }
            closedir($d);
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function getApplied($conn)
    {
        $applied = array();
        try {
            $res = $conn->query('SELECT filename, checksum, applied_at FROM acc_migrations');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $applied[$row['filename']] = $row;
                }
            }
        } catch (Throwable $e) {
        }
        return $applied;
    }

    public function index()
    {
        requirePermission('accounting_manage');
        $db = accounting_db_connection();
        $this->ensureMigrationsTable($db);
        $path = $this->getMigrationsPath();
        $files = $path ? $this->listFiles($path) : array();
        $applied = $this->getApplied($db);

        $items = array();
        foreach ($files as $f) {
            $full = $path . DIRECTORY_SEPARATOR . $f;
            $checksum = is_file($full) ? sha1_file($full) : '';
            $items[] = array(
                'filename' => $f,
                'checksum' => $checksum,
                'applied' => isset($applied[$f]) ? $applied[$f]['applied_at'] : null,
                'matches' => isset($applied[$f]) ? ($applied[$f]['checksum'] === $checksum) : null,
            );
        }
        $this->render('migrations/index', array(
            'page_title' => 'Migrations',
            'items' => $items,
            'path' => $path,
            'csrf' => getCsrfToken()
        ));
    }

    public function run()
    {
        requirePermission('accounting_manage');
        if (!verifyPostCsrf()) {
            http_response_code(400);
            echo 'Invalid CSRF token';
            return;
        }
        $db = accounting_db_connection();
        $this->ensureMigrationsTable($db);
        $path = $this->getMigrationsPath();
        if (!$path) {
            $this->render('migrations/index', array(
                'page_title' => 'Migrations',
                'items' => array(),
                'error' => 'Migrations folder not found.',
                'csrf' => getCsrfToken()
            ));
            return;
        }

        $files = $this->listFiles($path);
        $applied = $this->getApplied($db);
        $ran = 0;
        foreach ($files as $f) {
            if (isset($applied[$f])) continue; // skip already applied
            $full = $path . DIRECTORY_SEPARATOR . $f;
            $sql = @file_get_contents($full);
            if ($sql === false) continue;
            // Execute multi-query to support multiple statements
            if ($db->multi_query($sql)) {
                while ($db->more_results() && $db->next_result()) {
                    // flush results
                    $tmp = $db->use_result();
                    if ($tmp instanceof mysqli_result) {
                        $tmp->free();
                    }
                }
                $checksum = sha1($sql);
                $stmt = $db->prepare('INSERT INTO acc_migrations (filename, checksum, applied_at) VALUES (?,?,NOW())');
                if ($stmt) {
                    $stmt->bind_param('ss', $f, $checksum);
                    $stmt->execute();
                    $stmt->close();
                }
                $ran++;
            }
        }
        $msg = $ran . ' migration(s) applied.';
        header('Location: ' . route('migrations') . '&success=' . urlencode($msg));
    }
}
