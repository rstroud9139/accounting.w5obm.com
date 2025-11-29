<?php
echo "Checking .env file loading...\n";

// Load environment
$envFile = '../config/.env';
if (file_exists($envFile)) {
    echo ".env file exists\n";
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
} else {
    echo ".env file NOT found\n";
}

// Show what we loaded for database credentials
echo "Database credentials loaded:\n";
echo "PROD_DB_HOST: " . ($_ENV['PROD_DB_HOST'] ?? 'NOT SET') . "\n";
echo "PROD_DB_USER: " . ($_ENV['PROD_DB_USER'] ?? 'NOT SET') . "\n";
echo "PROD_DB_PASS: " . (isset($_ENV['PROD_DB_PASS']) ? '[SET - ' . strlen($_ENV['PROD_DB_PASS']) . ' chars]' : 'NOT SET') . "\n";
echo "PROD_DB_NAME: " . ($_ENV['PROD_DB_NAME'] ?? 'NOT SET') . "\n";
