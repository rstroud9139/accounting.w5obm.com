<?php

/**
 * W5OBM Main Navigation Menu
 * File: /include/menu.php
 * Enhanced navigation with modern design and improved UX
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/session_init.php';
// Ensure helper functions available for robust auth checks
if (!function_exists('isAuthenticated')) {
    require_once __DIR__ . '/helper_functions.php';
}


// Align navbar colors with the active theme preference
if (!function_exists('getCurrentTheme')) {
    require_once __DIR__ . '/theme_utils.php';
}
// Load site settings (global overrides)
if (!function_exists('get_site_setting')) {
    @require_once __DIR__ . '/site_settings.php';
}

$theme_user_id = $_SESSION['user_id'] ?? null;
$current_theme_key = function_exists('getCurrentThemeKey')
    ? getCurrentThemeKey($theme_user_id)
    : ($_SESSION['selectedThemeKey'] ?? 'default');
$theme_branding = function_exists('getThemeBranding')
    ? getThemeBranding($current_theme_key)
    : [];

$nav_bg = $theme_branding['nav_bg'] ?? 'rgba(255, 255, 255, 0.95)';
$nav_scrolled_bg = $theme_branding['nav_bg_scrolled'] ?? 'rgba(255, 255, 255, 0.98)';
$nav_border = $theme_branding['nav_border'] ?? 'rgba(0, 0, 0, 0.1)';
$nav_shadow = $theme_branding['nav_shadow'] ?? '0 2px 20px rgba(0, 0, 0, 0.1)';
$text_primary = $theme_branding['text_primary'] ?? '#1f2937';
$text_secondary = $theme_branding['text_secondary'] ?? '#6b7280';
$session_pill_bg = $theme_branding['session_pill_bg'] ?? 'rgba(255, 255, 255, 0.95)';
$session_pill_border = $theme_branding['session_pill_border'] ?? 'rgba(0, 0, 0, 0.05)';
$session_pill_color = $theme_branding['session_pill_color'] ?? '#1f2937';
$accent_primary = $theme_branding['accent_primary'] ?? '#2563eb';
$accent_secondary = $theme_branding['accent_secondary'] ?? '#7c3aed';
$nav_tone = $theme_branding['tone'] ?? 'light';

// Site-level overrides (if defined in site settings)
$override_nav_bg = get_site_setting('nav_bg_override');
$override_nav_text_primary = get_site_setting('nav_text_primary_override');
$override_nav_text_secondary = get_site_setting('nav_text_secondary_override');
$override_accent_primary = get_site_setting('accent_primary_override');
$override_accent_secondary = get_site_setting('accent_secondary_override');
if ($override_nav_bg) $nav_bg = $override_nav_bg;
if ($override_nav_bg) $nav_scrolled_bg = $override_nav_bg; // keep consistent background when scrolled if override provided
if ($override_nav_text_primary) $text_primary = $override_nav_text_primary;
if ($override_nav_text_secondary) $text_secondary = $override_nav_text_secondary;
if ($override_accent_primary) $accent_primary = $override_accent_primary;
if ($override_accent_secondary) $accent_secondary = $override_accent_secondary;

// --- Per-user navbar overrides (session or DB) ---
// Layering order now:
// 1. Base theme branding (theme_utils.php getThemeBranding)
// 2. Site-wide overrides from site_settings.php (admin controlled)
// 3. User navbar overrides (only affect that user's session)
// These can be stored in session vars: user_nav_bg_override, user_nav_text_primary_override, user_nav_text_secondary_override,
// user_accent_primary_override, user_accent_secondary_override. If DB columns exist, they are loaded (dynamic detection) and cached into session.

function loadUserNavbarOverrides($userId)
{
    $overrides = [];
    if (!$userId) return $overrides;
    // Session first
    $sessionKeys = [
        'user_nav_bg_override' => 'nav_bg',
        'user_nav_text_primary_override' => 'text_primary',
        'user_nav_text_secondary_override' => 'text_secondary',
        'user_accent_primary_override' => 'accent_primary',
        'user_accent_secondary_override' => 'accent_secondary'
    ];
    foreach ($sessionKeys as $sessKey => $mapKey) {
        if (!empty($_SESSION[$sessKey])) {
            $overrides[$mapKey] = $_SESSION[$sessKey];
        }
    }
    // Attempt DB columns (graceful); only if not already in session
    global $conn;
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        try {
            $neededCols = [
                'nav_bg_user',
                'nav_text_primary_user',
                'nav_text_secondary_user',
                'accent_primary_user',
                'accent_secondary_user'
            ];
            $existing = [];
            if ($res = $conn->query("SHOW COLUMNS FROM auth_users")) {
                while ($c = $res->fetch_assoc()) {
                    $existing[] = $c['Field'];
                }
                $res->free();
            }
            $present = array_intersect($neededCols, $existing);
            if (!empty($present)) {
                $cols = implode(',', $present);
                $stmt = $conn->prepare("SELECT $cols FROM auth_users WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            foreach ($present as $col) {
                                $val = trim((string)$row[$col]);
                                if ($val !== '') {
                                    switch ($col) {
                                        case 'nav_bg_user':
                                            $overrides['nav_bg'] = $val;
                                            $overrides['nav_bg_scrolled'] = $val;
                                            break;
                                        case 'nav_text_primary_user':
                                            $overrides['text_primary'] = $val;
                                            break;
                                        case 'nav_text_secondary_user':
                                            $overrides['text_secondary'] = $val;
                                            break;
                                        case 'accent_primary_user':
                                            $overrides['accent_primary'] = $val;
                                            break;
                                        case 'accent_secondary_user':
                                            $overrides['accent_secondary'] = $val;
                                            break;
                                    }
                                }
                            }
                        }
                    }
                    $stmt->close();
                }
            }
        } catch (Throwable $e) { /* ignore */
        }
    }
    return $overrides;
}

