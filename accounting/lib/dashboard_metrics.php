<?php

require_once __DIR__ . '/helpers.php';

if (!function_exists('accounting_table_exists')) {
    function accounting_table_exists(mysqli $connection, string $table): bool
    {
        $safeTable = $connection->real_escape_string($table);
        $result = $connection->query("SHOW TABLES LIKE '{$safeTable}'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('accounting_years_since')) {
    function accounting_years_since(?string $date): float
    {
        if (empty($date)) {
            return 0.0;
        }

        try {
            $start = new DateTime($date);
            $now = new DateTime();
            $interval = $start->diff($now);
            return $interval->y + ($interval->m / 12) + ($interval->d / 365);
        } catch (Exception $e) {
            logError('Dashboard metrics date error: ' . $e->getMessage(), 'accounting');
            return 0.0;
        }
    }
}

if (!function_exists('accounting_collect_dashboard_metrics')) {
    function accounting_collect_dashboard_metrics(?mysqli $ledgerConn, ?mysqli $reportConn = null, int $recentLimit = 25): array
    {
        $metrics = [
            'cash_balance' => 0.0,
            'asset_value' => 0.0,
            'asset_summary' => [
                'count' => 0,
                'book_total' => 0.0,
                'current_total' => 0.0,
                'depreciable_count' => 0,
                'avg_age_years' => 0.0,
            ],
            'month_totals' => ['income' => 0.0, 'expenses' => 0.0, 'net_balance' => 0.0],
            'ytd_totals' => ['income' => 0.0, 'expenses' => 0.0, 'net_balance' => 0.0],
            'recent_transactions' => [],
            'reports' => [
                'total' => 0,
                'recent_30' => 0,
                'last_generated_at' => null,
            ],
        ];

        if ($ledgerConn instanceof mysqli) {
            $hasAccounts = accounting_table_exists($ledgerConn, 'acc_accounts');
            $hasTransactions = accounting_table_exists($ledgerConn, 'acc_transactions');
            $hasCategories = accounting_table_exists($ledgerConn, 'acc_categories');
            $hasAssets = accounting_table_exists($ledgerConn, 'acc_assets');

            if ($hasAccounts) {
                $cashQuery = $ledgerConn->query("SELECT COALESCE(SUM(current_balance), 0) as total FROM acc_accounts WHERE account_type = 'Asset' AND (account_name LIKE '%Cash%' OR account_name LIKE '%Bank%')");
                if ($cashQuery && ($row = $cashQuery->fetch_assoc())) {
                    $metrics['cash_balance'] = (float)$row['total'];
                }

                $assetQuery = $ledgerConn->query("SELECT COALESCE(SUM(current_balance), 0) as total FROM acc_accounts WHERE account_type = 'Asset'");
                if ($assetQuery && ($row = $assetQuery->fetch_assoc())) {
                    $metrics['asset_value'] = (float)$row['total'];
                }
            }

            if ($hasTransactions) {
                $currentMonth = date('Y-m');
                $monthQuery = $ledgerConn->query("SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expenses FROM acc_transactions WHERE DATE_FORMAT(transaction_date, '%Y-%m') = '{$currentMonth}'");
                if ($monthQuery && ($row = $monthQuery->fetch_assoc())) {
                    $metrics['month_totals']['income'] = (float)$row['income'];
                    $metrics['month_totals']['expenses'] = (float)$row['expenses'];
                    $metrics['month_totals']['net_balance'] = $metrics['month_totals']['income'] - $metrics['month_totals']['expenses'];
                }

                $currentYear = date('Y');
                $ytdQuery = $ledgerConn->query("SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expenses FROM acc_transactions WHERE YEAR(transaction_date) = {$currentYear}");
                if ($ytdQuery && ($row = $ytdQuery->fetch_assoc())) {
                    $metrics['ytd_totals']['income'] = (float)$row['income'];
                    $metrics['ytd_totals']['expenses'] = (float)$row['expenses'];
                    $metrics['ytd_totals']['net_balance'] = $metrics['ytd_totals']['income'] - $metrics['ytd_totals']['expenses'];
                }

                $categorySelect = $hasCategories ? 'c.name as category_name' : 'NULL as category_name';
                $categoryJoin = $hasCategories ? 'LEFT JOIN acc_categories c ON t.category_id = c.id' : '';
                $txQuery = $ledgerConn->query("SELECT t.id, t.transaction_date, t.description, t.amount, t.type, {$categorySelect} FROM acc_transactions t {$categoryJoin} ORDER BY t.transaction_date DESC, t.id DESC LIMIT " . (int)max(1, $recentLimit));
                if ($txQuery) {
                    while ($row = $txQuery->fetch_assoc()) {
                        $metrics['recent_transactions'][] = $row;
                    }
                }
            }

            if ($hasAssets) {
                $assetSummary = $metrics['asset_summary'];
                $totalAgeYears = 0.0;
                $assetResult = $ledgerConn->query("SELECT id, value, acquisition_date, depreciation_rate FROM acc_assets");
                if ($assetResult) {
                    while ($row = $assetResult->fetch_assoc()) {
                        $value = (float)($row['value'] ?? 0);
                        $assetSummary['count'] += 1;
                        $assetSummary['book_total'] += $value;

                        $ratePercent = max(0.0, (float)($row['depreciation_rate'] ?? 0));
                        $rate = $ratePercent > 0 ? $ratePercent / 100 : 0.0;
                        $years = accounting_years_since($row['acquisition_date'] ?? null);
                        $currentValue = $value;
                        if ($rate > 0 && $years > 0) {
                            $currentValue = max(0.0, $value * pow(1 - $rate, $years));
                        }
                        $assetSummary['current_total'] += $currentValue;

                        if ($ratePercent > 0) {
                            $assetSummary['depreciable_count'] += 1;
                        }

                        if ($years > 0) {
                            $totalAgeYears += $years;
                        }
                    }
                    $assetResult->close();
                }

                if ($assetSummary['count'] > 0) {
                    $assetSummary['avg_age_years'] = $totalAgeYears / $assetSummary['count'];
                }

                $metrics['asset_summary'] = $assetSummary;
            }
        }

        if ($reportConn instanceof mysqli) {
            $recentResult = $reportConn->query("SELECT COUNT(*) AS count FROM acc_reports WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            if ($recentResult && ($row = $recentResult->fetch_assoc())) {
                $metrics['reports']['recent_30'] = (int)$row['count'];
            }

            $totalResult = $reportConn->query("SELECT COUNT(*) AS count, MAX(generated_at) AS last_run FROM acc_reports");
            if ($totalResult && ($row = $totalResult->fetch_assoc())) {
                $metrics['reports']['total'] = (int)$row['count'];
                $metrics['reports']['last_generated_at'] = $row['last_run'] ?? null;
            }
        }

        return $metrics;
    }
}
