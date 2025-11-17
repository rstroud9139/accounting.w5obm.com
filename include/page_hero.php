
<?php
/**
 * Public Page Hero Utilities
 * Provides consistent hero/banner output for public-facing pages.
 */

if (!function_exists('w5obm_enqueue_theme_css')) {
    function w5obm_enqueue_theme_css(): void
    {
        if (defined('W5OBM_THEME_CSS_LOADED')) {
            return;
        }

        $cssPath = __DIR__ . '/../css/w5obm-main.css';
        $version = file_exists($cssPath) ? filemtime($cssPath) : time();
        echo '<link rel="stylesheet" href="' . BASE_URL . 'css/w5obm-main.css?v=' . $version . '">';
        define('W5OBM_THEME_CSS_LOADED', true);
    }
}

if (!function_exists('renderPublicPageHero')) {
    function renderPublicPageHero(array $config = []): void
    {
        $defaults = [
            'title' => 'W5OBM',
            'subtitle' => 'Serving Mississippi and the Mid-South since 1998.',
            'badge' => '',
            'icon' => '',
            'actions' => [],
            'stats' => [],
            'variant' => 'navy',
            'eyebrow' => '',
            'show_logo' => true, // new: toggle display of hero logo
            'logo_size' => 'large', // small|normal|large passed to hero logo renderer
        ];

        $config = array_merge($defaults, $config);
        $variant = preg_replace('/[^a-z0-9_-]/i', '', $config['variant']);
        $sectionClasses = 'page-hero page-hero--' . ($variant ?: 'navy');
        $title = htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars($config['subtitle'], ENT_QUOTES, 'UTF-8');
        $badge = trim($config['badge']);
        $icon = trim($config['icon']);

        echo '<section class="' . $sectionClasses . '">';
        echo '  <div class="container hero-content py-5">';
        echo '    <div class="row g-4 align-items-center">';
        // Left column (title + optional logo)
        echo '      <div class="col-lg-7">';
        if (!empty($config['show_logo'])) {
            // Include standardized hero logo component if available
            @require_once __DIR__ . '/hero_logo.php';
            if (function_exists('renderHeroLogo')) {
                echo '<div class="mb-3">';
                renderHeroLogo('', 'W5OBM Amateur Radio Club', $config['logo_size']);
                echo '</div>';
            }
        }
        if ($badge !== '') {
            echo '        <span class="hero-badge">';
            if ($icon !== '') {
                echo '<i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-2"></i>';
            }
            echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8');
            echo '</span>';
        }
        if (!empty($config['eyebrow'])) {
            echo '<p class="hero-eyebrow text-uppercase small mb-2">' . htmlspecialchars($config['eyebrow'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '        <h1 class="display-5 fw-bold mt-2">' . $title . '</h1>';
        if ($subtitle !== '') {
            echo '        <p class="lead text-white-75 mb-4">' . $subtitle . '</p>';
        }

        if (!empty($config['actions'])) {
            echo '        <div class="hero-actions d-flex flex-wrap gap-3">';
            foreach ($config['actions'] as $action) {
                $actionLabel = htmlspecialchars($action['label'] ?? 'Learn more', ENT_QUOTES, 'UTF-8');
                $actionUrl = htmlspecialchars($action['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                $actionStyle = htmlspecialchars($action['style'] ?? 'btn-light text-primary fw-bold', ENT_QUOTES, 'UTF-8');
                $actionIcon = $action['icon'] ?? '';
                $target = !empty($action['external']) ? ' target="_blank" rel="noopener"' : '';
                echo '          <a class="btn ' . $actionStyle . '" href="' . $actionUrl . '"' . $target . '>';
                if ($actionIcon !== '') {
                    echo '<i class="fas ' . htmlspecialchars($actionIcon, ENT_QUOTES, 'UTF-8') . ' me-2"></i>';
                }
                echo $actionLabel . '</a>';
            }
            echo '        </div>';
        }
        echo '      </div>';

        if (!empty($config['stats'])) {
            echo '      <div class="col-lg-5">';
            echo '        <div class="hero-stats">';
            foreach ($config['stats'] as $stat) {
                $value = htmlspecialchars($stat['value'] ?? '', ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($stat['label'] ?? '', ENT_QUOTES, 'UTF-8');
                $meta = htmlspecialchars($stat['meta'] ?? '', ENT_QUOTES, 'UTF-8');
                echo '          <div class="hero-stat">';
                if ($value !== '') {
                    echo '            <div class="hero-stat-value">' . $value . '</div>';
                }
                if ($label !== '') {
                    echo '            <div class="hero-stat-label">' . $label . '</div>';
                }
                if ($meta !== '') {
                    echo '            <div class="hero-stat-meta">' . $meta . '</div>';
                }
                echo '          </div>';
            }
            echo '        </div>';
            echo '      </div>';
        }

        echo '    </div>';
        echo '  </div>';
        echo '</section>';
    }
}
