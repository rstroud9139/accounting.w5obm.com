<?php
// /accounting/reports/donor_list.php
     require_once __DIR__ . '/../utils/session_manager.php';
     require_once __DIR__ . '/../../include/dbconn.php';
     require_once __DIR__ . '/../controllers/donation_controller.php';

    // Validate session
    validate_session();

    // Fetch all donors with donation summary
    $query = "SELECT 
            c.id,
            c.name,
            c.email,
            c.phone,
            c.address,
            COUNT(d.id) AS donation_count,
            SUM(d.amount) AS total_donated,
            MAX(d.donation_date) AS latest_donation
          FROM acc_contacts c
          LEFT JOIN acc_donations d ON c.id = d.contact_id
          GROUP BY c.id, c.name, c.email, c.phone, c.address
          ORDER BY total_donated DESC, name ASC";
    $result = $conn->query($query);
    $donors = [];
    while ($row = $result->fetch_assoc()) {
        $donors[] = $row;
    }
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Donor List</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <div class="container mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader('Donor List', 'All donors with donation history'); ?>

         <!-- Donors Table -->
         <div class="card shadow">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Donors</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                     <a href="../donations/add.php" class="btn btn-success no-print">Add New Donation</a>
                     <a href="download.php?type=donor_list" class="btn btn-primary no-print">Download PDF</a>
                 </div>
             </div>
             <div class="card-body">
                 <table class="table table-striped" id="donorTable">
                     <thead>
                         <tr>
                             <th>Donor Name</th>
                             <th>Contact Information</th>
                             <th>Total Donated</th>
                             <th>Donation Count</th>
                             <th>Latest Donation</th>
                             <th>Actions</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($donors as $donor): ?>
                             <tr>
                                 <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                 <td>
                                     <?php if (!empty($donor['email'])): ?>
                                         <strong>Email:</strong> <?php echo htmlspecialchars($donor['email']); ?><br>
                                     <?php endif; ?>
                                     <?php if (!empty($donor['phone'])): ?>
                                         <strong>Phone:</strong> <?php echo htmlspecialchars($donor['phone']); ?><br>
                                     <?php endif; ?>
                                     <?php if (!empty($donor['address'])): ?>
                                         <strong>Address:</strong> <?php echo htmlspecialchars($donor['address']); ?>
                                     <?php endif; ?>
                                 </td>
                                 <td>$<?php echo number_format($donor['total_donated'] ?? 0, 2); ?></td>
                                 <td><?php echo $donor['donation_count'] ?? 0; ?></td>
                                 <td>
                                     <?php echo !empty($donor['latest_donation']) ? date('m/d/Y', strtotime($donor['latest_donation'])) : 'N/A'; ?>
                                 </td>
                                 <td>
                                     <a href="../donations/list.php?contact_id=<?php echo $donor['id']; ?>" class="btn btn-info btn-sm">View Donations</a>
                                     <a href="annual_donor_statement.php?donor_id=<?php echo $donor['id']; ?>&year=<?php echo date('Y'); ?>" class="btn btn-warning btn-sm">Generate Statement</a>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
     <script>
         $(document).ready(function() {
             $('#donorTable').DataTable({
                 order: [
                     [2, 'desc']
                 ], // Sort by total donated descending
                 pageLength: 25
             });
         });
     </script>
 </body>

 </html>