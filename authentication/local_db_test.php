<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing local database connection...\n";
echo "Host: " . $_SERVER['HTTP_HOST'] ?? 'Not set' . "\n";
echo "Server Address: " . $_SERVER['SERVER_ADDR'] ?? 'Not set' . "\n";

// Load environment
$envFile = '../config/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

// Use local database settings
$servername = $_ENV['LOCAL_DB_HOST'] ?? 'localhost';
$username = $_ENV['LOCAL_DB_USER'] ?? 'root';
$password = $_ENV['LOCAL_DB_PASS'] ?? '';
$dbname = $_ENV['LOCAL_DB_NAME'] ?? 'w5obm';

echo "Connecting to: $servername as $username to database $dbname\n";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully!\n";

// Check auth_users table
$result = $conn->query("SHOW TABLES LIKE 'auth_users'");
if ($result->num_rows > 0) {
    echo "auth_users table exists\n";

    // Check user count
    $count_result = $conn->query("SELECT COUNT(*) as count FROM auth_users");
    $count = $count_result->fetch_assoc()['count'];
    echo "Number of users in auth_users table: $count\n";

    // Show users (without passwords)
    if ($count > 0) {
        $users_result = $conn->query("SELECT id, username, email, created_at FROM auth_users ORDER BY id");
        echo "Users in table:\n";
        while ($user = $users_result->fetch_assoc()) {
            echo "  ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Created: {$user['created_at']}\n";
        }
    }
} else {
    echo "auth_users table does NOT exist\n";
}

$conn->close();
