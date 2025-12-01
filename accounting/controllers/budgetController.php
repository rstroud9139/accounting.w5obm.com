<?php

/**
 * Budget Controller - W5OBM Accounting System
 * File: /accounting/controllers/budgetController.php
 * Provides CRUD helpers for budgets aligned to the chart of accounts.
 * Uses acc_budget_plans / acc_budget_plan_lines to avoid legacy collisions.
 */

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

function budget_require_connection(): mysqli
{
    $db = accounting_db_connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('Database connection unavailable for budget controller operations.');
    }
    return $db;
}

if (!function_exists('ensureBudgetTables')) {
    function ensureBudgetTables(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $db = budget_require_connection();

        $db->query(<<<SQL
            CREATE TABLE IF NOT EXISTS acc_budget_plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                fiscal_year SMALLINT NOT NULL,
                status ENUM('draft','approved','archived') NOT NULL DEFAULT 'draft',
                notes TEXT NULL,
                total_annual_amount DECIMAL(13,2) NOT NULL DEFAULT 0,
                created_by INT NULL,
                updated_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_fiscal_year (fiscal_year),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);

        $db->query(<<<SQL
            CREATE TABLE IF NOT EXISTS acc_budget_plan_lines (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                budget_id INT UNSIGNED NOT NULL,
                account_id INT NOT NULL,
                annual_amount DECIMAL(13,2) NOT NULL DEFAULT 0,
                monthly_plan LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_budget_account (budget_id, account_id),
                CONSTRAINT fk_budget_line_budget FOREIGN KEY (budget_id) REFERENCES acc_budget_plans(id) ON DELETE CASCADE,
                CONSTRAINT fk_budget_line_account FOREIGN KEY (account_id) REFERENCES acc_ledger_accounts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);

        $ensured = true;
    }
}

if (!function_exists('budgetStatuses')) {
    function budgetStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'archived' => 'Archived',
        ];
    }
}

if (!function_exists('fetchBudgets')) {
    function fetchBudgets(array $filters = []): array
    {
        ensureBudgetTables();
        $db = budget_require_connection();

        $where = [];
        $params = [];
        $types = '';

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'b.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['year'])) {
            $where[] = 'b.fiscal_year = ?';
            $params[] = (int)$filters['year'];
            $types .= 'i';
        }

        if (!empty($filters['search'])) {
            $where[] = '(b.name LIKE ? OR b.notes LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $types .= 'ss';
        }

        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $queryParts = [
            'SELECT b.*',
            '       COALESCE(SUM(l.annual_amount), 0) AS allocated_amount',
            '       COUNT(l.id) AS line_count',
            '       u.username AS created_by_name',
            '       uu.username AS updated_by_name',
            'FROM acc_budget_plans b',
            'LEFT JOIN acc_budget_plan_lines l ON b.id = l.budget_id',
            'LEFT JOIN w5obm.auth_users u ON b.created_by = u.id',
            'LEFT JOIN w5obm.auth_users uu ON b.updated_by = uu.id',
        ];

        if ($whereSql !== '') {
            $queryParts[] = $whereSql;
        }

        $queryParts[] = 'GROUP BY b.id';
        $queryParts[] = 'ORDER BY b.fiscal_year DESC, b.status ASC, b.name ASC';

        $query = implode("\n", $queryParts);

        $stmt = $db->prepare($query);
        if ($stmt === false) {
            return [];
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $budgets = [];
        while ($row = $result->fetch_assoc()) {
            $budgets[] = $row;
        }
        $stmt->close();

        return $budgets;
    }
}

if (!function_exists('getBudgetById')) {
    function getBudgetById(int $budgetId): ?array
    {
        ensureBudgetTables();
        $db = budget_require_connection();

        $stmt = $db->prepare('SELECT * FROM acc_budget_plans WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $budgetId);
        $stmt->execute();
        $budget = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$budget) {
            return null;
        }

        $budget['lines'] = fetchBudgetLines($budgetId);
        return $budget;
    }
}

