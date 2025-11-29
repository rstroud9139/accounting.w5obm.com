<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../include/session_init.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../../include/helper_functions.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/quick_post.php';
require_once __DIR__ . '/../controllers/donation_controller.php';
require_once __DIR__ . '/../controllers/transactionController.php';

function accounting_quick_cash_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    accounting_quick_cash_response(405, [
        'ok' => false,
        'message' => 'Method not allowed.'
    ]);
}

if (!isAuthenticated()) {
    accounting_quick_cash_response(401, [
        'ok' => false,
        'message' => 'Authentication required.'
    ]);
}

try {
    csrf_verify_post_or_throw();
} catch (Exception $e) {
    accounting_quick_cash_response(422, [
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}

$userId = getCurrentUserId();
$canPost = hasPermission($userId, 'accounting_manage') || hasPermission($userId, 'accounting_add');
if (!$canPost) {
    accounting_quick_cash_response(403, [
        'ok' => false,
        'message' => 'You do not have permission to post donations.'
    ]);
}

$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
if ($amount <= 0) {
    accounting_quick_cash_response(422, [
        'ok' => false,
        'message' => 'Enter a valid amount greater than zero.'
    ]);
}

$eventLabel = trim((string)($_POST['event_label'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$rawDate = trim((string)($_POST['donation_date'] ?? date('Y-m-d')));
$dateObj = DateTime::createFromFormat('Y-m-d', $rawDate);
$donationDate = $dateObj ? $dateObj->format('Y-m-d') : date('Y-m-d');

/** @var mysqli $conn */
$context = accounting_quick_cash_resolve_context($conn);
$contactId = $context['contact_id'] ?? null;
if (!$contactId) {
    accounting_quick_cash_response(500, [
        'ok' => false,
        'message' => 'Unable to prepare quick-post contact.'
    ]);
}

$categoryId = (int)($_POST['category_id'] ?? 0);
if ($categoryId <= 0 && !empty($context['default_category_id'])) {
    $categoryId = (int)$context['default_category_id'];
}
if ($categoryId <= 0 || !accounting_quick_cash_category_exists($conn, $categoryId)) {
    accounting_quick_cash_response(422, [
        'ok' => false,
        'message' => 'Select a valid income category.'
    ]);
}

$accountId = (int)($_POST['account_id'] ?? 0);
if ($accountId <= 0 && !empty($context['default_account_id'])) {
    $accountId = (int)$context['default_account_id'];
}
if ($accountId <= 0 || !accounting_quick_cash_account_exists($conn, $accountId)) {
    accounting_quick_cash_response(422, [
        'ok' => false,
        'message' => 'Select a valid deposit account.'
    ]);
}

$description = accounting_quick_cash_description($eventLabel);

if ($conn instanceof mysqli) {
    $conn->begin_transaction();
}

try {
    $donationOk = add_donation($contactId, $amount, $donationDate, $description, true, $notes);
    if (!$donationOk) {
        throw new RuntimeException('Failed to save donation record.');
    }

    $transactionId = add_transaction($categoryId, $amount, $donationDate, $description, 'Income', $accountId);
    if (!$transactionId) {
        throw new RuntimeException('Failed to post ledger entry.');
    }

    if ($conn instanceof mysqli) {
        $conn->commit();
    }

    accounting_quick_cash_response(200, [
        'ok' => true,
        'message' => 'Cash donation recorded.',
        'description' => $description,
        'contact' => $context['contact_name'] ?? accounting_quick_cash_contact_name(),
    ]);
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        $conn->rollback();
    }
    if (function_exists('logError')) {
        logError('Quick cash post failed: ' . $e->getMessage(), 'accounting');
    }
    accounting_quick_cash_response(500, [
        'ok' => false,
        'message' => 'Unable to post cash donation. Please try again.'
    ]);
}
