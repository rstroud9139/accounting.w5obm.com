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
