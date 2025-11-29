<?php

/**
 * Quick Auth Users Table Check
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../include/dbconn.php';

echo "<h2>Auth Users Table Structure Check</h2>";

try {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'auth_users'");
    if ($result->num_rows === 0) {
        echo "<p style='color: red;'>❌ auth_users table does NOT exist!</p>";

        echo "<p>Creating auth_users table...</p>";

        $create_sql = "
        CREATE TABLE auth_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            first_name VARCHAR(50),
            last_name VARCHAR(50),
            callsign VARCHAR(10),
            phone VARCHAR(20),
            role ENUM('admin', 'user', 'moderator') DEFAULT 'user',
            is_Admin TINYINT(1) DEFAULT 0,
            is_SuperAdmin TINYINT(1) DEFAULT 0,
            status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
            is_verified TINYINT(1) DEFAULT 1,
            failed_login_attempts INT DEFAULT 0,
            locked_until DATETIME NULL,
            two_factor_enabled TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        if ($conn->query($create_sql) === TRUE) {
            echo "<p style='color: green;'>✅ auth_users table created successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Error creating table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ auth_users table exists</p>";
    }

    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $structure = $conn->query("DESCRIBE auth_users");
    if ($structure) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Count existing records
    $count_result = $conn->query("SELECT COUNT(*) as count FROM auth_users");
    if ($count_result) {
        $count = $count_result->fetch_assoc()['count'];
        echo "<p>Current records in auth_users: <strong>$count</strong></p>";

        if ($count > 0) {
            echo "<h3>Existing Users:</h3>";
$users_result = $conn->query("SELECT id, username, email, callsign, is_active AS status, created_at FROM auth_users ORDER BY id");
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Callsign</th><th>Status</th><th>Created</th></tr>";
            while ($user = $users_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['callsign']) . "</td>";
                echo "<td>" . htmlspecialchars($user['status']) . "</td>";
                echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    // Test members table
    echo "<h3>Members Table Sample:</h3>";
    $members_result = $conn->query("SELECT callsign, first_name, last_name, email, status FROM members WHERE status = 'Active' LIMIT 3");
    if ($members_result && $members_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Callsign</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Status</th></tr>";
        while ($member = $members_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($member['callsign']) . "</td>";
            echo "<td>" . htmlspecialchars($member['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['email']) . "</td>";
            echo "<td>" . htmlspecialchars($member['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p><em>Use one of these callsigns for testing registration</em></p>";
    } else {
        echo "<p style='color: red;'>❌ No active members found in members table!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
