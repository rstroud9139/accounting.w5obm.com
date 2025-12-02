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
        $roleOverride = false;
        if (function_exists('isSuperAdmin') && isSuperAdmin($userId)) {
            $roleOverride = true;
        } elseif (function_exists('isAdmin') && isAdmin($userId)) {
            $roleOverride = true;
        }

        $hasPermissionCheck = static function (?string $permission) use ($hasPermissionFn, $userId) {
            if (!$permission || !$hasPermissionFn) {
                return true;
            }
            try {
                return (bool)($hasPermissionFn)($userId, $permission);
            } catch (Exception $e) {
                error_log('Workspace nav permission error: ' . $e->getMessage());
                return false;
            }
        };

        $hasAnyPermission = static function (?array $permissions = null) use ($hasPermissionCheck, $roleOverride) {
            if ($roleOverride || empty($permissions)) {
                return true;
            }
            foreach ($permissions as $permission) {
                if ($hasPermissionCheck($permission)) {
                    return true;
                }
            }
            return false;
        };

        $canManage = $options['can_manage'] ?? $hasAnyPermission(['accounting_manage']);
        $canAdd = $options['can_add'] ?? $hasAnyPermission(['accounting_add', 'accounting_manage']);

        $navItems = [
            'workspace' => array_values(array_filter([
                $canAdd ? [
                    'key' => 'transactions-new',
                    'label' => 'Add Transaction',
                    'icon' => 'fa-plus-circle text-success',
                    'url' => route('transaction_new'),
                    'chevron' => true,
                    'permissions' => ['accounting_add', 'accounting_manage'],
                ] : null,
                [
                    'key' => 'dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'fa-chart-pie text-primary',
                    'url' => route('dashboard'),
                    'chevron' => true,
                    'permissions' => ['accounting_view', 'accounting_manage'],
                ],
                [
                    'key' => 'transactions',
                    'label' => 'Transactions',
                    'icon' => 'fa-exchange-alt text-secondary',
                    'url' => route('transactions'),
                    'chevron' => true,
                    'permissions' => ['accounting_view', 'accounting_manage'],
                ],
                [
                    'key' => 'reports',
                    'label' => 'Reports',
                    'icon' => 'fa-chart-bar text-success',
                    'url' => '/accounting/reports_dashboard.php',
                    'chevron' => true,
                    'permissions' => ['accounting_view', 'accounting_manage'],
                ],
                [
                    'key' => 'imports',
                    'label' => 'Imports',
                    'icon' => 'fa-file-import text-info',
                    'url' => route('import'),
                    'chevron' => true,
                    'permissions' => ['accounting_manage'],
                ],
                [
                    'key' => 'ledger',
                    'label' => 'Chart of Accounts',
                    'icon' => 'fa-book text-warning',
                    'url' => route('accounts'),
                    'chevron' => true,
                    'permissions' => ['accounting_manage'],
                ],
                [
                    'key' => 'budgets',
                    'label' => 'Budgets',
                    'icon' => 'fa-wallet text-secondary',
                    'url' => '/accounting/budgets/',
                    'chevron' => true,
                    'permissions' => ['accounting_view', 'accounting_manage'],
                ],
                [
                    'key' => 'categories',
                    'label' => 'Categories',
                    'icon' => 'fa-tags text-info',
                    'url' => '/accounting/categories/',
                    'chevron' => true,
                    'permissions' => ['accounting_view', 'accounting_manage'],
                ],
                [
                    'key' => 'vendors',
                    'label' => 'Vendors',
                    'icon' => 'fa-store text-danger',
                    'url' => '/accounting/vendors/',
                    'chevron' => true,
                    'permissions' => ['accounting_view', 'accounting_manage'],
                ],
            ])),
            'other' => [
                [
                    'key' => 'assets',
                    'label' => 'Assets',
                    'icon' => 'fa-boxes text-primary',
                    'url' => '/accounting/assets/list.php',
                    'chevron' => false,
                    'permissions' => ['accounting_view', 'accounting_manage', 'accounting_add'],
                ],
                [
                    'key' => 'donations',
                    'label' => 'Donations',
                    'icon' => 'fa-heart text-danger',
                    'url' => '/accounting/donations/index.php',
                    'chevron' => false,
                    'permissions' => ['accounting_view', 'accounting_manage'],
                ],
            ],
        ];

        $navItems['workspace'] = array_values(array_filter($navItems['workspace'], static function ($item) use ($hasAnyPermission) {
            return $hasAnyPermission($item['permissions'] ?? []);
        }));
        $navItems['other'] = array_values(array_filter($navItems['other'], static function ($item) use ($hasAnyPermission) {
            return $hasAnyPermission($item['permissions'] ?? []);
        }));

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

