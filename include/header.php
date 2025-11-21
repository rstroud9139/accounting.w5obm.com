<?php

/**
 * EMERGENCY FIXED HEADER.PHP - W5OBM Website
 * File: /include/header.php
 * CRITICAL FIX: Simplified Bootstrap loading to prevent URL concatenation errors
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// If redirected after logout, aggressively clear any lingering session/cookies and prevent caching
if (isset($_GET['logged_out'])) {
    // Clear PHP session data
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        @setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', !empty($_SERVER['HTTPS']), true);
    }
    // Clear any logout marker and common auth cookies (best-effort)
    @setcookie('force_logout', '', time() - 3600, '/');
    @setcookie('remember_token', '', time() - 3600, '/');
    // Strengthen caching policy
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// Include database connection and helper functions
require_once __DIR__ . '/dbconn.php';
require_once __DIR__ . '/helper_functions.php';
require_once __DIR__ . '/page_hero.php';
@require_once __DIR__ . '/theme_utils.php';
@require_once __DIR__ . '/site_settings.php';

// Load .env configuration
$env_file = __DIR__ . '/../config/.env';
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
    if ($env) {
        foreach ($env as $key => $value) {
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Environment-aware BASE_URL definition
if (!defined('BASE_URL')) {
    // Environment detection based on domain name
    $server_name = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

    if (
        strpos($server_name, 'dev.w5obm.com') !== false ||
        strpos($server_name, 'localhost') !== false ||
        strpos(__DIR__, 'dev.w5obm.com') !== false
    ) {
        // Development environment
        define('BASE_URL', '/');
        define('SITE_ENVIRONMENT', 'development');
    } else {
        // Production environment
        define('BASE_URL', '/');
        define('SITE_ENVIRONMENT', 'production');
    }
}

// Set page title if not already set
if (!isset($page_title)) {
    $page_title = "W5OBM Amateur Radio Club";
}

// Enhanced security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy
$csp = "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' " .
    "https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net https://stackpath.bootstrapcdn.com " .
    "https://www.googletagmanager.com https://www.google-analytics.com " .
    "https://cdn.tiny.cloud https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; " .
    "style-src 'self' 'unsafe-inline' " .
    "https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net https://stackpath.bootstrapcdn.com https://fonts.googleapis.com; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net https://stackpath.bootstrapcdn.com; " .
    "img-src 'self' data: https: blob:; " .
    "connect-src 'self' https://api.tiny.cloud; " .
    "frame-src 'self' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://www.google.com;";

header("Content-Security-Policy: $csp");

// THEME DETECTION & THEME VARIABLES
$user_theme_css = null;
$currentThemeKey = 'default';
$branding = null;
if (isset($_SESSION['user_id'])) {
    // Try to load user's theme preference
    if (file_exists(__DIR__ . '/theme_utils.php')) {
        require_once __DIR__ . '/theme_utils.php';
        try {
            $selectedThemeData = getCurrentTheme($_SESSION['user_id']);
            if (!empty($selectedThemeData) && isset($selectedThemeData['css'])) {
                $theme_css = $selectedThemeData['css'];
                // Only use if it's a valid URL
                if (filter_var($theme_css, FILTER_VALIDATE_URL)) {
                    $user_theme_css = $theme_css;
                }
            }
            if (function_exists('getCurrentThemeKey')) {
                $currentThemeKey = getCurrentThemeKey($_SESSION['user_id']);
            }
        } catch (Exception $e) {
            // Ignore theme errors - fall back to default
        }
    }
}
// If no user-specific theme CSS, attempt site default theme for guests or fallback
if (!$user_theme_css) {
    // Load site settings & theme utilities
    if (!function_exists('getSiteDefaultThemeKey')) {
        @require_once __DIR__ . '/site_settings.php';
        @require_once __DIR__ . '/theme_utils.php';
    }
    if (function_exists('getSiteDefaultThemeKey')) {
        try {
            $siteThemeKey = getSiteDefaultThemeKey();
            $themes = loadAvailableThemes();
            if (isset($themes[$siteThemeKey]) && isset($themes[$siteThemeKey]['css'])) {
                $cssCandidate = $themes[$siteThemeKey]['css'];
                if (filter_var($cssCandidate, FILTER_VALIDATE_URL)) {
                    $user_theme_css = $cssCandidate; // apply as effective theme
                }
            }
            $currentThemeKey = $siteThemeKey;
        } catch (Throwable $e) {
            // Non-fatal; keep default bootstrap
        }
    }
}

// Allow safe on-page preview via query parameter without persisting (used by admin Theme Gallery)
try {
    if (isset($_GET['preview_theme'])) {
        $previewKey = preg_replace('/[^a-z0-9_\-]/i', '', (string)$_GET['preview_theme']);
        if ($previewKey !== '') {
            if (!function_exists('loadAvailableThemes')) {
                @require_once __DIR__ . '/theme_utils.php';
            }
            $themes = function_exists('loadAvailableThemes') ? loadAvailableThemes() : [];
            if (isset($themes[$previewKey]) && isset($themes[$previewKey]['css'])) {
                $cssCandidate = $themes[$previewKey]['css'];
                if (filter_var($cssCandidate, FILTER_VALIDATE_URL)) {
                    $user_theme_css = $cssCandidate;
                    $currentThemeKey = $previewKey; // ensure branding aligns
                }
            }
        }
    }
} catch (Throwable $e) { /* non-fatal */
}

