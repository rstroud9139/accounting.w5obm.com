<?php

/**
 * FIXED Two-Factor Authentication Setup
 * File: /authentication/2fa/two_factor_setup.php
 * Purpose: Initial 2FA setup process for users
 * FIXES: All missing functions, proper MySQLi usage, Website Guidelines compliance
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../../include/helper_functions.php';

// Standard authentication check per guidelines
requireAuthentication();

$user_id = getCurrentUserId();

// Generate CSRF token per Website Guidelines
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Error handling per guidelines
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error_message = '';
$success_message = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Check if 2FA is already enabled
try {
    // Gracefully handle schema without 2FA columns
    $has_enabled = false;
    if ($res = $conn->query("SHOW COLUMNS FROM auth_users LIKE 'two_factor_enabled'")) { $has_enabled = $res->num_rows > 0; $res->close(); }
    $select = $has_enabled ? 'two_factor_enabled, username, email' : 'username, email';
    $stmt = $conn->prepare("SELECT $select FROM auth_users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if ($has_enabled && !empty($user_data['two_factor_enabled'])) {
        setToastMessage('info', '2FA Already Enabled', 'Two-factor authentication is already enabled on your account.', 'club-logo');
        header('Location: two_factor_auth.php');
        exit();
    }
} catch (Exception $e) {
    error_log("2FA Setup Database Error: " . $e->getMessage());
    $error_message = "A database error occurred. Please try again.";
}

// CSRF Protection for POST requests per guidelines
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setToastMessage('danger', 'Security Error', 'Invalid CSRF token. Please try again.', 'club-logo');
        header('Location: two_factor_setup.php');
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_secret'])) {
        // Step 1: Generate new secret
        try {
            $secret = generateTOTPSecret();
            $_SESSION['temp_2fa_secret'] = $secret;
            $step = 2;
            setToastMessage('info', '2FA Secret Generated', 'Please scan the QR code with your authenticator app.', 'club-logo');
        } catch (Exception $e) {
            error_log("2FA Secret generation error: " . $e->getMessage());
            $error_message = "Failed to generate security key. Please try again.";
        }
    } elseif (isset($_POST['verify_code'])) {
        // Step 2: Verify code and enable 2FA
        $verification_code = trim($_POST['verification_code'] ?? '');

        if (!isset($_SESSION['temp_2fa_secret'])) {
            $error_message = "Session expired. Please start over.";
            $step = 1;
        } elseif (empty($verification_code) || !preg_match('/^\d{6}$/', $verification_code)) {
            $error_message = "Please enter a valid 6-digit verification code.";
            $step = 2;
        } else {
            $secret = $_SESSION['temp_2fa_secret'];

            if (verifyTOTPCodeWithSecret($secret, $verification_code)) {
                try {
                    // Enable 2FA for the user
                    $stmt = $conn->prepare("UPDATE auth_users SET two_factor_enabled = 1, two_factor_secret = ?, two_factor_setup_at = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $secret, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    // Generate backup codes
                    $backup_codes = generateBackupCodes();
                    $backup_codes_json = json_encode(array_map(function ($code) {
                        return ['code' => $code, 'used' => false];
                    }, $backup_codes));

                    $stmt = $conn->prepare("UPDATE auth_users SET two_factor_backup_codes = ? WHERE id = ?");
                    $stmt->bind_param('si', $backup_codes_json, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['backup_codes'] = $backup_codes;
                    unset($_SESSION['temp_2fa_secret']);

                    logAuthActivity($user_id, '2fa_enabled', 'Two-factor authentication enabled', getClientIP(), true);

                    $success_message = "Two-factor authentication has been successfully enabled!";
                    $step = 3;
                } catch (Exception $e) {
                    error_log("2FA Enable Error: " . $e->getMessage());
                    $error_message = "Failed to enable two-factor authentication. Please try again.";
                    $step = 2;
                }
            } else {
                $error_message = "Invalid verification code. Please try again.";
                $step = 2;
            }
        }
    } elseif (isset($_POST['save_backup_codes'])) {
        // Step 3: Acknowledge backup codes saved
        unset($_SESSION['backup_codes']);
        setToastMessage('success', '2FA Setup Complete', 'Two-factor authentication has been successfully enabled for your account.', 'club-logo');
        header('Location: two_factor_auth.php');
        exit();
    }
}

// Generate QR code data for step 2
$qr_data = array();
if ($step === 2 && isset($_SESSION['temp_2fa_secret'])) {
    $qr_data = generateTOTPQRCode($user_data['username'], $_SESSION['temp_2fa_secret']);
}

$page_title = "Two-Factor Authentication Setup - W5OBM";
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <!-- Toast Container per Website Guidelines -->
    <div class="toast-container position-fixed d-flex justify-content-center"
        style="top: 70px; left: 0; right: 0; z-index: 9999; pointer-events: none;">
        <div style="pointer-events: auto;">
            <!-- Toasts will be dynamically inserted here -->
        </div>
    </div>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h3><i class="fas fa-shield-alt me-2"></i>Enable Two-Factor Authentication</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Progress indicator -->
                        <div class="mb-4">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="step-indicator <?= $step >= 1 ? 'active' : '' ?>">
                                        <i class="fas fa-key fa-2x"></i>
                                        <h6 class="mt-2">Generate Key</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="step-indicator <?= $step >= 2 ? 'active' : '' ?>">
                                        <i class="fas fa-qrcode fa-2x"></i>
                                        <h6 class="mt-2">Scan QR Code</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="step-indicator <?= $step >= 3 ? 'active' : '' ?>">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                        <h6 class="mt-2">Complete</h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($step === 1): ?>
                            <!-- Step 1: Introduction and Generate Secret -->
                            <div class="step-content">
                                <h4><i class="fas fa-info-circle me-2"></i>Before We Begin</h4>
                                <p>You'll need an authenticator app on your phone to use two-factor authentication. We recommend:</p>

                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card border-primary shadow">
                                            <div class="card-body text-center">
                                                <i class="fab fa-google fa-2x text-primary mb-2"></i>
                                                <h6>Google Authenticator</h6>
                                                <small class="text-muted">Available for iOS and Android</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-primary shadow">
                                            <div class="card-body text-center">
                                                <i class="fas fa-mobile-alt fa-2x text-primary mb-2"></i>
                                                <h6>Microsoft Authenticator</h6>
                                                <small class="text-muted">Available for iOS and Android</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>What is Two-Factor Authentication?</strong><br>
                                    2FA adds an extra layer of security to your account by requiring both your password
                                    and a 6-digit code from your authenticator app to log in.
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <div class="text-center">
                                        <button type="submit" name="generate_secret" class="btn btn-success btn-lg shadow">
                                            <i class="fas fa-key me-2"></i>Generate Security Key
                                        </button>
                                    </div>
                                </form>
                            </div>

                        <?php elseif ($step === 2): ?>
                            <!-- Step 2: QR Code and Verification -->
                            <div class="step-content">
                                <h4><i class="fas fa-qrcode me-2"></i>Scan QR Code</h4>
                                <p>Open your authenticator app and scan this QR code:</p>

                                <div class="row">
                                    <div class="col-md-6 text-center">
                                        <?php if (!empty($qr_data['qr_url'])): ?>
                                            <div class="card shadow">
                                                <div class="card-body">
                                                    <img src="<?= htmlspecialchars($qr_data['qr_url']) ?>"
                                                        alt="QR Code"
                                                        class="img-fluid"
                                                        style="max-width: 200px;">
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                QR Code generation failed. Please use manual entry.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Can't scan the QR code?</h6>
                                        <p>Enter this code manually in your authenticator app:</p>
                                        <div class="alert alert-info">
                                            <strong>Account:</strong> <?= htmlspecialchars($qr_data['account_name'] ?? $user_data['username']) ?><br>
                                            <strong>Key:</strong> <code><?= htmlspecialchars($qr_data['manual_entry'] ?? '') ?></code>
                                        </div>

                                        <h6 class="mt-4">Verify Setup</h6>
                                        <p>Enter the 6-digit code from your authenticator app:</p>

                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <div class="mb-3">
                                                <input type="text"
                                                    class="form-control form-control-lg text-center shadow-sm"
                                                    name="verification_code"
                                                    placeholder="000000"
                                                    maxlength="6"
                                                    pattern="\d{6}"
                                                    required
                                                    autofocus>
                                            </div>
                                            <button type="submit" name="verify_code" class="btn btn-success shadow">
                                                <i class="fas fa-check me-2"></i>Verify & Enable 2FA
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($step === 3): ?>
                            <!-- Step 3: Backup Codes -->
                            <div class="step-content">
                                <h4><i class="fas fa-key me-2"></i>Save Your Backup Codes</h4>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Important:</strong> Save these backup codes in a safe place.
                                    You can use them to access your account if you lose your phone.
                                </div>

                                <?php if (isset($_SESSION['backup_codes'])): ?>
                                    <div class="card border-warning mb-4 shadow">
                                        <div class="card-header bg-warning text-dark">
                                            <h5 class="mb-0"><i class="fas fa-key me-2"></i>Your Backup Codes</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <?php foreach ($_SESSION['backup_codes'] as $code): ?>
                                                    <div class="col-md-6 mb-2">
                                                        <div class="card bg-light">
                                                            <div class="card-body text-center py-2">
                                                                <code class="fs-5"><?= htmlspecialchars($code) ?></code>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>How to use backup codes:</strong><br>
                                        If you can't access your authenticator app, use one of these codes instead of the 6-digit code during login.
                                        Each code can only be used once.
                                    </div>

                                    <div class="text-center">
                                        <button onclick="window.print()" class="btn btn-outline-primary me-2 shadow">
                                            <i class="fas fa-print me-2"></i>Print Codes
                                        </button>

                                        <button onclick="downloadCodes()" class="btn btn-outline-info me-2 shadow">
                                            <i class="fas fa-download me-2"></i>Download Codes
                                        </button>

                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" name="save_backup_codes" class="btn btn-success shadow">
                                                <i class="fas fa-check me-2"></i>I've Saved My Backup Codes
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 text-center">
                            <a href="../dashboard.php" class="btn btn-outline-secondary shadow">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-format verification code input
            const codeInput = document.querySelector('input[name="verification_code"]');
            if (codeInput) {
                codeInput.addEventListener('input', function(e) {
                    // Only allow digits
                    e.target.value = e.target.value.replace(/\D/g, '');

                    // Limit to 6 digits
                    if (e.target.value.length > 6) {
                        e.target.value = e.target.value.slice(0, 6);
                    }
                });
            }

            // Display any toast messages
            <?php
            $toast = getToastMessage();
            if ($toast): ?>
                if (typeof showToast === 'function') {
                    showToast(
                        '<?= htmlspecialchars($toast['type'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['title'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['message'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['icon'] ?? 'club-logo', ENT_QUOTES) ?>'
                    );
                }
            <?php endif; ?>
        });

        // Download backup codes function
        function downloadCodes() {
            <?php if (isset($_SESSION['backup_codes'])): ?>
                const codes = <?= json_encode($_SESSION['backup_codes']) ?>;
                const content = 'W5OBM Two-Factor Authentication Backup Codes\n' +
                    'Generated: ' + new Date().toLocaleString() + '\n' +
                    'Account: <?= htmlspecialchars($user_data['username']) ?>\n\n' +
                    'IMPORTANT: Keep these codes safe and secure!\n' +
                    'Each code can only be used once.\n\n' +
                    codes.join('\n');

                const blob = new Blob([content], {
                    type: 'text/plain'
                });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'W5OBM_2FA_Backup_Codes.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            <?php endif; ?>
        }
    </script>

    <!-- Enhanced CSS -->
    <style>
        .step-indicator {
            opacity: 0.5;
            transition: opacity 0.3s;
            color: #6c757d;
        }

        .step-indicator.active {
            opacity: 1;
            color: #28a745;
        }

        .card {
            border: none;
            border-radius: 15px;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .card.border-warning,
            .card.border-warning * {
                visibility: visible;
            }

            .card.border-warning {
                position: absolute;
                left: 0;
                top: 0;
            }
        }

        /* Mobile responsiveness */
        @media (max-width: 576px) {
            .container {
                padding: 1rem;
            }

            .card-body {
                padding: 1rem;
            }
        }
    </style>
</body>

</html>
