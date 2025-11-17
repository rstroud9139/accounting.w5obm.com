<?php
// filepath: e:\xampp\htdocs\w5obmcom_admin\w5obm.com\include\load_env.php

function loadEnv($path)
{
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }

    return true;
}

// Load the .env file from the config directory
$envPath = __DIR__ . '/../config/.env';
if (!loadEnv($envPath)) {
    error_log("Warning: .env file not found at: $envPath");
}