// Build theme branding map (colors for CSS variables)
if (function_exists('getThemeBranding')) {
    try {
        $branding = getThemeBranding($currentThemeKey ?: 'default');
    } catch (Throwable $e) {
        $branding = null;
    }
}

// Optional navbar tone/brand overrides for preview (light|dark)
// This adjusts variables used below so CSS reflects the selection
if (isset($_GET['preview_nav'])) {
    $pvNav = strtolower((string)$_GET['preview_nav']);
    if ($pvNav === 'dark') {
        // Dark navbar defaults
        $branding = is_array($branding) ? $branding : [];
        $branding['text_primary'] = '#e5e7eb';
        $branding['text_secondary'] = '#cbd5e1';
        $branding['nav_bg'] = 'rgba(15,23,42,0.98)';
        $branding['nav_bg_scrolled'] = 'rgba(15,23,42,0.98)';
        $branding['nav_border'] = 'rgba(0,0,0,0.5)';
        $branding['nav_shadow'] = '0 8px 24px rgba(0,0,0,.4)';
        $branding['tone'] = 'dark';
    } elseif ($pvNav === 'light') {
        $branding = is_array($branding) ? $branding : [];
        $branding['text_primary'] = '#0f172a';
        $branding['text_secondary'] = '#475569';
        $branding['nav_bg'] = 'rgba(255,255,255,0.96)';
        $branding['nav_bg_scrolled'] = 'rgba(255,255,255,0.98)';
        $branding['nav_border'] = 'rgba(0,0,0,0.18)';
        $branding['nav_shadow'] = '0 8px 26px rgba(0,0,0,.1)';
        $branding['tone'] = 'light';
    }
}
?>

<!-- HEAD SECTION CONTENT -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Olive Branch Amateur Radio Club - Serving Mississippi and Beyond since 1998">
<meta name="keywords" content="amateur radio, ham radio, W5OBM, OBARC, Mississippi, Memphis">
<meta name="author" content="W5OBM Amateur Radio Club">

<!-- Open Graph Tags -->
<meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
<meta property="og:description" content="Olive Branch Amateur Radio Club - Serving Mississippi and Beyond since 1998">
<meta property="og:image" content="<?= BASE_URL ?>images/badges/club_logo.png">
<meta property="og:url" content="<?= BASE_URL ?>">
<meta property="og:type" content="website">

