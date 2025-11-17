<?php

/**
 * Enhanced Environment Configuration Handler
 * Loads environment-specific settings including base URL and handles subdirectories
 */

class EnvironmentConfig
{
    private static $config = null;

    public static function load()
    {
        if (self::$config === null) {
            $envFile = __DIR__ . '/.env';

            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '#') === 0) continue; // Skip comments

                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    self::$config[$key] = $value;
                }
            }

            // Auto-detect environment if not set in .env
            self::detectEnvironment();
        }

        return self::$config;
    }

    private static function detectEnvironment()
    {
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';

        // Check if we're in development
        $isDev = (strpos($server_name, 'localhost') !== false ||
            strpos($server_name, '127.0.0.1') !== false ||
            strpos($document_root, 'dev.w5obm.com') !== false ||
            strpos($script_name, 'dev.w5obm.com') !== false);

        if ($isDev) {
            self::$config['ENVIRONMENT'] = 'development';
            self::$config['BASE_URL'] = '/w5obmcom_admin/dev.w5obm.com/';
        } else {
            self::$config['ENVIRONMENT'] = 'production';
            self::$config['BASE_URL'] = '/';
        }
    }

    public static function get($key, $default = null)
    {
        $config = self::load();
        return $config[$key] ?? $default;
    }
}
