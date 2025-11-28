<?php
// /accounting/donations/edit.php
     require_once __DIR__ . '/../utils/session_manager.php';
     require_once __DIR__ . '/../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/donation_controller.php';
    require_once __DIR__ . '/../../include/premium_hero.php';

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
    $contacts = $contacts_result ? $contacts_result->fetch_all(MYSQLI_ASSOC) : [];
    if ($contacts_result instanceof mysqli_result) {
        $contacts_result->free();
    }

    $donorName = '';
    foreach ($contacts as $contactOption) {
        if ((int)$contactOption['id'] === (int)$donation['contact_id']) {
            $donorName = $contactOption['name'];
            break;
        }
    }
    $donorName = $donorName !== '' ? $donorName : 'Selected donor';
    $receiptWasSent = !empty($donation['receipt_sent']);

    $donationEditHeroChips = array_filter([
        'Donation #' . (int)$id,
        'Donor: ' . htmlspecialchars($donorName),
        'Tax: ' . ($donation['tax_deductible'] ? 'Deductible' : 'Non-deductible'),
    ]);

    $donationEditHeroHighlights = [
        [
            'label' => 'Amount',
            'value' => '$' . number_format((float)$donation['amount'], 2),
            'meta' => 'Editable',
        ],
        [
            'label' => 'Donation Date',
            'value' => date('M j, Y', strtotime($donation['donation_date'])),
            'meta' => 'Calendar field',
        ],
        [
            'label' => 'Receipt',
            'value' => $receiptWasSent ? 'Sent' : 'Pending',
            'meta' => $donation['receipt_date'] ?? 'Update after save',
        ],
    ];

    $donationEditHeroActions = [
        [
            'label' => 'View Donations',
            'url' => '/accounting/donations/',
            'variant' => 'outline',
            'icon' => 'fa-list',
        ],
        [
            'label' => 'Receipt Center',
            'url' => '/accounting/donations/receipt.php',
            'variant' => 'outline',
            'icon' => 'fa-receipt',
        ],
        [
            'label' => 'Accounting Dashboard',
            'url' => '/accounting/dashboard.php',
            'variant' => 'outline',
            'icon' => 'fa-arrow-left',
        ],
    ];
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Edit Donation</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>

     <?php
     if (function_exists('displayToastMessage')) {
         displayToastMessage();
     }
     ?>

     <?php if (function_exists('renderPremiumHero')): ?>
         <?php renderPremiumHero([
             'eyebrow' => 'Donations Workspace',
             'title' => 'Edit Donation',
             'subtitle' => 'Correct donor info, update receipts, and keep the ledger current.',
             'chips' => $donationEditHeroChips,
             'highlights' => $donationEditHeroHighlights,
             'actions' => $donationEditHeroActions,
             'theme' => 'berry',
             'size' => 'compact',
             'media_mode' => 'none',
         ]); ?>
     <?php endif; ?>

     <div class="page-container">
         <?php if (!function_exists('renderPremiumHero')): ?>
             <?php $fallbackLogo = accounting_logo_src_for(__DIR__); ?>
             <section class="hero hero-small mb-4">
                 <div class="hero-body py-3">
                     <div class="container-fluid">
                         <div class="row align-items-center">
                             <div class="col-md-2 d-none d-md-flex justify-content-center">
                                 <img src="<?= htmlspecialchars($fallbackLogo); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                             </div>
                             <div class="col-md-6 text-center text-md-start text-white">
                                 <h1 class="h4 mb-1">Edit Donation</h1>
                                 <p class="mb-0 small">Update contribution details and receipt status.</p>
                             </div>
                             <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                 <a href="/accounting/donations/" class="btn btn-outline-light btn-sm me-2">
                                     <i class="fas fa-arrow-left me-1"></i>Back to Donations
                                 </a>
                                 <a href="/accounting/donations/receipt.php" class="btn btn-primary btn-sm">
                                     <i class="fas fa-receipt me-1"></i>Receipt Center
                                 </a>
                             </div>
                         </div>
                     </div>
                 </div>
             </section>
         <?php endif; ?>

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
                             <?php foreach ($contacts as $contact): ?>
                                 <option value="<?= (int)$contact['id']; ?>" <?= (int)$donation['contact_id'] === (int)$contact['id'] ? 'selected' : ''; ?>>
                                     <?= htmlspecialchars($contact['name']); ?>
                                 </option>
                             <?php endforeach; ?>
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