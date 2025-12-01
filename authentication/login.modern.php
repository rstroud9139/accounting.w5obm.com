<?php

/**
 * Authentication Login Page - W5OBM Amateur Radio Club
 * File: /authentication/login.php
 * 
 * STANDARDS COMPLIANT VERSION
 * Following W5OBM Website Development Guidelines v2.1
 * - Proper include order and structure
 * - Security headers and CSRF protection
 * - Mobile-first responsive design
 * - Accessibility compliance
 * - Error handling per guidelines
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required includes per Website Guidelines (in correct order)
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/auth_utils.php';

// Environment and error handling per Website Guidelines
$is_dev = (strpos($_SERVER['HTTP_HOST'], 'dev.') === 0 ||
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

if ($is_dev) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Security headers per Website Guidelines
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Helper: resolve safe post-login redirect; default to authorization dashboard
// Supports both legacy `redirect` (path-only) and new absolute `return_url` usage.
function resolvePostLoginRedirectPath($user_id, $requested = '', $absoluteReturnUrl = '')
{
    // Default destination is the Accounting Dashboard
    $default = '/accounting/dashboard.php';

    // When an absolute return_url is provided (e.g., from accounting.w5obm.com),
    // prefer it if it is a well-formed HTTP(S) URL. This enables cross-subdomain
    // handoff back to the originating application after successful login.
    if (is_string($absoluteReturnUrl) && $absoluteReturnUrl !== '') {
        $trimmed = trim($absoluteReturnUrl);
        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }
    }

    if (!is_string($requested) || $requested === '') {
        return $default;
    }

    // Normalize to internal absolute path (no schema allowed)
    $path = parse_url($requested, PHP_URL_PATH);
    if (!$path) return $default;
    if ($path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    // Disallow external URLs entirely for the legacy `redirect` parameter
    if (preg_match('#^https?://#i', $requested)) {
        return $default;
    }

    // Admin area requires admin privilege
    if (preg_match('#^/administration(/|$)#', $path)) {
        return isAdmin($user_id) ? $path : $default;
    }

    // Otherwise allow internal path
    return $path;
}

// Check if user is already authenticated (per guidelines)
if (isAuthenticated()) {
    $current_user_id = getCurrentUserId();

    // Respect any stored redirect/return_url but gate admin paths by privilege
    $desired = $_SESSION['login_redirect'] ?? ($_GET['redirect'] ?? '');
    $absoluteReturn = $_GET['return_url'] ?? '';
    $redirect_url = resolvePostLoginRedirectPath($current_user_id, $desired, $absoluteReturn);

    if (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Location: ' . $redirect_url);
        exit();
    } else {
        echo "<meta http-equiv=\"refresh\" content=\"0;url={$redirect_url}\">";
        echo "<script>window.location.href = '" . htmlspecialchars($redirect_url, ENT_QUOTES) . "';</script>";
        exit();
    }
}

// CSRF token generation per Website Guidelines
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$error_message = '';
$success_message = '';
$username = '';
$remember_me = false;
$login_attempts = 0;
$is_locked = false;
$lockout_time = 0;

// Capture optional absolute return_url for use in redirect and in the login form
$return_url = $_REQUEST['return_url'] ?? '';

// Check for lockout (basic rate limiting)
$client_ip = getClientIP();
$lockout_key = 'login_attempts_' . md5($client_ip);

if (isset($_SESSION[$lockout_key])) {
    $attempts_data = $_SESSION[$lockout_key];
    $login_attempts = $attempts_data['count'] ?? 0;
    $last_attempt = $attempts_data['last_attempt'] ?? 0;

    // Reset attempts after 15 minutes
    if (time() - $last_attempt > 900) {
        unset($_SESSION[$lockout_key]);
        $login_attempts = 0;
    } elseif ($login_attempts >= 5) {
        $is_locked = true;
        $lockout_time = 900 - (time() - $last_attempt);
    }
}

// Handle form submission per Website Guidelines
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // CSRF Protection per guidelines
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Security error. Please try again.';
        logAuthActivity(0, 'login_csrf_failure', 'CSRF token mismatch', $client_ip, false);
    } elseif ($is_locked) {
        $error_message = 'Too many failed attempts. Please wait ' . ceil($lockout_time / 60) . ' minutes before trying again.';
        logAuthActivity(0, 'login_locked_out', 'Account locked due to multiple failed attempts', $client_ip, false);
    } else {
        // Get and sanitize input per guidelines
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

        // Validate input
        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
        } else {
            // Use the auth_utils login function
            $login_result = performUserLogin($username, $password, $remember_me);

            // Temporary diagnostic logging to understand login behavior in production
            error_log('LOGIN_RESULT: ' . print_r($login_result, true));

            if ($login_result['success']) {
                // Clear session lockout
                unset($_SESSION[$lockout_key]);

                if ($login_result['requires_2fa']) {
                    // Store intended redirect URL
                    $redirect_url = $_SESSION['login_redirect'] ?? ($_GET['redirect'] ?? '');
                    if ($redirect_url) {
                        $_SESSION['login_redirect'] = $redirect_url;
                    }

                    $twofa = '/authentication/2fa/two_factor_verify.php';
                    if (ob_get_level() > 0) {
                        @ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Location: ' . $twofa);
                        exit();
                    } else {
                        echo "<meta http-equiv=\"refresh\" content=\"0;url={$twofa}\">";
                        echo "<script>window.location.href = '" . htmlspecialchars($twofa, ENT_QUOTES) . "';</script>";
                        exit();
                    }
                } else {
                    // Complete login without 2FA
                    setToastMessage('success', 'Welcome Back!', 'You have been logged in successfully.', 'club-logo');

                    // If an absolute return_url was provided (e.g., from accounting.w5obm.com),
                    // honor it directly so the user is sent back to the originating app.
                    $absoluteReturn = $_POST['return_url'] ?? ($_GET['return_url'] ?? '');
                    if (is_string($absoluteReturn) && $absoluteReturn !== '' && preg_match('#^https?://#i', $absoluteReturn)) {
                        if (ob_get_level() > 0) {
                            @ob_end_clean();
                        }
                        if (!headers_sent()) {
                            header('Location: ' . $absoluteReturn);
                        } else {
                            echo "<meta http-equiv=\"refresh\" content=\"0;url={$absoluteReturn}\">";
                            echo "<script>window.location.href = '" . htmlspecialchars($absoluteReturn, ENT_QUOTES) . "';</script>";
                        }
                        exit();
                    }

                    // Otherwise, determine safe internal redirect URL using legacy redirect handling
                    $desired = $_SESSION['login_redirect'] ?? ($_GET['redirect'] ?? '');
                    unset($_SESSION['login_redirect']);
                    $dest = resolvePostLoginRedirectPath($login_result['user_id'], $desired, '');

                    if (ob_get_level() > 0) {
                        @ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Location: ' . $dest);
                    } else {
                        echo "<meta http-equiv=\"refresh\" content=\"0;url={$dest}\">";
                        echo "<script>window.location.href = '" . htmlspecialchars($dest, ENT_QUOTES) . "';</script>";
                    }
                    exit();
                }
            } else {
                $error_message = $login_result['message'];

                // Track failed attempts by IP
                if (!empty($error_message)) {
                    $login_attempts++;
                    $_SESSION[$lockout_key] = [
                        'count' => $login_attempts,
                        'last_attempt' => time()
                    ];
                }
            }
        }
    }
}

// Set page title per Website Guidelines
$page_title = 'Login - W5OBM Amateur Radio Club';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Include header per Website Guidelines -->
    <?php include __DIR__ . '/../include/header.php'; ?>

    <!-- Additional CSS per guidelines -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Standards-compliant mobile-first CSS -->
    <style>
        :root {
            --primary-blue: var(--primary-blue);
            --secondary-blue: var(--secondary-blue);
            --accent-gold: var(--accent-gold);
            --login-purple: #6f42c1;
            --dark-gray: #2c3e50;
            --light-gray: #f8f9fa;
            --success-green: #28a745;
            --warning-orange: #ffc107;
            --danger-red: #dc3545;
        }

        /* Login page specific background */
        body {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            min-height: 100vh;
        }

        /* Mobile-first container per guidelines */
        .page-container {
            max-width: 100%;
            margin: 10px;
            padding: 15px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Tablet styles per guidelines */
        @media (min-width: 768px) {
            .page-container {
                max-width: 90%;
                margin: 20px auto;
                padding: 20px;
            }
        }

        /* Desktop styles per guidelines */
        @media (min-width: 992px) {
            .page-container {
                max-width: 80%;
                margin: 30px auto;
                padding: 30px;
            }
        }

        /* Large desktop per guidelines */
        @media (min-width: 1200px) {
            .page-container {
                max-width: 70%;
            }
        }

        /* Standards-compliant login card following card design standards */
        .login-card {
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin-bottom: 20px;
        }

        .login-header {
            background: linear-gradient(135deg, var(--theme-accent-primary) 0%, var(--theme-accent-secondary) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: 12px 12px 0 0 !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }

        .login-header-content {
            position: relative;
            z-index: 2;
        }

        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent-gold), #FFC700);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 20px rgba(255, 215, 0, 0.3);
        }

        .login-body {
            padding: 2.5rem;
        }

        /* Form controls per standards - using form-control-lg sizing */
        .form-control {
            border-radius: 0.5rem;
            border: 2px solid #e9ecef;
            padding: 0.875rem 1rem;
            font-size: 1.125rem;
            height: calc(2.875rem + 2px);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--login-purple);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }

        /* Button standards - btn-primary btn-lg */
        .btn-login {
            background: linear-gradient(135deg, var(--theme-accent-primary) 0%, var(--theme-accent-secondary) 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            font-size: 1.125rem;
            font-weight: 600;
            border-width: 2px !important;
            transition: all 0.3s ease;
            width: 100%;
            cursor: pointer;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, var(--theme-accent-secondary) 0%, var(--theme-accent-primary) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            color: white;
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Action button styles per standards */
        .action-button {
            font-weight: 600;
            border-width: 2px !important;
            transition: all 0.3s ease;
        }

        .action-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .input-group-text {
            background: var(--light-gray);
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 0.5rem 0.5rem 0;
            cursor: pointer;
            height: calc(2.875rem + 2px);
            padding: 0.875rem 1rem;
            font-size: 1.125rem;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 0.5rem 0 0 0.5rem;
        }

        /* Alert styling per guidelines */
        .alert {
            border: none;
            border-radius: 15px;
            border-left: 5px solid;
            margin-bottom: 1rem;
        }

        .alert-danger {
            border-left-color: var(--danger-red);
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-red);
        }

        .alert-warning {
            border-left-color: var(--warning-orange);
            background-color: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .alert-success {
            border-left-color: var(--success-green);
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-green);
        }

        /* Accessibility improvements */
        .login-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .login-links a {
            color: var(--login-purple);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .login-links a:hover,
        .login-links a:focus {
            color: var(--secondary-blue);
            text-decoration: underline;
        }

        .security-badge {
            background: var(--light-gray);
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        /* Mobile responsive adjustments per guidelines */
        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }

            .login-body {
                padding: 2rem 1.5rem;
            }

            .login-header {
                padding: 2rem 1.5rem;
            }

            .login-logo {
                width: 60px;
                height: 60px;
            }
        }

        /* Focus indicators for accessibility per standards */
        .form-check-input:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .btn:focus,
        .action-button:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .form-control:focus {
            border-color: var(--theme-accent-primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
            outline: none;
        }
    </style>
</head>

<body>
    <!-- Include menu per Website Guidelines -->
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <!-- Toast Message Display per guidelines -->
    <?php
    if (function_exists('displayToastMessage')) {
        displayToastMessage();
    }
    ?>

    <!-- Toast Container for dynamic messages -->
    <div id="toastContainer" class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999;"></div>

    <!-- Main page container following standards -->
    <div class="page-container">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                    <div class="login-card" data-aos="zoom-in">
                        <!-- Login Header -->
                        <div class="login-header">
                            <div class="login-header-content">
                                <div class="login-logo">
                                    <i class="fas fa-radio fa-2x" style="color: var(--primary-blue);"></i>
                                </div>
                                <h1 class="h2 fw-bold mb-2">W5OBM Login</h1>
                                <p class="mb-0 opacity-90">Olive Branch Amateur Radio Club</p>
                                <small class="opacity-75">Member Authentication Portal</small>
                            </div>
                        </div>

                        <div class="login-body">
                            <!-- Error Messages -->
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger" role="alert" data-aos="fade-in">
                                    <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                                    <?= htmlspecialchars($error_message) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Success Messages -->
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success" role="alert" data-aos="fade-in">
                                    <i class="fas fa-check-circle me-2" aria-hidden="true"></i>
                                    <?= htmlspecialchars($success_message) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Lockout Warning -->
                            <?php if ($login_attempts >= 3 && !$is_locked): ?>
                                <div class="alert alert-warning" role="alert" data-aos="fade-in">
                                    <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                                    <strong>Warning:</strong> You have <?= (5 - $login_attempts) ?> login attempts remaining.
                                </div>
                            <?php endif; ?>

                            <!-- Lockout Message -->
                            <?php if ($is_locked): ?>
                                <div class="alert alert-danger" role="alert" data-aos="fade-in">
                                    <i class="fas fa-lock me-2" aria-hidden="true"></i>
                                    <strong>Account Locked:</strong> Please wait <?= ceil($lockout_time / 60) ?> minutes before trying again.
                                </div>
                            <?php endif; ?>

                            <!-- Login Form -->
                            <form method="POST" action="" id="loginForm" <?= $is_locked ? 'style="display:none;"' : '' ?> data-aos="fade-up" data-aos-delay="200" novalidate>
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">

                                <!-- Username/Email Field -->
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-1" aria-hidden="true"></i>Username or Email
                                    </label>
                                    <input type="text"
                                        class="form-control form-control-lg"
                                        id="username"
                                        name="username"
                                        value="<?= htmlspecialchars($username) ?>"
                                        required
                                        autocomplete="username"
                                        placeholder="Enter your username or email"
                                        aria-describedby="username-help">
                                    <div id="username-help" class="visually-hidden">Enter your registered username or email address</div>
                                </div>

                                <!-- Password Field -->
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-1" aria-hidden="true"></i>Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password"
                                            class="form-control form-control-lg"
                                            id="password"
                                            name="password"
                                            required
                                            autocomplete="current-password"
                                            placeholder="Enter your password"
                                            aria-describedby="password-help password-toggle">
                                        <button class="input-group-text" type="button" id="password-toggle" onclick="togglePassword()" title="Show/Hide Password" aria-label="Toggle password visibility">
                                            <i class="fas fa-eye" id="passwordToggleIcon" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div id="password-help" class="visually-hidden">Enter your account password</div>
                                </div>

                                <!-- Remember Me Checkbox -->
                                <div class="mb-4 form-check">
                                    <input type="checkbox"
                                        class="form-check-input"
                                        id="remember_me"
                                        name="remember_me"
                                        value="1"
                                        <?= $remember_me ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="remember_me">
                                        <i class="fas fa-clock me-1" aria-hidden="true"></i>Remember me for 30 days
                                    </label>
                                </div>

                                <!-- Login Button per standards -->
                                <button type="submit" class="btn btn-primary btn-lg action-button" id="loginButton">
                                    <i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i>Login to W5OBM
                                </button>
                            </form>

                            <!-- Additional Links -->
                            <div class="login-links" data-aos="fade-up" data-aos-delay="400">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <a href="forgot_password.php">
                                            <i class="fas fa-question-circle me-1" aria-hidden="true"></i>Forgot Password?
                                        </a>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <h2 class="h6" style="color: var(--primary-blue);">New to W5OBM?</h2>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <a href="../membership/membership_app.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-user-plus me-2" aria-hidden="true"></i>Join W5OBM Club
                                        </a>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <small class="text-muted d-block mb-2">
                                            Membership includes account creation and full access to club resources
                                        </small>
                                    </div>

                                    <div class="col-12 mb-2">
                                        <hr class="my-2">
                                        <h2 class="h6" style="color: var(--primary-blue);">Already a W5OBM Member?</h2>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <a href="register.php" class="btn btn-success w-100">
                                            <i class="fas fa-user-check me-2" aria-hidden="true"></i>Create Member Account
                                        </a>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">
                                            Current W5OBM members can create their login account here
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Footer -->
                        <div class="security-badge" data-aos="fade-up" data-aos-delay="600">
                            <i class="fas fa-shield-alt me-2" aria-hidden="true"></i>
                            <strong>Secure Login</strong> - Protected by SSL encryption and advanced security measures
                        </div>
                    </div>

                    <!-- Help Text -->
                    <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="800">
                        <small style="color: rgba(255, 255, 255, 0.8);">
                            Having trouble logging in? Contact us at
                            <a href="mailto:admin@w5obm.com" style="color: var(--accent-gold);">admin@w5obm.com</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include footer per Website Guidelines -->
    <?php include __DIR__ . '/../include/footer.php'; ?>

    <!-- AOS Animation Script -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                once: true,
                offset: 50
            });

            // Password visibility toggle
            window.togglePassword = function() {
                const passwordField = document.getElementById('password');
                const toggleIcon = document.getElementById('passwordToggleIcon');
                const toggleButton = document.getElementById('password-toggle');

                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                    toggleButton.setAttribute('aria-label', 'Hide password');
                } else {
                    passwordField.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                    toggleButton.setAttribute('aria-label', 'Show password');
                }
            };

            const loginForm = document.getElementById('loginForm');
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');

            // Focus management per accessibility guidelines
            if (usernameField && usernameField.value === '') {
                usernameField.focus();
            } else if (passwordField) {
                passwordField.focus();
            }

            // Enhanced form validation
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const username = usernameField.value.trim();
                    const password = passwordField.value;

                    if (username === '' || password === '') {
                        e.preventDefault();
                        if (typeof showToast === 'function') {
                            showToast('warning', 'Validation Error', 'Please enter both username and password.', 'club-logo');
                        } else {
                            alert('Please enter both username and password.');
                        }
                        return false;
                    }

                    // Show logging in toast
                    if (typeof showToast === 'function') {
                        showToast('info', 'Logging In', 'Authenticating your credentials...', 'club-logo');
                    }

                    // Disable submit button to prevent double submission
                    const submitButton = document.getElementById('loginButton');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';

                        // Re-enable after 5 seconds as fallback
                        setTimeout(() => {
                            submitButton.disabled = false;
                            submitButton.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Login to W5OBM';
                        }, 5000);
                    }
                });
            }

            // Auto-unlock countdown if locked
            <?php if ($is_locked && $lockout_time > 0): ?>
                let lockoutRemaining = <?= $lockout_time ?>;

                function updateLockoutCountdown() {
                    if (lockoutRemaining <= 0) {
                        location.reload();
                        return;
                    }

                    const lockoutAlert = document.querySelector('.alert-danger');
                    if (lockoutAlert) {
                        const minutes = Math.ceil(lockoutRemaining / 60);
                        lockoutAlert.innerHTML = '<i class="fas fa-lock me-2"></i><strong>Account Locked:</strong> Please wait ' + minutes + ' minutes before trying again.';
                    }

                    lockoutRemaining--;
                }

                setInterval(updateLockoutCountdown, 1000);
            <?php endif; ?>

            // Check for caps lock (accessibility feature)
            function checkCapsLock(event) {
                const capsLockOn = event.getModifierState && event.getModifierState('CapsLock');
                const capsWarning = document.getElementById('capsWarning');

                if (capsLockOn) {
                    if (!capsWarning) {
                        const warning = document.createElement('div');
                        warning.id = 'capsWarning';
                        warning.className = 'alert alert-warning mt-2';
                        warning.setAttribute('role', 'alert');
                        warning.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Caps Lock is ON';
                        event.target.closest('.mb-3').appendChild(warning);
                    }
                } else if (capsWarning) {
                    capsWarning.remove();
                }
            }

            // Add caps lock detection to password field
            if (passwordField) {
                passwordField.addEventListener('keyup', checkCapsLock);
            }

            console.log('W5OBM Login page loaded successfully! Standards compliant. 73s!');
        });
    </script>
</body>

</html>