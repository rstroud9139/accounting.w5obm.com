<?php

/**
 * W5OBM Hero Logo Component
 * File: /include/hero_logo.php
 * Purpose: Standardized hero logo HTML for consistent display across all pages
 * 
 * Usage:
 * include __DIR__ . '/../include/hero_logo.php';
 * // Optional per-page size control (before calling renderHeroLogo):
 * // $hero_logo_size can be 'small' | 'normal' | 'large' or a numeric pixel value (e.g., 180)
 * // Example:
 * //   $hero_logo_size = 'small';          // uses CSS class hero-logo-small (120px)
 * //   $hero_logo_size = 'large';          // uses CSS class hero-logo-large (250px)
 * //   $hero_logo_size = 180;              // sets inline width/height to 180px
 * // Optional per-page show/hide control (before calling renderHeroLogo):
 * //   $hero_logo_show = false;            // hides the hero logo on this page
 * //   // alias supported as well: $show_hero_logo = false;
 * // Then render (existing calls continue to work without changes):
 * renderHeroLogo('optional-css-classes', 'optional-custom-alt-text');
 */

/**
 * Render the standardized W5OBM hero logo
 * 
 * @param string $additional_classes Optional CSS classes to add to the logo
 * @param string $alt_text Optional custom alt text (defaults to standard club name)
 * @param string $size Optional size variant: 'small', 'normal', 'large' (default: 'normal')
 */
function renderHeroLogo($additional_classes = '', $alt_text = '', $size = 'normal')
{
    // Page-level opt-out: if either $hero_logo_show or $show_hero_logo is explicitly false/0/'0'/'false', skip rendering
    $show_flag = null;
    if (array_key_exists('hero_logo_show', $GLOBALS)) {
        $show_flag = $GLOBALS['hero_logo_show'];
    } elseif (array_key_exists('show_hero_logo', $GLOBALS)) {
        $show_flag = $GLOBALS['show_hero_logo'];
    }
    if ($show_flag !== null) {
        $falsey = ($show_flag === false || $show_flag === 0 || $show_flag === '0' || $show_flag === 'false');
        if ($falsey) {
            return; // do not render logo
        }
    }

    // Determine base path for logo
    $logo_path = '';
    if (strpos($_SERVER['REQUEST_URI'], '/events/') !== false) {
        $logo_path = '../images/badges/club_logo.png';
    } elseif (strpos($_SERVER['REQUEST_URI'], '/members/') !== false) {
        $logo_path = '../images/badges/club_logo.png';
    } elseif (strpos($_SERVER['REQUEST_URI'], '/administration/') !== false) {
        $logo_path = '../images/badges/club_logo.png';
    } elseif (strpos($_SERVER['REQUEST_URI'], '/authentication/') !== false) {
        $logo_path = '../images/badges/club_logo.png';
    } else {
        $logo_path = 'images/badges/club_logo.png';
    }

    // Set default alt text
    if (empty($alt_text)) {
        $alt_text = 'W5OBM Amateur Radio Club';
    }

    // Determine effective size (page-level variable takes precedence when set)
    $effective_size = $size;
    if (isset($GLOBALS['hero_logo_size'])) {
        $effective_size = $GLOBALS['hero_logo_size'];
    }

    // Map size to CSS class or inline style
    $size_class = '';
    $style_css = '';

    // Numeric pixel support (e.g., 180)
    if (is_numeric($effective_size)) {
        $px = max(80, min(400, (int)$effective_size)); // clamp to sensible bounds
        // Scale padding roughly with size (8px..24px range)
        $pad = max(8, min(24, (int)round($px * 0.075)));
        $style_css = "width: {$px}px; height: {$px}px; padding: {$pad}px;";
    } else {
        // String-based sizes
        $sz = strtolower(trim((string)$effective_size));
        if ($sz === 'sm' || $sz === 'small') {
            $size_class = 'hero-logo-small';
        } elseif ($sz === 'lg' || $sz === 'large') {
            $size_class = 'hero-logo-large';
        } else {
            // 'normal' | 'md' | default -> base size (200px) via CSS
            $size_class = '';
        }
    }

    // Combine all classes
    $all_classes = trim("club-hero-logo animate-entrance {$size_class} {$additional_classes}");

    // Output the hero logo HTML
    echo '<div class="hero-logo text-center mb-4">';
    echo '<img src="' . htmlspecialchars($logo_path) . '" ';
    echo 'alt="' . htmlspecialchars($alt_text) . '" ';
    echo 'class="' . htmlspecialchars($all_classes) . '"';
    if ($style_css !== '') {
        echo ' style="' . htmlspecialchars($style_css) . '"';
    }
    echo '>';
    echo '</div>';
}

/**
 * Get the hero logo CSS link tag
 * Call this in the <head> section of pages that use the hero logo
 */
function getHeroLogoCSSLink()
{
    $css_path = '';
    if (
        strpos($_SERVER['REQUEST_URI'], '/events/') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/members/') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/administration/') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/authentication/') !== false
    ) {
        $css_path = '../css/hero-logo-styles.css';
    } else {
        $css_path = 'css/hero-logo-styles.css';
    }

    return '<link rel="stylesheet" href="' . htmlspecialchars($css_path) . '">';
}

/**
 * Quick helper to output just the hero logo with default settings
 */
function echoHeroLogo()
{
    renderHeroLogo();
}

/**
 * Hero logo with animation delay for AOS or other animation libraries
 */
function renderAnimatedHeroLogo($delay = 100, $additional_classes = '', $size = 'normal')
{
    renderHeroLogo($additional_classes . ' animate-entrance', '', $size);
}