if (!function_exists('accounting_quick_cash_contact_name')) {
    function accounting_quick_cash_contact_name(): string
    {
        return 'Anonymous - Cash Collections';
    }
}

if (!function_exists('accounting_quick_cash_resolve_context')) {
    /**
     * Resolve quick-post cash donation context: categories, accounts, defaults, contact
     * @param mysqli|null $conn Database connection
     * @return array Context data for quick-post widget
     */
    function accounting_quick_cash_resolve_context($conn = null): array
    {
        $db = $conn instanceof mysqli ? $conn : accounting_db_connection();

        $context = [
            'contact_id' => null,
            'contact_name' => accounting_quick_cash_contact_name(),
            'categories' => [],
            'accounts' => [],
            'default_category_id' => null,
            'default_account_id' => null,
        ];

        if (!$db instanceof mysqli) {
            return $context;
        }

        try {
            // Ensure anonymous contact exists
            $contactName = $context['contact_name'];
            $stmt = $db->prepare("SELECT id FROM acc_contacts WHERE name = ? LIMIT 1");
            $stmt->bind_param('s', $contactName);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row) {
                $context['contact_id'] = (int)$row['id'];
            } else {
                // Create the anonymous contact
                $stmt = $db->prepare("\
                    INSERT INTO acc_contacts (name, email, notes, created_at) 
                    VALUES (?, NULL, 'System-generated contact for quick-post cash donations', NOW())
                ");
                $stmt->bind_param('s', $contactName);
                if ($stmt->execute()) {
                    $context['contact_id'] = (int)$db->insert_id;
                }
                $stmt->close();
            }

            // Fetch income categories
            $stmt = $db->prepare("\
                SELECT id, name, type, description 
                FROM acc_transaction_categories 
                WHERE type = 'Income'
                ORDER BY 
                    CASE WHEN name LIKE '%Donation%' THEN 1 
                         WHEN name LIKE '%Contribution%' THEN 2 
                         ELSE 3 END,
                    name
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $context['categories'][] = $row;
                if (
                    !$context['default_category_id'] &&
                    (stripos($row['name'], 'Donation') !== false || stripos($row['name'], 'Contribution') !== false)
                ) {
                    $context['default_category_id'] = (int)$row['id'];
                }
            }
            $stmt->close();

            // If no donation category found, set first income category as default
            if (!$context['default_category_id'] && !empty($context['categories'])) {
                $context['default_category_id'] = (int)$context['categories'][0]['id'];
            }

            // Fetch cash/checking accounts
            $stmt = $db->prepare("\
                SELECT id, name, account_number, account_type 
                FROM acc_ledger_accounts 
                WHERE active = 1 
                  AND account_type = 'Asset'
                  AND (name LIKE '%Cash%' OR name LIKE '%Checking%' OR account_number LIKE '11%')
                ORDER BY 
                    CASE WHEN name LIKE '%Cash%' THEN 1 
                         WHEN name LIKE '%Checking%' THEN 2 
                         ELSE 3 END,
                    name
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $context['accounts'][] = $row;
                if (!$context['default_account_id']) {
                    $context['default_account_id'] = (int)$row['id'];
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error resolving quick-post context: " . $e->getMessage());
        }

        return $context;
    }
}
