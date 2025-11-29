<?php

/**
 * Auth Users Table Structure Inspector and Fixer
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../include/dbconn.php';

echo "<h2>Auth Users Table Structure Inspector</h2>";

try {
    // Check if auth_users table exists
    $result = $conn->query("SHOW TABLES LIKE 'auth_users'");
    if ($result->num_rows === 0) {
        echo "<p style='color: red;'>❌ auth_users table does NOT exist!</p>";
        echo "<p>Need to create the table first.</p>";
        exit;
    }

    echo "<p style='color: green;'>✅ auth_users table exists</p>";

    // Get current table structure
    echo "<h3>Current Table Structure:</h3>";
    $structure = $conn->query("DESCRIBE auth_users");
    $columns = [];

    if ($structure) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            $columns[] = $row['Field'];
            echo "<tr>";
            echo "<td style='padding: 5px;'><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<h3>Column Analysis:</h3>";

    // Check for password columns
    $password_columns = array_filter($columns, function ($col) {
        return stripos($col, 'password') !== false || stripos($col, 'pwd') !== false || stripos($col, 'pass') !== false;
    });

    if (!empty($password_columns)) {
        echo "<p><strong>Password columns found:</strong> " . implode(', ', $password_columns) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ No password columns found!</p>";
    }

    // Check required columns
    $required_columns = ['username', 'email', 'first_name', 'last_name'];
    $missing_columns = [];

    foreach ($required_columns as $req_col) {
        if (in_array($req_col, $columns)) {
            echo "<p>✅ <strong>$req_col</strong> column exists</p>";
        } else {
            $missing_columns[] = $req_col;
            echo "<p style='color: red;'>❌ <strong>$req_col</strong> column missing</p>";
        }
    }

    // Look for similar column names
    echo "<h3>Column Mapping Suggestions:</h3>";
    $mapping_suggestions = [];

    // Check for password_hash variations
    if (!in_array('password_hash', $columns)) {
        $possible_password = array_filter($columns, function ($col) {
            return stripos($col, 'password') !== false || stripos($col, 'pwd') !== false || $col === 'pass';
        });
        if (!empty($possible_password)) {
            $mapping_suggestions['password_hash'] = reset($possible_password);
            echo "<p>💡 Use '<strong>" . reset($possible_password) . "</strong>' instead of 'password_hash'</p>";
        }
    }

    // Check for other common variations
    $common_mappings = [
        'first_name' => ['fname', 'firstname', 'first'],
        'last_name' => ['lname', 'lastname', 'last'],
        'callsign' => ['call_sign', 'call', 'amateur_call'],
        'phone' => ['telephone', 'phone_number', 'mobile'],
        'is_Admin' => ['admin', 'is_admin', 'admin_flag'],
        'is_SuperAdmin' => ['super_admin', 'is_super_admin', 'superadmin'],
        'created_at' => ['date_created', 'creation_date', 'reg_date']
    ];

    foreach ($common_mappings as $expected => $alternatives) {
        if (!in_array($expected, $columns)) {
            foreach ($alternatives as $alt) {
                if (in_array($alt, $columns)) {
                    $mapping_suggestions[$expected] = $alt;
                    echo "<p>💡 Use '<strong>$alt</strong>' instead of '$expected'</p>";
                    break;
                }
            }
        }
    }

    // Generate updated SQL query
    if (!empty($mapping_suggestions)) {
        echo "<h3>Suggested SQL Query Update:</h3>";

        $original_columns = [
            'username',
            'email',
            'password_hash',
            'first_name',
            'last_name',
            'callsign',
            'phone',
            'status',
            'created_at'
        ];

        $mapped_columns = [];
        foreach ($original_columns as $col) {
            if (isset($mapping_suggestions[$col])) {
                $mapped_columns[] = $mapping_suggestions[$col];
            } elseif (in_array($col, $columns)) {
                $mapped_columns[] = $col;
            } else {
                $mapped_columns[] = "NULL as $col  -- MISSING COLUMN";
            }
        }

        echo "<textarea style='width: 100%; height: 150px; font-family: monospace;'>";
        echo "INSERT INTO auth_users \n";
        echo "(" . implode(", ", $mapped_columns) . ") \n";
        echo "VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
        echo "</textarea>";
    }

    // Show all columns for reference
    echo "<h3>All Available Columns:</h3>";
    echo "<p><code>" . implode(', ', $columns) . "</code></p>";

    // If we can fix it automatically, offer that option
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_auth_utils'])) {
        echo "<h3>Attempting to Fix auth_utils.php...</h3>";

        // Read the auth_utils.php file and update the column names
        $auth_utils_file = __DIR__ . '/auth_utils.php';
        $content = file_get_contents($auth_utils_file);

        if ($content) {
            // Replace the INSERT query with correct column names
            if (isset($mapping_suggestions['password_hash'])) {
                $password_col = $mapping_suggestions['password_hash'];
                echo "<p>Updating password_hash to $password_col...</p>";

                // Update the INSERT query in createUserAccount
                $old_query = 'INSERT INTO auth_users 
            (username, email, password_hash, first_name, last_name, callsign, phone, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, \'active\', NOW())';

                $new_query = "INSERT INTO auth_users 
            (username, email, $password_col, first_name, last_name, callsign, phone, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";

                $content = str_replace($old_query, $new_query, $content);

                if (file_put_contents($auth_utils_file, $content)) {
                    echo "<p style='color: green;'>✅ Updated auth_utils.php successfully!</p>";
                } else {
                    echo "<p style='color: red;'>❌ Failed to update auth_utils.php</p>";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

$conn->close();
?>

<h3>Actions:</h3>
<form method="POST">
    <input type="submit" name="fix_auth_utils" value="Auto-Fix auth_utils.php Column Names"
        style="background: #007cba; color: white; padding: 10px 15px; border: none; cursor: pointer;">
</form>