<?php

/**
 * Quick Post Handler - Cash Donation Routine
 * File: /accounting/donations/quick_post_handler.php
 * Purpose: Backend processor for quick-post cash donations
 * SECURITY: Requires authentication and accounting_add permission
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/donation_controller.php';
require_once __DIR__ . '/../controllers/transactionController.php';
require_once __DIR__ . '/../utils/csrf.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_manage') && !hasPermission($user_id, 'accounting_add')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to post donations.', 'club-logo');
    header('Location: /accounting/dashboard.php');
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounting/dashboard.php');
    exit();
}

try {
    // Verify CSRF token
    csrf_verify_post_or_throw();

    // Validate quick post type
    $quickPostType = sanitizeInput($_POST['quick_post_type'] ?? '', 'string');
    if ($quickPostType !== 'cash_donation') {
        throw new Exception('Invalid quick post type.');
    }

    // Extract and validate form data
    $amount = sanitizeInput($_POST['amount'] ?? '', 'float');
    $donation_date = sanitizeInput($_POST['donation_date'] ?? '', 'string');
    $deposit_account_id = sanitizeInput($_POST['deposit_account_id'] ?? '', 'int');
    $description = sanitizeInput($_POST['description'] ?? 'Cash donation - Event collection', 'string');
    $notes = sanitizeInput($_POST['notes'] ?? '', 'string');
    $tax_deductible = !empty($_POST['tax_deductible']);

    // Validation
    if (!$amount || $amount <= 0) {
        throw new Exception('Please enter a valid donation amount.');
    }

    if (!$donation_date) {
        throw new Exception('Donation date is required.');
    }

    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $donation_date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $donation_date) {
        throw new Exception('Invalid date format.');
    }

    if (!$deposit_account_id) {
        throw new Exception('Deposit account is required.');
    }

    // Verify the deposit account exists and is active
    $stmt = $conn->prepare("SELECT id, name, account_type FROM acc_ledger_accounts WHERE id = ? AND active = 1");
    $stmt->bind_param('i', $deposit_account_id);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$account) {
        throw new Exception('Invalid or inactive deposit account selected.');
    }

    // Create anonymous contact entry for "Cash - Event Collections"
    // Check if anonymous contact exists
    $stmt = $conn->prepare("SELECT id FROM acc_contacts WHERE name = 'Anonymous - Cash Collections' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $anonymousContact = $result->fetch_assoc();
    $stmt->close();

    if (!$anonymousContact) {
        // Create the anonymous contact
        $stmt = $conn->prepare("
            INSERT INTO acc_contacts (name, email, notes, created_at) 
            VALUES ('Anonymous - Cash Collections', NULL, 'System-generated contact for quick-post cash donations', NOW())
        ");
        $stmt->execute();
        $contact_id = $conn->insert_id;
        $stmt->close();
    } else {
        $contact_id = $anonymousContact['id'];
    }

    // Record the donation
    $donationOk = add_donation(
        $contact_id,
        $amount,
        $donation_date,
        $description,
        $tax_deductible,
        $notes
    );

    if (!$donationOk) {
        throw new Exception('Failed to record donation. Please try again.');
    }

    $donation_id = $conn->insert_id;

    // Create corresponding transaction to credit the deposit account
    // Determine income category for donations
    $stmt = $conn->prepare("
        SELECT id FROM acc_transaction_categories 
        WHERE type = 'Income' 
          AND (name LIKE '%Donation%' OR name LIKE '%Contribution%' OR name LIKE '%Gift%')
        ORDER BY 
            CASE WHEN name LIKE '%Donation%' THEN 1 ELSE 2 END,
            id 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $incomeCategory = $result->fetch_assoc();
    $stmt->close();

    $category_id = $incomeCategory ? $incomeCategory['id'] : null;

    if (!$category_id) {
        // Create default donation category if none exists
        $stmt = $conn->prepare("
            INSERT INTO acc_transaction_categories (name, type, description, created_at) 
            VALUES ('Donations', 'Income', 'Charitable donations and contributions', NOW())
        ");
        $stmt->execute();
        $category_id = $conn->insert_id;
        $stmt->close();
    }

    // Post the transaction
    $transaction_description = $description . ($notes ? ' - ' . substr($notes, 0, 50) : '');
    $transactionOk = add_transaction(
        $category_id,
        $amount,
        $donation_date,
        $transaction_description,
        'Income',
        $deposit_account_id,
        null // no vendor for cash donations
    );

    if (!$transactionOk) {
        // Log warning but don't fail the donation
        logError("Quick post: donation #{$donation_id} recorded but transaction failed", 'accounting');
    }

    // Log activity
    logActivity(
        $user_id,
        'quick_post_cash_donation',
        'acc_donations',
        $donation_id,
        "Quick-posted cash donation: \${$amount} to {$account['name']}"
    );

    // Success - redirect back to dashboard with confirmation
    setToastMessage(
        'success',
        'Cash Donation Posted',
        "Successfully recorded \$" . number_format($amount, 2) . " cash donation to {$account['name']}.",
        'fa-bolt'
    );
    header('Location: /accounting/dashboard.php?quick_post=success');
    exit();

} catch (Exception $e) {
    setToastMessage('danger', 'Quick Post Failed', $e->getMessage(), 'club-logo');
    logError("Quick post cash donation error: " . $e->getMessage(), 'accounting');
    header('Location: /accounting/dashboard.php?quick_post=error');
    exit();
}
