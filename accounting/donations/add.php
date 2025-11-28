<?php
// /accounting/donations/add.php
     require_once __DIR__ . '/../utils/session_manager.php';
     require_once __DIR__ . '/../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/donation_controller.php';
    require_once __DIR__ . '/../utils/email_utils.php';
    require_once __DIR__ . '/../lib/email_bridge.php';
    require_once __DIR__ . '/../../include/premium_hero.php';

    // Validate session
    validate_session();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contact_id = $_POST['contact_id'];
        $amount = $_POST['amount'];
        $donation_date = $_POST['donation_date'];
        $description = $_POST['description'];
        $tax_deductible = isset($_POST['tax_deductible']) ? true : false;
        $notes = $_POST['notes'] ?? '';

        if (add_donation($contact_id, $amount, $donation_date, $description, $tax_deductible, $notes)) {
            // Attempt to send a receipt email upon successful insert
            $donation_id = $conn->insert_id ?? null;
            if ($donation_id) {
                // Fetch donation + contact details for composing the email
                $donation = fetch_donation_by_id($donation_id);
                if ($donation && !empty($donation['contact_email'])) {
                    list($subject, $htmlBody, $textBody) = compose_donation_receipt_email($donation);
                    $sent = accounting_email_send_simple($donation['contact_email'], $subject, $htmlBody, true);
                    $sentOk = is_array($sent) ? ($sent['success'] ?? false) : (bool)$sent;
                    if ($sentOk) {
                        // Mark receipt as sent
                        mark_receipt_sent($donation_id);
                        header('Location: list.php?status=success&email=sent');
                        exit();
                    } else {
                        // Non-blocking: donation saved but email failed
                        header('Location: list.php?status=success&email=failed');
                        exit();
                    }
                }
            }
            // Fallback redirect if no email could be attempted
            header('Location: list.php?status=success');
            exit();
        } else {
            $error_message = "Failed to add donation.";
        }
    }

    // Fetch contacts for dropdown
    $query = "SELECT id, name FROM acc_contacts ORDER BY name ASC";
    $contacts_result = $conn->query($query);
    $contacts = $contacts_result ? $contacts_result->fetch_all(MYSQLI_ASSOC) : [];
    if ($contacts_result instanceof mysqli_result) {
        $contacts_result->free();
    }
    $contactCount = count($contacts);
    $defaultDonationDate = date('Y-m-d');

    $donationAddHeroChips = [
        'Mode: Manual entry',
        'Auto receipt: enabled',
        'Tax default: deductible',
    ];

    $donationAddHeroHighlights = [
        [
            'label' => 'Donors Available',
            'value' => number_format($contactCount),
            'meta' => 'From contacts',
        ],
        [
            'label' => 'Default Date',
            'value' => date('M j, Y', strtotime($defaultDonationDate)),
            'meta' => 'Prefilled',
        ],
        [
            'label' => 'Receipt Emails',
            'value' => 'Enabled',
            'meta' => 'Sends on save',
        ],
    ];

    $donationAddHeroActions = [
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
     <title>Add Donation</title>
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
             'title' => 'Record Donation',
             'subtitle' => 'Capture gifts, issue receipts, and keep the tax trail tight.',
             'chips' => $donationAddHeroChips,
             'highlights' => $donationAddHeroHighlights,
             'actions' => $donationAddHeroActions,
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
                                 <h1 class="h4 mb-1">Add Donation</h1>
                                 <p class="mb-0 small">Register a contribution and optionally email the receipt.</p>
                             </div>
                             <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                 <a href="/accounting/donations/" class="btn btn-outline-light btn-sm me-2">
                                     <i class="fas fa-arrow-left me-1"></i>Donations Workspace
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
                 <h3>New Donation</h3>
             </div>
             <div class="card-body">
                 <?php if (isset($error_message)): ?>
                     <div class="alert alert-danger"><?php echo $error_message; ?></div>
                 <?php endif; ?>
                 <form action="add.php" method="POST">
                     <div class="mb-3">
                         <label for="contact_id" class="form-label">Donor</label>
                         <select id="contact_id" name="contact_id" class="form-control" required>
                             <option value="">Select a donor</option>
                             <?php foreach ($contacts as $contact): ?>
                                 <option value="<?= (int)$contact['id']; ?>"><?= htmlspecialchars($contact['name']); ?></option>
                             <?php endforeach; ?>
                         </select>
                         <small class="form-text text-muted">
                             <a href="#" data-bs-toggle="modal" data-bs-target="#newContactModal">Add new donor</a>
                         </small>
                     </div>
                     <div class="mb-3">
                         <label for="amount" class="form-label">Amount</label>
                         <input type="number" step="0.01" id="amount" name="amount" class="form-control" required>
                     </div>
                     <div class="mb-3">
                         <label for="donation_date" class="form-label">Donation Date</label>
                         <input type="date" id="donation_date" name="donation_date" class="form-control" value="<?= htmlspecialchars($defaultDonationDate); ?>" required>
                     </div>
                     <div class="mb-3">
                         <label for="description" class="form-label">Description</label>
                         <textarea id="description" name="description" class="form-control"></textarea>
                     </div>
                     <div class="mb-3 form-check">
                         <input type="checkbox" class="form-check-input" id="tax_deductible" name="tax_deductible" checked>
                         <label class="form-check-label" for="tax_deductible">Tax Deductible</label>
                     </div>
                     <div class="mb-3">
                         <label for="notes" class="form-label">Notes</label>
                         <textarea id="notes" name="notes" class="form-control"></textarea>
                     </div>
                     <button type="submit" class="btn btn-success">Add Donation</button>
                     <a href="list.php" class="btn btn-secondary">Cancel</a>
                 </form>
             </div>
         </div>
    </div>

     <!-- Modal for adding a new contact -->
     <div class="modal fade" id="newContactModal" tabindex="-1" aria-labelledby="newContactModalLabel" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h5 class="modal-title" id="newContactModalLabel">Add New Donor</h5>
                     <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                 </div>
                 <div class="modal-body">
                     <form id="contactForm">
                         <div class="mb-3">
                             <label for="name" class="form-label">Name</label>
                             <input type="text" class="form-control" id="name" name="name" required>
                         </div>
                         <div class="mb-3">
                             <label for="email" class="form-label">Email</label>
                             <input type="email" class="form-control" id="email" name="email">
                         </div>
                         <div class="mb-3">
                             <label for="phone" class="form-label">Phone</label>
                             <input type="text" class="form-control" id="phone" name="phone">
                         </div>
                         <div class="mb-3">
                             <label for="address" class="form-label">Address</label>
                             <textarea class="form-control" id="address" name="address"></textarea>
                         </div>
                         <div class="mb-3">
                             <label for="tax_id" class="form-label">Tax ID (optional)</label>
                             <input type="text" class="form-control" id="tax_id" name="tax_id">
                         </div>
                     </form>
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                     <button type="button" class="btn btn-primary" id="saveContact">Save Donor</button>
                 </div>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>

     <script>
         // AJAX to add a new contact
         document.getElementById('saveContact').addEventListener('click', function() {
             const formData = new FormData(document.getElementById('contactForm'));

             fetch('add_contact_ajax.php', {
                     method: 'POST',
                     body: formData
                 })
                 .then(response => response.json())
                 .then(data => {
                     if (data.status === 'success') {
                         // Add the new contact to the dropdown
                         const select = document.getElementById('contact_id');
                         const option = document.createElement('option');
                         option.value = data.contact_id;
                         option.text = data.name;
                         option.selected = true;
                         select.add(option);

                         // Close the modal
                         const modal = bootstrap.Modal.getInstance(document.getElementById('newContactModal'));
                         modal.hide();
                     } else {
                         alert('Error: ' + data.message);
                     }
                 })
                 .catch(error => {
                     console.error('Error:', error);
                     alert('An error occurred. Please try again.');
                 });
         });
     </script>
 </body>

 </html>