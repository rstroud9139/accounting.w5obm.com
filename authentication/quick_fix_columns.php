<?php

/**
 * Quick Column Name Detective and Fixer
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../include/dbconn.php';

echo "<h2>Quick Fix for Password Column Issue</h2>";

try {
    // Get current table structure
    $structure = $conn->query("DESCRIBE auth_users");
    $columns = [];

    if ($structure) {
        while ($row = $structure->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    echo "<p><strong>Available columns:</strong> " . implode(', ', $columns) . "</p>";

    // Find password column
    $password_column = null;
    $possible_password_names = ['password_hash', 'password', 'pwd_hash', 'pass_hash', 'user_password'];

    foreach ($possible_password_names as $name) {
        if (in_array($name, $columns)) {
            $password_column = $name;
            break;
        }
    }

    if ($password_column) {
        echo "<p style='color: green;'>✅ Found password column: <strong>$password_column</strong></p>";

        // If it's not password_hash, we need to update the code
        if ($password_column !== 'password_hash') {
            echo "<p style='color: orange;'>⚠️ Need to update auth_utils.php to use '$password_column' instead of 'password_hash'</p>";

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_now'])) {
                echo "<h3>Fixing auth_utils.php...</h3>";

                $auth_utils_file = __DIR__ . '/auth_utils.php';
                $content = file_get_contents($auth_utils_file);

                if ($content) {
                    // Replace all instances of password_hash with the correct column name
                    $updated_content = str_replace('password_hash', $password_column, $content);

                    if (file_put_contents($auth_utils_file, $updated_content)) {
                        echo "<p style='color: green;'>✅ Successfully updated auth_utils.php!</p>";
                        echo "<p>Changed all 'password_hash' references to '$password_column'</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Failed to write to auth_utils.php</p>";
                    }
                }
            } else {
                echo "<form method='POST'>";
                echo "<input type='submit' name='fix_now' value='Fix auth_utils.php Now' ";
                echo "style='background: #dc3545; color: white; padding: 10px 15px; border: none; cursor: pointer;'>";
                echo "</form>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ No password column found!</p>";
        echo "<p>Available columns: " . implode(', ', $columns) . "</p>";
    }

    // Also check for other common column issues
    $column_mapping = [
        'password_hash' => ['password', 'pwd_hash', 'pass_hash', 'user_password'],
        'first_name' => ['fname', 'firstname', 'first'],
        'last_name' => ['lname', 'lastname', 'last'],
        'is_Admin' => ['admin', 'is_admin', 'admin_flag'],
        'is_SuperAdmin' => ['super_admin', 'is_super_admin', 'superadmin']
    ];

    echo "<h3>Column Mapping Check:</h3>";
    foreach ($column_mapping as $expected => $alternatives) {
        if (!in_array($expected, $columns)) {
            foreach ($alternatives as $alt) {
                if (in_array($alt, $columns)) {
                    echo "<p>💡 Found '$alt' - should map to '$expected'</p>";
                    break;
                }
            }
        } else {
            echo "<p>✅ '$expected' exists</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
