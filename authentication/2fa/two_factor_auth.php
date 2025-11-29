<?php
// filepath: e:\xampp\htdocs\w5obmcom_admin\w5obm.com\authentication\2fa\two_factor_auth.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../../include/session_init.php';

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../../include/helper_functions.php';

// Standard authentication check
requireAuthentication();

$user_id = getCurrentUserId();
$error_message = '';
$success_message = '';

// Fetch user 2FA status
// Determine schema support for 2FA columns
$has_enabled_col = false;
$has_codes_col = false;
try {
    if ($res = $conn->query("SHOW COLUMNS FROM auth_users LIKE 'two_factor_enabled'")) {
        $has_enabled_col = $res->num_rows > 0; $res->close();
    }
    if ($res = $conn->query("SHOW COLUMNS FROM auth_users LIKE 'two_factor_backup_codes'")) {
        $has_codes_col = $res->num_rows > 0; $res->close();
    }
} catch (Exception $e) {
    // ignore
}

$two_factor_enabled = false;
$backup_codes = [];
if ($has_enabled_col || $has_codes_col) {
    $selCols = [];
    if ($has_enabled_col) $selCols[] = 'two_factor_enabled';
    if ($has_codes_col) $selCols[] = 'two_factor_backup_codes';
    $cols = implode(', ', $selCols);
    if ($cols) {
        $stmt = $conn->prepare("SELECT $cols FROM auth_users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        if ($has_enabled_col) {
            $two_factor_enabled = (bool)($user['two_factor_enabled'] ?? false);
        }
        if ($has_codes_col && !empty($user['two_factor_backup_codes'])) {
            $backup_codes = json_decode($user['two_factor_backup_codes'], true) ?: [];
        }
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        toastError('Security Error', 'CSRF token validation failed.');
        header('Location: ' . BASE_URL . 'authentication/2fa/two_factor_auth.php');
        exit();
    }

    if (isset($_POST['disable_2fa'])) {
        if (!$has_enabled_col) {
            toastError('Unavailable', '2FA disable is not supported on current schema.');
            header('Location: ' . BASE_URL . 'authentication/2fa/two_factor_auth.php');
            exit();
        }
        // Disable 2FA
        $stmt = $conn->prepare("UPDATE auth_users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_backup_codes = NULL WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        logError("2FA disabled for user $user_id", 'auth');
        toastSuccess('2FA Disabled', 'Two-factor authentication has been disabled.');
        header('Location: ' . BASE_URL . 'authentication/2fa/two_factor_auth.php');
        exit();
    } elseif (isset($_POST['regenerate_backup_codes'])) {
        if (!$has_codes_col) {
            toastError('Unavailable', 'Backup codes not supported on current schema.');
            header('Location: ' . BASE_URL . 'authentication/2fa/two_factor_auth.php');
            exit();
        }
        // Regenerate backup codes
        $new_codes = generateBackupCodes();
        $codes_json = json_encode(array_map(function ($code) {
            return ['code' => $code, 'used' => false];
        }, $new_codes));
        $stmt = $conn->prepare("UPDATE auth_users SET two_factor_backup_codes = ? WHERE id = ?");
        $stmt->bind_param('si', $codes_json, $user_id);
        $stmt->execute();
        $stmt->close();
        logError("2FA backup codes regenerated for user $user_id", 'auth');
        $_SESSION['backup_codes'] = $new_codes;
        toastSuccess('Backup Codes Regenerated', 'Your backup codes have been regenerated.');
        header('Location: ' . BASE_URL . 'authentication/2fa/two_factor_auth.php');
        exit();
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = "Two-Factor Authentication Settings - W5OBM";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../../include/header.php'; ?>
    <style>
        .backup-codes {
            font-family: monospace;
            font-size: 1.2em;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 1em;
        }

        .backup-codes .used {
            text-decoration: line-through;
            color: #aaa;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication Settings</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($two_factor_enabled): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Two-factor authentication is <strong>ENABLED</strong> on your account.
                            </div>
                            <form method="post" class="mb-3">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" name="disable_2fa" class="btn btn-danger">
                                    <i class="fas fa-times-circle me-2"></i>Disable 2FA
                                </button>
                            </form>
                            <h6>Backup Codes</h6>
                            <div class="backup-codes">
                                <?php if (!empty($backup_codes)): ?>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($backup_codes as $code): ?>
                                            <li class="<?= !empty($code['used']) ? 'used' : '' ?>">
                                                <?= htmlspecialchars($code['code']) ?>
                                                <?php if (!empty($code['used'])): ?>
                                                    <span class="badge bg-secondary ms-2">Used</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <em>No backup codes available.</em>
                                <?php endif; ?>
                            </div>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" name="regenerate_backup_codes" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-sync-alt me-1"></i>Regenerate Backup Codes
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Two-factor authentication is <strong>NOT enabled</strong> on your account.
                            </div>
                            <a href="<?= BASE_URL ?>authentication/2fa/two_factor_setup.php" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Enable 2FA
                            </a>
                        <?php endif; ?>
                        <div class="mt-4 text-center">
                            <a href="<?= BASE_URL ?>authentication/?action=dashboard" class="btn btn-secondary">
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
            <?php
            $toastMessages = getToastMessages();
            if (!empty($toastMessages)):
                foreach ($toastMessages as $toast): ?>
                    showToast(
                        '<?= htmlspecialchars($toast['type'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['title'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['message'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['theme'], ENT_QUOTES) ?>'
                    );
            <?php endforeach;
            endif; ?>
        });
    </script>
</body>

</html>
