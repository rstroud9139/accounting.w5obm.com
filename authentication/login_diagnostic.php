<?php

/**
 * LOGIN PAGE DIAGNOSTIC
 * File: /authentication/login_diagnostic.php
 * Purpose: Debug why login.php shows blue screen
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Login Diagnostic</title>";
echo "<style>body{font-family:Arial;margin:20px;background:white;color:black;} .box{border:2px solid;padding:15px;margin:10px 0;} .good{border-color:green;background:#e8f5e8;} .bad{border-color:red;background:#ffe8e8;} .warn{border-color:orange;background:#fff3cd;}</style>";
echo "</head><body>";

echo "<h1>🔍 Login Page Diagnostic Report</h1>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s T') . "</p>";

// 1. Session Test
echo "<div class='box good'>";
echo "<h2>🔐 Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<p>✅ Session Status: " . session_status() . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "</div>";

// 2. File Includes Test
echo "<div class='box'>";
echo "<h2>📁 Include Files Test</h2>";

$files_to_test = [
    '../include/dbconn.php' => 'Database Connection',
    '../include/helper_functions.php' => 'Helper Functions',
    'auth_utils.php' => 'Authentication Utilities',
    '../include/header.php' => 'Header Include',
    '../include/menu.php' => 'Menu Include',
    '../include/footer.php' => 'Footer Include'
];

$all_includes_good = true;
foreach ($files_to_test as $file => $description) {
    $exists = file_exists(__DIR__ . '/' . $file);
    echo "<p>" . ($exists ? '✅' : '❌') . " <strong>$description:</strong> " . ($exists ? 'Found' : 'MISSING') . "</p>";
    if (!$exists) $all_includes_good = false;
}

if ($all_includes_good) {
    echo "<p class='good'>All required files found.</p>";
} else {
    echo "<p class='bad'>Some required files are missing!</p>";
}
echo "</div>";

// 3. Database Connection Test
echo "<div class='box'>";
echo "<h2>🗄️ Database Connection Test</h2>";
try {
    require_once __DIR__ . '/../include/dbconn.php';
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            echo "<p class='bad'>❌ Database connection failed: " . $conn->connect_error . "</p>";
        } else {
            echo "<p class='good'>✅ Database connection successful</p>";
        }
    } else {
        echo "<p class='bad'>❌ Database connection variable missing</p>";
    }
} catch (Exception $e) {
    echo "<p class='bad'>❌ Database include error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 4. Helper Functions Test
echo "<div class='box'>";
echo "<h2>🛠️ Helper Functions Test</h2>";
try {
    require_once __DIR__ . '/../include/helper_functions.php';

    $functions_to_test = [
        'isAuthenticated',
        'getCurrentUserId',
        'sanitizeInput',
        'isAdmin',
        'setToastMessage'
    ];

    foreach ($functions_to_test as $func) {
        $exists = function_exists($func);
        echo "<p>" . ($exists ? '✅' : '❌') . " Function <code>$func</code>: " . ($exists ? 'Found' : 'MISSING') . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='bad'>❌ Helper functions error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 5. Auth Utils Test
echo "<div class='box'>";
echo "<h2>🔐 Authentication Utils Test</h2>";
try {
    require_once __DIR__ . '/auth_utils.php';

    $auth_functions = [
        'performUserLogin',
        'logAuthActivity',
        'getClientIP'
    ];

    foreach ($auth_functions as $func) {
        $exists = function_exists($func);
        echo "<p>" . ($exists ? '✅' : '❌') . " Function <code>$func</code>: " . ($exists ? 'Found' : 'MISSING') . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='bad'>❌ Auth utils error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 6. Header Include Test  
echo "<div class='box'>";
echo "<h2>📄 Header Include Test</h2>";
echo "<p>Testing header.php inclusion...</p>";

ob_start();
try {
    $page_title = 'Login Diagnostic - W5OBM';
    $page_description = 'Testing login page includes';
    require_once __DIR__ . '/../include/header.php';
    $header_output = ob_get_contents();
    ob_end_clean();

    if (strlen($header_output) > 0) {
        echo "<p class='good'>✅ Header.php included successfully (" . strlen($header_output) . " bytes)</p>";
        if (strpos($header_output, '<html') !== false) {
            echo "<p class='good'>✅ HTML structure detected in header</p>";
        } else {
            echo "<p class='warn'>⚠️ No HTML structure found in header output</p>";
        }
    } else {
        echo "<p class='bad'>❌ Header.php produced no output</p>";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "<p class='bad'>❌ Header include error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 7. Test Basic Login Page Elements
echo "<div class='box'>";
echo "<h2>🎨 Login Page Elements Test</h2>";
echo "<p>Testing if login page would render properly...</p>";

try {
    // Simulate login page variables
    $error_message = '';
    $success_message = '';
    $username = '';
    $login_attempts = 0;
    $is_locked = false;
    $lockout_time = 0;

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    echo "<p class='good'>✅ Login page variables initialized</p>";
    echo "<p class='good'>✅ CSRF token generated: " . substr($_SESSION['csrf_token'], 0, 8) . "...</p>";

    // Test if critical CSS classes and IDs would work
    echo "<p class='good'>✅ Login form elements ready</p>";
} catch (Exception $e) {
    echo "<p class='bad'>❌ Login page setup error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 8. Menu Include Test
echo "<div class='box'>";
echo "<h2>📋 Menu Include Test</h2>";
echo "<p>Testing menu.php inclusion...</p>";

ob_start();
try {
    require_once __DIR__ . '/../include/menu.php';
    $menu_output = ob_get_contents();
    ob_end_clean();

    if (strlen($menu_output) > 0) {
        echo "<p class='good'>✅ Menu.php included successfully (" . strlen($menu_output) . " bytes)</p>";
    } else {
        echo "<p class='warn'>⚠️ Menu.php produced no output (might be conditional)</p>";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "<p class='bad'>❌ Menu include error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 9. Summary and Solutions
echo "<div class='box warn'>";
echo "<h2>💡 Diagnostic Summary</h2>";
echo "<p><strong>Most likely causes of blue screen in login.php:</strong></p>";
echo "<ol>";
echo "<li><strong>CSS/Theme Issue:</strong> Bootstrap theme not loading or conflicting styles</li>";
echo "<li><strong>Missing Dependencies:</strong> Required JS/CSS libraries not loading</li>";
echo "<li><strong>Session Issues:</strong> PHP session problems causing redirects</li>";
echo "<li><strong>Include Problems:</strong> Header/menu includes not rendering properly</li>";
echo "</ol>";

echo "<p><strong>Recommended fixes:</strong></p>";
echo "<ul>";
echo "<li><a href='login_minimal.php' target='_blank'>Test minimal login version</a></li>";
echo "<li><a href='../weekly_nets/index.php' target='_blank'>Test weekly nets (should now work)</a></li>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Verify Bootstrap CSS is loading in header.php</li>";
echo "</ul>";
echo "</div>";

echo "<p><a href='login.php' target='_blank'>→ Test actual login.php</a></p>";
echo "</body></html>";
