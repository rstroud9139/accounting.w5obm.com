<?php

require_once __DIR__ . '/helpers.php';

if (!function_exists('accountingEnsureReportGroupingTables')) {
    function accountingEnsureReportGroupingTables(mysqli $conn): void
    {
        $tables = [
            "CREATE TABLE IF NOT EXISTS acc_report_groups (\n                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                name VARCHAR(120) NOT NULL,\n                group_type VARCHAR(20) NOT NULL,\n                description TEXT NULL,\n                created_by INT NULL,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS acc_report_group_items (\n                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                group_id INT UNSIGNED NOT NULL,\n                catalog_key VARCHAR(120) NOT NULL,\n                position INT UNSIGNED NOT NULL DEFAULT 0,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                UNIQUE KEY idx_group_item (group_id, catalog_key),\n                INDEX idx_group_id (group_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($tables as $sql) {
            if (!$conn->query($sql)) {
                error_log('Failed to ensure report grouping table: ' . $conn->error);
            }
        }
    }
}

if (!function_exists('accountingGetReportGroupById')) {
    function accountingGetReportGroupById(mysqli $conn, int $groupId): ?array
    {
        $stmt = $conn->prepare('SELECT * FROM acc_report_groups WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        $stmt->bind_result($id, $name, $groupType, $description, $createdBy, $createdAt, $updatedAt);
        $row = null;
        if ($stmt->fetch()) {
            $row = [
                'id' => $id,
                'name' => $name,
                'group_type' => $groupType,
                'description' => $description,
                'created_by' => $createdBy,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }
        $stmt->close();

        return $row;
    }
}

if (!function_exists('accountingFilterCatalogKeysByType')) {
    function accountingFilterCatalogKeysByType(array $keys, array $catalog, string $groupType): array
    {
        $groupType = strtolower(trim($groupType));
        $keys = array_unique(array_map(static function ($value) {
            return strtolower(trim((string)$value));
        }, $keys));

        return array_values(array_filter($keys, static function ($key) use ($catalog, $groupType) {
            return isset($catalog[$key]) && in_array($groupType, $catalog[$key]['supports'] ?? [], true);
        }));
    }
}

if (!function_exists('accountingAddReportsToGroup')) {
    function accountingAddReportsToGroup(mysqli $conn, int $groupId, array $reportKeys, array $catalog, string $groupType): array
    {
        $validKeys = accountingFilterCatalogKeysByType($reportKeys, $catalog, $groupType);
        if (empty($validKeys)) {
            return [
                'success' => false,
                'message' => 'No compatible reports selected for this grouping option.',
                'added' => 0,
            ];
        }

        $maxPosition = 0;
        $positionResult = $conn->query('SELECT COALESCE(MAX(position), 0) as max_pos FROM acc_report_group_items WHERE group_id = ' . (int)$groupId);
        if ($positionResult) {
            $positionRow = $positionResult->fetch_assoc();
            $maxPosition = (int)($positionRow['max_pos'] ?? 0);
        }

        $checkStmt = $conn->prepare('SELECT id FROM acc_report_group_items WHERE group_id = ? AND catalog_key = ? LIMIT 1');
        $insertStmt = $conn->prepare('INSERT INTO acc_report_group_items (group_id, catalog_key, position, created_at) VALUES (?, ?, ?, NOW())');

        if (!$checkStmt || !$insertStmt) {
            return [
                'success' => false,
                'message' => 'Unable to prepare statements for group updates.',
                'added' => 0,
            ];
        }

        $inserted = 0;
        foreach ($validKeys as $key) {
            $checkStmt->bind_param('is', $groupId, $key);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $checkStmt->free_result();
                continue;
            }
            $checkStmt->free_result();

            $maxPosition++;
            $positionValue = $maxPosition;
            $keyValue = $key;
            $insertStmt->bind_param('isi', $groupId, $keyValue, $positionValue);
            if ($insertStmt->execute()) {
                $inserted++;
            }
        }

        $checkStmt->close();
        $insertStmt->close();

        return [
            'success' => $inserted > 0,
            'message' => $inserted > 0
                ? sprintf('Added %d item%s to the group.', $inserted, $inserted === 1 ? '' : 's')
                : 'All selected reports were already members of this group.',
            'added' => $inserted,
        ];
    }
}

if (!function_exists('accountingRemoveReportFromGroup')) {
    function accountingRemoveReportFromGroup(mysqli $conn, int $groupId, string $catalogKey): array
    {
        $catalogKey = strtolower(trim($catalogKey));
        $stmt = $conn->prepare('DELETE FROM acc_report_group_items WHERE group_id = ? AND catalog_key = ?');
        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Unable to prepare removal statement.',
            ];
        }

        $stmt->bind_param('is', $groupId, $catalogKey);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            return [
                'success' => true,
                'message' => 'Report removed from the group.',
            ];
        }

        return [
            'success' => false,
            'message' => 'The selected report was not part of this group.',
        ];
    }
}

