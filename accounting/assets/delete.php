 <!-- /accounting/assets/delete.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/asset_controller.php';

    // Validate session
    validate_session();

    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // Handle asset deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header('Location: list.php?status=invalid_request');
            exit();
        }
        $id = $_POST['id'];

        if (delete_asset($id)) {
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
