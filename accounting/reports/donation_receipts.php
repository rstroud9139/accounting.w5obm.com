 <!-- /accounting/reports/donation_receipts.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/donation_controller.php';

    // Validate session
    validate_session();

    // Get parameters
    $start_date = $_GET['start_date'] ?? date('Y-01-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $contact_id = $_GET['contact_id'] ?? null;
    $receipt_status = $_GET['receipt_status'] ?? 'not_sent'; // 'all', 'sent', 'not_sent'

    // Generate all receipts flag
    $generate_all = isset($_GET['generate_all']) && $_GET['generate_all'] == 1;
    $email_all = isset($_GET['email_all']) && $_GET['email_all'] == 1;

    // Apply filters to get donations
    $filters = "WHERE donation_date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    $types = 'ss';

    if ($contact_id) {
        $filters .= " AND contact_id = ?";
        $params[] = $contact_id;
        $types .= 'i';
    }

    if ($receipt_status == 'sent') {
        $filters .= " AND receipt_sent = 1";
    } elseif ($receipt_status == 'not_sent') {
        $filters .= " AND receipt_sent = 0";
    }

    $query = "SELECT d.*, c.name as contact_name, c.email as contact_email
          FROM acc_donations d
          JOIN acc_contacts c ON d.contact_id = c.id
          $filters
          ORDER BY d.donation_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $donations = [];
    while ($row = $result->fetch_assoc()) {
        $donations[] = $row;
    }

    // Process generation or emailing of receipts
    if ($generate_all || $email_all) {
        $count = 0;
        foreach ($donations as $donation) {
            if ($donation['receipt_sent'] == 0 || $generate_all) {
                $receipt_path = generate_donation_receipt($donation['id']);
                if ($receipt_path) {
                    $count++;
                    // If emailing is requested and email is available
                    if ($email_all && !empty($donation['contact_email'])) {
                        $subject = "Donation Receipt #{$donation['id']}";
                        $body = "Dear {$donation['contact_name']},\n\n";
                        $body .= "Thank you for your donation of \${$donation['amount']} on {$donation['donation_date']}.\n\n";
                        $body .= "Please find attached your donation receipt for tax purposes.\n\n";
                        $body .= "We appreciate your support!\n\n";
                        $body .= "Sincerely,\nThe Amateur Radio Club";

                        send_email($donation['contact_email'], $subject, $body, $receipt_path);
                    }
                }
            }
        }

        if ($count > 0) {
            $success_message = "$count receipt(s) " . ($email_all ? "generated and emailed" : "generated") . " successfully.";
        } else {
            $error_message = "No receipts were generated. Please try again.";
        }

        // Refresh the donation list after processing
        $stmt->execute();
        $result = $stmt->get_result();
        $donations = [];
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }
    }

    // Fetch contacts for dropdown
    $contact_query = "SELECT id, name FROM acc_contacts ORDER BY name";
    $contact_result = $conn->query($contact_query);
    $contacts = [];
    while ($row = $contact_result->fetch_assoc()) {
        $contacts[] = $row;
    }
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Donation Receipts</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <div class="container mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader('Donation Receipts', 'Generate and manage tax receipts for donations'); ?>

         <?php if (isset($success_message)): ?>
             <div class="alert alert-success"><?php echo $success_message; ?></div>
         <?php endif; ?>
         <?php if (isset($error_message)): ?>
             <div class="alert alert-danger"><?php echo $error_message; ?></div>
         <?php endif; ?>

         <!-- Filter Form -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Filter Donations</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="donation_receipts.php" method="GET" class="row">
                     <div class="col-md-3 mb-3">
                         <label for="start_date" class="form-label">Start Date</label>
                         <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                     </div>
                     <div class="col-md-3 mb-3">
                         <label for="end_date" class="form-label">End Date</label>
                         <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                     </div>
                     <div class="col-md-3 mb-3">
                         <label for="contact_id" class="form-label">Donor</label>
                         <select id="contact_id" name="contact_id" class="form-control">
                             <option value="">All Donors</option>
                             <?php foreach ($contacts as $contact): ?>
                                 <option value="<?php echo $contact['id']; ?>" <?php echo $contact_id == $contact['id'] ? 'selected' : ''; ?>>
                                     <?php echo htmlspecialchars($contact['name']); ?>
                                 </option>
                             <?php endforeach; ?>
                         </select>
                     </div>
                     <div class="col-md-3 mb-3">
                         <label for="receipt_status" class="form-label">Receipt Status</label>
                         <select id="receipt_status" name="receipt_status" class="form-control">
                             <option value="all" <?php echo $receipt_status == 'all' ? 'selected' : ''; ?>>All</option>
                             <option value="sent" <?php echo $receipt_status == 'sent' ? 'selected' : ''; ?>>Sent</option>
                             <option value="not_sent" <?php echo $receipt_status == 'not_sent' ? 'selected' : ''; ?>>Not Sent</option>
                         </select>
                     </div>
                     <div class="col-md-12 mb-3">
                         <button type="submit" class="btn btn-primary">Filter</button>
                         <a href="donation_receipts.php" class="btn btn-secondary">Reset</a>
                         <a href="donation_receipts.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&contact_id=<?php echo $contact_id; ?>&receipt_status=<?php echo $receipt_status; ?>&generate_all=1" class="btn btn-success">Generate All Receipts</a>
                         <a href="donation_receipts.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&contact_id=<?php echo $contact_id; ?>&receipt_status=<?php echo $receipt_status; ?>&email_all=1" class="btn btn-info">Email All Receipts</a>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Donations List -->
         <div class="card shadow">
             <div class="card-header">
                 <h3>Donations</h3>
             </div>
             <div class="card-body">
                 <?php if (empty($donations)): ?>
                     <div class="alert alert-info">No donations found matching the selected criteria.</div>
                 <?php else: ?>
                     <table class="table table-striped">
                         <thead>
                             <tr>
                                 <th>ID</th>
                                 <th>Donor</th>
                                 <th>Amount</th>
                                 <th>Date</th>
                                 <th>Receipt Sent</th>
                                 <th>Actions</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($donations as $donation): ?>
                                 <tr>
                                     <td><?php echo $donation['id']; ?></td>
                                     <td><?php echo htmlspecialchars($donation['contact_name']); ?></td>
                                     <td>$<?php echo number_format($donation['amount'], 2); ?></td>
                                     <td><?php echo $donation['donation_date']; ?></td>
                                     <td>
                                         <?php if ($donation['receipt_sent']): ?>
                                             <span class="badge bg-success">Yes - <?php echo $donation['receipt_date']; ?></span>
                                         <?php else: ?>
                                             <span class="badge bg-warning">No</span>
                                         <?php endif; ?>
                                     </td>
                                     <td>
                                         <a href="../donations/receipt.php?id=<?php echo $donation['id']; ?>" class="btn btn-primary btn-sm">View Receipt</a>

                                         <?php if (!empty($donation['contact_email'])): ?>
                                             <a href="../donations/receipt.php?id=<?php echo $donation['id']; ?>&email=1" class="btn btn-info btn-sm">Email Receipt</a>
                                         <?php endif; ?>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 <?php endif; ?>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>