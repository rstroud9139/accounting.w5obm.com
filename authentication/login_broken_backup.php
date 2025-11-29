<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - W5OBM Amateur Radio Club</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">

<?php

/**
 * Authentication Login Page - W5OBM Amateur Radio Club
 * File: /authentication/login.php
 * COMPLETE REDESIGN: Fixed all broken links, undefined functions, and design compliance
 * Following W5OBM Modern Website Design Guidelines with proper Toast implementation
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required includes per Website Guidelines - with error handling
try {
    require_once __DIR__ . '/../include/helper_functions.php';
    require_once __DIR__ . '/auth_utils.php';
    
    // Handle database connection gracefully
    $db_connected = false;
    if (file_exists(__DIR__ . '/../include/dbconn.php')) {
        // Suppress warnings during development
        $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
        include_once __DIR__ . '/../include/dbconn.php';
        error_reporting($old_error_reporting);
        
        // Check if we have a database connection
        if (isset($mysqli) && $mysqli instanceof mysqli && !$mysqli->connect_error) {
            $db_connected = true;
        }
    }
} catch (Exception $e) {
    // Log error but continue to show login form
    error_log("Login page includes error: " . $e->getMessage());
    $db_connected = false;
}

// Environment and error handling per Website Guidelines
$is_dev = (strpos($_SERVER['HTTP_HOST'], 'dev.') === 0 ||
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

if ($is_dev) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Security headers per Website Guidelines
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Check if user is already logged in (only if database is connected)
if ($db_connected && function_exists('isAuthenticated') && isAuthenticated()) {
    $current_user_id = getCurrentUserId();

    if ($current_user_id && function_exists('isAdmin') && isAdmin($current_user_id)) {
        $redirect_url = '../administration/dashboard.php';
    } else {
        $redirect_url = 'dashboard.php';
    }

    // Validate that the target file exists before redirecting
    $target_file = __DIR__ . '/' . str_replace('..', '', $redirect_url);
    if (!file_exists($target_file)) {
        $redirect_url = 'dashboard.php';
        error_log("Admin dashboard not found, redirecting to user dashboard");
    }

    header('Location: ' . $redirect_url);
    exit();
}

// CSRF token generation per Website Guidelines
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$error_message = '';
$success_message = '';
$username = '';
$login_attempts = 0;
$is_locked = false;
$lockout_time = 0;

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // Check if database is available for login processing
    if (!$db_connected) {
        $error_message = 'Login service temporarily unavailable. Please try again in a few minutes.';
    }
    // CSRF Protection
    elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Security error. Please try again.';
        if (function_exists('logAuthActivity')) {
            logAuthActivity(0, 'login_csrf_failure', 'CSRF token mismatch', $client_ip, false);
        }
    } elseif ($is_locked) {
        $error_message = 'Too many failed attempts. Please wait ' . ceil($lockout_time / 60) . ' minutes before trying again.';
        if (function_exists('logAuthActivity')) {
            logAuthActivity(0, 'login_locked_out', 'Account locked due to multiple failed attempts', $client_ip, false);
        }
    } else {
        // Get and sanitize input
        $username = function_exists('sanitizeInput') ? sanitizeInput($_POST['username'] ?? '') : trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

        // Validate input
        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
        } else {
            // Use the auth_utils login function (if available)
            if (function_exists('performUserLogin')) {
                $login_result = performUserLogin($username, $password, $remember_me);
            } else {
                $login_result = ['success' => false, 'message' => 'Authentication system unavailable.'];
            }

            if ($login_result['success']) {
                // Clear session lockout
                unset($_SESSION[$lockout_key]);

                if ($login_result['requires_2fa']) {
                    // Store intended redirect URL
                    $redirect_url = $_SESSION['login_redirect'] ?? ($_GET['redirect'] ?? '');
                    if ($redirect_url) {
                        $_SESSION['login_redirect'] = $redirect_url;
                    }

                    header('Location: 2fa/two_factor_verify.php');
                    exit();
                } else {
                    // Complete login without 2FA
                    setToastMessage('success', 'Welcome Back!', 'You have been logged in successfully.', 'club-logo');

                    // Determine redirect URL
                    $redirect_url = $_SESSION['login_redirect'] ?? ($_GET['redirect'] ?? '');
                    unset($_SESSION['login_redirect']);

                    if ($redirect_url && filter_var($redirect_url, FILTER_VALIDATE_URL) === false) {
                        // Internal redirect
                        header('Location: ' . $redirect_url);
                    } else {
                        if (isAdmin($login_result['user_id'])) {
                            // Verify admin dashboard exists before redirecting
                            $admin_dashboard = __DIR__ . '/../administration/dashboard.php';
                            if (file_exists($admin_dashboard)) {
                                header('Location: ../administration/dashboard.php');
                            } else {
                                error_log("ADMIN DASHBOARD MISSING: Redirecting admin user to regular dashboard");
                                setToastMessage('warning', 'Admin Dashboard Unavailable', 'Admin dashboard is currently unavailable. Using standard dashboard.', 'club-logo');
                                header('Location: dashboard.php');
                            }
                        } else {
                            header('Location: dashboard.php');
                        }
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

// Define page information per Website Guidelines
$page_title = 'Login - W5OBM Amateur Radio Club';
$page_description = 'Secure login for W5OBM Amateur Radio Club members and administrators';

// Include standard header per Website Guidelines
require_once __DIR__ . '/../include/header.php';
?>

    <!-- Custom Login Page Styles -->
    <style>
        :root {
            --primary-blue: var(--primary-blue);
            --secondary-blue: var(--secondary-blue);
            --accent-gold: var(--accent-gold);
            --login-purple: #6f42c1;
            --dark-gray: #2c3e50;
            --light-gray: #f8f9fa;
        }

        body {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            min-height: 100vh;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }

        .login-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: none;
            max-width: 500px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, var(--login-purple), var(--secondary-blue));
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
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

        .form-control {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--login-purple);
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--login-purple), var(--secondary-blue));
            color: white;
            border: none;
            border-radius: 15px;
            padding: 12px 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, var(--secondary-blue), var(--login-purple));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(111, 66, 193, 0.3);
            color: white;
        }

        .input-group-text {
            background: var(--light-gray);
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 15px 15px 0;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 15px 0 0 15px;
        }

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

        .login-links a:hover {
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

        .alert {
            border: none;
            border-radius: 15px;
            border-left: 5px solid;
        }

        .alert-danger {
            border-left-color: #dc3545;
        }

        .alert-warning {
            border-left-color: #ffc107;
        }

        .alert-success {
            border-left-color: #28a745;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                padding: 1rem;
            }

            .login-body {
                padding: 2rem 1.5rem;
            }

            .login-header {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999;"></div>

    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="login-card" data-aos="zoom-in">
                        <!-- Login Header -->
                        <div class="login-header">
                            <div class="login-header-content">
                                <div class="login-logo">
                                    <i class="fas fa-radio fa-2x" style="color: var(--primary-blue);"></i>
                                </div>
                                <h2 class="fw-bold mb-2">W5OBM Login</h2>
                                <p class="mb-0 opacity-90">Olive Branch Amateur Radio Club</p>
                                <small class="opacity-75">Member Authentication Portal</small>
                            </div>
                        </div>

                        <div class="login-body">
                            <!-- Error Messages -->
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger" data-aos="fade-in">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= htmlspecialchars($error_message) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Success Messages -->
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success" data-aos="fade-in">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?= htmlspecialchars($success_message) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Lockout Warning -->
                            <?php if ($login_attempts >= 3 && !$is_locked): ?>
                                <div class="alert alert-warning" data-aos="fade-in">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> You have <?= (5 - $login_attempts) ?> login attempts remaining.
                                </div>
                            <?php endif; ?>

                            <!-- Lockout Message -->
                            <?php if ($is_locked): ?>
                                <div class="alert alert-danger" data-aos="fade-in">
                                    <i class="fas fa-lock me-2"></i>
                                    <strong>Account Locked:</strong> Please wait <?= ceil($lockout_time / 60) ?> minutes before trying again.
                                </div>
                            <?php endif; ?>

                            <!-- Login Form -->
                            <form method="POST" action="" id="loginForm" <?= $is_locked ? 'style="display:none;"' : '' ?> data-aos="fade-up" data-aos-delay="200">
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                <!-- Username/Email Field -->
                                <div class="mb-3">
                                    <label for="username" class="form-label fw-semibold">
                                        <i class="fas fa-user me-1"></i>Username or Email
                                    </label>
                                    <input type="text"
                                        class="form-control"
                                        id="username"
                                        name="username"
                                        value="<?= htmlspecialchars($username) ?>"
                                        required
                                        autocomplete="username"
                                        placeholder="Enter your username or email">
                                </div>

                                <!-- Password Field -->
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-1"></i>Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password"
                                            class="form-control"
                                            id="password"
                                            name="password"
                                            required
                                            autocomplete="current-password"
                                            placeholder="Enter your password">
                                        <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword()" title="Show/Hide Password">
                                            <i class="fas fa-eye" id="passwordToggleIcon"></i>
                                        </span>
                                    </div>
                                </div>

                                <!-- Remember Me Checkbox -->
                                <div class="mb-4 form-check">
                                    <input type="checkbox"
                                        class="form-check-input"
                                        id="remember_me"
                                        name="remember_me"
                                        value="1">
                                    <label class="form-check-label" for="remember_me">
                                        <i class="fas fa-clock me-1"></i>Remember me for 30 days
                                    </label>
                                </div>

                                <!-- Login Button -->
                                <button type="submit" class="btn btn-login btn-lg" id="loginButton">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to W5OBM
                                </button>
                            </form>

                            <!-- Additional Links -->
                            <div class="login-links" data-aos="fade-up" data-aos-delay="400">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <a href="forgot_password.php">
                                            <i class="fas fa-question-circle me-1"></i>Forgot Password?
                                        </a>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <h6 style="color: var(--primary-blue);">New to W5OBM?</h6>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <a href="../membership/membership_app.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-user-plus me-2"></i>Join W5OBM Club
                                        </a>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">
                                            Membership includes account creation and full access to club resources
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Footer -->
                        <div class="security-badge" data-aos="fade-up" data-aos-delay="600">
                            <i class="fas fa-shield-alt me-2"></i>
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

                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                }
            };

            const loginForm = document.getElementById('loginForm');
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');

            // Focus on username field
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

            // Check for caps lock
            function checkCapsLock(event) {
                const capsLockOn = event.getModifierState && event.getModifierState('CapsLock');
                const capsWarning = document.getElementById('capsWarning');

                if (capsLockOn) {
                    if (!capsWarning) {
                        const warning = document.createElement('div');
                        warning.id = 'capsWarning';
                        warning.className = 'alert alert-warning mt-2';
                        warning.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Caps Lock is ON';
                        event.target.parentNode.appendChild(warning);
                    }
                } else if (capsWarning) {
                    capsWarning.remove();
                }
            }

            // Add caps lock detection to password field
            if (passwordField) {
                passwordField.addEventListener('keyup', checkCapsLock);
            }

            console.log('W5OBM Login page loaded successfully! 73s!');
        });
    </script>
</body>

</html>