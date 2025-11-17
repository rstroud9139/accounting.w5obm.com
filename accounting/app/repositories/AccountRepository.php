<?php

class AccountRepository
{
    private $conn;
    private $hasLedger = false;
    private $hasChart = false;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->hasLedger = $this->tableExists('acc_ledger_accounts');
        $this->hasChart = $this->tableExists('acc_chart_of_accounts');
    }

    private function tableExists($table)
    {
        $stmt = $this->conn->prepare("SHOW TABLES LIKE ?");
        $stmt->bind_param('s', $table);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            return $res && $res->num_rows > 0;
        }
        return false;
    }

    public function getAll()
    {
        if ($this->hasLedger) {
            $sql = "SELECT id, account_number AS code, name, account_type AS type, parent_account_id AS parent_id, IFNULL(active,1) AS is_active
                    FROM acc_ledger_accounts
                    ORDER BY account_type, name";
        } elseif ($this->hasChart) {
            $sql = "SELECT id, code, name, type, parent_id, IFNULL(is_active,1) AS is_active
                    FROM acc_chart_of_accounts
                    ORDER BY type, name";
        } else {
            return [];
        }
        $res = $this->conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getById($id)
    {
        if ($this->hasLedger) {
            $stmt = $this->conn->prepare("SELECT id, account_number AS code, name, account_type AS type, parent_account_id AS parent_id, IFNULL(active,1) AS is_active
                                          FROM acc_ledger_accounts WHERE id = ?");
        } elseif ($this->hasChart) {
            $stmt = $this->conn->prepare("SELECT id, code, name, type, parent_id, IFNULL(is_active,1) AS is_active
                                          FROM acc_chart_of_accounts WHERE id = ?");
        } else {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($code, $name, $type, $parent_id = null)
    {
        if ($this->hasLedger) {
            $stmt = $this->conn->prepare("INSERT INTO acc_ledger_accounts (account_number, name, account_type, parent_account_id, active)
                                          VALUES (?, ?, ?, ?, 1)");
        } elseif ($this->hasChart) {
            $stmt = $this->conn->prepare("INSERT INTO acc_chart_of_accounts (code, name, type, parent_id, is_active)
                                          VALUES (?, ?, ?, ?, 1)");
        } else {
            return false;
        }
        if ($parent_id !== null) {
            $stmt->bind_param('sssi', $code, $name, $type, $parent_id);
        } else {
            $null = null;
            $stmt->bind_param('sssi', $code, $name, $type, $null);
        }
        return $stmt->execute();
    }

    public function update($id, $code, $name, $type, $parent_id = null, $is_active = 1)
    {
        if ($this->hasLedger) {
            $stmt = $this->conn->prepare("UPDATE acc_ledger_accounts
                                          SET account_number = ?, name = ?, account_type = ?, parent_account_id = ?, active = ?
                                          WHERE id = ?");
        } elseif ($this->hasChart) {
            $stmt = $this->conn->prepare("UPDATE acc_chart_of_accounts
                                          SET code = ?, name = ?, type = ?, parent_id = ?, is_active = ?
                                          WHERE id = ?");
        } else {
            return false;
        }
        if ($parent_id !== null) {
            $stmt->bind_param('sssiii', $code, $name, $type, $parent_id, $is_active, $id);
        } else {
            $null = null;
            $stmt->bind_param('sssiii', $code, $name, $type, $null, $is_active, $id);
        }
        return $stmt->execute();
    }

    public function deactivate($id)
    {
        if ($this->hasLedger) {
            $stmt = $this->conn->prepare("UPDATE acc_ledger_accounts SET active = 0 WHERE id = ?");
        } elseif ($this->hasChart) {
            $stmt = $this->conn->prepare("UPDATE acc_chart_of_accounts SET is_active = 0 WHERE id = ?");
        } else {
            return false;
        }
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}
