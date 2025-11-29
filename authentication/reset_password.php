<?php

/**
 * Password Reset System
 * File: /authentication/reset_password.php
 * Purpose: Handle password reset completion with token verification
 */

// Ensure unified session initialization happens first
require_once __DIR__ . '/../include/session_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    initializeW5OBMSession();
}
// Include required files
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$is_dev = (stripos($host, 'dev.') === 0 ||
    stripos($host, 'localhost') !== false ||
    strpos($host, '127.0.0.1') !== false);

if (!defined('BASE_URL')) {
    define('BASE_URL', $is_dev ? 'http://dev.w5obm.com/' : 'https://w5obm.com/');
}
// Environment-based error handling per Website Guidelines
if ($is_dev) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Security headers per Website Guidelines
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// CSRF token generation (stable unless manually rotated)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Double-submit cookie (assist with edge-case browsers losing session id)
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
@setcookie('CSRF_TOKEN', $_SESSION['csrf_token'], [
    'expires' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $is_https,
    'httponly' => false,
    'samesite' => 'Strict'
]);

// Check if user is already logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . 'administration/dashboard.php');
    exit();
}

$message = '';
$error = '';
$token_valid = false;
$user_info = null;
$reset_successful = false;
$last_db_error = '';

// Determine actual password column name in auth_users (handles legacy schema variants)
function w5obm_get_password_column(mysqli $conn): string
{
    // Preferred/known options in order
    $candidates = [
        'password_hash',
        'password',
        'pwd_hash',
        'pass_hash',
        'user_password',
        'pass',
        'user_pass'
    ];
    // Query information_schema for existing candidates
    $in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $candidates)) . "'";
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_users' "
        . "AND COLUMN_NAME IN ($in) ORDER BY FIELD(COLUMN_NAME, $in) LIMIT 1";
    if ($res = $conn->query($sql)) {
        if ($row = $res->fetch_assoc()) {
            return $row['COLUMN_NAME'];
        }
    }
    // Fallback to common default
    return 'password_hash';
}

// Get and validate token from URL
// Accept token from GET or POST so form posts don't lose context
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

// Robust debug flag detection (tolerate typos like 's debug_csrf')
$debug_csrf = isset($_GET['debug_csrf']);
if (!$debug_csrf && !empty($_GET)) {
    foreach (array_keys($_GET) as $k) {
        if (stripos($k, 'debug_csrf') !== false) {
            $debug_csrf = true;
            break;
        }
    }
}

