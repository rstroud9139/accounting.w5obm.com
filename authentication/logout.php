<?php

require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/auth_utils.php';

$redirect = $_GET['redirect'] ?? '/authentication/login.php?logged_out=1';

if (performUserLogout()) {
	setToastMessage('success', 'Signed Out', 'You have been logged out safely.', 'club-logo');
} else {
	setToastMessage('warning', 'Logout Issue', 'We were unable to fully terminate the session. Please close your browser.', 'club-logo');
}

header('Location: ' . $redirect);
exit();
