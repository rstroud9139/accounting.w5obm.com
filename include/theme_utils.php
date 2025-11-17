<?php

/**
 * EMERGENCY Theme Utility Functions for W5OBM
 * CRITICAL FIX: Safe database handling with proper error recovery
 * Handles theme loading, validation, and management
 */

if (!defined('THEME_UTILS_LOADED')) {
    define('THEME_UTILS_LOADED', true);

    /**
     * Get ALL available Bootstrap themes (complete list)
     * @return array Complete theme configuration with all 26 themes
     */
    function getDefaultThemes()
    {
        return [
            "default" => [
                "name" => "1. Default Bootstrap",
                "css" => "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css",
                "description" => "Standard Bootstrap theme"
            ],
            "journal" => [
                "name" => "2. Journal",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/journal/bootstrap.min.css",
                "description" => "News-style theme with elegant typography"
            ],
            "cerulean" => [
                "name" => "3. Cerulean",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/cerulean/bootstrap.min.css",
                "description" => "A calm blue theme"
            ],
            "cosmo" => [
                "name" => "4. Cosmo",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/cosmo/bootstrap.min.css",
                "description" => "Metro-inspired theme"
            ],
            "cyborg" => [
                "name" => "5. Cyborg",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/cyborg/bootstrap.min.css",
                "description" => "Dark theme for night owls"
            ],
            "darkly" => [
                "name" => "6. Darkly",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css",
                "description" => "Flat dark theme"
            ],
            "flatly" => [
                "name" => "7. Flatly",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css",
                "description" => "Flat design theme"
            ],
            "litera" => [
                "name" => "8. Litera",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/litera/bootstrap.min.css",
                "description" => "The Medium theme"
            ],
            "lumen" => [
                "name" => "9. Lumen",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/lumen/bootstrap.min.css",
                "description" => "Light and shadow theme"
            ],
            "lux" => [
                "name" => "10. Lux",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/lux/bootstrap.min.css",
                "description" => "A luxurious theme"
            ],
            "materia" => [
                "name" => "11. Materia",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/materia/bootstrap.min.css",
                "description" => "Material Design theme"
            ],
            "minty" => [
                "name" => "12. Minty",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/minty/bootstrap.min.css",
                "description" => "A fresh, minty theme"
            ],
            "morph" => [
                "name" => "13. Morph",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/morph/bootstrap.min.css",
                "description" => "Morphing design theme"
            ],
            "pulse" => [
                "name" => "14. Pulse",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/pulse/bootstrap.min.css",
                "description" => "Energetic purple theme"
            ],
            "quartz" => [
                "name" => "15. Quartz",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/quartz/bootstrap.min.css",
                "description" => "Sharp and precise theme"
            ],
            "sandstone" => [
                "name" => "16. Sandstone",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/sandstone/bootstrap.min.css",
                "description" => "Warm, sandy theme"
            ],
            "simplex" => [
                "name" => "17. Simplex",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/simplex/bootstrap.min.css",
                "description" => "Minimalist theme"
            ],
            "sketchy" => [
                "name" => "18. Sketchy",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/sketchy/bootstrap.min.css",
                "description" => "Hand-drawn style theme"
            ],
            "slate" => [
                "name" => "19. Slate",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/slate/bootstrap.min.css",
                "description" => "Sleek dark theme"
            ],
            "solar" => [
                "name" => "20. Solar",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/solar/bootstrap.min.css",
                "description" => "Solarized theme"
            ],
            "spacelab" => [
                "name" => "21. Spacelab",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/spacelab/bootstrap.min.css",
                "description" => "Silvery space theme"
            ],
            "superhero" => [
                "name" => "22. Superhero",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/superhero/bootstrap.min.css",
                "description" => "The brave and the blue"
            ],
            "united" => [
                "name" => "23. United",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/united/bootstrap.min.css",
                "description" => "Ubuntu-inspired theme"
            ],
            "vapor" => [
                "name" => "24. Vapor",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/vapor/bootstrap.min.css",
                "description" => "Retro gaming theme"
            ],
            "yeti" => [
                "name" => "25. Yeti",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/yeti/bootstrap.min.css",
                "description" => "A friendly foundation"
            ],
            "zephyr" => [
                "name" => "26. Zephyr",
                "css" => "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/zephyr/bootstrap.min.css",
                "description" => "Breezy and fresh theme"
            ],
            "w5obm_green" => [
                "name" => "27. W5OBM Green",
                "css" => "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css",
                "description" => "W5OBM Amateur Radio Club - Green accessibility theme"
            ]
        ];
    }

    /**
     * Load available themes from themes.js file with fallback
     * @return array Array of available themes
     */
    function loadAvailableThemes()
    {
        static $themes = null;

        if ($themes !== null) {
            return $themes;
        }

        // Try to load from themes.js file
        $possiblePaths = [
            __DIR__ . '/../js/themes.js',
            __DIR__ . '/js/themes.js',
            __DIR__ . '/../themes.js'
        ];

        $themesContent = null;
        foreach ($possiblePaths as $themesPath) {
            if (file_exists($themesPath) && is_readable($themesPath)) {
                $themesContent = file_get_contents($themesPath);
                break;
            }
        }

        if ($themesContent) {
            // Extract themes object using regex
            $pattern = '/const themes = (\{[\s\S]*?\});/';
            if (preg_match($pattern, $themesContent, $matches)) {
                $jsObject = $matches[1];

                // Clean up for JSON parsing
                $jsObject = preg_replace('/\/\/.*$/m', '', $jsObject);
                $jsObject = preg_replace('/\/\*[\s\S]*?\*\//', '', $jsObject);

                $themes = json_decode($jsObject, true);
            }
        }

        // Always fall back to default themes if anything fails
        if ($themes === null || empty($themes)) {
            $themes = getDefaultThemes();
        }

        return $themes;
    }

    /**
     * SAFE: Get current theme for user with robust error handling
     * @param int|null $userId User ID (null for session-based)
     * @return array Current theme data
     */
    function getCurrentTheme($userId = null)
    {
        global $conn;

        $themes = loadAvailableThemes();
        $defaultTheme = [
            'name' => 'Default Bootstrap',
            'css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            'description' => 'Default Bootstrap theme'
        ];

        // Only try database if connection exists and user ID provided
        if ($userId && isset($conn) && $conn instanceof mysqli) {
            try {
                // Check if connection is still valid
                if ($conn->ping()) {
                    $stmt = $conn->prepare("SELECT theme_preference FROM auth_users WHERE id = ? AND status = 'active' LIMIT 1");

                    if ($stmt) {
                        $stmt->bind_param("i", $userId);

                        if ($stmt->execute()) {
                            $result = $stmt->get_result();

                            if ($result && $row = $result->fetch_assoc()) {
                                $themeKey = $row['theme_preference'];

                                // Close statement properly
                                $stmt->close();

                                // Return theme data if valid
                                if ($themeKey && isset($themes[$themeKey])) {
                                    return $themes[$themeKey];
                                }
                            } else {
                                // No results, close statement
                                $stmt->close();
                            }
                        } else {
                            // Execute failed, close statement
                            $stmt->close();
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but don't break the site
                if (function_exists('logError')) {
                    logError("Theme loading error (non-fatal): " . $e->getMessage());
                }
            }
        }

        // Fall back to session
        if (isset($_SESSION['selectedTheme']) && is_array($_SESSION['selectedTheme'])) {
            return $_SESSION['selectedTheme'];
        }

        // Return default theme
        return $themes['default'] ?? $defaultTheme;
    }

    /**
     * SAFE: Save theme preference with robust error handling
     * @param string $themeKey Theme identifier
     * @param int|null $userId User ID (null for session-based)
     * @return bool Success status
     */
    function saveThemePreference($themeKey, $userId = null)
    {
        global $conn;

        $themes = loadAvailableThemes();

        if (!isset($themes[$themeKey])) {
            return false;
        }

        $themeData = $themes[$themeKey];

        // Save to database if connection exists and user ID provided
        if ($userId && isset($conn) && $conn instanceof mysqli) {
            try {
                // Check if connection is still valid
                if ($conn->ping()) {
                    $stmt = $conn->prepare("UPDATE auth_users SET theme_preference = ? WHERE id = ?");

                    if ($stmt) {
                        $stmt->bind_param("si", $themeKey, $userId);
                        $result = $stmt->execute();
                        $stmt->close();

                        if ($result) {
                            // Save to session for immediate use
                            $_SESSION['selectedTheme'] = $themeData;
                            $_SESSION['selectedThemeKey'] = $themeKey;

                            // Log activity if function exists
                            if (function_exists('logActivity')) {
                                logActivity(
                                    $userId,
                                    'theme_preference_updated',
                                    'auth_users',
                                    $userId,
                                    "Theme changed to: {$themeData['name']}"
                                );
                            }

                            return true;
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but don't break the site
                if (function_exists('logError')) {
                    logError("Theme saving error (non-fatal): " . $e->getMessage());
                }
            }
        }

        // Always save to session as fallback
        $_SESSION['selectedTheme'] = $themeData;
        $_SESSION['selectedThemeKey'] = $themeKey;

        return true;
    }

    /**
     * SAFE: Get current theme key for user
     * @param int|null $userId User ID
     * @return string Theme key
     */
    function getCurrentThemeKey($userId = null)
    {
        global $conn;

        if ($userId && isset($conn) && $conn instanceof mysqli) {
            try {
                // Check if connection is still valid
                if ($conn->ping()) {
                    $stmt = $conn->prepare("SELECT theme_preference FROM auth_users WHERE id = ? AND status = 'active' LIMIT 1");

                    if ($stmt) {
                        $stmt->bind_param("i", $userId);

                        if ($stmt->execute()) {
                            $result = $stmt->get_result();

                            if ($result && $row = $result->fetch_assoc()) {
                                $stmt->close();
                                return $row['theme_preference'] ?: 'default';
                            } else {
                                $stmt->close();
                            }
                        } else {
                            $stmt->close();
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but don't break the site
                if (function_exists('logError')) {
                    logError("Theme key loading error (non-fatal): " . $e->getMessage());
                }
            }
        }

        // Fall back to session
        return $_SESSION['selectedThemeKey'] ?? 'default';
    }

    /**
     * Get theme color for preview
     * @param string $themeKey Theme identifier
     * @return string Hex color code
     */
    function getThemeColor($themeKey)
    {
        $colors = [
            'default' => '#007bff',
            'journal' => '#eb6864',
            'cerulean' => '#2FA4E7',
            'cosmo' => '#2780E3',
            'cyborg' => '#2A9FD6',
            'darkly' => '#375A7F',
            'flatly' => '#18BC9C',
            'litera' => '#4582EC',
            'lumen' => '#158CBA',
            'lux' => '#1A1A1A',
            'materia' => '#2196F3',
            'minty' => '#78C2AD',
            'morph' => '#E91E63',
            'pulse' => '#593196',
            'quartz' => '#435F7A',
            'sandstone' => '#93C54B',
            'simplex' => '#D9230F',
            'sketchy' => '#333333',
            'slate' => '#3A3F44',
            'solar' => '#B58900',
            'spacelab' => '#446E9B',
            'superhero' => '#DF691A',
            'united' => '#DD4814',
            'vapor' => '#593196',
            'yeti' => '#008CBA',
            'zephyr' => '#316AC5'
        ];

        return $colors[$themeKey] ?? '#007bff';
    }

    /**
     * Get comprehensive branding colors for navigation/theming surfaces
     */
    function getThemeBranding($themeKey = 'default')
    {
        $baseColor = getThemeColor($themeKey);
        $isDark = isDarkTheme($themeKey);

        $accentPrimary = $baseColor;
        $accentSecondary = adjustColorBrightness($baseColor, $isDark ? 60 : -40);

        $navBaseHex = adjustColorBrightness($baseColor, $isDark ? -80 : 120);
        $navScrolledHex = adjustColorBrightness($navBaseHex, $isDark ? -20 : 15);
        $borderHex = adjustColorBrightness($baseColor, $isDark ? 30 : -90);
        $shadowTint = hexToRgba(adjustColorBrightness($baseColor, $isDark ? -120 : -60), $isDark ? 0.55 : 0.25);
        $sessionBgHex = $isDark ? adjustColorBrightness($baseColor, -40) : '#ffffff';

        return [
            'tone' => $isDark ? 'dark' : 'light',
            'nav_bg' => hexToRgba($navBaseHex, $isDark ? 0.94 : 0.96),
            'nav_bg_scrolled' => hexToRgba($navScrolledHex, $isDark ? 0.97 : 0.98),
            'nav_border' => hexToRgba($borderHex, $isDark ? 0.45 : 0.18),
            'nav_shadow' => '0 8px 26px ' . $shadowTint,
            'text_primary' => $isDark ? '#f8fafc' : '#0f172a',
            'text_secondary' => $isDark ? '#dbeafe' : '#475569',
            'session_pill_bg' => hexToRgba($sessionBgHex, 0.95),
            'session_pill_border' => $isDark
                ? hexToRgba(adjustColorBrightness($baseColor, 20), 0.45)
                : 'rgba(0, 0, 0, 0.05)',
            'session_pill_color' => $isDark ? '#ffffff' : '#1f2937',
            'accent_primary' => $accentPrimary,
            'accent_secondary' => $accentSecondary,
        ];
    }

    /**
     * Site default theme key (global) if configured.
     * Fallback to 'default'.
     */
    function getSiteDefaultThemeKey(): string
    {
        if (!function_exists('get_site_setting')) {
            @require_once __DIR__ . '/site_settings.php';
        }
        return function_exists('get_site_setting') ? (get_site_setting('default_theme_key', 'default') ?: 'default') : 'default';
    }

    function adjustColorBrightness($hexColor, $steps = 0)
    {
        $steps = max(-255, min(255, (int)$steps));
        $hexColor = ltrim($hexColor ?? '#000000', '#');
        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0] . $hexColor[0]
                . $hexColor[1] . $hexColor[1]
                . $hexColor[2] . $hexColor[2];
        }

        $channels = str_split(substr($hexColor, 0, 6), 2);
        $adjusted = array_map(function ($channel) use ($steps) {
            $value = hexdec($channel) + $steps;
            $value = max(0, min(255, $value));
            return $value;
        }, $channels);

        return sprintf('#%02x%02x%02x', $adjusted[0], $adjusted[1], $adjusted[2]);
    }

    function hexToRgba($hexColor, $alpha = 1.0)
    {
        $hexColor = ltrim($hexColor ?? '#000000', '#');
        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0] . $hexColor[0]
                . $hexColor[1] . $hexColor[1]
                . $hexColor[2] . $hexColor[2];
        }

        $alpha = max(0, min(1, (float)$alpha));
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
    }


    /**
     * Check if theme is dark
     * @param string $themeKey Theme identifier
     * @return bool True if dark theme
     */
    function isDarkTheme($themeKey)
    {
        $darkThemes = ['cyborg', 'darkly', 'slate', 'solar', 'superhero', 'vapor'];
        return in_array($themeKey, $darkThemes);
    }

    /**
     * SAFE: Initialize theme system
     */
    function initializeThemeSystem()
    {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            @session_start();
        }

        // Try to load user theme if logged in
        if (isset($_SESSION['user_id'])) {
            try {
                $userTheme = getCurrentTheme($_SESSION['user_id']);
                if ($userTheme && is_array($userTheme)) {
                    $_SESSION['selectedTheme'] = $userTheme;
                    $_SESSION['selectedThemeKey'] = getCurrentThemeKey($_SESSION['user_id']);
                }
            } catch (Exception $e) {
                // Don't let theme errors break the site
                if (function_exists('logError')) {
                    logError("Theme initialization error (non-fatal): " . $e->getMessage());
                }
            }
        } else {
            // Apply site default theme for guests (no user_id)
            try {
                $siteKey = getSiteDefaultThemeKey();
                $themes = loadAvailableThemes();
                if (isset($themes[$siteKey])) {
                    $_SESSION['selectedTheme'] = $themes[$siteKey];
                    $_SESSION['selectedThemeKey'] = $siteKey;
                }
            } catch (Throwable $e) {
                // Non-fatal
            }
        }
    }

    /**
     * Get theme selection HTML for user profile pages
     * @param string $currentThemeKey Current theme key
     * @return string HTML for theme selection
     */
    function getThemeSelectionHTML($currentThemeKey = null)
    {
        $themes = loadAvailableThemes();
        $currentThemeKey = $currentThemeKey ?: getCurrentThemeKey();

        $html = '<div class="mb-3">';
        $html .= '<label for="theme_preference" class="form-label">';
        $html .= '<i class="fas fa-palette me-1"></i>Theme Preference</label>';
        $html .= '<select class="form-control form-control-lg" id="theme_preference" name="theme_preference">';

        foreach ($themes as $themeKey => $themeData) {
            $selected = ($themeKey === $currentThemeKey) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($themeKey) . '" ' . $selected . '>';
            $html .= htmlspecialchars($themeData['name']);
            $html .= '</option>';
        }

        $html .= '</select>';
        $html .= '<div class="form-text">Choose your preferred theme for the website</div>';
        $html .= '</div>';

        return $html;
    }

    // SAFE: Auto-initialize theme system when this file is included
    if (!defined('THEME_AUTO_INIT_DISABLED')) {
        try {
            initializeThemeSystem();
        } catch (Exception $e) {
            // Don't let initialization errors break the site
            if (function_exists('logError')) {
                logError("Theme auto-initialization error (non-fatal): " . $e->getMessage());
            }
        }
    }
} // End if (!defined('THEME_UTILS_LOADED'))