// Apply per-user overrides if a user is authenticated (ensure check before $is_logged_in is defined below)
$__userLogged = function_exists('isAuthenticated') ? isAuthenticated() : (!empty($theme_user_id));
if ($__userLogged) {
    $userOverrides = loadUserNavbarOverrides($theme_user_id);
    if (isset($userOverrides['nav_bg'])) $nav_bg = $userOverrides['nav_bg'];
    if (isset($userOverrides['nav_bg_scrolled'])) $nav_scrolled_bg = $userOverrides['nav_bg_scrolled'];
    if (isset($userOverrides['text_primary'])) $text_primary = $userOverrides['text_primary'];
    if (isset($userOverrides['text_secondary'])) $text_secondary = $userOverrides['text_secondary'];
    if (isset($userOverrides['accent_primary'])) $accent_primary = $userOverrides['accent_primary'];
    if (isset($userOverrides['accent_secondary'])) $accent_secondary = $userOverrides['accent_secondary'];
}

// Allow navbar tone override for previews via query parameter (light|dark)
if (isset($_GET['preview_nav'])) {
    $pvNav = strtolower((string)$_GET['preview_nav']);
    if ($pvNav === 'dark') {
        $nav_tone = 'dark';
        $nav_bg = 'rgba(15, 23, 42, 0.98)';
        $nav_scrolled_bg = 'rgba(15, 23, 42, 0.98)';
        $nav_border = 'rgba(0,0,0,0.5)';
        $nav_shadow = '0 8px 24px rgba(0,0,0,.4)';
        $text_primary = '#e5e7eb';
        $text_secondary = '#cbd5e1';
    } elseif ($pvNav === 'light') {
        $nav_tone = 'light';
        $nav_bg = 'rgba(255, 255, 255, 0.96)';
        $nav_scrolled_bg = 'rgba(255, 255, 255, 0.98)';
        $nav_border = 'rgba(0,0,0,0.18)';
        $nav_shadow = '0 8px 26px rgba(0,0,0,.1)';
        $text_primary = '#0f172a';
        $text_secondary = '#475569';
    }
}

// Initialize login status using centralized checks
$is_logged_in = false;
$user_display_name = '';
$is_admin_user = false;

if (function_exists('isAuthenticated') && isAuthenticated()) {
    $is_logged_in = true;
    $uid = $_SESSION['user_id'] ?? null;
    $user_display_name = $_SESSION['username'] ?? ($_SESSION['callsign'] ?? 'User');
    if ($uid && function_exists('isAdmin')) {
        $is_admin_user = isAdmin($uid);
    }
} else {
    // Ensure no stale UI state from partial sessions
    $is_logged_in = false;
    $user_display_name = '';
    $is_admin_user = false;
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/'); // or your actual base path
}
?>

<!-- Global print header (visible only when printing) -->
<?php @include_once __DIR__ . '/report_header.php'; ?>
<div class="global-print-header" style="display:none;">
    <?php if (function_exists('renderReportHeader')) {
        renderReportHeader(isset($page_title) ? $page_title : 'W5OBM');
    } ?>

</div>