if (!function_exists('accountingHandleCreateReportGroup')) {
    function accountingHandleCreateReportGroup(mysqli $conn, int $userId, array $input, array $catalog): array
    {
        $name = trim($input['group_name'] ?? '');
        $groupType = strtolower(trim($input['group_type'] ?? ''));
        $description = trim($input['group_description'] ?? '');
        $allowedTypes = ['monthly', 'ytd', 'annual'];

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'Please provide a name for the group.',
            ];
        }

        if (!in_array($groupType, $allowedTypes, true)) {
            return [
                'success' => false,
                'message' => 'Invalid grouping option selected.',
            ];
        }

        $stmt = $conn->prepare('INSERT INTO acc_report_groups (name, group_type, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Unable to create the report group.',
            ];
        }

        $descValue = $description !== '' ? $description : null;
        $stmt->bind_param('sssi', $name, $groupType, $descValue, $userId);
        $stmt->execute();
        $groupId = (int)$stmt->insert_id;
        $stmt->close();

        $selectedKeys = isset($input['group_reports']) && is_array($input['group_reports']) ? $input['group_reports'] : [];
        $addedResult = null;
        if (!empty($selectedKeys)) {
            $addedResult = accountingAddReportsToGroup($conn, $groupId, $selectedKeys, $catalog, $groupType);
        }

        $message = 'New group created successfully.';
        if ($addedResult && ($addedResult['added'] ?? 0) > 0) {
            $message = sprintf('Group created and %d report%s added.', $addedResult['added'], $addedResult['added'] === 1 ? '' : 's');
        }

        return [
            'success' => true,
            'message' => $message,
        ];
    }
}

if (!function_exists('accountingFetchReportGroups')) {
    function accountingFetchReportGroups(mysqli $conn, array $catalog): array
    {
        $groups = [];
        $groupResult = $conn->query('SELECT * FROM acc_report_groups ORDER BY created_at DESC');
        if ($groupResult) {
            while ($row = $groupResult->fetch_assoc()) {
                $row['items'] = [];
                $groups[(int)$row['id']] = $row;
            }
        }

        if (empty($groups)) {
            return [];
        }

        $groupIds = implode(',', array_keys($groups));
        $itemsResult = $conn->query('SELECT * FROM acc_report_group_items WHERE group_id IN (' . $groupIds . ') ORDER BY position ASC, id ASC');
        if ($itemsResult) {
            while ($item = $itemsResult->fetch_assoc()) {
                $groupId = (int)$item['group_id'];
                if (!isset($groups[$groupId])) {
                    continue;
                }
                $key = $item['catalog_key'];
                $item['catalog'] = $catalog[$key] ?? null;
                $groups[$groupId]['items'][] = $item;
            }
        }

        foreach ($groups as &$group) {
            $group['item_count'] = count($group['items']);
            $group['item_keys'] = array_map(static function ($item) {
                return $item['catalog_key'];
            }, $group['items']);

            $group['eligible_items'] = array_values(array_filter($catalog, static function ($item) use ($group) {
                return in_array($group['group_type'], $item['supports'] ?? [], true) && !in_array($item['key'], $group['item_keys'], true);
            }));
        }

        return array_values($groups);
    }
}

if (!function_exists('accountingEnsureDefaultReportGroups')) {
    function accountingEnsureDefaultReportGroups(mysqli $conn, array $catalog, ?int $userId = null): array
    {
        $defaults = [
            [
                'name' => 'Monthly Board Packet',
                'group_type' => 'monthly',
                'description' => 'Cash, income, and expense snapshot for leadership huddles.',
                'items' => [
                    'monthly_summary',
                    'income_statement',
                    'expense_report',
                    'cash_account_report',
                    'sources_uses',
                    'monthly_income_report',
                ],
            ],
            [
                'name' => 'YTD Leadership Brief',
                'group_type' => 'ytd',
                'description' => 'Trend-focused packet covering budget, cash, and income.',
                'items' => [
                    'ytd_income_statement',
                    'ytd_budget_comparison',
                    'ytd_cash_flow',
                    'ytd_income_report',
                    'ytd_cash_report',
                    'ytd_income_statement_monthly',
                    'ytd_cash_flow_monthly',
                ],
            ],
            [
                'name' => 'Annual Stewardship Packet',
                'group_type' => 'annual',
                'description' => 'Compliance-ready bundle for donors and record retention.',
                'items' => [
                    'balance_sheet',
                    'cash_flow',
                    'physical_assets_report',
                    'asset_listing',
                    'donor_list',
                    'donation_summary',
                    'donation_receipts',
                    'annual_donor_statement',
                    'financial_statements_portal',
                ],
            ],
        ];

        $summary = [
            'created' => 0,
            'items_added' => 0,
        ];

        foreach ($defaults as $preset) {
            $groupType = $preset['group_type'];
            $validItems = accountingFilterCatalogKeysByType($preset['items'], $catalog, $groupType);
            if (empty($validItems)) {
                continue;
            }

            $groupId = null;
            $lookupStmt = $conn->prepare('SELECT id FROM acc_report_groups WHERE name = ? AND group_type = ? LIMIT 1');
            if ($lookupStmt) {
                $lookupStmt->bind_param('ss', $preset['name'], $groupType);
                $lookupStmt->execute();
                $lookupStmt->bind_result($existingId);
                if ($lookupStmt->fetch()) {
                    $groupId = (int)$existingId;
                }
                $lookupStmt->close();
            }

            if (!$groupId) {
                $insertStmt = $conn->prepare('INSERT INTO acc_report_groups (name, group_type, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
                if ($insertStmt) {
                    $description = $preset['description'] ?? '';
                    $creatorId = ($userId && $userId > 0) ? $userId : 0;
                    $insertStmt->bind_param('sssi', $preset['name'], $groupType, $description, $creatorId);
                    if ($insertStmt->execute()) {
                        $groupId = (int)$insertStmt->insert_id;
                        $summary['created']++;
                    }
                    $insertStmt->close();
                }
            }

            if ($groupId) {
                $addResult = accountingAddReportsToGroup($conn, $groupId, $validItems, $catalog, $groupType);
                $summary['items_added'] += $addResult['added'] ?? 0;
            }
        }

        return $summary;
    }
}
