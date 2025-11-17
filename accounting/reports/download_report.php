<?php

/**
 * Download Report
 * File: /accounting/reports/download_report.php
 * Purpose: Download generated report files
 * SECURITY: Requires accounting permissions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files per Website Guidelines
require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

// Check authentication using consolidated functions
if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access reports.', 'fas fa-chart-line');
    header('Location: ../../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check application permissions
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to download reports.', 'fas fa-chart-line');
    header('Location: ../../authentication/dashboard.php');
    exit();
}

// Get report ID from URL
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$report_id) {
    setToastMessage('danger', 'Invalid Report', 'Report ID is required.', 'fas fa-exclamation-triangle');
    header('Location: reports_dashboard.php');
    exit();
}

// Fetch report details
$stmt = $conn->prepare("SELECT * FROM acc_reports WHERE id = ?");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    setToastMessage('danger', 'Report Not Found', 'The requested report could not be found.', 'fas fa-exclamation-triangle');
    header('Location: reports_dashboard.php');
    exit();
}

// Check if file exists
if (!$report['file_path'] || !file_exists($report['file_path'])) {
    setToastMessage('danger', 'File Not Found', 'The report file is not available for download.', 'fas fa-exclamation-triangle');
    header('Location: view_report.php?id=' . $report_id);
    exit();
}

// Log download
logActivity($user_id, 'report_downloaded', 'auth_activity_log', $report_id, "Downloaded report: {$report['report_type']}");

// Security check - ensure file is in allowed directory
$allowed_paths = [
    realpath(__DIR__ . '/../../reports/'),
    realpath(__DIR__ . '/../../exports/'),
    realpath(__DIR__ . '/../../temp/')
];

$file_path = realpath($report['file_path']);
$is_allowed = false;

foreach ($allowed_paths as $allowed_path) {
    if ($allowed_path && strpos($file_path, $allowed_path) === 0) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    setToastMessage('danger', 'Security Error', 'File access denied for security reasons.', 'fas fa-shield-alt');
    header('Location: view_report.php?id=' . $report_id);
    exit();
}

// Get file info
$file_size = filesize($file_path);
$file_name = basename($file_path);
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Set appropriate content type
$content_types = [
    'pdf' => 'application/pdf',
    'csv' => 'text/csv',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'txt' => 'text/plain',
    'html' => 'text/html'
];

$content_type = isset($content_types[$file_extension]) ? $content_types[$file_extension] : 'application/octet-stream';

// Generate download filename
$download_filename = sanitize_filename($report['report_type'] . '_' . date('Y-m-d', strtotime($report['generated_at'])) . '.' . $file_extension);

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for file download
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $download_filename . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Read and output file
$handle = fopen($file_path, 'rb');
if ($handle) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
} else {
    // If file can't be opened, redirect with error
    setToastMessage('danger', 'Download Error', 'Could not open the report file for download.', 'fas fa-exclamation-triangle');
    header('Location: view_report.php?id=' . $report_id);
    exit();
}

exit();

/**
 * Sanitize filename for download
 */
function sanitize_filename($filename)
{
    // Remove or replace unsafe characters
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    // Remove multiple consecutive underscores
    $filename = preg_replace('/_+/', '_', $filename);
    // Remove leading/trailing underscores
    $filename = trim($filename, '_');
    // Ensure filename is not empty
    if (empty($filename)) {
        $filename = 'report_' . date('Y-m-d_H-i-s');
    }
    return $filename;
}