<!-- Modern Navigation with Glass Morphism Effect -->
<nav class="navbar navbar-expand-lg fixed-top modern-nav" data-nav-theme="<?= htmlspecialchars($nav_tone, ENT_QUOTES, 'UTF-8'); ?>" data-theme-key="<?= htmlspecialchars($current_theme_key ?? 'default', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="container-fluid px-4">
        <!-- Enhanced Brand Section -->
        <div class="navbar-brand-container">
            <a class="navbar-brand modern-brand" href="<?php echo BASE_URL; ?>index.php">
                <div class="brand-logo">
                    <img src="<?php echo BASE_URL; ?>images/badges/club_logo.png" alt="W5OBM Logo" class="logo-img">
                </div>
                <div class="brand-text">
                    <span class="brand-main">W5OBM</span>
                    <span class="brand-sub">Amateur Radio Club</span>
                </div>
            </a>
        </div>

        <!-- Mobile Controls -->
        <div class="mobile-controls d-lg-none">
            <?php if ($is_logged_in): ?>
                <div class="mobile-user-indicator">
                    <i class="fas fa-user-circle"></i>
                    <span><?= htmlspecialchars(substr($user_display_name, 0, 8)) ?></span>
                </div>
            <?php endif; ?>
            <button class="navbar-toggler modern-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#modernNavbar" aria-controls="modernNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="toggler-line"></span>
                <span class="toggler-line"></span>
                <span class="toggler-line"></span>
            </button>
        </div>

        <!-- Main Navigation -->
        <div class="collapse navbar-collapse" id="modernNavbar">
            <!-- Primary Navigation Items -->
            <ul class="navbar-nav me-auto">
                <!-- About Us with Modern Mega Menu -->
                <li class="nav-item dropdown mega-dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-users nav-icon"></i>
                        <span>About Us</span>
                    </a>
                    <div class="dropdown-menu mega-menu shadow" aria-labelledby="aboutDropdown">
                        <div class="mega-content">
                            <div class="mega-section">
                                <h6 class="mega-title">Our Club</h6>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>aboutus.php">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <span class="item-title">About W5OBM</span>
                                        <span class="item-desc">Learn about our history and mission</span>
                                    </div>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>aboutus_officers.php">
                                    <i class="fas fa-user-tie"></i>
                                    <div>
                                        <span class="item-title">Club Officers</span>
                                        <span class="item-desc">Meet our leadership team</span>
                                    </div>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>aboutus_meetings.php">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <span class="item-title">Club Meetings</span>
                                        <span class="item-desc">When and where we meet</span>
                                    </div>
                                </a>
                            </div>
                            <div class="mega-section">
                                <h6 class="mega-title">Our Members</h6>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>aboutus_members.php">
                                    <i class="fas fa-address-book"></i>
                                    <div>
                                        <span class="item-title">Member Directory</span>
                                        <span class="item-desc">Connect with fellow hams</span>
                                    </div>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>aboutus_membershipbyclass.php">
                                    <i class="fas fa-layer-group"></i>
                                    <div>
                                        <span class="item-title">License Classes</span>
                                        <span class="item-desc">Membership by license class</span>
                                    </div>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>aboutus_volunteerexaminers.php">
                                    <i class="fas fa-clipboard-check"></i>
                                    <div>
                                        <span class="item-title">VE Team</span>
                                        <span class="item-desc">Our volunteer examiners</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </li>

                <!-- Activities -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="activitiesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-broadcast-tower nav-icon"></i>
                        <span>Activities</span>
                    </a>
                    <div class="dropdown-menu modern-dropdown shadow">
                        <div class="dropdown-header">
                            <i class="fas fa-radio"></i>
                            <span>Club Activities</span>
                        </div>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>weeklynets.php">
                            <i class="fas fa-microphone"></i>
                            <div>
                                <span class="item-title">Weekly Nets</span>
                                <span class="item-desc">Club and area net information</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>weeklynets_preamble.php">
                            <i class="fas fa-scroll"></i>
                            <div>
                                <span class="item-title">Net Preamble</span>
                                <span class="item-desc">OBARC weekly net preamble</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>weeklynets_schedule.php">
                            <i class="fas fa-calendar-check"></i>
                            <div>
                                <span class="item-title">NCS Schedule</span>
                                <span class="item-desc">Schedule & net reports</span>
                            </div>
                        </a>
                    </div>
                </li>

                <!-- Ham Radio Info -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="hamRadioDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-satellite nav-icon"></i>
                        <span>Ham Radio</span>
                    </a>
                    <div class="dropdown-menu modern-dropdown shadow">
                        <div class="dropdown-header">
                            <i class="fas fa-certificate"></i>
                            <span>Licensing</span>
                        </div>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>hamlicensing.php">
                            <i class="fas fa-id-card"></i>
                            <div>
                                <span class="item-title">Get Licensed</span>
                                <span class="item-desc">Start your ham radio journey</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>hamlicensing_licenseprocess.php">
                            <i class="fas fa-route"></i>
                            <div>
                                <span class="item-title">License Process</span>
                                <span class="item-desc">Step-by-step guide</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>hamlicensing_licensetypes.php">
                            <i class="fas fa-layer-group"></i>
                            <div>
                                <span class="item-title">License Types</span>
                                <span class="item-desc">Tech, General, Extra</span>
                            </div>
                        </a>

                        <div class="dropdown-divider"></div>

                        <div class="dropdown-header">
                            <i class="fas fa-tower-broadcast"></i>
                            <span>Equipment & Technical</span>
                        </div>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>repeaters.php">
                            <i class="fas fa-broadcast-tower"></i>
                            <div>
                                <span class="item-title">Repeaters</span>
                                <span class="item-desc">Local repeater information</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>repeaters_heatmaps.php">
                            <i class="fas fa-map"></i>
                            <div>
                                <span class="item-title">Coverage Maps</span>
                                <span class="item-desc">RF coverage analysis</span>
                            </div>
                        </a>
                    </div>
                </li>

                <?php if ($is_admin_user): ?>
                    <!-- Administration Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-users-cog nav-icon"></i>
                            <span>Administration</span>
                        </a>
                        <div class="dropdown-menu modern-dropdown shadow" aria-labelledby="adminDropdown">
                            <div class="dropdown-header">
                                <i class="fas fa-cog"></i>
                                <span>Admin</span>
                            </div>
                            <a class="dropdown-item" href="<?= BASE_URL ?>administration/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                <div>
                                    <span class="item-title">Admin Dashboard</span>
                                    <span class="item-desc">System administration</span>
                                </div>
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>administration/users/">
                                <i class="fas fa-users"></i>
                                <div>
                                    <span class="item-title">Users</span>
                                    <span class="item-desc">Manage user accounts</span>
                                </div>
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>administration/system/">
                                <i class="fas fa-server"></i>
                                <div>
                                    <span class="item-title">System</span>
                                    <span class="item-desc">System settings</span>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?= BASE_URL ?>administration/tools/admin_link_audit.php">
                                <i class="fas fa-tools"></i>
                                <div>
                                    <span class="item-title">Tools</span>
                                    <span class="item-desc">Admin utilities</span>
                                </div>
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>administration/documentation/index.php">
                                <i class="fas fa-book"></i>
                                <div>
                                    <span class="item-title">Documentation</span>
                                    <span class="item-desc">System & architecture docs</span>
                                </div>
                            </a>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- Resources -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="resourcesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-book-open nav-icon"></i>
                        <span>Resources</span>
                    </a>
                    <div class="dropdown-menu modern-dropdown shadow">
                        <div class="dropdown-header">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Learning Resources</span>
                        </div>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>references_newhams.php">
                            <i class="fas fa-seedling"></i>
                            <div>
                                <span class="item-title">New Hams</span>
                                <span class="item-desc">Getting started guide</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>references_radios.php">
                            <i class="fas fa-radio"></i>
                            <div>
                                <span class="item-title">Equipment Guide</span>
                                <span class="item-desc">Radios & accessories</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>references_arduino.php">
                            <i class="fas fa-microchip"></i>
                            <div>
                                <span class="item-title">Arduino Projects</span>
                                <span class="item-desc">Ham radio automation</span>
                            </div>
                        </a>

                        <div class="dropdown-divider"></div>

                        <div class="dropdown-header">
                            <i class="fas fa-link"></i>
                            <span>External Links</span>
                        </div>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>references_orgs.php">
                            <i class="fas fa-building"></i>
                            <div>
                                <span class="item-title">Organizations</span>
                                <span class="item-desc">ARRL, clubs, groups</span>
                            </div>
                        </a>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>references_vendors.php">
                            <i class="fas fa-store"></i>
                            <div>
                                <span class="item-title">Vendors</span>
                                <span class="item-desc">Where to buy equipment</span>
                            </div>
                        </a>

                        <div class="dropdown-divider"></div>

                        <div class="dropdown-header">
                            <i class="fas fa-newspaper"></i>
                            <span>News & Media</span>
                        </div>
                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>newsroom.php">
                            <i class="fas fa-newspaper"></i>
                            <div>
                                <span class="item-title">W5OBM Newsroom</span>
                                <span class="item-desc">Live headlines & research feeds</span>
                            </div>
                        </a>
            </ul>

            <!-- Action Items -->
            <ul class="navbar-nav action-nav">
                <li class="nav-item">
                    <a class="nav-link action-link membership-link" href="<?php echo BASE_URL; ?>/membership/membership_app.php">
                        <i class="fas fa-user-plus"></i>
                        <span>Join Us</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link action-link merch-link" href="<?php echo BASE_URL; ?>merch_page.php">
                        <i class="fas fa-tshirt"></i>
                        <span>Merch</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link action-link" href="<?php echo BASE_URL; ?>photos/piwigo/index.php">
                        <i class="fas fa-images"></i>
                        <span>Gallery</span>
                    </a>
                </li>
            </ul>

            <!-- User Area -->
            <ul class="navbar-nav user-nav">
                <?php if ($is_logged_in): ?>
                    <li class="nav-item dropdown user-dropdown">
                        <a class="nav-link dropdown-toggle user-link" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <span class="user-name"><?= htmlspecialchars($user_display_name) ?></span>
                            <div class="user-status"></div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end user-menu shadow">
                            <div class="user-info">
                                <div class="user-avatar-large">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div class="user-details">
                                    <span class="user-name-full"><?= htmlspecialchars($user_display_name) ?></span>
                                    <span class="user-role"><?= $is_admin_user ? 'Administrator' : 'Member' ?></span>
                                </div>
                            </div>

                            <div class="dropdown-divider"></div>

                            <?php if ($is_admin_user): ?>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>administration/dashboard.php">
                                    <i class="fas fa-cogs"></i>
                                    <span>Admin Dashboard</span>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>administration/theme_management/">
                                    <i class="fas fa-palette"></i>
                                    <span>Theme Management</span>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>administration/theme_management/theme_settings.php">
                                    <i class="fas fa-cog"></i>
                                    <span>Theme Settings</span>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>themes/user_navbar_settings.php">
                                    <i class="fas fa-adjust"></i>
                                    <span>User Navbar Override</span>
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>authentication/dashboard.php">
                                    <i class="fas fa-user"></i>
                                    <span>User Dashboard</span>
                                </a>
                            <?php else: ?>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>authentication/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>Dashboard</span>
                                </a>
                            <?php endif; ?>

                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>members/edit_profile.php">
                                <i class="fas fa-user-edit"></i>
                                <span>Profile Settings</span>
                            </a>

                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>themes/picktheme.php">
                                <i class="fas fa-paint-brush"></i>
                                <span>Change Theme</span>
                            </a>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>themes/user_navbar_settings.php">
                                <i class="fas fa-adjust"></i>
                                <span>Navbar Colors</span>
                            </a>

                            <div class="dropdown-divider"></div>

                            <a class="dropdown-item logout-item" href="<?php echo BASE_URL; ?>authentication/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link login-link" href="<?php echo BASE_URL; ?>authentication/login.php">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Sign In</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Modern Session Indicator -->