if (!function_exists('fetchBudgetLines')) {
    function fetchBudgetLines(int $budgetId): array
    {
        ensureBudgetTables();
        $db = budget_require_connection();

        $stmt = $db->prepare('SELECT * FROM acc_budget_plan_lines WHERE budget_id = ?');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $budgetId);
        $stmt->execute();
        $result = $stmt->get_result();
        $lines = [];
        while ($row = $result->fetch_assoc()) {
            $lines[(int)$row['account_id']] = $row;
        }
        $stmt->close();

        return $lines;
    }
}

if (!function_exists('createBudget')) {
    function createBudget(array $data, array $lineItems): ?int
    {
        ensureBudgetTables();
        $db = budget_require_connection();

        $stmt = $db->prepare('INSERT INTO acc_budget_plans (name, fiscal_year, status, notes, total_annual_amount, created_by)
            VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return null;
        }

        $createdBy = getCurrentUserId();
        $total = array_sum(array_map('floatval', $lineItems));
        $notes = $data['notes'] ?? null;
        $stmt->bind_param(
            'sissdi',
            $data['name'],
            $data['fiscal_year'],
            $data['status'],
            $notes,
            $total,
            $createdBy
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $budgetId = $db->insert_id;
        $stmt->close();

        saveBudgetLines($budgetId, $lineItems);

        logActivity($createdBy, 'budget_created', 'acc_budget_plans', $budgetId, 'Created budget ' . $data['name']);

        return $budgetId;
    }
}

if (!function_exists('updateBudget')) {
    function updateBudget(int $budgetId, array $data, array $lineItems): bool
    {
        ensureBudgetTables();
        $db = budget_require_connection();

        $stmt = $db->prepare('UPDATE acc_budget_plans SET name = ?, fiscal_year = ?, status = ?, notes = ?, total_annual_amount = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return false;
        }

        $updatedBy = getCurrentUserId();
        $total = array_sum(array_map('floatval', $lineItems));
        $notes = $data['notes'] ?? null;
        $stmt->bind_param(
            'sissdii',
            $data['name'],
            $data['fiscal_year'],
            $data['status'],
            $notes,
            $total,
            $updatedBy,
            $budgetId
        );

        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            saveBudgetLines($budgetId, $lineItems);
            logActivity($updatedBy, 'budget_updated', 'acc_budget_plans', $budgetId, 'Updated budget ' . $data['name']);
        }

        return $result;
    }
}

if (!function_exists('deleteBudget')) {
    function deleteBudget(int $budgetId): bool
    {
        ensureBudgetTables();
        $db = budget_require_connection();

        $stmt = $db->prepare('DELETE FROM acc_budget_plans WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $budgetId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            logActivity(getCurrentUserId(), 'budget_deleted', 'acc_budget_plans', $budgetId, 'Deleted budget record');
        }

        return $result;
    }
}

if (!function_exists('saveBudgetLines')) {
    function saveBudgetLines(int $budgetId, array $lineItems): void
    {
        $db = budget_require_connection();

        $deleteStmt = $db->prepare('DELETE FROM acc_budget_plan_lines WHERE budget_id = ?');
        if ($deleteStmt) {
            $deleteStmt->bind_param('i', $budgetId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        if (empty($lineItems)) {
            return;
        }

        $insertStmt = $db->prepare('INSERT INTO acc_budget_plan_lines (budget_id, account_id, annual_amount)
            VALUES (?, ?, ?)');
        if (!$insertStmt) {
            return;
        }

        foreach ($lineItems as $accountId => $amount) {
            $cleanAmount = round((float)$amount, 2);
            if ($cleanAmount <= 0) {
                continue;
            }
            $insertStmt->bind_param('iid', $budgetId, $accountId, $cleanAmount);
            $insertStmt->execute();
        }

        $insertStmt->close();
    }
}

if (!function_exists('summarizeBudgetLines')) {
    function summarizeBudgetLines(array $lines): float
    {
        if (empty($lines)) {
            return 0.0;
        }
        return array_reduce($lines, static function ($carry, $line) {
            return $carry + (float)($line['annual_amount'] ?? 0);
        }, 0.0);
    }
}
