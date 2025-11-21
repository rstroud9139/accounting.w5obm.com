<?php
// Minimal, robust entry point for accounting.w5obm.com root.
// Always redirect into the accounting app index; that script
// handles authentication and return_url logic.

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'accounting.w5obm.com';
$uri    = '/accounting/index.php';
$qs     = $_SERVER['QUERY_STRING'] ?? '';

$target = $scheme . '://' . $host . $uri . ($qs !== '' ? ('?' . $qs) : '');

if (!headers_sent()) {
	header('Location: ' . $target);
} else {
	echo "<meta http-equiv=\"refresh\" content=\"0;url=" . htmlspecialchars($target, ENT_QUOTES) . "\">";
	echo "<script>window.location.href='" . htmlspecialchars($target, ENT_QUOTES) . "';</script>";
}
exit;
