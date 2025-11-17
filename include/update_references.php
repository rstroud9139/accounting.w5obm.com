<?php
// Update paths in your membership files from:
require_once __DIR__ . '/include/member_email_template.php';

// To:
require_once __DIR__ . '/../emailSystem/EmailService.php';
$emailService = new EmailService();
$emailService->sendFromTemplate($email, 'membership/welcome', $data);
