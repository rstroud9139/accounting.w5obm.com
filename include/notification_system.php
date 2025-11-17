<?php

/**
 * System Notifications Management
 * File: /administration/notification_system.php
 * Purpose: Create and manage system-wide notifications
 * Access: Admin only
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/dbconn.php';
require_once __DIR__ . '/helper_functions.php';

// Standard authentication check per guidelines
if (!isAuthenticated()) {
    header('Location: ../authentication/?action=login');
    exit();
}

$user_id = getCurrentUserId();

// Check admin access using existing helper function
if (!isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'Administrator privileges required.', 'club-logo');
    header('Location: /authentication/dashboard.php');
    exit();
}

// Generate CSRF token per guidelines
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Error handling per guidelines
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log access
logActivity($user_id, 'notification_system_access', 'auth_activity_log', null, 'Accessed notification system');

// Create system_notifications table if it doesn't exist
$create_table_sql = "
CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    target_user_id INT NULL,
    created_by INT NOT NULL,
    expires_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES auth_users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    INDEX idx_active (is_active),
    INDEX idx_expires (expires_at),
    INDEX idx_target (target_user_id)
)";

try {
    $conn->query($create_table_sql);
} catch (Exception $e) {
    error_log("Error creating system_notifications table: " . $e->getMessage());
}

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setToastMessage('danger', 'Security Error', 'Invalid CSRF token.', 'club-logo');
        header('Location: notification_system.php');
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_notification':
                $title = trim($_POST['title']);
                $message = trim($_POST['message']);
                $type = $_POST['type'] ?? 'info';
                $target_user_id = !empty($_POST['target_user_id']) ? intval($_POST['target_user_id']) : null;
                $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

                if (!empty($title) && !empty($message)) {
                    $query = "INSERT INTO system_notifications (title, message, type, target_user_id, expires_at, created_by, created_at, is_active) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('sssisi', $title, $message, $type, $target_user_id, $expires_at, $user_id);

                    if ($stmt->execute()) {
                        $notification_id = $conn->insert_id;
                        setToastMessage('success', 'Success', 'Notification created successfully.', 'club-logo');
                        logActivity($user_id, 'create_notification', 'system_notifications', $notification_id, "Created notification: $title");
                    } else {
                        setToastMessage('danger', 'Error', 'Error creating notification: ' . $stmt->error, 'club-logo');
                    }
                    $stmt->close();
                } else {
                    setToastMessage('danger', 'Validation Error', 'Title and message are required.', 'club-logo');
                }
                break;

            case 'toggle_notification':
                $notification_id = intval($_POST['notification_id']);
                $query = "UPDATE system_notifications SET is_active = NOT is_active WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $notification_id);

                if ($stmt->execute()) {
                    setToastMessage('success', 'Success', 'Notification status updated.', 'club-logo');
                    logActivity($user_id, 'toggle_notification', 'system_notifications', $notification_id, 'Toggled notification status');
                } else {
                    setToastMessage('danger', 'Error', 'Error updating notification.', 'club-logo');
                }
                $stmt->close();
                break;

            case 'delete_notification':
                $notification_id = intval($_POST['notification_id']);
                $query = "DELETE FROM system_notifications WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $notification_id);

                if ($stmt->execute()) {
                    setToastMessage('success', 'Success', 'Notification deleted successfully.', 'club-logo');
                    logActivity($user_id, 'delete_notification', 'system_notifications', $notification_id, 'Deleted notification');
                } else {
                    setToastMessage('danger', 'Error', 'Error deleting notification.', 'club-logo');
                }
                $stmt->close();
                break;
        }
    } catch (Exception $e) {
        setToastMessage('danger', 'Error', 'An error occurred: ' . $e->getMessage(), 'club-logo');
        error_log("Notification system error: " . $e->getMessage());
    }
}

// Get notifications with creator information
$notifications_query = "SELECT n.*, u.username as created_by_username, u.first_name, u.last_name,
                               CASE WHEN n.target_user_id IS NOT NULL THEN CONCAT(tu.first_name, ' ', tu.last_name) ELSE 'All Users' END as target_display
                        FROM system_notifications n 
                        LEFT JOIN auth_users u ON n.created_by = u.id 
                        LEFT JOIN auth_users tu ON n.target_user_id = tu.id
                        ORDER BY n.created_at DESC 
                        LIMIT 100";

$notifications = [];
try {
    $result = $conn->query($notifications_query);
    if ($result) {
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// Get users for targeting dropdown
$users_query = "SELECT id, username, first_name, last_name FROM auth_users WHERE status = 'active' ORDER BY first_name, last_name";
$users = [];
try {
    $result = $conn->query($users_query);
    if ($result) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

$page_title = "System Notifications - W5OBM";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include __DIR__ . '/../include/header.php'; ?>

    <style>
        .master-container {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 98%;
            background: white;
            border-radius: 8px;
            padding: 20px;
        }

        .sub-container {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 15px 0;
            padding: 20px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .header-card {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            text-align: center;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .notification-preview {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container position-fixed d-flex justify-content-center" style="top: 250px; left: 50%; transform: translateX(-50%); z-index: 9999; pointer-events: none;">
        <div style="pointer-events: auto;">
            <!-- Toasts will be displayed here -->
        </div>
    </div>

    <div class="container-fluid mt-4">
        <div class="master-container">

            <!-- Header Container -->
            <div class="header-card">
                <div class="row align-items-center">
                    <div class="col-md-2 col-sm-12 text-center">
                        <img src="../images/badges/club_logo.png" alt="Club Logo" class="img-card-150">
                    </div>
                    <div class="col-md-10 col-sm-12">
                        <h2><i class="fas fa-bell me-2"></i>System Notifications</h2>
                        <p class="lead">Create and manage system-wide notifications for users</p>
                        <p class="mb-0">Send targeted or broadcast messages to inform users about important updates</p>
                    </div>
                </div>
            </div>

            <!-- Create Notification Form -->
            <div class="sub-container">
                <h4><i class="fas fa-plus me-2"></i>Create New Notification</h4>
                <form method="POST" action="notification_system.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="create_notification">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Notification Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required maxlength="255">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="info">Info (Blue)</option>
                                    <option value="success">Success (Green)</option>
                                    <option value="warning">Warning (Yellow)</option>
                                    <option value="danger">Danger (Red)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_user_id" class="form-label">Target User</label>
                                <select class="form-select" id="target_user_id" name="target_user_id">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Leave blank to send to all users</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expires_at" class="form-label">Expires At</label>
                                <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                                <div class="form-text">Leave blank for no expiration</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>

                    <!-- Preview -->
                    <div class="mb-3">
                        <label class="form-label">Preview</label>
                        <div id="notification-preview" class="notification-preview alert alert-info">
                            <strong>Preview Title</strong><br>
                            Preview message will appear here...
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-bell me-2"></i>Create Notification
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Admin Dashboard
                        </a>
                    </div>
                </form>
            </div>

            <!-- Existing Notifications -->
            <div class="sub-container">
                <h4><i class="fas fa-list me-2"></i>Existing Notifications</h4>

                <?php if (empty($notifications)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No notifications have been created yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Target</th>
                                    <th>Created</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($notification['title']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($notification['message'], 0, 100)) ?><?= strlen($notification['message']) > 100 ? '...' : '' ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $notification['type'] === 'danger' ? 'danger' : ($notification['type'] === 'warning' ? 'warning' : ($notification['type'] === 'success' ? 'success' : 'info')) ?>">
                                                <?= ucfirst($notification['type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($notification['target_display']) ?></td>
                                        <td>
                                            <?= date('M j, H:i', strtotime($notification['created_at'])) ?>
                                            <br><small class="text-muted">by <?= htmlspecialchars($notification['created_by_username']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($notification['expires_at']): ?>
                                                <?= date('M j, Y H:i', strtotime($notification['expires_at'])) ?>
                                                <?php if (strtotime($notification['expires_at']) < time()): ?>
                                                    <br><small class="text-danger">Expired</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No expiration</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($notification['expires_at'] && strtotime($notification['expires_at']) < time()): ?>
                                                <span class="badge bg-secondary">Expired</span>
                                            <?php elseif ($notification['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="toggle_notification">
                                                <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Toggle Status">
                                                    <i class="fas fa-toggle-<?= $notification['is_active'] ? 'on' : 'off' ?>"></i>
                                                </button>
                                            </form>

                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="delete_notification">
                                                <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script>
        // Display any pending toast messages
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            $toastMessages = getToastMessages();
            if (!empty($toastMessages)):
                foreach ($toastMessages as $toast): ?>
                    showToast(
                        '<?= htmlspecialchars($toast['type'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['title'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['message'], ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($toast['theme'] ?? 'club-logo', ENT_QUOTES) ?>'
                    );
            <?php endforeach;
            endif; ?>
        });

        // Live preview functionality
        function updatePreview() {
            const title = document.getElementById('title').value || 'Preview Title';
            const message = document.getElementById('message').value || 'Preview message will appear here...';
            const type = document.getElementById('type').value;

            const preview = document.getElementById('notification-preview');
            preview.className = `notification-preview alert alert-${type}`;
            preview.innerHTML = `<strong>${title}</strong><br>${message}`;
        }

        // Add event listeners
        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('message').addEventListener('input', updatePreview);
        document.getElementById('type').addEventListener('change', updatePreview);
    </script>

</body>

</html>
