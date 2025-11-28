<?php

require_once __DIR__ . '/premium_hero.php';

/**
 * Shared Report Header Renderer
 * Usage: include this file then call renderReportHeader($title, $subtitle, $meta [, $options])
 */

if (!function_exists('renderReportHeader')) {
    function renderReportHeader(string $title, string $subtitle = '', array $meta = [], array $options = []): void
    {
        $generatedAt = date('F j, Y g:i A');
        $cleanMeta = array_filter($meta, static function ($value) {
            return $value !== '' && $value !== null;
        });

        if (function_exists('renderPremiumHero')) {
            $chips = [];
            foreach ($cleanMeta as $key => $value) {
                $chips[] = sprintf('%s: %s', $key, $value);
            }
            if (empty($chips)) {
                $chips[] = 'Generated: ' . $generatedAt;
            }

            $highlights = [
                [
                    'label' => 'Generated',
                    'value' => $generatedAt,
                    'meta' => 'Local time'
                ],
            ];

            foreach ($cleanMeta as $metaKey => $metaValue) {
                if (count($highlights) >= 4) {
                    break;
                }
                $highlights[] = [
                    'label' => $metaKey,
                    'value' => (string)$metaValue,
                    'meta' => 'Report input'
                ];
            }

            $config = array_merge([
                'eyebrow' => 'Reporting Workspace',
                'title' => $title,
                'subtitle' => $subtitle !== '' ? $subtitle : 'Automated accounting output',
                'description' => '',
                'theme' => 'cobalt',
                'size' => 'compact',
                'media_mode' => 'none',
                'chips' => $chips,
                'highlights' => $highlights,
            ], $options);

            renderPremiumHero($config);
            return;
        }

        echo '<div class="d-flex align-items-center mb-4">';
        echo '  <img src="' . (defined('BASE_URL') ? BASE_URL : '/') . 'images/badges/club_logo.png" alt="W5OBM Logo" class="img-card-175">';
        echo '  <div class="ms-3">';
        echo '    <h2 class="mb-1">' . htmlspecialchars($title) . '</h2>';
        if ($subtitle !== '') {
            echo '    <p class="mb-1">' . htmlspecialchars($subtitle) . '</p>';
        }
        $metaParts = [];
        foreach ($cleanMeta as $k => $v) {
            $metaParts[] = htmlspecialchars($k) . ': ' . htmlspecialchars((string)$v);
        }
        $metaLine = implode(', ', $metaParts);
        echo '    <small class="text-muted">Generated: ' . $generatedAt . ($metaLine ? ' | ' . $metaLine : '') . '</small>';
        echo '  </div>';
        echo '</div>';
    }
}
