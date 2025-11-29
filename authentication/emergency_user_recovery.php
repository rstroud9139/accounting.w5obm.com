<?php

/**
 * Emergency User Account Recovery Tool
 * Use this to recreate your admin account if auth_users data was deleted
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Emergency User Account Recovery</h2>";

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

// Try to connect to production database
$servername = $_ENV['PROD_DB_HOST'] ?? 'mysql.w5obm.com';
$username = $_ENV['PROD_DB_USER'] ?? 'w5obmcom_admin';
$password = $_ENV['PROD_DB_PASS'] ?? '';
$dbname = $_ENV['PROD_DB_NAME'] ?? 'w5obm';

echo "<p>Attempting to connect to: $servername as $username</p>";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo "<div style='color: red; background: #ffe6e6; padding: 15px; margin: 10px 0; border: 1px solid red;'>";
    echo "<h3>❌ Database Connection Failed</h3>";
    echo "<p>Error: " . $conn->connect_error . "</p>";
    echo "<p><strong>Possible solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Check if your IP address (67.20.5.150) is allowed on the MySQL server</li>";
    echo "<li>Verify the database password hasn't changed</li>";
    echo "<li>Confirm the database server is accessible from your location</li>";
    echo "<li>Contact your hosting provider to whitelist your IP</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid orange;'>";
    echo "<h3>⚠️ Alternative Solutions</h3>";
    echo "<p>Since we can't connect to the database, here are alternative approaches:</p>";
    echo "<ol>";
    echo "<li><strong>Use cPanel/phpMyAdmin:</strong> Access your hosting control panel and manually create the auth_users table and your account</li>";
    echo "<li><strong>VPN/Different Network:</strong> Try connecting from a different network/IP</li>";
    echo "<li><strong>Direct Server Access:</strong> If you have SSH access, run this script directly on the server</li>";
    echo "<li><strong>Hosting Provider:</strong> Ask them to run the user creation script from their end</li>";
    echo "</ol>";
    echo "</div>";

    // Show the SQL that needs to be run
    echo "<div style='background: #e7f3ff; padding: 15px; margin: 10px 0; border: 1px solid blue;'>";
    echo "<h3>📋 Manual SQL to Run</h3>";
    echo "<p>If you have direct database access, run this SQL to recreate your account:</p>";
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace;'>";
    echo "-- Create auth_users table if it doesn't exist\n";
    echo "CREATE TABLE IF NOT EXISTS auth_users (\n";
    echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
    echo "    username VARCHAR(50) UNIQUE NOT NULL,\n";
    echo "    password_hash VARCHAR(255) NOT NULL,\n";
    echo "    email VARCHAR(100) UNIQUE NOT NULL,\n";
    echo "    first_name VARCHAR(50),\n";
    echo "    last_name VARCHAR(50),\n";
    echo "    role ENUM('admin', 'user', 'moderator') DEFAULT 'user',\n";
    echo "    is_active TINYINT(1) DEFAULT 1,\n";
    echo "    is_verified TINYINT(1) DEFAULT 0,\n";
    echo "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    echo "    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
    echo ");\n\n";

    $admin_password = password_hash('TempPassword123!', PASSWORD_DEFAULT);
    echo "-- Insert your admin account (change username/email as needed)\n";
    echo "INSERT INTO auth_users (username, password_hash, email, first_name, last_name, role, is_active, is_verified) VALUES\n";
    echo "('admin', '$admin_password', 'admin@w5obm.com', 'Admin', 'User', 'admin', 1, 1);\n";
    echo "</textarea>";
    echo "<p><strong>Note:</strong> The password for this account would be: <code>TempPassword123!</code><br>";
    echo "Change it immediately after logging in!</p>";
    echo "</div>";

    exit();
}

echo "<div style='color: green; background: #e6ffe6; padding: 15px; margin: 10px 0; border: 1px solid green;'>";
echo "<h3>✅ Database Connected Successfully!</h3>";
echo "</div>";

// Check if auth_users table exists
$result = $conn->query("SHOW TABLES LIKE 'auth_users'");
if ($result->num_rows == 0) {
    echo "<div style='color: orange; background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid orange;'>";
    echo "<h3>⚠️ auth_users Table Missing</h3>";
    echo "<p>The auth_users table doesn't exist. Creating it now...</p>";
    echo "</div>";

    // Create the table
    $create_table_sql = "
    CREATE TABLE auth_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        role ENUM('admin', 'user', 'moderator') DEFAULT 'user',
        is_active TINYINT(1) DEFAULT 1,
        is_verified TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if ($conn->query($create_table_sql) === TRUE) {
        echo "<div style='color: green;'>✅ auth_users table created successfully</div>";
    } else {
        echo "<div style='color: red;'>❌ Error creating table: " . $conn->error . "</div>";
        exit();
    }
}

// Check current user count
$count_result = $conn->query("SELECT COUNT(*) as count FROM auth_users");
$count = $count_result->fetch_assoc()['count'];
echo "<p><strong>Current users in auth_users table: $count</strong></p>";

if ($count > 0) {
    echo "<h4>Existing Users:</h4>";
    $users_result = $conn->query("SELECT id, username, email, role, is_active, created_at FROM auth_users ORDER BY id");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Created</th></tr>";
    while ($user = $users_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$user['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Form to create emergency admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $admin_username = $_POST['admin_username'] ?? 'admin';
    $admin_email = $_POST['admin_email'] ?? 'admin@w5obm.com';
    $admin_password = $_POST['admin_password'] ?? 'TempPassword123!';

    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

    $insert_sql = "INSERT INTO auth_users (username, password_hash, email, first_name, last_name, role, is_active, is_verified) 
                   VALUES (?, ?, ?, 'Emergency', 'Admin', 'admin', 1, 1)";

    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sss", $admin_username, $password_hash, $admin_email);

    if ($stmt->execute()) {
        echo "<div style='color: green; background: #e6ffe6; padding: 15px; margin: 10px 0; border: 1px solid green;'>";
        echo "<h3>✅ Emergency Admin Account Created!</h3>";
        echo "<p><strong>Username:</strong> $admin_username</p>";
        echo "<p><strong>Password:</strong> $admin_password</p>";
        echo "<p><strong>Email:</strong> $admin_email</p>";
        echo "<p><a href='login.php' style='background: blue; color: white; padding: 10px; text-decoration: none;'>Go to Login Page</a></p>";
        echo "</div>";
    } else {
        echo "<div style='color: red;'>❌ Error creating admin account: " . $conn->error . "</div>";
    }
}

$conn->close();
?>

<h3>Create Emergency Admin Account</h3>
<form method="POST" style="background: #f8f9fa; padding: 20px; border: 1px solid #ddd;">
    <p><label>Username: <input type="text" name="admin_username" value="admin" style="width: 200px; padding: 5px;"></label></p>
    <p><label>Email: <input type="email" name="admin_email" value="admin@w5obm.com" style="width: 200px; padding: 5px;"></label></p>
    <p><label>Password: <input type="text" name="admin_password" value="TempPassword123!" style="width: 200px; padding: 5px;"></label></p>
    <p><input type="submit" name="create_admin" value="Create Emergency Admin Account"
            style="background: #dc3545; color: white; padding: 10px 15px; border: none; cursor: pointer;"></p>
    <p><small>⚠️ Change the password immediately after logging in!</small></p>
</form>