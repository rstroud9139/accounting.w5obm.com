<-- /accounting/vendors/add.php
    <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/vendor_controller.php';

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

        <div class="container mt-5">
            <div class="d-flex align-items-center mb-4">
                <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
                <h2 class="ms-3">Add Vendor</h2>
            </div>

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

        <?php include '../../include/footer.php'; ?>
    </body>

    </html>