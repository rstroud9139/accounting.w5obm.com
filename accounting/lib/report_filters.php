<?php

require_once __DIR__ . '/helpers.php';

if (!function_exists('accountingNormalizeReportFilters')) {
    function accountingNormalizeReportFilters(array $input, array $availableTypes): array
    {
        $allowedRanges = ['30', '90', '365', 'ytd', 'all'];
        $allowedSorts = ['generated_desc', 'generated_asc', 'type_asc', 'type_desc'];
        $allowedGroups = ['none', 'type', 'month'];

        $type = sanitizeInput($input['type'] ?? 'all', 'string');
        $range = sanitizeInput($input['range'] ?? '90', 'string');
        $search = sanitizeInput($input['search'] ?? '', 'string');
        $sort = sanitizeInput($input['sort'] ?? 'generated_desc', 'string');
        $group = sanitizeInput($input['group'] ?? 'none', 'string');

        if (!in_array($range, $allowedRanges, true)) {
            $range = '90';
        }
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'generated_desc';
        }
        if (!in_array($group, $allowedGroups, true)) {
            $group = 'none';
        }

        return [
            'type' => $type,
            'range' => $range,
            'search' => $search,
            'sort' => $sort,
            'group' => $group,
        ];
    }
}

if (!function_exists('accountingApplyReportFilters')) {
    function accountingApplyReportFilters(array $reports, array $filters): array
    {
        return array_values(array_filter($reports, static function ($report) use ($filters) {
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
    }
}

if (!function_exists('accountingSortReports')) {
    function accountingSortReports(array $reports, string $sort): array
    {
        if (empty($reports)) {
            return $reports;
        }

        usort($reports, static function ($a, $b) use ($sort) {
            $timeA = strtotime($a['generated_at'] ?? '') ?: 0;
            $timeB = strtotime($b['generated_at'] ?? '') ?: 0;
            switch ($sort) {
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

        return $reports;
    }
}

if (!function_exists('accountingGetPresetReportTypes')) {
    function accountingGetPresetReportTypes(array $availableTypes, int $limit = 3): array
    {
        return array_slice(array_values($availableTypes), 0, max(0, $limit));
    }
}
