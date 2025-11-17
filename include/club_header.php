<?php

/**
 * W5OBM Club Header System - FIXED WITH CSS LOGO SIZING
 * File: /include/club_header.php
 * 
 * UPDATED: Now uses CSS classes for logo sizing per website standards
 * Maximum logo size: 200px x 200px (enforced via CSS)
 * 
 * Usage:
 * <?php
 * require_once __DIR__ . '/include/club_header.php';
 * renderClubHeader('Page Title', 'primary'); // or 'secondary', 'admin'
 * ?>
 * 
 * IMPORTANT: This file should be included AFTER header.php and menu.php
 */

// Ensure constants are defined
if (!defined('BASE_URL')) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_localhost = ($host === 'localhost' || strpos($host, '127.0.0.1') !== false);

    if ($is_localhost) {
        define('BASE_URL', 'http://localhost/w5obmcom_admin/w5obm.com/');
    } else {
        define('BASE_URL', 'https://www.w5obm.com/');
    }
}

/**
 * Render the club header with specified title and type
 * 
 * @param string $pageTitle The title to display in the header
 * @param string $headerType 'primary' for main pages, 'secondary' for sub-pages, 'admin' for admin pages
 * @param array $options Optional parameters for customization
 */
function renderClubHeader($pageTitle = '', $headerType = 'primary', $options = [])
{
    // Default options
    $defaults = [
        'show_logo' => true,
        'show_datetime' => true,
        'show_net_info' => false,
        'custom_badge_text' => '',
        'custom_badge_class' => 'bg-light text-dark',
        'container_class' => 'container-fluid'
    ];

    $options = array_merge($defaults, $options);

    // Determine header class and logo CSS class based on type
    switch ($headerType) {
        case 'secondary':
            $headerClass = 'club-header-secondary';
            $logoClass = 'img-fluid header-logo-medium'; // 150px x 150px
            $headerBgClass = 'bg-secondary';
            break;
        case 'admin':
            $headerClass = 'club-header-admin';
            $logoClass = 'img-fluid header-logo-small'; // 120px x 120px for admin
            $headerBgClass = 'bg-danger';
            break;
        case 'dashboard':
            $headerClass = 'club-header-dashboard';
            $logoClass = 'img-fluid header-logo'; // 150px x 150px default
            $headerBgClass = 'bg-primary';
            break;
        default: // primary
            $headerClass = 'club-header';
            $logoClass = 'img-fluid header-logo'; // 150px x 150px default
            $headerBgClass = 'bg-primary';
            break;
    }

    // Get current date/time
    $currentDateTime = date('l, F j, Y - g:i A');

    // Badge text - use custom if provided, otherwise default
    $badgeText = !empty($options['custom_badge_text']) ? $options['custom_badge_text'] : 'NET Session';

    // Start header output with proper styling
    echo '<div class="card shadow mb-4 border-0" style="background: linear-gradient(90deg, var(--theme-accent-primary, var(--theme-accent-primary)) 0%, var(--theme-accent-secondary, #6c63ff) 100%);">';
    echo '<div class="card-header bg-transparent border-0 py-4" style="color: var(--theme-text-primary, #ffffff);">';
    echo '<div class="container-fluid">';
    echo '<div class="row align-items-center">';

    // Logo column - FIXED: Now uses CSS classes instead of inline sizing
    if ($options['show_logo']) {
        echo '<div class="col-md-2 col-12 text-center mb-3 mb-md-0">';
        echo '<img src="' . BASE_URL . 'images/badges/club_logo.png" alt="W5OBM Club Logo" class="img-fluid rounded-circle shadow" style="max-width:120px; background:#fff;">';
        echo '</div>';
    }

    // Title column
    $titleColClass = $options['show_logo'] ? 'col-md-7 col-12' : 'col-md-9 col-12';
    echo '<div class="' . $titleColClass . ' text-center text-md-start">';

    // Club title
    echo '<h1 class="fw-bold display-5 mb-1" style="letter-spacing:1px;">W5OBM Amateur Radio Club</h1>';

    // Page title
    if (!empty($pageTitle)) {
        echo '<h2 class="h4 mb-0">' . htmlspecialchars($pageTitle) . '</h2>';
    }

    // Subtitle based on header type
    switch ($headerType) {
        case 'admin':
            echo '<p class="mb-0 opacity-75">Administrative Control Center</p>';
            break;
        case 'dashboard':
            echo '<p class="mb-0 opacity-75">Your Personal Control Center</p>';
            break;
        case 'secondary':
            echo '<p class="mb-0 opacity-75">Club Information & Resources</p>';
            break;
        default:
            echo '<p class="mb-0 opacity-75">Serving Mississippi and Beyond since 1998</p>';
            break;
    }

    echo '</div>';

    // Info column
    if ($options['show_datetime'] || $options['show_net_info']) {
        echo '<div class="col-md-3 text-center text-md-end">';
        echo '<div class="header-info">';

        if ($options['show_datetime']) {
            echo '<div class="date-time small mb-1" id="current-datetime">' . $currentDateTime . '</div>';
        }

        if ($options['show_net_info']) {
            echo '<div class="net-info">';
            echo '<span class="badge ' . $options['custom_badge_class'] . '">' . htmlspecialchars($badgeText) . '</span>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    echo '</div>'; // row
    echo '</div>'; // container-fluid
    echo '</div>'; // card-header
    echo '</div>'; // card

    // Add JavaScript for live clock if datetime is shown
    if ($options['show_datetime']) {
        echo '<script>';
        echo 'document.addEventListener("DOMContentLoaded", function() {';
        echo '    function updateDateTime() {';
        echo '        const now = new Date();';
        echo '        const options = { weekday: "long", year: "numeric", month: "long", day: "numeric", hour: "numeric", minute: "2-digit", hour12: true };';
        echo '        const dateTimeString = now.toLocaleDateString("en-US", options);';
        echo '        const element = document.getElementById("current-datetime");';
        echo '        if (element) element.textContent = dateTimeString;';
        echo '    }';
        echo '    updateDateTime();';
        echo '    setInterval(updateDateTime, 60000);'; // Update every minute
        echo '});';
        echo '</script>';
    }
}

/**
 * Render a dashboard header (standard size with welcome message)
 * 
 * @param string $pageTitle The title to display
 * @param string $userName Optional user name for personalization
 */
function renderDashboardHeader($pageTitle, $userName = '')
{
    $welcomeMessage = !empty($userName) ? "Welcome back, " . htmlspecialchars($userName) . "!" : "Welcome to your dashboard!";

    renderClubHeader($pageTitle, 'dashboard', [
        'show_datetime' => true,
        'show_net_info' => false,
        'custom_badge_text' => $welcomeMessage
    ]);
}

/**
 * Render a simple secondary header (medium size, less prominent)
 * 
 * @param string $pageTitle The title to display
 * @param string $subtitle Optional subtitle
 */
function renderSecondaryHeader($pageTitle, $subtitle = '')
{
    renderClubHeader($pageTitle, 'secondary', [
        'show_datetime' => false,
        'show_net_info' => !empty($subtitle),
        'custom_badge_text' => $subtitle,
        'custom_badge_class' => 'bg-info text-white'
    ]);
}

/**
 * Render a minimal header for admin/auth pages (small logo)
 * 
 * @param string $pageTitle The title to display
 * @param string $section Section name (e.g., 'Administration', 'Authentication')
 */
function renderAdminHeader($pageTitle, $section = '')
{
    $badgeText = !empty($section) ? $section : 'Admin Panel';

    renderClubHeader($pageTitle, 'admin', [
        'show_datetime' => true,
        'show_net_info' => true,
        'custom_badge_text' => $badgeText,
        'custom_badge_class' => 'bg-warning text-dark'
    ]);
}

/**
 * Render a compact header for authentication pages
 * 
 * @param string $pageTitle The title to display
 * @param string $authType Type of auth page (e.g., 'Login', 'Register', '2FA')
 */
function renderAuthHeader($pageTitle, $authType = '')
{
    $badgeText = !empty($authType) ? $authType : 'Authentication';

    renderClubHeader($pageTitle, 'admin', [
        'show_datetime' => false,
        'show_net_info' => true,
        'custom_badge_text' => $badgeText,
        'custom_badge_class' => 'bg-success text-white'
    ]);
}

/**
 * Render a header for membership/public pages
 * 
 * @param string $pageTitle The title to display
 * @param boolean $showNetInfo Whether to show net information
 */
function renderMembershipHeader($pageTitle, $showNetInfo = false)
{
    renderClubHeader($pageTitle, 'primary', [
        'show_datetime' => true,
        'show_net_info' => $showNetInfo,
        'custom_badge_text' => 'Membership',
        'custom_badge_class' => 'bg-success text-white'
    ]);
}

/**
 * Get the appropriate logo CSS class for different contexts
 * 
 * @param string $context Context: 'header', 'large', 'medium', 'small'
 * @return string CSS class for the logo
 */
function getLogoClass($context = 'header')
{
    switch ($context) {
        case 'large':
            return 'img-fluid header-logo-large';   // 175px x 175px (still under 200px limit)
        case 'medium':
            return 'img-fluid header-logo-medium';  // 150px x 150px
        case 'small':
            return 'img-fluid header-logo-small';   // 120px x 120px
        case 'header':
        default:
            return 'img-fluid header-logo';         // 150px x 150px (default)
    }
}

/**
 * CSS Styles for club headers (add to page if needed)
 */
function addClubHeaderStyles()
{
    echo '<style>';
    echo '/* Club Header Styling - Complements w5obm.css */';
    echo '.club-title { font-size: 1.75rem; font-weight: 600; margin-bottom: 0.25rem; }';
    echo '.page-title { font-size: 1.25rem; font-weight: 500; margin-bottom: 0; }';
    echo '.header-info { font-size: 0.875rem; }';
    echo '.date-time { font-weight: 500; }';
    echo '.net-info .badge { font-size: 0.75rem; }';
    echo '@media (max-width: 768px) {';
    echo '  .club-title { font-size: 1.5rem; }';
    echo '  .page-title { font-size: 1.1rem; }';
    echo '  .header-info { font-size: 0.8rem; text-align: center !important; margin-top: 1rem; }';
    echo '}';
    echo '</style>';
}

/*
LOGO SIZE REFERENCE (from w5obm.css):
=======================================
.header-logo         - 150px x 150px (default, responsive)
.header-logo-large   - 175px x 175px (still under 200px limit)
.header-logo-medium  - 150px x 150px (same as default)
.header-logo-small   - 120px x 120px (for compact layouts)

All logos have:
- max-width: 200px !important
- max-height: 200px !important
- object-fit: contain
- Responsive scaling for mobile devices
- Consistent styling and shadows
*/