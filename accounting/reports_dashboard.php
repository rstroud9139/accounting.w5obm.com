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
require_once __DIR__ . '/../include/premium_hero.php';

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

$status = $_GET['status'] ?? null;

// Fetch reports
$query = "SELECT * FROM acc_reports ORDER BY generated_at DESC";
$result = $conn->query($query);
$reports = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Get report type counts for visualization
$report_type_query = "SELECT report_type, COUNT(*) as count FROM acc_reports GROUP BY report_type";
$report_type_result = $conn->query($report_type_query);
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
$monthly_report_result = $conn->query($monthly_report_query);
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

$recent_result = $conn->query("SELECT COUNT(*) as count FROM acc_reports WHERE generated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($recent_result) {
    $recent_reports_count = $recent_result->fetch_assoc()['count'] ?? 0;
}

$total_result = $conn->query("SELECT COUNT(*) as count FROM acc_reports");
if ($total_result) {
    $total_reports_count = $total_result->fetch_assoc()['count'] ?? 0;
}

$avg_result = $conn->query("SELECT AVG(monthly_count) as avg FROM (SELECT COUNT(*) as monthly_count FROM acc_reports GROUP BY YEAR(generated_at), MONTH(generated_at)) as counts");
if ($avg_result) {
    $avg_reports_per_month = $avg_result->fetch_assoc()['avg'] ?? 0;
}

// FIXED: Prepare JSON data for JavaScript
$report_types_json = json_encode($report_types);
$monthly_reports_json = json_encode($monthly_reports);

$filters = [
    'type' => sanitizeInput($_GET['type'] ?? 'all', 'string'),
    'range' => sanitizeInput($_GET['range'] ?? '90', 'string'),
    'search' => sanitizeInput($_GET['search'] ?? '', 'string'),
    'sort' => sanitizeInput($_GET['sort'] ?? 'generated_desc', 'string'),
    'group' => sanitizeInput($_GET['group'] ?? 'none', 'string'),
];

$allowedRanges = ['30', '90', '365', 'ytd', 'all'];
if (!in_array($filters['range'], $allowedRanges, true)) {
    $filters['range'] = '90';
}

$allowedSorts = ['generated_desc', 'generated_asc', 'type_asc', 'type_desc'];
if (!in_array($filters['sort'], $allowedSorts, true)) {
    $filters['sort'] = 'generated_desc';
}

$allowedGroups = ['none', 'type', 'month'];
if (!in_array($filters['group'], $allowedGroups, true)) {
    $filters['group'] = 'none';
}

$groupLabels = [
    'none' => 'Disabled',
    'type' => 'Report type',
    'month' => 'Month generated',
];

$availableTypes = array_values(array_unique(array_filter(array_map(static function ($report) {
    return $report['report_type'] ?? '';
}, $reports))));
sort($availableTypes);

if ($filters['type'] !== 'all' && !in_array($filters['type'], $availableTypes, true)) {
    $availableTypes[] = $filters['type'];
    sort($availableTypes);
}

$filteredReports = array_values(array_filter($reports, static function ($report) use ($filters) {
    $matchesType = $filters['type'] === 'all' || strcasecmp($report['report_type'] ?? '', $filters['type']) === 0;

    $matchesRange = true;
    $generatedAt = $report['generated_at'] ?? null;
    $timestamp = $generatedAt ? strtotime($generatedAt) : null;
    if ($timestamp && $filters['range'] !== 'all') {
        switch ($filters['range']) {
            case '30':
                $matchesRange = $timestamp >= strtotime('-30 days');
                break;
            case '90':
                $matchesRange = $timestamp >= strtotime('-90 days');
                break;
            case '365':
                $matchesRange = $timestamp >= strtotime('-365 days');
                break;
            case 'ytd':
                $matchesRange = $timestamp >= strtotime(date('Y-01-01 00:00:00'));
                break;
            default:
                $matchesRange = true;
                break;
        }
    } elseif (!$timestamp && $filters['range'] !== 'all') {
        $matchesRange = false;
    }

    $matchesSearch = true;
    if (!empty($filters['search'])) {
        $needle = strtolower($filters['search']);
        $haystack = strtolower(
            ($report['report_type'] ?? '') . ' ' .
                ($report['parameters'] ?? '') . ' ' .
                ($report['generated_by'] ?? '') . ' ' .
                (string)($report['id'] ?? '')
        );
        $matchesSearch = strpos($haystack, $needle) !== false;
    }

    return $matchesType && $matchesRange && $matchesSearch;
}));

