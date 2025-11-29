<?php
// Quick login diagnosis
require_once __DIR__ . '/include/dbconn.php';
require_once __DIR__ . '/include/helper_functions.php';

echo "<h2>Login Diagnosis Tool</h2>\n";

if (isset($_POST['diagnose'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        echo "<h3>Diagnosing login for: " . htmlspecialchars($username) . "</h3>\n";

        // Step 1: Check if user exists
        $stmt = $conn->prepare("SELECT id, username, email, password AS password_hash, is_active FROM auth_users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            echo "<p>❌ <strong>ISSUE FOUND:</strong> User not found in database</p>\n";
        } else {
            echo "<p>✅ User exists in database</p>\n";
            echo "<p>User ID: " . $user['id'] . "</p>\n";
            echo "<p>Username: " . htmlspecialchars($user['username']) . "</p>\n";
            echo "<p>Email: " . htmlspecialchars($user['email']) . "</p>\n";
            echo "<p>Active: " . ($user['is_active'] ? 'Yes' : 'No') . "</p>\n";

            if (!$user['is_active']) {
                echo "<p>❌ <strong>ISSUE FOUND:</strong> User account is not active (is_active = 0)</p>\n";
            }

            // Step 2: Test password
            echo "<h4>Password Verification Test:</h4>\n";
            echo "<p>Stored hash: <code>" . htmlspecialchars(substr($user['password_hash'] ?? '', 0, 50)) . "...</code></p>\n";
            echo "<p>Password length: " . strlen($password) . " characters</p>\n";

            // Test direct PHP password_verify
            $php_verify = password_verify($password, $user['password_hash'] ?? '');
            echo "<p>PHP password_verify(): " . ($php_verify ? "✅ VALID" : "❌ INVALID") . "</p>\n";

            // Test our helper function
            $helper_verify = verifyPassword($password, $user['password_hash'] ?? '');
            echo "<p>verifyPassword() function: " . ($helper_verify ? "✅ VALID" : "❌ INVALID") . "</p>\n";

            if (!$php_verify) {
                echo "<p>❌ <strong>ISSUE FOUND:</strong> Password does not match stored hash</p>\n";

                // Test if it's a hash format issue
                echo "<h5>Hash Analysis:</h5>\n";
                echo "<p>Hash starts with: " . htmlspecialchars(substr($user['password_hash'], 0, 10)) . "</p>\n";
                echo "<p>Hash length: " . strlen($user['password_hash']) . "</p>\n";

                // Test creating a new hash with the same password
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $new_verify = password_verify($password, $new_hash);
                echo "<p>Test new hash with same password: " . ($new_verify ? "✅ Works" : "❌ Still fails") . "</p>\n";

                if ($new_verify) {
                    echo "<p>🔧 <strong>SOLUTION:</strong> The stored password hash appears to be corrupted. The password is valid but the hash in the database is wrong.</p>\n";
                    echo "<p>You can fix this by updating the user's password hash in the database.</p>\n";

                    if (isset($_POST['fix_hash']) && $_POST['fix_hash'] === 'yes') {
                        echo "<h5>Fixing Password Hash:</h5>\n";
                        $stmt = $conn->prepare("UPDATE auth_users SET password = ?, password_changed_at = NOW(), updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param('si', $new_hash, $user['id']);
                        if ($stmt->execute()) {
                            echo "<p>✅ Password hash updated successfully! Try logging in now.</p>\n";
                        } else {
                            echo "<p>❌ Failed to update password hash: " . $stmt->error . "</p>\n";
                        }
                        $stmt->close();
                    } else {
                        echo "<form method='POST' style='margin: 10px 0; padding: 10px; background: #f0f8ff; border: 1px solid #007bff;'>\n";
                        echo "<input type='hidden' name='username' value='" . htmlspecialchars($username) . "'>\n";
                        echo "<input type='hidden' name='password' value='" . htmlspecialchars($password) . "'>\n";
                        echo "<input type='hidden' name='diagnose' value='1'>\n";
                        echo "<input type='hidden' name='fix_hash' value='yes'>\n";
                        echo "<p><strong>Would you like to fix this user's password hash?</strong></p>\n";
                        echo "<button type='submit'>Yes, Fix Password Hash</button>\n";
                        echo "</form>\n";
                    }
                }
            } else {
                echo "<p>✅ Password verification successful!</p>\n";
                echo "<p>If login is still failing, the issue might be elsewhere in the authentication flow.</p>\n";
            }
        }
    }
}
?>

<form method="POST" style="margin: 20px 0; padding: 20px; border: 1px solid #ccc; background: #f9f9f9;">
    <h3>Enter Login Credentials to Diagnose:</h3>
    <p>
        <label>Username or Email:<br>
            <input type="text" name="username" required style="width: 300px; padding: 5px;" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </label>
    </p>
    <p>
        <label>Password:<br>
            <input type="password" name="password" required style="width: 300px; padding: 5px;">
        </label>
    </p>
    <button type="submit" name="diagnose" value="1">Diagnose Login Issue</button>
</form>

<hr>
<p><strong>Instructions:</strong> Enter the exact username/email and password you're trying to use to log in. This tool will check:</p>
<ul>
    <li>If the user exists in the database</li>
    <li>If the account is active</li>
    <li>If the password matches the stored hash</li>
    <li>Provide solutions if issues are found</li>
</ul>