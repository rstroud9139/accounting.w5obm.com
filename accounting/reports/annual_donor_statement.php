<?php
// /accounting/reports/annual_donor_statement.php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once __DIR__ . '/../../include/dbconn.php';
    require_once __DIR__ . '/../lib/helpers.php';
    require_once __DIR__ . '/../controllers/donation_controller.php';
    require_once __DIR__ . '/../../include/premium_hero.php';

    // Validate session
    validate_session();

    // Get parameters
    $year = $_GET['year'] ?? date('Y');
    $generate_all = isset($_GET['generate_all']) && $_GET['generate_all'] == 1;

    $generated_count = 0;
    $email_count = 0;

    // Get all donors with donations in the selected year
    $query = "SELECT DISTINCT c.id, c.name, c.email
          FROM acc_donations d
          JOIN acc_contacts c ON d.contact_id = c.id
          WHERE YEAR(d.donation_date) = ?
          ORDER BY c.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $donors = [];
    while ($row = $result->fetch_assoc()) {
        $donors[] = $row;
    }

    // Handle form submission for generating statements
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $generate_all) {
        $donor_ids = $_POST['donor_ids'] ?? [];

        if ($generate_all) {
            $donor_ids = array_column($donors, 'id');
        }

        $generated_count = 0;
        $email_count = 0;

        foreach ($donor_ids as $donor_id) {
            $file_path = generate_yearly_donor_statement($donor_id, $year);
            if ($file_path) {
                $generated_count++;

                // Check if email should be sent
                if (isset($_POST['send_email']) && $_POST['send_email'] == 1) {
                    foreach ($donors as $donor) {
                        if ($donor['id'] == $donor_id && !empty($donor['email'])) {
                            $subject = "$year Annual Donation Statement";
                            $body = "Dear {$donor['name']},\n\n";
                            $body .= "Thank you for your generous support during $year. ";
                            $body .= "Please find attached your annual donation statement for tax purposes.\n\n";
                            $body .= "We appreciate your continued support!\n\n";
                            $body .= "Sincerely,\nThe Amateur Radio Club";

                            if (send_email($donor['email'], $subject, $body, $file_path)) {
                                $email_count++;
                            }
                        }
                    }
                }
            }
        }

        if ($generated_count > 0) {
            $success_message = "$generated_count statement(s) generated successfully.";
            if ($email_count > 0) {
                $success_message .= " $email_count email(s) sent.";
            }
        } else {
            $error_message = "No statements were generated. Please try again.";
        }
    }

    $donor_count = count($donors);
    $email_ready_count = 0;
    foreach ($donors as $donor) {
        if (!empty($donor['email'])) {
            $email_ready_count++;
        }
    }

    $generation_mode = $generate_all ? 'Bulk run' : 'Interactive';
    $email_mode = isset($_POST['send_email']) && $_POST['send_email'] == 1 ? 'Emails: enabled' : 'Emails: optional';

    $annualDonorHeroChips = [
        'Year: ' . $year,
        'Mode: ' . $generation_mode,
        $email_mode,
    ];

    $annualDonorHeroHighlights = [
        [
            'label' => 'Eligible Donors',
            'value' => number_format($donor_count),
            'meta' => 'With donations in ' . $year,
        ],
        [
            'label' => 'Email Ready',
            'value' => number_format($email_ready_count),
            'meta' => 'Contacts with email',
        ],
        [
            'label' => 'Statements Today',
            'value' => number_format($generated_count),
            'meta' => $email_count > 0 ? number_format($email_count) . ' emailed' : 'Email optional',
        ],
    ];

    $annualDonorHeroActions = [
        [
            'label' => 'Generate All',
            'url' => '/accounting/reports/annual_donor_statement.php?year=' . urlencode($year) . '&generate_all=1',
            'variant' => 'outline',
            'icon' => 'fa-copy',
        ],
        [
            'label' => 'Donations Workspace',
            'url' => '/accounting/donations/',
            'variant' => 'outline',
            'icon' => 'fa-hand-holding-heart',
        ],
        [
            'label' => 'Reporting Dashboard',
            'url' => '/accounting/reports/reports_dashboard.php',
            'variant' => 'outline',
            'icon' => 'fa-chart-line',
        ],
    ];

    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Annual Donor Statements</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <?php
     if (function_exists('displayToastMessage')) {
         displayToastMessage();
     }
     ?>

     <div class="container mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader(
             'Annual Donor Statements',
             'Generate year-end statements for tax purposes',
             ['Year' => $year],
             [
                 'eyebrow' => 'Donations Reporting',
                 'chips' => $annualDonorHeroChips,
                 'highlights' => $annualDonorHeroHighlights,
                 'actions' => $annualDonorHeroActions,
                 'theme' => 'berry',
                 'size' => 'compact',
                 'media_mode' => 'none',
             ]
         ); ?>

         <?php if (isset($success_message)): ?>
             <div class="alert alert-success"><?php echo $success_message; ?></div>
         <?php endif; ?>
         <?php if (isset($error_message)): ?>
             <div class="alert alert-danger"><?php echo $error_message; ?></div>
         <?php endif; ?>

         <!-- Year Selection -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Select Year</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="annual_donor_statement.php" method="GET" class="row">
                     <div class="col-md-6 mb-3">
                         <label for="year" class="form-label">Year</label>
                         <select id="year" name="year" class="form-control">
                             <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                 <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                     <?php echo $y; ?>
                                 </option>
                             <?php endfor; ?>
                         </select>
                     </div>
                     <div class="col-md-6 mb-3 d-flex align-items-end">
                         <button type="submit" class="btn btn-primary">Show Donors</button>
                         <a href="annual_donor_statement.php?year=<?php echo $year; ?>&generate_all=1" class="btn btn-success ms-2">Generate All Statements</a>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Donor List -->
         <?php if (!empty($donors)): ?>
             <div class="card shadow">
                 <div class="card-header">
                     <h3>Donors for <?php echo $year; ?></h3>
                 </div>
                 <div class="card-body">
                     <form action="annual_donor_statement.php?year=<?php echo $year; ?>" method="POST">
                         <div class="mb-3">
                             <div class="form-check">
                                 <input class="form-check-input" type="checkbox" id="send_email" name="send_email" value="1">
                                 <label class="form-check-label" for="send_email">
                                     Send statements by email (when email address is available)
                                 </label>
                             </div>
                         </div>

                         <table class="table table-striped">
                             <thead>
                                 <tr>
                                     <th>
                                         <input type="checkbox" id="select_all" class="form-check-input">
                                     </th>
                                     <th>Donor Name</th>
                                     <th>Email</th>
                                     <th>Total Donations</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($donors as $donor):
                                        // Get donor's total donations for the year
                                        $query = "SELECT SUM(amount) as total FROM acc_donations 
                                              WHERE contact_id = ? AND YEAR(donation_date) = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param('ii', $donor['id'], $year);
                                        $stmt->execute();
                                        $total_donation = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
                                    ?>
                                     <tr>
                                         <td>
                                             <input type="checkbox" name="donor_ids[]" value="<?php echo $donor['id']; ?>" class="form-check-input donor-checkbox">
                                         </td>
                                         <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                         <td><?php echo htmlspecialchars($donor['email'] ?? 'Not available'); ?></td>
                                         <td>$<?php echo number_format($total_donation, 2); ?></td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>

                         <button type="submit" class="btn btn-primary">Generate Selected Statements</button>
                     </form>
                 </div>
             </div>
         <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !$generate_all): ?>
             <div class="alert alert-info">No donors found for <?php echo $year; ?>.</div>
         <?php endif; ?>
     </div>

     <?php include '../../include/footer.php'; ?>

     <script>
         document.getElementById('select_all').addEventListener('change', function() {
             document.querySelectorAll('.donor-checkbox').forEach(function(checkbox) {
                 checkbox.checked = document.getElementById('select_all').checked;
             });
         });
     </script>
 </body>

 </html>