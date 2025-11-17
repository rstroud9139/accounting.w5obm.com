<!-- /accounting/categories/list.php -->
<?php
require_once __DIR__ . '/../utils/session_manager.php';
require_once '../../include/dbconn.php';
require_once __DIR__ . '/../controllers/category_controller.php';

// Validate session
validate_session();

// Get status message if any
$status = $_GET['status'] ?? null;

// Fetch all categories
$categories = fetch_all_categories();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Categories</title>
    <?php include '../../include/header.php'; ?>
</head>

<body>
    <?php include '../../include/menu.php'; ?>

    <div class="container mt-5">
        <div class="d-flex align-items-center mb-4">
            <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
            <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
            <h2 class="ms-3">Categories</h2>
        </div>

        <?php if ($status === 'success'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('success', 'Category Added', 'The category has been successfully added to the system.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php elseif ($status === 'updated'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('success', 'Category Updated', 'The category has been successfully updated.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php elseif ($status === 'deleted'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('success', 'Category Deleted', 'The category has been successfully removed from the system.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php elseif ($status === 'error'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('error', 'Operation Failed', 'An error occurred while processing your request. Please try again.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php elseif ($status === 'in_use'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showToast('warning', 'Cannot Delete', 'This category is currently in use by existing transactions and cannot be deleted.', 'club-logo');
                    }, 500);
                });
            </script>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header">
                <h3>Category List</h3>
                <a href="add.php" class="btn btn-success float-end">Add New Category</a>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo $category['type']; ?></td>
                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                <td>
                                    <a href="edit.php?id=<?php echo $category['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <form action="delete.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ($_SESSION['csrf_token'] = bin2hex(random_bytes(32)))) ?>">
                                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?');">Delete</button>
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
</body>

</html>