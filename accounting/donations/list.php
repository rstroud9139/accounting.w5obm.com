<!-- /accounting/donations/list.php -->
<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once '../../include/dbconn.php';
require_once __DIR__ . '/../controllers/donation_controller.php';

// Validate session
validate_session();

// Get status message if any
$status = $_GET['status'] ?? null;
$emailStatus = $_GET['email'] ?? null;

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-01-01');
$end_date = $_GET['end_date'] ?? date('Y-12-31');
$contact_id = $_GET['contact_id'] ?? null;

// Fetch all donations with filters
$donations = fetch_all_donations($start_date, $end_date, $contact_id);

// Calculate total amount
$total_amount = 0;
foreach ($donations as $donation) {
    $total_amount += $donation['amount'];
}

// Fetch contacts for filter dropdown
$query = "SELECT id, name FROM acc_contacts ORDER BY name ASC";
$contacts_result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Donations</title>
    <?php include '../../include/header.php'; ?>
</head>

<body>
    <?php include '../../include/menu.php'; ?>

    <div class="container mt-5">
        <div class="d-flex align-items-center mb-4">
            <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
            <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
            <h2 class="ms-3">Donations</h2>
        </div>

        <?php if ($status === 'success'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('success', 'Donation Recorded', 'The donation has been successfully recorded in the system.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php elseif ($status === 'updated'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('success', 'Donation Updated', 'The donation information has been successfully updated.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php elseif ($status === 'deleted'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('success', 'Donation Deleted', 'The donation record has been successfully removed.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php endif; ?>

        <?php if ($emailStatus === 'sent'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('success', 'Receipt Emailed', 'The donation receipt email was sent to the donor.', 'club-logo');
                    }, 700);
                });
            </script>
        <?php elseif ($emailStatus === 'failed'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('warning', 'Email Failed', 'Donation saved but the receipt email could not be sent.', 'club-logo');
                    }, 700);
                });
            </script>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header">
                <h3>Filter Donations</h3>
            </div>
            <div class="card-body">
                <form action="list.php" method="GET" class="row">
                    <div class="col-md-3 mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="contact_id" class="form-label">Donor</label>
                        <select id="contact_id" name="contact_id" class="form-control">
                            <option value="">All Donors</option>
                            <?php while ($contact = $contacts_result->fetch_assoc()): ?>
                                <option value="<?php echo $contact['id']; ?>" <?php echo $contact_id == $contact['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($contact['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="list.php" class="btn btn-secondary ms-2">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3>Donation List</h3>
                <div>
                    <a href="add.php" class="btn btn-success">Add New Donation</a>
                    <div class="btn-group">
                        <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="receipt.php?action=generate_all&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&contact_id=<?php echo $contact_id; ?>">Generate All Receipts</a></li>
                            <li><a class="dropdown-item" href="receipt.php?action=email_all&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&contact_id=<?php echo $contact_id; ?>">Email All Receipts</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../reports/donation_summary.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">Donation Summary Report</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h4>Total: $<?php echo number_format($total_amount, 2); ?></h4>
                </div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Donor</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Tax Deductible</th>
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
                                <td><?php echo htmlspecialchars($donation['description']); ?></td>
                                <td><?php echo $donation['tax_deductible'] ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <?php if ($donation['receipt_sent']): ?>
                                        <span class="badge bg-success">Yes - <?php echo $donation['receipt_date']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit.php?id=<?php echo $donation['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <button type="button" class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="receipt.php?id=<?php echo $donation['id']; ?>">Generate Receipt</a></li>
                                            <li><a class="dropdown-item" href="receipt.php?id=<?php echo $donation['id']; ?>&email=1">Email Receipt</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="delete.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this donation?');" class="d-inline">
                                                    <input type="hidden" name="id" value="<?php echo $donation['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>
</body>

</html>