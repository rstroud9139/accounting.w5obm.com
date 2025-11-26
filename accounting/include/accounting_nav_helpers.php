<?php

if (!function_exists('accounting_locate_root')) {
    function accounting_locate_root(string $startDir): string
    {
        $dir = $startDir;
        while ($dir && !is_dir($dir . '/app')) {
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        return $dir;
    }
}

if (!function_exists('accounting_head_assets')) {
    function accounting_head_assets(): void
    {
        echo '<link rel="stylesheet" href="/accounting/app/assets/accounting.css">' . PHP_EOL;
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $query = http_build_query(array_merge(['route' => $name], $params));
        return '/accounting/app/index.php?' . $query;
    }
}

if (!function_exists('accounting_render_nav')) {
    function accounting_render_nav(string $startDir): void
    {
        $root = accounting_locate_root($startDir);
        $navPath = $root . '/app/views/partials/accounting_nav.php';
        if (file_exists($navPath)) {
            include $navPath;
        }
    }
}

if (!function_exists('accounting_resolve_user_id')) {
    function accounting_resolve_user_id(): ?int
    {
        if (function_exists('getCurrentUserId')) {
            return getCurrentUserId();
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('accounting_render_workspace_nav')) {
    function accounting_render_workspace_nav(string $activeKey = 'dashboard', array $options = []): void
    {
        $userId = $options['user_id'] ?? accounting_resolve_user_id();
        $hasPermissionFn = function_exists('hasPermission') ? 'hasPermission' : null;
        $canManage = $options['can_manage'] ?? ($hasPermissionFn ? ($hasPermissionFn)($userId, 'accounting_manage') : true);
        $canAdd = $options['can_add'] ?? ($canManage || ($hasPermissionFn ? ($hasPermissionFn)($userId, 'accounting_add') : true));

        $navItems = [
            'workspace' => array_values(array_filter([
                $canAdd ? [
                    'key' => 'transactions-new',
                    'label' => 'Add Transaction',
                    'icon' => 'fa-plus-circle text-success',
                    'url' => '/accounting/transactions/add_transaction.php',
                    'chevron' => true,
                ] : null,
                [
                    'key' => 'dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'fa-chart-pie text-primary',
                    'url' => '/accounting/dashboard.php',
                    'chevron' => true,
                ],
                [
                    'key' => 'transactions',
                    'label' => 'Transactions',
                    'icon' => 'fa-exchange-alt text-secondary',
                    'url' => '/accounting/transactions/transactions.php',
                    'chevron' => true,
                ],
                [
                    'key' => 'reports',
                    'label' => 'Reports',
                    'icon' => 'fa-chart-bar text-success',
                    'url' => '/accounting/reports_dashboard.php',
                    'chevron' => true,
                ],
                [
                    'key' => 'ledger',
                    'label' => 'Chart of Accounts',
                    'icon' => 'fa-book text-warning',
                    'url' => '/accounting/ledger/',
                    'chevron' => true,
                ],
                [
                    'key' => 'categories',
                    'label' => 'Categories',
                    'icon' => 'fa-tags text-info',
                    'url' => '/accounting/categories/',
                    'chevron' => true,
                ],
                [
                    'key' => 'vendors',
                    'label' => 'Vendors',
                    'icon' => 'fa-store text-danger',
                    'url' => '/accounting/vendors/',
                    'chevron' => true,
                ],
            ])),
            'other' => [
                [
                    'key' => 'assets',
                    'label' => 'Assets',
                    'icon' => 'fa-boxes text-primary',
                    'url' => '/accounting/assets/list.php',
                    'chevron' => false,
                ],
                [
                    'key' => 'donations',
                    'label' => 'Donations',
                    'icon' => 'fa-heart text-danger',
                    'url' => '/accounting/donations/index.php',
                    'chevron' => false,
                ],
            ],
        ];

        echo '<nav class="accounting-workspace-nav bg-white border rounded shadow-sm h-100">';
        echo '<div class="px-3 py-2 border-bottom"><span class="text-muted text-uppercase small">Workspace</span></div>';
        echo '<div class="list-group list-group-flush">';
        foreach ($navItems['workspace'] as $item) {
            $isActive = $activeKey === $item['key'];
            $classes = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            if ($isActive) {
                $classes .= ' active';
            }
            echo '<a class="' . $classes . '" href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<span><i class="fas ' . htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') . ' me-2"></i>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            if ($item['chevron']) {
                echo '<i class="fas fa-chevron-right small text-muted"></i>';
            }
            echo '</a>';
        }
        echo '<div class="list-group-item small text-muted text-uppercase">Other</div>';
        foreach ($navItems['other'] as $item) {
            $isActive = $activeKey === $item['key'];
            $classes = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            if ($isActive) {
                $classes .= ' active';
            }
            echo '<a class="' . $classes . '" href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<span><i class="fas ' . htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') . ' me-2"></i>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            if ($item['chevron']) {
                echo '<i class="fas fa-chevron-right small text-muted"></i>';
            }
            echo '</a>';
        }
        echo '</div>';
        echo '</nav>';
    }
}
