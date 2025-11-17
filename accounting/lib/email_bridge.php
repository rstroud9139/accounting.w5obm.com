<?php

/**
 * Accounting Email Bridge
 * Purpose: Decouple accounting from site-level EmailService.
 * Prefers /emailSystem/EmailService.php when present; otherwise falls back to PHP mail with minimal capabilities.
 */

// Try to include centralized EmailService from known locations
$__email_candidates = [
    __DIR__ . '/../../emailSystem/EmailService.php',
    __DIR__ . '/../../include/EmailService.php',
];
foreach ($__email_candidates as $__c) {
    if (is_file($__c)) {
        require_once $__c;
        break;
    }
}

/**
 * Check if the centralized EmailService class is available
 */
function accounting_email_service_available(): bool
{
    return class_exists('EmailService');
}

/**
 * Send a simple email (HTML/text) with optional attachments
 * Returns ['success' => bool, 'message' => string]
 */
function accounting_email_send_simple(string $to, string $subject, string $body, bool $isHtml = true, array $attachments = []): array
{
    try {
        // Test harness capture (no outbound email) when env flag set
        if (getenv('ACCOUNTING_TEST_CAPTURE_EMAILS') === '1') {
            $outDir = getenv('ACCOUNTING_TEST_EMAIL_OUTDIR') ?: sys_get_temp_dir();
            $payload = [
                'type' => 'simple',
                'to' => $to,
                'subject' => $subject,
                'isHtml' => $isHtml,
                'attachments' => $attachments,
                'body' => $body,
                'ts' => date('c'),
            ];
            @file_put_contents($outDir . DIRECTORY_SEPARATOR . 'acc_email_' . uniqid() . '.json', json_encode($payload, JSON_PRETTY_PRINT));
            return ['success' => true, 'message' => 'captured'];
        }
        if (accounting_email_service_available()) {
            $svc = new EmailService();
            // Prefer core signature (to, subject, body, isHtml)
            return $svc->sendSimpleEmail($to, $subject, $body, $isHtml);
        }
        // Fallback via PHP mail() – minimal, no attachments
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = $isHtml ? 'Content-type: text/html; charset=UTF-8' : 'Content-type: text/plain; charset=UTF-8';
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        $ok = mail($to, $subject, $body, implode("\r\n", $headers));
        return [
            'success' => $ok,
            'message' => $ok ? 'Sent via mail() fallback' : 'mail() fallback failed'
        ];
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('accounting_email_send_simple error: ' . $e->getMessage(), 'accounting');
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send a template email if EmailService supports it; fallback to simple email with rendered params
 */
function accounting_email_send_template(string $to, string $template, array $params, string $subject): array
{
    try {
        if (getenv('ACCOUNTING_TEST_CAPTURE_EMAILS') === '1') {
            $outDir = getenv('ACCOUNTING_TEST_EMAIL_OUTDIR') ?: sys_get_temp_dir();
            $payload = [
                'type' => 'template',
                'to' => $to,
                'template' => $template,
                'params' => $params,
                'subject' => $subject,
                'ts' => date('c'),
            ];
            @file_put_contents($outDir . DIRECTORY_SEPARATOR . 'acc_email_' . uniqid() . '.json', json_encode($payload, JSON_PRETTY_PRINT));
            return ['success' => true, 'message' => 'captured'];
        }
        if (accounting_email_service_available()) {
            $svc = new EmailService();
            if (method_exists($svc, 'sendTemplateEmail')) {
                return $svc->sendTemplateEmail($to, $template, $params, $subject);
            }
        }
        // Fallback: basic rendering from params
        $body = '<h3>' . htmlspecialchars($subject) . '</h3><pre style="font-family:inherit;white-space:pre-wrap">' . htmlspecialchars(json_encode($params, JSON_PRETTY_PRINT)) . '</pre>';
        return accounting_email_send_simple($to, $subject, $body, true);
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('accounting_email_send_template error: ' . $e->getMessage(), 'accounting');
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
