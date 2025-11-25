<?php
// /accounting/vendors/add.php
require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../controllers/vendorController.php';
require_once __DIR__ . '/../../include/premium_hero.php';

// Validate session
validate_session();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $contact_name = $_POST['contact_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (add_vendor($name, $contact_name, $email, $phone, $address, $notes)) {
        header('Location: list.php?status=success');
        exit();
    } else {
        $error_message = "Failed to add vendor. Please try again.";
    }
}
?>
<!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Add Vendor</title>
        <?php include '../../include/header.php'; ?>
    </head>

    <body>
        <?php include '../../include/menu.php'; ?>

        <div class="page-container" style="margin-top:0;padding-top:0;">
            <?php if (function_exists('renderPremiumHero')): ?>
                <?php renderPremiumHero([
                    'eyebrow' => 'Vendor Operations',
                    'title' => 'Add Vendor',
                    'subtitle' => 'Capture partner contact info, service notes, and readiness in one step.',
                    'theme' => 'cobalt',
                    'size' => 'compact',
                    'media_mode' => 'none',
                    'chips' => [
                        'Quick form entry',
                        'Secure contact storage'
                    ],
                    'actions' => [
                        [
                            'label' => 'Back to Vendors',
                            'url' => '/accounting/vendors/list.php',
                            'variant' => 'outline',
                            'icon' => 'fa-table-list'
                        ],
                        [
                            'label' => 'Accounting Dashboard',
                            'url' => '/accounting/dashboard.php',
                            'variant' => 'outline',
                            'icon' => 'fa-arrow-left'
                        ]
                    ],
                ]); ?>
            <?php else: ?>
                <section class="hero hero-small mb-4">
                    <div class="hero-body py-3">
                        <div class="container-fluid">
                            <div class="row align-items-center">
                                <div class="col-md-2 d-none d-md-flex justify-content-center">
                                    <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                                    <img src="<?= htmlspecialchars($logoSrc); ?>" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
                                </div>
                                <div class="col-md-6 text-center text-md-start text-white">
                                    <h1 class="h4 mb-1">Add Vendor</h1>
                                    <p class="mb-0 small">Centralize contact records for trusted partners.</p>
                                </div>
                                <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                                    <a href="list.php" class="btn btn-outline-light btn-sm me-2">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Vendors
                                    </a>
                                    <a href="/accounting/dashboard.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-home me-1"></i>Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <div class="container mt-4">
                <div class="card shadow">
                <div class="card-header">
                    <h3>Add New Vendor</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form action="add.php" method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Vendor Name</label>
                                <input type="text" id="name" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_name" class="form-label">Contact Person</label>
                                <input type="text" id="contact_name" name="contact_name" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">Add Vendor</button>
                        <a href="list.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
                </div>
            </div>
        </div>

        <?php include '../../include/footer.php'; ?>
    </body>

    </html>