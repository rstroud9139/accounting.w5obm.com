<?php
// /accounting/donations/edit.php
     require_once __DIR__ . '/../utils/session_manager.php';
     require_once __DIR__ . '/../../include/dbconn.php';
     require_once __DIR__ . '/../controllers/donation_controller.php';

    // Validate session
    validate_session();

    // Get donation ID
    $id = $_GET['id'] ?? null;

    if (!$id) {
        header('Location: list.php?status=invalid_request');
        exit();
    }

    // Fetch donation data
    $donation = fetch_donation_by_id($id);

    if (!$donation) {
        header('Location: list.php?status=not_found');
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contact_id = $_POST['contact_id'];
        $amount = $_POST['amount'];
        $donation_date = $_POST['donation_date'];
        $description = $_POST['description'];
        $tax_deductible = isset($_POST['tax_deductible']) ? true : false;
        $notes = $_POST['notes'] ?? '';

        if (update_donation($id, $contact_id, $amount, $donation_date, $description, $tax_deductible, $notes)) {
            header('Location: list.php?status=updated');
            exit();
        } else {
            $error_message = "Failed to update donation.";
        }
    }

    // Fetch contacts for dropdown
    $query = "SELECT id, name FROM acc_contacts ORDER BY name ASC";
    $contacts_result = $conn->query($query);
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Edit Donation</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>

     <div class="container mt-5">
         <div class="d-flex align-items-center mb-4">
             <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
             <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
             <h2 class="ms-3">Edit Donation</h2>
         </div>

         <div class="card shadow">
             <div class="card-header">
                 <h3>Edit Donation</h3>
             </div>
             <div class="card-body">
                 <?php if (isset($error_message)): ?>
                     <div class="alert alert-danger"><?php echo $error_message; ?></div>
                 <?php endif; ?>
                 <form action="edit.php?id=<?php echo $id; ?>" method="POST">
                     <div class="mb-3">
                         <label for="contact_id" class="form-label">Donor</label>
                         <select id="contact_id" name="contact_id" class="form-control" required>
                             <?php while ($contact = $contacts_result->fetch_assoc()): ?>
                                 <option value="<?php echo $contact['id']; ?>" <?php echo $donation['contact_id'] == $contact['id'] ? 'selected' : ''; ?>>
                                     <?php echo htmlspecialchars($contact['name']); ?>
                                 </option>
                             <?php endwhile; ?>
                         </select>
                     </div>
                     <div class="mb-3">
                         <label for="amount" class="form-label">Amount</label>
                         <input type="number" step="0.01" id="amount" name="amount" class="form-control" value="<?php echo $donation['amount']; ?>" required>
                     </div>
                     <div class="mb-3">
                         <label for="donation_date" class="form-label">Donation Date</label>
                         <input type="date" id="donation_date" name="donation_date" class="form-control" value="<?php echo $donation['donation_date']; ?>" required>
                     </div>
                     <div class="mb-3">
                         <label for="description" class="form-label">Description</label>
                         <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($donation['description']); ?></textarea>
                     </div>
                     <div class="mb-3 form-check">
                         <input type="checkbox" class="form-check-input" id="tax_deductible" name="tax_deductible" <?php echo $donation['tax_deductible'] ? 'checked' : ''; ?>>
                         <label class="form-check-label" for="tax_deductible">Tax Deductible</label>
                     </div>
                     <div class="mb-3">
                         <label for="notes" class="form-label">Notes</label>
                         <textarea id="notes" name="notes" class="form-control"><?php echo htmlspecialchars($donation['notes']); ?></textarea>
                     </div>
                     <button type="submit" class="btn btn-primary">Update Donation</button>
                     <a href="list.php" class="btn btn-secondary">Cancel</a>
                 </form>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>