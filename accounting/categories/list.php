<?php
// /accounting/categories/list.php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/categoryController.php';

// Validate session
validate_session();

// Get status message if any
$status = $_GET['status'] ?? null;

// Fetch all categories
$categories = fetch_all_categories();
?>
<?php
/**
 * Legacy Category List Redirect
 * File retained for backwards compatibility; all requests are routed to the
 * modernized categories workspace with inline modal CRUD.
 */

$legacyQuery = $_SERVER['QUERY_STRING'] ?? '';
$target = 'index.php' . ($legacyQuery ? '?' . $legacyQuery : '');
header('Location: ' . $target);
exit();
