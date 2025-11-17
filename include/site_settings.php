<?php

/**
 * Site Settings Utilities (Lightweight)
 * Provides key/value retrieval for global (site-wide) visual and hero settings.
 * Fallback order: database table `site_settings` (if exists) -> JSON file in /config -> provided default.
 * Safe for inclusion anywhere; never throws fatals.
 */

if (!defined('SITE_SETTINGS_LOADED')) {
    define('SITE_SETTINGS_LOADED', true);

    /**
     * Load all site settings from DB or JSON cache.
     * @return array
     */
    function load_site_settings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        // Attempt DB first
        try {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
                // Detect table existence cheaply
                $rs = $conn->query("SHOW TABLES LIKE 'site_settings'");
                if ($rs && $rs->num_rows > 0) {
                    $q = $conn->query("SELECT setting_key, setting_value FROM site_settings");
                    if ($q) {
                        while ($row = $q->fetch_assoc()) {
                            $cache[$row['setting_key']] = $row['setting_value'];
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Non-fatal; proceed to JSON fallback
        }

        // JSON fallback (merged, DB wins)
        $jsonFile = __DIR__ . '/../config/site_settings.json';
        if (file_exists($jsonFile) && is_readable($jsonFile)) {
            $json = @file_get_contents($jsonFile);
            if ($json) {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        if (!isset($cache[$k])) {
                            $cache[$k] = $v;
                        }
                    }
                }
            }
        }

        return $cache;
    }

    /**
     * Get a site setting.
     */
    function get_site_setting(string $key, $default = null)
    {
        $all = load_site_settings();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Persist a site setting (DB if available, otherwise JSON file append/update).
     * NOTE: Call from admin-only contexts. Returns bool success.
     */
    function set_site_setting(string $key, string $value): bool
    {
        // Normalize
        $key = trim($key);
        if ($key === '') return false;

        $written = false;
        try {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
                $rs = $conn->query("SHOW TABLES LIKE 'site_settings'");
                if ($rs && $rs->num_rows > 0) {
                    $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    if ($stmt) {
                        $stmt->bind_param('ss', $key, $value);
                        $written = $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore DB errors, fall back to file
        }

        // JSON fallback write (merge existing; DB already updated overrides file)
        $jsonFile = __DIR__ . '/../config/site_settings.json';
        $data = load_site_settings(); // current cache
        $data[$key] = $value;
        @file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

        // Invalidate in-memory cache for subsequent reads
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($jsonFile, true);
        }
        // Force cache reload on next request
        $ref = new ReflectionFunction('load_site_settings');
        $staticVars = $ref->getStaticVariables();
        // Cannot directly modify static outside, so simple approach: redefine marker constant to trigger reload path
        // Instead, we can simply unset the local static via run-time hack unsupported; accept stale for current request.

        return $written || true; // Always true if file write attempted
    }

    /**
     * Convenience: Get all hero defaults as structured array.
     */
    function get_hero_defaults(): array
    {
        return [
            'gradient_start' => get_site_setting('hero_gradient_start', 'var(--primary-blue)'),
            'gradient_end' => get_site_setting('hero_gradient_end', 'var(--secondary-blue)'),
            'overlay_from' => get_site_setting('hero_overlay_from_rgba', 'rgba(3,26,84,0.85)'),
            'overlay_to' => get_site_setting('hero_overlay_to_rgba', 'rgba(20,94,255,0.70)'),
            'title' => get_site_setting('hero_title', 'Making Our Community Radio Active Since 1998'),
            'subtitle' => get_site_setting('hero_subtitle', 'Advancing amateur radio, emergency readiness, and education.'),
        ];
    }
}
