 <!-- /accounting/donations/delete.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once __DIR__ . '/../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/donation_controller.php';
    require_once __DIR__ . '/../utils/csrf.php';

    // Validate session
    validate_session();

    // Handle donation deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        try {
            csrf_verify_post_or_throw();
        } catch (Exception $e) {
            header('Location: list.php?status=csrf_error');
            exit();
        }

        $id = (int)$_POST['id'];

        if ($id <= 0) {
            header('Location: list.php?status=invalid_id');
            exit();
        }

        // Optional: permission check (reuse accounting permissions if available)
        if (function_exists('hasPermission') && function_exists('getCurrentUserId')) {
            $uid = getCurrentUserId();
            if (!isAdmin($uid) && !hasPermission($uid, 'accounting_manage') && !hasPermission($uid, 'donations_manage')) {
                header('Location: list.php?status=denied');
                exit();
            }
        }

        if (delete_donation($id)) {
            header('Location: list.php?status=deleted');
            exit();
        } else {
            header('Location: list.php?status=error');
            exit();
        }
    } else {
        header('Location: list.php?status=invalid_request');
        exit();
    }
