<?php

/**
 * Two-Factor Authentication Verification - W5OBM Amateur Radio Club
 * File: /authentication/2fa/two_factor_verify.php
 * Purpose: Handle 2FA verification for users during login
 * CREATED: To complete 2FA authentication flow
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../../include/helper_functions.php';
require_once __DIR__ . '/../totp_utils.php';
require_once __DIR__ . '/../auth_utils.php';

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

// Check if 2FA verification is required
if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['2fa_user_id'])) {
    setToastMessage('warning', '2FA Not Required', 'Two-factor authentication is not currently required.');
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['2fa_user_id'];
$error_message = '';
$success_message = '';
$attempts_remaining = 3;

// Check if user exists and has 2FA enabled
try {
    $stmt = $conn->prepare("
        SELECT username, first_name, last_name, two_factor_enabled, 
               two_factor_backup_codes, failed_login_attempts
        FROM auth_users 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !$user['two_factor_enabled']) {
        setToastMessage('error', 'Invalid Session', 'Two-factor authentication session is invalid.');
        header('Location: ../login.php');
        exit();
    }

    // Check backup codes availability
    $backup_codes = $user['two_factor_backup_codes'] ? json_decode($user['two_factor_backup_codes'], true) : [];
    $backup_codes_available = is_array($backup_codes) && count($backup_codes) > 0;
} catch (Exception $e) {
    logError("2FA verification page error: " . $e->getMessage(), 'auth');
    setToastMessage('error', 'System Error', 'An error occurred. Please try logging in again.');
    header('Location: ../login.php');
    exit();
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Security error. Please try again.';
        logAuthActivity($user_id, '2fa_csrf_failure', 'CSRF token mismatch during 2FA verification', null, false);
    } else {
        $verification_code = sanitizeInput($_POST['verification_code'] ?? '');
        $trust_device = isset($_POST['trust_device']) && $_POST['trust_device'] === '1';

        if (empty($verification_code)) {
            $error_message = 'Please enter your verification code.';
        } else {
            // Verify the code using the utility function
            $verification_result = verify2FACode($user_id, $verification_code);

            if ($verification_result['success']) {
                // Complete 2FA authentication
                if (complete2FAAuthentication($user_id, $trust_device)) {
                    $success_message = $verification_result['message'];

                    // Show backup code warning if used
                    if ($verification_result['is_backup_code']) {
                        $remaining = $verification_result['backup_codes_remaining'];
                        setToastMessage(
                            'warning',
                            'Backup Code Used',
                            "You have {$remaining} backup codes remaining. Consider regenerating backup codes."
                        );
                    } else {
                        setToastMessage('success', 'Login Successful', 'Two-factor authentication completed successfully.');
                    }

                    // Determine redirect URL
                    $redirect_url = $_SESSION['login_redirect'] ?? '';
                    unset($_SESSION['login_redirect']);

                    if (empty($redirect_url)) {
                        if (isAdmin($user_id)) {
                            $redirect_url = '../../administration/dashboard.php';
                        } else {
                            $redirect_url = '../dashboard.php';
                        }
                    }

                    header('Location: ' . $redirect_url);
                    exit();
                } else {
                    $error_message = 'Failed to complete authentication. Please try again.';
                }
            } else {
                $error_message = $verification_result['message'];

                // Track failed attempts
                if (!isset($_SESSION['2fa_attempts'])) {
                    $_SESSION['2fa_attempts'] = 0;
                }
                $_SESSION['2fa_attempts']++;

                $attempts_remaining = max(0, 3 - $_SESSION['2fa_attempts']);

                // Lock out after 3 failed attempts
                if ($attempts_remaining <= 0) {
                    unset($_SESSION['2fa_pending']);
                    unset($_SESSION['2fa_user_id']);
                    unset($_SESSION['2fa_attempts']);

                    setToastMessage('error', 'Too Many Attempts', 'Too many failed verification attempts. Please log in again.');
                    logAuthActivity($user_id, '2fa_lockout', 'Too many failed 2FA attempts, session terminated', null, false);
                    header('Location: ../login.php');
                    exit();
                }
            }
        }
    }
}

$page_title = '2FA Verification - W5OBM';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
    <link rel="stylesheet" href="../../css/club-header.css">
</head>

<body>
    <?php
    include __DIR__ . '/../../include/menu.php';
    require_once __DIR__ . '/../../include/club_header.php';
    renderAdminHeader('Two-Factor Authentication', 'Security Verification');
    ?>

    <!-- Toast Message Display -->
    <?php displayToastMessage(); ?>

    <!-- Page Container -->
    <div class="page-container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <!-- 2FA Verification Card -->
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark text-center">
                        <div class="mb-2">
                            <i class="fas fa-shield-alt fa-3x"></i>
                        </div>
                        <h4 class="mb-0">Two-Factor Authentication</h4>
                        <small>Additional security verification required</small>
                    </div>

                    <div class="card-body">
                        <!-- Welcome Message -->
                        <div class="alert alert-info border-0 mb-3">
                            <div class="d-flex">
                                <i class="fas fa-info-circle me-3 mt-1"></i>
                                <div>
                                    <strong>Welcome, <?= htmlspecialchars($user['first_name']) ?>!</strong><br>
                                    Please enter your 6-digit verification code from your authenticator app.
                                </div>
                            </div>
                        </div>

                        <!-- Error Messages -->
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger border-0">
                                <div class="d-flex">
                                    <i class="fas fa-exclamation-triangle me-3 mt-1 text-danger"></i>
                                    <div>
                                        <?= htmlspecialchars($error_message) ?>
                                        <?php if ($attempts_remaining > 0): ?>
                                            <br><small>Attempts remaining: <?= $attempts_remaining ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 2FA Verification Form -->
                        <form method="POST" action="" id="verificationForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                            <!-- Verification Code Field -->
                            <div class="mb-3">
                                <label for="verification_code" class="form-label">
                                    <i class="fas fa-key me-1"></i>Verification Code
                                </label>
                                <input type="text"
                                    class="form-control form-control-lg text-center"
                                    id="verification_code"
                                    name="verification_code"
                                    maxlength="8"
                                    pattern="[0-9\-]{4,8}"
                                    placeholder="000000"
                                    required
                                    autocomplete="one-time-code"
                                    autofocus>
                                <div class="form-text">
                                    Enter the 6-digit code from your authenticator app
                                </div>
                            </div>

                            <!-- Trust Device Option -->
                            <?php if (isTrustedDeviceSupported()): ?>
                                <div class="mb-3 form-check">
                                    <input type="checkbox"
                                        class="form-check-input"
                                        id="trust_device"
                                        name="trust_device"
                                        value="1">
                                    <label class="form-check-label" for="trust_device">
                                        <i class="fas fa-computer me-1"></i>
                                        Trust this device for 30 days
                                    </label>
                                    <div class="form-text">
                                        <small>You won't need 2FA on this device for 30 days</small>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Submit Button -->
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-warning btn-lg shadow">
                                    <i class="fas fa-check me-2"></i>Verify Code
                                </button>
                            </div>
                        </form>

                        <!-- Backup Code Option -->
                        <?php if ($backup_codes_available): ?>
                            <div class="text-center">
                                <hr>
                                <button type="button"
                                    class="btn btn-link text-decoration-none"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#backupCodeForm"
                                    aria-expanded="false">
                                    <i class="fas fa-life-ring me-1"></i>Use Backup Code Instead
                                </button>

                                <div class="collapse mt-3" id="backupCodeForm">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <div class="mb-3">
                                                    <label for="backup_code" class="form-label">
                                                        <i class="fas fa-life-ring me-1"></i>Backup Code
                                                    </label>
                                                    <input type="text"
                                                        class="form-control text-center"
                                                        id="backup_code"
                                                        name="verification_code"
                                                        placeholder="XXXX-XXXX"
                                                        pattern="[A-Z0-9\-]{9}"
                                                        maxlength="9">
                                                    <div class="form-text">
                                                        Enter one of your backup recovery codes
                                                    </div>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-secondary">
                                                        <i class="fas fa-key me-2"></i>Use Backup Code
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Help Links -->
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Having trouble?
                                <a href="mailto:admin@w5obm.com" class="text-decoration-none">Contact Support</a>
                            </small>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="card-footer text-center bg-light">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            This session will expire in 15 minutes
                        </small>
                    </div>
                </div>

                <!-- Additional Help -->
                <div class="text-center mt-3">
                    <a href="../login.php" class="btn btn-link text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const verificationForm = document.getElementById('verificationForm');
            const verificationCode = document.getElementById('verification_code');

            // Auto-format verification code input
            verificationCode.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                if (value.length > 6) {
                    value = value.substring(0, 6);
                }
                e.target.value = value;
            });

            // Auto-submit when 6 digits entered
            verificationCode.addEventListener('input', function(e) {
                if (e.target.value.length === 6) {
                    // Small delay to allow user to see the complete code
                    setTimeout(() => {
                        verificationForm.submit();
                    }, 500);
                }
            });

            // Form submission handling
            verificationForm.addEventListener('submit', function(e) {
                const code = verificationCode.value.trim();

                if (code.length < 4) {
                    e.preventDefault();
                    if (typeof showToast === 'function') {
                        showToast('warning', 'Invalid Code', 'Please enter a valid verification code.', 'club-logo');
                    } else {
                        alert('Please enter a valid verification code.');
                    }
                    return false;
                }

                // Disable submit button to prevent double submission
                const submitButton = verificationForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
                }
            });

            // Auto-focus and countdown timer
            let timeLeft = 900; // 15 minutes in seconds

            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                const timerElement = document.querySelector('.card-footer small');

                if (timerElement) {
                    timerElement.innerHTML = `<i class="fas fa-clock me-1"></i>Session expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
                }

                if (timeLeft <= 0) {
                    // Session expired
                    window.location.href = '../login.php';
                    return;
                }

                timeLeft--;
            }

            // Update timer every second
            const timerInterval = setInterval(updateTimer, 1000);
            updateTimer(); // Initial call

            // Cleanup on page unload
            window.addEventListener('beforeunload', function() {
                clearInterval(timerInterval);
            });
        });
    </script>
</body>

</html>