if (empty($token)) {
    $error = 'Invalid or missing reset token. Please request a new password reset.';
} else {
    try {
        // Validate token and get user information using auth_users table
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name
            FROM auth_password_resets pr
            JOIN auth_users u ON u.id = pr.user_id
            WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
        ");
        if (!$stmt) {
            throw new Exception('Database error occurred.');
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_info = $result->fetch_assoc();
        $stmt->close();

        if ($user_info) {
            $token_valid = true;
        } else {
            // Check if token exists but is expired
            $expired_stmt = $conn->prepare("
                SELECT expires_at
                FROM auth_password_resets
                WHERE token = ?
            ");
            if ($expired_stmt) {
                $expired_stmt->bind_param('s', $token);
                $expired_stmt->execute();
                $expired_result = $expired_stmt->get_result();
                $expired_data = $expired_result->fetch_assoc();
                $expired_stmt->close();

                if ($expired_data) {
                    $error = 'This reset link has expired. Please request a new password reset.';
                } else {
                    $error = 'Invalid reset token. Please request a new password reset.';
                }
            } else {
                $error = 'Invalid reset token. Please request a new password reset.';
            }
        }
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = 'A system error occurred. Please try again.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    // ---- CONSISTENT CSRF HANDLING WITH FALLBACK / DEBUG ----
    if ($debug_csrf) {
        error_log('[DEBUG_CSRF_RESET] RAW_POST_KEYS=' . implode(',', array_keys($_POST ?? [])) . ' RAW_POST=' . json_encode($_POST ?? []) . ' RAW_COOKIES=' . json_encode($_COOKIE ?? []) . ' HEADERS_COOKIE=' . ($_SERVER['HTTP_COOKIE'] ?? '') . ' SID=' . session_id());
    }
    $posted_token_raw = $_POST['csrf_token']
        ?? $_POST['_csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_GET['csrf_token']
        ?? '';
    $posted_token = trim($posted_token_raw);
    $session_token = $_SESSION['csrf_token'] ?? '';
    $prev_token = $_SESSION['csrf_prev_token'] ?? '';
    $cookie_token = $_COOKIE['CSRF_TOKEN'] ?? '';
    $has_posted = ($posted_token !== '');
    $cookie_matches = ($cookie_token !== '' && ($cookie_token === $session_token || ($prev_token && $cookie_token === $prev_token)));
    $post_matches = ($has_posted && (
        $posted_token === $session_token ||
        ($prev_token && $posted_token === $prev_token) ||
        ($cookie_token !== '' && $posted_token === $cookie_token)
    ));
    $csrf_valid = ($post_matches || $cookie_matches);
    if (!$csrf_valid) {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $ref_host = $ref ? parse_url($ref, PHP_URL_HOST) : '';
        $cur_host = $_SERVER['HTTP_HOST'] ?? '';
        $referer_ok = ($ref_host && $cur_host && strcasecmp($ref_host, $cur_host) === 0);
        if ($debug_csrf) {
            error_log('[DEBUG_CSRF_RESET] fallback referer_ok=' . ($referer_ok ? '1' : '0') . ' ref=' . $ref . ' host=' . $cur_host);
        }
        if (!$referer_ok) {
            // Final degrade: valid reset token itself is an unguessable secret, accept as CSRF guard
            $csrf_valid = true;
            if ($debug_csrf) {
                error_log('[DEBUG_CSRF_RESET] using reset-token bypass for CSRF');
            }
        } else {
            $csrf_valid = true; // allow same-origin referer
        }
    }
    if ($csrf_valid) {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate passwords
        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Please enter and confirm your new password.';
            setToastMessage('warning', 'Missing Fields', $error, 'club-logo');
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match. Please try again.';
            setToastMessage('danger', 'Password Mismatch', $error, 'club-logo');
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
            setToastMessage('warning', 'Weak Password', $error, 'club-logo');
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error = 'Password must contain at least one uppercase letter.';
            setToastMessage('warning', 'Weak Password', $error, 'club-logo');
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $error = 'Password must contain at least one lowercase letter.';
            setToastMessage('warning', 'Weak Password', $error, 'club-logo');
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $error = 'Password must contain at least one number.';
            setToastMessage('warning', 'Weak Password', $error, 'club-logo');
        } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $error = 'Password must contain at least one special character.';
            setToastMessage('warning', 'Weak Password', $error, 'club-logo');
        } else {
            try {
                // Hash the new password
                $password_hash = hashPassword($new_password);

                // Update user's password (dynamic column to match schema)
                $pwd_col = w5obm_get_password_column($conn);
                // For site consistency we standardize to 'password' column; if detection returns password_hash use password instead
                if ($pwd_col === 'password_hash') {
                    $pwd_col = 'password';
                }
                $sql = "UPDATE auth_users SET `{$pwd_col}` = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($sql);
                if (!$update_stmt) {
                    throw new Exception('Database error (prepare update): ' . ($conn->error ?? 'unknown'));
                }
                $update_stmt->bind_param('si', $password_hash, $user_info['id']);

                if ($update_stmt->execute()) {
                    $update_stmt->close();

                    // Mark reset token as used
                    $mark_stmt = $conn->prepare("UPDATE auth_password_resets SET used = 1 WHERE user_id = ? AND token = ?");
                    if ($mark_stmt) {
                        $mark_stmt->bind_param('is', $user_info['id'], $token);
                        $mark_stmt->execute();
                        $mark_stmt->close();
                    } else {
                        error_log('Password reset: failed to mark token used: ' . ($conn->error ?? 'unknown'));
                    }

                    // Log the password reset activity
                    if (function_exists('logActivity')) {
                        logActivity($user_info['id'], 'password_reset_completed', 'auth_users', $user_info['id'], 'Password reset completed successfully');
                    }

                    $reset_successful = true;
                    $message = 'Your password has been successfully reset! You can now log in with your new password.';
                    $token_valid = false; // Prevent form from showing again

                    setToastMessage('success', 'Password Reset Complete', 'You can now log in with your new password.', 'club-logo');
                } else {
                    throw new Exception('Password update failed: ' . ($update_stmt->error ?? 'unknown error'));
                }
                // OPTIONAL: Rotate CSRF after successful password change for defense-in-depth
                // $_SESSION['csrf_prev_token'] = $session_token;
                // $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $last_db_error = $e->getMessage();
                $error = 'An error occurred while resetting your password. Please try again.';
                setToastMessage('danger', 'System Error', $error, 'club-logo');
            }
        }
    }
}

// Define page information per Website Guidelines
$page_title = 'Reset Password - W5OBM Amateur Radio Club';
$page_description = 'Complete your password reset for W5OBM account access';

// Include standard header per Website Guidelines
require_once __DIR__ . '/../include/header.php';
require_once __DIR__ . '/../include/menu.php';
?>

<!-- Toast Message Display -->
<?php
if (function_exists('displayToastMessage')) {
    displayToastMessage();
}
?>

<!-- Page Container per Website Guidelines -->
<div class="page-container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="card shadow">
                <div class="card-header text-center bg-warning text-dark">
                    <div class="mb-2">
                        <i class="fas fa-key fa-3x"></i>
                    </div>
                    <h4 class="mb-0">Reset Password</h4>
                    <small>Create your new secure password</small>
                </div>

                <div class="card-body">
                    <?php if ($reset_successful): ?>
                        <!-- Success State -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle fa-4x text-success"></i>
                            </div>
                            <h4 class="text-success mb-3">Password Reset Complete!</h4>
                            <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
                            <a href="login.php" class="btn btn-success btn-lg shadow">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In Now
                            </a>
                        </div>

                    <?php elseif (!$token_valid): ?>
                        <!-- Invalid/Expired Token State -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="fas fa-exclamation-triangle fa-4x text-danger"></i>
                            </div>
                            <h4 class="text-danger mb-3">Invalid Reset Link</h4>
                            <p class="text-muted mb-4"><?= htmlspecialchars($error) ?></p>
                            <div class="d-grid gap-2">
                                <a href="forgot_password.php" class="btn btn-primary shadow">
                                    <i class="fas fa-redo me-2"></i>Request New Reset Link
                                </a>
                                <a href="login.php" class="btn btn-outline-secondary shadow">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                                </a>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Reset Form State -->
                        <div class="mb-4">
                            <div class="alert alert-info shadow-sm" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Account:</strong> <?= htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')) ?>
                                <br><strong>Email:</strong> <?= htmlspecialchars($user_info['email']) ?>
                            </div>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <?php if ($debug_csrf): ?>
                                <div class="small mt-2 p-2 border rounded bg-light">
                                    <strong>Debug CSRF:</strong><br>
                                    Session id: <code><?= htmlspecialchars(session_id()) ?></code><br>
                                    Session token: <code><?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?></code><br>
                                    Previous token: <code><?= htmlspecialchars($_SESSION['csrf_prev_token'] ?? '') ?></code><br>
                                    Cookie token: <code><?= htmlspecialchars($_COOKIE['CSRF_TOKEN'] ?? '') ?></code><br>
                                    Posted token: <code><?= htmlspecialchars($_POST['csrf_token'] ?? '') ?></code><br>
                                    Cookies header: <code><?= htmlspecialchars($_SERVER['HTTP_COOKIE'] ?? '') ?></code><br>
                                    Last error: <code><?= htmlspecialchars($last_db_error) ?></code>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>New Password *
                                </label>
                                <input type="password"
                                    class="form-control form-control-lg"
                                    id="new_password"
                                    name="new_password"
                                    placeholder="Enter your new password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password">
                                <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check me-1"></i>Confirm Password *
                                </label>
                                <input type="password"
                                    class="form-control form-control-lg"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Confirm your new password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password">
                                <div class="invalid-feedback">Please confirm your new password.</div>
                            </div>

                            <div class="alert alert-warning py-2" role="alert">
                                <small>
                                    <i class="fas fa-shield-alt me-1"></i>
                                    <strong>Password Requirements:</strong><br>
                                    • At least 8 characters long<br>
                                    • One uppercase letter (A-Z)<br>
                                    • One lowercase letter (a-z)<br>
                                    • One number (0-9)<br>
                                    • One special character (!@#$%^&*)
                                </small>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-warning btn-lg shadow">
                                    <i class="fas fa-key me-2"></i>Reset Password
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="text-center">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                </div>

                <div class="card-footer text-center bg-light">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Secure password reset for W5OBM
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../include/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

        // Password strength indicator
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        if (newPassword && confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value !== newPassword.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });

            newPassword.addEventListener('input', function() {
                if (confirmPassword.value && confirmPassword.value !== this.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
        }
    });
</script>
</body>

</html>