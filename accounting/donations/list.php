<!-- /accounting/donations/list.php -->
<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/donation_controller.php';

/**
 * Legacy Donations List Redirect
 * This endpoint is retained for backwards compatibility. All traffic is routed to the
 * modern donations workspace while preserving any query parameters used for filters.
 */

validate_session();

$legacyQuery = $_SERVER['QUERY_STRING'] ?? '';
$target = 'index.php' . ($legacyQuery ? '?' . $legacyQuery : '');
header('Location: ' . $target);
exit();
