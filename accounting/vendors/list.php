<-- /accounting/vendors/list.php
    <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/vendor_controller.php';

    // Validate session
    validate_session();

    // Ensure CSRF token
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Get status message if any
    $status = $_GET['status'] ?? null;

    // Fetch all vendors
    $vendors = fetch_all_vendors();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Vendors</title>
        <?php include '../../include/header.php'; ?>
    </head>

    <body>
        <?php include '../../include/menu.php'; ?>

        <div class="container mt-5">
            <div class="d-flex align-items-center mb-4">
                <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
                <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
                <h2 class="ms-3">Vendors</h2>
            </div>

            <?php if ($status === 'success'): ?>
                <div class="alert alert-success">Vendor added successfully!</div>
            <?php elseif ($status === 'updated'): ?>
                <div class="alert alert-success">Vendor updated successfully!</div>
            <?php elseif ($status === 'deleted'): ?>
                <div class="alert alert-success">Vendor deleted successfully!</div>
            <?php elseif ($status === 'error'): ?>
                <div class="alert alert-danger">An error occurred. Please try again.</div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3>Vendor List</h3>
                    <a href="add.php" class="btn btn-success">Add New Vendor</a>
                </div>
                <div class="card-body">
                    <table class="table table-striped" id="vendorsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendors as $vendor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['contact_name']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['phone']); ?></td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $vendor['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <form action="delete.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this vendor?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="id" value="<?php echo $vendor['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
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
                $('#vendorsTable').DataTable({
                    order: [
                        [0, 'asc']
                    ], // Sort by name ascending
                    pageLength: 25
                });
            });
        </script>
    </body>

    </html>