<?php

/**
 * Session Management
 * File: /authentication/session_management.php
 * Purpose: Manage active user sessions and allow termination of sessions
 * VERIFY: Check if this file is actually referenced anywhere in your system
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../include/session_init.php';

// FIXED: Use consolidated helper functions (1 level up from authentication)
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';

// ADD THIS FUNCTION HERE IF NOT IN helper_functions.php
if (!function_exists('time_ago')) {
    function time_ago($datetime)
    {
        $time = time() - strtotime($datetime);

        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 2592000) return floor($time / 86400) . ' days ago';
        return date('M j, Y', strtotime($datetime));
    }
}

// ADD THIS FUNCTION HERE IF NOT IN helper_functions.php
if (!function_exists('getToastMessages')) {
    function getToastMessages()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $messages = $_SESSION['toast_messages'] ?? [];
        unset($_SESSION['toast_messages']);
        return $messages;
    }
}

// FIXED: Authentication check per guidelines
if (!isAuthenticated()) {
    setToastMessage('warning', 'Login Required', 'Please login to manage your sessions.', 'club-logo');
    header('Location: login.php');
    exit();
}

$user_id = getCurrentUserId();

// FIXED: CSRF token generation per guidelines
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle session termination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setToastMessage('danger', 'Security Error', 'Invalid CSRF token.', 'club-logo');
        header('Location: session_management.php');
        exit();
    }

    if ($_POST['action'] === 'terminate_session') {
        $session_id = $_POST['session_id'] ?? '';
        $current_session = session_id();

        if ($session_id && $session_id !== $current_session) {
            $stmt = $conn->prepare("DELETE FROM auth_sessions WHERE user_id = ? AND session_token = ?");
            $stmt->bind_param('is', $user_id, $session_id);
            if ($stmt->execute()) {
                setToastMessage('success', 'Session Terminated', 'The selected session has been terminated.', 'club-logo');
                logActivity($user_id, 'session_terminated', 'auth_sessions', null, "Terminated session: $session_id");
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'terminate_all') {
        $current_session = session_id();
        $stmt = $conn->prepare("DELETE FROM auth_sessions WHERE user_id = ? AND session_token != ?");
        $stmt->bind_param('is', $user_id, $current_session);
        if ($stmt->execute()) {
            $terminated_count = $stmt->affected_rows;
            setToastMessage('success', 'Sessions Terminated', "Terminated $terminated_count other sessions.", 'club-logo');
            logActivity($user_id, 'all_sessions_terminated', 'auth_sessions', null, "Terminated $terminated_count sessions");
        }
        $stmt->close();
    }

    header('Location: session_management.php');
    exit();
}

// Get active sessions
$sessions = [];
try {
    $stmt = $conn->prepare("
        SELECT session_token, ip_address, user_agent, created_at, expires_at, updated_at
        FROM auth_sessions 
        WHERE user_id = ? AND expires_at > NOW()
        ORDER BY updated_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Session management error: " . $e->getMessage());
}

$page_title = "Session Management - W5OBM";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>
</head>

<body>
    <?php
    include __DIR__ . '/../include/menu.php';
    require_once __DIR__ . '/../include/club_header.php';
    renderAdminHeader('Session Management', 'Authentication');
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
                        <i class="fas fa-laptop fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Active Sessions</h3>
                        <small>Manage your active login sessions across devices</small>
                    </div>
                    <div class="col-auto">
                        <!-- FIXED: Back button with shadow per guidelines -->
                        <a href="dashboard.php" class="btn btn-light btn-sm shadow">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FIXED: Content Sub-Container per Website Guidelines -->
    <div class="container sub-container">

        <?php if (empty($sessions)): ?>
            <!-- FIXED: Card with shadow per guidelines -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        No active sessions found in the database.
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- FIXED: Card with shadow per guidelines -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="mb-3">
                        <form method="POST" style="display: inline;"
                            onsubmit="return confirm('Terminate all other sessions? You will need to login again on other devices.')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="terminate_all">
                            <!-- FIXED: Button with shadow per guidelines -->
                            <button type="submit" class="btn btn-warning shadow">
                                <i class="fas fa-power-off me-2"></i>Terminate All Other Sessions
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Device/Browser</th>
                                    <th>IP Address</th>
                                    <th>Started</th>
                                    <th>Last Activity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <?php
                                    $is_current = $session['session_token'] === session_id();
                                    $user_agent = $session['user_agent'];
                                    $device_info = 'Unknown Device';

                                    if (strpos($user_agent, 'Mobile') !== false) {
                                        $device_info = '<i class="fas fa-mobile-alt"></i> Mobile Device';
                                    } elseif (strpos($user_agent, 'Tablet') !== false) {
                                        $device_info = '<i class="fas fa-tablet-alt"></i> Tablet';
                                    } else {
                                        $device_info = '<i class="fas fa-desktop"></i> Desktop/Laptop';
                                    }
                                    ?>
                                    <tr class="<?= $is_current ? 'table-success' : '' ?>">
                                        <td>
                                            <?= $device_info ?>
                                            <?php if ($is_current): ?>
                                                <span class="badge bg-success ms-2">Current Session</span>
                                            <?php endif; ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($user_agent, 0, 80)) ?>...</small>
                                        </td>
                                        <td><?= htmlspecialchars($session['ip_address']) ?></td>
                                        <td>
                                            <?= date('M j, Y g:i A', strtotime($session['created_at'])) ?>
                                            <br><small class="text-muted"><?= time_ago($session['created_at']) ?></small>
                                        </td>
                                        <td>
                                            <?= date('M j, Y g:i A', strtotime($session['updated_at'])) ?>
                                            <br><small class="text-muted"><?= time_ago($session['updated_at']) ?></small>
                                        </td>
                                        <td>
                                            <?php if (strtotime($session['expires_at']) > time()): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Expired</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_current): ?>
                                                <form method="POST" style="display: inline;"
                                                    onsubmit="return confirm('Terminate this session?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="action" value="terminate_session">
                                                    <input type="hidden" name="session_id" value="<?= htmlspecialchars($session['session_token']) ?>">
                                                    <!-- FIXED: Button with shadow per guidelines -->
                                                    <button type="submit" class="btn btn-outline-danger btn-sm shadow">
                                                        <i class="fas fa-times"></i> Terminate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Current Session</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
    </div>

    <script>
        // FIXED: Display toast messages per guidelines
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            $toast_messages = getToastMessages();
            if (!empty($toast_messages)) {
                foreach ($toast_messages as $message) {
                    echo "showToast('" . $message['type'] . "', '" .
                        addslashes($message['title']) . "', '" .
                        addslashes($message['message']) . "', '" .
                        ($message['theme'] ?? 'club-logo') . "');\n";
                }
            }
            ?>
        });
    </script>

    <?php include __DIR__ . '/../include/footer.php'; ?>
</body>

</html>