$groupLinkBaseFilters = [
    'range' => $filters['range'],
    'sort' => $filters['sort'],
    'search' => $filters['search'],
    'type' => 'all',
];

$reportGroupSummary = buildReportGroupingSummary($filteredReports, $filters['group'], $groupLinkBaseFilters);

if (!empty($filteredReports)) {
    usort($filteredReports, static function ($a, $b) use ($filters) {
        $timeA = strtotime($a['generated_at'] ?? '') ?: 0;
        $timeB = strtotime($b['generated_at'] ?? '') ?: 0;
        switch ($filters['sort']) {
            case 'generated_asc':
                return $timeA <=> $timeB;
            case 'type_asc':
                return strcasecmp($a['report_type'] ?? '', $b['report_type'] ?? '');
            case 'type_desc':
                return strcasecmp($b['report_type'] ?? '', $a['report_type'] ?? '');
            case 'generated_desc':
            default:
                return $timeB <=> $timeA;
        }
    });
}

$filteredReportCount = count($filteredReports);
$presetTypes = array_slice($availableTypes, 0, 3);

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
        'icon' => 'fa-plus-circle'
    ],
    [
        'label' => 'Financial Statements',
        'url' => 'reports/financial_statements.php',
        'icon' => 'fa-file-invoice-dollar',
        'variant' => 'outline'
    ],
    [
        'label' => 'Back to Accounting',
        'url' => 'dashboard.php',
        'icon' => 'fa-arrow-left',
        'variant' => 'outline'
    ],
];

$reportCollections = [
    [
        'id' => 'financial',
        'filter_label' => 'Financial',
        'title' => 'Financial Statements',
        'description' => 'Board-ready statements for balance, income, and liquidity snapshots.',
        'icon' => 'fa-scale-balanced',
        'badge' => 'Core',
        'accent' => 'primary',
        'reports' => [
            [
                'label' => 'Income Statement',
                'description' => 'Month-by-month revenue vs. expense detail.',
                'url' => 'reports/income_statement.php',
                'meta' => 'Monthly packets',
                'icon' => 'fa-chart-line'
            ],
            [
                'label' => 'Balance Sheet',
                'description' => 'Assets, liabilities, and equity snapshot.',
                'url' => 'reports/balance_sheet.php',
                'meta' => 'As-of date',
                'icon' => 'fa-scale-balanced'
            ],
            [
                'label' => 'Cash Flow',
                'description' => 'Operating, investing, and financing activity.',
                'url' => 'reports/cash_flow.php',
                'meta' => 'Quarter focus',
                'icon' => 'fa-droplet'
            ],
            [
                'label' => 'YTD Budget Comparison',
                'description' => 'Actuals vs. plan across major accounts.',
                'url' => 'reports/ytd_budget_comparison.php',
                'meta' => 'Variance view',
                'icon' => 'fa-columns'
            ],
        ],
    ],
    [
        'id' => 'operational',
        'filter_label' => 'Operational',
        'title' => 'Operational Insights',
        'description' => 'Monitor recurring activity, spending controls, and income drivers.',
        'icon' => 'fa-sitemap',
        'badge' => 'Ops',
        'accent' => 'success',
        'reports' => [
            [
                'label' => 'Monthly Summary',
                'description' => 'Single page recap that leadership can skim quickly.',
                'url' => 'reports/monthly_summary.php',
                'meta' => 'Snapshot',
                'icon' => 'fa-calendar-alt'
            ],
            [
                'label' => 'Expense Detail',
                'description' => 'Line-item expenses for targeted audits.',
                'url' => 'reports/expense_report.php',
                'meta' => 'Category drill-down',
                'icon' => 'fa-file-invoice-dollar'
            ],
            [
                'label' => 'Income Detail',
                'description' => 'Donations, dues, and event revenue trends.',
                'url' => 'reports/income_report.php',
                'meta' => 'Drill by source',
                'icon' => 'fa-dollar-sign'
            ],
            [
                'label' => 'Sources & Uses',
                'description' => 'Understand how every incoming dollar is deployed.',
                'url' => 'reports/sources_uses.php',
                'meta' => 'Cash accountability',
                'icon' => 'fa-route'
            ],
        ],
    ],
    [
        'id' => 'assets',
        'filter_label' => 'Assets',
        'title' => 'Asset & Cash Tracking',
        'description' => 'Pair physical inventory with banking activity for compliance.',
        'icon' => 'fa-boxes-stacked',
        'badge' => 'Logistics',
        'accent' => 'warning',
        'reports' => [
            [
                'label' => 'Physical Assets',
                'description' => 'Equipment and club property inventory.',
                'url' => 'reports/physical_assets_report.php',
                'meta' => 'Insurance ready',
                'icon' => 'fa-boxes'
            ],
            [
                'label' => 'Asset Listing',
                'description' => 'Filterable list with depreciation checkpoints.',
                'url' => 'reports/asset_listing.php',
                'meta' => 'Condition check',
                'icon' => 'fa-clipboard-check'
            ],
            [
                'label' => 'Cash Account Activity',
                'description' => 'Monthly inflow/outflow by bank account.',
                'url' => 'reports/cash_account_report.php',
                'meta' => 'Bank rec support',
                'icon' => 'fa-piggy-bank'
            ],
            [
                'label' => 'YTD Cash Flow',
                'description' => 'Track cumulative burn or surplus each year.',
                'url' => 'reports/ytd_cash_flow.php',
                'meta' => 'YTD trending',
                'icon' => 'fa-wave-square'
            ],
        ],
    ],
    [
        'id' => 'donations',
        'filter_label' => 'Donations',
        'title' => 'Donations & Compliance',
        'description' => 'Ready-to-send donor receipts and stewardship exports.',
        'icon' => 'fa-hand-holding-heart',
        'badge' => 'Outreach',
        'accent' => 'info',
        'reports' => [
            [
                'label' => 'Donor List',
                'description' => 'Full roster with contact references.',
                'url' => 'reports/donor_list.php',
                'meta' => 'Stewardship',
                'icon' => 'fa-users'
            ],
            [
                'label' => 'Donation Summary',
                'description' => 'Aggregate totals by campaign or fund.',
                'url' => 'reports/donation_summary.php',
                'meta' => 'Campaign view',
                'icon' => 'fa-chart-pie'
            ],
            [
                'label' => 'Donation Receipts',
                'description' => 'Batch-print acknowledgement letters.',
                'url' => 'reports/donation_receipts.php',
                'meta' => 'Ready to mail',
                'icon' => 'fa-envelope-open-text'
            ],
            [
                'label' => 'Annual Donor Statement',
                'description' => 'Single PDF per donor for tax season.',
                'url' => 'reports/annual_donor_statement.php',
                'meta' => 'IRS compliant',
                'icon' => 'fa-file-signature'
            ],
        ],
    ],
];

