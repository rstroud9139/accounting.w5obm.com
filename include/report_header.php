<?php

/**
 * Shared Report Header Renderer
 * Usage: include this file then call renderReportHeader($title, $subtitle, $meta)
 */

if (!function_exists('renderReportHeader')) {
    function renderReportHeader(string $title, string $subtitle = '', array $meta = []): void
    {
        $generatedAt = date('F j, Y g:i A');
        echo '<div class="d-flex align-items-center mb-4">';
        echo '  <img src="' . (defined('BASE_URL') ? BASE_URL : '/') . 'images/badges/club_logo.png" alt="W5OBM Logo" class="img-card-175">';
        echo '  <div class="ms-3">';
        echo '    <h2 class="mb-1">' . htmlspecialchars($title) . '</h2>';
        if ($subtitle !== '') {
            echo '    <p class="mb-1">' . htmlspecialchars($subtitle) . '</p>';
        }
        // Meta line: key: value, comma-separated
        $metaParts = [];
        foreach ($meta as $k => $v) {
            if ($v === '' || $v === null) continue;
            $metaParts[] = htmlspecialchars($k) . ': ' . htmlspecialchars((string)$v);
        }
        $metaLine = implode(', ', $metaParts);
        echo '    <small class="text-muted">Generated: ' . $generatedAt . ($metaLine ? ' | ' . $metaLine : '') . '</small>';
        echo '  </div>';
        echo '</div>';
    }
}
