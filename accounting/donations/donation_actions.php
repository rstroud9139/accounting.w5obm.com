<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/donation_controller.php';
require_once __DIR__ . '/../utils/email_utils.php';
require_once __DIR__ . '/../lib/email_bridge.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounting/donations/');
    exit();
}

if (!isAuthenticated()) {
    setToastMessage('danger', 'Session Expired', 'Please sign in and try again.', 'club-logo');
    header('Location: /authentication/login.php');
    exit();
}

if (!isset($_SESSION['csrf_token']) || ($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
    setToastMessage('danger', 'Security Check Failed', 'Please refresh the page and submit again.', 'club-logo');
    header('Location: /accounting/donations/');
    exit();
}

$action = $_POST['action'] ?? '';
$userId = getCurrentUserId();
$canView = hasPermission($userId, 'accounting_view') || hasPermission($userId, 'accounting_manage');
$canAdd = hasPermission($userId, 'accounting_add') || hasPermission($userId, 'accounting_manage');
$canManage = hasPermission($userId, 'accounting_manage');

if (!$canView) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to manage donations.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

try {
    switch ($action) {
        case 'create':
            if (!$canAdd) {
                throw new Exception('You do not have permission to record donations.');
            }

            $donationData = [
                'contact_id' => sanitizeInput($_POST['contact_id'] ?? 0, 'int'),
                'amount' => sanitizeInput($_POST['amount'] ?? 0, 'double'),
                'donation_date' => sanitizeInput($_POST['donation_date'] ?? date('Y-m-d'), 'string'),
                'description' => sanitizeInput($_POST['description'] ?? '', 'string'),
                'tax_deductible' => !empty($_POST['tax_deductible']),
                'notes' => sanitizeInput($_POST['notes'] ?? '', 'string'),
            ];

            if ($donationData['contact_id'] <= 0) {
                throw new Exception('Please choose a donor.');
            }

            if (empty($donationData['donation_date'])) {
                $donationData['donation_date'] = date('Y-m-d');
            }

            if ($donationData['amount'] <= 0) {
                throw new Exception('Please enter a valid donation amount.');
            }

            if (!add_donation(
                $donationData['contact_id'],
                $donationData['amount'],
                $donationData['donation_date'],
                $donationData['description'],
                $donationData['tax_deductible'],
                $donationData['notes']
            )) {
                throw new Exception('Unable to save the donation.');
            }

            $newId = $GLOBALS['conn']->insert_id ?? null;
            $shouldSendReceipt = !empty($_POST['send_receipt']);
            $toastSet = false;

            if ($newId && $shouldSendReceipt) {
                $donation = fetch_donation_by_id($newId);
                if ($donation && !empty($donation['contact_email'])) {
                    list($subject, $htmlBody, $textBody) = compose_donation_receipt_email($donation);
                    $result = accounting_email_send_simple($donation['contact_email'], $subject, $htmlBody, true);
                    if (!empty($result['success'])) {
                        mark_receipt_sent($newId);
                        setToastMessage('success', 'Donation Recorded', 'Donation saved and receipt emailed.', 'club-logo');
                    } else {
                        setToastMessage('warning', 'Donation Recorded', 'Donation saved but the receipt email failed.', 'club-logo');
                    }
                    $toastSet = true;
                } else {
                    setToastMessage('info', 'Donation Recorded', 'Donation saved but no email is on file for this donor.', 'club-logo');
                    $toastSet = true;
                }
            }

            if (!$toastSet) {
                setToastMessage('success', 'Donation Recorded', 'Donation saved successfully.', 'club-logo');
            }

            logActivity($userId, 'donation_create', 'acc_donations', $newId, 'Donation recorded via workspace');
            break;

        case 'update':
            if (!$canManage) {
                throw new Exception('You do not have permission to update donations.');
            }

            $donationId = sanitizeInput($_POST['id'] ?? 0, 'int');
            if ($donationId <= 0) {
                throw new Exception('Invalid donation reference.');
            }

            $donationData = [
                'contact_id' => sanitizeInput($_POST['contact_id'] ?? 0, 'int'),
                'amount' => sanitizeInput($_POST['amount'] ?? 0, 'double'),
                'donation_date' => sanitizeInput($_POST['donation_date'] ?? date('Y-m-d'), 'string'),
                'description' => sanitizeInput($_POST['description'] ?? '', 'string'),
                'tax_deductible' => !empty($_POST['tax_deductible']),
                'notes' => sanitizeInput($_POST['notes'] ?? '', 'string'),
            ];

            if ($donationData['contact_id'] <= 0) {
                throw new Exception('Please choose a donor.');
            }

            if ($donationData['amount'] <= 0) {
                throw new Exception('Please enter a valid donation amount.');
            }

            if (!update_donation(
                $donationId,
                $donationData['contact_id'],
                $donationData['amount'],
                $donationData['donation_date'],
                $donationData['description'],
                $donationData['tax_deductible'],
                $donationData['notes']
            )) {
                throw new Exception('Unable to update the donation record.');
            }

            $markSent = isset($_POST['receipt_sent']);
            set_donation_receipt_status($donationId, $markSent);

            $receiptRequested = !empty($_POST['send_receipt']);
            $sentReceipt = false;
            if ($receiptRequested) {
                $pdfPath = generate_donation_receipt($donationId);
                if ($pdfPath && function_exists('send_donation_receipt_email_html')) {
                    $sentReceipt = send_donation_receipt_email_html($donationId, $pdfPath);
                }
            }

            if ($receiptRequested) {
                if ($sentReceipt) {
                    setToastMessage('success', 'Donation Updated', 'Changes saved and receipt emailed.', 'club-logo');
                } else {
                    setToastMessage('warning', 'Donation Updated', 'Changes saved but the receipt email could not be sent.', 'club-logo');
                }
            } else {
                setToastMessage('success', 'Donation Updated', 'Changes saved successfully.', 'club-logo');
            }

            logActivity($userId, 'donation_update', 'acc_donations', $donationId, 'Donation updated via workspace');
            break;

        case 'delete':
            if (!$canManage) {
                throw new Exception('You do not have permission to delete donations.');
            }

            $donationId = sanitizeInput($_POST['id'] ?? 0, 'int');
            if ($donationId <= 0) {
                throw new Exception('Invalid donation reference.');
            }

            if (!delete_donation($donationId)) {
                throw new Exception('Failed to delete donation.');
            }

            setToastMessage('success', 'Donation Deleted', 'The donation has been removed.', 'club-logo');
            logActivity($userId, 'donation_delete', 'acc_donations', $donationId, 'Donation deleted via workspace');
            break;

        default:
            throw new Exception('Unknown action requested.');
    }
} catch (Exception $e) {
    setToastMessage('danger', 'Donations', $e->getMessage(), 'club-logo');
}

header('Location: /accounting/donations/');
exit();
