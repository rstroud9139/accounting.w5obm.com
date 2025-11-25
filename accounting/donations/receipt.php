<?php
// /accounting/donations/receipt.php
     require_once __DIR__ . '/../utils/session_manager.php';
     require_once __DIR__ . '/../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/donation_controller.php';
    require_once __DIR__ . '/../utils/email_utils.php';

    // Validate session
    validate_session();

    // Get request parameters
    $donation_id = $_GET['id'] ?? null;
    $action = $_GET['action'] ?? 'view';
    $email = isset($_GET['email']) && $_GET['email'] == 1;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $contact_id = $_GET['contact_id'] ?? null;

    // Handle single donation receipt
    if ($donation_id) {
        $donation = fetch_donation_by_id($donation_id);

        if (!$donation) {
            header('Location: list.php?status=not_found');
            exit();
        }

        // Generate receipt PDF
        $receipt_path = generate_donation_receipt($donation_id);

        if (!$receipt_path) {
            header('Location: list.php?status=error');
            exit();
        }

        // Email the receipt if requested
        if ($email && !empty($donation['contact_email'])) {
            if (function_exists('send_donation_receipt_email_html')) {
                $ok = send_donation_receipt_email_html((int)$donation_id, $receipt_path);
            } else {
                // Fallback to simple text email if helper not available
                $subject = "Donation Receipt #{$donation_id}";
                $body = "Thank you for your donation of \${$donation['amount']} on {$donation['donation_date']}.\n\n";
                $body .= "Please find attached your donation receipt for tax purposes.\n\n";
                $body .= "We appreciate your support!";
                $ok = send_email($donation['contact_email'], $subject, $body, $receipt_path);
            }

            if ($ok) {
                header('Location: list.php?status=email_sent');
                exit();
            } else {
                header('Location: list.php?status=email_error');
                exit();
            }
        }

        // Otherwise, display the receipt
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="donation_receipt_' . $donation_id . '.pdf"');
        readfile($receipt_path);
        exit();
    }

    // Handle batch receipt generation
    if ($action == 'generate_all') {
        // Fetch all donations matching the filters
        $donations = fetch_all_donations($start_date, $end_date, $contact_id);

        if (empty($donations)) {
            header('Location: list.php?status=no_donations');
            exit();
        }

        // Generate receipts for all donations
        $generated = 0;
        foreach ($donations as $donation) {
            if (!$donation['receipt_sent']) {
                $receipt_path = generate_donation_receipt($donation['id']);
                if ($receipt_path) {
                    $generated++;
                }
            }
        }

        header('Location: list.php?status=receipts_generated&count=' . $generated);
        exit();
    }

    // Handle batch email sending
    if ($action == 'email_all') {
        // Fetch all donations matching the filters
        $donations = fetch_all_donations($start_date, $end_date, $contact_id);

        if (empty($donations)) {
            header('Location: list.php?status=no_donations');
            exit();
        }

        // Email receipts for all donations
        $sent = 0;
        foreach ($donations as $donation) {
            if (!$donation['receipt_sent'] && !empty($donation['contact_email'])) {
                $receipt_path = generate_donation_receipt($donation['id']);

                if ($receipt_path) {
                    $ok = function_exists('send_donation_receipt_email_html')
                        ? send_donation_receipt_email_html((int)$donation['id'], $receipt_path)
                        : send_email(
                            $donation['contact_email'],
                            "Donation Receipt #{$donation['id']}",
                            "Thank you for your donation of \${$donation['amount']} on {$donation['donation_date']}.\n\nPlease find attached your donation receipt for tax purposes.\n\nWe appreciate your support!",
                            $receipt_path
                        );

                    if ($ok) {
                        $sent++;
                    }
                }
            }
        }

        header('Location: list.php?status=emails_sent&count=' . $sent);
        exit();
    }

    // Default fallback
    header('Location: list.php');
    exit();
