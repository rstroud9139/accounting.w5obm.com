<?php

/**
 * Email Report
 * File: /accounting/reports/email_report.php
 * Purpose: Email generated reports to users
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
    setToastMessage('danger', 'Access Denied', 'You do not have permission to email reports.', 'fas fa-chart-line');
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

// Get current user info
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM auth_users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_email = filter_var($_POST['recipient_email'], FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);
    $include_attachment = isset($_POST['include_attachment']);

    // Validate email
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        setToastMessage('danger', 'Invalid Email', 'Please enter a valid email address.', 'fas fa-exclamation-triangle');
    } else {
        // Send email (implementation depends on your email system)
        $email_sent = sendReportEmail($recipient_email, $subject, $message, $report, $include_attachment);

        if ($email_sent) {
            // Log the action
            logActivity($user_id, 'report_emailed', 'auth_activity_log', $report_id, "Emailed report to: {$recipient_email}");

            setToastMessage('success', 'Email Sent', 'Report has been emailed successfully.', 'fas fa-envelope');
            header('Location: view_report.php?id=' . $report_id);
            exit();
        } else {
            setToastMessage('danger', 'Email Failed', 'Failed to send the email. Please try again.', 'fas fa-exclamation-triangle');
        }
    }
}

$page_title = "Email Report - W5OBM";
include __DIR__ . '/../../include/header.php';
?>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <div class="container mt-4">
        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-envelope fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Email Report</h3>
                        <small>Send <?php echo ucfirst($report['report_type']); ?> Report</small>
                    </div>
                    <div class="col-auto">
                        <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn btn-light btn-sm shadow">
                            <i class="fas fa-arrow-left me-1"></i>Back to Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Report Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Report Type:</strong> <?php echo ucfirst($report['report_type']); ?></p>
                        <p><strong>Generated:</strong> <?php echo date('M d, Y g:i A', strtotime($report['generated_at'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Parameters:</strong> <?php echo htmlspecialchars($report['parameters']); ?></p>
                        <p><strong>File Available:</strong>
                            <?php if ($report['file_path'] && file_exists($report['file_path'])): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-danger">No</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Form -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-paper-plane me-2"></i>Send Email
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recipient_email" class="form-label">Recipient Email *</label>
                                <input type="email" class="form-control shadow-sm" id="recipient_email" name="recipient_email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <input type="text" class="form-control shadow-sm" id="subject" name="subject"
                                    value="W5OBM <?php echo ucfirst($report['report_type']); ?> Report" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control shadow-sm" id="message" name="message" rows="6">Hello,

Please find the attached <?php echo ucfirst($report['report_type']); ?> report generated on <?php echo date('M d, Y', strtotime($report['generated_at'])); ?>.

Report Parameters: <?php echo htmlspecialchars($report['parameters']); ?>

Best regards,
<?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>
W5OBM Amateur Radio Club</textarea>
                    </div>

                    <?php if ($report['file_path'] && file_exists($report['file_path'])): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_attachment" name="include_attachment" checked>
                                <label class="form-check-label" for="include_attachment">
                                    Include report file as attachment
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn btn-secondary shadow me-md-2">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary shadow">
                            <i class="fas fa-paper-plane me-1"></i>Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('recipient_email').value;
            const subject = document.getElementById('subject').value;

            if (!email || !subject) {
                e.preventDefault();
                showToast('danger', 'Validation Error', 'Please fill in all required fields.', 'fas fa-exclamation-triangle');
            }
        });

        // Show info toast on page load
        setTimeout(function() {
            showToast('info', 'Email Report', 'Complete the form below to send the report via email.', 'fas fa-envelope');
        }, 500);
    </script>
</body>

</html>

<?php

/**
 * Send report email function
 * This is a placeholder - implement based on your email system
 */
function sendReportEmail($recipient_email, $subject, $message, $report, $include_attachment = false)
{
    // Implementation depends on your email system
    // This is a placeholder that returns true for demonstration
    // In a real implementation, you would:
    // 1. Use PHPMailer or similar email library
    // 2. Configure SMTP settings
    // 3. Attach the report file if requested
    // 4. Send the email
    // 5. Return true on success, false on failure

    // For now, simulate success
    return true;
}
