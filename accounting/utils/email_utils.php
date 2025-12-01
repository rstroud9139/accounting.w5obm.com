<?php
// /accounting/utils/email_utils.php

require_once __DIR__ . '/../../include/dbconn.php';
/**
 * Email Utilities
 * Functions for sending emails
 */

/**
 * Send an email with an optional attachment.
 */
function send_email($to, $subject, $body, $attachment = null, $isHtml = false, $altBody = null)
{
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // If not, try to load it
        $phpmailer_path = __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
        if (file_exists($phpmailer_path)) {
            require_once $phpmailer_path;
            require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';
            require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';
        } else {
            // Use basic mail function if PHPMailer is not available
            $headers = "From: no-reply@example.com\r\n";
            $headers .= "Reply-To: no-reply@example.com\r\n";
            if ($isHtml) {
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }

            return mail($to, $subject, $body, $headers);
        }
    }

    // Use PHPMailer if available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();

            // Get email settings from configuration
            $config = get_email_config();

            $mail->Host = $config['host'] ?? 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'] ?? 'username@example.com';
            $mail->Password = $config['password'] ?? 'password';
            $mail->SMTPSecure = $config['secure'] ?? 'tls';
            $mail->Port = $config['port'] ?? 587;

            $mail->setFrom($config['from_email'] ?? 'no-reply@example.com', $config['from_name'] ?? 'Accounting System');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            if ($isHtml || stripos($body, '<html') !== false || stripos($body, '<body') !== false) {
                $mail->isHTML(true);
                $mail->Body = $body;
                if ($altBody) {
                    $mail->AltBody = $altBody;
                }
            } else {
                $mail->isHTML(false);
                $mail->Body = $body;
            }

            if ($attachment && file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }

            return $mail->send();
        } catch (Exception $e) {
            log_error('Email error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    return false;
}

/**
 * Build an absolute base URL (scheme + host + base path) for embedding images/links in emails.
 */
function get_absolute_base_url()
{
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    } elseif (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $base = defined('BASE_URL') ? BASE_URL : '/';
    if ($base === '' || $base[0] !== '/') {
        $base = '/' . $base;
    }
    // Ensure trailing slash
    if (substr($base, -1) !== '/') {
        $base .= '/';
    }
    return $scheme . '://' . $host . $base;
}

/**
 * Compose the HTML body for a donation receipt email using letterhead and IRS language.
 * Returns [subject, htmlBody, textBody].
 */
function compose_donation_receipt_email($donation)
{
    // Organization details from settings with sane defaults
    $org_name    = get_setting('organization_name', 'W5OBM Amateur Radio Club');
    $org_email   = get_setting('organization_email', 'contact@w5obm.com');
    $org_address = get_setting('organization_address', 'Olive Branch, MS');
    $org_tax_id  = get_setting('organization_tax_id', 'XX-XXXXXXX');
    $org_website = get_setting('organization_website', 'https://dev.w5obm.com/');

    $baseUrl = get_absolute_base_url();
    // Prefer accounting-local logo if present and publicly accessible; fallback to site-level
    $accLogoPath = __DIR__ . '/../images/badges/club_logo.png';
    if (is_file($accLogoPath)) {
        $logoUrl = $baseUrl . 'accounting/images/badges/club_logo.png';
    } else {
        $logoUrl = $baseUrl . 'images/badges/club_logo.png';
    }

    $amount = (float)($donation['amount'] ?? 0);
    $date   = !empty($donation['donation_date']) ? date('F j, Y', strtotime($donation['donation_date'])) : date('F j, Y');
    $desc   = trim((string)($donation['description'] ?? ''));
    $notes  = trim((string)($donation['notes'] ?? ''));
    $taxDed = isset($donation['tax_deductible']) ? (int)$donation['tax_deductible'] === 1 : true;

    $donorName  = $donation['contact_name'] ?? ($donation['donor_name'] ?? 'Donor');
    $receiptNo  = $donation['id'] ?? ($donation['donation_id'] ?? '');

    $threshold500 = $amount >= 500.0;

    // Compliance/Disclosure text
    $noGoods = $taxDed ? 'No goods or services were provided in exchange for this contribution.' : 'Goods or services may have been provided in exchange for this contribution.';
    $orgStatus = "$org_name is a 501(c)(3) nonprofit organization. EIN: $org_tax_id.";
    // Tune to $500: only include substantiation note at $500+ per spec
    $irsLine  = $threshold500 ? 'This contemporaneous written acknowledgment satisfies IRS substantiation requirements for contributions of $500 or more.' : '';

    $nonCashLine = '';
    // Heuristic to flag non-cash or quid pro quo based on keywords
    $combined = strtolower($desc . ' ' . $notes);
    if (strpos($combined, 'non-cash') !== false || strpos($combined, 'in-kind') !== false || strpos($combined, 'equipment') !== false) {
        $nonCashLine = 'Non‑cash contribution description: ' . htmlspecialchars($desc !== '' ? $desc : $notes);
    }
    $form8283Line = '';
    if ($threshold500 && $nonCashLine !== '') {
        $form8283Line = 'Note: For non‑cash contributions with a total value over $500, you may be required to file IRS Form 8283. Contributions over $5,000 may require a qualified appraisal.';
    }

    $subject = "Donation Receipt from $org_name";

    // Body headline varies slightly by threshold for tone only
    $headline = $threshold500 ? 'Official Charitable Contribution Receipt' : 'Thank you for your generous contribution';

    // Build HTML body
    ob_start();
?>
    <html>

    <body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:#f6f7f9; margin:0; padding:24px; color:#0f172a;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:720px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
            <tr>
                <td style="padding:24px 24px 0 24px; text-align:center;">
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($org_name) ?>" style="width:96px; height:96px; border-radius:12px; border:1px solid #e5e7eb;" />
                    <h1 style="margin:16px 0 4px 0; font-size:22px; line-height:1.3; color:#0f172a;"><?= htmlspecialchars($org_name) ?></h1>
                    <div style="font-size:13px; color:#475569;">
                        <div><?= htmlspecialchars($org_address) ?></div>
                        <div>501(c)(3) Nonprofit • EIN: <?= htmlspecialchars($org_tax_id) ?></div>
                        <div><a href="<?= htmlspecialchars($org_website) ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($org_website) ?></a> • <a href="mailto:<?= htmlspecialchars($org_email) ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($org_email) ?></a></div>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="padding:8px 24px 0 24px;">
                    <hr style="border:0; border-top:1px solid #e5e7eb; margin:16px 0;" />
                    <h2 style="margin:8px 0 12px 0; font-size:18px; color:#0f172a;"><?= htmlspecialchars($headline) ?></h2>
                    <p style="margin:0 0 12px 0; font-size:14px; color:#0f172a;">Dear <?= htmlspecialchars($donorName) ?>,</p>
                    <p style="margin:0 0 12px 0; font-size:14px; color:#0f172a;">Thank you for your contribution of <strong>$<?= number_format($amount, 2) ?></strong> on <strong><?= htmlspecialchars($date) ?></strong>.</p>
                    <?php if ($desc !== ''): ?>
                        <p style="margin:0 0 12px 0; font-size:14px; color:#0f172a;">Purpose/Notes: <?= htmlspecialchars($desc) ?></p>
                    <?php endif; ?>

                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:12px 0; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;">
                        <tr>
                            <td style="padding:12px 16px; font-size:13px; color:#0f172a;">
                                <div><strong>Receipt #:</strong> <?= htmlspecialchars((string)$receiptNo) ?></div>
                                <div><strong>Donation Date:</strong> <?= htmlspecialchars($date) ?></div>
                                <div><strong>Amount:</strong> $<?= number_format($amount, 2) ?></div>
                                <?php if ($donation['contact_tax_id'] ?? false): ?>
                                    <div><strong>Donor Tax ID:</strong> <?= htmlspecialchars($donation['contact_tax_id']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <p style="margin:8px 0; font-size:13px; color:#0f172a;"><?= htmlspecialchars($noGoods) ?></p>
                    <p style="margin:8px 0; font-size:13px; color:#0f172a;"><?= htmlspecialchars($orgStatus) ?></p>
                    <?php if ($nonCashLine !== ''): ?>
                        <p style="margin:8px 0; font-size:13px; color:#0f172a;"><?= $nonCashLine ?></p>
                    <?php endif; ?>
                    <?php if ($form8283Line !== ''): ?>
                        <p style="margin:8px 0; font-size:12px; color:#334155;"><?= htmlspecialchars($form8283Line) ?></p>
                    <?php endif; ?>
                    <?php if ($irsLine !== ''): ?>
                        <p style="margin:8px 0; font-size:12px; color:#334155;"><?= htmlspecialchars($irsLine) ?></p>
                    <?php endif; ?>

                    <p style="margin:12px 0 0 0; font-size:13px; color:#0f172a;">Please keep this receipt for your records. If you have questions, contact us at <a href="mailto:<?= htmlspecialchars($org_email) ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($org_email) ?></a>.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:16px 24px;">
                    <hr style="border:0; border-top:1px solid #e5e7eb; margin:0 0 12px 0;" />
                    <div style="font-size:12px; color:#64748b;">This email serves as your official receipt. <?= htmlspecialchars($org_name) ?> appreciates your support.</div>
                </td>
            </tr>
        </table>
    </body>

    </html>
<?php
    $htmlBody = ob_get_clean();

    // Text alternative
    $textLines = [];
    $textLines[] = $org_name;
    $textLines[] = $org_address;
    $textLines[] = "501(c)(3) Nonprofit • EIN: $org_tax_id";
    $textLines[] = $org_website . ' • ' . $org_email;
    $textLines[] = '';
    $textLines[] = $headline;
    $textLines[] = "Dear $donorName,";
    $textLines[] = "Thank you for your contribution of $" . number_format($amount, 2) . " on $date.";
    if ($desc !== '') {
        $textLines[] = 'Purpose/Notes: ' . $desc;
    }
    $textLines[] = 'Receipt #: ' . $receiptNo;
    $textLines[] = 'Donation Date: ' . $date;
    $textLines[] = 'Amount: $' . number_format($amount, 2);
    if (!empty($donation['contact_tax_id'])) {
        $textLines[] = 'Donor Tax ID: ' . $donation['contact_tax_id'];
    }
    $textLines[] = $noGoods;
    $textLines[] = $orgStatus;
    if ($nonCashLine !== '') {
        $textLines[] = $nonCashLine;
    }
    if ($form8283Line !== '') {
        $textLines[] = $form8283Line;
    }
    if ($irsLine !== '') {
        $textLines[] = $irsLine;
    }
    $textLines[] = '';
    $textLines[] = 'Please keep this receipt for your records.';

    $textBody = implode("\n", $textLines);

    return [$subject, $htmlBody, $textBody];
}

/**
 * Send a donation receipt email (HTML, letterhead) and update receipt status on success.
 */
function send_donation_receipt_email_html($donation_id, $pdf_path)
{
    $db = accounting_db_connection();

    // Fetch donation + contact details
    $query = "SELECT d.*, c.name as contact_name, c.email as contact_email, c.tax_id as contact_tax_id\n              FROM acc_donations d\n              JOIN acc_contacts c ON d.contact_id = c.id\n              WHERE d.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();

    if (!$donation || empty($donation['contact_email'])) {
        return false;
    }

    list($subject, $htmlBody, $textBody) = compose_donation_receipt_email($donation);

    if (send_email($donation['contact_email'], $subject, $htmlBody, $pdf_path, true, $textBody)) {
        // Update receipt sent status
        $query = "UPDATE acc_donations SET receipt_sent = 1, receipt_date = CURDATE() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param('i', $donation_id);
        $stmt->execute();
        return true;
    }
    return false;
}

/**
 * Get email configuration from settings.
 */
function get_email_config()
{
    $db = accounting_db_connection();

    $config = [
        'host' => 'smtp.example.com',
        'username' => 'username@example.com',
        'password' => 'password',
        'secure' => 'tls',
        'port' => 587,
        'from_email' => 'no-reply@example.com',
        'from_name' => 'Accounting System'
    ];

    // Check if settings table exists
    $result = $db->query("SHOW TABLES LIKE 'acc_settings'");
    if ($result->num_rows > 0) {
        // Get email settings from database
        $query = "SELECT setting_key, setting_value FROM acc_settings WHERE setting_key LIKE 'email_%'";
        $settings_result = $db->query($query);

        if ($settings_result) {
            while ($row = $settings_result->fetch_assoc()) {
                $key = str_replace('email_', '', $row['setting_key']);
                $config[$key] = $row['setting_value'];
            }
        }
    }

    return $config;
}

/**
 * Send a donation receipt email.
 */
function send_donation_receipt_email($donation_id, $pdf_path)
{
    $db = accounting_db_connection();

    // Get donation details
    $query = "SELECT d.*, c.name as donor_name, c.email as donor_email 
              FROM acc_donations d
              JOIN acc_contacts c ON d.contact_id = c.id
              WHERE d.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();

    if (!$donation || empty($donation['donor_email'])) {
        return false;
    }

    // Get organization info from settings
    $org_name = get_setting('organization_name', 'Amateur Radio Club');
    $org_email = get_setting('organization_email', 'contact@example.com');

    $subject = "Donation Receipt from $org_name";

    $body = "Dear " . $donation['donor_name'] . ",\n\n";
    $body .= "Thank you for your generous donation of $" . number_format($donation['amount'], 2) . " on " . date('F j, Y', strtotime($donation['donation_date'])) . ".\n\n";
    $body .= "Your support helps us continue our important work. ";
    $body .= "Please find attached your official receipt for tax purposes.\n\n";
    $body .= "If you have any questions, please contact us at $org_email.\n\n";
    $body .= "Sincerely,\n";
    $body .= "$org_name";

    if (send_email($donation['donor_email'], $subject, $body, $pdf_path)) {
        // Update receipt sent status
        $query = "UPDATE acc_donations SET receipt_sent = 1, receipt_date = CURDATE() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param('i', $donation_id);
        $stmt->execute();

        return true;
    }

    return false;
}

/**
 * Get a setting value.
 */
function get_setting($key, $default = null)
{
    $db = accounting_db_connection();

    // Check if settings table exists
    $result = $db->query("SHOW TABLES LIKE 'acc_settings'");
    if ($result->num_rows > 0) {
        $query = "SELECT setting_value FROM acc_settings WHERE setting_key = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['setting_value'];
        }
    }

    return $default;
}