<!-- Twitter Card Tags -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
<meta name="twitter:description" content="Olive Branch Amateur Radio Club - Serving Mississippi and Beyond since 1998">
<meta name="twitter:image" content="<?= BASE_URL ?>images/badges/club_logo.png">

<!-- Favicon and Icons -->
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>images/badges/club_logo.png">

<!-- Preconnect to External Domains for Performance -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://stackpath.bootstrapcdn.com">

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">

<!-- CRITICAL: Bootstrap CSS - FORCE LOAD WITHOUT URL CONCATENATION -->
<?php if ($user_theme_css): ?>
    <!-- User selected theme -->
    <link href="<?= htmlspecialchars($user_theme_css) ?>" rel="stylesheet" id="theme-css">
<?php else: ?>
    <!-- Default Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" id="theme-css">
<?php endif; ?>

<!-- Font Awesome 6.0 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/2.3.0/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.0/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.0/css/responsive.bootstrap5.min.css">

<!-- Handsontable CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable@latest/dist/handsontable.full.min.css">

<!-- Additional CSS Libraries -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<!-- Print styles: cleaner printouts for reports/pages -->
<style media="print">
    /* Hide navigation and footers when printing */
    .navbar,
    .modern-nav,
    nav,
    footer,
    .page-footer,
    .btn,
    .no-print {
        display: none !important;
    }

    /* Expand content */
    .container,
    .container-fluid,
    .page-container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Remove card chrome; keep content */
    .card,
    .card-header,
    .card-body,
    .card-footer {
        box-shadow: none !important;
        border: none !important;
    }

    .card-header {
        background: none !important;
        color: #000 !important;
    }

    /* Improve table readability */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    th,
    td {
        border: 1px solid #ccc !important;
        padding: 6px 8px !important;
    }


    /* Show global print header */
    .global-print-header {
        display: flex !important;
        align-items: center;
        gap: 12px;
        margin: 0 0 12px 0;
        padding-bottom: 8px;
        border-bottom: 1px solid #ccc;
    }

    .global-print-header img {
        max-height: 56px;
        width: auto;
    }

    thead th {
        background: #f2f2f2 !important;
    }

    /* Avoid page breaks inside rows */
    tr,
    img {
        page-break-inside: avoid;
    }

    h1,
    h2,
    h3,
    h4 {
        page-break-after: avoid;
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">

<!-- CUSTOM W5OBM CSS - Load after Bootstrap (point to main site assets) -->
<link rel="stylesheet" href="https://w5obm.com/css/w5obm.css">
<!-- Hero Logo Styles globally to standardize hero logos across pages -->
<link rel="stylesheet" href="https://w5obm.com/css/hero-logo-styles.css">

<?php
// Ensure a CSRF token exists for activity pings
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$W5_CSRF = $_SESSION['csrf_token'];
// Load W5OBM theme-specific CSS if user has selected a local theme variant
// NOTE: This is optional theme loading - if it fails, just use default theme
if (isAuthenticated() && isset($_SESSION['user_id'])) {
    // Check if theme preference is stored in session (faster than database lookup)
    if (isset($_SESSION['theme_preference']) && $_SESSION['theme_preference'] === 'w5obm_green') {
        echo '<link rel="stylesheet" href="' . BASE_URL . 'css/w5obm_theme_green.css">' . "\n";
    }
    // Skip database theme lookup to prevent header errors
    // Theme can be loaded by dashboard pages if needed
}
?>

<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-V4G39WP388"></script>
<script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', 'G-V4G39WP388, GT-WBT9Q3W');
</script>

<?php
// Prepare safe variables for CSS output
$bp = (is_array($branding) && isset($branding['accent_primary'])) ? (string)$branding['accent_primary'] : 'var(--theme-accent-primary)';
$bs = (is_array($branding) && isset($branding['accent_secondary'])) ? (string)$branding['accent_secondary'] : 'var(--theme-accent-secondary)';
$txp = (is_array($branding) && isset($branding['text_primary'])) ? (string)$branding['text_primary'] : '#0f172a';
$txs = (is_array($branding) && isset($branding['text_secondary'])) ? (string)$branding['text_secondary'] : '#475569';
$navbg = (is_array($branding) && isset($branding['nav_bg'])) ? (string)$branding['nav_bg'] : 'rgba(255,255,255,0.96)';
$navbgs = (is_array($branding) && isset($branding['nav_bg_scrolled'])) ? (string)$branding['nav_bg_scrolled'] : 'rgba(255,255,255,0.98)';
$navbd = (is_array($branding) && isset($branding['nav_border'])) ? (string)$branding['nav_border'] : 'rgba(0,0,0,0.18)';
$navsh = (is_array($branding) && isset($branding['nav_shadow'])) ? (string)$branding['nav_shadow'] : '0 8px 26px rgba(0,0,0,.1)';

$primaryBlue = function_exists('get_site_setting') ? (string)get_site_setting('primary_blue', 'var(--primary-blue)') : 'var(--primary-blue)';
$secondaryBlue = function_exists('get_site_setting') ? (string)get_site_setting('secondary_blue', 'var(--secondary-blue)') : 'var(--secondary-blue)';
$accentGold = function_exists('get_site_setting') ? (string)get_site_setting('accent_gold', 'var(--accent-gold)') : 'var(--accent-gold)';
$heroFrom = function_exists('get_site_setting') ? (string)get_site_setting('hero_overlay_from_rgba', 'rgba(3,26,84,0.85)') : 'rgba(3,26,84,0.85)';
$heroTo = function_exists('get_site_setting') ? (string)get_site_setting('hero_overlay_to_rgba', 'rgba(20,94,255,0.70)') : 'rgba(20,94,255,0.70)';

// Configurable hero overlay fade strength and start position
$heroFadeAlpha = function_exists('get_site_setting') ? (string)get_site_setting('hero_overlay_fade_alpha', '0.08') : '0.08';
$heroFadeStart = function_exists('get_site_setting') ? (string)get_site_setting('hero_overlay_fade_start_percent', '55%') : '55%';
// Optional dark-mode overrides (for adaptive fading)
$heroFadeAlphaDark = function_exists('get_site_setting') ? (string)get_site_setting('hero_overlay_fade_alpha_dark', '') : '';
$heroFadeStartDark = function_exists('get_site_setting') ? (string)get_site_setting('hero_overlay_fade_start_percent_dark', '') : '';

// Compute effective fade values based on theme tone (branding['tone'])
$tone = is_array($branding) && isset($branding['tone']) ? strtolower((string)$branding['tone']) : 'light';
if ($tone === 'dark') {
    // If dark-specific overrides exist use them, else derive lighter fade (less alpha, later start)
    $heroFadeAlphaEffective = $heroFadeAlphaDark !== '' ? $heroFadeAlphaDark : max(0.0, (float)$heroFadeAlpha * 0.6);
    $heroFadeStartEffective = $heroFadeStartDark !== '' ? $heroFadeStartDark : '60%';
} else {
    // Light tone: optionally intensify a bit for readability (slightly higher alpha, earlier start)
    $heroFadeAlphaEffective = min(1.0, (float)$heroFadeAlpha * 1.25);
    $heroFadeStartEffective = $heroFadeStart;
}
// Normalize formatting for CSS (alpha numeric) & ensure percent suffix for start
if (is_numeric($heroFadeAlphaEffective)) {
    $heroFadeAlphaEffective = (string)round((float)$heroFadeAlphaEffective, 3);
}
if (strpos($heroFadeStartEffective, '%') === false) {
    // Coerce to percent if raw number
    $heroFadeStartEffective = rtrim((string)$heroFadeStartEffective) . '%';
}

// Allow per-page overrides (page sets $hero_fade_alpha_override / $hero_fade_start_override BEFORE including header)
if (isset($hero_fade_alpha_override) && $hero_fade_alpha_override !== '') {
    $heroFadeAlphaEffective = (string)$hero_fade_alpha_override;
}
if (isset($hero_fade_start_override) && $hero_fade_start_override !== '') {
    $heroFadeStartEffective = (string)$hero_fade_start_override;
    if (strpos($heroFadeStartEffective, '%') === false) {
        $heroFadeStartEffective .= '%';
    }
}

// Preview fade profiles via ?preview_fade=strong|minimal|default (non-persistent)
if (isset($_GET['preview_fade'])) {
    $pvFade = strtolower((string)$_GET['preview_fade']);
    switch ($pvFade) {
        case 'strong':
            // Earlier start & stronger alpha (capped)
            $heroFadeStartEffective = adjustPreviewPercent($heroFadeStartEffective, -5);
            $heroFadeAlphaEffective = (string)min(1.0, (float)$heroFadeAlphaEffective * 1.6);
            break;
        case 'minimal':
            // Later start & weaker alpha
            $heroFadeStartEffective = adjustPreviewPercent($heroFadeStartEffective, +10);
            $heroFadeAlphaEffective = (string)max(0.0, (float)$heroFadeAlphaEffective * 0.3);
            break;
        case 'default':
        default:
            // no change
            break;
    }
}

// Helper to shift percent safely (local function)
if (!function_exists('adjustPreviewPercent')) {
    function adjustPreviewPercent($percentString, $delta)
    {
        $raw = rtrim($percentString, '%');
        if (!is_numeric($raw)) return $percentString; // fallback
        $val = (int)$raw + (int)$delta;
        $val = max(0, min(100, $val));
        return $val . '%';
    }
}
?>

<!-- Custom CSS per Website Guidelines and Theme Variables -->
<style>
    /* W5OBM Custom Variables */
    :root {
        /* Base palette (theme-driven where available) */
        --w5obm-primary: <?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>;
        --w5obm-secondary: <?= htmlspecialchars($bs, ENT_QUOTES, 'UTF-8') ?>;
        --w5obm-success: #198754;
        --w5obm-danger: #dc3545;
        --w5obm-warning: #ffc107;
        --w5obm-info: #0dcaf0;
        --w5obm-light: #f8f9fa;
        --w5obm-dark: #343a40;

        /* Theme-driven vars */
        --theme-accent-primary: <?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>;
        --theme-accent-secondary: <?= htmlspecialchars($bs, ENT_QUOTES, 'UTF-8') ?>;
        --theme-text-primary: <?= htmlspecialchars($txp, ENT_QUOTES, 'UTF-8') ?>;
        --theme-text-secondary: <?= htmlspecialchars($txs, ENT_QUOTES, 'UTF-8') ?>;
        --theme-nav-bg: <?= htmlspecialchars($navbg, ENT_QUOTES, 'UTF-8') ?>;
        --theme-nav-bg-scrolled: <?= htmlspecialchars($navbgs, ENT_QUOTES, 'UTF-8') ?>;
        --theme-nav-border: <?= htmlspecialchars($navbd, ENT_QUOTES, 'UTF-8') ?>;
        --theme-nav-shadow: <?= htmlspecialchars($navsh, ENT_QUOTES, 'UTF-8') ?>;

        /* Site settings driven hero colors (fallbacks provided) */
        --primary-blue: <?= htmlspecialchars($primaryBlue, ENT_QUOTES, 'UTF-8') ?>;
        --secondary-blue: <?= htmlspecialchars($secondaryBlue, ENT_QUOTES, 'UTF-8') ?>;
        --accent-gold: <?= htmlspecialchars($accentGold, ENT_QUOTES, 'UTF-8') ?>;
        --hero-overlay-from: <?= htmlspecialchars($heroFrom, ENT_QUOTES, 'UTF-8') ?>;
        --hero-overlay-to: <?= htmlspecialchars($heroTo, ENT_QUOTES, 'UTF-8') ?>;
        --hero-fade-alpha: <?= htmlspecialchars($heroFadeAlpha, ENT_QUOTES, 'UTF-8') ?>;
        --hero-fade-start: <?= htmlspecialchars($heroFadeStart, ENT_QUOTES, 'UTF-8') ?>;
        --hero-fade-alpha-effective: <?= htmlspecialchars($heroFadeAlphaEffective, ENT_QUOTES, 'UTF-8') ?>;
        --hero-fade-start-effective: <?= htmlspecialchars($heroFadeStartEffective, ENT_QUOTES, 'UTF-8') ?>;
    }

    /* Accounting dashboard: no global fixed-navbar padding */
    body {
        padding-top: 0 !important;
    }

    .w5obm-text-primary {
        color: var(--w5obm-primary) !important;
    }

    .w5obm-text-secondary {
        color: var(--w5obm-secondary) !important;
    }

    .w5obm-bg-primary {
        background-color: var(--w5obm-primary) !important;
    }

    .w5obm-bg-secondary {
        background-color: var(--w5obm-secondary) !important;
    }

    /* Ensure all images have shadows per guidelines */
    img:not(.no-shadow) {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Navigation improvements */
    .navbar {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    }

    /* Form improvements per guidelines */
    .form-control,
    .form-select {
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    }

    /* Table improvements */
    .table {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    }

    /* Hero - shared base styles (full-height variants defined elsewhere) */
    .hero {
        position: relative;
        border-radius: 1rem;
        overflow: hidden;
        background: linear-gradient(135deg, var(--hero-overlay-from), var(--hero-overlay-to));
        color: #fff;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);
    }

    .hero .hero-body {
        position: relative;
        z-index: 1;
    }

    /* Compact hero for dashboards */
    .hero-small {
        min-height: 120px;
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
    }

    @media (min-width: 768px) {
        .hero-small {
            min-height: 130px;
            padding: 1rem 2rem;
        }
    }

    /* Summary tiles overlapping hero bottom edge */
    .hero-summary-row {
        margin-top: -28px;
        position: relative;
        z-index: 2;
    }

    .hero-summary-card {
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
    }

    /* Card improvements per guidelines */
    .card {
        border: none;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        border-radius: 12px 12px 0 0 !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        background: linear-gradient(135deg, var(--theme-accent-primary) 0%, var(--theme-accent-secondary) 100%) !important;
        color: var(--theme-text-primary, #fff) !important;
    }

    /* Master container per guidelines */
    .master-container {
        max-width: 90%;
        margin: 50px auto 0 auto;
        padding: 30px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    /* Responsive container widths */
    @media (min-width: 768px) {
        .master-container {
            max-width: 85%;
        }
    }

    @media (min-width: 992px) {
        .master-container {
            max-width: 80%;
        }
    }

    @media (min-width: 1200px) {
        .master-container {
            max-width: 75%;
        }
    }

    /* Animation classes */
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    /* Loading spinner */
    .spinner-w5obm {
        border: 3px solid var(--w5obm-light);
        border-top: 3px solid var(--w5obm-primary);
        border-radius: 50%;
        width: 30px;
        height: 30px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>

<!-- Dropdowns use Bootstrap's click behavior by default; no hover override -->

<!-- Keep-alive pings on user activity to prevent idle timeout while actively using the site -->
<?php if (isAuthenticated()): ?>
    <script>
        (function() {
            var throttleMs = 120000; // 2 minutes
            var lastPing = 0;
            var csrf = <?= json_encode($W5_CSRF) ?>;

            function ping() {
                var now = Date.now();
                if (now - lastPing < throttleMs) return;
                lastPing = now;
                fetch('<?= BASE_URL ?>authentication/extend_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        csrf_token: csrf
                    })
                }).catch(function() {});
            }
            ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function(evt) {
                window.addEventListener(evt, ping, {
                    passive: true
                });
            });
            // Also ping on visibility change when returning to tab
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) ping();
            });
        })();
    </script>
<?php endif; ?>