<?php if ($is_logged_in): ?>
    <div id="modernSessionIndicator" class="modern-session-indicator">
        <div class="session-pill">
            <div class="session-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="session-time">
                <span id="sessionTime">--:--</span>
            </div>
            <div class="session-controls">
                <button id="extendSessionBtn" class="session-btn extend-btn" title="Extend Session">
                    <i class="fas fa-plus"></i>
                </button>
                <button id="minimizeSessionBtn" class="session-btn minimize-btn" title="Minimize">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modern Styles -->
<style>
    :root {
        --nav-height: 70px;
        --nav-bg: <?= htmlspecialchars($nav_bg, ENT_QUOTES, 'UTF-8'); ?>;
        --nav-bg-scrolled: <?= htmlspecialchars($nav_scrolled_bg, ENT_QUOTES, 'UTF-8'); ?>;
        --nav-border: <?= htmlspecialchars($nav_border, ENT_QUOTES, 'UTF-8'); ?>;
        --nav-shadow: <?= htmlspecialchars($nav_shadow, ENT_QUOTES, 'UTF-8'); ?>;
        --accent-primary: <?= htmlspecialchars($accent_primary, ENT_QUOTES, 'UTF-8'); ?>;
        --accent-secondary: <?= htmlspecialchars($accent_secondary, ENT_QUOTES, 'UTF-8'); ?>;
        --accent-success: #059669;
        --accent-warning: #d97706;
        --accent-danger: #dc2626;
        --text-primary: <?= htmlspecialchars($text_primary, ENT_QUOTES, 'UTF-8'); ?>;
        --text-secondary: <?= htmlspecialchars($text_secondary, ENT_QUOTES, 'UTF-8'); ?>;
        --session-pill-bg: <?= htmlspecialchars($session_pill_bg, ENT_QUOTES, 'UTF-8'); ?>;
        --session-pill-border: <?= htmlspecialchars($session_pill_border, ENT_QUOTES, 'UTF-8'); ?>;
        --session-pill-color: <?= htmlspecialchars($session_pill_color, ENT_QUOTES, 'UTF-8'); ?>;
        --border-radius: 12px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Main Navigation */
    .modern-nav {
        background: var(--nav-bg) !important;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--nav-border);
        box-shadow: var(--nav-shadow);
        height: var(--nav-height);
        transition: var(--transition);
    }

    .modern-nav.scrolled {
        background: var(--nav-bg-scrolled) !important;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
        border-bottom: 1px solid var(--nav-border);
    }

    /* Brand Section */
    .navbar-brand-container {
        display: flex;
        align-items: center;
    }

    .modern-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: var(--text-primary);
        transition: var(--transition);
    }

    .modern-brand:hover {
        color: var(--accent-primary);
        text-decoration: none;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .logo-img {
        width: 32px;
        height: 32px;
        object-fit: contain;
        filter: brightness(0) invert(1);
    }

    .brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .brand-main {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .brand-sub {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Mobile Controls */
    .mobile-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .mobile-user-indicator {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: var(--accent-primary);
        color: white;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .modern-toggler {
        border: none;
        padding: 8px;
        background: transparent;
        position: relative;
        width: 32px;
        height: 32px;
    }

    .toggler-line {
        display: block;
        width: 20px;
        height: 2px;
        background: var(--text-primary);
        border-radius: 1px;
        transition: var(--transition);
        margin: 3px 0;
    }

    .modern-toggler:focus .toggler-line:nth-child(1) {
        transform: rotate(45deg) translate(6px, 6px);
    }

    .modern-toggler:focus .toggler-line:nth-child(2) {
        opacity: 0;
    }

    .modern-toggler:focus .toggler-line:nth-child(3) {
        transform: rotate(-45deg) translate(6px, -6px);
    }

    /* Navigation Links */
    .navbar-nav .nav-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        border-radius: 8px;
        transition: var(--transition);
        color: var(--text-primary);
        font-weight: 500;
        position: relative;
    }

    .navbar-nav .nav-link:hover {
        background: rgba(37, 99, 235, 0.1);
        color: var(--accent-primary);
    }

    .nav-icon {
        font-size: 1rem;
        width: 20px;
        text-align: center;
    }

    /* Action Links */
    .action-nav .action-link {
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
        color: white !important;
        border-radius: 25px;
        margin: 0 4px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .action-nav .action-link:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        background: linear-gradient(135deg, var(--accent-secondary), var(--accent-primary));
    }

    .membership-link {
        background: linear-gradient(135deg, var(--accent-success), #10b981) !important;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3) !important;
    }

    .merch-link {
        background: linear-gradient(135deg, var(--accent-warning), #f59e0b) !important;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3) !important;
    }

    /* Dropdowns */
    .dropdown-menu {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        backdrop-filter: blur(20px);
        background: rgba(255, 255, 255, 0.95);
        padding: 12px;
        margin-top: 8px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        pointer-events: none;
    }

    .dropdown-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        pointer-events: auto;
    }

    /* Hover behavior for desktop */
    @media (min-width: 992px) {
        /* hover-to-open disabled */
    }

    .modern-dropdown {
        min-width: 280px;
    }

    .dropdown-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--accent-primary);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 8px;
        transition: var(--transition);
        border: none;
        margin-bottom: 4px;
    }

    .dropdown-item:hover {
        background: rgba(37, 99, 235, 0.1);
        color: var(--accent-primary);
    }

    .dropdown-item i {
        width: 20px;
        text-align: center;
        color: var(--text-secondary);
    }

    .dropdown-item:hover i {
        color: var(--accent-primary);
    }

    .dropdown-item div {
        display: flex;
        flex-direction: column;
    }

    .item-title {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .item-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    /* Mega Menu */
    .mega-dropdown .dropdown-menu {
        min-width: 600px;
    }

    .mega-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .mega-section {
        padding: 12px;
    }

    .mega-title {
        font-weight: 700;
        color: var(--accent-primary);
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid rgba(37, 99, 235, 0.1);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* User Navigation */
    .user-nav .user-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 16px;
        border-radius: 25px;
        background: rgba(37, 99, 235, 0.1);
        color: var(--accent-primary);
        font-weight: 600;
        position: relative;
    }

    .user-avatar {
        font-size: 1.5rem;
    }

    .user-status {
        width: 8px;
        height: 8px;
        background: var(--accent-success);
        border-radius: 50%;
        position: absolute;
        top: 8px;
        right: 16px;
        border: 2px solid white;
    }

    .login-link {
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
        color: white !important;
        border-radius: 25px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .login-link:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
    }

    /* User Menu */
    .user-menu {
        min-width: 280px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 12px;
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(124, 58, 237, 0.1));
        border-radius: 8px;
        margin-bottom: 8px;
    }

    .user-avatar-large {
        font-size: 2.5rem;
        color: var(--accent-primary);
    }

    .user-details {
        display: flex;
        flex-direction: column;
    }

    .user-name-full {
        font-weight: 600;
        color: var(--text-primary);
    }

    .user-role {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .logout-item {
        color: var(--accent-danger) !important;
        margin-top: 8px;
    }

    .logout-item:hover {
        background: rgba(220, 38, 38, 0.1) !important;
    }

    /* Modern Session Indicator */
    .modern-session-indicator {
        position: fixed;
        top: 140px;
        /* push below navbar/header to avoid covering dropdowns */
        right: 20px;
        z-index: 950;
        /* stay under Bootstrap dropdowns (z-index ~1000) */
        transition: var(--transition);
        pointer-events: none;
        /* do not block page interactions */
    }

    .session-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--session-pill-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--session-pill-border);
        border-radius: 25px;
        padding: 8px 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
        font-size: 0.875rem;
        font-weight: 600;
        min-width: 120px;
        color: var(--session-pill-color);
    }

    .session-icon,
    .session-time {
        color: var(--session-pill-color);
    }

    .session-time {
        font-weight: 700;
    }

    .session-controls {
        display: flex;
        gap: 4px;
        margin-left: 8px;
        border-left: 1px solid var(--nav-border);
        padding-left: 8px;
    }

    .session-btn {
        width: 24px;
        height: 24px;
        border: none;
        background: transparent;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
        color: var(--session-pill-color);
        font-size: 0.75rem;
        pointer-events: auto;
        /* allow interaction with buttons */
    }

    .session-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        color: var(--session-pill-color);
    }

    .extend-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .session-pill.warning {
        background: rgba(255, 193, 7, 0.95);
        border-color: rgba(0, 0, 0, 0.1);
        color: #1f2937;
        animation: pulse-warning 2s infinite;
    }

    .session-pill.warning .session-time,
    .session-pill.warning .session-icon,
    .session-pill.warning .session-btn {
        color: #1f2937;
    }

    .session-pill.danger {
        background: rgba(220, 38, 38, 0.95);
        border-color: rgba(255, 255, 255, 0.2);
        color: #ffffff;
        animation: pulse-danger 1s infinite;
    }

    .session-pill.danger .session-time,
    .session-pill.danger .session-icon,
    .session-pill.danger .session-btn {
        color: #ffffff;
    }

    .session-pill.minimized {
        transform: translateX(calc(100% - 40px));
    }

    .session-pill.minimized:hover {
        transform: translateX(0);
    }

    @keyframes pulse-warning {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.8;
        }
    }

    @keyframes pulse-danger {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.9;
            transform: scale(1.02);
        }
    }

    /* Ensure user menu stays light-colored for readability */
    /* Ensure user dropdown matches others */
    .user-menu {
        background: rgba(255, 255, 255, 0.95) !important;
    }

    .user-menu .dropdown-item {
        color: inherit !important;
    }

    .user-menu .dropdown-item:hover {
        background: rgba(37, 99, 235, 0.08) !important;
        color: inherit !important;
    }

    /* Mobile Responsive */
    @media (max-width: 991.98px) {
        .modern-nav {
            height: auto;
            min-height: var(--nav-height);
        }

        .navbar-collapse {
            background: var(--nav-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--nav-border);
            border-radius: var(--border-radius);
            margin-top: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .navbar-nav {
            gap: 8px;
        }

        .navbar-nav .nav-link {
            padding: 16px;
            border-radius: var(--border-radius);
            margin-bottom: 4px;
        }

        .action-nav {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--nav-border);
        }

        .user-nav {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--nav-border);
        }

        .mega-content {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .modern-session-indicator {
            top: auto;
            bottom: 20px;
            /* move to bottom on mobile */
            right: 10px;
        }

        .session-pill {
            padding: 6px 12px;
            font-size: 0.75rem;
            min-width: 100px;
        }

        .brand-text {
            display: none;
        }
    }

    @media (max-width: 575.98px) {
        .modern-session-indicator {
            position: relative;
            top: auto;
            right: auto;
            margin: 10px;
            order: -1;
        }

        .dropdown-menu {
            position: static !important;
            transform: none !important;
            width: 100%;
            margin-top: 8px;
            box-shadow: none;
            border: 1px solid var(--nav-border);
        }

        .mega-dropdown .dropdown-menu {
            min-width: auto;
        }
    }

    /* Accessibility */
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* Print Styles */
    @media print {

        .modern-nav,
        .modern-session-indicator {
            display: none !important;
        }
    }

    /* Focus States for Accessibility */
    .nav-link:focus,
    .dropdown-item:focus,
    .session-btn:focus {
        outline: 2px solid var(--accent-primary);
        outline-offset: 2px;
    }

    .modern-toggler:focus {
        outline: 2px solid var(--accent-primary);
        outline-offset: 2px;
    }

    /* High Contrast Mode */
    @media (prefers-contrast: high) {
        .modern-nav {
            background: white;
            border-bottom: 2px solid black;
        }

        .nav-link {
            color: black;
            border: 1px solid transparent;
        }

        .nav-link:hover,
        .nav-link:focus {
            border-color: black;
            background: yellow;
        }
    }
