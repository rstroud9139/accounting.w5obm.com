<?php

/**
 * Email Verification System
 * File: /authentication/verify_email.php
 * Purpose: Handle email verification for new user accounts
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../include/session_init.php';

require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('error_log', __DIR__ . '/logs/verify_email_error_log.txt');
error_reporting(E_ALL);

// Check if user is already logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . 'administration/dashboard.php');
    exit();
}

$message = '';
$error = '';
$verification_successful = false;
$user_info = null;

// Get and validate token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid or missing verification token.';
} else {
    try {
        // Find user with this verification token using auth_email_verification
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.is_active, ev.expires_at, ev.verified_at, u.created_at
            FROM auth_email_verification ev
            JOIN auth_users u ON u.id = ev.user_id
            WHERE ev.verification_token = ?
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
            // Check if verification token is not too old (based on ev.expires_at)
            $current_time = time();
            $expires_time = isset($user_info['expires_at']) ? strtotime($user_info['expires_at']) : 0;

            if ($expires_time && $current_time > $expires_time) {
                $error = 'This verification link has expired. Please contact support for assistance.';
            } else {
                // Verify the email and activate the account
                $update_stmt = $conn->prepare("
                    UPDATE auth_users 
                    SET is_active = 1,
                        email_verified = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                if (!$update_stmt) {
                    throw new Exception('Database update error occurred.');
                }

                $update_stmt->bind_param('i', $user_info['id']);

                if ($update_stmt->execute()) {
                    $update_stmt->close();

                    // Mark verification as completed
                    $vstmt = $conn->prepare("UPDATE auth_email_verification SET verified_at = NOW() WHERE user_id = ? AND verification_token = ?");
                    if ($vstmt) {
                        $vstmt->bind_param('is', $user_info['id'], $token);
                        $vstmt->execute();
                        $vstmt->close();
                    }

                    // Log the verification activity
                    if (function_exists('logActivity')) {
                        logActivity($user_info['id'], 'email_verified', 'auth_users', $user_info['id'], 'Email verification completed successfully');
                    }

                    $verification_successful = true;
                    $message = 'Your email has been successfully verified! Your account is now active and you can log in.';

                    setToastMessage('success', 'Email Verified', 'Your account is now active. You can log in.', 'club-logo');
                } else {
                    throw new Exception('Failed to update account status.');
                }
            }
        } else {
            // Check if token exists in verification table and whether it's already verified
            $verified_stmt = $conn->prepare("
                SELECT ev.verified_at
                FROM auth_email_verification ev
                WHERE ev.verification_token = ?
            ");

            if ($verified_stmt) {
                $verified_stmt->bind_param('s', $token);
                $verified_stmt->execute();
                $verified_result = $verified_stmt->get_result();
                $verified_data = $verified_result->fetch_assoc();
                $verified_stmt->close();

                if ($verified_data) {
                    if (!empty($verified_data['verified_at'])) {
                        $error = 'This email has already been verified. You can log in to your account.';
                    } else {
                        $error = 'Account verification pending. Please contact support for assistance.';
                    }
                } else {
                    $error = 'Invalid verification token. Please check your email link or contact support.';
                }
            } else {
                $error = 'Invalid verification token. Please check your email link or contact support.';
            }
        }
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        $error = 'A system error occurred during verification. Please try again or contact support.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - W5OBM</title>
    <?php include __DIR__ . '/../include/header.php'; ?>
</head>

<body class="bg-gradient-primary">
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <!-- TOAST CONTAINER - CENTERED, 50px BELOW NAVBAR -->
    <div class="toast-container position-fixed d-flex justify-content-center"
        style="top: 70px; left: 0; right: 0; z-index: 9999; pointer-events: none;">
        <div style="pointer-events: auto;">
            <!-- Toasts will be dynamically inserted here -->
        </div>
    </div>

    <div class="container-fluid h-100">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="card shadow-lg" style="border-radius: 20px; border: none;">

                    <?php if ($verification_successful): ?>
                        <!-- Success State -->
                        <div class="card-header text-center bg-success text-white py-4" style="border-radius: 20px 20px 0 0;">
                            <h2 class="mb-0">
                                <i class="fas fa-check-circle me-2"></i>Email Verified!
                            </h2>
                            <p class="mb-0 mt-2">Your account is now active</p>
                        </div>

                        <div class="card-body p-4 text-center">
                            <div class="mb-4">
                                <i class="fas fa-user-check fa-4x text-success"></i>
                            </div>

                            <h4 class="text-success mb-3">Welcome to W5OBM!</h4>

                            <?php if ($user_info): ?>
                                <div class="alert alert-light shadow-sm mb-4" role="alert">
                                    <strong>Account Details:</strong><br>
                                    <strong>Name:</strong> <?= htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')) ?><br>
                                    <strong>Username:</strong> <?= htmlspecialchars($user_info['username']) ?><br>
                                    <strong>Email:</strong> <?= htmlspecialchars($user_info['email']) ?>
                                </div>
                            <?php endif; ?>

                            <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>

                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>authentication/?action=login" class="btn btn-success btn-lg shadow-sm" style="border-radius: 10px;">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In Now
                                </a>
                                <a href="<?= BASE_URL ?>" class="btn btn-outline-primary shadow-sm" style="border-radius: 10px;">
                                    <i class="fas fa-home me-2"></i>Go to Homepage
                                </a>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Error State -->
                        <div class="card-header text-center bg-danger text-white py-4" style="border-radius: 20px 20px 0 0;">
                            <h2 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Verification Failed
                            </h2>
                            <p class="mb-0 mt-2">Unable to verify your email</p>
                        </div>

                        <div class="card-body p-4 text-center">
                            <div class="mb-4">
                                <i class="fas fa-times-circle fa-4x text-danger"></i>
                            </div>

                            <h4 class="text-danger mb-3">Verification Error</h4>

                            <div class="alert alert-danger shadow-sm mb-4" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="../contactus.php" class="btn btn-primary shadow">
                                    <i class="fas fa-envelope me-2"></i>Contact Support
                                </a>
                                <a href="login.php" class="btn btn-outline-secondary shadow">
                                    <i class="fas fa-sign-in-alt me-2"></i>Try to Login
                                </a>
                                <a href="../index.php" class="btn btn-outline-secondary shadow">
                                    <i class="fas fa-home me-2"></i>Go to Homepage
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card-footer text-center bg-light">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            Secure email verification for W5OBM
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-redirect to login after successful verification (optional)
            <?php if ($verification_successful): ?>
                setTimeout(function() {
                    // Optional: Auto-redirect after 10 seconds
                    // window.location.href = '<?= BASE_URL ?>authentication/?action=login';
                }, 10000);
            <?php endif; ?>
        });
    </script>
</body>

</html>