$reportCollectionFilters = array_map(static function (array $collection) {
    return [
        'id' => $collection['id'],
        'label' => $collection['filter_label'] ?? $collection['title'],
    ];
}, $reportCollections);

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
                                <a href="/accounting/reports/financial_statements.php" class="btn btn-warning btn-sm me-2">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>Financial Statements
                                </a>
                                <a href="reports/generate_report.php" class="btn btn-primary btn-sm">
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
                                                                <a href="<?= htmlspecialchars($report['view_url']) ?>" class="btn btn-outline-primary" title="View">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="<?= htmlspecialchars($report['download_url']) ?>" class="btn btn-outline-success" title="Download">
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
                                                        <a href="<?= htmlspecialchars($reportItem['url']) ?>" class="btn btn-outline-primary btn-sm">
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
                        <a href="reports/income_statement.php?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-chart-line me-2"></i>Income Statement
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/expense_report.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" class="btn btn-outline-danger w-100">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Expense Report
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/cash_account_report.php?month=<?= date('Y-m') ?>" class="btn btn-outline-info w-100">
                            <i class="fas fa-money-bill-wave me-2"></i>Cash Account
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/monthly_income_report.php?month=<?= date('Y-m') ?>" class="btn btn-outline-success w-100">
                            <i class="fas fa-dollar-sign me-2"></i>Monthly Income
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/ytd_income_statement.php?year=<?= date('Y') ?>" class="btn btn-outline-warning w-100">
                            <i class="fas fa-calendar-alt me-2"></i>YTD Income
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/YTD_cash_report.php?year=<?= date('Y') ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-wallet me-2"></i>YTD Cash
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/physical_assets_report.php" class="btn btn-outline-dark w-100">
                            <i class="fas fa-boxes me-2"></i>Physical Assets
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="reports/generate_report.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle me-2"></i>Custom Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-header bg-light border-0 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-table me-2 text-primary"></i>Available Reports</h5>
                    <small class="text-muted">Filtered view updates with the controls above.</small>
                </div>
                <div class="d-flex gap-2 mt-2 mt-sm-0">
                    <a href="reports/generate_report.php" class="btn btn-success btn-sm">
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
                                                <a href="reports/view_report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="reports/download_report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline-success" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="reports/email_report.php?id=<?= urlencode($report['id']) ?>" class="btn btn-outline-primary" title="Email">
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

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initReportFilters();
            initReportCollections();

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