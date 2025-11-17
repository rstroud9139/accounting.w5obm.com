<?php
class MappingService
{
    private $conn;
    private $dataDir;
    private $jsonPath;

    public function __construct($conn = null)
    {
        $this->conn = $conn;
        $this->dataDir = __DIR__ . '/../data';
        $this->jsonPath = $this->dataDir . '/category_offset_map.json';
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0777, true);
        }
    }

    private function tableExists($name)
    {
        if (!$this->conn) return false;
        $stmt = @$this->conn->prepare('SHOW TABLES LIKE ?');
        if (!$stmt) return false;
        $stmt->bind_param('s', $name);
        if (!$stmt->execute()) return false;
        $res = $stmt->get_result();
        return $res && $res->num_rows > 0;
    }

    public function getMap()
    {
        // Prefer DB table acc_category_account_map (category_id INT, offset_account_id INT)
        if ($this->tableExists('acc_category_account_map')) {
            $rows = array();
            $res = @$this->conn->query('SELECT category_id, offset_account_id FROM acc_category_account_map');
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[(int)$r['category_id']] = (int)$r['offset_account_id'];
                }
                return $rows;
            }
        }
        // Fallback to JSON file
        if (is_file($this->jsonPath)) {
            $txt = @file_get_contents($this->jsonPath);
            $arr = @json_decode($txt, true);
            return is_array($arr) ? $arr : array();
        }
        return array();
    }

    public function saveMap($map)
    {
        // Try DB first
        if ($this->tableExists('acc_category_account_map')) {
            // Replace all rows; simple approach
            @$this->conn->query('DELETE FROM acc_category_account_map');
            $stmt = @$this->conn->prepare('INSERT INTO acc_category_account_map (category_id, offset_account_id) VALUES (?, ?)');
            if ($stmt) {
                foreach ($map as $catId => $acctId) {
                    $ci = (int)$catId;
                    $ai = (int)$acctId;
                    $stmt->bind_param('ii', $ci, $ai);
                    $stmt->execute();
                }
                return true;
            }
        }
        // Fallback to JSON file
        $json = json_encode($map, JSON_PRETTY_PRINT);
        return @file_put_contents($this->jsonPath, $json) !== false;
    }

    public function suggestOffsetAccount($categoryId)
    {
        $map = $this->getMap();
        $cid = (int)$categoryId;
        return isset($map[$cid]) ? (int)$map[$cid] : 0;
    }
}
