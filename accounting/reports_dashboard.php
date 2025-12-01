<?php

/**
 * Reports Dashboard
 * File: /accounting/reports_dashboard.php
 * Purpose: Main dashboard for financial reports
 * FIXED: Updated to use consolidated helper functions and fixed missing JSON variables
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/report_groups.php';
require_once __DIR__ . '/lib/report_filters.php';
require_once __DIR__ . '/../include/premium_hero.php';
require_once __DIR__ . '/report_catalog.php';

try {
    $accountingConn = accounting_db_connection();
} catch (Exception $e) {
    setToastMessage('danger', 'Database Error', 'Accounting database connection is unavailable.', 'fas fa-plug');
    header('Location: /accounting/dashboard.php');
    exit();
}

if (!function_exists('accounting_report_type_icon')) {
    function accounting_report_type_icon(string $type): string
    {
        static $map = [
            'income_statement' => 'fa-chart-line',
            'balance_sheet' => 'fa-scale-balanced',
            'cash_flow' => 'fa-droplet',
            'expense_report' => 'fa-file-invoice-dollar',
            'donor_list' => 'fa-hand-holding-heart',
            'donation_summary' => 'fa-heart',
            'asset_listing' => 'fa-boxes-stacked',
            'ytd_income_statement' => 'fa-calendar-alt',
            'ytd_budget_comparison' => 'fa-scale-balanced',
        ];

        $normalized = strtolower(trim($type));
        return $map[$normalized] ?? 'fa-file-lines';
    }
}

if (!function_exists('accounting_report_type_accent')) {
    function accounting_report_type_accent(string $type): string
    {
        static $map = [
            'income_statement' => 'success',
            'balance_sheet' => 'primary',
            'cash_flow' => 'info',
            'expense_report' => 'danger',
            'donor_list' => 'warning',
            'donation_summary' => 'warning',
            'asset_listing' => 'secondary',
            'ytd_income_statement' => 'primary',
            'ytd_budget_comparison' => 'dark',
        ];

        $normalized = strtolower(trim($type));
        return $map[$normalized] ?? 'secondary';
    }
}

if (!function_exists('buildReportGroupingSummary')) {
    function buildReportGroupingSummary(array $reports, string $groupType, array $baseFilters = []): array
    {
        $groupType = strtolower($groupType);
        if (!in_array($groupType, ['type', 'month'], true)) {
            return [
                'type' => 'none',
                'groups' => [],
                'group_count' => 0,
                'item_count' => 0,
            ];
        }

        $baseFilters = array_filter($baseFilters, static function ($value) {
            return $value !== null && $value !== '';
        });

        $groups = [];
        foreach ($reports as $report) {
            $key = 'uncategorized';
            $label = 'Uncategorized';
            $icon = 'fa-file-lines';
            $accent = 'secondary';
            $filterLink = null;

            if ($groupType === 'type') {
                $rawType = strtolower(trim((string)($report['report_type'] ?? '')));
                $key = $rawType !== '' ? $rawType : 'uncategorized';
                $label = $key === 'uncategorized' ? 'Uncategorized' : ucwords(str_replace('_', ' ', $key));
                $icon = accounting_report_type_icon($key);
                $accent = accounting_report_type_accent($key);
                $filterLink = 'reports_dashboard.php?' . http_build_query(array_merge($baseFilters, [
                    'type' => $key,
                    'group' => 'none',
                ]));
            } elseif ($groupType === 'month') {
                $timestamp = !empty($report['generated_at']) ? strtotime($report['generated_at']) : null;
                $key = $timestamp ? date('Y-m', $timestamp) : 'unknown';
                $label = $timestamp ? date('F Y', $timestamp) : 'Unknown Period';
                $icon = 'fa-calendar-days';
                $accent = 'info';
                $filterLink = $timestamp ? 'reports_dashboard.php?' . http_build_query(array_merge($baseFilters, [
                    'group' => 'none',
                    'search' => date('F Y', $timestamp),
                    'range' => 'all',
                ])) : null;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $label,
                    'icon' => $icon,
                    'accent' => $accent,
                    'count' => 0,
                    'last_generated' => null,
                    'reports' => [],
                    'filter_link' => $filterLink,
                ];
            }

            $groups[$key]['count']++;
            if (!empty($report['generated_at'])) {
                $generatedTimestamp = strtotime($report['generated_at']);
                if (!$groups[$key]['last_generated'] || $generatedTimestamp > $groups[$key]['last_generated']) {
                    $groups[$key]['last_generated'] = $generatedTimestamp;
                }
            }

            if (count($groups[$key]['reports']) < 4) {
                $paramPreview = trim((string)($report['parameters'] ?? ''));
                if ($paramPreview !== '') {
                    $paramPreview = function_exists('mb_strimwidth')
                        ? mb_strimwidth($paramPreview, 0, 90, '…')
                        : substr($paramPreview, 0, 87) . '…';
                }

                $groups[$key]['reports'][] = [
                    'id' => $report['id'],
                    'title' => ucwords(str_replace('_', ' ', $report['report_type'] ?? 'Report')),
                    'generated_at' => !empty($report['generated_at']) ? date('M d, Y g:i A', strtotime($report['generated_at'])) : '—',
                    'parameters' => $paramPreview,
                    'view_url' => 'reports/view_report.php?id=' . urlencode((string)$report['id']),
                    'download_url' => 'reports/download_report.php?id=' . urlencode((string)$report['id']),
                ];
            }
        }

        $groups = array_values($groups);
        usort($groups, static function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        foreach ($groups as &$group) {
            $group['last_generated_display'] = $group['last_generated']
                ? date('M d, Y', $group['last_generated'])
                : '—';
        }
        unset($group);

        return [
            'type' => $groupType,
            'groups' => $groups,
            'group_count' => count($groups),
            'item_count' => array_sum(array_map(static function ($group) {
                return $group['count'];
            }, $groups)),
        ];
    }
}


// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access reports.', 'fas fa-chart-line');
    header('Location: ../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to access reports.', 'fas fa-chart-line');
    header('Location: ../authentication/dashboard.php');
    exit();
}

// Log access
logActivity($user_id, 'reports_dashboard_access', 'auth_activity_log', null, 'Accessed Reports Dashboard');

accountingEnsureReportGroupingTables($accountingConn);
$reportCatalog = getReportCatalog();
accountingEnsureDefaultReportGroups($accountingConn, $reportCatalog, $user_id ?? null);
$groupingTypeMeta = [
    'monthly' => [
        'label' => 'Monthly Grouping',
        'description' => 'Calendar month packet for leadership reviews.',
        'badge' => 'Calendar Month',
        'icon' => 'fa-calendar-days',
    ],
    'ytd' => [
        'label' => 'YTD Grouping',
        'description' => 'January through current month trending bundle.',
        'badge' => 'Jan - Current',
        'icon' => 'fa-chart-area',
    ],
    'annual' => [
        'label' => 'Annual Grouping',
        'description' => 'Full-year compliance and stewardship packets.',
        'badge' => 'Full Year',
        'icon' => 'fa-award',
    ],
];

$reportCatalogByType = [];
foreach (array_keys($groupingTypeMeta) as $groupTypeKey) {
    $reportCatalogByType[$groupTypeKey] = getReportCatalogForType($groupTypeKey);
}
$groupingTypeKeys = array_keys($groupingTypeMeta);
$defaultGroupType = $groupingTypeKeys[0] ?? 'monthly';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_action'])) {
    $action = sanitizeInput($_POST['group_action'], 'string');
    $actionResult = [
        'success' => false,
        'message' => 'Unknown action.',
    ];

    if ($action === 'create_group') {
        $actionResult = accountingHandleCreateReportGroup($accountingConn, (int)$user_id, $_POST, $reportCatalog);
    } elseif (in_array($action, ['add_reports', 'remove_report'], true)) {
        $groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
        $groupRow = $groupId > 0 ? accountingGetReportGroupById($accountingConn, $groupId) : null;
        if (!$groupRow) {
            $actionResult = [
                'success' => false,
                'message' => 'Selected group was not found.',
            ];
        } elseif ($action === 'add_reports') {
            $selectedKeys = isset($_POST['group_reports']) && is_array($_POST['group_reports']) ? $_POST['group_reports'] : [];
            $actionResult = accountingAddReportsToGroup($accountingConn, $groupId, $selectedKeys, $reportCatalog, $groupRow['group_type']);
        } else {
            $catalogKey = sanitizeInput($_POST['catalog_key'] ?? '', 'string');
            $actionResult = accountingRemoveReportFromGroup($accountingConn, $groupId, $catalogKey);
        }
    }

    setToastMessage($actionResult['success'] ? 'success' : 'danger', 'Report Groups', $actionResult['message'], 'fas fa-object-group');
    $redirectUrl = 'reports_dashboard.php';
    if (!empty($_GET)) {
        $redirectUrl .= '?' . http_build_query($_GET);
    }
    header('Location: ' . $redirectUrl);
    exit();
}

$status = $_GET['status'] ?? null;

// Fetch reports
$query = "SELECT * FROM acc_reports ORDER BY generated_at DESC";
$result = $accountingConn->query($query);
$reports = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Get report type counts for visualization
$report_type_query = "SELECT report_type, COUNT(*) as count FROM acc_reports GROUP BY report_type";
$report_type_result = $accountingConn->query($report_type_query);
$report_types = [];
if ($report_type_result) {
    while ($row = $report_type_result->fetch_assoc()) {
        $report_types[] = [
            'report_type' => $row['report_type'],
            'count' => intval($row['count'])
        ];
    }
}

// Get monthly report generation counts for the past year
$monthly_report_query = "SELECT DATE_FORMAT(generated_at, '%Y-%m') as month, COUNT(*) as count 
                        FROM acc_reports 
                        WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(generated_at, '%Y-%m')
                        ORDER BY month ASC";
$monthly_report_result = $accountingConn->query($monthly_report_query);
$monthly_reports = [];
if ($monthly_report_result) {
    while ($row = $monthly_report_result->fetch_assoc()) {
        $monthly_reports[] = [
            'month' => $row['month'],
            'count' => intval($row['count'])
        ];
    }
}

$hasReportTypeData = array_sum(array_map(static function ($typeRow) {
    return (int)($typeRow['count'] ?? 0);
}, $report_types)) > 0;
$hasMonthlyReportData = array_sum(array_map(static function ($monthlyRow) {
    return (int)($monthlyRow['count'] ?? 0);
}, $monthly_reports)) > 0;

// Calculate recent report statistics
$recent_reports_count = 0;
$total_reports_count = 0;
$avg_reports_per_month = 0;

$recent_result = $accountingConn->query("SELECT COUNT(*) as count FROM acc_reports WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($recent_result) {
    $recent_reports_count = $recent_result->fetch_assoc()['count'] ?? 0;
}

$total_result = $accountingConn->query("SELECT COUNT(*) as count FROM acc_reports");
if ($total_result) {
    $total_reports_count = $total_result->fetch_assoc()['count'] ?? 0;
}

$avg_result = $accountingConn->query("SELECT AVG(monthly_count) as avg FROM (SELECT COUNT(*) as monthly_count FROM acc_reports GROUP BY YEAR(generated_at), MONTH(generated_at)) as counts");
if ($avg_result) {
    $avg_reports_per_month = $avg_result->fetch_assoc()['avg'] ?? 0;
}

// FIXED: Prepare JSON data for JavaScript
$report_types_json = json_encode($report_types);
$monthly_reports_json = json_encode($monthly_reports);

$groupLabels = [
    'none' => 'Disabled',
    'type' => 'Report type',
    'month' => 'Month generated',
];

$availableTypes = array_values(array_unique(array_filter(array_map(static function ($report) {
    return $report['report_type'] ?? '';
}, $reports))));
sort($availableTypes);

$filters = accountingNormalizeReportFilters($_GET, $availableTypes);

if ($filters['type'] !== 'all' && !in_array($filters['type'], $availableTypes, true)) {
    $availableTypes[] = $filters['type'];
    sort($availableTypes);
}

$filteredReports = accountingApplyReportFilters($reports, $filters);
$filteredReports = accountingSortReports($filteredReports, $filters['sort']);

$groupLinkBaseFilters = [
    'range' => $filters['range'],
    'sort' => $filters['sort'],
    'search' => $filters['search'],
    'type' => 'all',
];

$reportGroupSummary = buildReportGroupingSummary($filteredReports, $filters['group'], $groupLinkBaseFilters);

$filteredReportCount = count($filteredReports);
$presetTypes = accountingGetPresetReportTypes($availableTypes, 3);

$rangeLabels = [
    '30' => 'Last 30 days',
    '90' => 'Last 90 days',
    '365' => 'Last 12 months',
    'ytd' => 'Year to date',
    'all' => 'All time'
];
$currentRangeLabel = $rangeLabels[$filters['range']] ?? 'Custom range';
$activeTypeLabel = $filters['type'] === 'all'
    ? 'All report types'
    : ucwords(str_replace('_', ' ', $filters['type']));

$reportHeroHighlights = [
    [
        'label' => 'Total Reports',
        'value' => number_format($total_reports_count),
        'meta' => 'Since launch'
    ],
    [
        'label' => 'Last 30 Days',
        'value' => number_format($recent_reports_count),
        'meta' => 'Recent activity'
    ],
    [
        'label' => 'Avg / Month',
        'value' => number_format((int)round($avg_reports_per_month)),
        'meta' => 'Rolling 12 months'
    ],
    [
        'label' => 'Filtered',
        'value' => number_format($filteredReportCount),
        'meta' => 'Matches current filters'
    ],
];

$reportHeroChips = [
    'Type: ' . $activeTypeLabel,
    'Range: ' . $currentRangeLabel,
];
$reportHeroChips[] = 'Group: ' . ($groupLabels[$filters['group']] ?? 'Disabled');
if (!empty($filters['search'])) {
    $reportHeroChips[] = 'Keyword: ' . $filters['search'];
}

$reportHeroActions = [
    [
        'label' => 'Generate Report',
        'url' => 'reports/generate_report.php',
        'icon' => 'fa-plus-circle',
        'external' => true,
        'data_report_launch' => true,
    ],
    [
        'label' => 'Financial Statements',
        'url' => 'reports/financial_statements.php',
        'icon' => 'fa-file-invoice-dollar',
        'variant' => 'outline',
        'external' => true,
        'data_report_launch' => true,
    ],
    [
        'label' => 'Back to Accounting',
        'url' => 'dashboard.php',
        'icon' => 'fa-arrow-left',
        'variant' => 'outline'
    ],
];

$buildCollectionItems = static function (array $keys) use ($reportCatalog) {
    $items = [];
    foreach ($keys as $key) {
        if (!isset($reportCatalog[$key])) {
            continue;
        }
        $entry = $reportCatalog[$key];
        $items[] = [
            'label' => $entry['label'],
            'description' => $entry['description'] ?? '',
            'url' => $entry['href'],
            'meta' => $entry['meta'] ?? null,
            'icon' => $entry['icon'] ?? 'fa-file-lines',
        ];
    }
    return $items;
};

$reportCollections = [
    [
        'id' => 'financial',
        'filter_label' => 'Financial',
        'title' => 'Financial Statements',
        'description' => 'Board-ready statements for balance, income, and liquidity snapshots.',
        'icon' => 'fa-scale-balanced',
        'badge' => 'Core',
        'accent' => 'primary',
        'reports' => $buildCollectionItems([
            'income_statement',
            'balance_sheet',
            'cash_flow',
            'ytd_budget_comparison',
        ]),
    ],
    [
        'id' => 'operational',
        'filter_label' => 'Operational',
        'title' => 'Operational Insights',
        'description' => 'Monitor recurring activity, spending controls, and income drivers.',
        'icon' => 'fa-sitemap',
        'badge' => 'Ops',
        'accent' => 'success',
        'reports' => $buildCollectionItems([
            'monthly_summary',
            'expense_report',
            'income_report',
            'sources_uses',
        ]),
    ],
    [
        'id' => 'assets',
        'filter_label' => 'Assets',
        'title' => 'Asset & Cash Tracking',
        'description' => 'Pair physical inventory with banking activity for compliance.',
        'icon' => 'fa-boxes-stacked',
        'badge' => 'Logistics',
        'accent' => 'warning',
        'reports' => $buildCollectionItems([
            'physical_assets_report',
            'asset_listing',
            'cash_account_report',
            'ytd_cash_flow',
        ]),
    ],
    [
        'id' => 'donations',
        'filter_label' => 'Donations',
        'title' => 'Donations & Compliance',
        'description' => 'Ready-to-send donor receipts and stewardship exports.',
        'icon' => 'fa-hand-holding-heart',
        'badge' => 'Outreach',
        'accent' => 'info',
        'reports' => $buildCollectionItems([
            'donor_list',
            'donation_summary',
            'donation_receipts',
            'annual_donor_statement',
        ]),
    ],
];

$reportCollectionFilters = array_map(static function (array $collection) {
    return [
        'id' => $collection['id'],
        'label' => $collection['filter_label'] ?? $collection['title'],
    ];
}, $reportCollections);

$reportGroups = accountingFetchReportGroups($accountingConn, $reportCatalog);

if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reports_export_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Report Type', 'Generated', 'Parameters']);
    foreach ($filteredReports as $report) {
        fputcsv($output, [
            $report['id'] ?? '',
            $report['report_type'] ?? '',
            $report['generated_at'] ?? '',
            $report['parameters'] ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$page_title = "Reports Dashboard - W5OBM";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>

    <style>
        .report-collections-card .card-header {
            background: linear-gradient(92deg, rgba(13, 110, 253, 0.12), rgba(25, 135, 84, 0.12));
        }

        .report-group-card {
            border-radius: 1rem;
            border: 1px solid #e4e9f2;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .report-group-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 1.25rem 2.5rem -1rem rgba(15, 23, 42, 0.25);
        }

        .report-group-card .card-header {
            border-bottom: 0;
            background-color: rgba(248, 249, 250, 0.75);
        }

        .report-group-card .list-group-item {
            border: 0;
            border-top: 1px solid #f1f3f5;
            padding: 1rem 1.25rem;
        }

        .report-item-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-right: .75rem;
        }

        .report-item-icon.accent-primary {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .report-item-icon.accent-success {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .report-item-icon.accent-warning {
            background-color: rgba(255, 193, 7, 0.2);
            color: #d39e00;
        }

        .report-item-icon.accent-info {
            background-color: rgba(13, 202, 240, 0.15);
            color: #0dcaf0;
        }

        .report-item-meta {
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .report-collection-filter.active {
            color: #fff;
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .report-collection-hidden {
            display: none !important;
        }

        @media (max-width: 991.98px) {
            .report-group-card .card-header {
                text-align: center;
            }
        }

        .analytics-placeholder {
            min-height: 240px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            padding: 1.5rem;
        }

        .analytics-placeholder i {
            font-size: 2rem;
            margin-bottom: .75rem;
            color: #adb5bd;
        }

        .group-builder-step {
            border: 1px dashed #d9dee9;
            border-radius: .9rem;
            padding: 1.25rem;
            background-color: #fff;
            height: 100%;
        }

        .group-builder-list {
            max-height: 320px;
            overflow-y: auto;
            margin-top: 1rem;
            padding-right: .5rem;
        }

        .group-builder-list.compact {
            max-height: 220px;
        }

        .group-builder-item {
            border: 1px solid #e4e9f2;
            border-radius: .75rem;
            padding: .65rem .85rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
            margin-bottom: .5rem;
            transition: border-color .15s ease, box-shadow .15s ease;
            cursor: pointer;
            background-color: #fff;
        }

        .group-builder-item:hover {
            border-color: #0d6efd;
            box-shadow: 0 0.75rem 1.5rem -1rem rgba(13, 110, 253, 0.45);
        }

        .group-builder-item input[type="checkbox"] {
            margin-top: .4rem;
        }

        .group-builder-item-body strong {
            display: block;
            font-size: .95rem;
        }

        .group-builder-item-body p {
            margin-bottom: 0;
            font-size: .82rem;
            color: #5c6671;
        }

        .group-builder-item-body small {
            display: block;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .68rem;
        }

        .group-builder-list-panel.d-none {
            display: none !important;
        }

        .report-group-widget {
            border-radius: .95rem;
            border: 1px solid #e4e9f2;
            background-color: #fff;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .report-group-widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 1.35rem 2.7rem -1.35rem rgba(33, 37, 41, 0.4);
        }

        .report-group-widget .group-detail {
            border-top: 1px solid #f1f3f5;
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .group-widget-badge {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .group-members-list .list-group-item {
            border: 0;
            border-top: 1px solid #f1f3f5;
            padding-left: 0;
            padding-right: 0;
        }

        .group-members-list .list-group-item:first-child {
            border-top: 0;
        }

        .group-builder-list::-webkit-scrollbar {
            width: 6px;
        }

        .group-builder-list::-webkit-scrollbar-thumb {
            background-color: rgba(13, 110, 253, 0.35);
            border-radius: 12px;
        }
    </style>
</head>

<body class="accounting-app bg-light">
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <?php if (function_exists('renderPremiumHero')): ?>
            <?php
            renderPremiumHero([
                'eyebrow' => 'Reports Center',
                'title' => 'Financial intelligence at a glance.',
                'subtitle' => 'Group statements, cash activity, and donor compliance in one streamlined hub.',
                'description' => 'Launch a curated bundle or craft a custom export while keeping leadership in sync.',
                'theme' => 'cobalt',
                'size' => 'compact',
                'media' => [
                    'type' => 'image',
                    'src' => 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=900&q=80',
                    'alt' => 'Team reviewing financial dashboards'
                ],
                'actions' => $reportHeroActions,
                'highlights' => $reportHeroHighlights,
                'chips' => $reportHeroChips,
            ]);
            ?>
        <?php else: ?>
            <section class="hero hero-small mb-4">
                <div class="hero-body py-3">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-2 d-none d-md-flex justify-content-center">
                                <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                                <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                            </div>
                            <div class="col-md-6 text-center text-md-start text-white">
                                <h1 class="h4 mb-1">Financial Reports</h1>
                                <p class="mb-0 small">Monitor performance, download data, and trigger new statements.</p>
                            </div>
                            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Accounting
                                </a>
                                <a href="/accounting/reports/financial_statements.php" class="btn btn-warning btn-sm me-2" target="_blank" rel="noopener" data-report-launch="true">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>Financial Statements
                                </a>
                                <a href="reports/generate_report.php" class="btn btn-primary btn-sm" target="_blank" rel="noopener" data-report-launch="true">
                                    <i class="fas fa-plus-circle me-1"></i>New Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($status): ?>
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show shadow" role="alert">
                <?= $status === 'success' ? 'Report generated successfully!' : 'Error generating report. Please try again.'; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-3 hero-summary-row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Total Reports</span>
                        <i class="fas fa-chart-line text-primary"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($total_reports_count) ?></h4>
                    <small class="text-muted">All-time generated reports</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Last 30 Days</span>
                        <i class="fas fa-clock text-success"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($recent_reports_count) ?></h4>
                    <small class="text-muted">Recent report activity</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Avg / Month</span>
                        <i class="fas fa-chart-area text-info"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format(round($avg_reports_per_month)) ?></h4>
                    <small class="text-muted">Rolling 12-month average</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="border rounded p-3 h-100 bg-light hero-summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Filtered Results</span>
                        <i class="fas fa-filter text-warning"></i>
                    </div>
                    <h4 class="mb-0"><?= number_format($filteredReportCount) ?></h4>
                    <small class="text-muted">Matches current filters</small>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                <div class="me-auto">
                    <p class="text-uppercase text-muted small mb-1">Workspace Shortcuts</p>
                    <small class="text-muted">Open the group builder or the full report inventory only when you need them.</small>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse"
                    data-bs-target="#reportGroupBuilderCollapse" aria-expanded="false" aria-controls="reportGroupBuilderCollapse">
                    <i class="fas fa-diagram-project me-1"></i>Manage Report Groups
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse"
                    data-bs-target="#reportInventoryCollapse" aria-expanded="false" aria-controls="reportInventoryCollapse">
                    <i class="fas fa-table me-1"></i>Browse Available Reports
                </button>
            </div>
        </div>

        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Reports</h5>
                    </div>
                    <div class="col-auto">
                        <a href="reports_dashboard.php" class="btn btn-outline-light btn-sm"><i class="fas fa-times me-1"></i>Reset</a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 flex-column flex-lg-row">
                    <div class="col-12 col-lg-3 border-bottom border-lg-bottom-0 border-lg-end bg-light-subtle p-3">
                        <h6 class="text-uppercase small text-muted fw-bold mb-2">Preset Filters</h6>
                        <p class="text-muted small mb-3">Jump to common time spans or popular report mixes.</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm report-chip text-start" data-range="30">
                                <span class="fw-semibold">Last 30 Days</span>
                                <small class="d-block text-muted">Recent activity</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm report-chip text-start" data-range="90">
                                <span class="fw-semibold">Quarter to Date</span>
                                <small class="d-block text-muted">Past 90 days</small>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm report-chip text-start" data-range="ytd">
                                <span class="fw-semibold">Year to Date</span>
                                <small class="d-block text-muted">Reset to January 1</small>
                            </button>
                            <?php foreach ($presetTypes as $type): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm report-chip text-start" data-type="<?= htmlspecialchars($type) ?>">
                                    <span class="fw-semibold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></span>
                                    <small class="d-block text-muted">Focus on type</small>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm report-chip text-start" data-clear="true">
                                <span class="fw-semibold">Clear Presets</span>
                                <small class="d-block text-muted">Reset filters & keyword</small>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="reportFilterForm">
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <div class="col">
                                    <label for="type" class="form-label text-muted text-uppercase small mb-1">Report Type</label>
                                    <select id="type" name="type" class="form-select form-select-sm">
                                        <option value="all" <?= $filters['type'] === 'all' ? 'selected' : '' ?>>All Types</option>
                                        <?php foreach ($availableTypes as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="range" class="form-label text-muted text-uppercase small mb-1">Date Range</label>
                                    <select id="range" name="range" class="form-select form-select-sm">
                                        <option value="30" <?= $filters['range'] === '30' ? 'selected' : '' ?>>Last 30 Days</option>
                                        <option value="90" <?= $filters['range'] === '90' ? 'selected' : '' ?>>Last 90 Days</option>
                                        <option value="365" <?= $filters['range'] === '365' ? 'selected' : '' ?>>Last 12 Months</option>
                                        <option value="ytd" <?= $filters['range'] === 'ytd' ? 'selected' : '' ?>>Year to Date</option>
                                        <option value="all" <?= $filters['range'] === 'all' ? 'selected' : '' ?>>All Time</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row row-cols-1 row-cols-md-2 g-3 mt-1">
                                <div class="col">
                                    <label for="sort" class="form-label text-muted text-uppercase small mb-1">Sort By</label>
                                    <select id="sort" name="sort" class="form-select form-select-sm">
                                        <option value="generated_desc" <?= $filters['sort'] === 'generated_desc' ? 'selected' : '' ?>>Newest First</option>
                                        <option value="generated_asc" <?= $filters['sort'] === 'generated_asc' ? 'selected' : '' ?>>Oldest First</option>
                                        <option value="type_asc" <?= $filters['sort'] === 'type_asc' ? 'selected' : '' ?>>Type (A-Z)</option>
                                        <option value="type_desc" <?= $filters['sort'] === 'type_desc' ? 'selected' : '' ?>>Type (Z-A)</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="group" class="form-label text-muted text-uppercase small mb-1">Group By</label>
                                    <select id="group" name="group" class="form-select form-select-sm">
                                        <option value="none" <?= $filters['group'] === 'none' ? 'selected' : '' ?>>Disabled</option>
                                        <option value="type" <?= $filters['group'] === 'type' ? 'selected' : '' ?>>Report Type</option>
                                        <option value="month" <?= $filters['group'] === 'month' ? 'selected' : '' ?>>Month Generated</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 col-lg-8">
                                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                    <input type="text" id="search" name="search" class="form-control form-control-sm"
                                        placeholder="Type, parameter, generated by" value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="reportExportBtn">
                                    <i class="fas fa-file-export me-1"></i>Export
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="collapse mb-4" id="reportGroupBuilderCollapse">
            <div class="card shadow-sm border-0" id="reportGroupBuilder">
                <div class="card-header bg-light border-0 d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="fas fa-diagram-project me-2 text-primary"></i>Report Group Builder</h5>
                        <small class="text-muted">Pick a grouping option, then add every report, chart, or graph that belongs in that packet.</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">New</span>
                </div>
                <div class="card-body">
                    <form method="POST" id="reportGroupBuilderForm" class="group-builder-form">
                        <input type="hidden" name="group_action" value="create_group">
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="group-builder-step h-100">
                                    <div class="text-uppercase small text-muted fw-bold mb-2">Step 1 · Choose grouping option</div>
                                    <?php foreach ($groupingTypeMeta as $typeKey => $meta): ?>
                                        <?php $radioId = 'group-type-' . $typeKey; ?>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="group_type" id="<?= htmlspecialchars($radioId) ?>" value="<?= htmlspecialchars($typeKey) ?>" <?= $typeKey === $defaultGroupType ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="<?= htmlspecialchars($radioId) ?>">
                                                <?= htmlspecialchars($meta['label']) ?>
                                            </label>
                                            <small class="text-muted d-block"><?= htmlspecialchars($meta['description']) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="group-builder-step h-100">
                                    <div class="text-uppercase small text-muted fw-bold mb-2">Step 2 · Name the packet</div>
                                    <div class="mb-3">
                                        <label for="groupName" class="form-label small text-muted">Group Name</label>
                                        <input type="text" id="groupName" name="group_name" class="form-control" placeholder="e.g. Monthly Board Packet" required>
                                    </div>
                                    <div>
                                        <label for="groupDescription" class="form-label small text-muted">Optional Description</label>
                                        <textarea id="groupDescription" name="group_description" class="form-control" rows="3" placeholder="What belongs in this group?"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-4">
                                <div class="group-builder-step h-100">
                                    <div class="text-uppercase small text-muted fw-bold mb-2">Quick Tips</div>
                                    <ul class="small text-muted ps-3 mb-0">
                                        <li class="mb-2">Monthly groups should reflect a single calendar month.</li>
                                        <li class="mb-2">YTD packets must summarize January through the current month.</li>
                                        <li>Annual bundles highlight full-year compliance, donor, and inventory reports.</li>
                                    </ul>
                                    <div class="alert alert-info mt-3 mb-0 py-2">
                                        <i class="fas fa-info-circle me-1"></i>Selecting a different grouping option refreshes the list below so only valid items remain.
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="group-builder-step">
                                    <div class="text-uppercase small text-muted fw-bold mb-2">Step 3 · Add reports, charts, and graphs</div>
                                    <p class="text-muted small mb-3">Every available accounting report or visualization is listed. Only the workloads that match the selected timeframe remain active.</p>
                                    <?php foreach ($reportCatalogByType as $typeKey => $items): ?>
                                        <?php $panelActive = $typeKey === $defaultGroupType; ?>
                                        <div class="group-builder-list-panel<?= $panelActive ? '' : ' d-none' ?>" data-catalog-panel="<?= htmlspecialchars($typeKey) ?>">
                                            <?php if (empty($items)): ?>
                                                <p class="text-muted fst-italic mb-0">No catalog entries configured for this grouping yet.</p>
                                            <?php else: ?>
                                                <div class="group-builder-list">
                                                    <?php foreach ($items as $item): ?>
                                                        <?php $checkboxId = 'catalog-' . $typeKey . '-' . $item['key']; ?>
                                                        <label class="group-builder-item form-check" for="<?= htmlspecialchars($checkboxId) ?>">
                                                            <input type="checkbox" class="form-check-input" name="group_reports[]" id="<?= htmlspecialchars($checkboxId) ?>" value="<?= htmlspecialchars($item['key']) ?>" <?= $panelActive ? '' : 'disabled' ?>>
                                                            <span class="group-builder-item-body">
                                                                <strong><?= htmlspecialchars($item['label']) ?></strong>
                                                                <small><?= htmlspecialchars(ucfirst($item['kind'] ?? 'report')) ?><?= !empty($item['meta']) ? ' · ' . htmlspecialchars($item['meta']) : '' ?></small>
                                                                <p><?= htmlspecialchars($item['description']) ?></p>
                                                            </span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus-circle me-1"></i>Create Group
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card shadow-sm border-0 mb-4" id="reportGroupLibrary">
            <div class="card-header bg-light border-0 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-layer-group me-2 text-primary"></i>Saved Report Groups</h5>
                    <small class="text-muted">Select a widget to review its members, add additional reports, or remove outdated ones.</small>
                </div>
                <span class="badge bg-secondary-subtle text-secondary"><i class="fas fa-calendar me-1"></i>Monthly · YTD · Annual</span>
            </div>
            <div class="card-body">
                <?php if (empty($reportGroups)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-object-group fa-2x mb-3"></i>
                        <p class="mb-1">No custom report groupings yet.</p>
                        <p class="small mb-0">Use the builder above to define Monthly, YTD, or Annual packets.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($reportGroups as $group): ?>
                            <?php
                            $typeMeta = $groupingTypeMeta[$group['group_type']] ?? ['badge' => strtoupper($group['group_type']), 'icon' => 'fa-object-group'];
                            $detailId = 'groupDetail' . (int)$group['id'];
                            ?>
                            <div class="col-xl-4 col-lg-6">
                                <div class="report-group-widget p-3" data-group-toggle="#<?= htmlspecialchars($detailId) ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <span class="group-widget-badge text-muted"><?= htmlspecialchars($typeMeta['badge'] ?? strtoupper($group['group_type'])) ?></span>
                                            <h5 class="mt-1 mb-1"><?= htmlspecialchars($group['name']) ?></h5>
                                            <small class="text-muted"><?= htmlspecialchars($group['description'] ?: 'No description provided.') ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary bg-opacity-10 text-primary"><?= number_format($group['item_count']) ?> items</span>
                                            <div class="text-muted mt-2"><i class="fas <?= htmlspecialchars($typeMeta['icon'] ?? 'fa-object-group') ?>"></i></div>
                                        </div>
                                    </div>
                                    <div class="collapse group-detail" id="<?= htmlspecialchars($detailId) ?>">
                                        <h6 class="fw-semibold mb-2">Current Members</h6>
                                        <?php if (empty($group['items'])): ?>
                                            <p class="text-muted small mb-3">No reports have been added yet.</p>
                                        <?php else: ?>
                                            <ul class="list-group group-members-list mb-3">
                                                <?php foreach ($group['items'] as $item): ?>
                                                    <?php $catalogEntry = $item['catalog'] ?? null; ?>
                                                    <li class="list-group-item">
                                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                                            <div>
                                                                <div class="fw-semibold"><?= htmlspecialchars($catalogEntry['label'] ?? ucwords(str_replace('_', ' ', $item['catalog_key']))) ?></div>
                                                                <?php if (!empty($catalogEntry['description'])): ?>
                                                                    <small class="text-muted d-block"><?= htmlspecialchars($catalogEntry['description']) ?></small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($catalogEntry['kind']) || !empty($catalogEntry['meta'])): ?>
                                                                    <small class="text-muted fst-italic">
                                                                        <?= htmlspecialchars(ucfirst($catalogEntry['kind'] ?? '')) ?><?= (!empty($catalogEntry['kind']) && !empty($catalogEntry['meta'])) ? ' · ' : '' ?><?= htmlspecialchars($catalogEntry['meta'] ?? '') ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <form method="POST" class="ms-auto">
                                                                <input type="hidden" name="group_action" value="remove_report">
                                                                <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                                                                <input type="hidden" name="catalog_key" value="<?= htmlspecialchars($item['catalog_key']) ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                    <i class="fas fa-trash me-1"></i>Remove
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <h6 class="fw-semibold mb-2">Add More Items</h6>
                                        <?php if (empty($group['eligible_items'])): ?>
                                            <p class="text-muted small mb-0">All compatible entries are already part of this group.</p>
                                        <?php else: ?>
                                            <form method="POST">
                                                <input type="hidden" name="group_action" value="add_reports">
                                                <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                                                <div class="group-builder-list compact">
                                                    <?php foreach ($group['eligible_items'] as $item): ?>
                                                        <?php $eligibleId = 'eligible-' . (int)$group['id'] . '-' . $item['key']; ?>
                                                        <label class="group-builder-item form-check" for="<?= htmlspecialchars($eligibleId) ?>">
                                                            <input type="checkbox" class="form-check-input" name="group_reports[]" id="<?= htmlspecialchars($eligibleId) ?>" value="<?= htmlspecialchars($item['key']) ?>">
                                                            <span class="group-builder-item-body">
                                                                <strong><?= htmlspecialchars($item['label']) ?></strong>
                                                                <small><?= htmlspecialchars(ucfirst($item['kind'] ?? 'report')) ?><?= !empty($item['meta']) ? ' · ' . htmlspecialchars($item['meta']) : '' ?></small>
                                                                <p><?= htmlspecialchars($item['description']) ?></p>
                                                            </span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="text-end mt-3">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-plus me-1"></i>Add Selected
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($reportGroupSummary['type'] !== 'none'): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light border-0 d-flex flex-wrap align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0"><i class="fas fa-object-group me-2 text-primary"></i>Grouping Overview</h5>
                        <small class="text-muted">Showing <?= number_format($reportGroupSummary['item_count']) ?> reports across <?= number_format($reportGroupSummary['group_count']) ?> groups.</small>
                    </div>
                    <a href="reports_dashboard.php?<?= htmlspecialchars(http_build_query(array_merge($groupLinkBaseFilters, ['group' => 'none']))) ?>" class="btn btn-outline-secondary btn-sm mt-3 mt-sm-0">
                        <i class="fas fa-times me-1"></i>Clear Grouping
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($reportGroupSummary['groups'])): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">No matching groups for the current filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($reportGroupSummary['groups'] as $group): ?>
                                <div class="col-xl-4 col-lg-6">
                                    <div class="card report-group-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="text-muted text-uppercase small fw-bold mb-1"><?= htmlspecialchars($group['count']) ?> reports</div>
                                                <h5 class="mb-0"><?= htmlspecialchars($group['label']) ?></h5>
                                                <small class="text-muted">Last generated <?= htmlspecialchars($group['last_generated_display'] ?? '—') ?></small>
                                            </div>
                                            <span class="badge bg-<?= htmlspecialchars($group['accent']) ?> bg-opacity-10 text-<?= htmlspecialchars($group['accent']) ?>">
                                                <i class="fas <?= htmlspecialchars($group['icon']) ?>"></i>
                                            </span>
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <?php if (empty($group['reports'])): ?>
                                                <li class="list-group-item text-muted fst-italic">Generate more activity to see details.</li>
                                            <?php else: ?>
                                                <?php foreach ($group['reports'] as $report): ?>
                                                    <li class="list-group-item">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <div class="fw-semibold"><?= htmlspecialchars($report['title']) ?></div>
                                                                <small class="text-muted d-block">Generated <?= htmlspecialchars($report['generated_at']) ?></small>
                                                                <?php if (!empty($report['parameters'])): ?>
                                                                    <small class="text-muted d-block"><?= htmlspecialchars($report['parameters']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="<?= htmlspecialchars($report['view_url']) ?>" class="btn btn-outline-primary" title="View" target="_blank" rel="noopener" data-report-launch="true">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="<?= htmlspecialchars($report['download_url']) ?>" class="btn btn-outline-success" title="Download" target="_blank" rel="noopener" data-report-launch="true">
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                        <?php if (!empty($group['filter_link'])): ?>
                                            <div class="card-footer bg-transparent border-0 text-end">
                                                <a href="<?= htmlspecialchars($group['filter_link']) ?>" class="btn btn-outline-secondary btn-sm">
                                                    Focus Group <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($reportCollections)): ?>
            <div class="card shadow-sm border-0 mb-4 report-collections-card">
                <div class="card-header border-0 py-3">
                    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">
                        <div>
                            <h5 class="mb-1"><i class="fas fa-layer-group me-2 text-primary"></i>Report Collections</h5>
                            <small class="text-muted">Curated bundles align to board packets, ops reviews, and donor mailings.</small>
                        </div>
                        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filter report collections">
                            <button type="button" class="btn btn-outline-primary report-collection-filter active" data-report-collection-filter="all">All</button>
                            <?php foreach ($reportCollectionFilters as $collectionFilter): ?>
                                <button type="button" class="btn btn-outline-primary report-collection-filter" data-report-collection-filter="<?= htmlspecialchars($collectionFilter['id']) ?>">
                                    <?= htmlspecialchars($collectionFilter['label']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <?php foreach ($reportCollections as $collection): ?>
                            <div class="col-xl-6" data-report-collection="<?= htmlspecialchars($collection['id']) ?>">
                                <div class="card report-group-card h-100">
                                    <div class="card-header d-flex align-items-start justify-content-between">
                                        <div>
                                            <div class="text-muted text-uppercase small fw-bold mb-1"><?= htmlspecialchars($collection['badge']) ?></div>
                                            <h5 class="mb-1"><?= htmlspecialchars($collection['title']) ?></h5>
                                            <p class="text-muted small mb-0"><?= htmlspecialchars($collection['description']) ?></p>
                                        </div>
                                        <span class="badge rounded-pill bg-<?= htmlspecialchars($collection['accent']) ?> bg-opacity-10 text-<?= htmlspecialchars($collection['accent']) ?> ms-3">
                                            <i class="fas <?= htmlspecialchars($collection['icon']) ?>"></i>
                                        </span>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($collection['reports'] as $reportItem): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                                    <div class="d-flex">
                                                        <div class="report-item-icon accent-<?= htmlspecialchars($collection['accent']) ?>">
                                                            <i class="fas <?= htmlspecialchars($reportItem['icon'] ?? 'fa-file-alt') ?>"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($reportItem['label']) ?></div>
                                                            <p class="text-muted small mb-2"><?= htmlspecialchars($reportItem['description']) ?></p>
                                                            <?php if (!empty($reportItem['meta'])): ?>
                                                                <span class="report-item-meta text-muted"><?= htmlspecialchars($reportItem['meta']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-md-end">
                                                        <a href="<?= htmlspecialchars($reportItem['url']) ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" data-report-launch="true">
                                                            <i class="fas fa-arrow-up-right-from-square me-1"></i>Open
                                                        </a>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasReportTypeData || $hasMonthlyReportData): ?>
            <div class="row g-4 mb-4">
                <div class="col-xl-6">
                    <div class="card shadow border-0 h-100">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Report Type Distribution</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($hasReportTypeData): ?>
                                <canvas id="reportTypeChart" height="280"></canvas>
                            <?php else: ?>
                                <div class="analytics-placeholder text-center">
                                    <i class="fas fa-chart-pie"></i>
                                    <p class="mb-1 fw-semibold">Report mix insight coming soon</p>
                                    <small class="text-muted">Generate a few reports to unlock this visualization.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card shadow border-0 h-100">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-success"></i>Monthly Generation Trends</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($hasMonthlyReportData): ?>
                                <canvas id="monthlyReportChart" height="280"></canvas>
                            <?php else: ?>
                                <div class="analytics-placeholder text-center">
                                    <i class="fas fa-wave-square"></i>
                                    <p class="mb-1 fw-semibold">Trend data not available yet</p>
                                    <small class="text-muted">Once monthly activity exists we will plot it here.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-light border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-play-circle me-2 text-primary"></i>Quick Actions</h5>
                    <small class="text-muted">Launch frequently used reports with one tap.</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/income_statement.php?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-outline-primary w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-chart-line me-2"></i>Income Statement
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/expense_report.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" class="btn btn-outline-danger w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Expense Report
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/cash_account_report.php?month=<?= date('Y-m') ?>" class="btn btn-outline-info w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-money-bill-wave me-2"></i>Cash Account
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/monthly_income_report.php?month=<?= date('Y-m') ?>" class="btn btn-outline-success w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-dollar-sign me-2"></i>Monthly Income
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/ytd_income_statement.php?year=<?= date('Y') ?>" class="btn btn-outline-warning w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-calendar-alt me-2"></i>YTD Income
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/YTD_cash_report.php?year=<?= date('Y') ?>" class="btn btn-outline-secondary w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-wallet me-2"></i>YTD Cash
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/physical_assets_report.php" class="btn btn-outline-dark w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-boxes me-2"></i>Physical Assets
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/generate_report.php" class="btn btn-primary w-100" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-plus-circle me-2"></i>Custom Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse" id="reportInventoryCollapse">
            <div class="card shadow border-0">
                <div class="card-header bg-light border-0 d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="fas fa-table me-2 text-primary"></i>Available Reports</h5>
                        <small class="text-muted">Filtered view updates with the controls above.</small>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-sm-0">
                        <a href="reports/generate_report.php" class="btn btn-success btn-sm" target="_blank" rel="noopener" data-report-launch="true">
                            <i class="fas fa-plus-circle me-1"></i>Generate New
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($filteredReports)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-circle-question fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-2">No reports match the filters you selected.</p>
                            <a href="reports_dashboard.php" class="btn btn-outline-primary btn-sm">Reset Filters</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="reportsTable" class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Parameters</th>
                                        <th>Generated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredReports as $report): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($report['id']) ?></td>
                                            <td>
                                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['report_type'] ?? 'Unknown'))) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($report['parameters'] ?? '—') ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?= $report['generated_at'] ? date('M d, Y g:i A', strtotime($report['generated_at'])) : '—' ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="reports/view_report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline-info" title="View" target="_blank" rel="noopener" data-report-launch="true">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="reports/download_report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline-success" title="Download" target="_blank" rel="noopener" data-report-launch="true">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <a href="reports/email_report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline-primary" title="Email" target="_blank" rel="noopener" data-report-launch="true">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                    <?php if (isAdmin($user_id)): ?>
                                                        <a href="reports/delete_report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline-danger" title="Delete"
                                                            onclick="return confirm('Are you sure you want to delete this report?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initReportFilters();
            initReportCollections();
            initReportGroupBuilder();
            initReportGroupWidgets();
            initReportLaunchHints();

            <?php if (!empty($filteredReports)): ?>
                if (typeof $.fn.DataTable !== 'undefined') {
                    $('#reportsTable').DataTable({
                        responsive: true,
                        pageLength: 10,
                        order: []
                    });
                }
            <?php endif; ?>

            initializeCharts();

            setTimeout(function() {
                showToast('info', 'Reports Dashboard', 'Here you can generate and view all financial reports.', 'fas fa-chart-line');
            }, 500);
        });

        function initReportFilters() {
            const form = document.getElementById('reportFilterForm');
            const typeSelect = document.getElementById('type');
            const rangeSelect = document.getElementById('range');
            const sortSelect = document.getElementById('sort');
            const groupSelect = document.getElementById('group');
            const searchInput = document.getElementById('search');
            const exportBtn = document.getElementById('reportExportBtn');
            const presetButtons = document.querySelectorAll('.report-chip');

            presetButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.dataset.clear === 'true') {
                        if (typeSelect) {
                            typeSelect.value = 'all';
                        }
                        if (rangeSelect) {
                            rangeSelect.value = '90';
                        }
                        if (groupSelect) {
                            groupSelect.value = 'none';
                        }
                        if (sortSelect) {
                            sortSelect.value = 'generated_desc';
                        }
                        if (searchInput) {
                            searchInput.value = '';
                        }
                        submitFilterForm(form);
                        return;
                    }

                    if (btn.dataset.type && typeSelect) {
                        typeSelect.value = btn.dataset.type;
                    }

                    if (btn.dataset.range && rangeSelect) {
                        rangeSelect.value = btn.dataset.range;
                    }

                    submitFilterForm(form);
                });
            });

            if (exportBtn && form) {
                exportBtn.addEventListener('click', function() {
                    const formData = new FormData(form);
                    const url = new URL(window.location.href);
                    formData.forEach(function(value, key) {
                        url.searchParams.set(key, value);
                    });
                    url.searchParams.set('export', '1');
                    window.location.href = url.toString();
                });
            }
        }

        function initReportCollections() {
            const filterButtons = document.querySelectorAll('[data-report-collection-filter]');
            const groupCards = document.querySelectorAll('[data-report-collection]');
            if (!filterButtons.length || !groupCards.length) {
                return;
            }

            filterButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const target = button.dataset.reportCollectionFilter || 'all';
                    filterButtons.forEach(function(btn) {
                        btn.classList.toggle('active', btn === button);
                    });
                    groupCards.forEach(function(card) {
                        if (target === 'all' || card.dataset.reportCollection === target) {
                            card.classList.remove('report-collection-hidden');
                        } else {
                            card.classList.add('report-collection-hidden');
                        }
                    });
                });
            });
        }

        function initReportGroupBuilder() {
            const builderForm = document.getElementById('reportGroupBuilderForm');
            if (!builderForm) {
                return;
            }

            const typeRadios = Array.from(builderForm.querySelectorAll('input[name="group_type"]'));
            const catalogPanels = Array.from(builderForm.querySelectorAll('[data-catalog-panel]'));

            function togglePanels(activeType) {
                catalogPanels.forEach(function(panel) {
                    const isActive = panel.dataset.catalogPanel === activeType;
                    panel.classList.toggle('d-none', !isActive);
                    panel.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
                        checkbox.disabled = !isActive;
                        if (!isActive) {
                            checkbox.checked = false;
                        }
                    });
                });
            }

            let activeRadio = typeRadios.find(function(radio) {
                return radio.checked;
            });
            if (!activeRadio && typeRadios.length) {
                typeRadios[0].checked = true;
                activeRadio = typeRadios[0];
            }
            if (activeRadio) {
                togglePanels(activeRadio.value);
            }

            typeRadios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    togglePanels(radio.value);
                });
            });
        }

        function initReportGroupWidgets() {
            if (typeof bootstrap === 'undefined') {
                return;
            }
            const cards = document.querySelectorAll('[data-group-toggle]');
            cards.forEach(function(card) {
                card.addEventListener('click', function(event) {
                    if (event.target.closest('form') || event.target.closest('button')) {
                        return;
                    }
                    const targetSelector = card.dataset.groupToggle;
                    if (!targetSelector) {
                        return;
                    }
                    const target = document.querySelector(targetSelector);
                    if (!target) {
                        return;
                    }
                    const collapse = bootstrap.Collapse.getOrCreateInstance(target, {
                        toggle: false
                    });
                    collapse.toggle();
                });
            });
        }

        function initReportLaunchHints() {
            if (typeof showToast !== 'function') {
                return;
            }
            const launchLinks = document.querySelectorAll('[data-report-launch]');
            if (!launchLinks.length) {
                return;
            }
            launchLinks.forEach(function(link) {
                if (link.dataset.reportLaunchBound === 'true') {
                    return;
                }
                link.dataset.reportLaunchBound = 'true';
                if (!link.getAttribute('aria-label')) {
                    const text = link.textContent ? link.textContent.trim() : '';
                    if (text !== '') {
                        link.setAttribute('aria-label', text + ' (opens in new window)');
                    }
                }
                link.addEventListener('click', function(event) {
                    if (event.defaultPrevented) {
                        return;
                    }
                    setTimeout(function() {
                        showToast('info', 'Report Opening', 'Report launched in a new window. Keep this dashboard tab for filters.', 'fas fa-arrow-up-right-from-square');
                    }, 150);
                });
            });
        }

        function submitFilterForm(form) {
            if (!form) {
                return;
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        function initializeCharts() {
            const reportTypeData = <?php echo $report_types_json ?: '[]'; ?>;
            const monthlyReportData = <?php echo $monthly_reports_json ?: '[]'; ?>;

            if (Array.isArray(reportTypeData) && reportTypeData.length > 0) {
                const reportTypeCtx = document.getElementById('reportTypeChart');
                if (reportTypeCtx) {
                    new Chart(reportTypeCtx, {
                        type: 'pie',
                        data: {
                            labels: reportTypeData.map(data => data.report_type),
                            datasets: [{
                                data: reportTypeData.map(data => data.count),
                                backgroundColor: [
                                    'rgba(40, 167, 69, 0.8)',
                                    'rgba(220, 53, 69, 0.8)',
                                    'rgba(255, 193, 7, 0.8)',
                                    'rgba(23, 162, 184, 0.8)',
                                    'rgba(108, 117, 125, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(40, 167, 69, 1)',
                                    'rgba(220, 53, 69, 1)',
                                    'rgba(255, 193, 7, 1)',
                                    'rgba(23, 162, 184, 1)',
                                    'rgba(108, 117, 125, 1)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw;
                                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: ${value} reports (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            if (Array.isArray(monthlyReportData) && monthlyReportData.length > 0) {
                const monthlyReportCtx = document.getElementById('monthlyReportChart');
                if (monthlyReportCtx) {
                    new Chart(monthlyReportCtx, {
                        type: 'line',
                        data: {
                            labels: monthlyReportData.map(data => {
                                const dateParts = data.month.split('-');
                                const year = dateParts[0];
                                const month = dateParts[1];
                                const date = new Date(year, month - 1);
                                return date.toLocaleDateString('en-US', {
                                    month: 'short',
                                    year: 'numeric'
                                });
                            }),
                            datasets: [{
                                label: 'Reports Generated',
                                data: monthlyReportData.map(data => data.count),
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 2,
                                tension: 0.3,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return `${context.dataset.label}: ${context.raw} reports`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }
    </script>
</body>

</html>