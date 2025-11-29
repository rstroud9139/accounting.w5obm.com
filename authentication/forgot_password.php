<?php

/**
 * Password Reset Request Form
 * File: /authentication/forgot_password.php
 * This file is included by index.php router
 */

// Ensure unified session initialization happens first
require_once __DIR__ . '/../include/session_init.php';
// session_init auto-starts; ensure active as fallback
if (session_status() !== PHP_SESSION_ACTIVE) {
    initializeW5OBMSession();
}
// Include required files
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/../emailSystem/CommunicationsManager.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$is_dev = (stripos($host, 'dev.') === 0 ||
    stripos($host, 'localhost') !== false ||
    strpos($host, '127.0.0.1') !== false);

if (!defined('BASE_URL')) {
    define('BASE_URL', $is_dev ? 'http://dev.w5obm.com/' : 'https://w5obm.com/');
} // Environment-based error handling per Website Guidelines
if ($is_dev) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
} // Security headers per Website Guidelines
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Also set a double-submit cookie with the same token (helps with multi-tab/session edge cases)
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
$email_value = '';

// Optional debug panel variables
$dbg_posted = $_POST['csrf_token'] ?? ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['csrf_token'] ?? '')));
$dbg_session = $_SESSION['csrf_token'] ?? '';
$dbg_prev = $_SESSION['csrf_prev_token'] ?? '';
$dbg_sid = session_id();
$dbg_sname = session_name();
$dbg_cookie = $_COOKIE['CSRF_TOKEN'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---- CONSISTENT CSRF HANDLING WITH FALLBACK / DEBUG ----
    if (isset($_GET['debug_csrf'])) {
        error_log('[DEBUG_CSRF] RAW_POST_KEYS=' . implode(',', array_keys($_POST ?? [])) . ' RAW_POST=' . json_encode($_POST ?? []) . ' RAW_COOKIES=' . json_encode($_COOKIE ?? []) . ' HEADERS_COOKIE=' . ($_SERVER['HTTP_COOKIE'] ?? ''));
    }
    // Accept multiple token sources (POST, Header, GET) and tolerate stale token (previous value)
    $posted_token_raw = $_POST['csrf_token']
        ?? $_POST['_csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_GET['csrf_token']
        ?? '';
    $posted_token = trim($posted_token_raw);

    // Support previous token (in case user opened tab before token changed elsewhere)
    $session_token = $_SESSION['csrf_token'] ?? '';
    $prev_token = $_SESSION['csrf_prev_token'] ?? '';
    $cookie_token = $_COOKIE['CSRF_TOKEN'] ?? '';

    // Valid if EITHER: posted token matches (current/prev/cookie) OR cookie matches (current/prev)
    $has_posted = ($posted_token !== '');
    $cookie_matches = ($cookie_token !== '' && ($cookie_token === $session_token || ($prev_token && $cookie_token === $prev_token)));
    $post_matches = ($has_posted && (
        $posted_token === $session_token ||
        ($prev_token && $posted_token === $prev_token) ||
        ($cookie_token !== '' && $posted_token === $cookie_token)
    ));
    $csrf_valid = ($post_matches || $cookie_matches);

    if (!$csrf_valid) {
        // Same-origin Referer fallback (final safety valve for hosted dev): allow if referer host matches
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $ref_host = $ref ? parse_url($ref, PHP_URL_HOST) : '';
        $cur_host = $_SERVER['HTTP_HOST'] ?? '';
        $referer_ok = ($ref_host && $cur_host && strcasecmp($ref_host, $cur_host) === 0);
        if (isset($_GET['debug_csrf'])) {
            error_log('[DEBUG_CSRF] fallback check referer_ok=' . ($referer_ok ? '1' : '0') . ' ref=' . $ref . ' host=' . $cur_host);
        }

        if ($referer_ok) {
            // Proceed as valid when same-origin referer is present
            $csrf_valid = true;
        } else {
            $error = 'Security token validation failed. Please try again.';
            setToastMessage('danger', 'Security Error', $error, 'club-logo');
            if (isset($_GET['debug_csrf'])) {
                error_log('[DEBUG_CSRF] forgot_password mismatch posted="' . $posted_token . '" session="' . $session_token . '" prev="' . $prev_token . '" cookie="' . $cookie_token . '" UA="' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '" IP="' . ($_SERVER['REMOTE_ADDR'] ?? '') . '"');
            }
            // Do NOT rotate token here; retain existing to allow a refresh to work
        }
    }

    if ($csrf_valid) {
        // Honeypot bot trap: silently succeed without sending email if filled
        $honeypot = trim($_POST['website'] ?? '');
        if ($honeypot !== '') {
            // Pretend success (do not send email or touch DB)
            $message = 'If your email address is in our system, you will receive a password reset link shortly.';
            setToastMessage('info', 'Reset Requested', $message, 'club-logo');
            // Do not proceed further
        } else {
            $email = trim($_POST['email'] ?? '');
            $email_value = $email; // For form repopulation

            // Basic validation
            if (empty($email)) {
                $error = 'Email address is required.';
                setToastMessage('warning', 'Missing Email', $error, 'club-logo');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
                setToastMessage('warning', 'Invalid Email', $error, 'club-logo');
            } else {
                // Rate limiting check
                $rate_limit_key = 'forgot_attempts_' . $_SERVER['REMOTE_ADDR'];
                if (!isset($_SESSION[$rate_limit_key])) {
                    $_SESSION[$rate_limit_key] = [];
                }
                // Clean old attempts (older than 15 minutes)
                $now = time();
                $_SESSION[$rate_limit_key] = array_filter($_SESSION[$rate_limit_key], function ($timestamp) use ($now) {
                    return ($now - $timestamp) < 900; // 15 minutes
                });

                if (count($_SESSION[$rate_limit_key]) >= 3) {
                    $error = 'Too many reset attempts. Please try again in 15 minutes.';
                    setToastMessage('danger', 'Rate Limit', $error, 'club-logo');
                } else {
                    try {
                        // Check if user exists
                        $stmt = $conn->prepare("SELECT id, username, email, first_name, last_name FROM auth_users WHERE email = ? AND is_active = 1");
                        if (!$stmt) {
                            throw new Exception('Database error occurred.');
                        }
                        $stmt->bind_param('s', $email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $stmt->close();

                        if ($user) {
                            // Generate reset token
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                            // Store token in auth_password_resets table
                            $insert_stmt = $conn->prepare("INSERT INTO auth_password_resets (user_id, token, expires_at, used) VALUES (?, ?, ?, 0)");
                            if (!$insert_stmt) {
                                throw new Exception('Database error occurred.');
                            }
                            $insert_stmt->bind_param('iss', $user['id'], $token, $expires);
                            $insert_stmt->execute();
                            $insert_stmt->close();

                            // Send email using CommunicationsManager
                            $communications = new CommunicationsManager(null, $conn);

                            // Build absolute reset link (use dedicated reset_password.php endpoint)
                            $base_reset = $is_dev ? 'http://dev.w5obm.com/' : 'https://w5obm.com/';
                            $reset_link = $base_reset . 'authentication/reset_password.php?token=' . urlencode($token);
                            $user_name = $user['first_name'] ?: $user['username'];

                            $email_body = '<p>Hello ' . htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') . ',</p>';
                            $email_body .= '<p>You have requested a password reset for your W5OBM account.</p>';
                            $email_body .= '<p><a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '">Reset your password</a></p>';
                            $email_body .= '<p>This link will expire in 1 hour.</p>';
                            $email_body .= '<p>If you did not request this reset, please ignore this email.</p>';
                            $email_body .= '<p>73,<br>W5OBM Amateur Radio Club</p>';

                            $email_sent = $communications->sendEmail(
                                $email,
                                'W5OBM Password Reset Request',
                                $email_body
                            );

                            if (!$email_sent) {
                                error_log("Password reset email failed for: " . $email);
                            }
                        }

                        // Always show same message for security (don't reveal if email exists)
                        $message = 'If your email address is in our system, you will receive a password reset link shortly.';
                        setToastMessage('info', 'Reset Requested', $message, 'club-logo');
                        $_SESSION[$rate_limit_key][] = $now;
                        // Keep CSRF token stable to prevent multi-tab mismatches
                        // If you want rotation, enable below two lines and keep prev window
                        // $_SESSION['csrf_prev_token'] = $session_token;
                        // $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } catch (Exception $e) {
                        error_log("Password reset error: " . $e->getMessage());
                        $error = 'A system error occurred. Please try again.';
                        setToastMessage('danger', 'System Error', $error, 'club-logo');
                    }
                }
            }
        }
    }
}
?>

<?php
// Define page information per Website Guidelines  
$page_title = 'Reset Password - W5OBM Amateur Radio Club';
$page_description = 'Request a password reset for your W5OBM account';

// Include standard header per Website Guidelines
require_once __DIR__ . '/../include/header.php';
require_once __DIR__ . '/../include/menu.php';
?>

<!-- Page Container per Website Guidelines -->
<div class="page-container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="card shadow">
                <div class="card-header bg-info text-white text-center">
                    <div class="mb-2">
                        <i class="fas fa-key fa-3x"></i>
                    </div>
                    <h4 class="mb-0">Reset Password</h4>
                    <small>Enter your email to receive a reset link</small>
                </div>

                <div class="card-body">
                    <form method="POST" action="" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <!-- Honeypot field for bots -->
                        <div style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;" aria-hidden="true">
                            <label>Leave this field empty: <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>

                        <div class="mb-3 position-relative">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email Address *
                            </label>
                            <input type="text"
                                class="form-control form-control-lg <?= $error ? 'is-invalid' : '' ?>"
                                id="email"
                                name="email"
                                placeholder="Enter your email address"
                                value="<?= htmlspecialchars($email_value) ?>"
                                autocomplete="email"
                                autofocus>
                            <?php if ($error): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <?php if (isset($_GET['debug_csrf'])): ?>
                                <div class="small mt-2 p-2 border rounded bg-light">
                                    <strong>Debug CSRF:</strong><br>
                                    Session name: <code><?= htmlspecialchars($dbg_sname) ?></code><br>
                                    Session id: <code><?= htmlspecialchars($dbg_sid) ?></code><br>
                                    Session token: <code><?= htmlspecialchars($dbg_session) ?></code><br>
                                    Previous token: <code><?= htmlspecialchars($dbg_prev) ?></code><br>
                                    Posted token: <code><?= htmlspecialchars($dbg_posted) ?></code><br>
                                    Cookie token: <code><?= htmlspecialchars($dbg_cookie) ?></code><br>
                                    Cookies: <code><?= htmlspecialchars($_SERVER['HTTP_COOKIE'] ?? '') ?></code>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($message && !$error): ?>
                            <div class="alert alert-info shadow-sm">
                                <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-info btn-lg shadow">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                            </button>
                        </div>
                    </form>

                    <div class="text-center">
                        <div class="row">
                            <div class="col-12 mb-2">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="../index.php" class="text-decoration-none">
                                    <i class="fas fa-home me-1"></i>Return to Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-center bg-light">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Reset links expire in 1 hour for security
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../include/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Setting up forgot password form validation');

        const form = document.querySelector('form');
        const emailInput = document.getElementById('email');

        if (form && emailInput) {
            console.log('Form and email input found');

            // Simple form handling - no client-side validation, just trim whitespace
            form.addEventListener('submit', function(event) {
                console.log('Form submission attempted');

                // Trim whitespace from email input
                const emailValue = emailInput.value.trim();
                emailInput.value = emailValue;

                console.log('Email value after trim:', emailValue);
                console.log('Allowing form submission to server');

                // Let the server handle all validation - no preventDefault()
            }, false);

            // Clear error styling when user starts typing
            emailInput.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const feedback = document.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.style.display = 'none';
                }
            });
        } else {
            console.log('Form or email input not found');
        }
    });
</script>

</body>

</html>