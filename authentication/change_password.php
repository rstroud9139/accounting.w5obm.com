<?php

/**
 * Change Password
 * File: /authentication/change_password.php
 * Purpose: Allow users to change their password
 * Access: Authenticated users only
 * FIXED: Following Website Guidelines with proper Toast messaging
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required includes per Website Guidelines
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';

// CAPTCHA validation function
function validateCaptcha($response, $secret)
{
    if (empty($response) || empty($secret)) {
        return false;
    }

    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = ['secret' => $secret, 'response' => $response];
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return false;
    }

    $success = json_decode($result, true)['success'] ?? false;
    return $success;
}

// Error handling per Website Guidelines
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$user_id = null;
$is_authenticated = isAuthenticated();
$email_required = false;
$email_verified = false;
$user_email = '';

// Check if user is authenticated or handle email verification
if ($is_authenticated) {
    $user_id = getCurrentUserId();
} else {
    // Allow password change with email verification
    $email_required = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
        $provided_email = trim($_POST['email']);

        // First check auth_users table
        $stmt = $conn->prepare("SELECT id, email FROM auth_users WHERE email = ?");
        $stmt->bind_param('s', $provided_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $user_id = $user_data['id'];
            $user_email = $user_data['email'];
            $email_verified = true;
        } else {
            // Check members table if not in auth_users
            $stmt->close();
            $stmt = $conn->prepare("SELECT id, email FROM members WHERE email = ?");
            $stmt->bind_param('s', $provided_email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $member_data = $result->fetch_assoc();
                $user_email = $member_data['email'];
                $email_verified = true;
                // Email exists in members but not auth_users - user doesn't have an account yet
                $user_id = null;
            }
        }
        $stmt->close();

        if (!$email_verified) {
            // Email doesn't exist in either table - require reCAPTCHA
            $email_verified = true; // Allow but require CAPTCHA
            $user_email = $provided_email;
        }
    }
}

// Load environment variables
$env = @parse_ini_file(__DIR__ . '/../config/.env');

// CSRF token generation per Website Guidelines
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$email_required || $email_verified)) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setToastMessage('danger', 'Security Error', 'Invalid CSRF token. Please try again.', 'club-logo');
        header('Location: change_password.php');
        exit();
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email_input = $_POST['email'] ?? '';
    $captcha_response = $_POST['g-recaptcha-response'] ?? '';

    try {
        // Validation
        if ($is_authenticated) {
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("All fields are required.");
            }
        } else {
            if (empty($new_password) || empty($confirm_password)) {
                throw new Exception("Password fields are required.");
            }
        }

        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }

        if (strlen($new_password) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }

        // Check password strength
        $strength = checkPasswordStrength($new_password);
        if ($strength['strength'] === 'weak') {
            throw new Exception("Password is too weak. Please use a stronger password.");
        }

        // Handle different authentication scenarios
        if ($is_authenticated && $user_id) {
            // Authenticated user - verify current password
            $stmt = $conn->prepare("SELECT password, password_hash FROM auth_users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            $stored_hash = $user['password_hash'] ?? ($user['password'] ?? null);
            if (!$user || !verifyPassword($current_password, $stored_hash)) {
                throw new Exception("Current password is incorrect.");
            }
        } else {
            // Non-authenticated user - different validation
            if (!$email_verified) {
                throw new Exception("Email verification required.");
            }

            // If email exists in neither table, require CAPTCHA
            if (!$user_id) {
                // Load CAPTCHA configuration
                $env = @parse_ini_file(__DIR__ . '/../config/.env');
                $captcha_secret = $env['CAPTCHA_SECRET_KEY'] ?? '';

                if (empty($captcha_response) || !validateCaptcha($captcha_response, $captcha_secret)) {
                    throw new Exception("Please complete the CAPTCHA verification.");
                }
            }
        }

        // Update password (handle different scenarios)
        $new_password_hash = hashPassword($new_password);

        if ($user_id) {
            // User exists in auth_users table
            $stmt = $conn->prepare("UPDATE auth_users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $new_password_hash, $user_id);

            if ($stmt->execute()) {
                $stmt->close();
                logActivity($user_id, 'password_changed', 'auth_users', $user_id, 'Password changed successfully');
                setToastMessage('success', 'Password Changed', 'Your password has been updated successfully.', 'club-logo');

                if ($is_authenticated) {
                    header('Location: dashboard.php');
                } else {
                    header('Location: login.php');
                }
                exit();
            } else {
                throw new Exception("Failed to update password. Please try again.");
            }
        } else {
            // Email exists in members table but no auth account - create new auth account
            $username = strtolower(explode('@', $user_email)[0]); // Use email prefix as username
            $stmt = $conn->prepare("INSERT INTO auth_users (username, email, password, is_active, email_verified, created_at) VALUES (?, ?, ?, 0, 0, NOW())");
            $stmt->bind_param('sss', $username, $user_email, $new_password_hash);

            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                $stmt->close();

                logActivity($new_user_id, 'account_created', 'auth_users', $new_user_id, 'Account created via password change');
                setToastMessage('success', 'Account Created', 'Your account has been created successfully. Please contact an administrator for approval.', 'club-logo');
                header('Location: login.php');
                exit();
            } else {
                throw new Exception("Failed to create account. Please try again.");
            }
        }
    } catch (Exception $e) {
        setToastMessage('danger', 'Error', $e->getMessage(), 'club-logo');
    }
}

$page_title = "Change Password - W5OBM";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>
    <?php if (!$is_authenticated): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>

<body>
    <?php
    include __DIR__ . '/../include/menu.php';
    require_once __DIR__ . '/../include/club_header.php';
    renderAdminHeader('Change Password', 'Authentication');
    ?>

    <!-- Toast Message Display -->
    <?php
    if (function_exists('displayToastMessage')) {
        displayToastMessage();
    }
    ?>

    <!-- Page Container per Website Guidelines -->
    <div class="page-container">

        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-key fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Change Password</h3>
                        <small>Update your account password</small>
                    </div>
                    <div class="col-auto">
                        <a href="dashboard.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-shield-alt me-2"></i>Password Security
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="changePasswordForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="row justify-content-center">
                        <div class="col-md-10 col-lg-8 col-xl-6">
                            <?php if (!$is_authenticated): ?>
                                <!-- Email Verification for Non-Authenticated Users -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-1"></i>Email Address *
                                    </label>
                                    <input type="email"
                                        class="form-control form-control-lg"
                                        id="email"
                                        name="email"
                                        value="<?= htmlspecialchars($user_email) ?>"
                                        required
                                        placeholder="Enter your email address">
                                    <div class="form-text">
                                        Enter the email associated with your membership or account
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($is_authenticated): ?>
                                <!-- Current Password for Authenticated Users -->
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">
                                        <i class="fas fa-lock me-1"></i>Current Password *
                                    </label>
                                    <input type="password"
                                        class="form-control form-control-lg"
                                        id="current_password"
                                        name="current_password"
                                        required
                                        placeholder="Enter your current password">
                                </div>
                            <?php endif; ?>

                            <?php if (!$is_authenticated && !$user_id): ?>
                                <!-- CAPTCHA for unverified emails -->
                                <div class="mb-3 text-center">
                                    <div class="g-recaptcha mx-auto d-inline-block" data-sitekey="<?php echo htmlspecialchars($env['CAPTCHA_SITE_KEY'] ?? ''); ?>"></div>
                                    <div class="form-text">
                                        CAPTCHA verification required for unregistered email addresses
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-key me-1"></i>New Password *
                                </label>
                                <input type="password"
                                    class="form-control form-control-lg"
                                    id="new_password"
                                    name="new_password"
                                    required
                                    placeholder="Enter your new password">
                                <div class="form-text">
                                    Password must be at least 8 characters long
                                </div>
                                <div id="password-strength" class="mt-2"></div>
                            </div>

                            <!-- Confirm New Password -->
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check me-1"></i>Confirm New Password *
                                </label>
                                <input type="password"
                                    class="form-control form-control-lg"
                                    id="confirm_password"
                                    name="confirm_password"
                                    required
                                    placeholder="Confirm your new password">
                                <div id="password-match" class="mt-2"></div>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg shadow me-2">
                                    <i class="fas fa-save me-1"></i>Change Password
                                </button>
                                <?php if ($is_authenticated): ?>
                                    <a href="dashboard.php" class="btn btn-secondary btn-lg shadow">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-secondary btn-lg shadow">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Guidelines -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Password Guidelines
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Strong Password Requirements:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>At least 8 characters long</li>
                            <li><i class="fas fa-check text-success me-2"></i>Mix of uppercase and lowercase letters</li>
                            <li><i class="fas fa-check text-success me-2"></i>Include numbers</li>
                            <li><i class="fas fa-check text-success me-2"></i>Special characters recommended</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Security Tips:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-shield-alt text-info me-2"></i>Use unique passwords for each account</li>
                            <li><i class="fas fa-shield-alt text-info me-2"></i>Don't share your password with others</li>
                            <li><i class="fas fa-shield-alt text-info me-2"></i>Change passwords regularly</li>
                            <li><i class="fas fa-shield-alt text-info me-2"></i>Enable two-factor authentication</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('password-strength');
            const passwordMatch = document.getElementById('password-match');

            // Password strength checker
            function checkPasswordStrength(password) {
                let score = 0;
                let feedback = [];

                if (password.length >= 8) score += 2;
                else feedback.push('Use at least 8 characters');

                if (/[a-z]/.test(password)) score += 1;
                else feedback.push('Include lowercase letters');

                if (/[A-Z]/.test(password)) score += 1;
                else feedback.push('Include uppercase letters');

                if (/[0-9]/.test(password)) score += 1;
                else feedback.push('Include numbers');

                if (/[^a-zA-Z0-9]/.test(password)) score += 2;
                else feedback.push('Include special characters');

                return {
                    score: score,
                    strength: score < 3 ? 'weak' : (score < 6 ? 'medium' : 'strong'),
                    feedback: feedback
                };
            }

            // Update password strength display
            function updatePasswordStrength() {
                const password = newPassword.value;
                if (password.length === 0) {
                    passwordStrength.innerHTML = '';
                    return;
                }

                const strength = checkPasswordStrength(password);
                let strengthClass = 'text-danger';
                let strengthText = 'Weak';

                if (strength.strength === 'medium') {
                    strengthClass = 'text-warning';
                    strengthText = 'Medium';
                } else if (strength.strength === 'strong') {
                    strengthClass = 'text-success';
                    strengthText = 'Strong';
                }

                passwordStrength.innerHTML = `
                    <small class="${strengthClass}">
                        <i class="fas fa-shield-alt me-1"></i>
                        Password Strength: ${strengthText}
                    </small>
                `;
            }

            // Check password match
            function checkPasswordMatch() {
                if (confirmPassword.value.length === 0) {
                    passwordMatch.innerHTML = '';
                    confirmPassword.setCustomValidity('');
                    return;
                }

                if (newPassword.value !== confirmPassword.value) {
                    passwordMatch.innerHTML = `
                        <small class="text-danger">
                            <i class="fas fa-times me-1"></i>
                            Passwords do not match
                        </small>
                    `;
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    passwordMatch.innerHTML = `
                        <small class="text-success">
                            <i class="fas fa-check me-1"></i>
                            Passwords match
                        </small>
                    `;
                    confirmPassword.setCustomValidity('');
                }
            }

            // Event listeners
            newPassword.addEventListener('input', function() {
                updatePasswordStrength();
                checkPasswordMatch();
            });

            confirmPassword.addEventListener('input', checkPasswordMatch);

            // Form submission
            document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
                const strength = checkPasswordStrength(newPassword.value);
                if (strength.strength === 'weak') {
                    e.preventDefault();
                    if (typeof showToast === 'function') {
                        showToast('warning', 'Weak Password', 'Please use a stronger password for better security.', 'club-logo');
                    }
                    return false;
                }
            });
        });
    </script>
</body>

</html>