<?php
// Accounting export bridge to shared admin exporter
// Provides a stable API so accounting can become a separate site later.

if (!function_exists('accounting_find_exporter')) {
    function accounting_find_exporter(): ?string
    {
        $candidates = [
            // Current monorepo path from accounting/reports/* files
            __DIR__ . '/../../w5obm.com/administration/reports/report_export.php',
            // Fallback if accounting becomes its own root with local copy
            __DIR__ . '/../administration/reports/report_export.php',
            // Another possible relative when structure shifts
            __DIR__ . '/../../../w5obm.com/administration/reports/report_export.php',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }
}

if (!function_exists('accounting_export')) {
    function accounting_export(string $reportType, array $rows, array $meta): void
    {
        $exporter = accounting_find_exporter();
        if (!$exporter) {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'Export error: exporter not found';
            exit;
        }
        // Shared exporter expects $report_data and $report_meta in scope and $_GET['report_type']
        $report_data = $rows;
        $report_meta = $meta;
        $_GET['report_type'] = $reportType;
        include $exporter;
        exit;
    }
}