</style>

<!-- Enhanced JavaScript for Modern Menu -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Session Management (Enhanced from original)
        <?php if ($is_logged_in): ?>
            let sessionTimeout = <?= isset($_SESSION['timeout']) ? $_SESSION['timeout'] - time() : 1800 ?>;
            let sessionWarningThreshold = 300; // 5 minutes
            let sessionDangerThreshold = 60; // 1 minute

            const sessionIndicator = document.getElementById('modernSessionIndicator');
            const sessionTimeDisplay = document.getElementById('sessionTime');
            const extendBtn = document.getElementById('extendSessionBtn');
            const minimizeBtn = document.getElementById('minimizeSessionBtn');

            let isMinimized = localStorage.getItem('modernSessionMinimized') === 'true';
            let warningShown = false;
            let dangerShown = false;

            // Initialize minimized state
            if (isMinimized) {
                sessionIndicator.querySelector('.session-pill').classList.add('minimized');
                minimizeBtn.innerHTML = '<i class="fas fa-plus"></i>';
            }

            // Update session timer
            function updateSessionTimer() {
                if (sessionTimeout <= 0) {
                    if (typeof showToast === 'function') {
                        showToast('warning', 'Session Expired', 'Your session has expired. Redirecting to login...', 'club-logo');
                    }

                    setTimeout(() => {
                        sessionStorage.clear();
                        localStorage.removeItem('modernSessionMinimized');
                        window.location.replace('<?= BASE_URL ?>authentication/login.php');
                    }, 2000);
                    return;
                }

                const minutes = Math.floor(sessionTimeout / 60);
                const seconds = sessionTimeout % 60;
                sessionTimeDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                const pill = sessionIndicator.querySelector('.session-pill');
                pill.classList.remove('warning', 'danger');

                if (sessionTimeout <= sessionDangerThreshold) {
                    pill.classList.add('danger');
                    if (!dangerShown && typeof showToast === 'function') {
                        dangerShown = true;
                        showToast('danger', 'Session Expiring!', 'Your session expires in less than 1 minute!', 'club-logo');
                    }
                } else if (sessionTimeout <= sessionWarningThreshold) {
                    pill.classList.add('warning');
                    if (!warningShown && typeof showToast === 'function') {
                        warningShown = true;
                        showToast('warning', 'Session Warning', 'Your session will expire in 5 minutes.', 'club-logo');
                    }
                }

                sessionTimeout--;
            }

            // Extend session
            function extendSession() {
                const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

                if (!csrfToken) {
                    if (typeof showToast === 'function') {
                        showToast('danger', 'Security Error', 'No security token available.', 'club-logo');
                    }
                    return;
                }

                extendBtn.disabled = true;
                extendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('<?= BASE_URL ?>authentication/extend_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            csrf_token: csrfToken
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            sessionTimeout = data.data.expires_in || 1800;
                            warningShown = false;
                            dangerShown = false;

                            if (typeof showToast === 'function') {
                                showToast('success', 'Session Extended', 'Your session has been extended.', 'club-logo');
                            }
                        } else {
                            if (data.redirect) {
                                if (typeof showToast === 'function') {
                                    showToast('warning', 'Session Expired', data.message || 'Redirecting to login...', 'club-logo');
                                }
                                setTimeout(() => {
                                    window.location.replace(data.redirect_url || '<?= BASE_URL ?>authentication/login.php');
                                }, 2000);
                            } else {
                                if (typeof showToast === 'function') {
                                    showToast('danger', 'Extension Failed', data.message || 'Failed to extend session.', 'club-logo');
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Session extension error:', error);
                        if (typeof showToast === 'function') {
                            showToast('danger', 'Connection Error', 'Unable to extend session.', 'club-logo');
                        }
                    })
                    .finally(() => {
                        extendBtn.disabled = false;
                        extendBtn.innerHTML = '<i class="fas fa-plus"></i>';
                    });
            }

            // Toggle minimize
            function toggleMinimize() {
                const pill = sessionIndicator.querySelector('.session-pill');
                isMinimized = !isMinimized;

                pill.classList.toggle('minimized', isMinimized);
                localStorage.setItem('modernSessionMinimized', isMinimized.toString());
                minimizeBtn.innerHTML = isMinimized ? '<i class="fas fa-plus"></i>' : '<i class="fas fa-minus"></i>';
            }

            // Event listeners
            extendBtn?.addEventListener('click', extendSession);
            minimizeBtn?.addEventListener('click', toggleMinimize);

            // Start timer
            updateSessionTimer();
            setInterval(updateSessionTimer, 1000);

            // Auto-extend on activity (throttled)
            let lastActivity = Date.now();
            let autoExtendEnabled = true;

            function handleActivity() {
                const now = Date.now();
                if (now - lastActivity < 30000) return;

                lastActivity = now;

                if (autoExtendEnabled && sessionTimeout > 0 && sessionTimeout < 300) {
                    autoExtendEnabled = false;

                    fetch('<?= BASE_URL ?>authentication/extend_session.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                csrf_token: csrfToken,
                                silent: true
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                sessionTimeout = data.data.expires_in || 1800;
                                warningShown = false;
                                dangerShown = false;
                            }
                        })
                        .catch(error => console.log('Silent extend failed:', error));

                    setTimeout(() => {
                        autoExtendEnabled = true;
                    }, 600000);
                }
            }

            ['mousemove', 'keypress', 'click', 'scroll'].forEach(event => {
                document.addEventListener(event, handleActivity, {
                    passive: true
                });
            });

            // Hide session timer when any navbar dropdown opens; restore on close
            document.addEventListener('show.bs.dropdown', function(e) {
                const ind = document.getElementById('modernSessionIndicator');
                if (ind) {
                    ind.style.display = 'none';
                }
            });
            document.addEventListener('hide.bs.dropdown', function(e) {
                const ind = document.getElementById('modernSessionIndicator');
                if (ind) {
                    ind.style.display = '';
                }
            });
        <?php endif; ?>

        // Smooth scrolling for anchor links (ignore plain '#')
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href') || '';
                if (href === '#' || href.length <= 1) return; // let default or dropdown handle
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const navbar = document.querySelector('.navbar-collapse');
            const toggler = document.querySelector('.navbar-toggler');

            if (navbar && navbar.classList.contains('show') &&
                !navbar.contains(e.target) &&
                !toggler.contains(e.target)) {
                toggler.click();
            }
        });
    });

    // Fixed navbar background - consistent styling
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.modern-nav');
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
    });
</script>

<?php
// Log menu access for analytics (optional)
if (function_exists('logActivity') && $is_logged_in) {
    logActivity($_SESSION['user_id'], 'menu_access', 'navigation', null, 'Accessed modern navigation menu');
}
?>